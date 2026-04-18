<?php
// Simple mysqli connection helper
$config = require __DIR__ . '/config.php';

try {
    $mysqli = @new mysqli($config['host'], $config['user'], $config['pass'], $config['db'], $config['port'], $config['socket']);
    if ($mysqli->connect_errno) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Database connection failed', 'message' => $mysqli->connect_error]);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
    exit;
}

$mysqli->set_charset('utf8mb4');

// $mysqli is available to include()ing scripts
