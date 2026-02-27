<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();
$customers = $pdo->query('SELECT id, ten, dia_chi, sdt, trang_thai_boc FROM khach_hang ORDER BY id DESC')->fetchAll();

function total_quantity($pdo, $khach_hang_id) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(so_luong), 0) AS total FROM khach_hang_hoa WHERE khach_hang_id = ?');
    $stmt->execute([$khach_hang_id]);
    $row = $stmt->fetch();
    return $row ? $row['total'] : 0;
}

function format_decimal($value) {
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
  <title>Quản lý hoa Tết</title>
  <link rel="stylesheet" href="assets/style.css?v=20260226_mobile24">
</head>
<body>
  <div class="container">
    <header class="header">
      <h1>Khách hàng</h1>
      <div class="actions">
        <a class="button" href="customer_form.php">Thêm khách hàng</a>
        <a class="button secondary" href="flowers.php">Quản lý loại hoa</a>
        <a class="button secondary" href="report.php">Thống kê</a>
      </div>
    </header>

    <?php if (isset($_GET['msg'])): ?>
      <div class="notice">Thao tác đã hoàn tất.</div>
    <?php endif; ?>

    <form class="inline-form" id="search-form" onsubmit="return false;">
      <input type="text" id="search-input" placeholder="Tìm theo tên khách hàng..." autocomplete="off">
      <button type="button" class="button secondary" id="filter-pending">Chưa Bốc</button>
      <button type="button" class="button secondary" id="clear-search">Xóa lọc</button>
    </form>

    <table class="index-table">
      <thead>
        <tr>
          <th>Tên Khách hàng</th>
          <th class="ship-col">Đặt cọc</th>
          <th>Trạng thái</th>
          <th>Số điện thoại</th>
          <th>Địa chỉ</th>
          <th class="quantity-col">Tổng số lượng (Cặp)</th>
          <th class="actions-col">Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($customers) === 0): ?>
        <tr><td colspan="7">Chưa có khách hàng.</td></tr>
      <?php else: ?>
        <?php foreach ($customers as $c): ?>
          <tr class="customer-row" data-name="<?php echo htmlspecialchars($c['ten']); ?>" data-status="<?php echo (($c['trang_thai_boc'] ?? 'chua_boc') === 'xong') ? 'xong' : 'chua_boc'; ?>">
            <td><a class="customer-name-link" href="actual_sale_form.php?id=<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['ten']); ?></a></td>
            <td class="ship-col">
              <a class="action-link ship-btn" href="customer_form.php?id=<?php echo $c['id']; ?>">Đặt cọc</a>
            </td>
            <td>
              <?php if (($c['trang_thai_boc'] ?? 'chua_boc') === 'xong'): ?>
                <span class="status-pill done">Xong</span>
              <?php else: ?>
                <span class="status-pill pending">Chưa Bốc</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($c['sdt']); ?></td>
            <td><?php echo htmlspecialchars($c['dia_chi']); ?></td>
            <td class="quantity-col"><?php echo htmlspecialchars(format_decimal(total_quantity($pdo, $c['id']))); ?></td>
            <td>
              <div class="row-actions">
                <a class="action-link danger icon-trash" href="delete_customer.php?id=<?php echo $c['id']; ?>" onclick="return confirm('Bạn có chắc muốn xóa khách hàng này?');" aria-label="Xóa" title="Xóa">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <script>
    (function () {
      const form = document.getElementById('search-form');
      const input = document.getElementById('search-input');
      if (!form || !input) return;

      const rows = Array.from(document.querySelectorAll('.customer-row'));
      const clearBtn = document.getElementById('clear-search');
      const pendingBtn = document.getElementById('filter-pending');
      let pendingOnly = false;

      function normalize(text) {
        return (text || '').toLocaleLowerCase('vi-VN').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      }

      function applyFilters() {
        const keyword = normalize(input.value.trim());
        rows.forEach(function (row) {
          const name = normalize(row.getAttribute('data-name'));
          const status = row.getAttribute('data-status') || 'chua_boc';
          const matchesName = (keyword === '' || name.includes(keyword));
          const matchesStatus = !pendingOnly || status === 'chua_boc';
          row.style.display = (matchesName && matchesStatus) ? '' : 'none';
        });
      }

      let timer = null;
      input.addEventListener('input', function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(applyFilters, 300);
      });

      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          input.value = '';
          pendingOnly = false;
          if (pendingBtn) pendingBtn.classList.remove('active');
          applyFilters();
          input.focus();
        });
      }

      if (pendingBtn) {
        pendingBtn.addEventListener('click', function () {
          pendingOnly = !pendingOnly;
          pendingBtn.classList.toggle('active', pendingOnly);
          applyFilters();
        });
      }
    })();
  </script>
</body>
</html>
