<?php require_once __DIR__ . '/../../includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 Forbidden · <?= h(APP_NAME) ?></title>
  <link href="../../assets/css/fonts.css" rel="stylesheet">
  <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="page-error">
  <div class="error-box">
    <div class="ia-avatar mx-auto mb-3 ia-avatar-xl">403</div>
    <h1 class="fw-800 ls-tight">Access denied</h1>
    <p class="text-muted fs-14">Your role doesn't have permission to view this area.</p>
    <a class="btn btn-grad px-4" href="../index.php">Back to dashboard</a>
  </div>
</body>
</html>
