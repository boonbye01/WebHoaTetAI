<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$pdo = db();
$id = (int)$_GET['id'];

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('DELETE FROM khach_hang_hoa WHERE khach_hang_id = ?');
    $stmt->execute([$id]);

    $stmt = $pdo->prepare('DELETE FROM khach_hang WHERE id = ?');
    $stmt->execute([$id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo 'Xóa dữ liệu thất bại.';
    exit;
}

header('Location: index.php?msg=1');

