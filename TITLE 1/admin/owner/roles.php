<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
require_page('owner.roles');
/**
 * Innovatech PH — owner: role & page-access management.
 * Set the exact pages each account may open. Empty = full access (default).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Role Management';
$pageSub = 'Control which pages each account can open';
$active = 'Role Management';

// page catalog per role (matches nav page keys)
$pageCatalog = [
    'system_admin' => [
        ['system.dashboard', 'System Dashboard'],
        ['system.institutions', 'Institutions'],
        ['system.accounts', 'Accounts'],
        ['system.logs', 'Audit Logs'],
        ['system.files', 'File Manager'],
    ],
    'system_staff' => [
        ['system.dashboard', 'System Dashboard'],
        ['system.institutions', 'Institutions'],
        ['system.files', 'File Manager'],
    ],
    'admin' => [
        ['admin.dashboard', 'Dashboard'],
        ['admin.buildings', 'Buildings'],
        ['admin.locations', 'Rooms & Areas'],
        ['admin.tours', '360 Tours'],
        ['admin.floorplans', 'Floor Plans'],
        ['admin.settings', 'Theme & Landing'],
        ['admin.ai', 'AI Tools'],
        ['admin.files', 'Organization Files'],
    ],
    'staff' => [
        ['staff.dashboard', 'Dashboard'],
        ['staff.facilities', 'Facilities'],
        ['staff.floorplans', 'Floor Plans'],
        ['staff.uploads', 'Media Uploads'],
        ['staff.aistitch', 'AI Stitch'],
        ['staff.aiinfo', 'AI Info'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['access_action'] ?? '';
    if ($action === 'save') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $u = crud()->raw(
            "SELECT u.id, u.email, u.role_id, r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=:id AND u.deleted_at IS NULL",
            ['id' => $userId]
        )->fetch();
        if ($u && isset($pageCatalog[$u['role_slug']])) {
            $keys = array_map('strval', (array) ($_POST['page_keys'] ?? []));
            // only valid keys for that role are accepted
            $valid = array_column($pageCatalog[$u['role_slug']], 0);
            $keys = array_values(array_unique(array_intersect($keys, $valid)));

            crud()->delete('user_page_access', ['user_id' => $userId]);
            foreach ($keys as $k) {
                crud()->insert('user_page_access', ['user_id' => $userId, 'page_key' => $k]);
            }
            crud()->insert('audit_logs', [
                'actor_user_id' => (int) current_user()['id'],
                'institution_id' => null,
                'action' => 'access.update',
                'module' => 'roles',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            flash('success', 'Access updated for ' . $u['email'] . '. It takes effect on that account\'s next sign-in.');
        } else {
            flash('error', 'Account not found.');
        }
    }
    redirect('admin/owner/roles');
}

$accounts = crud()->raw(
    "SELECT u.id, u.email, u.first_name, u.last_name, u.last_login_at, r.slug AS role_slug, r.name AS role_name,
            i.name AS inst_name
     FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN institutions i ON i.id=u.institution_id
     WHERE u.deleted_at IS NULL AND r.slug IN ('system_admin','system_staff','admin','staff')
       AND u.id <> :me
     ORDER BY r.id ASC, u.first_name ASC",
    ['me' => (int) current_user()['id']]
)->fetchAll();

// existing access rows indexed by (user_id, page_key)
$access = crud()->select('user_page_access', 'user_id, page_key');
$accessMap = [];
foreach ($access as $a) {
    $accessMap[$a['user_id']][$a['page_key']] = true;
}
$rolesName = ['system_admin' => 'System Admin', 'system_staff' => 'System Staff', 'admin' => 'Organization Admin', 'staff' => 'Organization Staff'];
?>

<div class="ia-card">
  <div class="card-head">
    <h3>Page access</h3>
    <span class="text-muted" style="font-size:12.5px">Accounts with no explicit list can open everything.</span>
  </div>
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>Account</th><th>Role</th><th>Institution</th><th>Current access</th><th class="text-end">Configure</th></tr></thead>
      <tbody>
        <?php foreach ($accounts as $acc): $slug = $acc['role_slug']; $has = isset($accessMap[$acc['id']]) ? array_keys($accessMap[$acc['id']]) : []; ?>
          <tr>
            <td>
              <div style="font-weight:700"><?= h($acc['first_name'] . ' ' . $acc['last_name']) ?></div>
              <div style="font-size:12.5px;color:var(--ia-muted)"><?= h($acc['email']) ?></div>
            </td>
            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($rolesName[$slug] ?? ucfirst($slug)) ?></span></td>
            <td style="color:var(--ia-muted)"><?= h($acc['inst_name'] ?? '—') ?></td>
            <td>
              <?php if (!$has): ?>
                <span class="badge badge-live">All pages</span>
              <?php else: ?>
                <span class="badge badge-draft"><?= count($has) ?>/<?= count($pageCatalog[$slug]) ?> pages</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#access-modal-<?= (int) $acc['id'] ?>">
                <?= ia_icon('shield', 13) ?> Access
              </button>
            </td>
          </tr>

          <div class="modal fade" id="access-modal-<?= (int) $acc['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Page access — <?= h($acc['first_name'] . ' ' . $acc['last_name']) ?></h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <form method="post">
                <input type="hidden" name="access_action" value="save">
                <input type="hidden" name="user_id" value="<?= (int) $acc['id'] ?>">
                <div class="modal-body">
                  <p style="font-size:13px;color:var(--ia-muted)"><?= h($acc['email']) ?> · <?= h($rolesName[$slug] ?? ucfirst($slug)) ?></p>
                  <p style="font-size:12.5px;color:var(--ia-muted)">
                    Tick the pages this account may open. Leave all unticked for <strong>full access</strong> (recommended default).
                  </p>
                  <div class="d-grid gap-2">
                    <?php foreach ($pageCatalog[$slug] as [$key, $label]): ?>
                      <label class="d-flex align-items-center gap-3 p-3 rounded-4" style="background:var(--ia-surface);border:1px solid var(--ia-border);cursor:pointer">
                        <input class="form-check-input mt-0" type="checkbox" name="page_keys[]" value="<?= h($key) ?>" <?= isset($accessMap[$acc['id']][$key]) ? 'checked' : '' ?>>
                        <span style="font-weight:600;font-size:14px"><?= h($label) ?></span>
                        <code class="ms-auto text-muted" style="font-size:11.5px"><?= h($key) ?></code>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-ia" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-grad px-4" type="submit">Save access</button>
                </div>
              </form>
            </div></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$accounts): ?><tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><?= ia_icon('shield', 26) ?></div><h4>No accounts to manage</h4><p>Create accounts first under Accounts.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>