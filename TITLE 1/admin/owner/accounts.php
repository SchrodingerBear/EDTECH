<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_admin();
require_page('owner.accounts', 'system.accounts');
/**
 * Innovatech PH — owner/system admin: accounts (admins & staff) per institution.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Accounts';
$pageSub = 'Admins, staff and system accounts';
$active = 'Accounts';

$isOwner = current_role() === 'owner';

// actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['acc_action'] ?? '';
    try {
        if ($action === 'create') {
            $roleSlug = $_POST['role'];
            $institutionId = (int) ($_POST['institution_id'] ?? 0) ?: null;
            $email = strtolower(trim($_POST['email'] ?? ''));
            $first = trim($_POST['first_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $sendEmail = isset($_POST['send_email']) ? 1 : 0;

            // only the owner can create system accounts
            if (!$isOwner && in_array($roleSlug, ['system_admin', 'system_staff'], true)) {
                throw new RuntimeException('Only the owner can create system accounts.');
            }
            $roleId = match ($roleSlug) {
                'admin' => 2, 'staff' => 3,
                'system_admin' => 5, 'system_staff' => 6,
                default => throw new RuntimeException('Unsupported role.'),
            };

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid email address is required.');
            }
            if (!$sendEmail) {
                throw new RuntimeException('Sending the temporary credentials to the account email is required.');
            }

            if (crud()->get('users', ['email' => $email, 'deleted_at' => ['IS', null]])) {
                throw new RuntimeException('That email is already in use.');
            }

            $plainPassword = $password !== '' ? $password : (random_token(3) . '-' . random_token(2));

            $newId = crud()->insert('users', [
                'role_id' => $roleId,
                'institution_id' => in_array($roleSlug, ['admin', 'staff'], true) ? $institutionId : null,
                'email' => $email,
                'username' => slugify($first . '-' . $last),
                'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
                'first_name' => $first ?: ($roleSlug === 'admin' ? 'Campus' : ucfirst($roleSlug)),
                'last_name' => $last ?: ucfirst($roleSlug),
                'is_active' => 1,
                'created_by' => (int) current_user()['id'],
            ]);

            $instName = null;
            if ($institutionId) {
                $instName = crud()->get('institutions', $institutionId)['name'] ?? null;
            }
            $newUser = [
                'email' => $email,
                'username' => slugify($first . '-' . $last),
                'first_name' => $first ?: ($roleSlug === 'admin' ? 'Campus' : ucfirst($roleSlug)),
                'last_name' => $last ?: ucfirst($roleSlug),
            ];
            $mailed = send_credentials($newUser, $roleSlug, $plainPassword, $instName);
            flash($mailed ? 'success' : 'warning',
                ucfirst($roleSlug) . ' account created. Temporary credentials were ' .
                ($mailed ? 'emailed to ' . h($email) . '.' : 'not sent (check the SMTP/email template settings) — fetch them in the mail spool.'));
            audit('account.create', 'accounts', 'user', $newId);
        }

        if ($action === 'toggle') {
            crud()->raw("UPDATE users SET is_active = 1 - is_active WHERE id = :id", ['id' => (int) ($_POST['id'] ?? 0)]);
            flash('success', 'Account status updated.');
        }

        if ($action === 'delete') {
            crud()->update('users', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => (int) ($_POST['id'] ?? 0)]);
            flash('success', 'Account archived.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/owner/accounts');
}

$accounts = crud()->raw(
    "SELECT u.*, r.slug AS role_slug, i.name AS inst_name, i.slug AS inst_slug
     FROM users u JOIN roles r ON r.id=u.role_id
     LEFT JOIN institutions i ON i.id=u.institution_id
     WHERE u.deleted_at IS NULL
       AND r.slug IN ('admin','staff'" . ($isOwner ? ",'system_admin','system_staff','owner'" : '') . ")
     ORDER BY u.created_at DESC"
)->fetchAll();

$institutions = crud()->select('institutions', 'id, name', ['deleted_at' => ['IS', null]], 'ORDER BY name');
$roleList = ['admin', 'staff'];
if ($isOwner) {
    $roleList = ['admin', 'staff', 'system_admin', 'system_staff'];
}
$roles = crud()->select('roles', 'id, slug, name', ['slug' => ['IN', $roleList]], 'ORDER BY id');
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-1" style="color:var(--ia-muted);font-size:13.5px"><?= count($accounts) ?> account(s)</p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#acc-create"><?= ia_icon('users', 16) ?> Add account</button>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>User</th><th>Role</th><th>Institution</th><th>Last login</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($accounts as $acc): ?>
          <tr>
            <td>
              <div style="font-weight:700"><?= h($acc['first_name'] . ' ' . $acc['last_name']) ?></div>
              <div style="font-size:12.5px;color:var(--ia-muted)"><?= h($acc['email']) ?></div>
            </td>
            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h(ucfirst($acc['role_slug'])) ?></span></td>
            <td style="color:var(--ia-muted)"><?= h($acc['inst_name'] ?? '—') ?></td>
            <td style="color:var(--ia-muted)"><?= $acc['last_login_at'] ? h(date('M j, g:i A', strtotime($acc['last_login_at']))) : 'never' ?></td>
            <td><span class="badge <?= (int) $acc['is_active'] === 1 ? 'badge-live' : 'badge-off' ?>"><?= (int) $acc['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <form method="post" class="d-inline"><input type="hidden" name="acc_action" value="toggle"><input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia" title="Toggle active"><?= (int) $acc['is_active'] === 1 ? ia_icon('shield', 12) . ' deactivate' : ia_icon('rocket', 12) . ' activate' ?></button>
                </form>
                <form method="post" class="d-inline" data-delete-form data-confirm="Archive <?= h($acc['email']) ?>?">
                  <input type="hidden" name="acc_action" value="delete"><input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$accounts): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><?= ia_icon('users', 26) ?></div><h4>No staff or admins yet</h4><p>Add admins for your institutions.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- create modal -->
<div class="modal fade" id="acc-create" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add account</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="acc_action" value="create">
      <div class="modal-body d-grid gap-3">
        <div>
          <label class="form-label">Role</label>
          <div class="role-pills" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px">
            <?php foreach ($roles as $r): ?>
              <label class="role-pill" style="padding:11px">
                <input type="radio" name="role" value="<?= h($r['slug']) ?>" <?= $r['slug'] === 'admin' ? 'checked' : '' ?>>
                <?= h($r['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <label class="form-label">Institution</label>
          <select class="form-select" name="institution_id" id="acc-inst-select">
            <option value="">— none / unassigned —</option>
            <?php foreach ($institutions as $i): ?><option value="<?= (int) $i['id'] ?>"><?= h($i['name']) ?></option><?php endforeach; ?>
          </select>
          <div class="form-text">Leave empty to create an unassigned org account — it can then be attached to a new institution via the assign-admin dropdown.</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name"></div>
          <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name"></div>
        </div>
        <div><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
        <div>
          <label class="form-label">Temporary password</label>
          <div class="pw-group">
            <input class="form-control" name="password" id="acc-pw" placeholder="blank = auto-generated">
            <button type="button" class="btn btn-outline-ia btn-shrink" data-gen="acc-pw" title="Generate a temporary password"><?= ia_icon('refresh', 14) ?> Generate</button>
            <button type="button" class="btn btn-outline-ia btn-shrink pw-eye" data-pw="acc-pw" title="Show / hide password"><?= ia_icon('eye', 14) ?></button>
          </div>
          <div class="form-text">Leave blank to auto-generate a secure temporary password and email it.</div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="send_email" id="acc-send" checked required>
          <label class="form-check-label" for="acc-send" style="font-size:13.5px">Send these temporary credentials to <span id="acc-send-target">the account email</span> (required)</label>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Create account &amp; email credentials</button></div>
    </form>
  </div></div>
</div>

<script>
  (function () {
    var instSel = document.getElementById('acc-inst-select');
    var instWrap = instSel ? instSel.closest('div') : null;
    var pills = document.querySelectorAll('#acc-create input[name="role"]');
    function syncInstVisibility() {
      if (!instWrap) return;
      var org = document.querySelector('#acc-create input[name="role"]:checked');
      var visible = org && (org.value === 'admin' || org.value === 'staff');
      instWrap.style.display = visible ? '' : 'none';
      if (instSel) instSel.required = visible;
      if (instSel) instSel.value = '';
    }
    pills.forEach(function (p) { p.addEventListener('change', syncInstVisibility); });
    syncInstVisibility();
  })();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>