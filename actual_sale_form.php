<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customer_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, ten, sdt, dia_chi, coc, trang_thai_boc FROM khach_hang WHERE id = ?');
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();
if (!$customer) {
    header('Location: index.php');
    exit;
}

$flowers = $pdo->query('SELECT id, ten FROM loai_hoa ORDER BY ten')->fetchAll();
$flower_name_map = [];
foreach ($flowers as $flower) {
    $flower_name_map[(int)$flower['id']] = $flower['ten'];
}

$plan_stmt = $pdo->prepare(
    'SELECT kh.loai_hoa_id, l.ten,
            SUM(kh.so_luong) AS so_luong,
            SUM(kh.so_luong * kh.gia) AS thanh_tien
     FROM khach_hang_hoa kh
     JOIN loai_hoa l ON l.id = kh.loai_hoa_id
     WHERE kh.khach_hang_id = ?
     GROUP BY kh.loai_hoa_id, l.ten
     ORDER BY l.ten'
);
$plan_stmt->execute([$customer_id]);
$plan_rows = $plan_stmt->fetchAll();

$actual_stmt = $pdo->prepare('SELECT id, loai_hoa_id, so_luong, gia FROM khach_hang_hoa_thuc_te WHERE khach_hang_id = ? ORDER BY id');
$actual_stmt->execute([$customer_id]);
$actual_rows = $actual_stmt->fetchAll();

function format_decimal($value) {
    $str = (string)$value;
    if (strpos($str, '.') === false) {
        return $str;
    }
    $str = rtrim($str, '0');
    return rtrim($str, '.');
}

function format_vnd($value) {
    return number_format((float)$value, 0, ',', '.');
}

$plan_map = [];
$total_plan_amount = 0;
foreach ($plan_rows as $row) {
    $flower_id = (int)$row['loai_hoa_id'];
    $qty = (float)$row['so_luong'];
    $amount = (float)$row['thanh_tien'];
    $plan_map[$flower_id] = [
        'ten' => $row['ten'],
        'qty' => $qty,
        'amount' => $amount,
    ];
    $total_plan_amount += $amount;
}

if (count($actual_rows) === 0 && count($plan_rows) > 0) {
    foreach ($plan_rows as $row) {
        $qty = (float)$row['so_luong'];
        $unit_price = 0;
        if ($qty > 0) {
            $unit_price = (float)$row['thanh_tien'] / $qty;
        }
        $actual_rows[] = [
            'id' => null,
            'loai_hoa_id' => (int)$row['loai_hoa_id'],
            'so_luong' => format_decimal($qty),
            'gia' => format_decimal($unit_price),
        ];
    }
}

if (count($actual_rows) === 0) {
    $actual_rows[] = ['id' => null, 'loai_hoa_id' => '', 'so_luong' => '', 'gia' => ''];
}

$actual_sum_stmt = $pdo->prepare(
    'SELECT kh.loai_hoa_id, l.ten,
            SUM(kh.so_luong) AS so_luong,
            SUM(kh.so_luong * kh.gia) AS thanh_tien
     FROM khach_hang_hoa_thuc_te kh
     JOIN loai_hoa l ON l.id = kh.loai_hoa_id
     WHERE kh.khach_hang_id = ?
     GROUP BY kh.loai_hoa_id, l.ten
     ORDER BY l.ten'
);
$actual_sum_stmt->execute([$customer_id]);
$actual_sum_rows = $actual_sum_stmt->fetchAll();

$actual_map = [];
$total_actual_amount = 0;
foreach ($actual_sum_rows as $row) {
    $flower_id = (int)$row['loai_hoa_id'];
    $qty = (float)$row['so_luong'];
    $amount = (float)$row['thanh_tien'];
    $actual_map[$flower_id] = [
        'ten' => $row['ten'],
        'qty' => $qty,
        'amount' => $amount,
    ];
    $total_actual_amount += $amount;
}

