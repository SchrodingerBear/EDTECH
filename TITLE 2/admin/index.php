<?php
/**
 * Lavadora — app entry.
 * Logged out → login screen (role auto-detected).
 * Logged in  → role home.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout/nav.php';

if (current_user()) {
  redirect(role_home(current_role()));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
  try {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
      throw new RuntimeException('Please enter your email and password.');
    }

    $user = crud()->raw(
      "SELECT u.*, r.slug AS role_slug FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email AND u.deleted_at IS NULL",
      ['email' => $email]
    )->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
      $error = 'Invalid email or password.';
    } elseif ((int) $user['is_active'] !== 1) {
      $error = 'This account is inactive. Contact the platform owner.';
    } else {
      do_login(db(), (int) $user['id']);
      redirect(role_home($user['role_slug']));
    }
  } catch (Throwable $e) {
    $error = 'Login failed: ' . $e->getMessage();
  }
}

/* Quick demo logins — only accounts that actually exist get a button. */
$quickAccs = [];
try {
  $quickAccs = crud()->raw(
    "SELECT u.email, r.name AS role_name, r.slug AS role_slug, u.first_name
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.deleted_at IS NULL AND u.is_active = 1
         ORDER BY r.id ASC"
  )->fetchAll();
} catch (Throwable $e) {
  $quickAccs = [];
}

$settings = [];
try {
  $settings = crud()->get('settings', 1) ?? [];
} catch (Throwable $e) {
  $settings = [];
}
$businessName = $settings['business_name'] ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Sign in · <?= h(APP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= url('admin/assets/css/dashboard.css') ?>" rel="stylesheet">
</head>

<body class="page-auth">
  <div class="login-wrap">
    <div class="login-brand">
      <div class="brand">
        <div class="logo"><img src="<?= h($settings['logo_path'] ? url($settings['logo_path']) : url('admin/assets/img/logo.svg')) ?>" alt="<?= h(APP_NAME) ?>"></div>
        <span><?= h($businessName) ?></span>
      </div>
      <div class="tagline">
        <h1>Fresh, clean laundry<br>made simple.</h1>
        <p><?= h(APP_PRODUCT) ?> — manage orders, customers, services and daily reports from one workspace.</p>
      </div>
      <div class="foot">© <?= date('Y') ?> <?= h($businessName) ?> · <?= h(APP_PRODUCT) ?></div>
    </div>

    <div class="login-card-wrap">
      <div class="login-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="badge badge-live">Secure access</span>
        </div>
        <h2>Welcome back</h2>
        <p class="sub">Sign in to the <?= h(APP_NAME) ?> workspace.</p>

        <?php if ($error): ?>
          <div class="alert alert-ia-danger border-0 rounded-4 py-2 small"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="d-grid gap-3">
          <input type="hidden" name="form" value="login">
          <div class="input-icon">
            <span class="icon"><?= ia_icon('mail', 16) ?></span>
            <input type="email" name="email" class="form-control" placeholder="Email address" required autofocus
              value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="input-icon">
            <span class="icon"><?= ia_icon('shield', 16) ?></span>
            <input type="password" name="password" class="form-control" placeholder="Password" required id="login-pw">
            <button type="button" class="pw-eye-icon btn" data-pw="login-pw" tabindex="-1" title="Show password"
              aria-label="Show password"><?= ia_icon('eye', 16) ?></button>
          </div>
          <button class="btn btn-grad py-2 fs-6" type="submit">Sign in</button>
        </form>

        <?php if ($quickAccs): ?>
          <div class="alert alert-ia-info border-0 rounded-4 mt-4 mb-0 small">
            <strong>Demo access</strong> — pick an account to auto-fill the form:
            <div class="d-flex flex-wrap gap-2 mt-2" id="quick-logins">
              <?php foreach ($quickAccs as $qa):
                $qaEmail = (string) $qa['email'];
                $qaRoleLabel = ucwords(str_replace('_', ' ', $qa['role_slug']));
                $qaName = trim($qa['first_name'] ?? '');
                ?>
                <button type="button" class="btn btn-sm btn-outline-ia quick-login" data-email="<?= h($qaEmail) ?>"
                  data-pass="password" title="<?= h($qaName) ?>">
                  <?= h($qaRoleLabel) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="text-center mt-4">
          <span class="ia-micro text-ia-muted"><?= h(APP_NAME) ?> &middot; <?= h(APP_PRODUCT) ?></span>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('admin/assets/js/dashboard.js') ?>"></script>
  <script>
    document.querySelectorAll('.quick-login').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelector('input[name="email"]').value = btn.getAttribute('data-email');
        document.querySelector('input[name="password"]').value = btn.getAttribute('data-pass');
      });
    });
  </script>
</body>

</html>
