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

function merge_history_exists($pdo) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'khach_hang_hoa_thuc_te_gop_lich_su'");
    return (bool)$stmt->fetchColumn();
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

$actual_stmt = $pdo->prepare(
    'SELECT id, loai_hoa_id, so_luong, gia, DATE_FORMAT(created_at, "%Y-%m-%dT%H:%i") AS thoi_gian
     FROM khach_hang_hoa_thuc_te
     WHERE khach_hang_id = ?
     ORDER BY id'
);
$actual_stmt->execute([$customer_id]);
$actual_rows = $actual_stmt->fetchAll();

$merge_history_map = [];
if (merge_history_exists($pdo)) {
    $history_stmt = $pdo->prepare(
        'SELECT id, loai_hoa_id, so_luong, gia, DATE_FORMAT(thoi_gian, "%d/%m %H:%i") AS thoi_gian_hien_thi
         FROM khach_hang_hoa_thuc_te_gop_lich_su
         WHERE khach_hang_id = ?
         ORDER BY loai_hoa_id, thoi_gian ASC, id ASC'
    );
    $history_stmt->execute([$customer_id]);
    $history_rows = $history_stmt->fetchAll();
    foreach ($history_rows as $hrow) {
        $flower_id = (int)$hrow['loai_hoa_id'];
        if (!isset($merge_history_map[$flower_id])) {
            $merge_history_map[$flower_id] = [];
        }
        $merge_history_map[$flower_id][] = [
            'id' => (int)$hrow['id'],
            'so_luong' => (float)$hrow['so_luong'],
            'gia' => (float)$hrow['gia'],
            'thoi_gian' => $hrow['thoi_gian_hien_thi'],
        ];
    }
}

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