if (count($actual_sum_rows) === 0) {
    foreach ($actual_rows as $row) {
        $flower_id = isset($row['loai_hoa_id']) ? (int)$row['loai_hoa_id'] : 0;
        if ($flower_id <= 0) {
            continue;
        }
        $qty = isset($row['so_luong']) ? (float)$row['so_luong'] : 0;
        $price = isset($row['gia']) ? (float)$row['gia'] : 0;
        $amount = $qty * $price;
        $name = $flower_name_map[$flower_id] ?? ('#' . $flower_id);

        if (!isset($actual_map[$flower_id])) {
            $actual_map[$flower_id] = [
                'ten' => $name,
                'qty' => 0,
                'amount' => 0,
            ];
        }
        $actual_map[$flower_id]['qty'] += $qty;
        $actual_map[$flower_id]['amount'] += $amount;
        $total_actual_amount += $amount;
    }
}

$all_flower_ids = array_unique(array_merge(array_keys($plan_map), array_keys($actual_map)));
sort($all_flower_ids);

$compare_rows = [];
foreach ($all_flower_ids as $flower_id) {
    $plan_qty = isset($plan_map[$flower_id]) ? $plan_map[$flower_id]['qty'] : 0;
    $actual_qty = isset($actual_map[$flower_id]) ? $actual_map[$flower_id]['qty'] : 0;
    $plan_amount = isset($plan_map[$flower_id]) ? $plan_map[$flower_id]['amount'] : 0;
    $actual_amount = isset($actual_map[$flower_id]) ? $actual_map[$flower_id]['amount'] : 0;
    $name = isset($actual_map[$flower_id]) ? $actual_map[$flower_id]['ten'] : $plan_map[$flower_id]['ten'];

    $compare_rows[] = [
        'ten' => $name,
        'plan_qty' => $plan_qty,
        'actual_qty' => $actual_qty,
        'qty_diff' => $actual_qty - $plan_qty,
        'plan_amount' => $plan_amount,
        'actual_amount' => $actual_amount,
        'amount_diff' => $actual_amount - $plan_amount,
    ];
}

$remaining_after_deposit = max($total_actual_amount - (float)$customer['coc'], 0);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ch&#7889;t b&#225;n Sau L&#234;n xe</title>
  <link rel="stylesheet" href="assets/style.css?v=20260226_mobile7">
