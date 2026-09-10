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

<script>
// Handle flash message dismissal and URL cleanup
document.addEventListener('DOMContentLoaded', function() {
    // Check for flash messages in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('flash_type') || urlParams.has('flash_message')) {
        // Clean up URL after a short delay
        setTimeout(() => {
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 3000);
    }
    
    // Handle alert dismissals
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const closeBtn = alert.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            });
        }
    });
});
</script>

</body>
</html>