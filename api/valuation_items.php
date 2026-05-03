<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/auth_middleware.php';

auth_send_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $auth = require_auth($mysqli);
    $company_id = (int)$auth['company_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $valuation_id = isset($_GET['valuation_id']) ? (int)$_GET['valuation_id'] : null;
    $project_item_id = isset($_GET['project_item_id']) ? (int)$_GET['project_item_id'] : null;
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

    if ($project_id) {
        $sql = "SELECT vi.project_item_id,
                       SUM(COALESCE(vi.qty, 0)) AS qty_valued
                FROM valuation_items vi
                INNER JOIN valuations v ON v.id = vi.valuation_id
                INNER JOIN projects p ON p.id = v.project_id
                WHERE v.project_id = ? AND p.company_id = ?
                GROUP BY vi.project_item_id
                ORDER BY vi.project_item_id ASC";
        $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ii', $project_id, $company_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($valuation_id) {
        $sql = "SELECT vi.*, 
                       COALESCE(i.name, '') AS item_name,
                       COALESCE(i.unit, '') AS unit
                FROM valuation_items vi
                LEFT JOIN project_items pi ON pi.id = vi.project_item_id
                LEFT JOIN items i ON i.id = pi.item_id
                INNER JOIN valuations v ON v.id = vi.valuation_id
                INNER JOIN projects p ON p.id = v.project_id
                WHERE vi.valuation_id = ? AND p.company_id = ?
                ORDER BY vi.id ASC";
        $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ii', $valuation_id, $company_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($project_item_id) {
        $stmt = $mysqli->prepare("SELECT vi.*
                      FROM valuation_items vi
                      INNER JOIN valuations v ON v.id = vi.valuation_id
                      INNER JOIN projects p ON p.id = v.project_id
                      WHERE vi.project_item_id = ? AND p.company_id = ?
                      ORDER BY vi.id ASC");
        $stmt->bind_param('ii', $project_item_id, $company_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode(['data' => $rows]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'project_id, valuation_id or project_item_id required']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
