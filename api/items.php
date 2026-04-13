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

function get_json_body() {
    $raw = file_get_contents('php://input');
    if (!$raw) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // optional id
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            $stmt = $mysqli->prepare('SELECT id, name, unit, unit_cost, created_at FROM items WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Item not found']);
                exit;
            }
            echo json_encode(['data' => $row]);
            $stmt->close();
            exit;
        }

        $sql = 'SELECT id, name, unit, unit_cost, created_at FROM items ORDER BY name ASC';
        $res = $mysqli->query($sql);
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($method === 'POST') {
        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }
        $name = isset($body['name']) ? trim($body['name']) : '';
        $unit = isset($body['unit']) ? trim($body['unit']) : '';
        $unit_cost = isset($body['unit_cost']) ? (float)$body['unit_cost'] : 0.0;
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'name is required']);
            exit;
        }
        $ins = $mysqli->prepare('INSERT INTO items (name, unit, unit_cost) VALUES (?, ?, ?)');
        $ins->bind_param('ssd', $name, $unit, $unit_cost);
        if (!$ins->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Insert failed', 'message' => $ins->error]);
            exit;
        }
        $newId = $ins->insert_id;
        $ins->close();
        echo json_encode(['data' => ['id' => $newId, 'name' => $name, 'unit' => $unit, 'unit_cost' => $unit_cost]]);
        exit;
    }

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
        // fetch existing
        $sel = $mysqli->prepare('SELECT name, unit, unit_cost FROM items WHERE id = ?');
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
        $name = isset($body['name']) ? trim($body['name']) : $row['name'];
        $unit = isset($body['unit']) ? trim($body['unit']) : $row['unit'];
        $unit_cost = isset($body['unit_cost']) ? (float)$body['unit_cost'] : (float)$row['unit_cost'];

        $upd = $mysqli->prepare('UPDATE items SET name = ?, unit = ?, unit_cost = ? WHERE id = ?');
        $upd->bind_param('ssdi', $name, $unit, $unit_cost, $id);
        if (!$upd->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'message' => $upd->error]);
            exit;
        }
        $upd->close();
        echo json_encode(['data' => ['id' => $id, 'name' => $name, 'unit' => $unit, 'unit_cost' => $unit_cost]]);
        exit;
    }

    if ($method === 'DELETE') {
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
        $del = $mysqli->prepare('DELETE FROM items WHERE id = ?');
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
