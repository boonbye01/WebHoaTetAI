<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: flowers.php');
    exit;
}

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$ten = trim($_POST['ten'] ?? '');
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$so_luong_ban_dau = isset($_POST['so_luong_ban_dau']) ? (float)$_POST['so_luong_ban_dau'] : 0;

if ($so_luong_ban_dau < 0) {
    $so_luong_ban_dau = 0;
}

if ($ten === '') {
    if ($is_ajax) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'name_required']);
    } else {
        header('Location: flowers.php');
    }
    exit;
}

$pdo = db();
if ($id) {
    $stmt = $pdo->prepare('UPDATE loai_hoa SET ten = ?, so_luong_ban_dau = ? WHERE id = ?');
    $stmt->execute([$ten, $so_luong_ban_dau, $id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO loai_hoa (ten, so_luong_ban_dau, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$ten, $so_luong_ban_dau]);
}

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

header('Location: flowers.php');

