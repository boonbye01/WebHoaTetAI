<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if (!isset($_GET['id'])) {
    header('Location: flowers.php');
    exit;
}

$pdo = db();
$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare('DELETE FROM loai_hoa WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: flowers.php');
} catch (Exception $e) {
    header('Location: flowers.php?error=1');
}

