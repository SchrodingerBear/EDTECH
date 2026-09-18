document.addEventListener('DOMContentLoaded', function () {
  var container = document.getElementById('items-container');
  if (!container) return;

  var services = window.ORDER_SERVICES || [];
  var serviceById = {};
  services.forEach(function (s) { serviceById[s.id] = s; });

  function peso(v) {
    v = parseFloat(v) || 0;
    return '\u20B1' + (Number.isInteger(v) ? v.toLocaleString() : v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  }

  function serviceOption(selected) {
    return '<option value="">Select service</option>' + services.map(function (s) {
      var sel = String(s.id) === String(selected) ? ' selected' : '';
      return '<option value="' + s.id + '" data-price="' + s.price + '"' + sel + '>' + s.name + ' \u2014 ' + peso(s.price) + ' / ' + s.unit + '</option>';
    }).join('');
  }

  function makeRow(item) {
    item = item || {};
    var row = document.createElement('div');
    row.className = 'row g-2 item-row mb-2';
    row.innerHTML =
      '<div class="col-md-6"><select class="form-select item-service">' + serviceOption(item.service_id || '') + '</select></div>' +
      '<div class="col-md-2"><input class="form-control item-qty" type="number" step="0.01" min="0.01" value="' + (item.quantity || 1) + '"></div>' +
      '<div class="col-md-2"><input class="form-control item-price" type="number" step="0.01" min="0" value="' + (item.unit_price || '') + '"></div>' +
      '<div class="col-md-1 text-end"><span class="item-line form-control-plaintext">' + peso(item.line_total) + '</span></div>' +
      '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm btn-remove-item" title="Remove">&times;</button></div>';
    return row;
  }

  var addBtn = document.getElementById('add-item');
  var delFee = document.getElementById('calc-delivery');
  var disc = document.getElementById('calc-discount');
  var sub = document.getElementById('calc-subtotal');
  var total = document.getElementById('calc-total');
  var paid = document.getElementById('calc-paid');
  var pickup = document.getElementById('pickup-type');
  var deliveryAddr = document.getElementById('delivery-address');
  var deliveryLabel = document.getElementById('delivery-address-label');

  function recalc() {
    var subt = 0;
    container.querySelectorAll('.item-row').forEach(function (row) {
      var price = parseFloat(row.querySelector('.item-price').value) || 0;
      var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
      var line = Math.round(price * qty * 100) / 100;
      row.querySelector('.item-line').textContent = peso(line);
      subt += line;
    });
    subt = Math.round(subt * 100) / 100;
    var df = pickup && pickup.value === 'delivery' ? (parseFloat(delFee.value) || 0) : 0;
    var d = parseFloat(disc.value) || 0;
    var t = Math.max(0, Math.round((subt + df - d) * 100) / 100);
    sub.textContent = peso(subt);
    total.textContent = peso(t);
    if (paid) {
      var p = parseFloat(paid.value) || 0;
      if (p > t) { paid.value = t; p = t; }
    }
  }

  container.querySelectorAll('.item-row').forEach(function (row) {
    row.querySelector('.item-service').addEventListener('change', function () {
      var price = this.selectedOptions[0].dataset.price;
      row.querySelector('.item-price').value = price || '';
      recalc();
    });
    row.querySelector('.item-service').dispatchEvent(new Event('change'));
    row.querySelector('.item-price').addEventListener('input', recalc);
    row.querySelector('.item-qty').addEventListener('input', recalc);
  });

  if (addBtn) addBtn.addEventListener('click', function () {
    container.appendChild(makeRow({}));
    var last = container.lastElementChild;
    var svc = last.querySelector('.item-service');
    svc.addEventListener('change', function () {
      var price = this.selectedOptions[0].dataset.price;
      last.querySelector('.item-price').value = price || '';
      recalc();
    });
    last.querySelector('.item-price').addEventListener('input', recalc);
    last.querySelector('.item-qty').addEventListener('input', recalc);
    recalc();
    svc.focus();
  });

  container.addEventListener('click', function (e) {
    if (e.target.closest('.btn-remove-item')) {
      var rows = container.querySelectorAll('.item-row');
      if (rows.length > 1) {
        e.target.closest('.item-row').remove();
        recalc();
      }
    }
  });

  [delFee, disc].forEach(function (el) { if (el) el.addEventListener('input', recalc); });
  if (paid) paid.addEventListener('input', recalc);
  if (pickup) {
    pickup.addEventListener('change', function () {
      recalc();
      if (deliveryAddr) {
        var show = pickup.value === 'delivery';
        deliveryAddr.disabled = !show;
        if (deliveryLabel) deliveryLabel.textContent = show ? 'Delivery address *' : 'Delivery address';
      }
    });
    pickup.dispatchEvent(new Event('change'));
  }

  var customerSelect = document.getElementById('customer-select');
  var newCustomerFields = document.getElementById('new-customer-fields');
  function toggleCustomerFields() {
    if (newCustomerFields) newCustomerFields.style.display = customerSelect && customerSelect.value === '0' ? '' : 'none';
  }
  if (customerSelect) { customerSelect.addEventListener('change', toggleCustomerFields); toggleCustomerFields(); }

  recalc();
});
