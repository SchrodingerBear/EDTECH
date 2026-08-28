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

$fmRoot = ROOT_PATH . '/' . trim(resolve_active_institution()['folder_path'], '/');
$fmRootUrl = org_url(resolve_active_institution()['slug'], '');
require __DIR__ . '/../partials/file-explorer.php';
require __DIR__ . '/../layout/footer.php';