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

$token = $_COOKIE[auth_cookie_name()] ?? '';
if (is_string($token) && $token !== '') {
    $tokenHash = hash('sha256', $token);
    $upd = $mysqli->prepare('UPDATE sessions SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL');
    if ($upd) {
        $upd->bind_param('s', $tokenHash);
        $upd->execute();
        $upd->close();
    }
}

auth_clear_session_cookie();

echo json_encode(['data' => ['ok' => true]]);
