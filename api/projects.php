<?php
// CRUD endpoint for projects
header('Content-Type: application/json; charset=utf-8');
// Allow CORS for local development (adjust in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Helper to read JSON body
function get_json_body() {
    $body = file_get_contents('php://input');
    if (!$body) return [];
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

// Helper to prepare bind_param with dynamic args
function refValues($arr){
    $refs = [];
    foreach($arr as $key => $value) $refs[$key] = &$arr[$key];
    return $refs;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($method === 'GET') {
        if ($id) {
            // Return single project with calculated budget from project_items
            $sql = "SELECT p.*, COALESCE(t.calc_budget, 0) AS calculated_budget
                    FROM projects p
                    LEFT JOIN (
                        SELECT pi.project_id,
                               SUM(COALESCE(pi.total_cost, pi.qty * COALESCE(pi.unit_cost, i.unit_cost))) AS calc_budget
                        FROM project_items pi
                        LEFT JOIN items i ON i.id = pi.item_id
                        GROUP BY pi.project_id
                    ) t ON t.project_id = p.id
                    WHERE p.id = ? LIMIT 1";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            echo json_encode(['data' => $row]);
            $stmt->close();
            exit;
        } else {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            // List projects with calculated budget
            $sql = "SELECT p.id, p.external_id, p.name, p.description, p.client, p.owner_user_id, p.status,
                           COALESCE(t.calc_budget, 0) AS calculated_budget, p.currency, p.start_date, p.end_date, p.is_active, p.created_at, p.updated_at
                    FROM projects p
                    LEFT JOIN (
                        SELECT pi.project_id,
                               SUM(COALESCE(pi.total_cost, pi.qty * COALESCE(pi.unit_cost, i.unit_cost))) AS calc_budget
                        FROM project_items pi
                        LEFT JOIN items i ON i.id = pi.item_id
                        GROUP BY pi.project_id
                    ) t ON t.project_id = p.id
                    WHERE p.deleted_at IS NULL
                    ORDER BY p.id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            echo json_encode(['data' => $rows]);
            $stmt->close();
            exit;
        }
    }

    if ($method === 'POST') {
        $data = get_json_body();
        if (empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'name is required']);
            exit;
        }
        $external_id = isset($data['external_id']) ? $data['external_id'] : null;
        $name = $data['name'];
        $description = isset($data['description']) ? $data['description'] : null;
        $client = isset($data['client']) ? $data['client'] : null;
        $owner_user_id = isset($data['owner_user_id']) ? (int)$data['owner_user_id'] : null;
        $status = isset($data['status']) ? $data['status'] : 'draft';
        $currency = isset($data['currency']) ? $data['currency'] : 'USD';
        $start_date = isset($data['start_date']) ? $data['start_date'] : null;
        $end_date = isset($data['end_date']) ? $data['end_date'] : null;
        $last_activity = isset($data['last_activity']) ? $data['last_activity'] : null;
        $collected = isset($data['collected']) ? (float)$data['collected'] : 0.00;
        $spent = isset($data['spent']) ? (float)$data['spent'] : 0.00;
        $metadata = isset($data['metadata']) ? $data['metadata'] : null;
        $metadata = is_array($metadata) ? json_encode($metadata) : $metadata;
        $stmt = $mysqli->prepare('INSERT INTO projects (external_id, name, description, client, owner_user_id, status, currency, start_date, end_date, last_activity, collected, spent, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $types = 'ssssisssssdds';
        $bind = [$types, $external_id, $name, $description, $client, $owner_user_id, $status, $currency, $start_date, $end_date, $last_activity, $collected, $spent, $metadata];
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
        $ok = $stmt->execute();
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['error' => 'Insert failed', 'message' => $stmt->error]);
            exit;
        }
        $newId = $stmt->insert_id;
        $stmt->close();
        http_response_code(201);
        echo json_encode(['data' => ['id' => $newId]]);
        exit;
    }

    if ($method === 'PUT') {
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
        $data = get_json_body();
        $fields = [];
        $values = [];
        $allowed = ['external_id','name','description','client','owner_user_id','status','currency','start_date','end_date','last_activity','collected','spent','metadata','is_active'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                if ($f === 'owner_user_id' || $f === 'is_active') {
                    $values[] = $data[$f] === null ? null : (int)$data[$f];
                } elseif ($f === 'collected' || $f === 'spent') {
                    $values[] = $data[$f] === null ? null : (float)$data[$f];
                } elseif ($f === 'metadata' && is_array($data[$f])) {
                    $values[] = json_encode($data[$f]);
                } else {
                    $values[] = $data[$f];
                }
            }
        }
        if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'no fields to update']); exit; }
        // determine types
        $types = '';
        foreach ($values as $v) {
            if (is_int($v)) $types .= 'i';
            elseif (is_float($v) || is_double($v)) $types .= 'd';
            else $types .= 's';
        }
        $types .= 'i'; // id
        $values[] = $id;
        $sql = 'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ? LIMIT 1';
        $stmt = $mysqli->prepare($sql);
        $bind = array_merge([$types], $values);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
        $ok = $stmt->execute();
        if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Update failed', 'message' => $stmt->error]); exit; }
        echo json_encode(['data' => ['id' => $id]]);
        $stmt->close();
        exit;
    }

    if ($method === 'DELETE') {
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
        $stmt = $mysqli->prepare('UPDATE projects SET deleted_at = NOW(), is_active = 0 WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Delete failed', 'message' => $stmt->error]); exit; }
        echo json_encode(['data' => ['id' => $id]]);
        $stmt->close();
        exit;
    }

    // Method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