if (count($actual_rows) === 0) {
    $actual_rows[] = ['id' => null, 'loai_hoa_id' => '', 'so_luong' => '', 'gia' => '', 'thoi_gian' => date('Y-m-d\TH:i')];
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
  <link rel="stylesheet" href="assets/style.css?v=20260226_mobile43">
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
      <input type="hidden" name="merge_history_payload" id="merge-history-payload" value="">
      <div class="field status-field">
        <label>Tr&#7841;ng th&#225;i b&#7889;c h&#224;ng</label>
        <select name="trang_thai_boc" id="trang-thai-boc" class="status-select">
          <option value="chua_boc" <?php echo ($customer['trang_thai_boc'] ?? 'chua_boc') === 'chua_boc' ? 'selected' : ''; ?>>Ch&#432;a B&#7889;c</option>
          <option value="xong" <?php echo ($customer['trang_thai_boc'] ?? 'chua_boc') === 'xong' ? 'selected' : ''; ?>>Xong</option>
        </select>
      </div>
      <div class="actual-items-scroll-wrap">
      <div class="items-header item-row actual-row">
        <div>Loại</div>
        <div>Số lượng</div>
        <div>Giá (VND)</div>
        <div>Thời gian</div>
        <div></div>
      </div>
      <div id="actual-items">
        <?php foreach ($actual_rows as $idx => $row): ?>
          <?php
            $history_items = [];
            $flower_id_for_history = isset($row['loai_hoa_id']) ? (int)$row['loai_hoa_id'] : 0;
            if ($flower_id_for_history > 0 && isset($merge_history_map[$flower_id_for_history])) {
              $history_items = $merge_history_map[$flower_id_for_history];
            }
          ?>
          <div class="item-row actual-row" data-has-history="<?php echo count($history_items) > 0 ? '1' : '0'; ?>">
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
            <input type="datetime-local" name="items[<?php echo $idx; ?>][thoi_gian]" value="<?php echo htmlspecialchars($row['thoi_gian'] ?? date('Y-m-d\TH:i')); ?>" class="actual-time">
            <div class="row-action-group">
              <button type="button" class="save-row-btn" title="Lưu và gộp dòng trùng" aria-label="Lưu dòng">Lưu</button>
              <button type="button" class="remove-row-btn" onclick="removeActualRow(this)" aria-label="Xóa dòng" title="Xóa dòng"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg></button>
            </div>
          </div>
          <?php if (count($history_items) > 0): ?>
            <div class="merge-history-box">
              <button type="button" class="toggle-merge-history" data-target="merge-history-<?php echo $idx; ?>" data-label="L&#7883;ch s&#7917; &#273;&#227; g&#7897;p (<?php echo count($history_items); ?>)">
                L&#7883;ch s&#7917; &#273;&#227; g&#7897;p (<?php echo count($history_items); ?>)
              </button>
              <div class="merge-history-list" id="merge-history-<?php echo $idx; ?>" hidden>
                <ul>
                  <?php foreach ($history_items as $hitem): ?>
                    <li data-history-id="<?php echo (int)$hitem['id']; ?>">
                      <span>
                        SL <?php echo htmlspecialchars(format_decimal($hitem['so_luong'])); ?> -
                        Gi&#225; <?php echo htmlspecialchars(format_vnd($hitem['gia'])); ?> VND -
                        <?php echo htmlspecialchars($hitem['thoi_gian']); ?>
                      </span>
                      <button type="button" class="history-delete-btn" data-history-id="<?php echo (int)$hitem['id']; ?>" title="Xóa lịch sử" aria-label="Xóa lịch sử">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg>
                      </button>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
            </div>
      <div class="actions">
        <button type="button" class="button secondary" onclick="addActualRow()">Th&#234;m d&#242;ng Sau L&#234;n xe</button>
      </div>
    </form>

    <h2>So kh&#7899;p ph&#225;t sinh</h2>
    <div class="compare-table-wrap">
    <table class="compare-table">
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
    </div>

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
    <div class="item-row actual-row" data-has-history="0">
      <select name="" class="actual-select">
        <option value="">-- Ch&#7885;n lo&#7841;i --</option>
        <?php foreach ($flowers as $flower): ?>
          <option value="<?php echo $flower['id']; ?>"><?php echo htmlspecialchars($flower['ten']); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" step="0.01" min="0" name="" placeholder="S&#7889; l&#432;&#7907;ng" class="actual-qty">
      <input type="text" name="" placeholder="Gi&#225; (VND)" class="actual-price currency-input">
      <input type="datetime-local" name="" class="actual-time">
      <div class="row-action-group">
        <button type="button" class="save-row-btn" title="Lưu và gộp dòng trùng" aria-label="Lưu dòng">Lưu</button>
        <button type="button" class="remove-row-btn" onclick="removeActualRow(this)" aria-label="Xóa dòng" title="Xóa dòng"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg></button>
      </div>
    </div>
  </template>

  <script>
    const actualItems = document.getElementById('actual-items');
    const actualTemplate = document.getElementById('actual-row-template');
    const actualForm = document.getElementById('actual-sale-form');
    const mergeHistoryPayloadInput = document.getElementById('merge-history-payload');
    let saveTimer = null;
    let saving = false;
    let dirty = false;
    let mergeHistoryBuffer = [];

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
        row.querySelector('.actual-time').name = `items[${index}][thoi_gian]`;
      });
    }

    function nowDatetimeLocal() {
      const d = new Date();
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function parsePriceValue(raw) {
      const n = String(raw || '').replace(/[^0-9]/g, '');
      return n === '' ? 0 : Number(n);
    }

    function isRowReadyForMerge(row) {
      const qtyInput = row.querySelector('.actual-qty');
      const priceInput = row.querySelector('.actual-price');
      const qty = Number(qtyInput && qtyInput.value ? qtyInput.value : 0);
      const priceRaw = String(priceInput && priceInput.value ? priceInput.value : '').trim();
      const priceValue = parsePriceValue(priceRaw);
      return qty > 0 && priceRaw !== '' && priceValue > 0;
    }

    function mergeRowsByType(sourceRow) {
      if (!sourceRow) return false;
      const sourceSelect = sourceRow.querySelector('.actual-select');
      const flowerId = sourceSelect ? String(sourceSelect.value || '') : '';
      if (!flowerId) return false;

      const rows = Array.from(actualItems.querySelectorAll('.actual-row'));
      const sameTypeRows = rows.filter((row) => {
        const select = row.querySelector('.actual-select');
        return select && String(select.value || '') === flowerId;
      });
      const mergeCandidates = sameTypeRows.filter((row) => isRowReadyForMerge(row));
      if (mergeCandidates.length <= 1) return false;

      const keep = mergeCandidates[0];
      const keepHadHistory = keep.getAttribute('data-has-history') === '1';
      let sumQty = 0;
      let sumAmount = 0;
      let latestTime = '';

      mergeCandidates.forEach((row) => {
        const qtyInput = row.querySelector('.actual-qty');
        const priceInput = row.querySelector('.actual-price');
        const timeInput = row.querySelector('.actual-time');
        const qty = Number(qtyInput && qtyInput.value ? qtyInput.value : 0);
        const price = parsePriceValue(priceInput ? priceInput.value : '');
        const timeValue = (timeInput && timeInput.value) ? timeInput.value : nowDatetimeLocal();

        if (qty > 0) {
          sumQty += qty;
          sumAmount += qty * price;
          if (row !== keep || !keepHadHistory) {
            mergeHistoryBuffer.push({
              loai_hoa_id: Number(flowerId),
              so_luong: qty,
              gia: price,
              thoi_gian: timeValue,
            });
          }
        }
        if (!latestTime || timeValue > latestTime) {
          latestTime = timeValue;
        }
      });

      const keepQty = keep.querySelector('.actual-qty');
      const keepPrice = keep.querySelector('.actual-price');
      const keepTime = keep.querySelector('.actual-time');
      const mergedPrice = sumQty > 0 ? Math.round(sumAmount / sumQty) : 0;

      if (keepQty) keepQty.value = sumQty > 0 ? String(sumQty) : '';
      if (keepPrice) keepPrice.value = mergedPrice > 0 ? formatVndInput(String(mergedPrice)) : '';
      if (keepTime) keepTime.value = latestTime || nowDatetimeLocal();
      keep.setAttribute('data-has-history', '1');

      for (let i = 1; i < mergeCandidates.length; i += 1) {
        mergeCandidates[i].remove();
      }

      reindexActualRows();
      dirty = true;
      scheduleSave();
      return true;
    }

    function addActualRow() {
      const fragment = actualTemplate.content.cloneNode(true);
      const timeInput = fragment.querySelector('.actual-time');
      if (timeInput) {
        timeInput.value = nowDatetimeLocal();
      }
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
        row.querySelector('.actual-time').value = nowDatetimeLocal();
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
        if (mergeHistoryPayloadInput) {
          mergeHistoryPayloadInput.value = JSON.stringify(mergeHistoryBuffer);
        }
        const formData = new FormData(actualForm);
        const response = await fetch('save_actual_sale.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        if (response.ok) {
          const result = await response.json().catch(() => ({}));
          dirty = false;
          mergeHistoryBuffer = [];
          if (mergeHistoryPayloadInput) {
            mergeHistoryPayloadInput.value = '';
          }
          if (result && result.merged) {
            window.location.reload();
            return;
          }
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
      if (e.target.classList.contains('actual-select') || e.target.classList.contains('actual-qty') || e.target.classList.contains('actual-price') || e.target.classList.contains('actual-time')) {
        dirty = true;
        scheduleSave();
      }
    });

    actualItems.addEventListener('change', (e) => {
      if (e.target.classList.contains('actual-select') || e.target.classList.contains('actual-qty') || e.target.classList.contains('actual-price') || e.target.classList.contains('actual-time')) {
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

    actualItems.addEventListener('click', (e) => {
      const saveBtn = e.target.closest('.save-row-btn');
      if (!saveBtn) return;
      const row = saveBtn.closest('.actual-row');
      const merged = mergeRowsByType(row);
      if (!merged) {
        dirty = true;
        scheduleSave();
      }
    });

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.toggle-merge-history');
      if (!btn) return;
      const targetId = btn.getAttribute('data-target');
      if (!targetId) return;
      const panel = document.getElementById(targetId);
      if (!panel) return;
      const isHidden = panel.hasAttribute('hidden');
      if (isHidden) {
        panel.removeAttribute('hidden');
        btn.textContent = 'Đóng lịch sử gộp';
      } else {
        panel.setAttribute('hidden', 'hidden');
        btn.textContent = btn.getAttribute('data-label') || 'Lịch sử đã gộp';
      }
    });

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.history-delete-btn');
      if (!btn) return;
      e.preventDefault();
      const historyId = btn.getAttribute('data-history-id');
      if (!historyId) return;
      const params = new URLSearchParams();
      params.append('id', historyId);
      params.append('khach_hang_id', '<?php echo (int)$customer['id']; ?>');
      try {
        const res = await fetch('delete_merge_history.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: params.toString()
        });
        if (!res.ok) {
          window.location.href = `delete_merge_history.php?id=${encodeURIComponent(historyId)}&khach_hang_id=<?php echo (int)$customer['id']; ?>&return_id=<?php echo (int)$customer['id']; ?>`;
          return;
        }
        window.location.reload();
      } catch (err) {
        console.error('Delete merge history failed', err);
        window.location.href = `delete_merge_history.php?id=${encodeURIComponent(historyId)}&khach_hang_id=<?php echo (int)$customer['id']; ?>&return_id=<?php echo (int)$customer['id']; ?>`;
      }
    });

    setInterval(() => {
      autoSave();
    }, 3000);
  </script>
</body>
</html>
















