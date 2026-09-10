<?php
/**
 * Test script to check existing panorama files
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
    
    // Check database for panoramas
    $panoramasResult = crud()->select('panoramas', '*', ['institution_id' => $institution_id], 'ORDER BY created_at DESC LIMIT 10');
    $panoramas = is_array($panoramasResult) ? $panoramasResult : $panoramasResult->fetchAll();
    
    // Check actual files in directory
    $files = [];
    if (is_dir($panoAbs)) {
        $fileList = scandir($panoAbs);
        foreach ($fileList as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $panoAbs . '/' . $file;
                $files[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'exists' => file_exists($filePath)
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'database_panoramas' => count($panoramas),
        'panoramas' => $panoramas,
        'directory_files' => count($files),
        'files' => $files,
        'directory_path' => $panoAbs,
        'directory_exists' => is_dir($panoAbs)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}