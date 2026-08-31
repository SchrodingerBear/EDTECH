<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — POS-style dashboard overview.
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
  "SELECT o.*, cu.first_name, cu.last_name, cu.phone AS customer_phone,
          CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
          (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items
   FROM laundry_orders o
   JOIN customers cu ON cu.id = o.customer_id
   WHERE o.created_at >= :ds
   ORDER BY o.created_at DESC LIMIT 12",
  ['ds' => date('Y-m-d 00:00:00')]
)->fetchAll();

// Receipt dataset for the popup modal: full items per today's order
$receipts = [];
if ($todayOrders) {
  $ids = array_column($todayOrders, 'id');
  $in = implode(',', array_map('intval', $ids));
  $allItems = $c->raw(
    "SELECT oi.*, s.name AS service_name, s.unit
     FROM order_items oi JOIN services s ON s.id = oi.service_id
     WHERE oi.order_id IN ($in) ORDER BY oi.id"
  )->fetchAll();
  $itemsByOrder = [];
  foreach ($allItems as $it) $itemsByOrder[$it['order_id']][] = $it;
  foreach ($todayOrders as $o) {
    $receipts[(int) $o['id']] = [
      'id' => (int) $o['id'],
      'order_no' => $o['order_no'],
      'created_at' => $o['created_at'],
      'customer' => $o['customer_name'],
      'phone' => $o['customer_phone'],
      'status' => order_statuses()[$o['status']] ?? $o['status'],
      'status_slug' => $o['status'],
      'payment' => ucfirst($o['payment_status']),
      'subtotal' => (float) $o['subtotal'],
      'delivery_fee' => (float) $o['delivery_fee'],
      'discount' => (float) $o['discount'],
      'total' => (float) $o['total'],
      'amount_paid' => (float) $o['amount_paid'],
      'balance' => round((float) $o['total'] - (float) $o['amount_paid'], 2),
      'items' => array_map(function ($it) {
        return [
          'name' => $it['service_name'],
          'qty' => rtrim(rtrim(number_format($it['quantity'], 2, '.', ''), '0'), '.'),
          'unit' => $it['unit'],
          'price' => (float) $it['unit_price'],
          'line' => (float) $it['line_total'],
        ];
      }, $itemsByOrder[(int) $o['id']] ?? []),
    ];
  }
}

