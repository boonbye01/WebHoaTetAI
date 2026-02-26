<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function parse_money_input($value) {
    $clean = preg_replace('/[^0-9]/', '', (string)$value);
    if ($clean === '') {
        return 0;
    }
    return (float)$clean;
}

$pdo = db();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$ten = trim($_POST['ten'] ?? '');
$dia_chi = trim($_POST['dia_chi'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$coc = parse_money_input($_POST['coc'] ?? '0');
$items = $_POST['items'] ?? [];
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if ($ten === '') {
    if ($is_ajax) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'name_required']);
    } else {
        header('Location: customer_form.php');
    }
    exit;
}

$pdo->beginTransaction();
try {
    if ($id) {
        $stmt = $pdo->prepare('UPDATE khach_hang SET ten = ?, dia_chi = ?, sdt = ?, coc = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$ten, $dia_chi, $sdt, $coc, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO khach_hang (ten, dia_chi, sdt, coc, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$ten, $dia_chi, $sdt, $coc]);
        $id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('DELETE FROM khach_hang_hoa WHERE khach_hang_id = ?');
    $stmt->execute([$id]);

    $insert = $pdo->prepare('INSERT INTO khach_hang_hoa (khach_hang_id, loai_hoa_id, so_luong, gia) VALUES (?, ?, ?, ?)');
    foreach ($items as $item) {
        $flower_id = isset($item['loai_hoa_id']) ? (int)$item['loai_hoa_id'] : 0;
        $qty = isset($item['so_luong']) ? trim((string)$item['so_luong']) : '';
        $gia = isset($item['gia']) ? trim((string)$item['gia']) : '';

        if ($flower_id <= 0 || $qty === '') {
            continue;
        }

        $qty_value = (float)$qty;
        if ($qty_value <= 0) {
            continue;
        }

        $gia_value = parse_money_input($gia);
        if ($gia_value < 0) {
            $gia_value = 0;
        }

        $insert->execute([$id, $flower_id, $qty_value, $gia_value]);
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
        echo 'Lưu dữ liệu thất bại.';
    }
    exit;
}

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

header('Location: index.php?msg=1');
