<?php
/**
 * Fix directory permissions for panorama uploads
 * This script runs as the web server user and can fix permissions
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Load config without auth dependency
    if (!file_exists(__DIR__ . '/includes/config.php')) {
        echo json_encode([
            'success' => false,
            'error' => 'Config file not found: ' . __DIR__ . '/includes/config.php'
        ]);
        exit;
    }
    
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
    
    $results = [];
    $results['root_path'] = ROOT_PATH;
    $results['org_path'] = $orgAbs;
    $results['pano_path'] = $panoAbs;
    
    // Try to fix permissions
    $results['before_org_writable'] = is_writable($orgAbs);
    $results['before_pano_writable'] = is_dir($panoAbs) ? is_writable($panoAbs) : false;
    
    // Try to make directories writable
    $orgFixed = @chmod($orgAbs, 0777);
    $results['chmod_org'] = $orgFixed;
    $results['chmod_org_error'] = $orgFixed ? false : error_get_last()['message'] ?? 'Unknown error';
    
    if (is_dir($panoAbs)) {
        $panoFixed = @chmod($panoAbs, 0777);
        $results['chmod_pano'] = $panoFixed;
        $results['chmod_pano_error'] = $panoFixed ? false : error_get_last()['message'] ?? 'Unknown error';
    } else {
        // Create directory with proper permissions
        $created = @mkdir($panoAbs, 0777, true);
        $results['created_pano'] = $created;
        $results['mkdir_error'] = $created ? false : error_get_last()['message'] ?? 'Unknown error';
        if ($created) {
            $panoFixed = @chmod($panoAbs, 0777);
            $results['chmod_pano'] = $panoFixed;
        }
    }
    
    $results['after_org_writable'] = is_writable($orgAbs);
    $results['after_pano_writable'] = is_dir($panoAbs) ? is_writable($panoAbs) : false;
    
    $results['org_perms'] = is_dir($orgAbs) ? substr(sprintf('%o', fileperms($orgAbs)), -4) : 'N/A';
    $results['pano_perms'] = is_dir($panoAbs) ? substr(sprintf('%o', fileperms($panoAbs)), -4) : 'N/A';
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'summary' => $results['after_pano_writable'] ? 'Permissions fixed successfully' : 'Failed to fix permissions'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}