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
if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id is required']);
    exit;
}

// Helper: check if table exists
function table_exists($mysqli, $table) {
    $db = $mysqli->real_escape_string($mysqli->query("SELECT DATABASE()")->fetch_row()[0]);
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = '$db' AND table_name = '" . $mysqli->real_escape_string($table) . "'";
    $res = $mysqli->query($sql);
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return (int)$row['cnt'] > 0;
}

// Helper: check if column exists
function column_exists($mysqli, $table, $column) {
    $db = $mysqli->real_escape_string($mysqli->query("SELECT DATABASE()")->fetch_row()[0]);
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = '$db' AND table_name = '" . $mysqli->real_escape_string($table) . "' AND column_name = '" . $mysqli->real_escape_string($column) . "'";
    $res = $mysqli->query($sql);
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return (int)$row['cnt'] > 0;
}

// Compute counts for a table with optional closed status detection
function counts_for_table($mysqli, $table, $project_id, $project_col = 'project_id') {
    if (!table_exists($mysqli, $table)) return ['total' => 0, 'closed' => null];

    $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM $table WHERE $project_col = ?");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $total = (int)$row['cnt'];
    $stmt->close();

    $closed = null;
    if (column_exists($mysqli, $table, 'status')) {
        $sql = "SELECT COUNT(*) as cnt FROM $table WHERE $project_col = ? AND LOWER(status) IN ('closed','done','completed','approved','cerrada','finalizado')";
        $stmt2 = $mysqli->prepare($sql);
        $stmt2->bind_param('i', $project_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $r2 = $res2->fetch_assoc();
        $closed = (int)$r2['cnt'];
        $stmt2->close();
    }

    return ['total' => $total, 'closed' => $closed];
}

function sum_expenses_amount($mysqli, $project_id) {
    if (!table_exists($mysqli, 'expenses') || !column_exists($mysqli, 'expenses', 'amount')) {
        return 0.0;
    }

    $stmt = $mysqli->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE project_id = ?');
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (float)($row['total'] ?? 0);
}

function sum_payroll_amount($mysqli, $project_id) {
    if (!table_exists($mysqli, 'payroll_entries') || !table_exists($mysqli, 'employees')) {
        return 0.0;
    }

        $sql = 'SELECT COALESCE(SUM(COALESCE(pe.paid_amount, 0)), 0) AS total
            FROM payroll_entries pe
            INNER JOIN employees e ON e.id = pe.employee_id
            WHERE e.project_id = ?';

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (float)($row['total'] ?? 0);
}

$result = [];
$result['project_items'] = counts_for_table($mysqli, 'project_items', $project_id, 'project_id');
$result['expenses'] = counts_for_table($mysqli, 'expenses', $project_id, 'project_id');
$result['valuations'] = counts_for_table($mysqli, 'valuations', $project_id, 'project_id');

$expenseAmount = sum_expenses_amount($mysqli, $project_id);
$payrollAmount = sum_payroll_amount($mysqli, $project_id);
$result['expenses']['amount'] = $expenseAmount;
$result['expenses']['payroll_amount'] = $payrollAmount;
$result['expenses']['project_total_amount'] = $expenseAmount + $payrollAmount;

echo json_encode(['data' => $result]);
