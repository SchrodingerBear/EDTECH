<?php
/**
 * Admin layout — opening shell (topbar + sidebar). Page sets:
 *   $pageTitle, $pageSub (optional), $active (nav item label)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/nav.php';

$u = current_user();
$role = current_role() ?? 'user';
$inst = current_institution();
$active = $active ?? '';
$flashData = pull_flash();

$roleHome = match ($role) {
  'owner' => 'admin/owner/dashboard',
  'system_admin', 'system_staff' => 'admin/system/dashboard',
  'admin' => 'admin/institution/dashboard',
  'staff' => 'admin/staff/dashboard',
  default => 'admin/index',
};
$roleLabel = ucwords(str_replace('_', ' ', (string) $role));

$platform_settings = crud()->get('platform_settings', 1) ?? [];
$brandLogoUrl = !empty($platform_settings['logo_path']) ? url($platform_settings['logo_path']) : url('assets/logo2.webp');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title><?= h($pageTitle ?? 'Dashboard') ?> · <?= h(APP_NAME) ?></title>
  <link rel="icon" href="<?= url('public/icon.svg') ?>" type="image/svg+xml">
  <meta name="theme-color" content="#4f46e5">
  <link href="<?= url('assets/css/inter-font.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/simple-datatables.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/css/font-awesome.min.css') ?>">
  <link rel="stylesheet" href="<?= url('admin/assets/css/dashboard.css') ?>">
  <script src="<?= url('assets/js/sweetalert2.min.js') ?>"></script>
  <script>window.IA_BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;</script>
  <?= $extraHead ?? '' ?>
</head>

<body class="<?= h(trim((string) ($bodyClass ?? ''))) ?>">

  <div class="ia-shell" id="ia-shell">

    <div class="ia-backdrop"></div>

    <!-- ============================== SIDEBAR ============================== -->
    <aside class="ia-sidebar">
      <div class="brand">
        <a class="brand-logo" href="<?= url($roleHome) ?>">
          <img src="<?= h($brandLogoUrl) ?>" alt="Innovatech PH">
        </a>
        <div class="brand-name"><?= h(APP_NAME) ?><small><?= h($roleLabel) ?></small></div>
      </div>

      <nav class="nav-scroll">
        <?php foreach (role_nav($role) as $group):
          if (isset($group[0]) && is_array($group[0]) && array_is_list($group)): ?>
            <?php $label = $group[0];
            $links = array_slice($group, 1); ?>
            <p class="ia-nav-label"><?= h($label) ?></p>
            <ul class="ia-nav">
              <?php foreach ($links as [$text, $href, $icon]): ?>
                <li>
                  <a class="nav-link <?= $active === $text ? 'active' : '' ?>" href="<?= url($href) ?>">
                    <span class="ia-icon"><?= ia_icon($icon) ?></span>
                    <span><?= h($text) ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php elseif (isset($group[0]) && is_string($group[0])): ?>
            <?php [$text, $href, $icon] = $group; ?>
            <p class="ia-nav-label"><?= h($text) ?></p>
            <ul class="ia-nav">
              <li>
                <a class="nav-link <?= $active === $text ? 'active' : '' ?>" href="<?= url($href) ?>">
                  <span class="ia-icon"><?= ia_icon($icon) ?></span>
                  <span><?= h($text) ?></span>
                </a>
              </li>
            </ul>
          <?php elseif (isset($group['title'])): ?>
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
        <div class="foot-text">
          <?= h($roleLabel) ?> workspace · v1.0
        </div>
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
        <?= ia_icon('menu') ?>
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

      <?php if (in_array($role, ['owner', 'system_admin'])): ?>
        <form method="post" action="<?= url('admin/owner/clear-cache.php') ?>" class="d-none d-md-block m-0">
          <button type="submit" class="ia-btn-ghost" title="Clear Cache" aria-label="Clear Cache">
            <?= ia_icon('refresh') ?: ia_icon('sun') ?>
          </button>
        </form>
      <?php endif; ?>

      <button type="button" class="theme-switch" data-theme-toggle aria-label="Toggle theme"
        title="Toggle dark / light">
        <span class="knob"><?= ia_icon('moon', 13) ?></span>
      </button>

      <?php if ($inst): ?>
        <a class="d-none d-md-flex align-items-center gap-2 px-3 py-2 rounded-4 surface-line"
          href="<?= h(org_url($inst['slug'])) ?>" target="_blank" title="<?= h($inst['name']) ?>">
          <span class="badge badge-live">LIVE</span>
          <span class="fs-13 fw-semibold"><?= h($inst['short_name'] ?: $inst['name']) ?></span>
        </a>
      <?php endif; ?>

      <div class="dropdown">
        <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
          <span
            class="ia-avatar"><?= h(mb_substr(trim(($u['first_name'][0] ?? '') . ($u['last_name'][0] ?? '')), 0, 2)) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text fw-bold text-ia-text"><?= h(display_name($u)) ?></span></li>
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