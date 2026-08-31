<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
/**
 * Innovatech PH — help center for all admin roles.
 */
$pageTitle = 'Help Center';
$pageSub = 'Guidance for your workspace';
$active = 'Help Center';
$bodyClass = 'page-help';
require_once __DIR__ . '/../layout/header.php';

$role = current_role();
$guides = match ($role) {
  'owner' => [
    ['icons', 'Managing institutions', 'Create and manage client Institutions. A project folder is generated automatically.'],
    ['icons2', 'Assigning admins', 'Open Accounts to create or reassign Organization Admin / Staff accounts.'],
    ['icons3', 'Role management', 'Use Role Management to control exactly which pages each account can open.'],
    ['icons4', 'Platform Settings', 'Configure System Settings, Website Settings, and Email Support.'],
    ['icons5', 'Audit & Files', 'Review system-wide Audit Logs and browse all files via File Manager.'],
    ['icons6', 'Archive & Restore', 'Manage deleted records and files through the Archive & Restore tool.'],
  ],
  'system_admin' => [
    ['icons', 'Organizations', 'Manage all client institutions from the Institutions screen.'],
    ['icons2', 'Accounts', 'Create Organization Admin and Staff accounts.'],
    ['icons3', 'Audit Logs', 'Review comprehensive system audit logs to track activities.'],
    ['icons4', 'File Manager', 'Browse and manage institution project folders globally.'],
  ],
  'system_staff' => [
    ['icons', 'Organizations', 'Browse all client institutions (View-only for creation/archiving).'],
    ['icons2', 'Files', 'Browse institution project folders from File Manager.'],
  ],
  'admin' => [
    ['icons', 'Content', 'Build the campus map: Buildings, Locations, 360 Tours, Floor Plans.'],
    ['icons2', 'Theme & Landing', 'Customize colors, fonts, popup and marker styles for your institution landing.'],
    ['icons3', 'AI & AR Tools', 'Use AI Tools for panorama stitching and Augmented Reality settings.'],
    ['icons4', 'Organization Files', 'Upload and organize media inside your organization folder.'],
    ['icons5', 'Archive & Restore', 'Recover or permanently delete items from your institution archive.'],
  ],
  'staff' => [
    ['icons', 'Content', 'Add facilities, floor plans, and manage media uploads.'],
    ['icons2', 'AI Tools', 'Use AI Stitch to assemble panoramas and AI Info to draft facility descriptions.'],
    ['icons3', 'Archive & Restore', 'Manage deleted records through the Archive & Restore functionality.'],
  ],
  default => [],
};

$matrix = [
  ['Feature', 'Owner', 'System Admin', 'System Staff', 'Org Admin', 'Org Staff'],
  ['Manage organizations', 'yes', 'yes', 'view only', '—', '—'],
  ['Create organization (project folder)', 'yes', 'yes', 'no', '—', '—'],
  ['Manage accounts', 'yes', 'yes', 'no', '—', '—'],
  ['Create system accounts', 'yes', 'no', 'no', '—', '—'],
  ['Role / page management', 'yes', 'no', 'no', '—', '—'],
  ['System-wide audit logs', 'yes', 'yes', 'no', '—', '—'],
  ['Manage own institution content', '—', '—', '—', 'yes', 'yes'],
  ['Org quick-configure', 'yes', 'yes', 'yes', 'yes', 'no'],
  ['System dashboard', 'yes', 'yes', 'yes', '—', '—'],
  ['Help Center', 'yes', 'yes', 'yes', 'yes', 'yes'],
];
?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="ia-card">
      <div class="card-head">
        <h3>Quick guides</h3>
      </div>
      <div class="card-body d-grid gap-3">
        <?php foreach ($guides as [$icon, $title, $body]): ?>
          <div class="d-flex gap-3 p-3 rounded-4 surface-line">
            <span class="ia-icon mt-1 help-guide-icon"><?= ia_icon('info') ?></span>
            <div>
              <div class="fw-bold"><?= h($title) ?></div>
              <div class="ia-meta-lg"><?= h($body) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">

    <div class="ia-card ">
      <div class="card-head">
        <h3>Still stuck?</h3>
      </div>
      <div class="card-body">
        <p class="ia-meta-lg">Contact your organization administrator or the Innovatech
          support desk for help with your workspace.</p>
        <a class="btn btn-grad mt-3" href="<?= url('admin/support?open=1') ?>"><?= ia_icon('life-buoy', 14) ?> Submit a support ticket</a>
        <p class="ia-meta-md mt-3 mb-0">Your ticket is emailed instantly to your organization owner and the Innovatech support desk, and you'll get a status update when it's worked on.</p>
      </div>
    </div>

    <?php if (in_array($role, ['owner', 'system_admin', 'admin', 'staff'])): ?>
      <div class="ia-card mt-4">
        <div class="card-head">
          <h3>System Environment</h3>
        </div>
        <div class="card-body">
          <div class="fs-125">
            <?php
            $checks = platform_environment_checks();
            foreach ($checks as $c) {
              $icon = $c['ok'] ? '<span class="text-success fw-bold">✓</span>' : '<span class="text-danger fw-bold">✕</span>';
              echo '<div class="d-flex justify-content-between mb-2 pb-2 divider-dashed-bottom">';
              echo "<span>{$c['label']}</span>";
              echo "<span class='text-end'>$icon {$c['detail']}</span>";
              echo "</div>";
            }
            ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php if (in_array($role, ['owner', 'system_admin', 'system_staff'])): ?>
  <div class="ia-card mt-4">
    <div class="card-head">
      <h3>Feature matrix</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-ia">
        <thead>
          <tr><?php foreach ($matrix[0] as $h): ?>
              <th class="<?= $h === 'Feature' ? '' : 'text-center' ?>"><?= h($h) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($matrix, 1) as $row): ?>
            <tr>
              <?php foreach ($row as $i => $cell): ?>
                <td class="<?= $i === 0 ? 'fw-semibold' : 'text-center' ?>">
                  <?php if (in_array($cell, ['yes', 'no'], true)): ?>
                    <span
                      class="badge <?= $cell === 'yes' ? 'badge-live' : 'badge-off' ?>"><?= $cell === 'yes' ? '✓ yes' : 'no' ?></span>
                  <?php else: ?>
                    <span class="text-ia-muted"><?= h($cell) ?></span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>