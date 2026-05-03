<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

auth_send_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$ctx = auth_get_session_context($mysqli);
if (!$ctx) {
    echo json_encode([
        'data' => null,
        'authenticated' => false,
    ]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'data' => [
        'user' => [
            'id' => (int)$ctx['user_id'],
            'company_id' => (int)$ctx['company_id'],
            'full_name' => (string)$ctx['full_name'],
            'email' => (string)$ctx['email'],
            'role' => (string)$ctx['role'],
        ],
        'company' => [
            'id' => (int)$ctx['company_id'],
            'legal_name' => (string)($ctx['legal_name'] ?? ''),
            'trade_name' => (string)($ctx['trade_name'] ?? ''),
        ],
        'license' => [
            'id' => (int)$ctx['license']['id'],
            'plan_name' => (string)$ctx['license']['plan_name'],
            'max_users' => (int)$ctx['license']['max_users'],
            'ends_at' => $ctx['license']['ends_at'],
        ]
    ]
]);
