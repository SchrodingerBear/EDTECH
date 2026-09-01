<?php
/**
 * API: Save 360 Camera Panorama
 * Receives panorama data from 360_cam app and saves to database
 */
require_once __DIR__ . '/../includes/auth.php';
require_api_auth();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Get institution from user's active institution
    $institution_id = (int) ($user['active_institution_id'] ?? 0);
    if (!$institution_id) {
        http_response_code(400);
        echo json_encode(['error' => 'No active institution']);
        exit;
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

    // Get institution folder
    $inst = crud()->select('institutions', 'folder_path', ['id' => $institution_id])->fetch();
    if (!$inst) {
        http_response_code(404);
        echo json_encode(['error' => 'Institution not found']);
        exit;
    }

    $orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
    $panoAbs = $orgAbs . '/assets/panos';
    if (!is_dir($panoAbs)) {
        mkdir($panoAbs, 0775, true);
    }

    // Save panorama file
    $ext = strtolower(pathinfo($_FILES['panorama']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $filename = 'pano_' . time() . '_' . random_token(8) . '.' . $ext;
    $filepath = $panoAbs . '/' . $filename;
    move_uploaded_file($_FILES['panorama']['tmp_name'], $filepath);

    // Get image dimensions
    $imageInfo = getimagesize($filepath);
    $width = $imageInfo[0] ?? null;
    $height = $imageInfo[1] ?? null;
    $fileSize = filesize($filepath);

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
