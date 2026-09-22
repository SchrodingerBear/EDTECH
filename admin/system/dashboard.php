<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_staff();
/**
 * Innovatech PH — system overview (system_admin / system_staff).
 */
$pageTitle = 'System Overview';
$pageSub = 'Platform health for the Innovatech team';
$active = 'Dashboard';
$bodyClass = 'page-system-dashboard';
require_once __DIR__ . '/../layout/header.php';

$stats = [
    'institutions' => crud()->count('institutions', ['deleted_at' => ['IS', null]]),
    'active'       => crud()->count('institutions', ['is_active' => 1, 'is_published' => 1, 'deleted_at' => ['IS', null]]),
    'org_admins'   => crud()->count('users', ['role_id' => 2, 'deleted_at' => ['IS', null]]),
    'org_staff'    => crud()->count('users', ['role_id' => 3, 'deleted_at' => ['IS', null]]),
    'logins'       => crud()->count('audit_logs', ['action' => 'auth.login']),
];
$storageStats = project_storage_stats();
$storage = $storageStats['storage'];
$storageUsed = $storageStats['storageUsed'];
$diskFree = $storageStats['diskFree'];
$diskTotal = $storageStats['diskTotal'];

$recentInstitutions = crud()->raw(
    'SELECT i.id, i.name, i.short_name, i.slug, i.landing_mode, i.is_published, i.is_active, i.created_at FROM institutions i WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 8'
)->fetchAll();

$recentLogins = crud()->raw(
    "SELECT a.created_at, a.ip_address, u.email, u.first_name, u.last_name FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.action='auth.login' ORDER BY a.created_at DESC LIMIT 40"
)->fetchAll();
?>
<div class="row g-4 mb-4">
  <?php $statCards = [
      ['Institutions', $stats['institutions'], 'school', var_export($stats['active'], true) . ' live'],
      ['Organization Admins', $stats['org_admins'], 'users', 'Assigned per institution'],
      ['Organization Staff', $stats['org_staff'], 'user', 'Content contributors'],
      ['Sign-ins', $stats['logins'], 'shield', 'Recorded logins'],
  ]; ?>
  <?php foreach ($statCards as [$label, $num, $icon, $note]): ?>
    <div class="col-6 col-xl-3">
      <div class="ia-stat">
        <div class="stat-icon"><?= ia_icon($icon) ?></div>
        <div class="stat-num"><?= (int) $num ?></div>
        <div class="stat-label"><?= h($label) ?></div>
        <div class="ia-micro mt-1"><?= $note ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <?php $storageChartId = 'ia-storage-chart-system'; require __DIR__ . '/../partials/storage-panel.php'; ?>
  </div>
  <div class="col-xl-5">
    <div class="ia-card h-100">
      <div class="card-head"><h3>Quick actions</h3></div>
      <div class="card-body d-grid gap-2 card-body-px">
        <?php if (current_role() === 'owner' || current_role() === 'system_admin'): ?>
          <a class="btn btn-grad" href="<?= url('admin/owner/institutions') ?>">+ Create institution</a>
          <a class="btn btn-outline-ia" href="<?= url('admin/owner/accounts') ?>">Manage accounts</a>
        <?php endif; ?>
        <?php if (current_role() === 'owner' || current_role() === 'system_admin'): ?>
          <a class="btn btn-outline-ia" href="<?= url('admin/help') ?>">Help center & navigation</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="ia-card">
      <div class="card-head">
        <h3>Institutions</h3>
        <a class="back-link" href="<?= url('admin/owner/institutions') ?>">Open manager</a>
      </div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>Institution</th><th>Mode</th><th>Status</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($recentInstitutions as $inst): ?>
              <tr>
                <td>
                  <div class="fw-bold"><?= h($inst['name']) ?></div>
                  <div class="fs-125 text-ia-muted"><?= h($inst['slug']) ?></div>
                </td>
                <td><span class="badge badge-surface"><?= $inst['landing_mode'] === 'floor_plan' ? 'Floor plan' : '360 rotation' ?></span></td>
                <td>
                  <?php if ((int) $inst['is_published'] === 1 && (int) $inst['is_active'] === 1): ?>
                    <span class="badge badge-live">live</span>
                  <?php elseif ((int) $inst['is_active'] === 1): ?>
                    <span class="badge badge-draft">draft</span>
                  <?php else: ?>
                    <span class="badge badge-off">inactive</span>
                  <?php endif; ?>
                </td>
                <td class="text-ia-muted"><?= h(date('M j, Y', strtotime($inst['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentInstitutions): ?><tr><td colspan="4"><div class="empty-state"><h4>No institutions yet</h4><p>Create your first client under Institutions.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Quick actions</h3></div>
      <div class="card-body d-grid gap-2 card-body-px">
        <?php if (current_role() === 'owner' || current_role() === 'system_admin'): ?>
          <a class="btn btn-grad" href="<?= url('admin/owner/institutions') ?>">+ Create institution</a>
          <a class="btn btn-outline-ia" href="<?= url('admin/owner/accounts') ?>">Manage accounts</a>
        <?php endif; ?>
        <?php if (current_role() === 'owner' || current_role() === 'system_admin'): ?>
          <a class="btn btn-outline-ia" href="<?= url('admin/help') ?>">Help center & navigation</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Quick Guides</h3></div>
      <div class="card-body card-body-px">
        <div class="guide-item">
            <strong>Manage Platform</strong>
            <div>Create and oversee partner institutions and organization accounts.</div>
        </div>
        <div class="guide-item">
            <strong>System Health</strong>
            <div>Monitor live institutions, active logins, and recent scenes.</div>
        </div>
        <div class="guide-item">
            <strong>Help Center</strong>
            <div>Access documentation and support for platform administration.</div>
        </div>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Recent sign-ins</h3><a class="back-link" href="<?= url('admin/owner/logs') ?>">All logs</a></div>
      <div class="card-body card-body-sm">
        <?php foreach ($recentLogins as $lg): ?>
          <div class="d-flex align-items-center gap-3 py-2 divider-bottom">
            <span class="ia-avatar ia-avatar-sm"><?= h(mb_strtoupper(mb_substr($lg['email'], 0, 1))) ?></span>
            <div class="flex-grow-1">
              <div class="ia-meta-lg fw-semibold"><?= h($lg['email'] ?? '—') ?></div>
              <div class="fs-12 text-ia-muted"><?= h($lg['ip_address'] ?? '') ?> · <?= h(date('M j, g:i A', strtotime($lg['created_at']))) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$recentLogins): ?><p class="text-muted fs-13">No sign-ins recorded yet.</p><?php endif; ?>
        <?php if (current_role() !== 'owner'): ?><p class="text-muted fs-12 mt-2">Log entries shown are read-only for system staff.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>