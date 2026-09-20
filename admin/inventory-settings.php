<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — inventory configuration (owner only).
 * Configure how much of each consumable is used per kg of laundry per
 * service (smart inventory), set minimums, and manage items.
 */
$pageTitle = 'Inventory Config';
$pageSub = 'Configure per-kg consumption and inventory settings';
$active = 'Inventory Config';

require_owner();
require_page('inventory_settings');

$c = crud();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  /* ---- Save usage rates per service per item ---- */
  if ($action === 'usage') {
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    if ($serviceId <= 0) {
      flash('danger', 'Select a service first.');
      redirect('admin/inventory-settings');
    }
    $rates = $_POST['rates'] ?? []; // item_id => usage_per_kg
    $types = $_POST['types'] ?? []; // item_id => consumption_type
    foreach ($rates as $itemId => $val) {
      $itemId = (int) $itemId;
      $val = (float) $val;
      $type = $types[$itemId] ?? 'per_kg';
      if ($itemId <= 0) continue;
      $exists = $c->get('inventory_usage', ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
      if ($val <= 0) {
        if ($exists) $c->delete('inventory_usage', ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
        continue;
      }
      $data = ['usage_per_kg' => $val, 'consumption_type' => $type];
      if ($exists) {
        $c->update('inventory_usage', $data, ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
      } else {
        $c->insert('inventory_usage', $data + ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
      }
    }
    audit('inventory.usage', 'inventory', 'service', $serviceId);
    flash('success', 'Usage rates saved.');
    redirect('admin/inventory-settings?service=' . $serviceId);
  }

  /* ---- Save minimums (bulk) ---- */
  if ($action === 'minimums') {
    $mins = $_POST['min'] ?? [];
    foreach ($mins as $itemId => $val) {
      $c->update('inventory_items', ['minimum_stock' => (float) $val], ['id' => (int) $itemId]);
    }
    audit('inventory.minimums', 'inventory');
    flash('success', 'Minimum stock levels saved.');
    redirect('admin/inventory-settings');
  }
}

$services = $c->select('services', '*', [], 'ORDER BY name');
$items = $c->select('inventory_items', '*', [], 'ORDER BY category, name');

$selectedService = (int) ($_GET['service'] ?? 0);
if ($selectedService <= 0 && $services) $selectedService = (int) $services[0]['id'];

$usageMap = [];
$typeMap = [];
if ($selectedService > 0) {
  $rows = $c->select('inventory_usage', '*', ['service_id' => $selectedService]);
  foreach ($rows as $r) {
    $usageMap[(int) $r['inventory_item_id']] = (float) $r['usage_per_kg'];
    $typeMap[(int) $r['inventory_item_id']] = $r['consumption_type'] ?? 'per_kg';
  }
}

// Forecast: average consumption per completed order (last 30 days) → projected run-out
$forecast = [];
try {
  $forecastRows = $c->raw(
    "SELECT ii.id, ii.name, ii.current_stock, ii.minimum_stock, ii.unit,
            iu.usage_per_kg, iu.consumption_type,
            (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
             JOIN laundry_orders o ON o.id = oi.order_id
             WHERE o.status = 'completed' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND oi.service_id = :svc) AS kg_30d
      FROM inventory_items ii
      LEFT JOIN inventory_usage iu ON iu.inventory_item_id = ii.id AND iu.service_id = :svc2
      WHERE ii.is_active = 1"
  , ['svc' => $selectedService, 'svc2' => $selectedService])->fetchAll();
} catch (Throwable $e) {
  $forecastRows = [];
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-4 mb-4">
  <div class="col-xl-12">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Smart consumption per kg</h3></div>
      <div class="card-body card-body-px">
        <p class="ia-micro mb-3">
          Set how much of each consumable is used per kilogram of laundry for the selected service.
          When an order is marked <strong>Claimed</strong>, stock is deducted automatically using these rates.
        </p>
        <form method="get" class="mb-3">
          <label class="form-label">Select service</label>
          <select class="form-select" name="service" onchange="this.form.submit()">
            <?php foreach ($services as $s): ?>
              <option value="<?= (int) $s['id'] ?>" <?= $selectedService === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['name'] . ' — ' . peso($s['price']) . '/' . $s['unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>

        <form method="post">
          <input type="hidden" name="form" value="usage">
          <input type="hidden" name="service_id" value="<?= $selectedService ?>">
          <div class="table-responsive">
            <table class="table table-ia">
              <thead><tr><th>Consumable</th><th>Unit</th><th style="width:160px">Type</th><th class="text-end" style="width:180px">Usage</th><th class="text-end">Current stock</th></tr></thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><?= h($it['name']) ?><?php if (!$it['is_active']): ?> <span class="badge badge-off">inactive</span><?php endif; ?></td>
                    <td class="text-ia-muted"><?= h($it['unit']) ?></td>
                    <td>
                      <select class="form-select form-select-sm" name="types[<?= (int) $it['id'] ?>]" onchange="this.form.submit()">
                        <option value="per_kg" <?= ($typeMap[(int) $it['id']] ?? 'per_kg') === 'per_kg' ? 'selected' : '' ?>>Per kg</option>
                        <option value="per_order" <?= ($typeMap[(int) $it['id']] ?? '') === 'per_order' ? 'selected' : '' ?>>Per order</option>
                      </select>
                    </td>
                    <td>
                      <div class="input-group">
                        <input class="form-control" type="number" step="0.01" min="0" name="rates[<?= (int) $it['id'] ?>]" value="<?= h($usageMap[(int) $it['id']] ?? '') ?>" placeholder="0">
                        <span class="input-group-text" id="unit-<?= (int) $it['id'] ?>">/kg</span>
                      </div>
                    </td>
                    <td class="text-end fw-semibold"><?= h($it['current_stock']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save usage rates</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
  document.querySelectorAll('select[name^="types["]').forEach(function(sel) {
    sel.addEventListener('change', function() {
      var itemId = this.name.match(/\[(\d+)\]/)[1];
      var unitEl = document.getElementById('unit-' + itemId);
      if (unitEl) {
        unitEl.textContent = this.value === 'per_order' ? '/order' : '/kg';
      }
    });
  });
</script>
<?php require __DIR__ . '/layout/footer.php'; ?>
