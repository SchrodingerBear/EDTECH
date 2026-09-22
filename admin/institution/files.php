<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.files');
/**
 * Innovatech PH — admin: your institution's project folder (visual file explorer).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Files';
$pageSub = 'Project folder for this institution';
$active = 'Files';

$inst = resolve_active_institution();
$fmRoot = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
$fmRootUrl = url(trim($inst['folder_path'], '/'));

require __DIR__ . '/../partials/file-explorer.php';
require __DIR__ . '/../layout/footer.php';