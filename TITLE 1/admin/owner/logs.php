<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_admin();
require_page('owner.logs', 'system.logs');
/**
 * Innovatech PH — owner: audit + access logs.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Audit Logs';
$pageSub = 'Every meaningful action, timestamped';
$active = 'Audit Logs';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$filterModule = trim($_GET['module'] ?? '');

$params = $filterModule !== '' ? ['mod' => $filterModule] : [];
$where = $filterModule !== '' ? 'WHERE a.module = :mod' : '';

$total = (int) crud()->raw("SELECT COUNT(*) FROM audit_logs a $where", $params)->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$logs = crud()->raw(
    "SELECT a.*, u.email AS actor_email, i.name AS inst_name
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.actor_user_id
     LEFT JOIN institutions i ON i.id = a.institution_id
     $where
     ORDER BY a.created_at DESC LIMIT $offset, $perPage",
    $params
)->fetchAll();

$modules = crud()->raw("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

$moduleBadges = [
    'auth' => 'badge-live', 'institutions' => 'badge-draft', 'content' => 'badge-live',
    'platform' => 'badge-off', 'settings' => 'badge-draft', 'security' => 'badge-off',
];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm <?= $filterModule === '' ? 'btn-grad' : 'btn-outline-ia' ?>" href="logs">All</a>
    <?php foreach ($modules as $mod): ?>
      <a class="btn btn-sm <?= $filterModule === $mod ? 'btn-grad' : 'btn-outline-ia' ?>" href="logs?module=<?= h($mod) ?>"><?= h($mod) ?></a>
    <?php endforeach; ?>
  </div>
  <span style="font-size:13px;color:var(--ia-muted)"><?= $total ?> log entr<?= $total === 1 ? 'y' : 'ies' ?></span>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Module</th><th>Institution</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td style="color:var(--ia-muted);white-space:nowrap"><?= h(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
            <td style="font-weight:600"><?= h($log['actor_email'] ?? 'system') ?></td>
            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($log['action']) ?></span></td>
            <td><span class="badge <?= $moduleBadges[$log['module']] ?? 'badge-draft' ?>"><?= h($log['module']) ?></span></td>
            <td style="color:var(--ia-muted)"><?= h($log['inst_name'] ?? '—') ?></td>
            <td style="color:var(--ia-muted)"><?= h($log['ip_address'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="6"><div class="empty-state"><h4>No logs</h4><p>Actions will appear here.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-center gap-2 py-3">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="btn btn-sm <?= $i === $page ? 'btn-grad' : 'btn-outline-ia' ?>" href="logs?page=<?= $i ?><?= $filterModule ? '&module=' . h($filterModule) : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>