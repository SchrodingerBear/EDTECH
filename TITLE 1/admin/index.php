<?php
/**
 * Innovatech PH — app entry.
 * Logged out  → login screen (role auto-detected + password reset).
 * Logged in   → role home.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout/nav.php'; // ia_icon() used on this page

if (current_user()) {
  redirect(role_home(current_role()));
}

$error = null;
$action = $_GET['action'] ?? 'login'; // login | forgot

/* ------------------------------- LOGIN POST ------------------------------- */
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

/* ------------------------------ FORGOT POST ------------------------------ */
$forgotSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'forgot') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $row = crud()->raw("SELECT id, first_name, last_name FROM users WHERE email = :e AND deleted_at IS NULL", ['e' => $email])->fetch();

  // Always pretend to send to avoid user enumeration.
  $forgotSent = true;

  if ($row) {
    $token = random_token(24);
    crud()->insert('password_resets', [
      'user_id' => (int) $row['id'],
      'token_hash' => hash('sha256', $token),
      'expires_at' => date('Y-m-d H:i:s', time() + 3600),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    // Send via configured SMTP / platform mail if possible; else dev link.
    $resetUrl = url('admin/reset-password?token=' . $token);
    $sent = false;
    try {
      $tpl = crud()->get('email_templates', ['slug' => 'password_reset']);
      $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
      $vars = [
        '{{name}}' => $name !== '' ? $name : 'there',
        '{{email}}' => $email,
        '{{link}}' => '<a href="' . h($resetUrl) . '">' . h($resetUrl) . '</a>',
      ];
      $subject = strtr((string) ($tpl['subject'] ?? 'Reset your password'), $vars);
      $body = strtr((string) ($tpl['body_html'] ?? ''), $vars);
      $sent = send_email($email, $subject, $body);
    } catch (Throwable $e) {
      $sent = false;
    }

    $_SESSION['_dev_reset_link'] = $sent ? null : $resetUrl;
  }
}

$devResetLink = $_SESSION['_dev_reset_link'] ?? null;
unset($_SESSION['_dev_reset_link']);

/* Quick demo logins — only accounts that actually exist get a button. */
$quickAccs = [];
try {
  $quickAccs = crud()->raw(
    "SELECT u.email, r.name AS role_name, r.slug AS role_slug, u.first_name, u.last_name
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.deleted_at IS NULL AND u.is_active = 1
         ORDER BY r.id ASC"
  )->fetchAll();
} catch (Throwable $e) {
  $quickAccs = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Sign in · <?= h(APP_NAME) ?></title>
  <link rel="icon" href="<?= url('public/icon.svg') ?>" type="image/svg+xml">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= url('admin/assets/css/dashboard.css') ?>" rel="stylesheet">
</head>

<body class="page-auth">
  <div class="login-wrap">
    <div class="login-brand">
      <div class="brand">
        <div class="logo"><img src="<?= url('assets/logo2.webp') ?>" alt="Innovatech PH"></div>
        <span><?= h(APP_NAME) ?></span>
      </div>
      <div class="tagline">
        <h1>Open every door<br>of your campus.</h1>
        <p>AI-assisted AR 360° virtual campus navigation — manage tours, floor plans, themes and staff from one
          workspace.</p>
      </div>
      <div class="foot">© <?= date('Y') ?> <?= h(APP_NAME) ?> · <?= h(APP_PRODUCT) ?></div>
    </div>

    <div class="login-card-wrap">
      <div class="login-card">
        <?php if ($action === 'forgot'): ?>
          <a class="back-link mb-3" href="<?= url('admin/index') ?>">← Back to sign in</a>
          <h2 class="mt-2">Reset password</h2>
          <p class="sub">Enter your email and we'll send you a secure reset link.</p>

          <?php if ($forgotSent): ?>
            <div class="alert alert-ia-success border-0 rounded-4">
              If that email exists, a reset link is on its way.
              <?php if ($devResetLink): ?>
                <div class="mt-2 small break-all">
                  <strong>Dev link (SMTP not configured):</strong><br>
                  <a href="<?= h($devResetLink) ?>"><?= h($devResetLink) ?></a>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <form method="post" action="<?= url('admin/index?action=forgot') ?>" class="d-grid gap-3">
              <input type="hidden" name="form" value="forgot">
              <div class="input-icon">
                <span class="icon"><?= ia_icon('mail', 16) ?></span>
                <input type="email" name="email" class="form-control" placeholder="you@school.edu.ph" required autofocus>
              </div>
              <button class="btn btn-grad py-2" type="submit">Send reset link</button>
            </form>
          <?php endif; ?>

        <?php else: ?>
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
            <div class="d-flex justify-content-end forgot-row">
              <a class="back-link" href="<?= url('admin/index?action=forgot') ?>">Forgot password?</a>
            </div>
            <button class="btn btn-grad py-2 fs-6" type="submit">Sign in</button>
          </form>

          <div class="alert alert-ia-info border-0 rounded-4 mt-4 mb-0 small">
            <strong>Demo access</strong> — pick an account to auto-fill the form:
            <div class="d-flex flex-wrap gap-2 mt-2" id="quick-logins">
              <?php foreach ($quickAccs as $qa):
                $qaEmail = (string) $qa['email'];
                $qaRoleLabel = ucwords(str_replace('_', ' ', $qa['role_slug']));
                $qaName = trim(($qa['first_name'] ?? '') . ' ' . ($qa['last_name'] ?? ''));
                ?>
                <button type="button" class="btn btn-sm btn-outline-ia quick-login" data-email="<?= h($qaEmail) ?>"
                  data-pass="password" title="<?= h($qaName) ?>">
                  <?= h($qaRoleLabel) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="text-center mt-4">
            <a class="back-link" href="<?= url('/') ?>" target="_blank">← Back to the public site</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('admin/assets/js/dashboard.js') ?>"></script>
  <script>
    document.querySelectorAll('.quick-login').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var email = btn.getAttribute('data-email');
        var pass = btn.getAttribute('data-pass');
        document.querySelector('input[name="email"]').value = email;
        document.querySelector('input[name="password"]').value = pass;
        document.querySelector('input[name="email"]').dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  </script>
</body>

</html>