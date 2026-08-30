document.addEventListener('DOMContentLoaded', function () {
  // Mobile menu
  var toggle = document.getElementById('menu-toggle');
  var mobileNav = document.getElementById('mobile-nav');
  if (toggle && mobileNav) {
    toggle.addEventListener('click', function () {
      var open = mobileNav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mobileNav.querySelectorAll('[data-close-menu]').forEach(function (a) {
      a.addEventListener('click', function () {
        mobileNav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Booking form
  var form = document.getElementById('booking-form');
  var itemsBox = document.getElementById('book-items');
  if (!form || !itemsBox) return;

  var services = window.BOOK_SERVICES || [];
  var byId = {};
  services.forEach(function (s) { byId[s.id] = s; });

  function peso(v) {
    v = parseFloat(v) || 0;
    return '\u20B1' + (Number.isInteger(v) ? v.toLocaleString() : v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  }

  function optionHtml(selected) {
    return '<option value="">Select a service</option>' + services.map(function (s) {
      var sel = String(s.id) === String(selected) ? ' selected' : '';
      return '<option value="' + s.id + '" data-price="' + s.price + '"' + sel + '>' + s.name + ' \u2014 ' + s.unit + '</option>';
    }).join('');
  }

  function makeRow() {
    var row = document.createElement('div');
    row.className = 'book-item-row';
    row.innerHTML =
      '<select class="book-service" name="service_id[]">' + optionHtml('') + '</select>' +
      '<input class="book-qty" type="number" name="quantity[]" min="0.01" step="0.01" value="1" placeholder="Qty">' +
      '<button type="button" class="book-remove" title="Remove">\u00d7</button>';
    return row;
  }

  function recalc() {
    var total = 0;
    itemsBox.querySelectorAll('.book-item-row').forEach(function (row) {
      var sel = row.querySelector('.book-service');
      var price = parseFloat(sel.selectedOptions[0].dataset.price) || 0;
      var qty = parseFloat(row.querySelector('.book-qty').value) || 0;
      total += price * qty;
    });
    document.getElementById('book-total').textContent = peso(total);
  }

  itemsBox.querySelectorAll('.book-item-row').forEach(function (row) {
    row.querySelector('.book-service').addEventListener('change', recalc);
    row.querySelector('.book-qty').addEventListener('input', recalc);
  });

  var addBtn = document.getElementById('book-add');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      var row = makeRow();
      itemsBox.appendChild(row);
      row.querySelector('.book-service').addEventListener('change', recalc);
      row.querySelector('.book-qty').addEventListener('input', recalc);
      recalc();
    });
  }

  itemsBox.addEventListener('click', function (e) {
    if (e.target.closest('.book-remove')) {
      if (itemsBox.querySelectorAll('.book-item-row').length > 1) {
        e.target.closest('.book-item-row').remove();
        recalc();
      }
    }
  });

  recalc();
});
