<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();

$import_msg = isset($_GET['import_msg']);
$import_error = isset($_GET['import_error']);
$import_open = $import_error || isset($_GET['import_open']);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS nhap_vuon_ngoai (
      id INT AUTO_INCREMENT PRIMARY KEY,
      loai_hoa_id INT NOT NULL,
      so_luong_cap DECIMAL(10,2) NOT NULL DEFAULT 0,
      don_gia_lay DECIMAL(12,2) NOT NULL DEFAULT 0,
      ten_nha_vuon VARCHAR(200) NOT NULL,
      ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
      ngay_nhap DATE NOT NULL,
      created_at DATETIME NOT NULL,
      CONSTRAINT fk_nhap_vuon_ngoai_loai_hoa FOREIGN KEY (loai_hoa_id) REFERENCES loai_hoa(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB"
);

$flowers = $pdo->query(
    'SELECT l.id, l.ten, l.so_luong_ban_dau,
            COALESCE(nvx.tong_so_luong, 0) AS sl_lay_vuon_khac,
            COALESCE(coc.tong_so_luong, 0) AS sl_khach_coc,
            ((l.so_luong_ban_dau + COALESCE(nvx.tong_so_luong, 0)) - COALESCE(coc.tong_so_luong, 0)) AS sl_con_lai_coc,
            COALESCE(coc_xong.tong_so_luong, 0) AS sl_khach_coc_xong,
            COALESCE(chot_xong.tong_so_luong, 0) AS sl_khach_chot_xong,
            (COALESCE(chot_xong.tong_so_luong, 0) - COALESCE(coc_xong.tong_so_luong, 0)) AS sl_phat_sinh_chot,
            (((l.so_luong_ban_dau + COALESCE(nvx.tong_so_luong, 0)) - COALESCE(coc.tong_so_luong, 0))
              - (COALESCE(chot_xong.tong_so_luong, 0) - COALESCE(coc_xong.tong_so_luong, 0))) AS sl_con_lai_sau_phat_sinh
     FROM loai_hoa l
     LEFT JOIN (
        SELECT loai_hoa_id, SUM(so_luong_cap) AS tong_so_luong
        FROM nhap_vuon_ngoai
        GROUP BY loai_hoa_id
     ) nvx ON nvx.loai_hoa_id = l.id
     LEFT JOIN (
        SELECT loai_hoa_id, SUM(so_luong) AS tong_so_luong
        FROM khach_hang_hoa
        GROUP BY loai_hoa_id
     ) coc ON coc.loai_hoa_id = l.id
     LEFT JOIN (
        SELECT c.loai_hoa_id, SUM(c.so_luong) AS tong_so_luong
        FROM khach_hang_hoa c
        JOIN khach_hang k ON k.id = c.khach_hang_id
        WHERE k.trang_thai_boc = \'xong\'
        GROUP BY c.loai_hoa_id
     ) coc_xong ON coc_xong.loai_hoa_id = l.id
     LEFT JOIN (
        SELECT t.loai_hoa_id, SUM(t.so_luong) AS tong_so_luong
        FROM khach_hang_hoa_thuc_te t
        JOIN khach_hang k ON k.id = t.khach_hang_id
        WHERE k.trang_thai_boc = \'xong\'
        GROUP BY t.loai_hoa_id
     ) chot_xong ON chot_xong.loai_hoa_id = l.id
     ORDER BY l.ten'
)->fetchAll();

$import_rows = $pdo->query(
    'SELECT n.id, n.ngay_nhap, n.so_luong_cap, n.don_gia_lay, n.ten_nha_vuon, n.ghi_chu, l.ten AS ten_hoa,
            (n.so_luong_cap * n.don_gia_lay) AS thanh_tien
     FROM nhap_vuon_ngoai n
     JOIN loai_hoa l ON l.id = n.loai_hoa_id
     ORDER BY n.id DESC
     LIMIT 100'
)->fetchAll();

$error = isset($_GET['error']);

function format_vnd($value) {
    return number_format((float)$value, 0, ',', '.');
}

function format_qty($value) {
    $str = (string)$value;
    if (strpos($str, '.') === false) {
        return $str;
    }
    $str = rtrim($str, '0');
    return rtrim($str, '.');
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý loại hoa/chậu</title>
  <link rel="stylesheet" href="assets/style.css?v=20260226_1">
</head>
<body>
  <div class="container">
    <header class="header">
      <h1>Quản lý loại hoa/chậu</h1>
      <div class="actions">
        <button type="button" class="button" id="open-import-panel">Nhập từ vườn khác</button>
        <a class="button secondary" href="index.php">Quay lại</a>
        <a class="button secondary" href="logout.php">Đăng xuất</a>
      </div>
    </header>

    <?php if ($error): ?>
      <div class="notice">Không thể xóa. Loại đang được khách hàng sử dụng.</div>
    <?php endif; ?>

    <?php if ($import_msg): ?>
      <div class="notice">Đã lưu phiếu nhập hoa từ vườn khác.</div>
    <?php endif; ?>

    <?php if ($import_error): ?>
      <div class="notice">Không lưu được phiếu nhập. Vui lòng kiểm tra lại thông tin.</div>
    <?php endif; ?>

    <form method="post" action="save_flower.php" class="inline-form">
      <input type="hidden" name="id" value="">
      <input type="text" name="ten" required placeholder="Tên loại chậu/hoa">
      <input type="number" step="0.01" min="0" name="so_luong_ban_dau" required placeholder="Tổng SL Trong Vườn">
      <button type="submit" class="button">Thêm</button>
    </form>

    <section class="import-panel" id="import-panel" <?php echo $import_open ? '' : 'hidden'; ?>>
      <h2>Nhập hoa từ vườn khác</h2>
      <form method="post" action="save_import_flower.php" class="inline-form import-form">
        <select name="loai_hoa_id" required>
          <option value="">-- Chọn chậu/hoa --</option>
          <?php foreach ($flowers as $f): ?>
            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['ten']); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" step="0.01" min="0.01" name="so_luong_cap" required placeholder="SL Cặp">
        <input type="text" name="don_gia_lay" inputmode="numeric" pattern="[0-9., ]*" required placeholder="Đơn giá lấy">
        <input type="text" name="ten_nha_vuon" required placeholder="Tên nhà vườn">
        <input type="text" name="ghi_chu" placeholder="Ghi chú (nếu có)">
        <input type="date" name="ngay_nhap" value="<?php echo date('Y-m-d'); ?>" required>
        <button type="submit" class="button">Lưu phiếu nhập</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>Ngày nhập</th>
            <th>Loại chậu/hoa</th>
            <th>SL Cặp</th>
            <th>Đơn giá lấy</th>
            <th>Tên nhà vườn</th>
            <th>Thành tiền</th>
            <th>Ghi chú</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($import_rows) === 0): ?>
          <tr><td colspan="7">Chưa có phiếu nhập nào.</td></tr>
        <?php else: ?>
          <?php foreach ($import_rows as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['ngay_nhap']); ?></td>
              <td><?php echo htmlspecialchars($row['ten_hoa']); ?></td>
              <td><?php echo htmlspecialchars(format_qty($row['so_luong_cap'])); ?></td>
              <td><?php echo htmlspecialchars(format_vnd($row['don_gia_lay'])); ?> VND</td>
              <td><?php echo htmlspecialchars($row['ten_nha_vuon']); ?></td>
              <td><?php echo htmlspecialchars(format_vnd($row['thanh_tien'])); ?> VND</td>
              <td><?php echo htmlspecialchars($row['ghi_chu']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </section>

    <table>
      <thead>
        <tr>
          <th>Loại chậu/hoa</th>
          <th>Tổng SL Trong Vườn</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($flowers) === 0): ?>
        <tr><td colspan="3">Chưa có loại.</td></tr>
      <?php else: ?>
        <?php foreach ($flowers as $f): ?>
          <tr>
            <td><?php echo htmlspecialchars($f['ten']); ?></td>
            <td>
              <form method="post" action="save_flower.php" class="table-inline-form">
                <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                <input type="hidden" name="ten" value="<?php echo htmlspecialchars($f['ten']); ?>">
                <input type="number" step="0.01" min="0" name="so_luong_ban_dau" required value="<?php echo htmlspecialchars(format_qty($f['so_luong_ban_dau'])); ?>" class="auto-stock-input">
              </form>
            </td>
            <td>
              <a class="danger" href="delete_flower.php?id=<?php echo $f['id']; ?>" onclick="return confirm('Bạn có chắc muốn xóa loại này?');">Xóa</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <h2>Thống kê 1: Số lượng sau khi khách hàng CỌC</h2>
    <table>
      <thead>
        <tr>
          <th>Loại chậu/hoa</th>
          <th>Tổng SL Trong Vườn</th>
          <th>Tổng SL Lấy vườn khác</th>
          <th>SL Khách Cọc</th>
          <th>SL còn lại</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($flowers) === 0): ?>
        <tr><td colspan="5">Chưa có dữ liệu.</td></tr>
      <?php else: ?>
        <?php foreach ($flowers as $f): ?>
          <tr>
            <td><?php echo htmlspecialchars($f['ten']); ?></td>
            <td><?php echo htmlspecialchars(format_qty($f['so_luong_ban_dau'])); ?></td>
            <td><?php echo htmlspecialchars(format_qty($f['sl_lay_vuon_khac'])); ?></td>
            <td><?php echo htmlspecialchars(format_qty($f['sl_khach_coc'])); ?></td>
            <td><?php echo htmlspecialchars(format_qty($f['sl_con_lai_coc'])); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <h2>Thống kê 2: Số lượng sau khi lên xe</h2>
    <table>
      <thead>
        <tr>
          <th>Loại chậu/hoa</th>
          <th>SL Rớt lại/thiếu</th>
          <th>Tổng số hoa còn lại sau lên xe</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($flowers) === 0): ?>
        <tr><td colspan="3">Chưa có dữ liệu.</td></tr>
      <?php else: ?>
        <?php foreach ($flowers as $f): ?>
          <?php
            $delta = (float)$f['sl_phat_sinh_chot'];
            $delta_text = ($delta > 0 ? '+' : '') . format_qty($delta);
            $delta_class = $delta > 0 ? 'delta-neg' : ($delta < 0 ? 'delta-pos' : '');
          ?>
          <tr>
            <td><?php echo htmlspecialchars($f['ten']); ?></td>
            <td><span class="<?php echo $delta_class; ?>"><?php echo htmlspecialchars($delta_text); ?></span></td>
            <td><?php echo htmlspecialchars(format_qty($f['sl_con_lai_sau_phat_sinh'])); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    (function () {
      const forms = document.querySelectorAll('.table-inline-form');
      const timers = new WeakMap();

      forms.forEach((form) => {
        const input = form.querySelector('.auto-stock-input');
        if (!input) return;

        const autoSave = async () => {
          const formData = new FormData(form);
          try {
            await fetch('save_flower.php', {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });
          } catch (e) {
            console.error('Auto update stock failed', e);
          }
        };

        const scheduleSave = () => {
          const oldTimer = timers.get(form);
          if (oldTimer) clearTimeout(oldTimer);
          const timer = setTimeout(autoSave, 600);
          timers.set(form, timer);
        };

        input.addEventListener('input', scheduleSave);
        input.addEventListener('change', autoSave);
      });

      const importButton = document.getElementById('open-import-panel');
      const importPanel = document.getElementById('import-panel');
      if (importButton && importPanel) {
        importButton.addEventListener('click', function () {
          importPanel.hidden = !importPanel.hidden;
        });
      }
    })();
  </script>
</body>
</html>



