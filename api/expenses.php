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

function get_project_status($mysqli, $projectId) {
    $stmt = $mysqli->prepare('SELECT status FROM projects WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string)($row['status'] ?? '') : null;
}

function ensure_project_item_belongs_to_project($mysqli, $projectItemId, $projectId) {
    if ($projectItemId === null) return;
    $stmt = $mysqli->prepare('SELECT id FROM project_items WHERE id = ? AND project_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectItemId, $projectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(400);
        echo json_encode(['error' => 'project_item_id is not valid for this project']);
        exit;
    }
}

function ensure_expense_category_exists($mysqli, $categoryId) {
    if ($categoryId === null) return;
    $stmt = $mysqli->prepare('SELECT id FROM expense_categories WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(400);
        echo json_encode(['error' => 'category_id is not valid']);
        exit;
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
                $sql = "SELECT e.*, COALESCE(i.name, '') AS project_item_name, COALESCE(c.name, '') AS category_name
                    FROM expenses e
                    LEFT JOIN project_items pi ON pi.id = e.project_item_id
                    LEFT JOIN items i ON i.id = pi.item_id
                    LEFT JOIN expense_categories c ON c.id = e.category_id
                    WHERE e.id = ?
                    LIMIT 1";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Expense not found']);
                exit;
            }
            echo json_encode(['data' => $row]);
            exit;
        }

        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        if (!$project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }

        $sql = "SELECT e.id, e.project_id, e.project_item_id, e.category_id, e.expense_date, e.description,
                       e.amount, e.currency, e.status, e.vendor, e.reference, e.notes, e.created_at, e.updated_at,
                   COALESCE(i.name, '') AS project_item_name, COALESCE(c.name, '') AS category_name
                FROM expenses e
                LEFT JOIN project_items pi ON pi.id = e.project_item_id
                LEFT JOIN items i ON i.id = pi.item_id
            LEFT JOIN expense_categories c ON c.id = e.category_id
                WHERE e.project_id = ?
                ORDER BY e.expense_date DESC, e.id DESC";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
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
        $project_item_id = isset($body['project_item_id']) && $body['project_item_id'] !== '' ? (int)$body['project_item_id'] : null;
        $category_id = isset($body['category_id']) && $body['category_id'] !== '' ? (int)$body['category_id'] : null;
        $expense_date = isset($body['expense_date']) ? trim((string)$body['expense_date']) : '';
        $description = isset($body['description']) ? trim((string)$body['description']) : '';
        $amount = isset($body['amount']) ? (float)$body['amount'] : 0.0;
        $currency = isset($body['currency']) ? trim((string)$body['currency']) : 'USD';
        $status = isset($body['status']) ? trim((string)$body['status']) : 'draft';
        $vendor = isset($body['vendor']) ? trim((string)$body['vendor']) : null;
        $reference = isset($body['reference']) ? trim((string)$body['reference']) : null;
        $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;

        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        if ($expense_date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'expense_date is required']);
            exit;
        }
        if ($description === '') {
            http_response_code(400);
            echo json_encode(['error' => 'description is required']);
            exit;
        }
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'amount must be greater than zero']);
            exit;
        }

        if (get_project_status($mysqli, $project_id) === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }
        ensure_project_item_belongs_to_project($mysqli, $project_item_id, $project_id);
        ensure_expense_category_exists($mysqli, $category_id);

        $stmt = $mysqli->prepare('INSERT INTO expenses (project_id, project_item_id, category_id, expense_date, description, amount, currency, status, vendor, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissdsssss', $project_id, $project_item_id, $category_id, $expense_date, $description, $amount, $currency, $status, $vendor, $reference, $notes);
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

        $sel = $mysqli->prepare('SELECT * FROM expenses WHERE id = ? LIMIT 1');
        $sel->bind_param('i', $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Expense not found']);
            exit;
        }

        $project_id = (int)$row['project_id'];
        if (get_project_status($mysqli, $project_id) === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }

        $project_item_id = array_key_exists('project_item_id', $body)
            ? (($body['project_item_id'] === '' || $body['project_item_id'] === null) ? null : (int)$body['project_item_id'])
            : ($row['project_item_id'] !== null ? (int)$row['project_item_id'] : null);
        $category_id = array_key_exists('category_id', $body)
            ? (($body['category_id'] === '' || $body['category_id'] === null) ? null : (int)$body['category_id'])
            : ($row['category_id'] !== null ? (int)$row['category_id'] : null);
        $expense_date = array_key_exists('expense_date', $body) ? trim((string)$body['expense_date']) : (string)$row['expense_date'];
        $description = array_key_exists('description', $body) ? trim((string)$body['description']) : (string)$row['description'];
        $amount = array_key_exists('amount', $body) ? (float)$body['amount'] : (float)$row['amount'];
        $currency = array_key_exists('currency', $body) ? trim((string)$body['currency']) : (string)$row['currency'];
        $status = array_key_exists('status', $body) ? trim((string)$body['status']) : (string)$row['status'];
        $vendor = array_key_exists('vendor', $body) ? trim((string)$body['vendor']) : (string)($row['vendor'] ?? '');
        $reference = array_key_exists('reference', $body) ? trim((string)$body['reference']) : (string)($row['reference'] ?? '');
        $notes = array_key_exists('notes', $body) ? trim((string)$body['notes']) : (string)($row['notes'] ?? '');

        if ($expense_date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'expense_date is required']);
            exit;
        }
        if ($description === '') {
            http_response_code(400);
            echo json_encode(['error' => 'description is required']);
            exit;
        }
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'amount must be greater than zero']);
            exit;
        }

        ensure_project_item_belongs_to_project($mysqli, $project_item_id, $project_id);
        ensure_expense_category_exists($mysqli, $category_id);

        $upd = $mysqli->prepare('UPDATE expenses SET project_item_id = ?, category_id = ?, expense_date = ?, description = ?, amount = ?, currency = ?, status = ?, vendor = ?, reference = ?, notes = ? WHERE id = ? LIMIT 1');
        $upd->bind_param('iissdsssssi', $project_item_id, $category_id, $expense_date, $description, $amount, $currency, $status, $vendor, $reference, $notes, $id);
        if (!$upd->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'message' => $upd->error]);
            exit;
        }
        $upd->close();

        echo json_encode(['data' => ['id' => $id, 'project_id' => $project_id]]);
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

        $sel = $mysqli->prepare('SELECT project_id FROM expenses WHERE id = ? LIMIT 1');
        $sel->bind_param('i', $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Expense not found']);
            exit;
        }

        $del = $mysqli->prepare('DELETE FROM expenses WHERE id = ?');
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