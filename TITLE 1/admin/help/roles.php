<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_staff();
/**
 * Innovatech PH — roles & features overview for system roles.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Roles & Features';
$pageSub = 'What each role can do';
$active = 'Roles & Features';

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
<div class="ia-card">
  <div class="card-head"><h3>Feature matrix</h3></div>
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><?php foreach ($matrix[0] as $h): ?><th style="<?= $h === 'Feature' ? '' : 'text-align:center' ?>"><?= h($h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach (array_slice($matrix, 1) as $row): ?>
          <tr>
            <?php foreach ($row as $i => $cell): ?>
              <td style="<?= $i === 0 ? 'font-weight:600' : 'text-align:center' ?>">
                <?php if (in_array($cell, ['yes', 'no'], true)): ?>
                  <span class="badge <?= $cell === 'yes' ? 'badge-live' : 'badge-off' ?>"><?= $cell === 'yes' ? '✓ yes' : 'no' ?></span>
                <?php else: ?>
                  <span style="color:var(--ia-muted)"><?= h($cell) ?></span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="ia-card mt-4">
  <div class="card-body d-grid gap-3" style="font-size:13.5px">
    <div><strong>Owner</strong> — full access: organizations, accounts, system settings, role management, file manager and the platform dashboard.</div>
    <div><strong>System Admin</strong> — day-to-day platform operations: manage organizations and accounts, review audit logs and files. Cannot change role-permission lists.</div>
    <div><strong>System Staff</strong> — supports the platform team: view organizations, open quick-configure and browse institution files.</div>
    <div><strong>Organization Admin</strong> — manages a single client institution: buildings, rooms, tours, floor plans, theme and AI tools.</div>
    <div><strong>Organization Staff</strong> — contributes content: facilities, floor plans, media uploads and AI-assisted authoring.</div>
    <div><strong>Visitor</strong> — public user account; browses a landing and virtual tour.</div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>