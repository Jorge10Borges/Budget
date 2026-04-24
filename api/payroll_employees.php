<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
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
        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $include_inactive = isset($_GET['include_inactive']) && (string)$_GET['include_inactive'] === '1';

        if ($id > 0) {
                $sql = 'SELECT id, project_id, full_name, id_number, mobile_bank, mobile_id_number, mobile_phone, bank_account_number, bank_account_holder_name, bank_account_holder_id, crew, day_rate, night_rate, is_active, created_at, updated_at
                    FROM employees
                    WHERE id = ? AND project_id = ?
                    LIMIT 1';
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ii', $id, $project_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Employee not found']);
                exit;
            }

            echo json_encode(['data' => $row]);
            exit;
        }

        $sql = 'SELECT id, project_id, full_name, id_number, mobile_bank, mobile_id_number, mobile_phone, bank_account_number, bank_account_holder_name, bank_account_holder_id, crew, day_rate, night_rate, is_active, created_at, updated_at
                FROM employees
                WHERE project_id = ?';
        if (!$include_inactive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY full_name ASC';

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();

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

        $project_id = isset($body['project_id']) ? (int)$body['project_id'] : 0;
        $full_name = isset($body['full_name']) ? trim((string)$body['full_name']) : '';
        $id_number = isset($body['id_number']) ? trim((string)$body['id_number']) : '';
        $mobile_bank = isset($body['mobile_bank']) ? trim((string)$body['mobile_bank']) : '';
        $mobile_id_number = isset($body['mobile_id_number']) ? trim((string)$body['mobile_id_number']) : '';
        $mobile_phone = isset($body['mobile_phone']) ? trim((string)$body['mobile_phone']) : '';
        $bank_account_number = isset($body['bank_account_number']) ? trim((string)$body['bank_account_number']) : '';
        $bank_account_holder_name = isset($body['bank_account_holder_name']) ? trim((string)$body['bank_account_holder_name']) : '';
        $bank_account_holder_id = isset($body['bank_account_holder_id']) ? trim((string)$body['bank_account_holder_id']) : '';
        $crew = isset($body['crew']) ? strtolower(trim((string)$body['crew'])) : 'day';
        $day_rate = isset($body['day_rate']) ? (float)$body['day_rate'] : 0.0;
        $night_rate = isset($body['night_rate']) ? (float)$body['night_rate'] : 0.0;

        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        if ($full_name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'full_name is required']);
            exit;
        }
        if (!in_array($crew, ['day', 'night'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'crew must be day or night']);
            exit;
        }
        if ($day_rate < 0 || $night_rate < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Rates must be >= 0']);
            exit;
        }

        $stmt = $mysqli->prepare('INSERT INTO employees (project_id, full_name, id_number, mobile_bank, mobile_id_number, mobile_phone, bank_account_number, bank_account_holder_name, bank_account_holder_id, crew, day_rate, night_rate, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('isssssssssdd', $project_id, $full_name, $id_number, $mobile_bank, $mobile_id_number, $mobile_phone, $bank_account_number, $bank_account_holder_name, $bank_account_holder_id, $crew, $day_rate, $night_rate);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Insert failed', 'message' => $stmt->error]);
            exit;
        }

        $newId = (int)$stmt->insert_id;
        $stmt->close();

        http_response_code(201);
        echo json_encode(['data' => ['id' => $newId, 'project_id' => $project_id]]);
        exit;
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($body['id']) ? (int)$body['id'] : 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $sel = $mysqli->prepare('SELECT id, project_id, full_name, id_number, mobile_bank, mobile_id_number, mobile_phone, bank_account_number, bank_account_holder_name, bank_account_holder_id, crew, day_rate, night_rate, is_active FROM employees WHERE id = ? LIMIT 1');
        $sel->bind_param('i', $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Employee not found']);
            exit;
        }

        $full_name = array_key_exists('full_name', $body) ? trim((string)$body['full_name']) : (string)$row['full_name'];
        $id_number = array_key_exists('id_number', $body) ? trim((string)$body['id_number']) : (string)$row['id_number'];
        $mobile_bank = array_key_exists('mobile_bank', $body) ? trim((string)$body['mobile_bank']) : (string)$row['mobile_bank'];
        $mobile_id_number = array_key_exists('mobile_id_number', $body) ? trim((string)$body['mobile_id_number']) : (string)$row['mobile_id_number'];
        $mobile_phone = array_key_exists('mobile_phone', $body) ? trim((string)$body['mobile_phone']) : (string)$row['mobile_phone'];
        $bank_account_number = array_key_exists('bank_account_number', $body) ? trim((string)$body['bank_account_number']) : (string)$row['bank_account_number'];
        $bank_account_holder_name = array_key_exists('bank_account_holder_name', $body) ? trim((string)$body['bank_account_holder_name']) : (string)$row['bank_account_holder_name'];
        $bank_account_holder_id = array_key_exists('bank_account_holder_id', $body) ? trim((string)$body['bank_account_holder_id']) : (string)$row['bank_account_holder_id'];
        $crew = array_key_exists('crew', $body) ? strtolower(trim((string)$body['crew'])) : (string)$row['crew'];
        $day_rate = array_key_exists('day_rate', $body) ? (float)$body['day_rate'] : (float)$row['day_rate'];
        $night_rate = array_key_exists('night_rate', $body) ? (float)$body['night_rate'] : (float)$row['night_rate'];
        $is_active = array_key_exists('is_active', $body) ? (int)((bool)$body['is_active']) : (int)$row['is_active'];

        if ($full_name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'full_name is required']);
            exit;
        }
        if (!in_array($crew, ['day', 'night'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'crew must be day or night']);
            exit;
        }
        if ($day_rate < 0 || $night_rate < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Rates must be >= 0']);
            exit;
        }

        $upd = $mysqli->prepare('UPDATE employees SET full_name = ?, id_number = ?, mobile_bank = ?, mobile_id_number = ?, mobile_phone = ?, bank_account_number = ?, bank_account_holder_name = ?, bank_account_holder_id = ?, crew = ?, day_rate = ?, night_rate = ?, is_active = ? WHERE id = ? LIMIT 1');
        $upd->bind_param('sssssssssddii', $full_name, $id_number, $mobile_bank, $mobile_id_number, $mobile_phone, $bank_account_number, $bank_account_holder_name, $bank_account_holder_id, $crew, $day_rate, $night_rate, $is_active, $id);
        if (!$upd->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'message' => $upd->error]);
            exit;
        }
        $upd->close();

        echo json_encode(['data' => ['id' => $id, 'project_id' => (int)$row['project_id']]]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $upd = $mysqli->prepare('UPDATE employees SET is_active = 0 WHERE id = ? LIMIT 1');
        $upd->bind_param('i', $id);
        if (!$upd->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed', 'message' => $upd->error]);
            exit;
        }
        $upd->close();

        echo json_encode(['data' => ['id' => $id, 'deleted' => true]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
