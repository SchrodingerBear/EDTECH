<?php
/**
 * Admin layout — closing shell.
 */
?>
  </main>
</div>

<script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/simple-datatables.min.js') ?>"></script>
<script src="<?= url('admin/assets/js/dashboard.js') ?>"></script>
<script src="<?= url('admin/assets/js/ia-ai-gen.js') ?>"></script>

<!-- PWA Offline Support -->
<script src="<?= url('assets/js/offline-db.js') ?>"></script>
<script src="<?= url('assets/js/sync-manager.js') ?>"></script>
<script>
// Register Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then((registration) => {
        console.log('[PWA] Service Worker registered:', registration.scope);
      })
      .catch((error) => {
        console.log('[PWA] Service Worker registration failed:', error);
      });
  });
}

// Add offline indicator to page
function addOfflineIndicator() {
  const indicator = document.createElement('div');
  indicator.id = 'offline-indicator';
  indicator.className = 'd-none position-fixed top-0 left-0 right-0 bg-warning text-dark text-center py-2 fw-bold';
  indicator.style.zIndex = '9999';
  indicator.innerHTML = '<i class="fas fa-wifi me-2"></i>You\'re offline - changes will sync when reconnected';
  document.body.appendChild(indicator);
}

// Add offline indicator on load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', addOfflineIndicator);
} else {
  addOfflineIndicator();
}
</script>
</body>
</html>