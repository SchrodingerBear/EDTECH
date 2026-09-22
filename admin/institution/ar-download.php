<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ar');
/**
 * Stream a ZIP of a building's floor-plan marker images, ordered numerically
 * (marker order), prefixed with floor level for sequential .mind training.
 */
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); exit; }
$iid = (int) $inst['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');

$bid = (int) ($_GET['building'] ?? 0);
$building = $bid ? crud()->get('buildings', ['id' => $bid, 'institution_id' => $iid]) : null;
if (!$building) { flash('error', 'Building not found.'); redirect('admin/institution/ar'); }

$plans = crud()->raw(
    "SELECT * FROM floor_plans WHERE institution_id=:iid AND building_id=:bid AND deleted_at IS NULL ORDER BY floor_level, title",
    ['iid' => $iid, 'bid' => $bid]
)->fetchAll();

$rows = []; // collected marker image files
$order = 0;
foreach ($plans as $fp) {
    $markers = crud()->raw(
        "SELECT * FROM floor_plan_markers WHERE institution_id=:iid AND floor_plan_id=:fpid AND marker_image_path IS NOT NULL ORDER BY IFNULL(sort_order,0), id",
        ['iid' => $iid, 'fpid' => (int) $fp['id']]
    )->fetchAll();
    foreach ($markers as $mk) {
        $order++;
        $abs = media_abs_path((string) $mk['marker_image_path'], $inst['folder_path']);
        if (!is_file($abs)) continue;
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $safeLabel = preg_replace('/[^A-Za-z0-9_\-]+/', '_', strtolower($mk['label'] ?? 'marker'));
        $floor = trim($fp['floor_level'] ?? '');
        $floorPart = $floor !== '' ? $floor . '_' : '';
        $name = sprintf('%03d_%s%s.%s', $order, $floorPart, $safeLabel, $ext);
        $rows[] = ['name' => $name, 'abs' => $abs];
    }
}

if (!$rows) { flash('error', 'No marker images on this building\'s floor plans yet.'); redirect('admin/institution/ar'); }

// ----- send ZIP -----
$safeBuilding = preg_replace('/[^A-Za-z0-9_\-]+/', '_', strtolower($building['name']));
$zipName = 'ar_' . $safeBuilding . '_markers.zip';
$tmp = tempnam(sys_get_temp_dir(), 'arz');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { flash('error', 'Could not create archive.'); redirect('admin/institution/ar'); }
// optionally include a manifest listing order -> marker for easy reference
$manifest = "MindAR marker image set — {$building['name']}\n\nOrder, File, Floor plan, Marker\n";
foreach ($rows as $i => $r) { $manifest .= ($i + 1) . ", " . $r['name'] . "\n"; }
$zip->addFromString('_manifest.txt', $manifest);
foreach ($rows as $r) { $zip->addFile($r['abs'], $r['name']); }
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit;
