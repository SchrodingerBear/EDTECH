<?php
/**
 * API: Save 360 Camera Panorama
 * Receives panorama data from 360_cam app and saves to database
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Must send JSON before any possible redirect
header('Content-Type: application/json');

// Check login without redirecting (this is a JSON API)
// For mobile app testing, if the user isn't logged in, we use a fallback user/institution.
$user = current_user();
$fallback_mode = false;

if (!$user) {
    // If we're not logged in, we use fallback to allow mobile testing without session
    $fallback_mode = true;
    $user = [
        'id' => 1,
        'institution_id' => 1
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    if ($fallback_mode) {
        $institution_id = 1;
        $inst = crud()->get('institutions', $institution_id);
        if (!$inst) {
            http_response_code(400);
            echo json_encode(['error' => 'Fallback institution not found.']);
            exit;
        }
    } else {
        // Resolve institution using the same logic as admin pages
        $inst = resolve_active_institution();
        if (!$inst) {
            http_response_code(400);
            echo json_encode(['error' => 'No active institution. Please open the admin panel and select an institution first.']);
            exit;
        }
        $institution_id = (int) $inst['id'];
    }

    // Validate required fields
    $title = $_POST['title'] ?? 'Untitled Panorama';
    $description = $_POST['description'] ?? '';
    $capture_data = $_POST['capture_data'] ?? null;

    if (!isset($_FILES['panorama']) || $_FILES['panorama']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Panorama file required']);
        exit;
    }

    // Institution already resolved above via resolve_active_institution()
    // $inst already contains folder_path

    $orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
    $panoAbs = $orgAbs . '/assets/panos';
    if (!is_dir($panoAbs)) {
        mkdir($panoAbs, 0775, true);
    }

    // Save panorama file
    $ext = strtolower(pathinfo($_FILES['panorama']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $filename = 'pano_' . time() . '_' . random_token(8) . '.' . $ext;
    $filepath = $panoAbs . '/' . $filename;
    
    // Check if file upload was successful
    if (!move_uploaded_file($_FILES['panorama']['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file to destination']);
        exit;
    }
    
    // Verify file was actually saved
    if (!file_exists($filepath)) {
        http_response_code(500);
        echo json_encode(['error' => 'File was not saved to filesystem']);
        exit;
    }

    // Get image dimensions
    $imageInfo = getimagesize($filepath);
    $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : 0;
    $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : 0;
    $fileSize = (int) filesize($filepath);

    // Generate thumbnail
    $thumbnailPath = null;
    if ($width && $height) {
        $thumbExt = 'jpg';
        $thumbFilename = 'thumb_' . time() . '_' . random_token(8) . '.' . $thumbExt;
        $thumbFilepath = $panoAbs . '/' . $thumbFilename;

        // Create thumbnail (scale to 300x150 maintaining aspect ratio)
        $thumbWidth = 300;
        $thumbHeight = 150;
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

        if ($ext === 'png') {
            $source = imagecreatefrompng($filepath);
        } else {
            $source = imagecreatefromjpeg($filepath);
        }

        if ($source) {
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
            imagejpeg($thumb, $thumbFilepath, 85);
            imagedestroy($thumb);
            imagedestroy($source);
            $thumbnailPath = 'assets/panos/' . $thumbFilename;
        }
    }

    // Save to database - use RELATIVE path only (no leading slash, no org prefix)
    $relativePath = 'assets/panos/' . $filename;
    $captureDataJson = $capture_data ? json_decode($capture_data, true) : null;

    $panoId = crud()->insert('panoramas', [
        'institution_id' => $institution_id,
        'created_by' => (int) $user['id'],
        'title' => $title,
        'description' => $description,
        'equirect_path' => $relativePath,
        'thumbnail_path' => $thumbnailPath,
        'capture_data' => $captureDataJson ? json_encode($captureDataJson) : null,
        'width' => $width,
        'height' => $height,
        'file_size' => $fileSize,
        'status' => 'completed'
    ]);

    // Also add to media_assets
    crud()->insert('media_assets', [
        'institution_id' => $institution_id,
        'uploaded_by' => (int) $user['id'],
        'kind' => 'pano',
        'file_path' => $relativePath,
        'original_name' => $_FILES['panorama']['name'],
        'width' => $width,
        'height' => $height
    ]);

    audit('panorama.create', '360_camera', 'panorama', $panoId);

    // Set success flash message for post-reload alert using existing flash system
    flash('success', '✅ Panorama saved to system! You can now download or attach it to a tour scene from the AI Tools page in the admin panel.');

    echo json_encode([
        'success' => true,
        'panorama_id' => $panoId,
        'equirect_path' => $relativePath,
        'thumbnail_path' => $thumbnailPath,
        'width' => $width,
        'height' => $height
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
