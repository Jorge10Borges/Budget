<?php

function auth_send_cors(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
    header('Access-Control-Max-Age: 600');
}

function auth_json_body(): ?array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function auth_cookie_name(): string {
    return 'budget_session';
}

function auth_cookie_path(): string {
    // Use root path so session works regardless of app base path (/ or /Budget).
    return '/';
}

function auth_set_session_cookie(string $token, int $ttlSeconds): void {
    $expires = time() + $ttlSeconds;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $domain = '192.168.1.200'; // Dominio explícito para compartir cookie entre puertos

    setcookie(auth_cookie_name(), $token, [
        'expires' => $expires,
        'path' => auth_cookie_path(),
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_clear_session_cookie(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $domain = '192.168.1.200';

    setcookie(auth_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => auth_cookie_path(),
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_find_active_license(mysqli $mysqli, int $companyId): ?array {
    $sql = "SELECT id, company_id, license_key, plan_name, max_users, starts_at, ends_at, status
            FROM licenses
            WHERE company_id = ?
              AND status = 'active'
              AND starts_at <= CURDATE()
              AND (ends_at IS NULL OR ends_at >= CURDATE())
            ORDER BY starts_at DESC, id DESC
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function auth_get_session_context(mysqli $mysqli): ?array {
    $token = $_COOKIE[auth_cookie_name()] ?? '';
    if (!is_string($token) || $token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $sql = "SELECT s.id AS session_id,
                   s.user_id,
                   s.expires_at,
                   u.company_id,
                   u.full_name,
                   u.email,
                   u.role,
                   u.is_active,
                   c.legal_name,
                   c.trade_name,
                   c.status AS company_status
            FROM sessions s
            INNER JOIN users u ON u.id = s.user_id
            INNER JOIN companies c ON c.id = u.company_id
            WHERE s.token_hash = ?
              AND s.revoked_at IS NULL
              AND s.expires_at > NOW()
            LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $ctx = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$ctx) {
        return null;
    }

    if ((int)($ctx['is_active'] ?? 0) !== 1) {
        return null;
    }

    if (strtolower((string)($ctx['company_status'] ?? '')) !== 'active') {
        return null;
    }

    $license = auth_find_active_license($mysqli, (int)$ctx['company_id']);
    if (!$license) {
        return null;
    }

    $touch = $mysqli->prepare('UPDATE sessions SET last_seen_at = NOW() WHERE id = ? LIMIT 1');
    if ($touch) {
        $sid = (int)$ctx['session_id'];
        $touch->bind_param('i', $sid);
        $touch->execute();
        $touch->close();
    }

    $ctx['license'] = $license;
    return $ctx;
}
