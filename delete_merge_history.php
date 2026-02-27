<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$source = $method === 'POST' ? $_POST : $_GET;
$history_id = isset($source['id']) ? (int)$source['id'] : 0;
$customer_id = isset($source['khach_hang_id']) ? (int)$source['khach_hang_id'] : 0;
$return_id = isset($source['return_id']) ? (int)$source['return_id'] : $customer_id;

if ($history_id <= 0 || $customer_id <= 0) {
    http_response_code(422);
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    } elseif ($return_id > 0) {
        header('Location: actual_sale_form.php?id=' . $return_id);
    }
    exit;
}

$pdo = db();
$check = $pdo->query("SHOW TABLES LIKE 'khach_hang_hoa_thuc_te_gop_lich_su'");
if (!$check->fetchColumn()) {
    http_response_code(404);
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'history_table_not_found']);
    } elseif ($return_id > 0) {
        header('Location: actual_sale_form.php?id=' . $return_id);
    }
    exit;
}

$pdo->beginTransaction();
try {
    $get_row = $pdo->prepare(
        'SELECT loai_hoa_id, so_luong, gia
         FROM khach_hang_hoa_thuc_te_gop_lich_su
         WHERE id = ? AND khach_hang_id = ?'
    );
    $get_row->execute([$history_id, $customer_id]);
    $history_row = $get_row->fetch();

    if ($history_row) {
        $flower_id = (int)$history_row['loai_hoa_id'];
        $del_qty = (float)$history_row['so_luong'];
        $del_amount = (float)$history_row['so_luong'] * (float)$history_row['gia'];

        $stmt = $pdo->prepare('DELETE FROM khach_hang_hoa_thuc_te_gop_lich_su WHERE id = ? AND khach_hang_id = ?');
        $stmt->execute([$history_id, $customer_id]);

        $sum_stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(so_luong),0) AS total_qty, COALESCE(SUM(so_luong * gia),0) AS total_amount
             FROM khach_hang_hoa_thuc_te
             WHERE khach_hang_id = ? AND loai_hoa_id = ?'
        );
        $sum_stmt->execute([$customer_id, $flower_id]);
        $sum_row = $sum_stmt->fetch();
        $new_qty = max(((float)$sum_row['total_qty']) - $del_qty, 0);
        $new_amount = max(((float)$sum_row['total_amount']) - $del_amount, 0);

        if ($new_qty <= 0) {
            $del_actual = $pdo->prepare('DELETE FROM khach_hang_hoa_thuc_te WHERE khach_hang_id = ? AND loai_hoa_id = ?');
            $del_actual->execute([$customer_id, $flower_id]);
        } else {
            $new_price = round($new_amount / $new_qty, 2);
            $first_id_stmt = $pdo->prepare(
                'SELECT id FROM khach_hang_hoa_thuc_te
                 WHERE khach_hang_id = ? AND loai_hoa_id = ?
                 ORDER BY id ASC LIMIT 1'
            );
            $first_id_stmt->execute([$customer_id, $flower_id]);
            $first_id = (int)$first_id_stmt->fetchColumn();
            if ($first_id > 0) {
                $update_first = $pdo->prepare(
                    'UPDATE khach_hang_hoa_thuc_te
                     SET so_luong = ?, gia = ?, updated_at = NOW()
                     WHERE id = ?'
                );
                $update_first->execute([$new_qty, $new_price, $first_id]);
                $delete_others = $pdo->prepare(
                    'DELETE FROM khach_hang_hoa_thuc_te
                     WHERE khach_hang_id = ? AND loai_hoa_id = ? AND id <> ?'
                );
                $delete_others->execute([$customer_id, $flower_id, $first_id]);
            }
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'delete_failed']);
    } elseif ($return_id > 0) {
        header('Location: actual_sale_form.php?id=' . $return_id);
    }
    exit;
}

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'recalc' => true]);
} elseif ($return_id > 0) {
    header('Location: actual_sale_form.php?id=' . $return_id);
}
