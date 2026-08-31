<?php
/**
 * Innovatech PH — AJAX: generate AI info for a target and push it into the
 * caller's description textarea. Replaces the old admin AI-info generator page.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
header('Content-Type: application/json; charset=utf-8');

try {
    $inst = resolve_active_institution();
    if (!$inst) throw new RuntimeException('No active institution.');
    $iid = (int) $inst['id'];
    $me = (int) current_user()['id'];

    $type = $_POST['type'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);
    $prompt = trim($_POST['prompt'] ?? '');
    $allowed = [
        'institution' => null, 'building' => 'buildings', 'room' => 'rooms',
        'facility' => 'facilities', 'campus_area' => 'campus_areas',
        'tour_scene' => 'tour_scenes', 'waypoint' => 'ar_waypoints',
        'hotspot' => 'scene_hotspots',
    ];
    if (!array_key_exists($type, $allowed)) throw new RuntimeException('Unknown target type.');

    $name = '';
    $row = null;
    if ($type === 'institution') {
        $name = $inst['name'] ?? '';
    } else {
        $table = $allowed[$type];
        $nameField = ['tour_scene' => 'title', 'hotspot' => 'label'][$type] ?? 'name';
        $row = crud()->raw("SELECT * FROM {$table} WHERE id=:id AND institution_id=:iid", ['id' => $targetId, 'iid' => $iid])->fetch();
        $name = $row[$nameField] ?? '';
        if (!$name) throw new RuntimeException('Target not found.');
    }

    $info = ['type' => $type];
    if ($row) {
        if (!empty($row['floor_label'])) $info['location'] = trim((string) $row['floor_label']);
        if ($type === 'room') {
            $parts = [];
            if (!empty($row['room_type'])) $parts[] = 'as a ' . trim((string) $row['room_type']) . ' space';
            if (!empty($row['capacity'])) $parts[] = 'with room for up to ' . (int) $row['capacity'] . ' people';
            $info['details'] = implode(' ', $parts);
        }
        if ($type === 'campus_area' && !empty($row['area_type'])) {
            $info['details'] = 'as a ' . trim((string) $row['area_type']) . ' area';
        }
        if ($type === 'waypoint') {
            $t = trim((string) ($row['overlay_title'] ?? ''));
            $info['purpose'] = $t !== '' && $t !== $name
                ? 'the AR waypoint "' . $t . '"'
                : 'guided AR discovery on the campus grounds';
        }
        if ($type === 'hotspot') {
            $info['purpose'] = 'the "' . $name . '" stop on the 360° tour';
        }
        if ($type === 'building' && !empty($row['code'])) {
            $info['details'] = 'under the campus code ' . trim((string) $row['code']);
        }
    }
    if ($prompt !== '') {
        $info['context'] = $prompt;
    }

    $out = ai_generate_description($name, $inst['name'], $info);

    if (in_array($type, ['building', 'room', 'facility', 'campus_area', 'tour_scene'], true)) {
        crud()->update($allowed[$type], ['ai_description' => $out], ['id' => $targetId, 'institution_id' => $iid]);
    }
    try {
        crud()->insert('ai_info_jobs', [
            'institution_id' => $iid, 'created_by' => $me, 'target_type' => $type,
            'target_id' => $targetId ?: null, 'prompt' => $prompt ?: null, 'output_text' => $out, 'status' => 'completed'
        ]);
    } catch (Throwable $e) { /* non-fatal */ }
    audit('ai_info.generate', 'ai', $type, $targetId ?: $iid);

    echo json_encode(['ok' => true, 'text' => $out]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}