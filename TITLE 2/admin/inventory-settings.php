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
      redirect('inventory-settings');
    }
    $rates = $_POST['rates'] ?? []; // item_id => usage_per_kg
    foreach ($rates as $itemId => $val) {
      $itemId = (int) $itemId;
      $val = (float) $val;
      if ($itemId <= 0) continue;
      $exists = $c->get('inventory_usage', ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
      if ($val <= 0) {
        if ($exists) $c->delete('inventory_usage', ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
        continue;
      }
      if ($exists) {
        $c->update('inventory_usage', ['usage_per_kg' => $val], ['service_id' => $serviceId, 'inventory_item_id' => $itemId]);
      } else {
        $c->insert('inventory_usage', ['service_id' => $serviceId, 'inventory_item_id' => $itemId, 'usage_per_kg' => $val]);
      }
    }
    audit('inventory.usage', 'inventory', 'service', $serviceId);
    flash('success', 'Usage rates saved.');
    redirect('inventory-settings?service=' . $serviceId);
  }

  /* ---- Save minimums (bulk) ---- */
  if ($action === 'minimums') {
    $mins = $_POST['min'] ?? [];
    foreach ($mins as $itemId => $val) {
      $c->update('inventory_items', ['minimum_stock' => (float) $val], ['id' => (int) $itemId]);
    }
    audit('inventory.minimums', 'inventory');
    flash('success', 'Minimum stock levels saved.');
    redirect('inventory-settings');
  }
}

$services = $c->select('services', '*', [], 'ORDER BY name');
$items = $c->select('inventory_items', '*', [], 'ORDER BY category, name');

$selectedService = (int) ($_GET['service'] ?? 0);
if ($selectedService <= 0 && $services) $selectedService = (int) $services[0]['id'];

$usageMap = [];
if ($selectedService > 0) {
  $rows = $c->select('inventory_usage', '*', ['service_id' => $selectedService]);
  foreach ($rows as $r) $usageMap[(int) $r['inventory_item_id']] = (float) $r['usage_per_kg'];
}

// Forecast: average consumption per completed order (last 30 days) → projected run-out
$forecast = [];
try {
  $forecastRows = $c->raw(
    "SELECT ii.id, ii.name, ii.current_stock, ii.minimum_stock, ii.unit,
            iu.usage_per_kg,
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
  <div class="col-xl-8">
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
              <thead><tr><th>Consumable</th><th>Unit</th><th class="text-end" style="width:180px">Usage per kg</th><th class="text-end">Current stock</th></tr></thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><?= h($it['name']) ?><?php if (!$it['is_active']): ?> <span class="badge badge-off">inactive</span><?php endif; ?></td>
                    <td class="text-ia-muted"><?= h($it['unit']) ?></td>
                    <td>
                      <div class="input-group">
                        <input class="form-control" type="number" step="0.01" min="0" name="rates[<?= (int) $it['id'] ?>]" value="<?= h($usageMap[(int) $it['id']] ?? '') ?>" placeholder="0">
                        <span class="input-group-text">/kg</span>
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

  <div class="col-xl-4">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>⚠ Forecast (30-day estimate)</h3></div>
      <div class="card-body card-body-px">
        <p class="ia-micro mb-3">Based on the last 30 days of claimed orders, here's when each item is estimated to run out at the current usage rate.</p>
        <?php if (!$forecastRows): ?><p class="ia-micro text-ia-muted">Select a service to see forecast.</p><?php endif; ?>
        <?php foreach ($forecastRows as $f):
          $kg = (float) ($f['kg_30d'] ?? 0);
          $rate = (float) ($f['usage_per_kg'] ?? 0);
          $stock = (float) $f['current_stock'];
          $usedPerMonth = $kg > 0 ? $rate * $kg : 0;
          if ($usedPerMonth <= 0) continue;
          $daysLeft = round(($stock / $usedPerMonth) * 30);
          $daysLeft = max(0, $daysLeft);
          $barPct = min(100, $stock > 0 ? round(($stock / max($stock, $usedPerMonth)) * 100) : 0);
          $low = $daysLeft <= 7;
          ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span class="small fw-semibold"><?= h($f['name']) ?></span>
              <span class="badge <?= $low ? 'badge-off' : 'badge-live' ?>">~<?= $daysLeft ?> days left</span>
            </div>
            <div class="ia-micro text-ia-muted mb-1"><?= h($stock) ?> <?= h($f['unit']) ?> · ~<?= h(number_format($usedPerMonth)) ?>/mo</div>
            <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= $barPct ?>%"></div></div>
          </div>
        <?php endforeach; ?>
        <?php if (!empty($forecastRows) && !array_filter($forecastRows, fn($f) => (float)($f['kg_30d'] ?? 0) > 0 && (float)($f['usage_per_kg'] ?? 0) > 0)): ?>
          <p class="ia-micro mt-2 text-ia-muted">No forecast data yet for this service. Claim some orders to populate.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
