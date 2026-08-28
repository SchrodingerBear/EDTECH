<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Innovatech PH — my profile / change password.
 */
require_once __DIR__ . '/layout/header.php';

$pageTitle = 'My profile';
$pageSub = 'Account details and security';

$u = current_user();
$msg = null;
$err = null;
$me = crud()->get('users', (int) $u['id']) ?: ['first_name' => '', 'last_name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        try {
            crud()->update('users', ['first_name' => $first, 'last_name' => $last, 'phone' => $phone], ['id' => (int) $u['id']]);
            $_SESSION['user']['first_name'] = $first;
            $_SESSION['user']['last_name'] = $last;
            $msg = 'Profile updated.';
        } catch (Throwable $e) { $err = 'Could not update profile.'; }
    } elseif ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['new_confirmation'] ?? '');
        $hash = crud()->raw("SELECT password_hash FROM users WHERE id=:id", ['id' => (int) $u['id']])->fetchColumn();
        if (!password_verify($cur, $hash)) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $err = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $err = 'New passwords do not match.';
        } else {
            crud()->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], ['id' => (int) $u['id']]);
            $msg = 'Password changed.';
        }
    }
}
?>
<?php if ($msg): ?><script>window.iaToast = window.iaToast || (()=>{});</script><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="ia-card">
      <div class="card-body text-center" style="padding:34px">
        <div class="ia-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:26px">
          <?= h(mb_strtoupper(mb_substr(trim($u['first_name'][0] ?? '') . trim($u['last_name'][0] ?? ''), 0, 2))) ?>
        </div>
        <h3 style="font-weight:800"><?= h(display_name($u)) ?></h3>
        <p class="text-muted mb-2"><?= h($u['email']) ?></p>
        <span class="badge" style="background:var(--ia-gradient);color:#fff"><?= h($u['role_name']) ?></span>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <?php if ($msg): ?><div class="alert alert-success border-0 rounded-4 py-2 small" style="background:rgba(16,185,129,.12);color:var(--ia-success)"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger border-0 rounded-4 py-2 small" style="background:rgba(239,68,68,.12);color:var(--ia-danger)"><?= h($err) ?></div><?php endif; ?>

    <div class="ia-card mb-4">
      <div class="card-head"><h3>Profile details</h3></div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="profile">
          <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" value="<?= h($u['first_name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="<?= h($u['last_name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= h($me['phone']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Email (fixed)</label><input class="form-control" value="<?= h($u['email']) ?>" disabled></div>
          <div class="col-12 text-end"><button class="btn btn-grad px-4" type="submit">Save changes</button></div>
        </form>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Change password</h3></div>
      <div class="card-body">
        <form method="post" class="d-grid gap-3">
          <input type="hidden" name="action" value="password">
          <div><label class="form-label">Current password</label>
            <div class="pw-group">
              <input type="password" name="current" class="form-control" required id="pw-cur">
              <button type="button" class="btn btn-outline-ia btn-shrink pw-eye" data-pw="pw-cur" title="Show password"><?= ia_icon('eye', 14) ?></button>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">New password</label>
              <div class="pw-group">
                <input type="password" name="new" class="form-control" minlength="8" required id="pw-new">
                <button type="button" class="btn btn-outline-ia btn-shrink pw-eye" data-pw="pw-new" title="Show password"><?= ia_icon('eye', 14) ?></button>
              </div>
            </div>
            <div class="col-md-6"><label class="form-label">Repeat new password</label>
              <div class="pw-group">
                <input type="password" name="new_confirmation" class="form-control" minlength="8" required id="pw-new2">
                <button type="button" class="btn btn-outline-ia btn-shrink pw-eye" data-pw="pw-new2" title="Show password"><?= ia_icon('eye', 14) ?></button>
              </div>
            </div>
          </div>
          <div class="text-end"><button class="btn btn-outline-ia px-4" type="submit">Update password</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>