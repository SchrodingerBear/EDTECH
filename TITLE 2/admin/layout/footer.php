<?php
/**
 * Lavadora — admin layout: closing shell.
 */
$__role = current_role();
$__acc = $_SESSION['user']['page_access'] ?? null;
$__can = fn($k) => $__acc === null || in_array($k, $__acc, true);
?>
  </main>

  <!-- ========================= BOTTOM NAV (mobile) ========================= -->
  <?php
  $__bottom = [
    ['Dashboard', 'admin/dashboard', 'home'],
    ['Orders', 'admin/orders', 'clipboard'],
    ['Inventory', 'admin/inventory', 'layers'],
  ];
  $__activePage = $active ?? '';
  ?>
  <nav class="ia-bottomnav d-lg-none" aria-label="Bottom navigation">
    <?php foreach ($__bottom as [$__label, $__href, $__icon]):
      $__isActive = $__activePage === $__label;
      ?>
      <a class="bn-item <?= $__isActive ? 'active' : '' ?>" href="<?= url($__href) ?>">
        <span class="bn-icon"><?= ia_icon($__icon, 22) ?></span>
        <span class="bn-label"><?= h($__label) ?></span>
      </a>
    <?php endforeach; ?>
    <a class="bn-item <?= $__activePage === 'More' ? 'active' : '' ?>" href="#" data-bn-more>
      <span class="bn-icon"><?= ia_icon('menu', 22) ?></span>
      <span class="bn-label">More</span>
    </a>
  </nav>

  <?php // More menu: role-aware
  $__moreGroups = role_nav($__role);
  $__moreItems = [];
  foreach ($__moreGroups as $__grp) {
    foreach (($__grp['items'] ?? []) as $__it) {
      $__href = $__it[1] ?? '';
      $__label = $__it[0] ?? '';
      if ($__href === 'admin/dashboard' || $__href === 'admin/orders' || $__href === 'admin/inventory') continue;
      $__moreItems[] = ['label' => $__label, 'href' => $__href, 'icon' => $__it[2] ?? 'home', 'page' => $__it[3] ?? null];
    }
  }
  ?>
    <div class="ia-bn-more d-lg-none" id="ia-bn-more" hidden>
      <div class="ia-bn-head">
        <span class="fw-bold">More options</span>
        <button type="button" class="btn btn-sm btn-outline-ia" data-bn-close><?= ia_icon('arrow-left', 15) ?> Close</button>
      </div>
      <div class="ia-bn-list">
        <?php foreach ($__moreItems as $__it):
          if ($__it['page'] && !$__can($__it['page'])) continue; ?>
          <a class="ia-bn-item" href="<?= url($__it['href']) ?>">
            <span class="ia-icon"><?= ia_icon($__it['icon'], 20) ?></span>
            <span><?= h($__it['label']) ?></span>
          </a>
        <?php endforeach; ?>
        <hr class="my-2">
        <a class="ia-bn-item" href="<?= url('admin/profile') ?>"><span class="ia-icon"><?= ia_icon('user', 20) ?></span><span>My profile</span></a>
        <a class="ia-bn-item text-danger" href="<?= url('admin/logout') ?>"><span class="ia-icon"><?= ia_icon('logout', 20) ?></span><span>Sign out</span></a>
      </div>
    </div>
    <div class="ia-bn-backdrop d-lg-none" data-bn-close></div>
</div>

  <!-- Offline status banner (managed by app.js) -->
  <div id="lav-offline-banner">⚠️ You are offline — all changes saved locally and will auto-sync when online.</div>
  <div id="lav-sync-badge" title="Unsynced changes"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script src="<?= url('admin/assets/js/dashboard.js') ?>"></script>
<!-- Offline-First App Boot (ES Module) -->
<script type="module" src="<?= url('assets/js/app.js') ?>"></script>
<script>
  // Show offline badge with unsynced count when lavadora is ready
  document.addEventListener('lavadora:ready', () => {
    const banner = document.getElementById('lav-offline-banner');
    const badge = document.getElementById('lav-sync-badge');

    async function refreshBadge() {
      if (!window.lavadora?.orders) return;
      const stats = await window.lavadora.orders.getDashboardStats();
      if (stats.unsynced > 0) {
        badge.textContent = `⏳ ${stats.unsynced} unsynced`;
        badge.style.display = 'block';
      } else {
        badge.style.display = 'none';
      }
    }

    // Update badge every 10 seconds
    refreshBadge();
    setInterval(refreshBadge, 10000);

    // Online/Offline banner
    window.lavadora.detector.onStatusChange((isOnline) => {
      banner.style.display = isOnline ? 'none' : 'block';
      if (isOnline) refreshBadge();
    });
    if (!window.lavadora.detector.isOnline()) banner.style.display = 'block';
  });

  // Service Worker registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('<?= url('sw.js') ?>')
        .then(r => console.log('[SW] Registered:', r.scope))
        .catch(e => console.warn('[SW] Registration failed:', e));
    });
  }
</script>
</body>
</html>
