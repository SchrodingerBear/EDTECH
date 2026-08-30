<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — services & pricing.
 */
$pageTitle = 'Services & Pricing';
$pageSub = 'Manage your laundry service price list';
$active = 'Services & Pricing';

require_owner();
require_page('services');

$c = crud();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  if (in_array($action, ['create', 'update'], true)) {
    $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
    $data = [
      'name' => trim($_POST['name'] ?? ''),
      'unit' => trim($_POST['unit'] ?? 'kg') ?: 'kg',
      'price' => (float) ($_POST['price'] ?? 0),
      'description' => trim($_POST['description'] ?? '') ?: null,
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($data['name'] === '' || $data['price'] < 0) {
      flash('danger', 'Service name and a valid price are required.');
    } else {
      if ($id > 0) {
        $c->update('services', $data, ['id' => $id]);
        audit('service.update', 'services', 'service', $id);
        flash('success', 'Service updated.');
      } else {
        $id = $c->insert('services', $data);
        audit('service.create', 'services', 'service', $id);
        flash('success', 'Service added.');
      }
    }
    redirect('services');
  }

  if ($action === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    $sv = $c->get('services', $id);
    if ($sv) {
      $c->update('services', ['is_active' => $sv['is_active'] ? 0 : 1], ['id' => $id]);
      flash('success', 'Service updated.');
    }
    redirect('services');
  }

  if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $count = $c->count('order_items', ['service_id' => $id]);
    if ($count > 0) {
      flash('danger', 'This service is used by ' . $count . ' order item(s) and cannot be deleted.');
    } else {
      $c->delete('services', ['id' => $id]);
      audit('service.delete', 'services', 'service', $id);
      flash('success', 'Service deleted.');
    }
    redirect('services');
  }
}

$services = $c->select('services', '*', [], 'ORDER BY is_active DESC, name');

$edit = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
  $edit = $c->get('services', (int) $_GET['edit']);
}

require_once __DIR__ . '/layout/header.php';
?>

<?php if ($edit || (isset($_GET['action']) && $_GET['action'] === 'new')): $isEdit = (bool) $edit; $f = $edit ?? []; ?>
  <div class="ia-card">
    <div class="card-head"><h3><?= $isEdit ? 'Edit service' : 'Add service' ?></h3><a class="back-link" href="services">← Back</a></div>
    <div class="card-body card-body-px">
      <form method="post" class="row g-3">
        <input type="hidden" name="form" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><?php endif; ?>
        <div class="col-md-4"><label class="form-label">Service name</label><input class="form-control" name="name" required value="<?= h($f['name'] ?? '') ?>"></div>
        <div class="col-md-3">
          <label class="form-label">Billing unit</label>
          <select class="form-select" name="unit">
            <?php foreach (['kg' => 'Per kilogram (kg)', 'piece' => 'Per piece', 'pair' => 'Per pair', 'set' => 'Per set', 'item' => 'Per item'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($f['unit'] ?? 'kg') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2"><label class="form-label">Price (₱)</label><input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= h($f['price'] ?? '') ?>" required></div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($f['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active (shown online)</label>
          </div>
        </div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= h($f['description'] ?? '') ?></textarea></div>
        <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save</button></div>
      </form>
    </div>
  </div>
<?php require __DIR__ . '/layout/footer.php'; return; endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-8"><h5 class="mb-0 mt-1">Price list (<?= count($services) ?>)</h5></div>
  <div class="col-md-4 text-md-end"><a class="btn btn-grad" href="services?action=new"><?= ia_icon('plus', 15) ?> Add service</a></div>
</div>

<div class="row g-4">
  <?php foreach ($services as $sv): ?>
    <div class="col-md-6 col-xl-4">
      <div class="ia-card h-100">
        <div class="card-body card-body-px">
          <div class="d-flex justify-content-between align-items-start">
            <span class="stat-icon mb-2"><?= ia_icon($sv['icon'] ?? 'shirt') ?></span>
            <span class="badge <?= $sv['is_active'] ? 'badge-live' : 'badge-off' ?>"><?= $sv['is_active'] ? 'active' : 'inactive' ?></span>
          </div>
          <h5 class="mb-1"><?= h($sv['name']) ?></h5>
          <div class="ia-micro text-ia-muted mb-2">per <?= h($sv['unit']) ?></div>
          <div class="fs-3 fw-bold text-ia-primary mb-2"><?= peso($sv['price']) ?></div>
          <p class="ia-micro mb-3"><?= h($sv['description'] ?? '') ?></p>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-ia" href="services?edit=<?= (int) $sv['id'] ?>"><?= ia_icon('edit', 14) ?> Edit</a>
            <form method="post" class="d-inline">
              <input type="hidden" name="form" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $sv['id'] ?>">
              <button class="btn btn-sm btn-outline-ia" type="submit"><?= $sv['is_active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
            <?php $usage = $c->count('order_items', ['service_id' => $sv['id']]); ?>
            <?php if ($usage === 0): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this service?');">
                <input type="hidden" name="form" value="delete">
                <input type="hidden" name="id" value="<?= (int) $sv['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"><?= ia_icon('trash', 14) ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$services): ?>
    <div class="col-12"><div class="empty-state"><h4>No services yet</h4><p>Add your first laundry service to build your price list.</p></div></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
