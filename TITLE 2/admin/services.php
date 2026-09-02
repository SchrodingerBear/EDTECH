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

// Ensure only one service exists - the 30/kg laundry service
$services = $c->select('services', '*', [], 'ORDER BY is_active DESC, name');

// If no services exist, create the default 30/kg service
if (empty($services)) {
    try {
        $c->insert('services', [
            'name' => 'Laundry Service',
            'unit' => 'kg',
            'price' => 30.00,
            'icon' => 'droplets',
            'description' => 'Professional laundry service at 30 per kilogram',
            'is_active' => 1
        ]);
        $services = $c->select('services', '*', [], 'ORDER BY is_active DESC, name');
    } catch (Throwable $e) {
        // If creation fails, continue with empty services
    }
}

// If multiple services exist, delete all except the first one (should be the 30/kg service)
if (count($services) > 1) {
    try {
        $firstService = $services[0];
        foreach ($services as $index => $service) {
            if ($index > 0) { // Keep only the first service
                $c->delete('services', ['id' => $service['id']]);
            }
        }
        $services = $c->select('services', '*', [], 'ORDER BY is_active DESC, name');
    } catch (Throwable $e) {
        // If deletion fails, continue with current services
    }
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-md-12"><h5 class="mb-0 mt-1">Price list (<?= count($services) ?>)</h5></div>
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
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$services): ?>
    <div class="col-12"><div class="empty-state"><h4>No services yet</h4><p>No laundry services available.</p></div></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
