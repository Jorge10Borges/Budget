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

    $valuation_id = isset($_GET['valuation_id']) ? (int)$_GET['valuation_id'] : null;
    $project_item_id = isset($_GET['project_item_id']) ? (int)$_GET['project_item_id'] : null;

    if ($valuation_id) {
        $stmt = $mysqli->prepare("SELECT * FROM valuation_items WHERE valuation_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $valuation_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($project_item_id) {
        $stmt = $mysqli->prepare("SELECT * FROM valuation_items WHERE project_item_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $project_item_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode(['data' => $rows]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'valuation_id or project_item_id required']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
