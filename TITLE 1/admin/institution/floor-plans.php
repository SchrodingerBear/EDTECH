<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.floorplans');
$pageTitle = 'Floor Plans';
$pageSub = 'Upload the campus map and drop circular markers (drag & drop studio)';
$active = 'Floor Plans';
$bodyClass = 'page-floor-plans';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];
$orgDir = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/floorplans';

$studioPlanId = (int) ($_GET['studio'] ?? 0);
$filterBuildingId = (int) ($_GET['building'] ?? 0);

require __DIR__ . '/floor plan/actions.php';
require __DIR__ . '/floor plan/data-loader.php';

if ($studio): 
    require __DIR__ . '/floor plan/studio-view.php';
    echo '<script>';
    require __DIR__ . '/floor plan/studio.js';
    echo '</script>';
else:
    require __DIR__ . '/floor plan/list-view.php';
endif;

require __DIR__ . '/../layout/footer.php';
