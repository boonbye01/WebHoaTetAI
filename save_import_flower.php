<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: flowers.php');
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
$loai_hoa_id = isset($_POST['loai_hoa_id']) ? (int)$_POST['loai_hoa_id'] : 0;
$so_luong_cap = isset($_POST['so_luong_cap']) ? (float)$_POST['so_luong_cap'] : 0;
$don_gia_lay = parse_money_input($_POST['don_gia_lay'] ?? '0');
$ten_nha_vuon = trim($_POST['ten_nha_vuon'] ?? '');
$coc = parse_money_input($_POST['coc'] ?? '0');
$ghi_chu = trim($_POST['ghi_chu'] ?? '');
$ngay_nhap = trim($_POST['ngay_nhap'] ?? '');

if ($loai_hoa_id <= 0 || $so_luong_cap <= 0 || $ten_nha_vuon === '' || $ngay_nhap === '') {
    header('Location: flowers.php?import_error=1&import_open=1');
    exit;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS nhap_vuon_ngoai (
      id INT AUTO_INCREMENT PRIMARY KEY,
      loai_hoa_id INT NOT NULL,
      so_luong_cap DECIMAL(10,2) NOT NULL DEFAULT 0,
      don_gia_lay DECIMAL(12,2) NOT NULL DEFAULT 0,
      ten_nha_vuon VARCHAR(200) NOT NULL,
      coc DECIMAL(12,2) NOT NULL DEFAULT 0,
      ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
      ngay_nhap DATE NOT NULL,
      created_at DATETIME NOT NULL,
      CONSTRAINT fk_nhap_vuon_ngoai_loai_hoa FOREIGN KEY (loai_hoa_id) REFERENCES loai_hoa(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB"
);
try {
    $pdo->exec("ALTER TABLE nhap_vuon_ngoai ADD COLUMN coc DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER ten_nha_vuon");
} catch (Exception $e) {
    // Column may already exist.
}

$pdo->beginTransaction();
try {
    $check = $pdo->prepare('SELECT id FROM loai_hoa WHERE id = ?');
    $check->execute([$loai_hoa_id]);
    if (!$check->fetch()) {
        throw new Exception('flower_not_found');
    }

    $insert = $pdo->prepare(
        'INSERT INTO nhap_vuon_ngoai (loai_hoa_id, so_luong_cap, don_gia_lay, ten_nha_vuon, coc, ghi_chu, ngay_nhap, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $insert->execute([$loai_hoa_id, $so_luong_cap, $don_gia_lay, $ten_nha_vuon, $coc, $ghi_chu, $ngay_nhap]);

    $update = $pdo->prepare('UPDATE loai_hoa SET so_luong_ban_dau = so_luong_ban_dau + ? WHERE id = ?');
    $update->execute([$so_luong_cap, $loai_hoa_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: flowers.php?import_error=1&import_open=1');
    exit;
}

header('Location: flowers.php?import_msg=1&import_open=1');

