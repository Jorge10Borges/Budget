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

function month_bounds($dateLike) {
    $ts = strtotime($dateLike ?: date('Y-m-d'));
    if ($ts === false) $ts = time();
    $start = date('Y-m-01', $ts);
    $end = date('Y-m-t', $ts);
    return [$start, $end];
}

function normalize_shift_value($value) {
    $n = is_numeric($value) ? (float)$value : 0.0;
    if ($n < 0) $n = 0.0;
    if ($n > 1.5) $n = 1.5;
    $allowed = [0.0, 0.5, 1.0, 1.5];
    foreach ($allowed as $v) {
        if (abs($n - $v) < 0.0001) {
            return $v;
        }
    }
    return 0.0;
}

function get_employee($mysqli, $employee_id, $project_id) {
    $stmt = $mysqli->prepare('SELECT id, project_id, full_name, crew, day_rate, night_rate, is_active FROM employees WHERE id = ? AND project_id = ? LIMIT 1');
    $stmt->bind_param('ii', $employee_id, $project_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
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

        $start_date = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

        if ($start_date === '' || $end_date === '') {
            [$defaultStart, $defaultEnd] = month_bounds(date('Y-m-d'));
            if ($start_date === '') $start_date = $defaultStart;
            if ($end_date === '') $end_date = $defaultEnd;
        }

        $sql = 'SELECT pe.id, pe.employee_id, pe.work_date, pe.worked_day, pe.worked_night, pe.paid_amount, pe.notes,
                       e.full_name, e.crew, e.day_rate, e.night_rate,
                       pe.paid_amount AS total_amount
                FROM payroll_entries pe
                INNER JOIN employees e ON e.id = pe.employee_id
                WHERE e.project_id = ?
                  AND pe.work_date BETWEEN ? AND ?
                ORDER BY pe.work_date ASC, e.full_name ASC';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('iss', $project_id, $start_date, $end_date);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        $summary = [
            'worked_day_count' => 0,
            'worked_night_count' => 0,
            'redouble_count' => 0,
            'total_amount' => 0.0,
        ];

        while ($r = $res->fetch_assoc()) {
            $r['worked_day'] = (float)$r['worked_day'];
            $r['worked_night'] = (float)$r['worked_night'];
            $r['paid_amount'] = (float)$r['paid_amount'];
            $r['total_amount'] = (float)$r['total_amount'];

            $summary['worked_day_count'] += $r['worked_day'];
            $summary['worked_night_count'] += $r['worked_night'];
            $summary['redouble_count'] += ($r['worked_day'] >= 1.0 && $r['worked_night'] >= 1.0) ? 1 : 0;
            $summary['total_amount'] += $r['total_amount'];

            $rows[] = $r;
        }
        $stmt->close();

        echo json_encode([
            'data' => $rows,
            'summary' => $summary,
            'range' => ['start_date' => $start_date, 'end_date' => $end_date],
        ]);
        exit;
    }

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        $project_id = isset($body['project_id']) ? (int)$body['project_id'] : 0;
        $employee_id = isset($body['employee_id']) ? (int)$body['employee_id'] : 0;
        $work_date = isset($body['work_date']) ? trim((string)$body['work_date']) : '';
        $worked_day = isset($body['worked_day']) ? normalize_shift_value($body['worked_day']) : 0.0;
        $worked_night = isset($body['worked_night']) ? normalize_shift_value($body['worked_night']) : 0.0;
        $has_paid_amount = array_key_exists('paid_amount', $body) && $body['paid_amount'] !== null && $body['paid_amount'] !== '';
        $paid_amount = $has_paid_amount ? (float)$body['paid_amount'] : null;
        $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;

        if ($project_id <= 0 || $employee_id <= 0 || $work_date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'project_id, employee_id and work_date are required']);
            exit;
        }
        if ($has_paid_amount && $paid_amount < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'paid_amount must be >= 0']);
            exit;
        }

        $employee = get_employee($mysqli, $employee_id, $project_id);
        if (!$employee) {
            http_response_code(404);
            echo json_encode(['error' => 'Employee not found for this project']);
            exit;
        }
        if ((int)$employee['is_active'] !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'Employee is inactive']);
            exit;
        }

        if ($worked_day == 0.0 && $worked_night == 0.0) {
            $del = $mysqli->prepare('DELETE FROM payroll_entries WHERE employee_id = ? AND work_date = ?');
            $del->bind_param('is', $employee_id, $work_date);
            if (!$del->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'Delete failed', 'message' => $del->error]);
                exit;
            }
            $del->close();

            echo json_encode(['data' => [
                'employee_id' => $employee_id,
                'work_date' => $work_date,
                'worked_day' => 0,
                'worked_night' => 0,
                'total_amount' => 0,
                'deleted' => true,
            ]]);
            exit;
        }

        $calculated_amount = (($worked_day * (float)$employee['day_rate']) + ($worked_night * (float)$employee['night_rate']));
        $final_paid_amount = $has_paid_amount ? round($paid_amount, 2) : round($calculated_amount, 2);

        $sql = 'INSERT INTO payroll_entries (employee_id, work_date, worked_day, worked_night, paid_amount, notes)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    worked_day = VALUES(worked_day),
                    worked_night = VALUES(worked_night),
                    paid_amount = VALUES(paid_amount),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('isddds', $employee_id, $work_date, $worked_day, $worked_night, $final_paid_amount, $notes);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Upsert failed', 'message' => $stmt->error]);
            exit;
        }
        $stmt->close();

        echo json_encode(['data' => [
            'employee_id' => $employee_id,
            'work_date' => $work_date,
            'worked_day' => $worked_day,
            'worked_night' => $worked_night,
            'paid_amount' => $final_paid_amount,
            'total_amount' => $final_paid_amount,
            'deleted' => false,
        ]]);
        exit;
    }

    if ($method === 'DELETE') {
        $body = get_json_body() ?: [];
        $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : (isset($body['employee_id']) ? (int)$body['employee_id'] : 0);
        $work_date = isset($_GET['work_date']) ? trim((string)$_GET['work_date']) : (isset($body['work_date']) ? trim((string)$body['work_date']) : '');

        if ($employee_id <= 0 || $work_date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'employee_id and work_date are required']);
            exit;
        }

        $del = $mysqli->prepare('DELETE FROM payroll_entries WHERE employee_id = ? AND work_date = ?');
        $del->bind_param('is', $employee_id, $work_date);
        if (!$del->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed', 'message' => $del->error]);
            exit;
        }
        $del->close();

        echo json_encode(['data' => ['employee_id' => $employee_id, 'work_date' => $work_date, 'deleted' => true]]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
