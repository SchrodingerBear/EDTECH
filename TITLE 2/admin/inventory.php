<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — inventory management.
 * Both staff and owner can view stock, record incoming supplies, and
 * manually adjust quantities. Owner additionally configures minimums.
 */
$pageTitle = 'Inventory';
$pageSub = 'Track consumables and manage stock levels';
$active = 'Inventory';

if (current_role() === 'owner') require_page('inventory'); else require_role('owner', 'staff');

$c = crud();
$role = current_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  /* ---- Create / update inventory item (owner only) ---- */
  if (in_array($action, ['create', 'update'], true)) {
    require_owner();
    require_page('inventory');
    $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
    $data = [
      'name' => trim($_POST['name'] ?? ''),
      'category' => trim($_POST['category'] ?? 'other') ?: 'other',
      'unit' => trim($_POST['unit'] ?? 'ml') ?: 'ml',
      'current_stock' => (float) ($_POST['current_stock'] ?? 0),
      'minimum_stock' => (float) ($_POST['minimum_stock'] ?? 0),
      'cost_per_unit' => (float) ($_POST['cost_per_unit'] ?? 0),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($data['name'] === '') {
      flash('danger', 'Item name is required.');
    } elseif ($id > 0) {
      $c->update('inventory_items', $data, ['id' => $id]);
      audit('inventory.update', 'inventory', 'inventory_item', $id);
      flash('success', 'Inventory item updated.');
    } else {
      $id = $c->insert('inventory_items', $data);
      audit('inventory.create', 'inventory', 'inventory_item', $id);
      flash('success', 'Inventory item added.');
    }
    redirect('inventory');
  }

  /* ---- Record received supply (in) / adjustment (adjust) / manual (any) ---- */
  if (in_array($action, ['receive', 'adjust'], true)) {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $item = $c->get('inventory_items', $itemId);
    if (!$item) {
      flash('danger', 'Inventory item not found.');
      redirect('inventory');
    }
    $qty = (float) ($_POST['quantity'] ?? 0);
    if ($action === 'receive') {
      // stock in
      if ($qty < 0) {
        flash('danger', 'Received quantity cannot be negative.');
        redirect('inventory');
      }
      $newStock = round((float) $item['current_stock'] + $qty, 2);
      $c->update('inventory_items', ['current_stock' => $newStock], ['id' => $itemId]);
      if ($qty > 0) {
        $c->insert('inventory_movements', [
          'inventory_item_id' => $itemId,
          'type' => 'in',
          'quantity' => $qty,
          'reference' => trim($_POST['reference'] ?? '') ?: null,
          'notes' => trim($_POST['notes'] ?? '') ?: null,
          'created_by' => current_user()['id'] ?? null,
        ]);
      }
      audit('inventory.receive', 'inventory', 'inventory_item', $itemId);
      flash('success', 'Stock received for ' . $item['name'] . '.');
    } else {
      // manual adjust (set to exact value)
      if ($qty < 0) {
        flash('danger', 'Stock cannot be negative.');
        redirect('inventory');
      }
      $delta = round($qty - (float) $item['current_stock'], 2);
      $c->update('inventory_items', ['current_stock' => $qty], ['id' => $itemId]);
      $c->insert('inventory_movements', [
        'inventory_item_id' => $itemId,
        'type' => 'adjust',
        'quantity' => $delta,
        'reference' => trim($_POST['reference'] ?? '') ?: null,
        'notes' => trim($_POST['notes'] ?? '') ?: null,
        'created_by' => current_user()['id'] ?? null,
      ]);
      audit('inventory.adjust', 'inventory', 'inventory_item', $itemId);
      flash('success', 'Stock adjusted for ' . $item['name'] . '.');
    }
    redirect('inventory');
  }

  /* ---- Toggle active state (owner) ---- */
  if ($action === 'toggle') {
    require_owner();
    $id = (int) ($_POST['id'] ?? 0);
    $item = $c->get('inventory_items', $id);
    if ($item) {
      $c->update('inventory_items', ['is_active' => $item['is_active'] ? 0 : 1], ['id' => $id]);
      flash('success', 'Inventory item updated.');
    }
    redirect('inventory');
  }
}

/* ------------------------------- data --------------------------------- */
$categories = ['detergent' => 'Detergent', 'softener' => 'Softener', 'bleach' => 'Bleach', 'packaging' => 'Packaging', 'other' => 'Other'];

