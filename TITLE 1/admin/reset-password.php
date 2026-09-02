<?php
/**
 * Innovatech PH — set a new password from a reset token.
 */
require_once __DIR__ . '/../includes/functions.php';

$token = (string) ($_GET['token'] ?? '');
$error = null;
$ok = false;

$reset = crud()->raw(
    "SELECT pr.user_id, u.email, u.first_name
     FROM password_resets pr JOIN users u ON u.id = pr.user_id
     WHERE pr.token_hash = :h AND pr.used_at IS NULL AND pr.expires_at > NOW()",
    ['h' => hash('sha256', $token)]
)->fetch();

if (!$reset) {
    $error = 'This reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirmation'] ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            db_transaction(function () use ($reset, $password, $token) {
                crud()->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => (int) $reset['user_id']]);
                crud()->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['token_hash' => hash('sha256', $token)]);
            });
            $ok = true;
        } catch (Throwable $e) {
            $error = 'Could not update the password: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset password · <?= h(APP_NAME) ?></title>
  <link href="<?= url('assets/css/inter-font.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= url('admin/assets/css/dashboard.css') ?>" rel="stylesheet">
</head>
<body class="page-auth d-flex align-items-center justify-content-center min-vh-100">
  <div class="rp-box">
    <div class="ia-card">
      <div class="card-head"><h3><?= $ok ? 'All set' : ($reset ? 'Choose a new password' : 'Reset link invalid') ?></h3></div>
      <div class="card-body">
        <?php if ($ok): ?>
          <p class="text-muted small">Your password has been updated. You can now sign in.</p>
          <a class="btn btn-grad w-100 py-2" href="<?= url('admin/index') ?>">Go to sign in</a>
        <?php elseif ($error): ?>
          <div class="alert alert-ia-danger border-0 rounded-4 py-2 small"><?= h($error) ?></div>
          <a class="btn btn-outline-ia w-100 py-2" href="<?= url('admin/index?action=forgot') ?>">Request a new link</a>
        <?php else: ?>
          <form method="post" class="d-grid gap-3">
            <div class="input-icon">
              <input type="password" name="password" class="form-control" placeholder="New password" required minlength="8" id="rp-pw">
              <button type="button" class="pw-eye-icon btn" data-pw="rp-pw" tabindex="-1" title="Show password" aria-label="Show password"><?= ia_icon('eye', 16) ?></button>
            </div>
            <div class="input-icon">
              <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required minlength="8" id="rp-pw2">
              <button type="button" class="pw-eye-icon btn" data-pw="rp-pw2" tabindex="-1" title="Show password" aria-label="Show password"><?= ia_icon('eye', 16) ?></button>
            </div>
            <button class="btn btn-grad py-2" type="submit">Update password</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var toggles = document.querySelectorAll('[data-pw]');
      Array.prototype.forEach.call(toggles, function (btn) {
        btn.addEventListener('click', function () {
          var input = document.getElementById(btn.getAttribute('data-pw'));
          if (!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.title = show ? 'Hide password' : 'Show password';
        });
      });
    })();
  </script>
</body>
</html>