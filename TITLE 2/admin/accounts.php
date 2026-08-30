<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — user accounts (owner only).
 */
$pageTitle = 'Accounts';
$pageSub = 'Manage staff login accounts';
$active = 'Accounts';

require_owner();
require_page('accounts');

$c = crud();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  if ($action === 'create') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $roleId = (int) ($_POST['role_id'] ?? 0);

    try {
      if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid email is required.');
      }
      if ($c->count('users', ['email' => $email, 'deleted_at' => ['IS', null]]) > 0) {
        throw new RuntimeException('An account with that email already exists.');
      }
      $password = $_POST['password'] ?? '';
      if (strlen($password) < 6) {
        throw new RuntimeException('Password must be at least 6 characters.');
      }
      $data = [
        'role_id' => $roleId ?: 2,
        'email' => $email,
        'username' => trim($_POST['username'] ?? '') ?: null,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? '') ?: null,
        'is_active' => isset($_POST['is_active']) ? 1 : 1,
        'created_by' => current_user()['id'] ?? null,
      ];
      if ($data['first_name'] === '' || $data['last_name'] === '') {
        throw new RuntimeException('First and last name are required.');
      }
      $id = $c->insert('users', $data);
      audit('account.create', 'accounts', 'user', $id);
      flash('success', 'Account created for ' . $email . '.');
    } catch (Throwable $e) {
      flash('danger', $e->getMessage());
    }
    redirect('accounts');
  }

  if ($action === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    $u = $c->get('users', $id);
    if ($u && (int) $u['id'] !== (int) current_user()['id']) {
      $c->update('users', ['is_active' => $u['is_active'] ? 0 : 1], ['id' => $id]);
      audit('account.toggle', 'accounts', 'user', $id);
      flash('success', 'Account status updated.');
    }
    redirect('accounts');
  }

  if ($action === 'reset_password') {
    $id = (int) ($_POST['id'] ?? 0);
    $u = $c->get('users', $id);
    if ($u) {
      $pw = $_POST['password'] ?? '';
      if (strlen($pw) < 6) {
        flash('danger', 'Password must be at least 6 characters.');
      } else {
        $c->update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)], ['id' => $id]);
        audit('account.reset_password', 'accounts', 'user', $id);
        flash('success', 'Password reset for ' . $u['email'] . '.');
      }
    }
    redirect('accounts');
  }
}

$accounts = $c->raw(
  "SELECT u.*, r.name AS role_name, r.slug AS role_slug
   FROM users u JOIN roles r ON r.id = u.role_id
   WHERE u.deleted_at IS NULL ORDER BY u.created_at DESC"
)->fetchAll();

$roles = $c->raw("SELECT * FROM roles WHERE slug IN ('owner','staff') ORDER BY id")->fetchAll();

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-md-8"><h5 class="mb-0 mt-1">User accounts (<?= count($accounts) ?>)</h5></div>
  <div class="col-md-4 text-md-end">
    <button class="btn btn-grad" data-bs-toggle="modal" data-bs-target="#addAccount"><?= ia_icon('plus', 15) ?> Add account</button>
  </div>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia" data-force-datatable>
      <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($accounts as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="ia-avatar ia-avatar-sm"><?= h(strtoupper(mb_substr($u['first_name'][0] ?? '', 0, 1) . mb_substr($u['last_name'][0] ?? '', 0, 1))) ?></div>
                <div class="fw-semibold"><?= h($u['first_name'] . ' ' . $u['last_name']) ?></div>
              </div>
            </td>
            <td class="text-ia-muted"><?= h($u['email']) ?></td>
            <td><span class="badge badge-surface"><?= h($u['role_name']) ?></span></td>
            <td class="text-ia-muted text-nowrap"><?= $u['last_login_at'] ? h(date('M j, Y g:i A', strtotime($u['last_login_at']))) : 'Never' ?></td>
            <td><span class="badge <?= $u['is_active'] ? 'badge-live' : 'badge-off' ?>"><?= $u['is_active'] ? 'active' : 'inactive' ?></span></td>
            <td class="text-end text-nowrap">
              <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                <button class="btn btn-sm btn-icon" data-bs-toggle="modal" data-bs-target="#resetPw<?= (int) $u['id'] ?>" title="Reset password"><?= ia_icon('shield', 15) ?></button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="form" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-sm btn-icon" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>"><?= $u['is_active'] ? ia_icon('moon', 15) : ia_icon('sun', 15) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <div class="modal fade" id="resetPw<?= (int) $u['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post">
                  <input type="hidden" name="form" value="reset_password">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <div class="modal-header"><h5 class="modal-title">Reset password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <label class="form-label">New password for <?= h($u['email']) ?></label>
                    <input class="form-control" type="text" name="password" placeholder="min 6 characters" required>
                  </div>
                  <div class="modal-footer"><button class="btn btn-grad" type="submit">Reset</button></div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addAccount" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="form" value="create">
        <div class="modal-header"><h5 class="modal-title">Add user account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" required></div>
            <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
            <div class="col-md-6"><label class="form-label">Role</label>
              <select class="form-select" name="role_id">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="text" name="password" value="password" required></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-grad" type="submit">Create account</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
