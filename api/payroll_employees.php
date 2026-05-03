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
    $auth = require_auth($mysqli);
    $company_id = (int)$auth['company_id'];

    if ($method === 'GET') {
        $scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : '';
        if ($scope === 'global') {
            $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
            $like = '%' . $q . '%';
            if ($project_id > 0) {
                assert_project_company($mysqli, $project_id, $company_id);
            }

            if ($project_id > 0 && $q !== '') {
                $sql = 'SELECT e.id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone,
                               e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id,
                               e.crew, e.day_rate, e.night_rate, e.created_at, e.updated_at,
                               CASE WHEN pe.employee_id IS NULL THEN 0 ELSE 1 END AS linked_to_project
                        FROM employees e
                        LEFT JOIN project_employees pe ON pe.employee_id = e.id AND pe.project_id = ?
                    WHERE e.company_id = ? AND (e.full_name LIKE ? OR e.id_number LIKE ?)
                        ORDER BY e.full_name ASC
                        LIMIT 500';
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('iiss', $project_id, $company_id, $like, $like);
            } elseif ($project_id > 0) {
                $sql = 'SELECT e.id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone,
                               e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id,
                               e.crew, e.day_rate, e.night_rate, e.created_at, e.updated_at,
                               CASE WHEN pe.employee_id IS NULL THEN 0 ELSE 1 END AS linked_to_project
                        FROM employees e
                        LEFT JOIN project_employees pe ON pe.employee_id = e.id AND pe.project_id = ?
                    WHERE e.company_id = ?
                        ORDER BY e.full_name ASC
                        LIMIT 500';
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ii', $project_id, $company_id);
            } elseif ($q !== '') {
                $sql = 'SELECT e.id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone,
                               e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id,
                               e.crew, e.day_rate, e.night_rate, e.created_at, e.updated_at,
                               0 AS linked_to_project
                        FROM employees e
                    WHERE e.company_id = ? AND (e.full_name LIKE ? OR e.id_number LIKE ?)
                        ORDER BY e.full_name ASC
                        LIMIT 500';
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('iss', $company_id, $like, $like);
            } else {
                $sql = 'SELECT e.id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone,
                               e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id,
                               e.crew, e.day_rate, e.night_rate, e.created_at, e.updated_at,
                               0 AS linked_to_project
                        FROM employees e
                    WHERE e.company_id = ?
                        ORDER BY e.full_name ASC
                        LIMIT 500';
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('i', $company_id);
            }

            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $r['linked_to_project'] = (int)($r['linked_to_project'] ?? 0);
                $rows[] = $r;
            }
            $stmt->close();

            echo json_encode(['data' => $rows]);
            exit;
        }

        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        assert_project_company($mysqli, $project_id, $company_id);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $include_inactive = isset($_GET['include_inactive']) && (string)$_GET['include_inactive'] === '1';

        if ($id > 0) {
            $sql = 'SELECT e.id, pe.project_id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone,
                   e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id,
                   e.crew, e.day_rate, e.night_rate, pe.is_active,
                   e.created_at, e.updated_at
                FROM employees e
                INNER JOIN project_employees pe ON pe.employee_id = e.id
                WHERE e.id = ? AND pe.project_id = ?
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

        $sql = 'SELECT e.id, pe.project_id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone,
                       e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id,
                       e.crew, e.day_rate, e.night_rate, pe.is_active,
                       e.created_at, e.updated_at
                FROM project_employees pe
                INNER JOIN employees e ON e.id = pe.employee_id
                WHERE pe.project_id = ?';
        if (!$include_inactive) {
            $sql .= ' AND pe.is_active = 1';
        }
        $sql .= ' ORDER BY e.full_name ASC';

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
        $employee_id = isset($body['employee_id']) ? (int)$body['employee_id'] : 0;

        if ($employee_id > 0) {
            if ($project_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'project_id is required']);
                exit;
            }
            assert_project_company($mysqli, $project_id, $company_id);

            $selEmp = $mysqli->prepare('SELECT id FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
            $selEmp->bind_param('ii', $employee_id, $company_id);
            $selEmp->execute();
            $existingEmp = $selEmp->get_result()->fetch_assoc();
            $selEmp->close();

            if (!$existingEmp) {
                http_response_code(404);
                echo json_encode(['error' => 'Employee not found']);
                exit;
            }

            $upsertRelation = $mysqli->prepare('INSERT INTO project_employees (project_id, employee_id, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1, updated_at = CURRENT_TIMESTAMP');
            $upsertRelation->bind_param('ii', $project_id, $employee_id);
            if (!$upsertRelation->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'Upsert relation failed', 'message' => $upsertRelation->error]);
                exit;
            }
            $upsertRelation->close();

            http_response_code(201);
            echo json_encode(['data' => ['id' => $employee_id, 'project_id' => $project_id, 'relation_created' => true]]);
            exit;
        }

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
        assert_project_company($mysqli, $project_id, $company_id);
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

        $mysqli->begin_transaction();

        // Reusar empleado existente por regla de unicidad (full_name + id_number).
        $selExisting = $mysqli->prepare('SELECT id FROM employees WHERE company_id = ? AND full_name = ? AND ((id_number IS NULL AND ? = \'\') OR id_number = ?) LIMIT 1');
        $selExisting->bind_param('isss', $company_id, $full_name, $id_number, $id_number);
        $selExisting->execute();
        $existing = $selExisting->get_result()->fetch_assoc();
        $selExisting->close();

        if ($existing) {
            $employee_id = (int)$existing['id'];
            $updEmp = $mysqli->prepare('UPDATE employees SET mobile_bank = ?, mobile_id_number = ?, mobile_phone = ?, bank_account_number = ?, bank_account_holder_name = ?, bank_account_holder_id = ?, crew = ?, day_rate = ?, night_rate = ? WHERE id = ? LIMIT 1');
            $updEmp->bind_param('sssssssddi', $mobile_bank, $mobile_id_number, $mobile_phone, $bank_account_number, $bank_account_holder_name, $bank_account_holder_id, $crew, $day_rate, $night_rate, $employee_id);
            if (!$updEmp->execute()) {
                throw new Exception('Update employee failed: ' . $updEmp->error);
            }
            $updEmp->close();
        } else {
            $insEmp = $mysqli->prepare('INSERT INTO employees (company_id, full_name, id_number, mobile_bank, mobile_id_number, mobile_phone, bank_account_number, bank_account_holder_name, bank_account_holder_id, crew, day_rate, night_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insEmp->bind_param('isssssssssdd', $company_id, $full_name, $id_number, $mobile_bank, $mobile_id_number, $mobile_phone, $bank_account_number, $bank_account_holder_name, $bank_account_holder_id, $crew, $day_rate, $night_rate);
            if (!$insEmp->execute()) {
                throw new Exception('Insert employee failed: ' . $insEmp->error);
            }
            $employee_id = (int)$insEmp->insert_id;
            $insEmp->close();
        }

        $upsertRelation = $mysqli->prepare('INSERT INTO project_employees (project_id, employee_id, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1, updated_at = CURRENT_TIMESTAMP');
        $upsertRelation->bind_param('ii', $project_id, $employee_id);
        if (!$upsertRelation->execute()) {
            throw new Exception('Upsert relation failed: ' . $upsertRelation->error);
        }
        $upsertRelation->close();

        $mysqli->commit();

        http_response_code(201);
        echo json_encode(['data' => ['id' => $employee_id, 'project_id' => $project_id]]);
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

        $project_id = isset($body['project_id']) ? (int)$body['project_id'] : (isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0);
        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        assert_project_company($mysqli, $project_id, $company_id);

        $sel = $mysqli->prepare('SELECT e.id, pe.project_id, e.full_name, e.id_number, e.mobile_bank, e.mobile_id_number, e.mobile_phone, e.bank_account_number, e.bank_account_holder_name, e.bank_account_holder_id, e.crew, e.day_rate, e.night_rate, pe.is_active FROM employees e INNER JOIN project_employees pe ON pe.employee_id = e.id WHERE e.id = ? AND pe.project_id = ? LIMIT 1');
        $sel->bind_param('ii', $id, $project_id);
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

        $mysqli->begin_transaction();

        $upd = $mysqli->prepare('UPDATE employees SET full_name = ?, id_number = ?, mobile_bank = ?, mobile_id_number = ?, mobile_phone = ?, bank_account_number = ?, bank_account_holder_name = ?, bank_account_holder_id = ?, crew = ?, day_rate = ?, night_rate = ? WHERE id = ? LIMIT 1');
        $upd->bind_param('sssssssssddi', $full_name, $id_number, $mobile_bank, $mobile_id_number, $mobile_phone, $bank_account_number, $bank_account_holder_name, $bank_account_holder_id, $crew, $day_rate, $night_rate, $id);
        if (!$upd->execute()) {
            throw new Exception('Update employee failed: ' . $upd->error);
        }
        $upd->close();

        $updRel = $mysqli->prepare('UPDATE project_employees SET is_active = ? WHERE project_id = ? AND employee_id = ? LIMIT 1');
        $updRel->bind_param('iii', $is_active, $project_id, $id);
        if (!$updRel->execute()) {
            throw new Exception('Update relation failed: ' . $updRel->error);
        }
        $updRel->close();

        $mysqli->commit();

        echo json_encode(['data' => ['id' => $id, 'project_id' => $project_id]]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        assert_project_company($mysqli, $project_id, $company_id);

        $upd = $mysqli->prepare('UPDATE project_employees SET is_active = 0 WHERE project_id = ? AND employee_id = ? LIMIT 1');
        $upd->bind_param('ii', $project_id, $id);
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
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
