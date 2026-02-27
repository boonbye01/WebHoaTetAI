<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$history_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$customer_id = isset($_POST['khach_hang_id']) ? (int)$_POST['khach_hang_id'] : 0;

if ($history_id <= 0 || $customer_id <= 0) {
    http_response_code(422);
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'invalid_input']);
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
    }
    exit;
}

$stmt = $pdo->prepare('DELETE FROM khach_hang_hoa_thuc_te_gop_lich_su WHERE id = ? AND khach_hang_id = ?');
$stmt->execute([$history_id, $customer_id]);

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
}

