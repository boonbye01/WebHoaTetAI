<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();
$flowers = $pdo->query('SELECT id, ten FROM loai_hoa ORDER BY ten')->fetchAll();

$khach_hang = [
    'id' => null,
    'ten' => '',
    'dia_chi' => '',
    'sdt' => '',
    'coc' => 0,
];
$items = [];

function format_decimal_input($value) {
    if ($value === null || $value === '') {
        return '';
    }
    $str = (string)$value;
    if (strpos($str, '.') === false) {
        return $str;
    }
    $str = rtrim($str, '0');
    return rtrim($str, '.');
}

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT id, ten, dia_chi, sdt, coc FROM khach_hang WHERE id = ?');
    $stmt->execute([$_GET['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $khach_hang = $row;
        $stmt = $pdo->prepare('SELECT id, loai_hoa_id, so_luong, gia FROM khach_hang_hoa WHERE khach_hang_id = ? ORDER BY id ASC');
        $stmt->execute([$khach_hang['id']]);
        $items = $stmt->fetchAll();
    }
}

if (count($items) === 0) {
    $items[] = ['loai_hoa_id' => '', 'so_luong' => '', 'gia' => ''];
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $khach_hang['id'] ? 'Sá»­a khÃ¡ch hÃ ng' : 'ThÃªm khÃ¡ch hÃ ng'; ?></title>
  <link rel="stylesheet" href="assets/style.css?v=20260226_mobile1">
</head>
<body>
  <div class="container">
    <header class="header">
      <h1><?php echo $khach_hang['id'] ? 'Sá»­a khÃ¡ch hÃ ng' : 'ThÃªm khÃ¡ch hÃ ng'; ?></h1>
      <div class="actions">
        <a class="button secondary" href="index.php">Quay láº¡i</a>
        <a class="button secondary" href="logout.php">ÄÄƒng xuáº¥t</a>
      </div>
    </header>

    <?php if (count($flowers) === 0): ?>
      <div class="notice">ChÆ°a cÃ³ loáº¡i hoa. Vui lÃ²ng thÃªm trong má»¥c Quáº£n lÃ½ loáº¡i hoa trÆ°á»›c.</div>
    <?php endif; ?>

    <form id="customer-form" method="post" action="save_customer.php" autocomplete="off">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($khach_hang['id']); ?>">
      <input type="hidden" name="ajax" value="1">

      <div class="customer-grid">
        <div class="field">
          <label>TÃªn</label>
          <input type="text" name="ten" required value="<?php echo htmlspecialchars($khach_hang['ten']); ?>">
        </div>

        <div class="field">
          <label>Sá»‘ Ä‘iá»‡n thoáº¡i</label>
          <input type="text" name="sdt" value="<?php echo htmlspecialchars($khach_hang['sdt']); ?>">
        </div>

        <div class="field">
          <label>Äá»‹a chá»‰</label>
          <input type="text" name="dia_chi" value="<?php echo htmlspecialchars($khach_hang['dia_chi']); ?>">
        </div>
      </div>

      <h2>Loáº¡i hoa/cháº­u</h2>
      <div class="items-header item-row">
        <div>Loáº¡i</div>
        <div>Sá»‘ lÆ°á»£ng</div>
        <div>GiÃ¡ (VND)</div>
        <div>ThÃ nh tiá»n</div>
        <div></div>
      </div>
      <div id="items">
        <?php foreach ($items as $index => $item): ?>
          <div class="item-row">
            <select name="items[<?php echo $index; ?>][loai_hoa_id]" class="flower-select">
              <option value="">-- Chá»n loáº¡i --</option>
              <?php foreach ($flowers as $f): ?>
                <option value="<?php echo $f['id']; ?>" <?php echo ($item['loai_hoa_id'] == $f['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($f['ten']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" min="0" name="items[<?php echo $index; ?>][so_luong]" class="qty-input" value="<?php echo htmlspecialchars(format_decimal_input($item['so_luong'])); ?>" placeholder="SL">
            <input type="text" name="items[<?php echo $index; ?>][gia]" class="price-input currency-input" value="<?php echo htmlspecialchars(format_decimal_input($item['gia'] ?? '')); ?>" placeholder="0">
            <div class="subtotal-text">0</div>
            <button type="button" class="button danger" onclick="removeRow(this)">XÃ³a dÃ²ng</button>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="actions">
        <button type="button" class="button secondary" onclick="addRow()">ThÃªm loáº¡i hoa/cháº­u</button>
      </div>

      <div class="totals-grid">
        <div class="field">
          <label>Tá»•ng táº¥t cáº£ loáº¡i hoa</label>
          <input type="text" id="tong-tat-ca" value="0" readonly>
        </div>
        <div class="field">
          <label>Nháº­n cá»c (VND)</label>
          <input type="text" id="coc-hien-thi" class="currency-input" value="<?php echo htmlspecialchars(format_decimal_input($khach_hang['coc'])); ?>" placeholder="0">
          <input type="hidden" name="coc" id="coc-raw" value="<?php echo htmlspecialchars(format_decimal_input($khach_hang['coc'])); ?>">
        </div>
        <div class="field">
          <label>CÃ²n láº¡i</label>
          <input type="text" id="con-lai" value="0" readonly>
        </div>
      </div>
    </form>
  </div>

  <template id="row-template">
    <div class="item-row">
      <select name="" class="flower-select">
        <option value="">-- Chá»n loáº¡i --</option>
        <?php foreach ($flowers as $f): ?>
          <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['ten']); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" step="0.01" min="0" name="" class="qty-input" placeholder="SL">
      <input type="text" name="" class="price-input currency-input" placeholder="0">
      <div class="subtotal-text">0</div>
      <button type="button" class="button danger" onclick="removeRow(this)">XÃ³a dÃ²ng</button>
    </div>
  </template>
  <script src="assets/app.js?v=20260225_2"></script>
</body>
</html>

