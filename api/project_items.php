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

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
try {
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'project_id is required']);
        exit;
    }

    $sql = "SELECT pi.id, pi.project_id, pi.item_id, COALESCE(i.name, '') AS item_name, COALESCE(i.unit, '') AS unit,
                   COALESCE(pi.qty, 0) AS qty,
                   COALESCE(pi.unit_cost, i.unit_cost, 0) AS unit_cost,
                   COALESCE(pi.total_cost, (pi.qty * COALESCE(pi.unit_cost, i.unit_cost)), 0) AS total_cost,
                   pi.created_at
            FROM project_items pi
            LEFT JOIN items i ON i.id = pi.item_id
            WHERE pi.project_id = ?
            ORDER BY pi.id ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    echo json_encode(['data' => $rows]);
    $stmt->close();
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
