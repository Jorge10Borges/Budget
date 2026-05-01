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

function get_project_status($mysqli, $projectId) {
    $stmt = $mysqli->prepare('SELECT status FROM projects WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? (string)($row['status'] ?? '') : null;
}

function empty_to_null($value) {
    if ($value === null) return null;
    if (is_string($value) && trim($value) === '') return null;
    return $value;
}

function table_exists($mysqli, $table) {
    $dbRes = $mysqli->query('SELECT DATABASE()');
    $dbRow = $dbRes ? $dbRes->fetch_row() : null;
    $db = $dbRow ? (string)$dbRow[0] : '';
    if ($db === '') return false;

    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.tables
            WHERE table_schema = ? AND table_name = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('ss', $db, $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['cnt'] ?? 0) > 0;
}

function column_exists($mysqli, $table, $column) {
    $dbRes = $mysqli->query('SELECT DATABASE()');
    $dbRow = $dbRes ? $dbRes->fetch_row() : null;
    $db = $dbRow ? (string)$dbRow[0] : '';
    if ($db === '') return false;

    $sql = "SELECT COUNT(*) AS cnt
            FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ? AND column_name = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('sss', $db, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['cnt'] ?? 0) > 0;
}

function payroll_join_sql($mysqli) {
    if (!table_exists($mysqli, 'payroll_entries')) {
        return "LEFT JOIN (SELECT 0 AS project_id, 0 AS total_payroll) pe ON pe.project_id = p.id";
    }

    if (column_exists($mysqli, 'payroll_entries', 'project_id')) {
        return "LEFT JOIN (
                    SELECT pe.project_id, SUM(COALESCE(pe.paid_amount, 0)) AS total_payroll
                    FROM payroll_entries pe
                    GROUP BY pe.project_id
                ) pe ON pe.project_id = p.id";
    }

    if (table_exists($mysqli, 'employees') && column_exists($mysqli, 'employees', 'project_id')) {
        return "LEFT JOIN (
                    SELECT e.project_id, SUM(COALESCE(pe.paid_amount, 0)) AS total_payroll
                    FROM payroll_entries pe
                    INNER JOIN employees e ON e.id = pe.employee_id
                    GROUP BY e.project_id
                ) pe ON pe.project_id = p.id";
    }

    return "LEFT JOIN (SELECT 0 AS project_id, 0 AS total_payroll) pe ON pe.project_id = p.id";
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $payrollJoinSql = payroll_join_sql($mysqli);

    if ($method === 'GET') {
        if ($id) {
            // Return single project with calculated budget from project_items
                     $sql = "SELECT p.*, COALESCE(t.calc_budget, 0) AS calculated_budget,
                          (COALESCE(e.total_spent, 0) + COALESCE(pe.total_payroll, 0)) AS spent,
                          COALESCE(v.valuations_collected, 0) AS valuations_collected,
                          COALESCE(vt.valuations_total, 0) AS valuations_total
                    FROM projects p
                    LEFT JOIN (
                        SELECT pi.project_id,
                               SUM(COALESCE(pi.total_cost, pi.qty * COALESCE(pi.unit_cost, i.unit_cost))) AS calc_budget
                        FROM project_items pi
                        LEFT JOIN items i ON i.id = pi.item_id
                        GROUP BY pi.project_id
                    ) t ON t.project_id = p.id
                    LEFT JOIN (
                        SELECT project_id, SUM(amount) AS total_spent
                        FROM expenses
                        GROUP BY project_id
                    ) e ON e.project_id = p.id
                    {$payrollJoinSql}
                    LEFT JOIN (
                        SELECT project_id, SUM(amount) AS valuations_collected
                        FROM valuations
                        WHERE LOWER(status) IN ('cobrado', 'cobrada', 'pagado', 'pagada', 'collected', 'paid')
                        GROUP BY project_id
                    ) v ON v.project_id = p.id
                    LEFT JOIN (
                        SELECT project_id, SUM(amount) AS valuations_total
                        FROM valuations
                        GROUP BY project_id
                    ) vt ON vt.project_id = p.id
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
                         COALESCE(t.calc_budget, 0) AS calculated_budget,
                         (COALESCE(e.total_spent, 0) + COALESCE(pe.total_payroll, 0)) AS spent,
                          COALESCE(v.valuations_collected, 0) AS valuations_collected,
                          COALESCE(vt.valuations_total, 0) AS valuations_total,
                          p.currency, p.start_date, p.end_date, p.last_activity, p.is_active, p.created_at, p.updated_at
                    FROM projects p
                    LEFT JOIN (
                        SELECT pi.project_id,
                               SUM(COALESCE(pi.total_cost, pi.qty * COALESCE(pi.unit_cost, i.unit_cost))) AS calc_budget
                        FROM project_items pi
                        LEFT JOIN items i ON i.id = pi.item_id
                        GROUP BY pi.project_id
                    ) t ON t.project_id = p.id
                    LEFT JOIN (
                        SELECT project_id, SUM(amount) AS total_spent
                        FROM expenses
                        GROUP BY project_id
                    ) e ON e.project_id = p.id
                    {$payrollJoinSql}
                    LEFT JOIN (
                        SELECT project_id, SUM(amount) AS valuations_collected
                        FROM valuations
                        WHERE LOWER(status) IN ('cobrado', 'cobrada', 'pagado', 'pagada', 'collected', 'paid')
                        GROUP BY project_id
                    ) v ON v.project_id = p.id
                    LEFT JOIN (
                        SELECT project_id, SUM(amount) AS valuations_total
                        FROM valuations
                        GROUP BY project_id
                    ) vt ON vt.project_id = p.id
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
        $start_date = isset($data['start_date']) ? empty_to_null($data['start_date']) : null;
        $end_date = isset($data['end_date']) ? empty_to_null($data['end_date']) : null;
        $last_activity = isset($data['last_activity']) ? empty_to_null($data['last_activity']) : null;
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
        $currentStatus = get_project_status($mysqli, $id);
        if ($currentStatus === null) { http_response_code(404); echo json_encode(['error' => 'Project not found']); exit; }
        $data = get_json_body();

        $fieldsToUpdate = array_keys($data);
        $onlyStatusChange = count($fieldsToUpdate) === 1 && in_array('status', $fieldsToUpdate, true);

        if ($currentStatus !== 'draft' && !$onlyStatusChange) {
            http_response_code(409);
            echo json_encode(['error' => 'Project is locked', 'message' => 'Solo se puede modificar un proyecto en estado draft']);
            exit;
        }

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
                    if (in_array($f, ['start_date', 'end_date', 'last_activity'], true)) {
                        $values[] = empty_to_null($data[$f]);
                    } else {
                        $values[] = $data[$f];
                    }
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
        $currentStatus = get_project_status($mysqli, $id);
        if ($currentStatus === null) { http_response_code(404); echo json_encode(['error' => 'Project not found']); exit; }
        if ($currentStatus !== 'draft') {
            http_response_code(409);
            echo json_encode(['error' => 'Project is locked', 'message' => 'Solo se puede modificar un proyecto en estado draft']);
            exit;
        }
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
