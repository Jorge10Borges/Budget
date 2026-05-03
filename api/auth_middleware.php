<?php

require_once __DIR__ . '/auth_common.php';

function require_auth(mysqli $mysqli): array {
    $ctx = auth_get_session_context($mysqli);
    if (!$ctx) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    return $ctx;
}

function require_role(array $ctx, array $allowedRoles): void {
    $role = strtolower((string)($ctx['role'] ?? ''));
    $allowed = array_map(static fn($r) => strtolower((string)$r), $allowedRoles);
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permisos insuficientes']);
        exit;
    }
}
