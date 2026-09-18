<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — reports & analytics.
 */
$pageTitle = 'Reports';
$pageSub = 'Revenue and business analytics';
$active = 'Reports';

require_owner();
require_page('reports');

$c = crud();

$range = $_GET['range'] ?? 'this_month';
$now = new DateTime();
$start = null;
$end = null;

$periodLabel = '';
switch ($range) {
  case 'today':
    $start = $now->format('Y-m-d 00:00:00');
    $end = $now->format('Y-m-d 23:59:59');
    $periodLabel = 'Today';
    break;
  case 'this_week':
    $start = $now->modify('monday this week')->format('Y-m-d 00:00:00');
    $end = date('Y-m-d 23:59:59');
    $periodLabel = 'This Week';
    break;
  case 'this_month':
    $start = date('Y-m-01 00:00:00');
    $end = date('Y-m-d 23:59:59');
    $periodLabel = 'This Month';
    break;
  case 'last_30':
    $start = date('Y-m-d 00:00:00', strtotime('-29 days'));
    $end = date('Y-m-d 23:59:59');
    $periodLabel = 'Last 30 Days';
    break;
  case 'all':
    $start = null;
    $end = null;
    $periodLabel = 'All Time';
    break;
}

$comp = ' WHERE o.deleted_at IS NULL OR 1=1 ';
$frag = " AND o.status='completed' ";
$whereCmd = '1=1';
$params = [];
if ($start !== null) {
  $whereCmd .= ' AND o.created_at >= :start';
  $params['start'] = $start;
}
if ($end !== null) {
  $whereCmd .= ' AND o.created_at <= :end';
  $params['end'] = $end;
}

// summary cards
$summary = $c->raw(
  "SELECT
      COALESCE(SUM(CASE WHEN o.status='completed' THEN o.total END),0) AS revenue,
      COUNT(CASE WHEN o.status != 'cancelled' THEN 1 END) AS order_count,
      COUNT(CASE WHEN o.status='completed' THEN 1 END) AS completed_count,
      COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total - o.amount_paid END),0) AS outstanding,
      AVG(CASE WHEN o.status='completed' THEN o.total END) AS avg_order
   FROM laundry_orders o WHERE $whereCmd", $params
)->fetch();

// revenue by day (last 14 or 30 days)
$days = $range === 'today' ? 7 : 14;
$dailyParams = $params;
$dailyFrom = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) . ' 00:00:00';
if (!$start || $start < $dailyFrom) $startForChart = $dailyFrom; else $startForChart = $start;
$chartParams = $params;
$chartParams['cs'] = $startForChart;
$daily = $c->raw(
  "SELECT DATE(o.created_at) AS d, COALESCE(SUM(o.total),0) AS rev, COUNT(*) AS n
   FROM laundry_orders o
   WHERE o.status='completed' AND o.created_at >= :cs
   GROUP BY DATE(o.created_at) ORDER BY d ASC", ['cs' => $startForChart]
)->fetchAll();

$dailyData = [];
$cursor = new DateTime($startForChart);
$endDate = new DateTime(date('Y-m-d'));
for ($i = 0; $i <= $endDate->diff($cursor)->days; $i++) {
  $key = $cursor->format('Y-m-d');
  $dailyData[] = ['d' => $key, 'rev' => 0, 'n' => 0];
  $cursor->modify('+1 day');
}

// fill from fetched
$byDate = [];
foreach ($daily as $row) $byDate[$row['d']] = $row;
foreach ($dailyData as &$dd) {
  if (isset($byDate[$dd['d']])) { $dd['rev'] = (float) $byDate[$dd['d']]['rev']; $dd['n'] = (int) $byDate[$dd['d']]['n']; }
}
unset($dd);

// revenue by service
$serviceParams = $params;
$byService = $c->raw(
  "SELECT s.name, COALESCE(SUM(oi.line_total),0) AS total
   FROM laundry_orders o
   JOIN order_items oi ON oi.order_id = o.id
   JOIN services s ON s.id = oi.service_id
   WHERE o.status='completed' AND $whereCmd
   GROUP BY s.id, s.name ORDER BY total DESC", $params
)->fetchAll();

// top customers
$topCustomers = $c->raw(
  "SELECT cu.first_name AS name, cu.phone,
          COUNT(o.id) AS n, COALESCE(SUM(o.total),0) AS total
   FROM laundry_orders o JOIN customers cu ON cu.id = o.customer_id
   WHERE o.status='completed' AND $whereCmd
   GROUP BY cu.id, cu.first_name, cu.phone
   ORDER BY total DESC LIMIT 6", $params
)->fetchAll();

