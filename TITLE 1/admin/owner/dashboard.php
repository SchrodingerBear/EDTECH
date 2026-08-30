<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/**
 * Innovatech PH — owner overview.
 */
$pageTitle = 'Owner Overview';
$pageSub = 'Platform health at a glance';
$active = 'Overview';
$bodyClass = 'page-owner-dashboard';

$c = crud();
$stats = [
  'institutions' => $c->count('institutions', ['deleted_at' => ['IS', null]]),
  'active' => $c->count('institutions', ['is_active' => 1, 'is_published' => 1, 'deleted_at' => ['IS', null]]),
  'admins' => $c->count('users', ['role_id' => 2, 'deleted_at' => ['IS', null]]),
  'staff' => $c->count('users', ['role_id' => 3, 'deleted_at' => ['IS', null]]),
  'logins' => $c->count('audit_logs', ['action' => 'auth.login']),
];

$storageStats = project_storage_stats();
$storage = $storageStats['storage'];
$storageUsed = $storageStats['storageUsed'];
$diskFree = $storageStats['diskFree'];
$diskTotal = $storageStats['diskTotal'];

$recentInstitutions = $c->raw(
  "SELECT i.id, i.name, i.short_name, i.slug, i.institution_type, i.landing_mode, i.is_published, i.is_active, i.created_at,
            (SELECT COUNT(*) FROM users u WHERE u.institution_id=i.id AND u.deleted_at IS NULL) AS users
     FROM institutions i WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 8"
)->fetchAll();

$recentLogins = $c->raw(
  "SELECT a.created_at, a.ip_address, u.email, u.first_name, u.last_name
     FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id
     WHERE a.action='auth.login' ORDER BY a.created_at DESC LIMIT 40"
)->fetchAll();

require_once __DIR__ . '/../layout/header.php';
?>
<div class="row g-4 mb-4">
  <?php $statCards = [
    ['Institutions', $stats['institutions'], 'school', (int) $stats['active'] . ' published live', 'institutions'],
    ['Admins', $stats['admins'], 'users', 'Platform admins', 'accounts'],
    ['Staff', $stats['staff'], 'user', 'Content staff', 'accounts'],
    ['Sign-ins', $stats['logins'], 'shield', 'Recorded logins', 'logs'],
  ]; ?>
  <?php foreach ($statCards as [$label, $num, $icon, $note, $link]): ?>
    <div class="col-6 col-xl-3">
      <a href="<?= h($link) ?>" class="stat-card-link d-block text-decoration-none text-reset">
        <div class="ia-stat">
          <div class="stat-icon"><?= ia_icon($icon) ?></div>
          <div class="stat-num"><?= (int) $num ?></div>
          <div class="stat-label"><?= h($label) ?></div>
          <div class="ia-micro mt-1"><?= h($note) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <?php $storageChartId = 'ia-storage-chart-owner';
    require __DIR__ . '/../partials/storage-panel.php'; ?>
  </div>
  <div class="col-xl-5">
    <div class="ia-card h-100">
      <div class="card-head">
        <h3>Quick actions</h3>
      </div>
      <div class="card-body d-grid gap-2 card-body-px">
        <a class="btn btn-grad" href="institutions">+ Create institution (auto-generates folder)</a>
        <a class="btn btn-outline-ia" href="accounts">Manage admins & staff</a>
        <a class="btn btn-outline-ia" href="website-settings">Edit product landing</a>
        <a class="btn btn-outline-ia" href="files">Open file manager</a>
        <a class="btn btn-outline-ia" href="<?= url('/') ?>" target="_blank">View product landing</a>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="ia-card">
      <div class="card-head">
        <h3>Quick guides</h3>
      </div>
      <div class="card-body card-body-px">
        <div class="row g-3 owner-guide">
          <div class="col-md-6 col-xl-4">
            <strong>1. Set up the Homepage</strong>
            <div>Go to <a href="website-settings">Website Settings</a> to
              edit the main Innovatech PH homepage copy, stats, and hero banner.</div>
          </div>
          <div class="col-md-6 col-xl-4">
            <strong>2. Client Onboarding</strong>
            <div>Create new partner <a
                href="institutions">Institutions</a>. A folder will automatically be generated for each client.</div>
          </div>
          <div class="col-md-6 col-xl-4">
            <strong>3. Account Access</strong>
            <div>Add organization admins and staff via <a
                href="accounts">Accounts</a> and assign them to their institution.</div>
          </div>
          <div class="col-md-6 col-xl-4">
            <strong>4. Manage Data</strong>
            <div>Use the <a href="files">File Manager</a> and <a
                href="archive">Archive & Restore</a> to manage organization assets and deleted content.</div>
          </div>
          <div class="col-md-6 col-xl-4">
            <strong>5. Monitor Activity</strong>
            <div>Keep an eye on system health via <a href="logs">Audit
                Logs</a> to see who is doing what.</div>
          </div>
          <div class="col-md-6 col-xl-4">
            <strong>6. Server environment</strong>
            <div>Check PHP version and write permissions in <a
                href="<?= url('admin/help') ?>">Help Center</a> if uploads fail.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
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
          <thead>
            <tr>
              <th>School</th>
              <th>Mode</th>
              <th>Users</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentInstitutions as $inst):
              $mode = $inst['landing_mode'] === 'floor_plan' ? 'Floor plan' : '360 rotation'; ?>
              <tr>
                <td>
                  <div class="fw-bold"><?= h($inst['name']) ?></div>
                  <div class="fs-125 text-ia-muted"><?= h($inst['slug']) ?></div>
                </td>
                <td><span class="badge badge-surface"><?= h($mode) ?></span></td>
                <td class="text-ia-muted"><?= (int) $inst['users'] ?></td>
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
            <?php if (!$recentInstitutions): ?>
              <tr>
                <td colspan="5">
                  <div class="empty-state">
                    <h4>No institutions yet</h4>
                    <p>Create your first school client.</p>
                  </div>
                </td>
              </tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card ">
      <div class="card-head">
        <h3>Recent sign-ins</h3><a class="back-link" href="logs">All logs</a>
      </div>
      <div class="table-responsive">
        <table class="table table-ia" data-force-datatable>
          <thead>
            <tr>
              <th>Account</th>
              <th>Name</th>
              <th>IP</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentLogins as $lg): ?>
              <tr>
                <td class="fw-semibold"><?= h($lg['email'] ?? '—') ?></td>
                <td class="text-ia-muted">
                  <?= h(trim(($lg['first_name'] ?? '') . ' ' . ($lg['last_name'] ?? '')) ?: '—') ?>
                </td>
                <td class="text-ia-muted"><?= h($lg['ip_address'] ?? '—') ?></td>
                <td class="text-ia-muted text-nowrap">
                  <?= h(date('M j, Y g:i A', strtotime($lg['created_at']))) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>