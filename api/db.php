<?php
// Simple mysqli connection helper
$config = require __DIR__ . '/config.php';

$mysqli = @new mysqli($config['host'], $config['user'], $config['pass'], $config['db'], $config['port'], $config['socket']);
if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed', 'message' => $mysqli->connect_error]);
    exit;
}

$mysqli->set_charset('utf8mb4');

// $mysqli is available to include()ing scripts
