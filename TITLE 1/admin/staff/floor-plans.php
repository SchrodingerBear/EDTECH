<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.floorplans');
$pageTitle = 'Floor Plans';
$pageSub = 'Upload campus maps and drop markers (percentages keep them responsive)';
$active = 'Floor Plans';
$bodyClass = 'page-staff-floorplans';
require_once __DIR__ . '/../layout/header.php';

$inst = current_institution();
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];
$orgDir = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/floorplans';
$planId = (int) ($_GET['plan'] ?? 0);

require __DIR__ . '/floor-plans/actions.php';
require __DIR__ . '/floor-plans/data-loader.php';

if ($plan): 
    require __DIR__ . '/floor-plans/studio-view.php';
    echo '<script>';
    require __DIR__ . '/floor-plans/studio.js';
    echo '</script>';
else:
    require __DIR__ . '/floor-plans/list-view.php';
endif;

require __DIR__ . '/../layout/footer.php';