</head>
<body>
  <div class="container">
    <header class="header actual-sale-header">
      <h1>Ch&#7889;t b&#225;n Sau L&#234;n xe</h1>
      <div class="actions">
        <a class="button secondary" href="index.php">Quay l&#7841;i</a>
        <a class="button secondary" href="customer_form.php?id=<?php echo $customer['id']; ?>">&#272;&#417;n &#273;&#7863;t c&#7885;c</a>
      </div>
    </header>

    <?php if (isset($_GET['msg'])): ?>
      <div class="notice">&#272;&#227; l&#432;u d&#7919; li&#7879;u b&#225;n Sau L&#234;n xe.</div>
    <?php endif; ?>

    <div class="customer-info-inline">
      <div><strong>Kh&#225;ch h&#224;ng:</strong> <?php echo htmlspecialchars($customer['ten']); ?></div>
      <div><strong>S&#272;T:</strong> <?php echo htmlspecialchars($customer['sdt']); ?></div>
      <div><strong>&#272;&#7883;a ch&#7881;:</strong> <?php echo htmlspecialchars($customer['dia_chi']); ?></div>
      <div><strong>&#272;&#227; c&#7885;c:</strong> <?php echo format_vnd($customer['coc']); ?> VND</div>
    </div>

    <h2>Nh&#7853;p b&#225;n Sau L&#234;n xe</h2>
    <form method="post" action="save_actual_sale.php" id="actual-sale-form">
      <input type="hidden" name="khach_hang_id" value="<?php echo $customer['id']; ?>">
      <input type="hidden" name="ajax" value="1">
      <div class="field">
        <label>Tr&#7841;ng th&#225;i b&#7889;c h&#224;ng</label>
        <select name="trang_thai_boc" id="trang-thai-boc">
          <option value="chua_boc" <?php echo ($customer['trang_thai_boc'] ?? 'chua_boc') === 'chua_boc' ? 'selected' : ''; ?>>Ch&#432;a B&#7889;c</option>
          <option value="xong" <?php echo ($customer['trang_thai_boc'] ?? 'chua_boc') === 'xong' ? 'selected' : ''; ?>>Xong</option>
        </select>
      </div>
      <div class="actual-items-scroll-wrap">
      <div class="items-header item-row actual-row">
        <div>Loại</div>
        <div>Số lượng</div>
        <div>Giá (VND)</div>
        <div></div>
      </div>
      <div id="actual-items">
        <?php foreach ($actual_rows as $idx => $row): ?>
          <div class="item-row actual-row">
            <select name="items[<?php echo $idx; ?>][loai_hoa_id]" class="actual-select">
              <option value="">-- Ch&#7885;n lo&#7841;i --</option>
              <?php foreach ($flowers as $flower): ?>
                <option value="<?php echo $flower['id']; ?>" <?php echo ((string)$row['loai_hoa_id'] === (string)$flower['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($flower['ten']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" min="0" name="items[<?php echo $idx; ?>][so_luong]" value="<?php echo htmlspecialchars(format_decimal($row['so_luong'])); ?>" placeholder="S&#7889; l&#432;&#7907;ng" class="actual-qty">
            <input type="text" name="items[<?php echo $idx; ?>][gia]" value="<?php echo htmlspecialchars(format_decimal($row['gia'])); ?>" placeholder="Gi&#225; (VND)" class="actual-price currency-input">
            <button type="button" class="remove-row-btn" onclick="removeActualRow(this)" aria-label="Xóa dòng" title="Xóa dòng"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg></button>
          </div>
        <?php endforeach; ?>
      </div>
            </div>
      <div class="actions">
        <button type="button" class="button secondary" onclick="addActualRow()">Th&#234;m d&#242;ng Sau L&#234;n xe</button>
      </div>
    </form>

    <h2>So kh&#7899;p ph&#225;t sinh</h2>
    <table>
      <thead>
        <tr>
          <th>Lo&#7841;i hoa/ch&#7853;u</th>
          <th>&#272;&#7863;t c&#7885;c (SL)</th>
          <th>&#272;&#227; l&#234;n xe (SL)</th>
          <th>Ch&#432;a B&#7889;c-R&#7899;t l&#7841;i</th>
          <th>Ti&#7873;n &#273;&#227; l&#234;n xe</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($compare_rows) === 0): ?>
        <tr><td colspan="5">Ch&#432;a c&#243; d&#7919; li&#7879;u so kh&#7899;p.</td></tr>
      <?php else: ?>
        <?php foreach ($compare_rows as $row): ?>
          <?php
            $remaining_qty = $row['plan_qty'] - $row['actual_qty'];
            $remaining_qty_class = $remaining_qty > 0 ? 'delta-neg' : ($remaining_qty < 0 ? 'delta-pos' : '');
            $remaining_qty_text = format_decimal($remaining_qty);
          ?>
          <tr>
            <td><?php echo htmlspecialchars($row['ten']); ?></td>
            <td><?php echo htmlspecialchars(format_decimal($row['plan_qty'])); ?></td>
            <td><?php echo htmlspecialchars(format_decimal($row['actual_qty'])); ?></td>
            <td class="<?php echo $remaining_qty_class; ?>"><?php echo htmlspecialchars($remaining_qty_text); ?></td>
            <td><?php echo htmlspecialchars(format_vnd($row['actual_amount'])); ?> VND</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="totals-grid actual-sale-totals">
      <div class="field">
        <label>T&#7893;ng ti&#7873;n hoa &#273;&#227; &#273;&#7863;t</label>
        <input type="text" value="<?php echo format_vnd($total_plan_amount); ?> VND" readonly>
      </div>
      <div class="field">
        <label>T&#7893;ng ti&#7873;n hoa &#273;&#227; l&#234;n xe</label>
        <input type="text" value="<?php echo format_vnd($total_actual_amount); ?> VND" readonly>
      </div>
      <div class="field">
        <label>Ti&#7873;n hoa &#273;&#227; l&#234;n xe tr&#7915; c&#7885;c</label>
        <input type="text" value="<?php echo format_vnd($remaining_after_deposit); ?> VND" readonly>
      </div>
    </div>
  </div>

  <template id="actual-row-template">
    <div class="item-row actual-row">
      <select name="" class="actual-select">
        <option value="">-- Ch&#7885;n lo&#7841;i --</option>
        <?php foreach ($flowers as $flower): ?>
          <option value="<?php echo $flower['id']; ?>"><?php echo htmlspecialchars($flower['ten']); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" step="0.01" min="0" name="" placeholder="S&#7889; l&#432;&#7907;ng" class="actual-qty">
      <input type="text" name="" placeholder="Gi&#225; (VND)" class="actual-price currency-input">
      <button type="button" class="remove-row-btn" onclick="removeActualRow(this)" aria-label="Xóa dòng" title="Xóa dòng"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg></button>
    </div>
  </template>

  <script>
    const actualItems = document.getElementById('actual-items');
    const actualTemplate = document.getElementById('actual-row-template');
    const actualForm = document.getElementById('actual-sale-form');
    let saveTimer = null;
    let saving = false;
    let dirty = false;

    function formatVndInput(value) {
      const n = String(value || '').replace(/[^0-9]/g, '');
      if (n === '') return '';
      return Number(n).toLocaleString('vi-VN');
    }

    function reindexActualRows() {
      const rows = actualItems.querySelectorAll('.actual-row');
      rows.forEach((row, index) => {
        row.querySelector('.actual-select').name = `items[${index}][loai_hoa_id]`;
        row.querySelector('.actual-qty').name = `items[${index}][so_luong]`;
        row.querySelector('.actual-price').name = `items[${index}][gia]`;
      });
    }

    function addActualRow() {
      const fragment = actualTemplate.content.cloneNode(true);
      actualItems.appendChild(fragment);
      reindexActualRows();
      dirty = true;
      scheduleSave();
    }

    function removeActualRow(button) {
      const row = button.closest('.actual-row');
      if (!row) return;
      if (actualItems.children.length === 1) {
        row.querySelector('.actual-select').value = '';
        row.querySelector('.actual-qty').value = '';
        row.querySelector('.actual-price').value = '';
        dirty = true;
        scheduleSave();
        return;
      }
      row.remove();
      reindexActualRows();
      dirty = true;
      scheduleSave();
    }

    async function autoSave() {
      if (saving || !dirty) return;
      saving = true;
      try {
        const formData = new FormData(actualForm);
        const response = await fetch('save_actual_sale.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        if (response.ok) {
          dirty = false;
        }
      } catch (e) {
        console.error('Auto save actual sale failed', e);
      } finally {
        saving = false;
      }
    }

    function scheduleSave() {
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(autoSave, 700);
    }

    actualItems.addEventListener('input', (e) => {
      if (e.target.classList.contains('actual-price')) {
        const cursorEnd = e.target.selectionStart === e.target.value.length;
        e.target.value = formatVndInput(e.target.value);
        if (cursorEnd) {
          e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        }
      }
      if (e.target.classList.contains('actual-select') || e.target.classList.contains('actual-qty') || e.target.classList.contains('actual-price')) {
        dirty = true;
        scheduleSave();
      }
    });

    actualItems.addEventListener('change', (e) => {
      if (e.target.classList.contains('actual-select') || e.target.classList.contains('actual-qty') || e.target.classList.contains('actual-price')) {
        dirty = true;
        scheduleSave();
      }
    });

    const trangThaiBoc = document.getElementById('trang-thai-boc');
    if (trangThaiBoc) {
      trangThaiBoc.addEventListener('change', () => {
        dirty = true;
        scheduleSave();
      });
    }

    actualItems.querySelectorAll('.actual-price').forEach((input) => {
      if (input.value !== '') {
        input.value = formatVndInput(input.value);
      }
    });

    reindexActualRows();
    setInterval(() => {
      autoSave();
    }, 3000);
  </script>
</body>
</html>









