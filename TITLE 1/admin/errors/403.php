<?php require_once __DIR__ . '/../../includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 Forbidden · <?= h(APP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link href="<?= url('admin/assets/css/dashboard.css') ?>" rel="stylesheet">
</head>
<body class="page-error">
  <div class="error-box">
    <div class="ia-avatar mx-auto mb-3 ia-avatar-xl">403</div>
    <h1 class="fw-800 ls-tight">Access denied</h1>
    <p class="text-muted fs-14">Your role doesn't have permission to view this area.</p>
    <a class="btn btn-grad px-4" href="<?= url('admin/index') ?>">Back to dashboard</a>
  </div>
</body>
</html>