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

$stmt = $pdo->prepare('DELETE FROM khach_hang_hoa_thuc_te_gop_lich_su WHERE id = ? AND khach_hang_id = ?');
$stmt->execute([$history_id, $customer_id]);

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
} elseif ($return_id > 0) {
    header('Location: actual_sale_form.php?id=' . $return_id);
}
