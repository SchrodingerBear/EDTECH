<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_admin_staff();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

$inst = resolve_active_institution();
if (!$inst) {
    echo json_encode(['success' => false, 'error' => 'No active institution']);
    exit;
}
$iid = (int) $inst['id'];

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
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        db()->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