// Recent customers (used in the collapsible "more details" block)
$recentCustomers = $c->raw(
  "SELECT * FROM customers ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

// Inventory low-stock count
$lowStockCount = 0;
try {
  $lowStockCount = (int) $c->raw(
    "SELECT COUNT(*) FROM inventory_items WHERE is_active = 1 AND current_stock <= minimum_stock"
  )->fetchColumn();
} catch (Throwable $e) {
  $lowStockCount = 0;
}

require_once __DIR__ . '/layout/header.php';
?>

<?php $role = current_role(); ?>

<!-- ============================ STAT CARDS (equal height) ============================ -->
<div class="pos-stats row g-2 g-md-3 mb-3">
  <?php
  $todayCount = (int) $c->count('laundry_orders', ['created_at' => ['>=', date('Y-m-d 00:00:00')]]);
  $statCards = [
    ['Today&#39;s Orders', $todayCount, 'clipboard', 'orders', 'stat-primary'],
    ['In Queue', ($statusCounts['pending'] ?? 0) + ($statusCounts['washing'] ?? 0) + ($statusCounts['drying'] ?? 0), 'wrench', 'orders?status=pending', 'stat-warn'],
    ['Ready', $statusCounts['ready'] ?? 0, 'shirt', 'orders?status=ready', 'stat-live'],
    ['Low Stock', $lowStockCount, 'layers', 'inventory', 'stat-danger'],
  ];
  foreach ($statCards as [$label, $num, $icon, $link, $mod]): ?>
    <div class="col-6 col-xl-3">
      <a href="<?= h($link) ?>" class="pos-stat h-100 <?= $mod ?> text-decoration-none">
        <span class="pos-stat-ic"><?= ia_icon($icon, 20) ?></span>
        <span class="pos-stat-num"><?= h($num) ?></span>
        <span class="pos-stat-label"><?= $label ?></span>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<!-- ============================ POS ACTION GRID (touch buttons) ============================ -->
<div class="pos-actions mb-3">
  <div class="pos-grid">
    <a href="orders?action=new" class="pos-btn pos-btn-hero">
      <span class="pos-btn-ic"><?= ia_icon('plus', 26) ?></span>
      <span class="pos-btn-txt">New<br>Order</span>
    </a>
    <a href="orders" class="pos-btn">
      <span class="pos-btn-ic"><?= ia_icon('clipboard', 22) ?></span>
      <span class="pos-btn-txt">Orders</span>
    </a>
    <a href="customers" class="pos-btn">
      <span class="pos-btn-ic"><?= ia_icon('users', 22) ?></span>
      <span class="pos-btn-txt">Customers</span>
    </a>
    <a href="inventory" class="pos-btn">
      <span class="pos-btn-ic"><?= ia_icon('layers', 22) ?></span>
      <span class="pos-btn-txt">Inventory</span>
    </a>
    <a href="orders?status=ready" class="pos-btn">
      <span class="pos-btn-ic"><?= ia_icon('shirt', 22) ?></span>
      <span class="pos-btn-txt">Ready to<br>Pickup</span>
    </a>
    <a href="customers?action=new" class="pos-btn">
      <span class="pos-btn-ic"><?= ia_icon('user', 22) ?></span>
      <span class="pos-btn-txt">New<br>Customer</span>
    </a>
    <?php if ($role === 'owner'): ?>
      <a href="reports" class="pos-btn">
        <span class="pos-btn-ic"><?= ia_icon('chart', 22) ?></span>
        <span class="pos-btn-txt">Reports</span>
      </a>
      <a href="system-settings" class="pos-btn">
        <span class="pos-btn-ic"><?= ia_icon('settings', 22) ?></span>
        <span class="pos-btn-txt">Settings</span>
      </a>
    <?php else: ?>
      <a href="orders?status=pending" class="pos-btn">
        <span class="pos-btn-ic"><?= ia_icon('wrench', 22) ?></span>
        <span class="pos-btn-txt">Pending</span>
      </a>
      <a href="orders?status=completed" class="pos-btn">
        <span class="pos-btn-ic"><?= ia_icon('shield', 22) ?></span>
        <span class="pos-btn-txt">Claimed</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- ============================ TODAY'S ORDERS (tap row -> receipt) ============================ -->
<div class="ia-card pos-recent">
  <div class="card-head">
    <h3>Today's orders <span class="ia-micro text-ia-muted fw-normal">· tap a row for receipt</span></h3>
    <a class="back-link" href="orders">View all</a>
  </div>
  <div class="pos-receipt-list">
    <?php foreach ($todayOrders as $o): ?>
      <button type="button" class="pos-row" data-receipt="<?= (int) $o['id'] ?>">
        <span class="pos-row-id">
          <span class="pos-row-no"><?= h($o['order_no']) ?></span>
          <span class="pos-row-sub"><?= h(date('g:i A', strtotime($o['created_at']))) ?></span>
        </span>
        <span class="pos-row-date">
          <span class="pos-row-time"><?= h(date('M j', strtotime($o['created_at']))) ?></span>
          <span class="pos-row-chev"><?= ia_icon('eye', 16) ?></span>
        </span>
      </button>
    <?php endforeach; ?>
    <?php if (!$todayOrders): ?>
      <div class="empty-state p-4 text-center">
        <h4>No orders yet today</h4>
        <p class="mb-0">Tap <strong>New Order</strong> to start.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============================ MORE DETAILS (collapsible, no scroll) ============================ -->
<details class="pos-more mb-3">
  <summary class="pos-more-sum">
    <span>More details</span>
    <span class="ia-micro text-ia-muted">revenue · pipeline · customers</span>
    <span class="pos-more-chev"><?= ia_icon('arrow-left', 15) ?></span>
  </summary>
  <div class="row g-3 mt-1">
    <div class="col-md-4">
      <div class="ia-card h-100">
        <div class="card-head"><h3>Revenue</h3></div>
        <div class="card-body card-body-px">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="ia-micro text-ia-muted">This month</span>
            <strong><?= peso($revenueMonth['total']) ?></strong>
          </div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="ia-micro text-ia-muted">All time</span>
            <strong><?= peso($revenueAll['total']) ?></strong>
          </div>
          <div class="d-flex align-items-center justify-content-between">
            <span class="ia-micro text-ia-muted">Outstanding</span>
            <strong class="text-ia-warning"><?= peso($outstanding['bal']) ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="ia-card h-100">
        <div class="card-head"><h3>Pipeline</h3></div>
        <div class="card-body card-body-px">
          <?php $total = array_sum($statusCounts);
          $pipeline = [
            ['Pending', 'badge-warn', $statusCounts['pending'] ?? 0],
            ['Washing', 'badge-info', $statusCounts['washing'] ?? 0],
            ['Drying', 'badge-info', $statusCounts['drying'] ?? 0],
            ['Ready', 'badge-live', $statusCounts['ready'] ?? 0],
            ['Claimed', 'badge-success', $statusCounts['completed'] ?? 0],
          ];
          foreach ($pipeline as [$label, $badge, $num]): $pct = $total ? round(($num / $total) * 100) : 0; ?>
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="small text-ia-muted"><?= h($label) ?></span>
              <span class="badge <?= $badge ?>"><?= (int) $num ?></span>
            </div>
            <div class="progress mb-2" style="height:6px">
              <div class="progress-bar" style="width:<?= $pct ?>%"></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="ia-card h-100">
        <div class="card-head"><h3>Recent customers</h3><a class="back-link" href="customers">All</a></div>
        <div class="card-body card-body-px">
          <?php foreach ($recentCustomers as $cm): ?>
            <a class="d-flex align-items-center gap-3 mb-3 text-reset text-decoration-none" href="customers?view=<?= (int) $cm['id'] ?>">
              <div class="ia-avatar"><?= h(strtoupper(mb_substr($cm['first_name'][0] ?? '', 0, 1) . mb_substr($cm['last_name'][0] ?? '', 0, 1))) ?></div>
              <div class="flex-grow-1">
                <div class="fw-semibold"><?= h($cm['first_name'] . ' ' . $cm['last_name']) ?></div>
                <div class="ia-micro text-ia-muted"><?= h($cm['phone']) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$recentCustomers): ?><div class="empty-state"><p>No customers yet.</p></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</details>

<!-- ============================ RECEIPT MODAL ============================ -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content pos-receipt-modal">
      <div class="modal-header">
        <h5 class="modal-title">Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="receipt-slot"></div>
      </div>
      <div class="modal-footer">
        <a href="orders?view=" class="btn btn-outline-ia" id="receipt-open">Open order</a>
        <button type="button" class="btn btn-grad" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="receipt-data"><?= json_enc($receipts) ?></script>

<?php require __DIR__ . '/layout/footer.php'; ?>
