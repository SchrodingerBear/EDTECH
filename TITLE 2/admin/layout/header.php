<?php
/**
 * Lavadora — admin layout: opening shell (topbar + sidebar).
 * Page sets: $pageTitle, $pageSub (optional), $active (nav item label)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/nav.php';

$u = current_user();
$role = current_role() ?? 'user';
$active = $active ?? '';
$flashData = pull_flash();

$roleHome = role_home($role);
$roleLabel = ucwords(str_replace('_', ' ', (string) $role));

$settings = crud()->get('settings', 1) ?? [];
$brandLogoUrl = !empty($settings['logo_path']) ? url($settings['logo_path']) : url('admin/assets/img/logo.svg');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title><?= h($pageTitle ?? 'Dashboard') ?> · <?= h(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('admin/assets/css/dashboard.css') ?>">
  <script>window.IA_BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;</script>
</head>

<body class="<?= h(trim((string) ($bodyClass ?? ''))) ?>">

  <div class="ia-shell" id="ia-shell">

    <div class="ia-backdrop"></div>

    <!-- ============================== SIDEBAR ============================== -->
    <aside class="ia-sidebar">
      <div class="brand">
        <a class="brand-logo" href="<?= url($roleHome) ?>">
          <img src="<?= h($brandLogoUrl) ?>" alt="<?= h(APP_NAME) ?>">
        </a>
        <div class="brand-name"><?= h(APP_NAME) ?><small><?= h($roleLabel) ?></small></div>
      </div>

      <nav class="nav-scroll">
        <?php foreach (role_nav($role) as $group):
          if (isset($group['title'])): ?>
            <p class="ia-nav-label"><?= h($group['title']) ?></p>
            <ul class="ia-nav">
              <?php foreach ($group['items'] as [$text, $href, $icon]): ?>
                <li>
                  <a class="nav-link <?= $active === $text ? 'active' : '' ?>" href="<?= url($href) ?>">
                    <span class="ia-icon"><?= ia_icon($icon) ?></span>
                    <span><?= h($text) ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; endforeach; ?>
      </nav>

      <div class="side-foot">
        <div class="foot-text"><?= h($roleLabel) ?> workspace · v1.0</div>
        <a class="nav-link" href="<?= url('admin/logout') ?>">
          <span class="ia-icon"><?= ia_icon('logout') ?></span>
          <span>Sign out</span>
        </a>
      </div>
    </aside>

    <!-- ============================== TOPBAR ============================== -->
    <header class="ia-topbar">
      <button type="button" class="ia-btn-ghost d-lg-none" id="sidebar-open" aria-label="Open menu">
        <?= ia_icon('menu') ?>
      </button>
      <button type="button" class="ia-btn-ghost d-none d-lg-grid" id="sidebar-collapse" aria-label="Collapse sidebar">
        <?= ia_icon('home') ?>
      </button>

      <div class="flex-grow-1">
        <h1 class="page-title"><?= h($pageTitle ?? 'Dashboard') ?></h1>
        <?php if (!empty($pageSub)): ?>
          <p class="page-sub mb-0 d-none d-sm-block"><?= h($pageSub) ?></p><?php endif; ?>
      </div>

      <a class="ia-btn-ghost" href="<?= url('/') ?>" target="_blank" title="View public landing"
        aria-label="View landing">
        <?= ia_icon('globe') ?>
      </a>

      <button type="button" class="theme-switch" data-theme-toggle aria-label="Toggle theme"
        title="Toggle dark / light">
        <span class="knob"><?= ia_icon('moon', 13) ?></span>
      </button>

      <div class="dropdown">
        <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
          <span
            class="ia-avatar"><?= h(strtoupper(mb_substr(trim(($u['first_name'][0] ?? '') . ($u['last_name'][0] ?? '')), 0, 2))) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text fw-bold"><?= h(display_name($u)) ?></span></li>
          <li><span class="dropdown-item-text small text-ia-muted"><?= h($u['email']) ?> ·
              <?= h($u['role_name']) ?></span></li>
          <li>
            <hr class="dropdown-divider">
          </li>
          <li><a class="dropdown-item" href="<?= url('admin/profile') ?>"><?= ia_icon('user', 15) ?> My profile</a></li>
          <li><a class="dropdown-item text-danger" href="<?= url('admin/logout') ?>"><?= ia_icon('logout', 15) ?> Sign
              out</a></li>
        </ul>
      </div>
    </header>

    <main class="ia-main">
      <script type="application/json" id="flash-data"><?= json_enc($flashData) ?></script>