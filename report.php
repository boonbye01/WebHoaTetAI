<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();

$total_deposit = (float)$pdo->query('SELECT COALESCE(SUM(coc), 0) FROM khach_hang')->fetchColumn();

$total_order = (float)$pdo->query(
    'SELECT COALESCE(SUM(so_luong * gia), 0)
     FROM khach_hang_hoa'
)->fetchColumn();

$total_after_done = (float)$pdo->query(
    'SELECT COALESCE(SUM(t.so_luong * t.gia), 0)
     FROM khach_hang_hoa_thuc_te t
     JOIN khach_hang k ON k.id = t.khach_hang_id
     WHERE k.trang_thai_boc = \'xong\''
)->fetchColumn();

function format_vnd($value) {
    return number_format((float)$value, 0, ',', '.');
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Th&#7889;ng k&#234; t&#7893;ng h&#7907;p</title>
  <link rel="stylesheet" href="assets/style.css?v=20260225_7">
</head>
<body>
  <div class="container report-shell">
    <header class="header report-header">
      <div>
        <h1>Th&#7889;ng k&#234; t&#7893;ng h&#7907;p</h1>
        <p class="report-subtitle">T&#7893;ng quan doanh thu &#273;&#7863;t h&#224;ng v&#224; sau khi l&#234;n xe</p>
      </div>
      <div class="actions">
        <a class="button secondary" href="index.php">Quay l&#7841;i</a>
        <a class="button secondary" href="logout.php">&#272;&#259;ng xu&#7845;t</a>
      </div>
    </header>

    <section class="report-grid">
      <article class="report-card primary">
        <p class="report-label">T&#7893;ng ti&#7873;n hoa &#273;&#7863;t</p>
        <p class="report-value"><?php echo format_vnd($total_order); ?> <span>VND</span></p>
      </article>

      <article class="report-card accent">
        <p class="report-label">T&#7893;ng ti&#7873;n c&#7885;c</p>
        <p class="report-value"><?php echo format_vnd($total_deposit); ?> <span>VND</span></p>
      </article>

      <article class="report-card success">
        <p class="report-label">T&#7893;ng ti&#7873;n hoa sau l&#234;n xe</p>
        <p class="report-note">Ch&#7881; c&#7897;ng kh&#225;ch c&#243; tr&#7841;ng th&#225;i Xong</p>
        <p class="report-value"><?php echo format_vnd($total_after_done); ?> <span>VND</span></p>
      </article>
    </section>
  </div>
</body>
</html>
