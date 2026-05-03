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

function is_valuation_editable_status($status) {
    $raw = strtolower(trim((string)$status));
    $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $raw);
    return in_array($normalized, ['borrador', 'en revision', 'revision', 'draft'], true);
}

function column_exists($mysqli, $table, $column) {
    $dbRes = $mysqli->query('SELECT DATABASE()');
    $dbRow = $dbRes ? $dbRes->fetch_row() : null;
    $db = $dbRow ? (string)$dbRow[0] : '';
    if ($db === '') return false;

    $sql = 'SELECT COUNT(*) AS cnt
            FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ? AND column_name = ?';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('sss', $db, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['cnt'] ?? 0) > 0;
}

function extract_valuator_from_notes($notes) {
    $source = trim((string)$notes);
    if ($source === '') return '';
    if (preg_match('/^\s*valuador\s*:\s*(.+?)(?:\r?\n\r?\n|\r?\n|$)/i', $source, $matches)) {
        return trim((string)($matches[1] ?? ''));
    }
    return '';
}

function strip_valuator_prefix_from_notes($notes) {
    $source = (string)$notes;
    $clean = preg_replace('/^\s*valuador\s*:\s*.+?(?:\r?\n\r?\n|\r?\n|$)/i', '', $source);
    return trim((string)$clean);
}

