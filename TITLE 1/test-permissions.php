<?php
/**
 * Test script to check directory permissions for panorama uploads
 */
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/functions.php';
    
    // Get institution info (fallback to ID 1)
    $institution_id = 1;
    $inst = crud()->get('institutions', $institution_id);
    
    if (!$inst) {
        echo json_encode([
            'success' => false,
            'error' => 'Institution not found'
        ]);
        exit;
    }
    
    $orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
    $panoAbs = $orgAbs . '/assets/panos';
    
    $checks = [
        'root_path' => ROOT_PATH,
        'org_path' => $orgAbs,
        'pano_path' => $panoAbs,
        'org_exists' => is_dir($orgAbs),
        'pano_exists' => is_dir($panoAbs),
        'org_writable' => is_writable($orgAbs),
        'pano_writable' => is_dir($panoAbs) ? is_writable($panoAbs) : false,
        'parent_writable' => is_writable($orgAbs),
        'php_upload_max' => ini_get('upload_max_filesize'),
        'php_post_max' => ini_get('post_max_size'),
        'php_memory_limit' => ini_get('memory_limit'),
        'current_user' => get_current_user(),
        'file_perms_org' => is_dir($orgAbs) ? substr(sprintf('%o', fileperms($orgAbs)), -4) : 'N/A',
        'file_perms_pano' => is_dir($panoAbs) ? substr(sprintf('%o', fileperms($panoAbs)), -4) : 'N/A'
    ];
    
    // Try to create the directory if it doesn't exist
    if (!is_dir($panoAbs)) {
        $created = mkdir($panoAbs, 0775, true);
        $checks['created_pano_dir'] = $created;
        $checks['pano_exists_after'] = is_dir($panoAbs);
        $checks['pano_writable_after'] = is_writable($panoAbs);
    }
    
    echo json_encode([
        'success' => true,
        'checks' => $checks,
        'summary' => $checks['pano_writable'] || $checks['pano_writable_after'] ? 'Directory is writable' : 'Directory is NOT writable'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}