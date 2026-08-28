<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_staff();
require_page('owner.files', 'system.files');
/**
 * Innovatech PH — owner file manager (organizations/).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'File Manager';
$pageSub = 'Media and project files across all institution folders';
$active = 'File Manager';

$tab = $_GET['tab'] ?? 'organizations';

if ($tab === 'assets') {
    $fmRoot = ROOT_PATH . '/assets';
    $fmRootUrl = BASE_URL . '/assets';
} elseif ($tab === 'public') {
    $fmRoot = ROOT_PATH . '/public';
    $fmRootUrl = BASE_URL . '/public';
} else {
    $fmRoot = ORG_ROOT;
    $fmRootUrl = ORG_ROOT_URL;
}

?>
<div class="mb-3 d-flex gap-3 border-bottom pb-2">
    <a href="?tab=organizations" class="text-decoration-none <?= $tab === 'organizations' ? 'fw-bold text-body border-bottom border-2 border-primary' : 'text-muted' ?>">Institutions</a>
    <a href="?tab=assets" class="text-decoration-none <?= $tab === 'assets' ? 'fw-bold text-body border-bottom border-2 border-primary' : 'text-muted' ?>">Core Assets</a>
    <a href="?tab=public" class="text-decoration-none <?= $tab === 'public' ? 'fw-bold text-body border-bottom border-2 border-primary' : 'text-muted' ?>">Public Media</a>
</div>
<?php

require __DIR__ . '/../partials/file-explorer.php';

require __DIR__ . '/../layout/footer.php';