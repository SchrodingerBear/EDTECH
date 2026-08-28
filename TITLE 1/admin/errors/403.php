<?php require_once __DIR__ . '/../../includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 Forbidden · <?= h(APP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link href="<?= url('admin/assets/css/dashboard.css') ?>" rel="stylesheet">
  <style>body{display:grid;place-items:center;min-height:100vh}</style>
</head>
<body>
  <div style="text-align:center;max-width:420px;padding:20px">
    <div class="ia-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:30px">403</div>
    <h1 style="font-weight:800;letter-spacing:-.02em">Access denied</h1>
    <p class="text-muted" style="font-size:14px">Your role doesn't have permission to view this area.</p>
    <a class="btn btn-grad px-4" href="<?= url('admin/index') ?>">Back to dashboard</a>
  </div>
</body>
</html>