$items = $c->select('inventory_items', '*', [], 'ORDER BY is_active DESC, category, name');

// low-stock list
$lowStock = array_values(array_filter($items, fn($i) => (float) $i['current_stock'] <= (float) $i['minimum_stock']));

// recent movements
$movements = $c->raw(
  "SELECT m.*, i.name AS item_name, u.first_name AS actor
   FROM inventory_movements m
   JOIN inventory_items i ON i.id = m.inventory_item_id
   LEFT JOIN users u ON u.id = m.created_by
   ORDER BY m.created_at DESC LIMIT 30"
)->fetchAll();

$edit = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
  $edit = $c->get('inventory_items', (int) $_GET['edit']);
}

$totalValue = 0;
foreach ($items as $i) { $totalValue += (float) $i['current_stock'] * (float) $i['cost_per_unit']; }

require_once __DIR__ . '/layout/header.php';
?>

<?php if (($edit || (isset($_GET['action']) && $_GET['action'] === 'new')) && $role === 'owner'): $isEdit = (bool) $edit; $f = $edit ?? []; ?>
  <div class="ia-card">
    <div class="card-head"><h3><?= $isEdit ? 'Edit inventory item' : 'Add inventory item' ?></h3><a class="back-link" href="inventory">← Back</a></div>
    <div class="card-body card-body-px">
      <form method="post" class="row g-3" id="inventory-item-form">
        <input type="hidden" name="form" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><?php endif; ?>
        <div class="col-md-4"><label class="form-label">Item name</label><input class="form-control" name="name" required value="<?= h($f['name'] ?? '') ?>"></div>
        <div class="col-md-3">
          <label class="form-label">Category</label>
          <select class="form-select" name="category">
            <?php foreach ($categories as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($f['category'] ?? 'other') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Unit</label>
          <select class="form-select" name="unit">
            <?php foreach (['ml' => 'ml', 'liters' => 'liters', 'kg' => 'kg', 'grams' => 'grams', 'pieces' => 'pieces'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($f['unit'] ?? 'ml') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($f['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
          </div>
        </div>
        <div class="col-md-3"><label class="form-label">Current stock</label><input class="form-control" type="number" step="0.01" min="0" name="current_stock" value="<?= h($f['current_stock'] ?? 0) ?>"></div>
        <div class="col-md-3"><label class="form-label">Minimum stock (alert level)</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_stock" value="<?= h($f['minimum_stock'] ?? 0) ?>"></div>
        <div class="col-md-3"><label class="form-label">Cost per unit (₱)</label><input class="form-control" type="number" step="0.01" min="0" name="cost_per_unit" value="<?= h($f['cost_per_unit'] ?? 0) ?>"></div>
        <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save</button></div>
      </form>
    </div>
  </div>
<?php require __DIR__ . '/layout/footer.php'; return; endif; ?>

<div class="row g-4 mb-4">
  <div class="col-6 col-xl-3"><div class="ia-stat"><div class="stat-icon"><?= ia_icon('package') ?></div><div class="stat-num"><?= count($items) ?></div><div class="stat-label">Items tracked</div></div></div>
  <div class="col-6 col-xl-3"><div class="ia-stat"><div class="stat-icon"><?= ia_icon('alert-triangle') ?></div><div class="stat-num"><?= count($lowStock) ?></div><div class="stat-label">Low stock alerts</div></div></div>
  <div class="col-6 col-xl-3"><div class="ia-stat"><div class="stat-icon"><?= ia_icon('layers') ?></div><div class="stat-num"><?= peso($totalValue) ?></div><div class="stat-label">Stock value</div></div></div>
  <div class="col-6 col-xl-3"><div class="ia-stat"><div class="stat-icon"><?= ia_icon('clock') ?></div><div class="stat-num"><?= count($movements) ?></div><div class="stat-label">Recent movements</div></div></div>
</div>

<?php if ($lowStock): ?>
  <div class="alert alert-warning border-0 rounded-4 mb-4">
    <strong><?= count($lowStock) ?> item(s) at or below minimum stock:</strong>
    <?= implode(', ', array_map(fn($i) => h($i['name'] . ' (' . $i['current_stock'] . ' ' . $i['unit'] . ')'), $lowStock)) ?>
  </div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <div class="ia-card">
      <div class="card-head">
        <h3>Inventory items (<?= count($items) ?>)</h3>
        <?php if ($role === 'owner'): ?><a class="btn btn-sm btn-grad" href="inventory?action=new"><?= ia_icon('plus', 14) ?> Add item</a><?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-ia" id="inventory-table" data-force-datatable>
          <thead><tr><th>Item</th><th>Category</th><th>Unit</th><th class="text-end">Stock</th><th class="text-end">Min</th><th>Status</th><?php if ($role === 'owner'): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($items as $i): ?>
              <?php $isLow = (float) $i['current_stock'] <= (float) $i['minimum_stock']; ?>
              <tr>
                <td class="fw-semibold"><?= h($i['name']) ?><?php if (!$i['is_active']): ?> <span class="badge badge-off">inactive</span><?php endif; ?></td>
                <td class="text-ia-muted"><?= h($categories[$i['category']] ?? $i['category']) ?></td>
                <td class="text-ia-muted"><?= h($i['unit']) ?></td>
                <td class="text-end fw-semibold <?= $isLow ? 'text-danger' : '' ?>"><?= h((float) $i['current_stock'] > floor($i['current_stock']) ? number_format($i['current_stock'], 2) : (int) $i['current_stock']) ?></td>
                <td class="text-end text-ia-muted"><?= h((float) $i['minimum_stock'] > floor($i['minimum_stock']) ? number_format($i['minimum_stock'], 2) : (int) $i['minimum_stock']) ?></td>
                <td><?= $isLow ? '<span class="badge badge-off">Low</span>' : '<span class="badge badge-live">OK</span>' ?></td>
                <?php if ($role === 'owner'): ?>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-icon" href="inventory?edit=<?= (int) $i['id'] ?>" title="Edit"><?= ia_icon('edit', 15) ?></a>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7"><div class="empty-state"><h4>No inventory items</h4><p><?= $role === 'owner' ? 'Add your first item to start tracking.' : 'Please check back soon.' ?></p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Record delivery / adjust stock</h3></div>
      <div class="card-body card-body-px">
        <p class="ia-micro mb-3">Select an item, then either record a new delivery (stock in) or manually set the stock level.</p>
        <form method="post" class="d-grid gap-3" id="stock-form">
          <input type="hidden" name="form" value="receive">
          <div>
            <label class="form-label">Item</label>
            <select class="form-select" name="item_id" required>
              <option value="">— Choose item —</option>
              <?php foreach ($items as $i): ?>
                <option value="<?= (int) $i['id'] ?>"><?= h($i['name'] . ' — ' . $i['current_stock'] . ' ' . $i['unit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Quantity</label>
              <input class="form-control" type="number" step="0.01" min="0" name="quantity" required>
            </div>
            <div class="col-6">
              <label class="form-label">Action</label>
              <select class="form-select" name="stock_action" id="stock-action">
                <option value="receive">Receive supply (+)</option>
                <option value="adjust">Adjust to exact</option>
              </select>
            </div>
          </div>
          <div>
            <label class="form-label">Reference</label>
            <input class="form-control" name="reference" placeholder="e.g. supplier no. / invoice">
          </div>
          <div>
            <label class="form-label">Notes</label>
            <input class="form-control" name="notes" placeholder="Optional details">
          </div>
          <button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save movement</button>
        </form>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Recent movements</h3></div>
      <div class="card-body card-body-px" style="max-height:360px; overflow:auto;">
        <?php if (!$movements): ?><div class="empty-state"><p>No movements yet.</p></div><?php endif; ?>
        <?php foreach ($movements as $m): ?>
          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="badge <?= $m['type'] === 'in' ? 'badge-live' : ($m['type'] === 'adjust' ? 'badge-draft' : 'badge-off') ?>"><?= strtoupper($m['type']) ?></span>
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= h($m['item_name']) ?></div>
              <div class="ia-micro text-ia-muted">
                <?= $m['type'] === 'in' ? '+' : '' ?><?= h($m['quantity']) ?> · <?= h($m['actor'] ?? '—') ?><?= $m['reference'] ? ' · ' . h($m['reference']) : '' ?>
              </div>
            </div>
            <div class="ia-micro text-ia-muted text-nowrap"><?= h(date('M j g:i A', strtotime($m['created_at']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
  document.getElementById('stock-action').addEventListener('change', function () {
    document.querySelector('#stock-form input[name="form"]').value = this.value;
  });
</script>

<!-- Offline bridge for inventory -->
<script type="module" src="<?= url('assets/js/ui/inventory.offline.js') ?>"></script>
<?php require __DIR__ . '/layout/footer.php'; ?>
