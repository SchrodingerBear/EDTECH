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
    ['Dashboard', 'dashboard', 'home'],
    ['Orders', 'orders', 'clipboard'],
    ['Inventory', 'inventory', 'layers'],
  ];
  $__activePage = $active ?? '';
  ?>
  <nav class="ia-bottomnav d-lg-none" aria-label="Bottom navigation">
    <?php foreach ($__bottom as [$__label, $__href, $__icon]):
      $__isActive = $__activePage === $__label;
      ?>
      <a class="bn-item <?= $__isActive ? 'active' : '' ?>" href="<?= $__href ?>.php">
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
      if ($__href === 'dashboard' || $__href === 'orders' || $__href === 'inventory') continue;
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
          <a class="ia-bn-item" href="<?= $__it['href'] ?>.php">
            <span class="ia-icon"><?= ia_icon($__it['icon'], 20) ?></span>
            <span><?= h($__it['label']) ?></span>
          </a>
        <?php endforeach; ?>
        <hr class="my-2">
        <a class="ia-bn-item" href="profile.php"><span class="ia-icon"><?= ia_icon('user', 20) ?></span><span>My profile</span></a>
        <a class="ia-bn-item text-danger" href="logout.php"><span class="ia-icon"><?= ia_icon('logout', 20) ?></span><span>Sign out</span></a>
      </div>
    </div>
    <div class="ia-bn-backdrop d-lg-none" data-bn-close></div>
</div>

  <!-- Offline status banner (managed by app.js) -->
  <div id="lav-offline-banner">⚠️ You are offline — all changes saved locally and will auto-sync when online.</div>
  <div id="lav-sync-badge" title="Unsynced changes"></div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/simple-datatables.min.js"></script>
<script src="../assets/js/admin-dashboard.js"></script>
<!-- Offline-First App Boot (ES Module) -->
<script type="module" src="../assets/js/app.js"></script>
<script>
  // Show offline badge with unsynced count when lavadora is ready
  document.addEventListener('lavadora:ready', () => {
    const banner = document.getElementById('lav-offline-banner');
    const badge = document.getElementById('lav-sync-badge');
    const statusEl = document.getElementById('online-status');
    const statusDot = document.getElementById('status-dot');
    const statusText = document.getElementById('status-text');

    function updateStatusUI(isOnline) {
      if (statusEl) statusEl.style.display = 'flex';
      if (statusDot) {
        statusDot.style.background = isOnline ? '#28a745' : '#dc3545';
        statusDot.style.boxShadow = isOnline ? '0 0 8px #28a745' : '0 0 8px #dc3545';
      }
      if (statusText) statusText.textContent = isOnline ? 'Online' : 'Offline';
      if (banner) banner.style.display = isOnline ? 'none' : 'block';
    }

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

    // Online/Offline status
    window.lavadora.detector.onStatusChange((isOnline) => {
      updateStatusUI(isOnline);
      if (isOnline) refreshBadge();
    });
    // Initial state
    updateStatusUI(window.lavadora.detector.isOnline());
  });

  // Service Worker registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('../sw.js')
        .then(r => console.log('[SW] Registered:', r.scope))
        .catch(e => console.warn('[SW] Registration failed:', e));
    });
  }
// Bottom nav "More" menu handlers
  document.addEventListener('click', function(e) {
    const moreBtn = e.target.closest('[data-bn-more]');
    const closeBtn = e.target.closest('[data-bn-close]');
    const moreMenu = document.getElementById('ia-bn-more');
    const backdrop = document.querySelector('.ia-bn-backdrop');
    
    if (moreBtn) {
      e.preventDefault();
      moreMenu.hidden = false;
      backdrop.hidden = false;
      document.body.style.overflow = 'hidden';
    }
    
    if (closeBtn || (e.target === backdrop && moreMenu && !moreMenu.hidden)) {
      moreMenu.hidden = true;
      backdrop.hidden = true;
      document.body.style.overflow = '';
    }
  });

  // Close on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const moreMenu = document.getElementById('ia-bn-more');
      const backdrop = document.querySelector('.ia-bn-backdrop');
      if (moreMenu && !moreMenu.hidden) {
        moreMenu.hidden = true;
        backdrop.hidden = true;
        document.body.style.overflow = '';
      }
    }
  });
</script>
</body>
</html>
