<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — dashboard overview.
 */
$pageTitle = 'Dashboard';
$pageSub = 'Laundry business at a glance';
$active = 'Dashboard';
$bodyClass = 'page-dashboard';

$c = crud();

// Order status counts
$statusCounts = [];
foreach (order_statuses() as $slug => $label) {
  $statusCounts[$slug] = $c->count('laundry_orders', ['status' => $slug]);
}

// Revenue totals (completed + paid)
$monthStart = date('Y-m-01 00:00:00');
$revenueMonth = $c->raw(
  "SELECT COALESCE(SUM(total),0) AS total FROM laundry_orders
   WHERE status = 'completed' AND created_at >= :ms", ['ms' => $monthStart]
)->fetch();
$revenueAll = $c->raw(
  "SELECT COALESCE(SUM(total),0) AS total FROM laundry_orders WHERE status = 'completed'"
)->fetch();

// Outstanding (unpaid/partial balances)
$outstanding = $c->raw(
  "SELECT COALESCE(SUM(total - amount_paid),0) AS bal FROM laundry_orders
   WHERE status != 'cancelled' AND total > amount_paid"
)->fetch();

// Customer + employee counts
$customerCount = $c->count('customers');
$employeeCount = $c->count('employees', ['is_active' => 1]);
$serviceCount = $c->count('services', ['is_active' => 1]);

// Today's orders
$todayOrders = $c->raw(
  "SELECT o.*, CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
          (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items
   FROM laundry_orders o
   JOIN customers cu ON cu.id = o.customer_id
   WHERE o.created_at >= :ds
   ORDER BY o.created_at DESC LIMIT 8",
  ['ds' => date('Y-m-d 00:00:00')]
)->fetchAll();

// Recent orders (all)
$recentOrders = $c->raw(
  "SELECT o.*, CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
          (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items
   FROM laundry_orders o
   JOIN customers cu ON cu.id = o.customer_id
   ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

// Recent customers
$recentCustomers = $c->raw(
  "SELECT * FROM customers ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-4 mb-4">
  <?php
  $statCards = [
    ['Today\'s Orders', (int) $c->count('laundry_orders', ['created_at' => ['>=', date('Y-m-d 00:00:00')]]), 'clipboard', 'Orders received today', 'orders'],
    ['In Progress', $statusCounts['in_progress'], 'wrench', 'Currently being processed', 'orders?status=in_progress'],
    ['Ready for Pickup', $statusCounts['ready'], 'shirt', 'Awaiting customer pickup', 'orders?status=ready'],
    ['Revenue This Month', peso($revenueMonth['total']), 'chart', 'Completed orders', 'reports'],
  ];
  foreach ($statCards as [$label, $num, $icon, $note, $link]): ?>
    <div class="col-6 col-xl-3">
      <a href="<?= h($link) ?>" class="stat-card-link d-block text-decoration-none text-reset">
        <div class="ia-stat">
          <div class="stat-icon"><?= ia_icon($icon) ?></div>
          <div class="stat-num"><?= h($num) ?></div>
          <div class="stat-label"><?= h($label) ?></div>
          <div class="ia-micro mt-1"><?= h($note) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <div class="ia-card">
      <div class="card-head">
        <h3>Today's orders</h3>
        <a class="back-link" href="orders">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>When</th></tr>
          </thead>
          <tbody>
            <?php foreach ($todayOrders as $o): ?>
              <tr>
                <td class="fw-semibold"><a class="back-link" href="orders?view=<?= (int) $o['id'] ?>"><?= h($o['order_no']) ?></a></td>
                <td><?= h($o['customer_name']) ?></td>
                <td class="text-ia-muted"><?= (int) $o['items'] ?></td>
                <td class="fw-semibold"><?= peso($o['total']) ?></td>
                <td><span class="badge <?= status_badge($o['status']) ?>"><?= h(order_statuses()[$o['status']] ?? $o['status']) ?></span></td>
                <td class="text-ia-muted text-nowrap"><?= h(date('g:i A', strtotime($o['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$todayOrders): ?>
              <tr><td colspan="6"><div class="empty-state"><h4>No orders yet today</h4><p>Create your first order to get started.</p></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card h-100">
      <div class="card-head"><h3>Quick actions</h3></div>
      <div class="card-body d-grid gap-2 card-body-px">
        <a class="btn btn-grad" href="orders?action=new">+ Create new order</a>
        <a class="btn btn-outline-ia" href="customers?action=new">+ Add customer</a>
        <a class="btn btn-outline-ia" href="orders?status=pending">View pending orders</a>
        <a class="btn btn-outline-ia" href="reports">Open reports</a>
        <?php if (current_role() === 'owner'): ?>
          <a class="btn btn-outline-ia" href="settings">Business settings</a>
        <?php endif; ?>
        <a class="btn btn-outline-ia" href="<?= url('/') ?>" target="_blank">View public site</a>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <div class="ia-card">
      <div class="card-head">
        <h3>Recent orders</h3>
        <a class="back-link" href="orders">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-ia" data-force-datatable>
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Total</th><th>Pay</th><th>Status</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $o): ?>
              <tr>
                <td class="fw-semibold"><a class="back-link" href="orders?view=<?= (int) $o['id'] ?>"><?= h($o['order_no']) ?></a></td>
                <td><?= h($o['customer_name']) ?></td>
                <td class="fw-semibold"><?= peso($o['total']) ?></td>
                <td><span class="badge <?= $o['payment_status'] === 'paid' ? 'badge-success' : ($o['payment_status'] === 'partial' ? 'badge-warn' : 'badge-off') ?>"><?= h(ucfirst($o['payment_status'])) ?></span></td>
                <td><span class="badge <?= status_badge($o['status']) ?>"><?= h(order_statuses()[$o['status']] ?? $o['status']) ?></span></td>
                <td class="text-ia-muted text-nowrap"><?= h(date('M j, Y', strtotime($o['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?>
              <tr><td colspan="6"><div class="empty-state"><h4>No orders yet</h4></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Pipeline</h3></div>
      <div class="card-body card-body-px">
        <?php
        $total = array_sum($statusCounts);
        $pipeline = [
          ['Pending', 'badge-warn', $statusCounts['pending']],
          ['In Progress', 'badge-info', $statusCounts['in_progress']],
          ['Ready', 'badge-live', $statusCounts['ready']],
          ['Completed', 'badge-success', $statusCounts['completed']],
        ];
        foreach ($pipeline as [$label, $badge, $num]): $pct = $total ? round(($num / $total) * 100) : 0; ?>
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-ia-muted"><?= h($label) ?></span>
            <span class="badge <?= $badge ?>"><?= (int) $num ?></span>
          </div>
          <div class="progress mb-3" style="height:6px">
            <div class="progress-bar" style="width:<?= $pct ?>%"></div>
          </div>
        <?php endforeach; ?>
        <div class="ia-micro mt-3">Outstanding balance: <strong><?= peso($outstanding['bal']) ?></strong></div>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Recent customers</h3><a class="back-link" href="customers">All</a></div>
      <div class="card-body card-body-px">
        <?php foreach ($recentCustomers as $cm): ?>
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="ia-avatar"><?= h(strtoupper(mb_substr($cm['first_name'][0] ?? '', 0, 1) . mb_substr($cm['last_name'][0] ?? '', 0, 1))) ?></div>
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= h($cm['first_name'] . ' ' . $cm['last_name']) ?></div>
              <div class="ia-micro text-ia-muted"><?= h($cm['phone']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$recentCustomers): ?><div class="empty-state"><p>No customers yet.</p></div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
