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
    ['icons', 'Managing institutions', 'Create a client under Institutions. A project folder is generated automatically under organizations/. Quick-configure its landing from the settings shortcut.'],
    ['icons2', 'Assigning admins', 'Open Accounts to create or reassign Organization Admin / Staff accounts, or pick an existing account inside the New Institution modal.'],
    ['icons3', 'Role management', 'Use Role Management to control exactly which pages each account can open. An account with no explicit list can open everything.'],
    ['icons4', 'Landing editor & website settings', 'Brand the public landing (colors, fonts, popup animation) per institution, and set global website details under Website Settings.'],
  ],
  'system_admin' => [
    ['icons', 'Organizations', 'Manage all client institutions from the Institutions screen. You can create and configure them but not manage roles.'],
    ['icons2', 'Accounts', 'Create Organization Admin and Staff accounts. System-role accounts are created only by the owner.'],
    ['icons3', 'Audit & files', 'Review audit logs and browse institution project folders under File Manager.'],
  ],
  'system_staff' => [
    ['icons', 'Organizations', 'Browse all client institutions and open their quick-configure screens. View-only: creation and archiving are not available to you.'],
    ['icons2', 'Files', 'Browse institution project folders from File Manager.'],
  ],
  'admin' => [
    ['icons', 'Content', 'Build the campus map: Buildings, Rooms & Areas, 360 Tours, Floor Plans.'],
    ['icons2', 'Theme & landing', 'Customize colors, fonts, popup and marker styles for your institution.'],
    ['icons3', 'AI tools', 'Stitch 360 panoramas or auto-generate facility info.'],
    ['icons4', 'Files', 'Upload and organise media inside your organization folder.'],
  ],
  'staff' => [
    ['icons', 'Content', 'Add facilities and floor plans, upload media assets.'],
    ['icons2', 'AI tools', 'Use AI Stitch to assemble panoramas and AI Info to draft facility descriptions.'],
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
        <p class="ia-meta-lg mb-0">Contact your organization administrator or the Innovatech
          support desk for help with your workspace.</p>
      </div>
    </div>

    <?php if (in_array($role, ['owner', 'system_admin'])): ?>
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