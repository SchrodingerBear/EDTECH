<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — my profile.
 */
$pageTitle = 'My Profile';
$pageSub = 'View and update your account details';
$active = '';

$c = crud();
$u = current_user();

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update') {
    $first = trim($_POST['first_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($first === '') {
      $msg = ['danger', 'Name is required.'];
    } else {
      $c->update('users', [
        'first_name' => $first,
        'phone' => $phone,
      ], ['id' => (int) $u['id']]);

      // refresh session cache
      $u['first_name'] = $first;
      $u['phone'] = $phone;
      $_SESSION['user'] = $u;

      audit('profile.update', 'profile', 'user', (int) $u['id']);
      $msg = ['success', 'Profile updated.'];
    }
  } elseif ($action === 'password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $row = $c->get('users', (int) $u['id']);

    if (!password_verify($current, $row['password_hash'])) {
      $msg = ['danger', 'Current password is incorrect.'];
    } elseif (strlen($new) < 6) {
      $msg = ['danger', 'New password must be at least 6 characters.'];
    } elseif ($new !== $confirm) {
      $msg = ['danger', 'New password confirmation does not match.'];
    } else {
      $c->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], ['id' => (int) $u['id']]);
      audit('profile.password', 'profile', 'user', (int) $u['id']);
      $msg = ['success', 'Password updated.'];
    }
  }
}

$user = $c->get('users', (int) $u['id']);

require_once __DIR__ . '/layout/header.php';
?>

<?php if ($msg): ?>
  <div id="ia-ajax-flash"></div>
  <?php flash($msg[0], $msg[1]); ?>
  <script>window.IA_FLASH_OVERRIDE = <?= json_enc([['type' => $msg[0], 'message' => $msg[1]]]) ?>;</script>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="ia-card">
      <div class="card-body text-center card-body-px py-5">
        <div class="ia-avatar ia-avatar-xl mx-auto"><?= h(strtoupper(mb_substr(trim($user['first_name'] ?? ''), 0, 1))) ?></div>
        <h4 class="mt-3 mb-0"><?= h($user['first_name']) ?></h4>
        <p class="text-ia-muted mb-0"><?= h($user['email']) ?></p>
        <span class="badge badge-live mt-2"><?= h(ucwords(str_replace('_', ' ', $user['role_slug'] ?? ''))) ?></span>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Account details</h3></div>
      <div class="card-body card-body-px">
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="update">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" name="first_name" required value="<?= h($user['first_name']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" value="<?= h($user['email']) ?>" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" value="<?= h($user['phone']) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save changes</button>
          </div>
        </form>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Change password</h3></div>
      <div class="card-body card-body-px">
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="password">
          <div class="col-md-4">
            <label class="form-label">Current password</label>
            <input type="password" class="form-control" name="current_password" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">New password</label>
            <input type="password" class="form-control" name="new_password" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Confirm new password</label>
            <input type="password" class="form-control" name="confirm_password" required>
          </div>
          <div class="col-12">
            <button class="btn btn-grad" type="submit"><?= ia_icon('shield', 15) ?> Update password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
