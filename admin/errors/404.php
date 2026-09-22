<?php require_once __DIR__ . '/../../includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 · <?= h(APP_NAME) ?></title>
  <link href="<?= url('assets/css/inter-font.css') ?>" rel="stylesheet">
  <link href="<?= url('admin/assets/css/dashboard.css') ?>" rel="stylesheet">
</head>
<body class="page-error">
  <div class="error-box">
    <div class="ia-avatar mx-auto mb-3 ia-avatar-xl">404</div>
    <h1 class="fw-800 ls-tight">Page not found</h1>
    <p class="text-muted fs-14">The page you're looking for doesn't exist or was moved.</p>
    <a class="btn btn-grad px-4" href="<?= url('/') ?>">Go to home</a>
  </div>
</body>
</html>