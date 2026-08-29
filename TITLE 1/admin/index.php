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
    $row = crud()->raw("SELECT id FROM users WHERE email = :e AND deleted_at IS NULL", ['e' => $email])->fetch();

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
            $settings = crud()->get('platform_settings', 1);
            $subject = str_replace('{{link}}', $resetUrl, $tpl['subject'] ?? 'Reset your password');
            $body = str_replace('{{link}}', '<a href="' . h($resetUrl) . '">' . h($resetUrl) . '</a>', $tpl['body_html'] ?? '');
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
  <style>
    .login-wrap { min-height: 100vh; display: grid; grid-template-columns: 1.05fr 1fr; }
    .login-brand {
      position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
      padding: 52px 56px; color: #fff;
      background: radial-gradient(1200px 600px at -10% -20%, rgba(139,92,246,.55), transparent 60%),
                  radial-gradient(900px 500px at 110% 110%, rgba(56,178,172,.45), transparent 60%),
                  linear-gradient(150deg, #24263c 0%, #181b2e 60%, #10131f 100%);
    }
    .login-brand::after {
      content: ""; position: absolute; width: 460px; height: 460px; border-radius: 50%;
      background: conic-gradient(from 120deg, #5b5bd6, #8b5cf6, #38b2ac, #5b5bd6);
      filter: blur(90px); opacity: .32; right: -120px; top: -60px; animation: drift 12s ease-in-out infinite;
    }
    @keyframes drift { 0%,100% { transform: translate(0,0) rotate(0deg);} 50% { transform: translate(-40px,60px) rotate(40deg);} }
    .login-brand .brand { position: relative; z-index: 1; display:flex; align-items:center; gap:14px; font-weight:800; font-size:20px; letter-spacing:-.02em; }
    .login-brand .brand .logo { width:44px; height:44px; border-radius:14px; background:linear-gradient(135deg,#5b5bd6,#8b5cf6); display:grid; place-items:center; font-size:19px; font-weight:800; box-shadow:0 8px 22px rgba(123,123,255,.4); }
    .login-brand .tagline { position:relative; z-index:1; max-width:420px; }
    .login-brand .tagline h1 { font-size: 42px; font-weight: 800; letter-spacing:-.03em; line-height:1.08; margin-bottom:18px; }
    .login-brand .tagline p { color:#aab; font-size:16px; line-height:1.6; }
    .login-brand .foot { position: relative; z-index:1; color:#899; font-size:13px; }
    .login-card-wrap { display:flex; align-items:center; justify-content:center; padding:40px; }
    .login-card { width:100%; max-width:420px; }
    .login-card h2 { font-size:24px; font-weight:800; letter-spacing:-.02em; }
    .login-card .sub { color:var(--ia-muted); font-size:14px; margin-bottom:26px; }
    .login-card code { background:var(--ia-surface); color:var(--ia-text); padding:2px 6px; border-radius:6px; }
    .back-link { color:var(--ia-muted); font-size:13.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .back-link:hover { color:var(--ia-text); }
    @media (max-width: 900px) { .login-wrap { grid-template-columns:1fr; } .login-brand { display:none; } }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <div class="brand">
      <div class="logo"><img src="<?= url('assets/logo2.webp') ?>" alt="Innovatech PH" style="width:100%;height:100%;object-fit:contain;border-radius:14px;"></div>
      <span><?= h(APP_NAME) ?></span>
    </div>
    <div class="tagline">
      <h1>Open every door<br>of your campus.</h1>
      <p>AI-assisted AR 360° virtual campus navigation — manage tours, floor plans, themes and staff from one workspace.</p>
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
          <div class="alert alert-success border-0 rounded-4" style="background:rgba(16,185,129,.12);color:var(--ia-success)">
            If that email exists, a reset link is on its way.
            <?php if ($devResetLink): ?>
              <div class="mt-2 small" style="word-break:break-all">
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
          <div class="alert alert-danger border-0 rounded-4 py-2 small" style="background:rgba(239,68,68,.12);color:var(--ia-danger)"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="d-grid gap-3">
          <input type="hidden" name="form" value="login">
          <div class="input-icon">
            <span class="icon"><?= ia_icon('mail', 16) ?></span>
            <input type="email" name="email" class="form-control" placeholder="Email address" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="input-icon">
            <span class="icon"><?= ia_icon('shield', 16) ?></span>
            <input type="password" name="password" class="form-control" placeholder="Password" required id="login-pw">
            <button type="button" class="pw-eye-icon btn" data-pw="login-pw" tabindex="-1" title="Show password" aria-label="Show password"><?= ia_icon('eye', 16) ?></button>
          </div>
          <div class="d-flex justify-content-end" style="margin-top:-2px">
            <a class="back-link" href="<?= url('admin/index?action=forgot') ?>">Forgot password?</a>
          </div>
          <button class="btn btn-grad py-2 fs-6" type="submit">Sign in</button>
        </form>

        <div class="alert alert-info border-0 rounded-4 mt-4 mb-0 small" style="background:rgba(59,130,246,.1);color:var(--ia-muted)">
          <strong>Demo access</strong> — pick an account to auto-fill the form:
          <div class="d-flex flex-wrap gap-2 mt-2" id="quick-logins">
            <?php foreach ($quickAccs as $qa):
                $qaEmail = (string) $qa['email'];
                $qaRoleLabel = ucwords(str_replace('_', ' ', $qa['role_slug']));
                $qaName = trim(($qa['first_name'] ?? '') . ' ' . ($qa['last_name'] ?? ''));
                ?>
              <button type="button" class="btn btn-sm btn-outline-ia quick-login" data-email="<?= h($qaEmail) ?>" data-pass="password" title="<?= h($qaName) ?>">
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