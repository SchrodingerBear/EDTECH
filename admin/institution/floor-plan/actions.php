<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../ar-helpers.php';
require_admin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$inst = resolve_active_institution();
if (!$inst) {
    echo json_encode(['success' => false, 'error' => 'No active institution']);
    exit;
}
$iid = (int) $inst['id'];

if ($action === 'create_floor_plan') {
    $title = $_POST['title'] ?? '';
    $building_id = !empty($_POST['building_id']) ? (int)$_POST['building_id'] : null;
    $floor_level = $_POST['floor_level'] ?? '';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }

    // Media picker sends either a file upload or a path selected from library
    $uploadedFile = $_FILES['fp_image_upload'] ?? null;
    $selectedUrl  = trim($_POST['fp_image_url'] ?? '');
    $hasUpload    = $uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK;
    $hasUrl       = $selectedUrl !== '';

    if (!$hasUpload && !$hasUrl) {
        echo json_encode(['success' => false, 'error' => 'A floor plan image is required']);
        exit;
    }

    $relPath = '';
    $width   = 0;
    $height  = 0;

    if ($hasUpload) {
        // Process uploaded file
        $file = $uploadedFile;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format. Allowed: jpg, png, webp.']);
            exit;
        }
        $size = getimagesize($file['tmp_name']);
        if (!$size) {
            echo json_encode(['success' => false, 'error' => 'Invalid image file']);
            exit;
        }
        $width  = $size[0];
        $height = $size[1];
        $folderPath = rtrim($inst['folder_path'], '/');
        $uploadDir  = ROOT_PATH . '/' . $folderPath . '/assets/floorplans';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $filename = uniqid() . '.' . $ext;
        $relPath  = $folderPath . '/assets/floorplans/' . $filename;
        $absPath  = ROOT_PATH . '/' . $relPath;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
            exit;
        }
    } else {
        // Selected from library — resolve dimensions from disk
        $relPath = ltrim($selectedUrl, '/');
        $absPath = ROOT_PATH . '/' . $relPath;
        if (file_exists($absPath)) {
            $size   = @getimagesize($absPath);
            $width  = $size ? $size[0] : 0;
            $height = $size ? $size[1] : 0;
        }
    }

    $aspect_ratio = $height > 0 ? $width / $height : 1;

    // Default to not campus landing
    $isCampusLanding = 0;
    if ($building_id === null) {
        // Find if there is an existing campus floor plan
        $existing = crud()->raw('SELECT id FROM floor_plans WHERE institution_id = :iid AND is_campus_landing = 1 AND deleted_at IS NULL', [':iid' => $iid])->fetch();
        if (!$existing) {
            $isCampusLanding = 1;
        }
    }

    try {
        $fpId = crud()->insert('floor_plans', [
            'institution_id' => $iid,
            'building_id' => $building_id,
            'floor_level' => $floor_level,
            'title' => $title,
            'image_path' => $relPath,
            'original_width' => $width,
            'original_height' => $height,
            'aspect_ratio' => $aspect_ratio,
            'is_campus_landing' => $isCampusLanding,
            'created_by' => $_SESSION['user']['id'] ?? null
        ]);
        
        // If this is the first one, also update the institution's starting floor plan
        if ($isCampusLanding) {
            crud()->update('institutions', [
                'starting_floor_plan_id' => $fpId,
                'landing_mode' => 'floor_plan'
            ], ['id' => $iid]);
        }

        echo json_encode(['success' => true, 'floor_plan_id' => $fpId]);
    } catch (Exception $e) {
        // Clean up file if db fails
        @unlink($absPath);
        error_log("Upload failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_floor_plan') {
    $fpId = (int)($_GET['floor_plan_id'] ?? 0);
    $fp = crud()->get('floor_plans', $fpId);
    if ($fp && (int)$fp['institution_id'] === $iid) {
        echo json_encode(['success' => true, 'floor_plan' => $fp]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found']);
    }
    exit;
}

if ($action === 'edit_floor_plan') {
    $fpId = (int)($_POST['floor_plan_id'] ?? 0);
    $title = $_POST['title'] ?? '';
    $building_id = !empty($_POST['building_id']) ? (int)$_POST['building_id'] : null;
    $floor_level = $_POST['floor_level'] ?? '';
    
    $fp = crud()->get('floor_plans', $fpId);
    if (!$fp || (int)$fp['institution_id'] !== $iid) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }

    $updateData = [
        'title' => $title,
        'building_id' => $building_id,
        'floor_level' => $floor_level
    ];
    
    // Media picker sends either a new upload or a library selection
    $uploadedFile = $_FILES['fp_image_edit_upload'] ?? null;
    $selectedUrl  = trim($_POST['fp_image_edit_url'] ?? '');
    $hasUpload    = $uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK;
    $hasUrl       = $selectedUrl !== '';

    if ($hasUpload) {
        $file = $uploadedFile;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format']);
            exit;
        }
        $size = @getimagesize($file['tmp_name']);
        if ($size) {
            $updateData['original_width']  = $size[0];
            $updateData['original_height'] = $size[1];
            $updateData['aspect_ratio']    = $size[1] > 0 ? $size[0] / $size[1] : 1;
        }
        $folderPath = rtrim($inst['folder_path'], '/');
        $uploadDir  = ROOT_PATH . '/' . $folderPath . '/assets/floorplans';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $filename = uniqid() . '.' . $ext;
        $newRel   = $folderPath . '/assets/floorplans/' . $filename;
        $absPath  = ROOT_PATH . '/' . $newRel;
        if (move_uploaded_file($file['tmp_name'], $absPath)) {
            $updateData['image_path'] = $newRel;
            @unlink(ROOT_PATH . '/' . ($fp['image_path'] ?? ''));
        }
    } elseif ($hasUrl) {
        $newRel = ltrim($selectedUrl, '/');
        if ($newRel !== ($fp['image_path'] ?? '')) {
            $updateData['image_path'] = $newRel;
            $absPath = ROOT_PATH . '/' . $newRel;
            if (file_exists($absPath)) {
                $size = @getimagesize($absPath);
                if ($size) {
                    $updateData['original_width']  = $size[0];
                    $updateData['original_height'] = $size[1];
                    $updateData['aspect_ratio']    = $size[1] > 0 ? $size[0] / $size[1] : 1;
                }
            }
        }
    }
    
    try {
        crud()->update('floor_plans', $updateData, ['id' => $fpId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Edit failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_markers') {
    $fpId = (int)($_GET['floor_plan_id'] ?? 0);
    
    if (!$fpId) {
        echo json_encode(['success' => false, 'error' => 'Missing floor plan ID']);
        exit;
    }

    $markers = crud()->raw('SELECT * FROM floor_plan_markers WHERE floor_plan_id = :fpid AND institution_id = :iid ORDER BY sort_order ASC', [
        ':fpid' => $fpId,
        ':iid' => $iid
    ])->fetchAll();

    echo json_encode(['success' => true, 'markers' => $markers]);
    exit;
}

if ($action === 'upload_marker_image') {
    $targetDir = trim($inst['folder_path'], '/') . '/assets/markers';
    try {
        $rel = handle_media_picker('marker_file', $targetDir);
        if (!$rel) {
            echo json_encode(['success' => false, 'error' => 'No image uploaded']);
            exit;
        }
        echo json_encode(['success' => true, 'url' => url($rel), 'path' => $rel]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'list_marker_images') {
    $folder = trim($inst['folder_path'], '/') . '/assets';
    $absFolder = ROOT_PATH . '/' . $folder;
    $files = [];
    if (is_dir($absFolder)) {
        $rdi = new RecursiveDirectoryIterator($absFolder, RecursiveDirectoryIterator::SKIP_DOTS);
        $rii = new RecursiveIteratorIterator($rdi);
        foreach ($rii as $file) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'])) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(ROOT_PATH) + 1));
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $rel,
                    'url'  => url($rel)
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

if ($action === 'save_markers') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['floor_plan_id']) || !isset($input['markers'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid payload']);
        exit;
    }

    $fpId = (int)$input['floor_plan_id'];
    $markers = $input['markers'];

    // Verify ownership
    $fp = crud()->get('floor_plans', $fpId);
    if (!$fp || (int)$fp['institution_id'] !== $iid) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        db()->beginTransaction();

        // Remove old markers for this floor plan
        $stmt = db()->prepare('DELETE FROM floor_plan_markers WHERE floor_plan_id = :fpid AND institution_id = :iid');
        $stmt->execute([':fpid' => $fpId, ':iid' => $iid]);

        // Insert new markers
        $insertStmt = db()->prepare('
            INSERT INTO floor_plan_markers 
            (institution_id, floor_plan_id, label, marker_type, x_percent, y_percent, facing_angle, marker_image_path, target_scene_id, target_floor_plan_id, target_building_id, target_room_id, target_area_id, popup_title, popup_html, sort_order)
            VALUES 
            (:iid, :fpid, :label, :mtype, :x, :y, :facing, :mimg, :tsid, :tfpid, :tbld, :troom, :tarea, :ptitle, :phtml, :sort)
        ');

        $sort = 0;
        foreach ($markers as $m) {
            $mtype = in_array($m['type'] ?? '', ['scene','entrance','exit','compass','360','floorplan','ar','info','room','area'], true) ? $m['type'] : 'scene';
            $insertStmt->execute([
                ':iid' => $iid,
                ':fpid' => $fpId,
                ':label' => trim($m['label'] ?? '') ?: 'Marker',
                ':mtype' => $mtype,
                ':x' => max(0, min(100, (float)($m['x'] ?? 0))),
                ':y' => max(0, min(100, (float)($m['y'] ?? 0))),
                ':facing' => max(0, min(360, (float)($m['facing_angle'] ?? 0))),
                ':mimg' => !empty($m['marker_image_path']) ? trim($m['marker_image_path']) : null,
                ':tsid' => !empty($m['target_scene_id']) ? (int)$m['target_scene_id'] : null,
                ':tfpid' => !empty($m['target_floor_plan_id']) ? (int)$m['target_floor_plan_id'] : null,
                ':tbld' => !empty($m['target_building_id']) ? (int)$m['target_building_id'] : null,
                ':troom' => !empty($m['target_room_id']) ? (int)$m['target_room_id'] : null,
                ':tarea' => !empty($m['target_area_id']) ? (int)$m['target_area_id'] : null,
                ':ptitle' => trim($m['popup_title'] ?? '') ?: null,
                ':phtml' => trim($m['popup_html'] ?? '') ?: null,
                ':sort' => $sort++
            ]);
        }

        db()->commit();
        sync_institution_config($iid);
        // Auto-register AR targets from markers so an AR image uploaded here shows
        // up in the AR manager without a manual re-upload. Best-effort only.
        try {
            if (function_exists('ar_ensure_schema')) { ar_ensure_schema(); }
            ar_ensure_targets($iid);
        } catch (Throwable $e) {
            // ignore — the AR manager still rescans on load
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        db()->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
