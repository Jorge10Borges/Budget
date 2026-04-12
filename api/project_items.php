<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Helper to read JSON body
function get_json_body() {
    $raw = file_get_contents('php://input');
    if (!$raw) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
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
    }

    // Crear nueva partida
    if ($method === 'POST') {
        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }
        $project_id = isset($body['project_id']) ? (int)$body['project_id'] : null;
        $item_id = isset($body['item_id']) ? $body['item_id'] : null;
        $qty = isset($body['qty']) ? (float)$body['qty'] : 0;
        $unit_cost = isset($body['unit_cost']) ? (float)$body['unit_cost'] : 0;
        if (!$project_id || !$item_id) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id and item_id are required']);
            exit;
        }
        $total_cost = round($qty * $unit_cost, 2);
        $ins = $mysqli->prepare("INSERT INTO project_items (project_id, item_id, qty, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param('issdd', $project_id, $item_id, $qty, $unit_cost, $total_cost);
        if (!$ins->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Insert failed', 'message' => $ins->error]);
            exit;
        }
        $newId = $ins->insert_id;
        $ins->close();
        echo json_encode(['data' => ['id' => $newId, 'project_id' => $project_id, 'item_id' => $item_id, 'qty' => $qty, 'unit_cost' => $unit_cost, 'total_cost' => $total_cost]]);
        exit;
    }

    // Actualizar partida existente
    if ($method === 'PUT' || $method === 'PATCH') {
        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }
        $id = isset($body['id']) ? (int)$body['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required for update']);
            exit;
        }
        // Obtener valores actuales
        $sel = $mysqli->prepare("SELECT qty, unit_cost FROM project_items WHERE id = ?");
        $sel->bind_param('i', $id);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res->fetch_assoc();
        $sel->close();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            exit;
        }
        $qty = isset($body['qty']) ? (float)$body['qty'] : (float)$row['qty'];
        $unit_cost = isset($body['unit_cost']) ? (float)$body['unit_cost'] : (float)$row['unit_cost'];
        $total_cost = round($qty * $unit_cost, 2);
        $upd = $mysqli->prepare("UPDATE project_items SET qty = ?, unit_cost = ?, total_cost = ? WHERE id = ?");
        $upd->bind_param('dddi', $qty, $unit_cost, $total_cost, $id);
        if (!$upd->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'message' => $upd->error]);
            exit;
        }
        $upd->close();
        echo json_encode(['data' => ['id' => $id, 'qty' => $qty, 'unit_cost' => $unit_cost, 'total_cost' => $total_cost]]);
        exit;
    }

    // Eliminar partida
    if ($method === 'DELETE') {
        // support id via query or JSON
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if (!$id) {
            $body = get_json_body();
            $id = isset($body['id']) ? (int)$body['id'] : null;
        }
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required for delete']);
            exit;
        }
        $del = $mysqli->prepare("DELETE FROM project_items WHERE id = ?");
        $del->bind_param('i', $id);
        if (!$del->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed', 'message' => $del->error]);
            exit;
        }
        $del->close();
        echo json_encode(['data' => ['id' => $id, 'deleted' => true]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