// top services count
$byStatus = $c->raw(
  "SELECT status, COUNT(*) AS n FROM laundry_orders o WHERE $whereCmd GROUP BY status", $params
)->fetchAll();

$statusMap = [];
foreach (order_statuses() as $slug => $label) $statusMap[$slug] = 0;
foreach ($byStatus as $r) $statusMap[$r['status']] = (int) $r['n'];

// max for chart scale
$maxRev = 0;
foreach ($dailyData as $dd) if ($dd['rev'] > $maxRev) $maxRev = $dd['rev'];
if ($maxRev <= 0) $maxRev = 1;

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="d-flex flex-wrap gap-2">
      <?php foreach (['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'last_30' => 'Last 30 Days', 'all' => 'All Time'] as $v => $l): ?>
        <a class="btn btn-sm <?= $range === $v ? 'btn-grad' : 'btn-outline-ia' ?>" href="reports?range=<?= $v ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-4 text-lg-end">
    <button class="btn btn-outline-ia" onclick="window.print()"><?= ia_icon('save', 15) ?> Print report</button>
  </div>
</div>

<div class="row g-4 mb-4">
  <?php
  $cards = [
    ['Total Revenue', peso($summary['revenue']), 'chart', 'Completed orders'],
    ['Orders', (int) $summary['order_count'], 'clipboard', $periodLabel],
    ['Avg. Order Value', peso($summary['avg_order']), 'tag', 'Completed only'],
    ['Outstanding', peso($summary['outstanding']), 'clock', 'Unpaid balances'],
  ];
  foreach ($cards as [$label, $num, $icon, $note]): ?>
    <div class="col-6 col-xl-3">
      <div class="ia-stat">
        <div class="stat-icon"><?= ia_icon($icon) ?></div>
        <div class="stat-num"><?= h($num) ?></div>
        <div class="stat-label"><?= h($label) ?></div>
        <div class="ia-micro mt-1"><?= h($note) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-8">
    <div class="ia-card">
      <div class="card-head"><h3>Revenue trend (<?= h($periodLabel) ?>)</h3></div>
      <div class="card-body card-body-px">
        <div class="bar-chart">
          <?php foreach ($dailyData as $dd): $pct = round(($dd['rev'] / $maxRev) * 100, 1); ?>
            <div class="bar-col" title="<?= h(date('M j', strtotime($dd['d']))) ?> · <?= peso($dd['rev']) ?>">
              <div class="bar-track">
                <div class="bar-fill" style="height:<?= $pct ?>%"></div>
              </div>
              <div class="bar-label"><?= h(date('j', strtotime($dd['d']))) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="ia-card h-100">
      <div class="card-head"><h3>Order status breakdown</h3></div>
      <div class="card-body card-body-px">
        <?php $total = array_sum($statusMap); foreach (order_statuses() as $slug => $label):
          $n = $statusMap[$slug]; $pct = $total ? round(($n / $total) * 100, 1) : 0; ?>
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-ia-muted"><?= $label ?></span>
            <span class="fw-semibold"><?= (int) $n ?></span>
          </div>
          <div class="progress mb-3" style="height:8px">
            <div class="progress-bar <?= $slug === 'completed' ? 'bg-success' : '' ?>" style="width:<?= $pct ?>%"></div>
          </div>
        <?php endforeach; ?>
        <div class="ia-micro mt-2">Grand total: <strong><?= (int) $total ?></strong> orders</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="ia-card">
      <div class="card-head"><h3>Revenue by service</h3></div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>Service</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
            <?php $svcMax = 0; foreach ($byService as $s) if ($s['total'] > $svcMax) $svcMax = (float) $s['total']; if ($svcMax <= 0) $svcMax = 1; ?>
            <?php foreach ($byService as $s): ?>
              <tr>
                <td><?= h($s['name']) ?></td>
                <td class="text-end fw-semibold"><?= peso($s['total']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$byService): ?><tr><td colspan="2"><div class="empty-state"><p>No completed orders in this period.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="ia-card">
      <div class="card-head"><h3>Top customers</h3></div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>Customer</th><th>Orders</th><th class="text-end">Spent</th></tr></thead>
          <tbody>
            <?php foreach ($topCustomers as $tc): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h($tc['name']) ?></div>
                  <div class="ia-micro text-ia-muted"><?= h($tc['phone']) ?></div>
                </td>
                <td class="text-ia-muted"><?= (int) $tc['n'] ?></td>
                <td class="text-end fw-semibold"><?= peso($tc['total']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$topCustomers): ?><tr><td colspan="3"><div class="empty-state"><p>No data yet.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