function project_belongs_company($mysqli, $projectId, $companyId) {
    $stmt = $mysqli->prepare('SELECT id FROM projects WHERE id = ? AND company_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $hasValuatorColumn = column_exists($mysqli, 'valuations', 'valuator');
    $auth = require_auth($mysqli);
    $company_id = (int)$auth['company_id'];

    if ($method === 'POST') {
        $body = get_json_body();
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }

        $project_id = isset($body['project_id']) ? (int)$body['project_id'] : 0;
        $date = isset($body['date']) ? trim((string)$body['date']) : '';
        $currency = isset($body['currency']) ? trim((string)$body['currency']) : 'USD';
        $status = isset($body['status']) ? trim((string)$body['status']) : 'borrador';
        $valuator = isset($body['valuator']) ? trim((string)$body['valuator']) : '';
        $notes = isset($body['notes']) ? trim((string)$body['notes']) : '';
        $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];

        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'project_id is required']);
            exit;
        }
        if (!project_belongs_company($mysqli, $project_id, $company_id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }
        if ($date === '') {
            http_response_code(400);
            echo json_encode(['error' => 'date is required']);
            exit;
        }
        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['error' => 'At least one valuation item is required']);
            exit;
        }

        $amount = 0.0;
        $normalizedItems = [];
        foreach ($items as $item) {
            $project_item_id = isset($item['project_item_id']) ? (int)$item['project_item_id'] : 0;
            $qty = isset($item['qty']) ? (float)$item['qty'] : 0.0;
            $unit_cost = isset($item['unit_cost']) ? (float)$item['unit_cost'] : 0.0;

            if ($project_item_id <= 0 || $qty <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Each item requires project_item_id and qty > 0']);
                exit;
            }

            $itemTotal = round($qty * $unit_cost, 2);
            $amount += $itemTotal;
            $normalizedItems[] = [
                'project_item_id' => $project_item_id,
                'qty' => $qty,
                'unit_cost' => $unit_cost,
                'total_cost' => $itemTotal,
            ];
        }

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Total amount must be greater than zero']);
            exit;
        }

        $notes = $notes !== '' ? strip_valuator_prefix_from_notes($notes) : '';

        try {
            $mysqli->begin_transaction();

            if (!$hasValuatorColumn) {
                throw new Exception('Missing column valuations.valuator. Run docs/sql/valuations_add_valuator.sql');
            }

            $insVal = $mysqli->prepare('INSERT INTO valuations (project_id, date, amount, currency, status, valuator, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insVal->bind_param('isdssss', $project_id, $date, $amount, $currency, $status, $valuator, $notes);
            if (!$insVal->execute()) {
                throw new Exception('Insert valuation failed: ' . $insVal->error);
            }
            $valuationId = (int)$insVal->insert_id;
            $insVal->close();

            $checkItem = $mysqli->prepare('SELECT id FROM project_items WHERE id = ? AND project_id = ? LIMIT 1');
            $insValItem = $mysqli->prepare('INSERT INTO valuation_items (valuation_id, project_item_id, qty, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)');

            foreach ($normalizedItems as $ni) {
                $project_item_id = (int)$ni['project_item_id'];
                $qty = (float)$ni['qty'];
                $unit_cost = (float)$ni['unit_cost'];
                $total_cost = (float)$ni['total_cost'];

                $checkItem->bind_param('ii', $project_item_id, $project_id);
                $checkItem->execute();
                $exists = $checkItem->get_result()->fetch_assoc();
                if (!$exists) {
                    throw new Exception('Project item not found for this project: ' . $project_item_id);
                }

                $insValItem->bind_param('iiddd', $valuationId, $project_item_id, $qty, $unit_cost, $total_cost);
                if (!$insValItem->execute()) {
                    throw new Exception('Insert valuation item failed: ' . $insValItem->error);
                }
            }

            $checkItem->close();
            $insValItem->close();

            $mysqli->commit();
            http_response_code(201);
            echo json_encode(['data' => ['id' => $valuationId, 'project_id' => $project_id, 'amount' => round($amount, 2)]]);
            exit;
        } catch (Exception $txe) {
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Create valuation failed', 'message' => $txe->getMessage()]);
            exit;
        }
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

        $sel = $mysqli->prepare('SELECT v.id, v.project_id, v.status FROM valuations v INNER JOIN projects p ON p.id = v.project_id WHERE v.id = ? AND p.company_id = ? LIMIT 1');
        $sel->bind_param('ii', $id, $company_id);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res->fetch_assoc();
        $sel->close();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Valuation not found']);
            exit;
        }

        $project_id = (int)($row['project_id'] ?? 0);

        $itemsProvided = isset($body['items']) && is_array($body['items']);
        $normalizedItems = [];
        if ($itemsProvided) {
            $items = $body['items'];
            if (empty($items)) {
                http_response_code(400);
                echo json_encode(['error' => 'At least one valuation item is required']);
                exit;
            }

            $amountFromItems = 0.0;
            foreach ($items as $item) {
                $project_item_id = isset($item['project_item_id']) ? (int)$item['project_item_id'] : 0;
                $qty = isset($item['qty']) ? (float)$item['qty'] : 0.0;
                $unit_cost = isset($item['unit_cost']) ? (float)$item['unit_cost'] : 0.0;

                if ($project_item_id <= 0 || $qty <= 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Each item requires project_item_id and qty > 0']);
                    exit;
                }

                $itemTotal = round($qty * $unit_cost, 2);
                $amountFromItems += $itemTotal;
                $normalizedItems[] = [
                    'project_item_id' => $project_item_id,
                    'qty' => $qty,
                    'unit_cost' => $unit_cost,
                    'total_cost' => $itemTotal,
                ];
            }

            if ($amountFromItems <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Total amount must be greater than zero']);
                exit;
            }

            $body['amount'] = round($amountFromItems, 2);
        }

        if (array_key_exists('notes', $body)) {
            $body['notes'] = trim((string)$body['notes']);
        }

        if (!$hasValuatorColumn) {
            throw new Exception('Missing column valuations.valuator. Run docs/sql/valuations_add_valuator.sql');
        }

        $allowed = ['date', 'amount', 'currency', 'status', 'created_by', 'notes', 'valuator'];
        $fields = [];
        $values = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = ?";
                if ($f === 'amount') {
                    $values[] = $body[$f] === null ? null : (float)$body[$f];
                } elseif ($f === 'created_by') {
                    $values[] = $body[$f] === null ? null : (int)$body[$f];
                } else {
                    $values[] = $body[$f];
                }
            }
        }

        if (empty($fields) && !$itemsProvided) {
            http_response_code(400);
            echo json_encode(['error' => 'no fields to update']);
            exit;
        }

        $types = '';
        foreach ($values as $v) {
            if (is_int($v)) $types .= 'i';
            elseif (is_float($v) || is_double($v)) $types .= 'd';
            else $types .= 's';
        }
        $types .= 'i';
        $values[] = $id;

        try {
            $mysqli->begin_transaction();

            if (!empty($fields)) {
                $sql = 'UPDATE valuations SET ' . implode(', ', $fields) . ' WHERE id = ? LIMIT 1';
                $upd = $mysqli->prepare($sql);

                $bind = [];
                $bind[] = &$types;
                foreach ($values as $k => $val) {
                    $bind[] = &$values[$k];
                }
                call_user_func_array([$upd, 'bind_param'], $bind);

                if (!$upd->execute()) {
                    throw new Exception('Update failed: ' . $upd->error);
                }
                $upd->close();
            }

            if ($itemsProvided) {
                $checkItem = $mysqli->prepare('SELECT id FROM project_items WHERE id = ? AND project_id = ? LIMIT 1');
                $delItems = $mysqli->prepare('DELETE FROM valuation_items WHERE valuation_id = ?');
                $insValItem = $mysqli->prepare('INSERT INTO valuation_items (valuation_id, project_item_id, qty, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)');

                $delItems->bind_param('i', $id);
                if (!$delItems->execute()) {
                    throw new Exception('Delete valuation items failed: ' . $delItems->error);
                }

                foreach ($normalizedItems as $ni) {
                    $project_item_id = (int)$ni['project_item_id'];
                    $qty = (float)$ni['qty'];
                    $unit_cost = (float)$ni['unit_cost'];
                    $total_cost = (float)$ni['total_cost'];

                    $checkItem->bind_param('ii', $project_item_id, $project_id);
                    $checkItem->execute();
                    $exists = $checkItem->get_result()->fetch_assoc();
                    if (!$exists) {
                        throw new Exception('Project item not found for this project: ' . $project_item_id);
                    }

                    $insValItem->bind_param('iiddd', $id, $project_item_id, $qty, $unit_cost, $total_cost);
                    if (!$insValItem->execute()) {
                        throw new Exception('Insert valuation item failed: ' . $insValItem->error);
                    }
                }

                $checkItem->close();
                $delItems->close();
                $insValItem->close();
            }

            $mysqli->commit();
            echo json_encode(['data' => ['id' => $id]]);
            exit;
        } catch (Exception $txe) {
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'message' => $txe->getMessage()]);
            exit;
        }
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $sel = $mysqli->prepare('SELECT v.id, v.status FROM valuations v INNER JOIN projects p ON p.id = v.project_id WHERE v.id = ? AND p.company_id = ? LIMIT 1');
        $sel->bind_param('ii', $id, $company_id);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res->fetch_assoc();
        $sel->close();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Valuation not found']);
            exit;
        }

        if (!is_valuation_editable_status($row['status'] ?? '')) {
            http_response_code(409);
            echo json_encode(['error' => 'Valuation is locked', 'message' => 'Solo se puede eliminar una valuación en estado borrador o en revisión']);
            exit;
        }

        try {
            $mysqli->begin_transaction();

            $delItems = $mysqli->prepare('DELETE FROM valuation_items WHERE valuation_id = ?');
            $delItems->bind_param('i', $id);
            if (!$delItems->execute()) {
                throw new Exception('Delete valuation items failed: ' . $delItems->error);
            }
            $delItems->close();

            $delVal = $mysqli->prepare('DELETE FROM valuations WHERE id = ? LIMIT 1');
            $delVal->bind_param('i', $id);
            if (!$delVal->execute()) {
                throw new Exception('Delete valuation failed: ' . $delVal->error);
            }
            $deleted = $delVal->affected_rows;
            $delVal->close();

            if ($deleted < 1) {
                throw new Exception('Valuation not found for delete');
            }

            $mysqli->commit();
            echo json_encode(['data' => ['id' => $id, 'deleted' => true]]);
            exit;
        } catch (Exception $txe) {
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed', 'message' => $txe->getMessage()]);
            exit;
        }
    }

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        $stmt = $mysqli->prepare("SELECT v.* FROM valuations v INNER JOIN projects p ON p.id = v.project_id WHERE v.id = ? AND p.company_id = ? LIMIT 1");
        $stmt->bind_param('ii', $id, $company_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) {
            if (!array_key_exists('valuator', $row)) {
                $row['valuator'] = extract_valuator_from_notes($row['notes'] ?? '');
            }
        }
        echo json_encode($row ? ['data' => $row] : ['data' => null]);
        exit;
    }

    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'project_id is required']);
        exit;
    }

    if (!project_belongs_company($mysqli, $project_id, $company_id)) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT * FROM valuations WHERE project_id = ? ORDER BY date DESC, id DESC");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        if (!array_key_exists('valuator', $r)) {
            $r['valuator'] = extract_valuator_from_notes($r['notes'] ?? '');
        }
        $rows[] = $r;
    }
    $stmt->close();

    echo json_encode(['data' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
