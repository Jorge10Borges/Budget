<?php
// Simple example endpoint: returns list of projects
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

try {
    $res = $mysqli->query('SELECT id, name, currency, start_date, end_date FROM projects ORDER BY id DESC LIMIT 100');
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    echo json_encode(['data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'message' => $e->getMessage()]);
}
