<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — audit logs.
 */
$pageTitle = 'Audit Logs';
$pageSub = 'Who did what, and when';
$active = 'Audit Logs';

require_owner();
require_page('logs');

$c = crud();

$module = $_GET['module'] ?? '';
$params = [];
$where = '';
if ($module !== '') {
  $where = ' WHERE a.module = :m';
  $params['m'] = $module;
}

$logs = $c->raw(
  "SELECT a.*, u.email, u.first_name AS user_name
   FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id
   $where ORDER BY a.created_at DESC LIMIT 300", $params
)->fetchAll();

$modules = $c->raw("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll();

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm <?= $module === '' ? 'btn-grad' : 'btn-outline-ia' ?>" href="logs">All</a>
      <?php foreach ($modules as $m): ?>
        <a class="btn btn-sm <?= $module === $m['module'] ? 'btn-grad' : 'btn-outline-ia' ?>" href="logs?module=<?= h($m['module']) ?>"><?= h(ucwords(str_replace('.', ' ', $m['module']))) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-md-5 text-md-end"><span class="text-ia-muted">Latest <?= count($logs) ?> events</span></div>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia" data-force-datatable>
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="text-ia-muted text-nowrap"><?= h(date('M j, Y g:i A', strtotime($l['created_at']))) ?></td>
            <td><?= h($l['user_name'] ?? ($l['email'] ?? 'System')) ?><div class="ia-micro text-ia-muted"><?= h($l['email'] ?? '') ?></div></td>
            <td><span class="badge badge-surface"><?= h($l['action']) ?></span></td>
            <td class="text-ia-muted"><?= h($l['module']) ?></td>
            <td class="text-ia-muted"><?= h($l['ip_address'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state"><h4>No log events</h4></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
