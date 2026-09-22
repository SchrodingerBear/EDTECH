<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ar');
/**
 * Stream a ZIP of AR target images for MindAR training (.mind workflow).
 *
 *   t=landmarks&fp=N    → this floor plan's Landmarks (floor-plan .mind)
 *   t=room&fp=N&scene=M → all 360 AR Hotspots of scene M on floor plan N (room .mind)
 *
 * Images are numbered in the same system order they sort in (name, id) and a
 * _manifest.txt maps each index to its target name + id, so the order you load
 * the ZIP into MindAR matches exactly.
 */
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); exit; }
$iid = (int) $inst['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');

$fpId = (int) ($_GET['fp'] ?? 0);
$type = $_GET['t'] ?? 'landmarks';
$sceneId = (int) ($_GET['scene'] ?? 0);

$fp = crud()->raw("SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL", ['id' => $fpId, 'iid' => $iid])->fetch();
if (!$fp) { flash('error', 'Floor plan not found.'); redirect('admin/institution/ar'); }

if ($type === 'room' && $sceneId > 0) {
    $scene = crud()->raw("SELECT * FROM tour_scenes WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL", ['id' => $sceneId, 'iid' => $iid])->fetch();
    if (!$scene) { flash('error', '360 scene not found.'); redirect('admin/institution/ar?b=1&fp=' . $fpId); }
    $kind = 'room';
    $zipBase = 'ar_fp' . $fpId . '_room' . $sceneId;
    $title = '360 AR Hotspots — room "' . $scene['title'] . '", floor plan "' . $fp['title'] . '"';
} else {
    $kind = 'landmarks';
    $zipBase = 'ar_fp' . $fpId . '_landmarks';
    $title = 'Landmarks (floor-plan .mind) — floor plan "' . $fp['title'] . '"';
}

$rows = []; // ['abs'=>.., 'name'=>.., 'label'=>.., 'id'=>integer]
$sql = $kind === 'room'
    ? "SELECT * FROM ar_targets WHERE institution_id=:iid AND floor_plan_id=:fp AND ar_type='360_ar_hotspot'
          AND target_scene_id=:sid AND is_active=1 AND image_path IS NOT NULL AND image_path <> ''
       ORDER BY name, id"
    : "SELECT * FROM ar_targets WHERE institution_id=:iid AND floor_plan_id=:fp AND ar_type='floorplan_ar'
          AND is_active=1 AND image_path IS NOT NULL AND image_path <> ''
       ORDER BY name, id";
$params = ['iid' => $iid, 'fp' => $fpId];
if ($kind === 'room') { $params['sid'] = $sceneId; }

$order = 0;
foreach (crud()->raw($sql, $params)->fetchAll() as $t) {
    $order++;
    $abs = media_abs_path((string) $t['image_path'], $inst['folder_path']);
    if (!is_file($abs)) { continue; }
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim((string) $t['name']));
    $safe = trim(preg_replace('/_+/', '_', $safe), '_');
    if ($safe === '') { $safe = 'target'; }
    $name = sprintf('%03d_%s.%s', $order, $safe, $ext);
    $rows[] = [
        'abs' => $abs,
        'name' => $name,
        'label' => (string) $t['name'],
        'id' => (int) $t['id'],
    ];
}

if (!$rows) {
    $msg = $kind === 'room'
        ? "No hotspot images yet for this room — capture them with Take from 360 first."
        : "No Landmark images on this floor plan yet.";
    flash('error', $msg);
    redirect('admin/institution/ar?b=1&fp=' . $fpId);
}

// ----- build + stream ZIP (order = MindAR target index) -----
$tmp = tempnam(sys_get_temp_dir(), 'arzip');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    flash('error', 'Could not create archive.');
    redirect('admin/institution/ar?b=1&fp=' . $fpId);
}

$manifest = $title . "\n"
    . "Load the images into MindAR IN THIS ORDER — target index = the 3-digit number.\n\n"
    . "Index, File, AR id, Name\n";
foreach ($rows as $i => $r) {
    $manifest .= ($i + 1) . ', ' . $r['name'] . ', ' . $r['id'] . ', ' . $r['label'] . "\n";
    $zip->addFile($r['abs'], $r['name']);
}
$zip->addFromString('_manifest.txt', $manifest);
$zip->close();

$safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '_', strtolower($zipBase));
$zipName = $safeTitle . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit;