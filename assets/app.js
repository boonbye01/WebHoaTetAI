const form = document.getElementById('customer-form');
const items = document.getElementById('items');
const template = document.getElementById('row-template');
const tongTatCaInput = document.getElementById('tong-tat-ca');
const cocHienThiInput = document.getElementById('coc-hien-thi');
const cocRawInput = document.getElementById('coc-raw');
const conLaiInput = document.getElementById('con-lai');

let saveTimer = null;
let saving = false;
let dirty = false;

function parseMoney(value) {
  if (value === null || value === undefined) return 0;
  const raw = String(value).replace(/[^0-9]/g, '');
  if (raw === '') return 0;
  return Number(raw);
}

function formatVnd(value) {
  const num = Number(value) || 0;
  return num.toLocaleString('vi-VN');
}

function parseQty(value) {
  const num = parseFloat(value);
  if (Number.isNaN(num) || num < 0) {
    return 0;
  }
  return num;
}

function formatQty(value) {
  const num = Number(value);
  if (!Number.isFinite(num)) return '0';
  if (Number.isInteger(num)) return String(num);
  return String(num).replace(/\.?0+$/, '');
}

function formatCurrencyInput(input) {
  const money = parseMoney(input.value);
  input.value = money > 0 ? formatVnd(money) : '';
}

function rowInputs(row) {
  return {
    select: row.querySelector('.flower-select'),
    qty: row.querySelector('.qty-input'),
    price: row.querySelector('.price-input'),
    subtotal: row.querySelector('.subtotal-text'),
  };
}

function updateRowSubtotal(row) {
  const { qty, price, subtotal } = rowInputs(row);
  const qtyValue = parseQty(qty.value);
  const priceValue = parseMoney(price.value);
  const subtotalValue = qtyValue * priceValue;
  subtotal.textContent = formatVnd(subtotalValue);
  return subtotalValue;
}

function updateTotals() {
  const rows = items.querySelectorAll('.item-row');
  let totalAll = 0;
  rows.forEach((row) => {
    totalAll += updateRowSubtotal(row);
  });

  const cocValue = parseMoney(cocHienThiInput.value);
  cocRawInput.value = String(cocValue);
  tongTatCaInput.value = formatVnd(totalAll);
  const conLai = Math.max(totalAll - cocValue, 0);
  conLaiInput.value = formatVnd(conLai);
}

function reindexRows() {
  const rows = items.querySelectorAll('.item-row');
  rows.forEach((row, index) => {
    const { select, qty, price } = rowInputs(row);
    select.name = `items[${index}][loai_hoa_id]`;
    qty.name = `items[${index}][so_luong]`;
    price.name = `items[${index}][gia]`;
  });
}

function addRow() {
  const clone = template.content.cloneNode(true);
  const row = clone.querySelector('.item-row');
  items.appendChild(row);
  reindexRows();
  updateTotals();
  dirty = true;
  scheduleSave();
}

function removeRow(button) {
  const row = button.closest('.item-row');
  if (!row) return;

  if (!confirm('Bạn có chắc muốn xóa dòng này?')) {
    return;
  }

  if (items.children.length === 1) {
    const { select, qty, price } = rowInputs(row);
    select.value = '';
    qty.value = '';
    price.value = '';
  } else {
    row.remove();
    reindexRows();
  }
  updateTotals();
  dirty = true;
  scheduleSave();
}

async function autoSave() {
  if (saving) return;
  if (!dirty) return;
  const tenInput = form.querySelector('input[name="ten"]');
  if (!tenInput || tenInput.value.trim() === '') {
    return;
  }

  saving = true;

  try {
    const formData = new FormData(form);
    const response = await fetch(form.action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error('save_failed');
    }

    const data = await response.json();
    if (!data || data.ok !== true) {
      throw new Error('save_failed');
    }

    const idInput = form.querySelector('input[name="id"]');
    if (idInput && data.id) {
      idInput.value = String(data.id);
    }
    dirty = false;

  } catch (err) {
    console.error('Auto-save failed', err);
  } finally {
    saving = false;
  }
}

function scheduleSave() {
  if (saveTimer) {
    clearTimeout(saveTimer);
  }
  saveTimer = setTimeout(autoSave, 700);
}

items.addEventListener('input', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;

  if (target.classList.contains('price-input')) {
    const cursorEnd = target.selectionStart === target.value.length;
    formatCurrencyInput(target);
    if (cursorEnd) {
      target.setSelectionRange(target.value.length, target.value.length);
    }
  }

  if (target.classList.contains('qty-input') || target.classList.contains('price-input') || target.classList.contains('flower-select')) {
    updateTotals();
    dirty = true;
    scheduleSave();
  }
});

items.addEventListener('change', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  if (target.classList.contains('price-input')) {
    formatCurrencyInput(target);
  }
  updateTotals();
  dirty = true;
  scheduleSave();
});

form.addEventListener('input', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  if (target.closest('#items')) return;

  if (target === cocHienThiInput) {
    formatCurrencyInput(cocHienThiInput);
    updateTotals();
    dirty = true;
    scheduleSave();
    return;
  }

  if (target.matches('input[name="ten"], input[name="sdt"], input[name="dia_chi"]')) {
    dirty = true;
    scheduleSave();
  }
});

form.addEventListener('change', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  if (target === cocHienThiInput) {
    formatCurrencyInput(cocHienThiInput);
    updateTotals();
    dirty = true;
    scheduleSave();
  }
});

// Fallback autosave in case some input events are not fired by browser/extensions.
setInterval(() => {
  autoSave();
}, 3000);

reindexRows();
items.querySelectorAll('.price-input').forEach((input) => {
  if (input.value !== '') {
    formatCurrencyInput(input);
  }
});
if (cocHienThiInput.value !== '') {
  formatCurrencyInput(cocHienThiInput);
}
updateTotals();
