<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$is_ajax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

function parse_money_input($value) {
    $clean = preg_replace('/[^0-9]/', '', (string)$value);
    if ($clean === '') {
        return 0;
    }
    return (float)$clean;
}

function parse_datetime_input($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return date('Y-m-d H:i:s');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
        return str_replace('T', ' ', $value) . ':00';
    }
    return date('Y-m-d H:i:s');
}

$pdo = db();
$customer_id = isset($_POST['khach_hang_id']) ? (int)$_POST['khach_hang_id'] : 0;
$items = $_POST['items'] ?? [];
$trang_thai_boc = $_POST['trang_thai_boc'] ?? 'chua_boc';
if ($trang_thai_boc !== 'xong') {
    $trang_thai_boc = 'chua_boc';
}

if ($customer_id <= 0) {
    if ($is_ajax) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'invalid_customer']);
    } else {
        header('Location: index.php');
    }
    exit;
}

$check = $pdo->prepare('SELECT id FROM khach_hang WHERE id = ?');
$check->execute([$customer_id]);
if (!$check->fetch()) {
    if ($is_ajax) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'customer_not_found']);
    } else {
        header('Location: index.php');
    }
    exit;
}

$pdo->beginTransaction();
try {
    $update_customer = $pdo->prepare('UPDATE khach_hang SET trang_thai_boc = ?, updated_at = NOW() WHERE id = ?');
    $update_customer->execute([$trang_thai_boc, $customer_id]);

    $stmt = $pdo->prepare('DELETE FROM khach_hang_hoa_thuc_te WHERE khach_hang_id = ?');
    $stmt->execute([$customer_id]);

    $insert = $pdo->prepare(
        'INSERT INTO khach_hang_hoa_thuc_te (khach_hang_id, loai_hoa_id, so_luong, gia, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );

    $valid_rows = [];
    foreach ($items as $item) {
        $flower_id = isset($item['loai_hoa_id']) ? (int)$item['loai_hoa_id'] : 0;
        $qty = isset($item['so_luong']) ? trim((string)$item['so_luong']) : '';
        $price = isset($item['gia']) ? trim((string)$item['gia']) : '';
        $time_input = isset($item['thoi_gian']) ? (string)$item['thoi_gian'] : '';

        if ($flower_id <= 0 || $qty === '') {
            continue;
        }

        $qty_value = (float)$qty;
        if ($qty_value <= 0) {
            continue;
        }

        $price_value = parse_money_input($price);
        if ($price_value < 0) {
            $price_value = 0;
        }

        $created_at_value = parse_datetime_input($time_input);
        $valid_rows[] = [
            'flower_id' => $flower_id,
            'qty' => $qty_value,
            'price' => $price_value,
            'time' => $created_at_value,
        ];
    }

    foreach ($valid_rows as $row) {
        $insert->execute([
            $customer_id,
            $row['flower_id'],
            $row['qty'],
            $row['price'],
            $row['time'],
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    if ($is_ajax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'save_failed']);
    } else {
        http_response_code(500);
        echo 'Lưu dữ liệu bán thực tế thất bại.';
    }
    exit;
}

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

header('Location: actual_sale_form.php?id=' . $customer_id . '&msg=1');
