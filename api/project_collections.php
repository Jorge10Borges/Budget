<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/auth_middleware.php';

auth_send_cors();

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

function validate_kind($kind) {
    return in_array($kind, ['anticipo', 'otro'], true);
}

function assert_project_company($mysqli, $projectId, $companyId) {
    $stmt = $mysqli->prepare('SELECT id FROM projects WHERE id = ? AND company_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit;
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $auth = require_auth($mysqli);
    $company_id = (int)$auth['company_id'];

    if ($method === 'GET') {
        if ($id > 0) {
            $stmt = $mysqli->prepare('SELECT pc.id, pc.project_id, pc.collection_date, pc.amount, pc.collection_kind, pc.other_type, pc.notes, pc.created_at, pc.updated_at
                                      FROM project_collections pc
                                      INNER JOIN projects p ON p.id = pc.project_id
                                      WHERE pc.id = ? AND p.company_id = ? LIMIT 1');
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['error' => 'Prepare failed', 'message' => $mysqli->error]);
                exit;
            }
            $stmt->bind_param('ii', $id, $company_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Collection not found']);
                exit;
            }

            echo json_encode(['data' => $row]);
            exit;
        }

        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        assert_project_company($mysqli, $project_id, $company_id);

        $sql = 'SELECT id, project_id, collection_date, amount, collection_kind, other_type, notes, created_at, updated_at
                FROM project_collections
                WHERE project_id = ?
                ORDER BY collection_date DESC, id DESC';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Prepare failed', 'message' => $mysqli->error]);
            exit;
        }

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
        $collection_date = isset($body['collection_date']) ? trim((string)$body['collection_date']) : '';
        $amount = isset($body['amount']) ? (float)$body['amount'] : 0.0;
        $collection_kind = isset($body['collection_kind']) ? trim((string)$body['collection_kind']) : '';
        $other_type = isset($body['other_type']) ? trim((string)$body['other_type']) : null;
        $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;

        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        if ($collection_date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'collection_date is required']);
            exit;
        }
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'amount must be greater than zero']);
            exit;
        }
        if (!validate_kind($collection_kind)) {
            http_response_code(400);
            echo json_encode(['error' => 'collection_kind must be anticipo or otro']);
            exit;
        }

        if ($collection_kind === 'otro' && ($other_type === null || $other_type === '')) {
            http_response_code(400);
            echo json_encode(['error' => 'other_type is required when collection_kind is otro']);
            exit;
        }

        if ($collection_kind !== 'otro') {
            $other_type = null;
        }

        assert_project_company($mysqli, $project_id, $company_id);

        $ins = $mysqli->prepare('INSERT INTO project_collections (project_id, collection_date, amount, collection_kind, other_type, notes) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$ins) {
            http_response_code(500);
            echo json_encode(['error' => 'Prepare failed', 'message' => $mysqli->error]);
            exit;
        }

        $ins->bind_param('isdsss', $project_id, $collection_date, $amount, $collection_kind, $other_type, $notes);
        if (!$ins->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Insert failed', 'message' => $ins->error]);
            exit;
        }

        $newId = (int)$ins->insert_id;
        $ins->close();

        http_response_code(201);
        echo json_encode(['data' => ['id' => $newId, 'project_id' => $project_id]]);
        exit;
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        $sel = $mysqli->prepare('SELECT pc.id, pc.project_id, pc.collection_date, pc.amount, pc.collection_kind, pc.other_type, pc.notes
                     FROM project_collections pc
                     INNER JOIN projects p ON p.id = pc.project_id
                     WHERE pc.id = ? AND p.company_id = ? LIMIT 1');
        if (!$sel) {
            http_response_code(500);
            echo json_encode(['error' => 'Prepare failed', 'message' => $mysqli->error]);
            exit;
        }
        $sel->bind_param('ii', $id, $company_id);
        $sel->execute();
        $current = $sel->get_result()->fetch_assoc();
        $sel->close();

        if (!$current) {
            http_response_code(404);
            echo json_encode(['error' => 'Collection not found']);
            exit;
        }

        $collection_date = array_key_exists('collection_date', $body) ? trim((string)$body['collection_date']) : (string)$current['collection_date'];
        $amount = array_key_exists('amount', $body) ? (float)$body['amount'] : (float)$current['amount'];
        $collection_kind = array_key_exists('collection_kind', $body) ? trim((string)$body['collection_kind']) : (string)$current['collection_kind'];
        $other_type = array_key_exists('other_type', $body)
            ? trim((string)($body['other_type'] ?? ''))
            : (string)($current['other_type'] ?? '');
        $notes = array_key_exists('notes', $body)
            ? trim((string)($body['notes'] ?? ''))
            : (string)($current['notes'] ?? '');

        if ($collection_date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'collection_date is required']);
            exit;
        }
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'amount must be greater than zero']);
            exit;
        }
        if (!validate_kind($collection_kind)) {
            http_response_code(400);
            echo json_encode(['error' => 'collection_kind must be anticipo or otro']);
            exit;
        }

        if ($collection_kind === 'otro' && $other_type === '') {
            http_response_code(400);
            echo json_encode(['error' => 'other_type is required when collection_kind is otro']);
            exit;
        }

        if ($collection_kind !== 'otro') {
            $other_type = null;
        }

        $notes = $notes === '' ? null : $notes;

        $upd = $mysqli->prepare('UPDATE project_collections SET collection_date = ?, amount = ?, collection_kind = ?, other_type = ?, notes = ? WHERE id = ? LIMIT 1');
        if (!$upd) {
            http_response_code(500);
            echo json_encode(['error' => 'Prepare failed', 'message' => $mysqli->error]);
            exit;
        }

        $upd->bind_param('sdsssi', $collection_date, $amount, $collection_kind, $other_type, $notes, $id);
        if (!$upd->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'message' => $upd->error]);
            exit;
        }
        $upd->close();

        echo json_encode(['data' => ['id' => $id, 'project_id' => (int)$current['project_id']]]);
        exit;
    }

    if ($method === 'DELETE') {
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $del = $mysqli->prepare('DELETE pc FROM project_collections pc INNER JOIN projects p ON p.id = pc.project_id WHERE pc.id = ? AND p.company_id = ? LIMIT 1');
        if (!$del) {
            http_response_code(500);
            echo json_encode(['error' => 'Prepare failed', 'message' => $mysqli->error]);
            exit;
        }

        $del->bind_param('ii', $id, $company_id);
        if (!$del->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed', 'message' => $del->error]);
            exit;
        }

        if ($del->affected_rows === 0) {
            $del->close();
            http_response_code(404);
            echo json_encode(['error' => 'Collection not found']);
            exit;
        }

        $del->close();
        echo json_encode(['data' => ['id' => $id]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
