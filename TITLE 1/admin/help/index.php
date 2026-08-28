<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
/**
 * Innovatech PH — help center for all admin roles.
 */
$pageTitle = 'Help Center';
$pageSub = 'Guidance for your workspace';
$active = 'Help Center';
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
?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="ia-card">
      <div class="card-head"><h3>Quick guides</h3></div>
      <div class="card-body d-grid gap-3">
        <?php foreach ($guides as [$icon, $title, $body]): ?>
          <div class="d-flex gap-3 p-3 rounded-4" style="background:var(--ia-surface);border:1px solid var(--ia-border)">
            <span class="ia-icon mt-1" style="opacity:.85"><?= ia_icon('info') ?></span>
            <div>
              <div style="font-weight:700"><?= h($title) ?></div>
              <div style="font-size:13.5px;color:var(--ia-muted)"><?= h($body) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="ia-card">
      <div class="card-head"><h3>Reset your password</h3></div>
      <div class="card-body">
        <p style="font-size:13.5px;color:var(--ia-muted)">Keep your workspace credentials safe. You can update your password from your profile.</p>
        <a class="btn btn-grad w-100" href="<?= url('admin/reset-password') ?>">Change password</a>
      </div>
    </div>
    <div class="ia-card mt-4">
      <div class="card-head"><h3>Still stuck?</h3></div>
      <div class="card-body">
        <p style="font-size:13.5px;color:var(--ia-muted)">Contact your organization administrator or the Innovatech support desk for help with your workspace.</p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>