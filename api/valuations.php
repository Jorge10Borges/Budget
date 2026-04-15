<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        $stmt = $mysqli->prepare("SELECT * FROM valuations WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        echo json_encode($row ? ['data' => $row] : ['data' => null]);
        exit;
    }

    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'project_id is required']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT * FROM valuations WHERE project_id = ? ORDER BY date DESC, id DESC");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();

    echo json_encode(['data' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
