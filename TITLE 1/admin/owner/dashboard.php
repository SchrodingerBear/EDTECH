<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/**
 * Innovatech PH — owner overview.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Owner Overview';
$pageSub = 'Platform health at a glance';
$active = 'Overview';

$c = crud();
$stats = [
    'institutions'  => $c->count('institutions', ['deleted_at' => ['IS', null]]),
    'active'        => $c->count('institutions', ['is_active' => 1, 'is_published' => 1, 'deleted_at' => ['IS', null]]),
    'admins'        => $c->count('users', ['role_id' => 2, 'deleted_at' => ['IS', null]]),
    'staff'         => $c->count('users', ['role_id' => 3, 'deleted_at' => ['IS', null]]),
    'scenes'        => $c->count('tour_scenes', ['deleted_at' => ['IS', null]]),
    'logins'        => $c->count('audit_logs', ['action' => 'auth.login']),
];

$recentInstitutions = $c->raw(
    "SELECT i.id, i.name, i.short_name, i.slug, i.institution_type, i.landing_mode, i.is_published, i.is_active, i.created_at,
            (SELECT COUNT(*) FROM users u WHERE u.institution_id=i.id AND u.deleted_at IS NULL) AS users
     FROM institutions i WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 6"
)->fetchAll();

$recentLogins = $c->raw(
    "SELECT a.*, u.email FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id
     WHERE a.action='auth.login' ORDER BY a.created_at DESC LIMIT 6"
)->fetchAll();
?>
<div class="row g-4 mb-4">
  <?php $statCards = [
      ['Institutions', $stats['institutions'], 'school', var_export($stats['active'], true) . ' published live'],
      ['Admins', $stats['admins'], 'users', 'Platform admins'],
      ['Staff', $stats['staff'], 'user', 'Content staff'],
      ['360 Scenes', $stats['scenes'], 'camera', 'Across all schools'],
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
        <h3>Recent institutions</h3>
        <a class="back-link" href="institutions">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>School</th><th>Mode</th><th>Users</th><th>Status</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($recentInstitutions as $inst):
                $mode = $inst['landing_mode'] === 'floor_plan' ? 'Floor plan' : '360 rotation'; ?>
              <tr>
                <td>
                  <div style="font-weight:700"><?= h($inst['name']) ?></div>
                  <div style="font-size:12.5px;color:var(--ia-muted)"><?= h($inst['slug']) ?></div>
                </td>
                <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($mode) ?></span></td>
                <td style="color:var(--ia-muted)"><?= (int) $inst['users'] ?></td>
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
            <?php if (!$recentInstitutions): ?><tr><td colspan="5"><div class="empty-state"><h4>No institutions yet</h4><p>Create your first school client.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Quick actions</h3></div>
      <div class="card-body d-grid gap-2" style="padding:18px">
        <a class="btn btn-grad" href="institutions">+ Create institution (auto-generates folder)</a>
        <a class="btn btn-outline-ia" href="accounts">Manage admins & staff</a>
        <a class="btn btn-outline-ia" href="files">Open file manager</a>
        <a class="btn btn-outline-ia" href="<?= url('/') ?>" target="_blank">View product landing</a>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Quick Guides</h3></div>
      <div class="card-body" style="padding:18px">
        <p class="text-muted" style="font-size:13px; margin-bottom:15px;">Follow these steps to manage the platform:</p>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">1. Set up the Homepage</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Go to <a href="website-settings">Website Settings</a> to edit the main Innovatech PH homepage copy, stats, and hero banner.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">2. Client Onboarding</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Create new partner <a href="institutions">Institutions</a>. A folder will automatically be generated for each client.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">3. Account Access</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Add organization admins and staff via <a href="accounts">Accounts</a> and assign them to their institution.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">4. Manage Data</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Use the <a href="files">File Manager</a> and <a href="archive">Archive & Restore</a> to manage organization assets and deleted content.</div>
        </div>
        <div>
            <strong style="color:var(--ia-text)">5. Monitor Activity</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Keep an eye on system health via <a href="logs">Audit Logs</a> to see who is doing what.</div>
        </div>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Recent sign-ins</h3><a class="back-link" href="logs">All logs</a></div>
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
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>