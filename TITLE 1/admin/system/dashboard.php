<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_staff();
/**
 * Innovatech PH — system overview (system_admin / system_staff).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'System Overview';
$pageSub = 'Platform health for the Innovatech team';
$active = 'Dashboard';

$stats = [
    'institutions' => crud()->count('institutions', ['deleted_at' => ['IS', null]]),
    'active'       => crud()->count('institutions', ['is_active' => 1, 'is_published' => 1, 'deleted_at' => ['IS', null]]),
    'org_admins'   => crud()->count('users', ['role_id' => 2, 'deleted_at' => ['IS', null]]),
    'org_staff'    => crud()->count('users', ['role_id' => 3, 'deleted_at' => ['IS', null]]),
    'scenes'       => crud()->count('tour_scenes', ['deleted_at' => ['IS', null]]),
    'logins'       => crud()->count('audit_logs', ['action' => 'auth.login']),
];

$recentInstitutions = crud()->raw(
    'SELECT i.id, i.name, i.short_name, i.slug, i.landing_mode, i.is_published, i.is_active, i.created_at FROM institutions i WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 8'
)->fetchAll();

$recentLogins = crud()->raw(
    "SELECT a.*, u.email FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.action='auth.login' ORDER BY a.created_at DESC LIMIT 6"
)->fetchAll();
?>
<div class="row g-4 mb-4">
  <?php $statCards = [
      ['Institutions', $stats['institutions'], 'school', var_export($stats['active'], true) . ' live'],
      ['Organization Admins', $stats['org_admins'], 'users', 'Assigned per institution'],
      ['Organization Staff', $stats['org_staff'], 'user', 'Content contributors'],
      ['360 Scenes', $stats['scenes'], 'camera', 'Across all institutions'],
  ]; ?>
  <?php foreach ($statCards as [$label, $num, $icon, $note]): ?>
    <div class="col-6 col-xl-3">
      <div class="ia-stat">
        <div class="stat-icon"><?= ia_icon($icon) ?></div>
        <div class="stat-num"><?= (int) $num ?></div>
        <div class="stat-label"><?= h($label) ?></div>
        <div style="font-size:11.5px;color:var(--ia-muted);margin-top:2px"><?= $note ?></div>
      </div>
    </div>
  <?php endforeach; ?>
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
                  <div style="font-weight:700"><?= h($inst['name']) ?></div>
                  <div style="font-size:12.5px;color:var(--ia-muted)"><?= h($inst['slug']) ?></div>
                </td>
                <td><span class="badge" style="background:var(--ia-surface-2)"><?= $inst['landing_mode'] === 'floor_plan' ? 'Floor plan' : '360 rotation' ?></span></td>
                <td>
                  <?php if ((int) $inst['is_published'] === 1 && (int) $inst['is_active'] === 1): ?>
                    <span class="badge badge-live">live</span>
                  <?php elseif ((int) $inst['is_active'] === 1): ?>
                    <span class="badge badge-draft">draft</span>
                  <?php else: ?>
                    <span class="badge badge-off">inactive</span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--ia-muted)"><?= h(date('M j, Y', strtotime($inst['created_at']))) ?></td>
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
      <div class="card-body d-grid gap-2" style="padding:18px">
        <?php if (current_role() === 'owner' || current_role() === 'system_admin'): ?>
          <a class="btn btn-grad" href="<?= url('admin/owner/institutions') ?>">+ Create institution</a>
          <a class="btn btn-outline-ia" href="<?= url('admin/owner/accounts') ?>">Manage accounts</a>
        <?php endif; ?>
        <a class="btn btn-outline-ia" href="<?= url('admin/help') ?>">Help center & navigation</a>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Quick Guides</h3></div>
      <div class="card-body" style="padding:18px">
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">Manage Platform</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Create and oversee partner institutions and organization accounts.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">System Health</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Monitor live institutions, active logins, and recent scenes.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">Help Center</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Access documentation and support for platform administration.</div>
        </div>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Recent sign-ins</h3><a class="back-link" href="<?= url('admin/owner/logs') ?>">All logs</a></div>
      <div class="card-body" style="padding:14px 18px">
        <?php foreach ($recentLogins as $lg): ?>
          <div class="d-flex align-items-center gap-3 py-2" style="border-bottom:1px solid var(--ia-border)">
            <span class="ia-avatar" style="width:32px;height:32px;font-size:12px"><?= h(mb_strtoupper(mb_substr($lg['email'], 0, 1))) ?></span>
            <div class="flex-grow-1">
              <div style="font-size:13.5px;font-weight:600"><?= h($lg['email'] ?? '—') ?></div>
              <div style="font-size:12px;color:var(--ia-muted)"><?= h($lg['ip_address'] ?? '') ?> · <?= h(date('M j, g:i A', strtotime($lg['created_at']))) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$recentLogins): ?><p class="text-muted" style="font-size:13px">No sign-ins recorded yet.</p><?php endif; ?>
        <?php if (current_role() !== 'owner'): ?><p class="text-muted" style="font-size:12px;margin-top:10px">Log entries shown are read-only for system staff.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>