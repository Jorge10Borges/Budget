<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

auth_send_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = auth_json_body();
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$email = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'email and password are required']);
    exit;
}

$sql = "SELECT u.id, u.company_id, u.full_name, u.email, u.password_hash, u.role, u.is_active,
               c.legal_name, c.trade_name, c.status AS company_status
        FROM users u
        INNER JOIN companies c ON c.id = u.company_id
        WHERE LOWER(u.email) = ?
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$user || (int)$user['is_active'] !== 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales inválidas']);
    exit;
}

if (!password_verify($password, (string)$user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales inválidas']);
    exit;
}

if (strtolower((string)($user['company_status'] ?? '')) !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Empresa inactiva']);
    exit;
}

$license = auth_find_active_license($mysqli, (int)$user['company_id']);
if (!$license) {
    http_response_code(403);
    echo json_encode(['error' => 'No hay licencia activa para esta empresa']);
    exit;
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$ttlSeconds = 60 * 60 * 8; // 8h
$expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$ins = $mysqli->prepare('INSERT INTO sessions (user_id, token_hash, ip_address, user_agent, expires_at, last_seen_at) VALUES (?, ?, ?, ?, ?, NOW())');
$uid = (int)$user['id'];
$ins->bind_param('issss', $uid, $tokenHash, $ip, $ua, $expiresAt);
if (!$ins->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo crear la sesión', 'message' => $ins->error]);
    $ins->close();
    exit;
}
$ins->close();

$upd = $mysqli->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ? LIMIT 1');
if ($upd) {
    $upd->bind_param('i', $uid);
    $upd->execute();
    $upd->close();
}

auth_set_session_cookie($token, $ttlSeconds);

echo json_encode([
    'data' => [
        'user' => [
            'id' => (int)$user['id'],
            'company_id' => (int)$user['company_id'],
            'full_name' => (string)$user['full_name'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ],
        'company' => [
            'legal_name' => (string)($user['legal_name'] ?? ''),
            'trade_name' => (string)($user['trade_name'] ?? ''),
        ],
        'license' => [
            'id' => (int)$license['id'],
            'plan_name' => (string)$license['plan_name'],
            'max_users' => (int)$license['max_users'],
            'ends_at' => $license['ends_at'],
        ]
    ]
]);
