<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ar');
/**
 * Innovatech PH — admin: AR Target Manager.
 * AR targets ARE the floor-plan markers that link to a 360 tour scene (360 AR Hotspot)
 * or to another floor plan (Landmark). Missing rows are created on load, but nothing
 * is ever deleted or overwritten automatically: images are uploaded by hand or captured
 * one-by-one via the "Take from 360" button, and every edit (image, popup info, .mind)
 * stays exactly as saved.
 */
require_once __DIR__ . '/ar-helpers.php';

$pageTitle = 'AR Targets';
$pageSub   = '360 AR Hotspots & Landmarks';
$active    = 'Augmented Reality';
$bodyClass = 'page-ar';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid    = (int) $inst['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
$orgRel = trim($inst['folder_path'], '/');
$arDir  = $orgAbs . '/assets/ar_targets';
$centerDir = $arDir . '/centerlooks';
$mindsDir  = $arDir . '/minds';
foreach ([$arDir, $centerDir, $mindsDir] as $d) {
    if (!is_dir($d)) { @mkdir($d, 0775, true); }
}

ar_ensure_schema();
ar_ensure_targets($iid);

// One-time normalization: old captures were stored org-relative ('assets/...').
// Rewrite them to full project paths so media_url() + the public app both resolve them.
crud()->raw(
    "UPDATE ar_targets SET image_path = CONCAT(:org, '/', image_path)
      WHERE institution_id=:iid AND image_path IS NOT NULL AND image_path <> ''
        AND image_path NOT LIKE 'http%' AND image_path NOT LIKE '/%' AND image_path NOT LIKE 'organizations/%'",
    ['org' => $orgRel, 'iid' => $iid]
)->execute();
crud()->raw(
    "UPDATE ar_targets SET auto_image_path = CONCAT(:org, '/', auto_image_path)
      WHERE institution_id=:iid AND auto_image_path IS NOT NULL AND auto_image_path <> ''
        AND auto_image_path NOT LIKE 'http%' AND auto_image_path NOT LIKE '/%' AND auto_image_path NOT LIKE 'organizations/%'",
    ['org' => $orgRel, 'iid' => $iid]
)->execute();

$selectedB  = (int) ($_GET['b'] ?? 0);
$selectedFp = (int) ($_GET['fp'] ?? 0);

// ---------------------------- actions ----------------------------
$backQuery = [];
if ($selectedB) { $backQuery[] = 'b=' . $selectedB; }
if ($selectedFp) { $backQuery[] = 'fp=' . $selectedFp; }
$backUrl = 'admin/institution/ar' . ($backQuery ? '?' . implode('&', $backQuery) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['ar_action'] ?? '';
    $q = $backUrl;
    try {
        if ($action === 'fp-assoc') {
            $fpId = (int) ($_POST['fp_id'] ?? 0);
            $bld  = (int) ($_POST['building_id'] ?? 0) ?: null;
            $lvl  = trim($_POST['floor_level'] ?? '');
            crud()->update('floor_plans', ['building_id' => $bld, 'floor_level' => $lvl ?: null], ['id' => $fpId, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Floor plan assigned to building.');
        }

        if ($action === 'scan_markers') {
            $created = ar_ensure_targets($iid);
            flash('success', 'Scanned floor-plan markers: ' . $created . ' new AR target(s) added.');
        }

        if ($action === 'save_info') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            $row = crud()->raw("SELECT * FROM ar_targets WHERE id=:id AND institution_id=:iid", ['id' => $tid, 'iid' => $iid])->fetch();
            if (!$row) throw new RuntimeException('Target not found.');
            $pimg = handle_media_picker('popup_image', $orgRel . '/assets/ar_targets/popups');
            if ($pimg !== null) { $pimg = $pimg !== '' ? $pimg : null; }
            else { $pimg = $row['popup_image_path'] ?: null; }
            crud()->update('ar_targets', [
                'name' => trim($_POST['name'] ?? '') ?: $row['name'],
                'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                'popup_description' => trim($_POST['popup_description'] ?? '') ?: null,
                'popup_image_path' => $pimg,
            ], ['id' => $tid, 'institution_id' => $iid]);
            flash('success', 'AR target info saved.');
        }

        if ($action === 'set_manual_image') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            $row = crud()->raw("SELECT * FROM ar_targets WHERE id=:id AND institution_id=:iid", ['id' => $tid, 'iid' => $iid])->fetch();
            if (!$row) throw new RuntimeException('Target not found.');
            $manual = handle_media_picker('manual_image', $orgRel . '/assets/ar_targets/manual');
            if ($manual === null) throw new RuntimeException('Choose an image.');
            crud()->update('ar_targets', [
                'manual_image_path' => $manual,
                'image_source' => 'manual',
                'image_path' => $manual,
            ], ['id' => $tid, 'institution_id' => $iid]);
            flash('success', 'Custom AR image saved.');
        }

        if ($action === 'reset_auto_image') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            $row = crud()->raw("SELECT * FROM ar_targets WHERE id=:id AND institution_id=:iid", ['id' => $tid, 'iid' => $iid])->fetch();
            if (!$row) throw new RuntimeException('Target not found.');
            $path = $row['auto_image_path'] ?: null;
            if ($path === null && $row['ar_type'] === 'floorplan_ar' && !empty($row['target_floor_plan_id'])) {
                $fp2 = crud()->raw("SELECT image_path FROM floor_plans WHERE id=:id AND deleted_at IS NULL", ['id' => (int) $row['target_floor_plan_id']])->fetch();
                if ($fp2) $path = $fp2['image_path'];
            }
            crud()->update('ar_targets', ['image_source' => 'auto', 'image_path' => $path], ['id' => $tid, 'institution_id' => $iid]);
            flash('success', 'AR image reset to auto-generated.');
        }

        if ($action === 'take_360') {
            @set_time_limit(120);
            $tid = (int) ($_POST['target_id'] ?? 0);
            $yaw = (isset($_POST['yaw']) && trim((string) $_POST['yaw']) !== '') ? (float) $_POST['yaw'] : null;
            $pitch = (isset($_POST['pitch']) && trim((string) $_POST['pitch']) !== '') ? (float) $_POST['pitch'] : null;
            $out = ar_take_from_360($iid, $tid, $yaw, $pitch);
            if (isset($out['error'])) throw new RuntimeException($out['error']);
            flash('success', '360 scene captured and saved as the AR image.');
        }

        if ($action === 'add_hotspot') {
            if ($selectedFp <= 0) { throw new RuntimeException('Pick a floor plan first.'); }
            $sid = (int) ($_POST['scene_id'] ?? 0);
            $scene = crud()->raw("SELECT * FROM tour_scenes WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL", ['id' => $sid, 'iid' => $iid])->fetch();
            if (!$scene) { throw new RuntimeException('360 scene not found.'); }
            $fpRow = crud()->raw("SELECT building_id FROM floor_plans WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL", ['id' => $selectedFp, 'iid' => $iid])->fetch();
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') { $name = trim((string) ($scene['title'] ?? '')) ?: '360 AR Hotspot'; }
            crud()->insert('ar_targets', [
                'institution_id' => $iid,
                'ar_type' => '360_ar_hotspot',
                'building_id' => ($fpRow && !empty($fpRow['building_id'])) ? (int) $fpRow['building_id'] : null,
                'floor_plan_id' => $selectedFp,
                'source_fingerprint' => 'manual_' . md5(uniqid((string) $sid, true)),
                'source_marker_id' => null,
                'name' => $name,
                'target_scene_id' => $sid,
                'target_floor_plan_id' => null,
                'image_source' => 'auto',
                'is_active' => 1,
            ]);
            flash('success', 'Hotspot "' . $name . '" added — give it an image now (Take from 360 or upload).');
        }

        if ($action === 'add_landmark_marker') {
            // Second creation method: register an existing "AR Landmark" floor-plan marker
            // (placed in the floor-plan studio) as this plan's Landmark AR target.
            $mid = (int) ($_POST['marker_id'] ?? 0);
            $m = crud()->raw(
                "SELECT m.* FROM floor_plan_markers m
                   WHERE m.id=:id AND m.institution_id=:iid AND m.marker_type='ar'",
                ['id' => $mid, 'iid' => $iid]
            )->fetch();
            if (!$m) { throw new RuntimeException('AR landmark marker not found.'); }
            $fpRow = crud()->raw(
                "SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL",
                ['id' => (int) $m['floor_plan_id'], 'iid' => $iid]
            )->fetch();
            if (!$fpRow) { throw new RuntimeException('Marker floor plan not found.'); }
            $finger = ar_source_fp($m);
            $exists = crud()->raw(
                "SELECT id FROM ar_targets WHERE institution_id=:iid AND source_fingerprint=:fp",
                ['iid' => $iid, 'fp' => $finger]
            )->fetch();
            if ($exists) {
                crud()->update('ar_targets', ['is_active' => 1], ['id' => (int) $exists['id'], 'institution_id' => $iid]);
                flash('success', 'Landmark "' . h((string) ($m['label'] ?? '')) . '" re-activated (target #' . (int) $exists['id'] . ').');
                redirect($q);
            }
            $ai = !empty($m['marker_image_path'])
                ? trim((string) $m['marker_image_path'])
                : ($fpRow['image_path'] ?: null);
            crud()->insert('ar_targets', [
                'institution_id' => $iid,
                'ar_type' => 'floorplan_ar',
                'building_id' => !empty($fpRow['building_id']) ? (int) $fpRow['building_id'] : null,
                'floor_plan_id' => (int) $m['floor_plan_id'],
                'source_fingerprint' => $finger,
                'source_marker_id' => (int) $m['id'],
                'name' => trim((string) ($m['label'] ?? '')) ?: 'Landmark',
                'target_scene_id' => null,
                'target_floor_plan_id' => (int) $m['floor_plan_id'],
                'image_source' => 'auto',
                'image_path' => $ai,
                'auto_image_path' => $ai,
                'is_active' => 1,
            ]);
            flash('success', 'Landmark "' . h((string) ($m['label'] ?? '')) . '" added from its floor-plan marker.');
        }

        if ($action === 'scene_mind') {
            $sid = (int) ($_POST['scene_id'] ?? 0);
            $scene = crud()->raw("SELECT * FROM tour_scenes WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL", ['id' => $sid, 'iid' => $iid])->fetch();
            if (!$scene) { throw new RuntimeException('360 scene not found.'); }
            $rel = ar_save_mind($_FILES['scene_mind'] ?? null, $mindsDir, 's' . $sid);
            if (!$rel) { throw new RuntimeException('Choose a .mind file.'); }
            if ($scene['ar_mind_path']) { ar_remove_mind($scene['ar_mind_path'], $orgAbs); }
            crud()->update('tour_scenes', ['ar_mind_path' => $rel], ['id' => $sid, 'institution_id' => $iid]);
            foreach (crud()->raw("SELECT id, target_mind_path FROM ar_targets WHERE target_scene_id=:sid AND target_mind_path IS NOT NULL", ['sid' => $sid])->fetchAll() as $lg) {
                if (!empty($lg['target_mind_path'])) { ar_remove_mind($lg['target_mind_path'], $orgAbs); }
            }
            crud()->raw("UPDATE ar_targets SET target_mind_path=NULL WHERE target_scene_id=:sid", ['sid' => $sid])->execute();
            flash('success', 'Room .mind saved for "' . $scene['title'] . '" — it now covers every hotspot in this room.');
        }

        if ($action === 'scene_mind_clear') {
            $sid = (int) ($_POST['scene_id'] ?? 0);
            $scene = crud()->raw("SELECT * FROM tour_scenes WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL", ['id' => $sid, 'iid' => $iid])->fetch();
            if ($scene && $scene['ar_mind_path']) { ar_remove_mind($scene['ar_mind_path'], $orgAbs); }
            if ($scene) {
                crud()->update('tour_scenes', ['ar_mind_path' => null], ['id' => $sid, 'institution_id' => $iid]);
                flash('success', 'Room .mind removed.');
            }
        }

        if ($action === 'target_mind') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            $row = crud()->raw("SELECT * FROM ar_targets WHERE id=:id AND institution_id=:iid", ['id' => $tid, 'iid' => $iid])->fetch();
            if (!$row) throw new RuntimeException('Target not found.');
            $rel = ar_save_mind($_FILES['target_mind'] ?? null, $mindsDir, 't' . $tid);
            if (!$rel) throw new RuntimeException('Choose a .mind file.');
            if ($row['target_mind_path']) { ar_remove_mind($row['target_mind_path'], $orgAbs); }
            crud()->update('ar_targets', ['target_mind_path' => $rel], ['id' => $tid, 'institution_id' => $iid]);
            flash('success', 'Target .mind saved.');
        }

        if ($action === 'target_mind_clear') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            $row = crud()->raw("SELECT * FROM ar_targets WHERE id=:id AND institution_id=:iid", ['id' => $tid, 'iid' => $iid])->fetch();
            if ($row && $row['target_mind_path']) { ar_remove_mind($row['target_mind_path'], $orgAbs); }
            crud()->update('ar_targets', ['target_mind_path' => null], ['id' => $tid, 'institution_id' => $iid]);
            flash('success', 'Target .mind removed.');
        }

        if ($action === 'fp_mind') {
            $fpId = (int) ($_POST['fp_id'] ?? 0);
            $fp = crud()->raw("SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid", ['id' => $fpId, 'iid' => $iid])->fetch();
            if (!$fp) throw new RuntimeException('Floor plan not found.');
            $rel = ar_save_mind($_FILES['fp_mind'] ?? null, $mindsDir, 'fp' . $fpId);
            if (!$rel) throw new RuntimeException('Choose a .mind file.');
            if ($fp['ar_mind_path']) { ar_remove_mind($fp['ar_mind_path'], $orgAbs); }
            crud()->update('floor_plans', ['ar_mind_path' => $rel], ['id' => $fpId, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Floor-plan .mind saved.');
        }

        if ($action === 'fp_mind_clear') {
            $fpId = (int) ($_POST['fp_id'] ?? 0);
            $fp = crud()->raw("SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid", ['id' => $fpId, 'iid' => $iid])->fetch();
            if ($fp && $fp['ar_mind_path']) { ar_remove_mind($fp['ar_mind_path'], $orgAbs); }
            crud()->update('floor_plans', ['ar_mind_path' => null], ['id' => $fpId, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Floor-plan .mind removed.');
        }

        if ($action === 'delete_target') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            $row = crud()->raw("SELECT * FROM ar_targets WHERE id=:id AND institution_id=:iid", ['id' => $tid, 'iid' => $iid])->fetch();
            if ($row) {
                if ($row['target_mind_path']) { ar_remove_mind($row['target_mind_path'], $orgAbs); }
                if ($row['manual_image_path']) {
                    $absManual = media_abs_path((string) $row['manual_image_path'], $orgRel);
                    if (str_starts_with($absManual, $orgAbs . '/assets/ar_targets/manual/') && is_file($absManual)) { @unlink($absManual); }
                }
                crud()->update('ar_targets', ['is_active' => 0], ['id' => $tid, 'institution_id' => $iid]);
            }
            flash('success', 'AR target deleted. It will not be recreated.');
        }
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect($q);
}

// ---------------------------- data ----------------------------
$buildings = crud()->raw("SELECT * FROM buildings WHERE institution_id=:iid AND deleted_at IS NULL ORDER BY name", ['iid' => $iid])->fetchAll();

$plansStmt = crud()->raw(
    "SELECT fp.*,
            (SELECT COUNT(*) FROM floor_plan_markers m WHERE m.floor_plan_id=fp.id) AS marker_count,
            (SELECT COUNT(*) FROM ar_targets t WHERE t.floor_plan_id=fp.id AND t.is_active=1) AS ar_count,
            (SELECT COUNT(*) FROM ar_targets t WHERE t.floor_plan_id=fp.id AND t.is_active=1 AND t.image_path <> '') AS ar_img_count,
            (SELECT COUNT(*) FROM ar_targets t WHERE t.floor_plan_id=fp.id AND t.is_active=1 AND t.image_path <> '' AND t.ar_type='floorplan_ar') AS ar_lm_img_count
       FROM floor_plans fp
      WHERE fp.institution_id=:iid AND fp.deleted_at IS NULL
      ORDER BY fp.building_id IS NULL, fp.building_id, fp.floor_level, fp.title",
    ['iid' => $iid]
);
$floorPlans = $plansStmt->fetchAll();

$fpJson = json_encode(array_map(static function ($p) {
    return [
        'id' => (int) $p['id'],
        'building_id' => (int) ($p['building_id'] ?? 0),
        'floor_level' => (string) ($p['floor_level'] ?? ''),
        'title' => (string) $p['title'],
    ];
}, $floorPlans), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$targetFp = null;
$targets = [];
$landmarkTargets = [];
$hotspotRooms = [];
$scenesAll = [];
if ($selectedFp) {
    foreach ($floorPlans as $p) { if ((int) $p['id'] === $selectedFp) { $targetFp = $p; break; } }
    if ($targetFp) {
        $targets = crud()->raw(
            "SELECT t.*, b.name AS building_name, s.title AS scene_title, s.equirect_path AS scene_equirect_path,
                    s.initial_yaw AS scene_yaw, s.initial_pitch AS scene_pitch, fp2.title AS target_fp_title
               FROM ar_targets t
               LEFT JOIN buildings b ON b.id=t.building_id
               LEFT JOIN tour_scenes s ON s.id=t.target_scene_id
               LEFT JOIN floor_plans fp2 ON fp2.id=t.target_floor_plan_id
              WHERE t.institution_id=:iid AND t.floor_plan_id=:fp AND t.is_active=1
              ORDER BY t.ar_type, t.name, t.id",
            ['iid' => $iid, 'fp' => $selectedFp]
        )->fetchAll();

        foreach ($targets as $t) {
            if ($t['ar_type'] === 'floorplan_ar') { $landmarkTargets[] = $t; continue; }
            $sid = (int) ($t['target_scene_id'] ?? 0);
            if ($sid <= 0) { continue; }
            $hotspotRooms[$sid]['scene_id'] = $sid;
            $hotspotRooms[$sid]['scene_title'] = (string) ($t['scene_title'] ?? ('Scene #' . $sid));
            $hotspotRooms[$sid]['scene_equirect'] = (string) ($t['scene_equirect_path'] ?? '');
            $hotspotRooms[$sid]['targets'][] = $t;
        }
        uasort($hotspotRooms, static function ($a, $b) {
            return strcasecmp((string) $a['scene_title'], (string) $b['scene_title']) ?: $a['scene_id'] - $b['scene_id'];
        });
        foreach ($hotspotRooms as &$room) {
            usort($room['targets'], static function ($a, $b) {
                return strcmp((string) $a['name'], (string) $b['name']) ?: (int) $a['id'] - (int) $b['id'];
            });
            $room['img_count'] = count(array_filter($room['targets'], static function ($t) { return !empty($t['image_path']); }));
        }
        unset($room);
        $landmarkImgCount = count(array_filter($landmarkTargets, static function ($t) { return !empty($t['image_path']); }));

        $scenesAll = crud()->raw(
            "SELECT s.id, s.title, s.equirect_path,
                    (SELECT COUNT(*) FROM ar_targets t
                      WHERE t.target_scene_id = s.id AND t.floor_plan_id = :fp AND t.is_active = 1) AS fp_count
               FROM tour_scenes s
              WHERE s.institution_id = :iid AND s.deleted_at IS NULL
              ORDER BY s.title, s.id",
            ['iid' => $iid, 'fp' => $selectedFp]
        )->fetchAll();

        // AR Landmark markers placed on this plan (candidate "second method" sources).
        $landmarkMarkers = crud()->raw(
            "SELECT m.*,
                    EXISTS(SELECT 1 FROM ar_targets t
                            WHERE t.institution_id = :iid_exists AND t.source_marker_id = m.id AND t.is_active = 1) AS has_target
               FROM floor_plan_markers m
              WHERE m.floor_plan_id = :fp AND m.institution_id = :iid AND m.marker_type = 'ar'
              ORDER BY m.label, m.id",
            ['iid_exists' => $iid, 'iid' => $iid, 'fp' => $selectedFp]
        )->fetchAll();

        // Existing floor-plan markers on this plan (to annotate a Landmark's source marker,
        // e.g. its position, and to flag targets whose marker was since removed from the map).
        $planMarkersById = [];
        foreach (crud()->raw(
            "SELECT id, label, marker_type, x_percent, y_percent FROM floor_plan_markers
              WHERE floor_plan_id = :fp AND institution_id = :iid",
            ['fp' => $selectedFp, 'iid' => $iid]
        )->fetchAll() as $pm) {
            $planMarkersById[(int) $pm['id']] = $pm;
        }
    }
}

// Training-order guide for the InnovaAR compiler modal.
// Rebuilds exactly the same order + numbering as ar-download-targets.php,
// so the compiler's target INDEX always maps 1:1 to an AR id.
$compilerSets = [];
if ($targetFp) {
    $lmRows = [];
    $o = 0;
    foreach ($landmarkTargets as $t) {
        $row = ar_compiler_row($t, ++$o);
        if ($row) { $lmRows[] = $row; }
    }
    if ($lmRows) {
        $compilerSets[] = ['kind' => 'landmarks', 'label' => 'Landmarks', 'hint' => 'compile these with the floor-plan .mind', 'rows' => $lmRows];
    }
    foreach ($hotspotRooms as $room) {
        $rows = [];
        $o = 0;
        foreach ($room['targets'] as $t) {
            $row = ar_compiler_row($t, ++$o);
            if ($row) { $rows[] = $row; }
        }
        if ($rows) {
            $compilerSets[] = ['kind' => 'room', 'label' => $room['scene_title'], 'hint' => "compile as this room's .mind (all hotspots, this order)", 'rows' => $rows];
        }
    }
}
?>

<!-- ------------------------------ header / drill ------------------------------ -->


<div class="row g-3 mb-4">
  <div class="col-md-6">
    <label class="form-label fw-semibold">Building</label>
    <select class="form-select" id="ar-building">
      <option value="0">All buildings</option>
      <?php foreach ($buildings as $b): ?>
        <option value="<?= (int) $b['id'] ?>" <?= $selectedB === (int) $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
      <?php endforeach; ?>
      <option value="-1" <?= $selectedB === -1 ? 'selected' : '' ?>>Floor plans without a building</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label fw-semibold">Floor plan</label>
    <select class="form-select" id="ar-floorplan" <?= !$selectedB ? 'disabled' : '' ?>>
      <option value="0">— overview (floor-plan summary) —</option>
    </select>
  </div>
</div>

<?php if (!$targetFp): ?>
<!-- ------------------------------ Mode A: buildings + floor plans ------------------------------ -->
<?php
$viewList = isset($selectedB) && $selectedB > 0
    ? array_values(array_filter($floorPlans, static function ($p) use ($selectedB) { return (int) ($p['building_id'] ?? 0) === $selectedB; }))
    : ((int) $selectedB === 0
        ? array_values($floorPlans)
        : array_values(array_filter($floorPlans, static function ($p) { return !empty($p['building_id']); })));
$unassigned = array_values(array_filter($floorPlans, static function ($p) { return empty($p['building_id']); }));
?>

<?php if (isset($selectedB) && $selectedB < 0): ?>
  <?php if ($unassigned): ?>
    <div class="ia-card mb-3">
      <div class="card-head"><h3 class="mb-0"><?= ia_icon('inbox', 16) ?> Floor plans without a building</h3></div>
      <div class="card-body">
        <div class="ia-micro text-ia-muted mb-3">Campus/map plans work as-is — their Landmarks and 360 AR Hotspots are
          active by default. Assigning a building is optional and only helps organize your list.</div>
        <div class="table-responsive">
          <table class="table table-ia align-middle">
            <thead><tr><th>Floor plan</th><th>Assign to building</th><th>Floor level</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($unassigned as $fp): ?>
              <tr>
                <td class="fw-bold"><?= h($fp['title']) ?></td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="ar_action" value="fp-assoc">
                    <input type="hidden" name="fp_id" value="<?= (int) $fp['id'] ?>">
                    <select class="form-select form-select-sm" name="building_id" style="max-width:240px" required>
                      <option value="" disabled selected>— choose building —</option>
                      <?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" name="floor_level" style="max-width:110px">
                      <option value="">—</option>
                      <?php foreach (['G','1','2','3','4','5','Basement','Roof'] as $fl): ?><option value="<?= $fl ?>"><?= h($fl) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-grad"><?= ia_icon('check', 13) ?> Assign</button>
                  </form>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-ia" href="ar?b=0&fp=<?= (int) $fp['id'] ?>"><?= ia_icon('scan-eye', 14) ?> View AR targets</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="empty-state py-5"><div class="empty-icon"><?= ia_icon('inbox', 24) ?></div><h5>No floor plans without a building</h5></div>
  <?php endif; ?>
<?php else: ?>
<?php
$viewBuildingName = '';
if ($selectedB > 0) {
    foreach ($buildings as $b) { if ((int) $b['id'] === $selectedB) { $viewBuildingName = (string) $b['name']; break; } }
}
?>

  <div class="ia-card mb-3">
    <div class="card-head d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h3 class="mb-0"><?= ia_icon('building', 17) ?> <?= h($viewBuildingName) ?: 'All buildings' ?> <span class="ia-micro text-ia-muted">— floor plan overview</span></h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-ia align-middle mb-0">
          <thead><tr><th>Floor plan</th><th>Floor</th><th>Markers</th><th>AR targets</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($viewList as $fp): ?>
            <tr>
              <td class="fw-bold"><?= h($fp['title']) ?><?php if (empty($fp['building_id'])): ?> <span class="badge badge-soft">no building</span><?php endif; ?></td>
              <td><?= h((string) ($fp['floor_level'] ?? '')) ?: '—' ?></td>
              <td><?= (int) $fp['marker_count'] ?></td>
              <td>
                <span class="badge badge-soft"><?= (int) $fp['ar_count'] ?> target(s)</span>
                <?php if ((int) $fp['ar_count'] > 0): ?>
                  <span class="ia-micro text-ia-muted d-block"><?= (int) $fp['ar_img_count'] ?> with image</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ((int) $fp['ar_lm_img_count'] > 0): ?>
                  <a class="btn btn-sm btn-outline-ia" href="<?= h(url('admin/institution/ar-download-targets.php?t=landmarks&fp=' . (int) $fp['id'])) ?>" title="Download all Landmark images (ZIP) for scanning / training"><?= ia_icon('download', 14) ?> Download ZIP</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-grad px-3" href="ar?b=<?= (int) $fp['building_id'] ?>&fp=<?= (int) $fp['id'] ?>"><?= ia_icon('scan-eye', 14) ?> View AR targets</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$viewList): ?>
            <tr><td colspan="5" class="text-muted py-4 text-center">No floor plans<?= $selectedB > 0 ? ' for this building' : '' ?> yet. Create one in Floor Plans, then open the studio to place markers.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php else: /* Mode B: specific floor plan */ ?>
<?php
$fpMindOk = !empty($targetFp['ar_mind_path']);
?>

<div class="ia-card mb-3">
  <div class="card-head d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h3 class="mb-0 mb-6px">
      <?= ia_icon('map', 17) ?> <?= h($targetFp['title']) ?>
      <?php if (!empty($targetFp['floor_level'])): ?><span class="badge badge-soft ms-1"><?= h($targetFp['floor_level']) ?></span><?php endif; ?>
      <?php if (empty($targetFp['building_id'])): ?><span class="badge badge-soft ms-1">default</span><?php endif; ?>
      <?php foreach ($buildings as $b): if ((int) $b['id'] === (int) ($targetFp['building_id'] ?? 0)): ?><span class="ia-micro text-ia-muted ms-2"><?= h($b['name']) ?></span><?php endif; endforeach; ?>
    </h3>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="float-status py-1 ms-auto">
        <span class="badge <?= (int) $targetFp['ar_img_count'] >= (int) $targetFp['ar_count'] && (int) $targetFp['ar_count'] > 0 ? 'badge-soft' : 'badge-danger' ?>"><?= (int) $targetFp['ar_img_count'] ?>/<?= (int) $targetFp['ar_count'] ?> images</span>
        <span class="badge <?= $fpMindOk ? 'badge-live' : 'badge-danger' ?>"><?= $fpMindOk ? 'floor-plan .mind ready' : 'floor-plan .mind missing' ?></span>
      </span>
      <a class="btn btn-sm btn-outline-ia" href="ar?b=<?= $selectedB ?>"><?= ia_icon('chevron', 13) ?> Back</a>
    </div>
  </div>

  <div class="card-body border-bottom d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div class="ia-micro text-ia-muted">
      Floor-plan <code>.mind</code> covers the whole marker set of this plan (used by Landmarks).
      Each 360 room has its own <code>.mind</code>, shared by all hotspots of that room — upload it in the room footer, then download that room's ZIP for the training order.
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?php if ($fpMindOk): ?>
        <span class="ia-micro text-ia-muted"><?= h(basename((string) $targetFp['ar_mind_path'])) ?></span>
        <form method="post"><?php ar_fp_clear_form($selectedFp); ?></form>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="ar_action" value="fp_mind">
        <input type="hidden" name="fp_id" value="<?= (int) $selectedFp ?>">
        <input type="file" class="form-control form-control-sm" name="fp_mind" accept=".mind,application/octet-stream" required>
        <button class="btn btn-sm btn-outline-ia"><?= ia_icon('upload', 13) ?> Upload floor-plan .mind</button>
      </form>
    </div>
  </div>

  <div class="card-body p-0">
      <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="ia-micro text-ia-muted"><?= count($landmarkTargets) ?> Landmark(s) · <?= count($hotspotRooms) ?> room(s) with AR hotspots</div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <a class="btn btn-sm btn-outline-ia <?= $landmarkImgCount ? '' : 'disabled' ?>" href="<?= h(url('admin/institution/ar-download-targets.php?t=landmarks&fp=' . (int) $selectedFp)) ?>" title="All Landmark images in one ZIP, numbered alphabetically to match the floor-plan .mind"><?= ia_icon('download', 13) ?> Download Landmarks ZIP</a>
          <button type="button" class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#ar-compiler-modal" title="Compile target images into .mind with the InnovaAR compiler, keeping the same index order as the ZIPs"><?= ia_icon('sparkles', 13) ?> AR Compiler</button>
          <button type="button" class="btn btn-sm btn-grad" data-bs-toggle="modal" data-bs-target="#ar-add-modal"><?= ia_icon('camera', 13) ?> Add AR hotspot</button>
        </div>
      </div>
      <?php if ($targets): ?>
      <?php if ($landmarkTargets): ?>
        <div class="px-3 pt-3 d-flex align-items-center gap-2 flex-wrap">
          <span class="ia-meta-lg fw-600"><?= ia_icon('map', 15) ?> Landmarks</span>
          <span class="ia-micro text-ia-muted">trained with the floor-plan .mind · the ZIP numbers targets in this same name order</span>
        </div>
        <div class="table-responsive">
          <table class="table table-ia align-middle mb-0">
            <thead><tr><th style="width:150px">Image</th><th>AR Name</th><th>Type / linked to</th><th>Popup info</th><th>.mind</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($landmarkTargets as $t): $type360 = $t['ar_type'] === '360_ar_hotspot'; $img = $t['image_path']; ?>
            <tr class="ar-row">
              <td>
                <div class="ar-thumb-wrap">
                  <div class="ar-thumb rounded border overflow-hidden <?= $img ? '' : 'ar-thumb-empty' ?>" style="width:132px;height:80px">
                    <?php if ($img): ?>
                      <img src="<?= h(media_url($img)) ?>" alt="" data-media-url="<?= h($img) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                      <div class="ar-thumb-placeholder d-flex align-items-center justify-content-center text-muted" style="width:100%;height:100%"><?= ia_icon('image', 22) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="ar-thumb-badge">
                    <?php if (!$img): ?>
                      <span class="badge badge-danger">no image</span>
                    <?php elseif ($t['image_source'] === 'manual'): ?>
                      <span class="badge badge-live">custom</span>
                    <?php else: ?>
                      <span class="badge badge-soft">auto</span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <span class="fw-bold"><?= h($t['name'] ?: ($t['ar_type'] === '360_ar_hotspot' ? '360 AR Hotspot' : 'Landmark')) ?></span>
                <div class="ia-micro text-ia-muted"><?= $type360 ? 'Linked scene: ' . h((string) ($t['scene_title'] ?? ('#' . (int) $t['target_scene_id']))) : 'Target plan: ' . h((string) ($t['target_fp_title'] ?? ('#' . (int) $t['target_floor_plan_id']))) ?></div>
                <?php if (!$type360): $srcM = isset($t['source_marker_id']) && (int) $t['source_marker_id'] ? ($planMarkersById[(int) $t['source_marker_id']] ?? null) : null; ?>
                  <?php if ($srcM): ?>
                    <span class="badge badge-soft mt-1" title="Created from the floor-plan marker on this map">
                      <?= ia_icon('crosshair', 11) ?> from marker <?= h((string) ($srcM['label'] ?: ('#' . (int) $t['source_marker_id']))) ?>
                      <span class="ia-micro">(<?= h($srcM['marker_type']) ?> · <?= h((string) ($srcM['x_percent'] ?? 0)) ?>%, <?= h((string) ($srcM['y_percent'] ?? 0)) ?>%)</span>
                    </span>
                  <?php elseif (!empty($t['source_marker_id'])): ?>
                    <span class="badge badge-draft mt-1" title="This target was created from a floor-plan marker that has since been removed from the map. The AR target still exists — delete it if it is no longer wanted.">
                      <?= ia_icon('trash-2', 11) ?> source marker removed from map
                    </span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($type360): ?>
                  <span class="badge badge-live">360 AR Hotspot</span>
                <?php else: ?>
                  <span class="badge badge-primary">Landmark</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($t['popup_title'] || $t['popup_description'] || $t['popup_image_path']): ?>
                  <?php if ($t['popup_title'] || $t['popup_description']): ?>
                    <span class="ia-micro fw-semibold"><?= h((string) ($t['popup_title'] ?: $t['name'])) ?></span>
                    <?php if ($t['popup_description']): ?><div class="ia-micro text-ia-muted text-truncate" style="max-width:220px"><?= h($t['popup_description']) ?></div><?php endif; ?>
                  <?php endif; ?>
                  <?php if ($t['popup_image_path']): ?>
                    <img src="<?= h(media_url($t['popup_image_path'])) ?>" class="rounded border mt-1" style="height:32px;width:48px;object-fit:cover" alt="">
                  <?php endif; ?>
                  <div class="ia-micro text-ia-muted">bound</div>
                <?php else: ?>
                  <span class="ia-micro text-ia-muted">none</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$type360): ?>
                  <span class="ia-micro text-ia-muted">uses floor-plan .mind</span>
                <?php elseif ($t['target_mind_path']): ?>
                  <span class="ia-micro fw-semibold d-block"><?= h(basename((string) $t['target_mind_path'])) ?></span>
                  <form method="post"><?php ar_target_mind_clear_form((int) $t['id']); ?></form>
                <?php else: ?>
                  <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="ar_action" value="target_mind">
                    <input type="hidden" name="target_id" value="<?= (int) $t['id'] ?>">
                    <input type="file" class="form-control form-control-sm" name="target_mind" accept=".mind,application/octet-stream" required style="max-width:150px">
                    <button class="btn btn-sm btn-outline-ia" title="Compiled marker set for this 360 hotspot"><?= ia_icon('upload', 13) ?> .mind</button>
                  </form>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="d-flex justify-content-end flex-wrap gap-1">
                  <button type="button" class="btn btn-sm btn-outline-ia" data-ar-edit="<?= (int) $t['id'] ?>"
                    data-name="<?= h($t['name']) ?>" data-title="<?= h((string) ($t['popup_title'] ?? '')) ?>"
                    data-desc="<?= h((string) ($t['popup_description'] ?? '')) ?>" data-pimg="<?= h((string) ($t['popup_image_path'] ?? '')) ?>"><?= ia_icon('edit', 12) ?> Info</button>
                  <button type="button" class="btn btn-sm btn-outline-ia" data-ar-img="<?= (int) $t['id'] ?>" data-img="<?= h((string) ($t['manual_image_path'] ?? ($t['image_source'] === 'manual' ? $t['image_path'] : ''))) ?>"><?= ia_icon('image', 12) ?> Image</button>
                  <?php if ($type360): ?>
                    <button type="button" class="btn btn-sm btn-outline-ia" data-ar-take360="<?= (int) $t['id'] ?>"
                      data-eq="<?= h(media_url((string) ($t['scene_equirect_path'] ?? ''))) ?>" data-yaw="<?= h((string) ($t['scene_yaw'] ?? 0)) ?>" data-pitch="<?= h((string) ($t['scene_pitch'] ?? 0)) ?>"
                      title="Pick the exact view inside the 360, then capture it as the AR image"><?= ia_icon('camera', 12) ?> Take from 360</button>
                  <?php endif; ?>
                  <?php if ($t['image_source'] === 'manual'): ?>
                    <form method="post"><?php ar_reset_auto_form((int) $t['id']); ?></form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Delete this AR target?');"><?php ar_delete_form((int) $t['id']); ?></form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; /* landmarks */ ?>

      <?php foreach ($hotspotRooms as $room): $roomMind = ar_room_mind_for((int) $room['scene_id']); ?>
        <div class="px-3 pt-3 d-flex align-items-center flex-wrap gap-2 mt-1">
          <span class="ia-meta-lg fw-600"><?= ia_icon('globe', 15) ?> <?= h($room['scene_title']) ?></span>
          <span class="badge badge-soft"><?= count($room['targets']) ?> hotspot(s)</span>
          <span class="badge <?= (int) $room['img_count'] === count($room['targets']) ? 'badge-soft' : 'badge-danger' ?>"><?= (int) $room['img_count'] ?>/<?= count($room['targets']) ?> images</span>
          <span class="ia-micro text-ia-muted">one .mind covers all hotspots in this room · the ZIP numbers targets in this same name order</span>
        </div>
        <div class="table-responsive">
          <table class="table table-ia align-middle mb-0">
            <thead><tr><th style="width:150px">Image</th><th>AR Name</th><th>Type</th><th>Popup info</th><th>.mind</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($room['targets'] as $t): $type360 = $t['ar_type'] === '360_ar_hotspot'; $img = $t['image_path']; ?>
              <tr class="ar-row">
                <td>
                  <div class="ar-thumb-wrap">
                    <div class="ar-thumb rounded border overflow-hidden <?= $img ? '' : 'ar-thumb-empty' ?>" style="width:132px;height:80px">
                      <?php if ($img): ?>
                        <img src="<?= h(media_url($img)) ?>" alt="" data-media-url="<?= h($img) ?>" style="width:100%;height:100%;object-fit:cover">
                      <?php else: ?>
                        <div class="ar-thumb-placeholder d-flex align-items-center justify-content-center text-muted" style="width:100%;height:100%"><?= ia_icon('image', 22) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="ar-thumb-badge">
                      <?php if (!$img): ?>
                        <span class="badge badge-danger">no image</span>
                      <?php elseif ($t['image_source'] === 'manual'): ?>
                        <span class="badge badge-live">custom</span>
                      <?php else: ?>
                        <span class="badge badge-soft">auto</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="fw-bold"><?= h($t['name'] ?: '360 AR Hotspot') ?></span>
                  <div class="ia-micro text-ia-muted">AR id #<?= (int) $t['id'] ?></div>
                </td>
                <td>
                  <span class="badge badge-live">360 AR Hotspot</span>
                </td>
                <td>
                  <?php if ($t['popup_title'] || $t['popup_description'] || $t['popup_image_path']): ?>
                    <?php if ($t['popup_title'] || $t['popup_description']): ?>
                      <span class="ia-micro fw-semibold"><?= h((string) ($t['popup_title'] ?: $t['name'])) ?></span>
                      <?php if ($t['popup_description']): ?><div class="ia-micro text-ia-muted text-truncate" style="max-width:220px"><?= h($t['popup_description']) ?></div><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($t['popup_image_path']): ?>
                      <img src="<?= h(media_url($t['popup_image_path'])) ?>" class="rounded border mt-1" style="height:32px;width:48px;object-fit:cover" alt="">
                    <?php endif; ?>
                    <div class="ia-micro text-ia-muted">bound</div>
                  <?php else: ?>
                    <span class="ia-micro text-ia-muted">none</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="ia-micro text-ia-muted" title="One .mind per room — upload it in the room footer below">shared room .mind</span>
                </td>
                <td class="text-end">
                  <div class="d-flex justify-content-end flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-ia" data-ar-edit="<?= (int) $t['id'] ?>"
                      data-name="<?= h($t['name']) ?>" data-title="<?= h((string) ($t['popup_title'] ?? '')) ?>"
                      data-desc="<?= h((string) ($t['popup_description'] ?? '')) ?>" data-pimg="<?= h((string) ($t['popup_image_path'] ?? '')) ?>"><?= ia_icon('edit', 12) ?> Info</button>
                    <button type="button" class="btn btn-sm btn-outline-ia" data-ar-img="<?= (int) $t['id'] ?>" data-img="<?= h((string) ($t['manual_image_path'] ?? ($t['image_source'] === 'manual' ? $t['image_path'] : ''))) ?>"><?= ia_icon('image', 12) ?> Image</button>
                    <?php if ($type360): ?>
                      <button type="button" class="btn btn-sm btn-outline-ia" data-ar-take360="<?= (int) $t['id'] ?>"
                        data-eq="<?= h(media_url((string) ($t['scene_equirect_path'] ?? ''))) ?>" data-yaw="<?= h((string) ($t['scene_yaw'] ?? 0)) ?>" data-pitch="<?= h((string) ($t['scene_pitch'] ?? 0)) ?>"
                        title="Pick the exact view inside the 360, then capture it as the AR image"><?= ia_icon('camera', 12) ?> Take from 360</button>
                    <?php endif; ?>
                    <?php if ($t['image_source'] === 'manual'): ?>
                      <form method="post"><?php ar_reset_auto_form((int) $t['id']); ?></form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Delete this AR target?');"><?php ar_delete_form((int) $t['id']); ?></form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="px-3 py-2 d-flex flex-wrap gap-2 align-items-center justify-content-between border-top">
          <span class="ia-micro text-ia-muted">Room <code>.mind</code> — compiled for all hotspots of this room, in the order shown above. Images come from the ZIP below.</span>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <?php if ($roomMind): ?>
              <span class="ia-micro text-ia-muted"><?= h(basename($roomMind)) ?></span>
              <form method="post"><?php ar_scene_mind_clear_form((int) $room['scene_id']); ?></form>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
              <input type="hidden" name="ar_action" value="scene_mind">
              <input type="hidden" name="scene_id" value="<?= (int) $room['scene_id'] ?>">
              <input type="file" class="form-control form-control-sm" name="scene_mind" accept=".mind,application/octet-stream" required style="max-width:150px">
              <button class="btn btn-sm btn-outline-ia" title="Compiled marker set for this room"><?= ia_icon('upload', 13) ?> Upload room .mind</button>
            </form>
            <a class="btn btn-sm btn-outline-ia" href="<?= h(url('admin/institution/ar-download-targets.php?t=room&fp=' . (int) $selectedFp . '&scene=' . (int) $room['scene_id'])) ?>" title="All hotspot images of this room in one ZIP, numbered alphabetically"><?= ia_icon('download', 13) ?> Download hotspot ZIP</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state py-5">
        <div class="empty-icon"><?= ia_icon('scan-eye', 24) ?></div>
        <h5>No AR targets yet</h5>
        <p class="mb-0">Open the <a href="floor-plans?studio=<?= (int) $selectedFp ?>" target="_blank">floor-plan studio</a> and add a marker that links to a 360 scene or to another floor plan — it appears here as an AR target. Then upload an image or use <strong>Take from 360</strong>.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ------------------------------ Modals ------------------------------ -->
<div class="modal fade" id="ar-info-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">AR target info (scan popup)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="ar_action" value="save_info">
        <input type="hidden" name="target_id" id="ar-info-id">
        <div class="mb-3"><label class="form-label fw-semibold">AR Name</label><input class="form-control" name="name" id="ar-info-name" placeholder="e.g. Main Building Lobby"></div>
        <div class="mb-3"><label class="form-label fw-semibold">Popup title</label><input class="form-control" name="popup_title" id="ar-info-title" placeholder="Shown when this target is scanned"></div>
        <div class="mb-3"><label class="form-label fw-semibold">Popup description</label><textarea class="form-control" name="popup_description" id="ar-info-desc" rows="3" placeholder="Optional text shown in the scan popup"></textarea></div>
        <?php
        $pickerName = 'popup_image';
        $pickerValue = '';
        $pickerLabel = 'Popup image (optional)';
        $pickerHelp = 'Optional image shown together with the popup text when the target is scanned.';
        require __DIR__ . '/../layout/media-picker-sweetalert.php';
        $pickerInfoId = $pickerId;
        ?>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Save info</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="ar-img-modal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Custom AR image</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="ar_action" value="set_manual_image">
        <input type="hidden" name="target_id" id="ar-img-target">
        <?php
        $pickerName = 'manual_image';
        $pickerValue = '';
        $pickerLabel = 'AR image (custom)';
        $pickerHelp = 'Optional custom visual target. Tip: for 360 hotspots you can use "Take from 360" instead of uploading.';
        require __DIR__ . '/../layout/media-picker-sweetalert.php';
        $pickerImgId = $pickerId;
        ?>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Save custom image</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="ar-cap-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= ia_icon('camera', 16) ?> Take from 360 — choose your view</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="ia-micro text-ia-muted mb-2">Drag on the panorama to look around. The crosshair at the center is exactly the
          part of the 360 that will be captured as the AR image — pick the view first, then capture.</p>
        <div class="ar-cap-stage rounded border bg-dark position-relative overflow-hidden" id="ar-cap-stage">
          <canvas id="ar-cap-canvas" class="d-block w-100" style="cursor:grab; touch-action:none; min-height:200px"></canvas>
          <div class="ar-cap-crosshair" aria-hidden="true"></div>
        </div>
        <div class="ia-micro text-ia-muted mt-2" id="ar-cap-readout">Loading 360…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-ia" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-grad px-4" id="ar-cap-capture">Capture this view</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="ar-add-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= ia_icon('camera', 16) ?> Add AR target</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><span class="badge badge-primary">Method 1</span> <span class="fw-semibold">From a 360 scene — 360 AR Hotspot</span></div>
        <p class="ia-micro text-ia-muted mb-2">Creates a marker visible inside a 360 room. Scenes that already have
          hotspots on this floor plan are marked — recommended ones have none yet.</p>
        <div class="list-group list-group-ia mb-4">
        <?php foreach ($scenesAll as $sc): $scCount = (int) ($sc['fp_count'] ?? 0); ?>
          <form method="post" class="list-group-item d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
              <span class="fw-semibold"><?= h($sc['title']) ?></span>
              <span class="badge ms-1 <?= $scCount ? 'badge-soft' : 'badge-live' ?>"><?= $scCount ? $scCount . ' hotspot(s)' : 'no hotspots yet' ?></span>
              <?php if (!empty($sc['equirect_path'])): ?><div class="ia-micro text-ia-muted">equirect 360 ready — pick a view with Take from 360 after adding</div><?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <input type="hidden" name="ar_action" value="add_hotspot">
              <input type="hidden" name="scene_id" value="<?= (int) $sc['id'] ?>">
              <input type="text" class="form-control form-control-sm" name="name" placeholder="AR name (defaults to scene title)" style="max-width:220px">
              <button class="btn btn-sm btn-grad"><?= ia_icon('camera', 12) ?> Add</button>
            </div>
          </form>
        <?php endforeach; ?>
        <?php if (!$scenesAll): ?><p class="text-muted py-3 mb-0"><em>No 360 scenes yet — create a tour scene first.</em></p><?php endif; ?>
        </div>

        <div class="mb-2"><span class="badge badge-soft">Method 2</span> <span class="fw-semibold">From an AR Landmark marker on this floor plan</span></div>
        <p class="ia-micro text-ia-muted mb-2">Any <strong>AR Landmark</strong> marker you place on this map in the floor-plan studio
          becomes a Landmark trained with the floor-plan .mind. Pick one below to register it now (they are also picked up automatically on load).</p>
        <div class="list-group list-group-ia mb-3">
        <?php foreach ($landmarkMarkers as $lm): ?>
          <div class="list-group-item d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
              <span class="fw-semibold"><?= h($lm['label'] ?: ('#' . (int) $lm['id'])) ?></span>
              <span class="ia-micro text-ia-muted d-block">at <?= h((string) ($lm['x_percent'] ?? 0)) ?>%, <?= h((string) ($lm['y_percent'] ?? 0)) ?>% on the map</span>
            </div>
            <?php if ((int) ($lm['has_target'] ?? 0)): ?>
              <span class="badge badge-soft">already registered</span>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="ar_action" value="add_landmark_marker">
                <input type="hidden" name="marker_id" value="<?= (int) $lm['id'] ?>">
                <button class="btn btn-sm btn-outline-ia"><?= ia_icon('map', 12) ?> Add as Landmark</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$landmarkMarkers): ?>
          <p class="text-muted py-3 mb-0"><em>No AR Landmark markers on this floor plan yet — add one in the floor-plan studio (AR Landmark tool), then return here.</em></p>
        <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-ia" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- AR Compiler (InnovaAR external tool, iframed) — training-order must match the ZIPs -->
<div class="modal fade" id="ar-compiler-modal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= ia_icon('sparkles', 17) ?> AR Compiler — target images → .mind</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ol class="ia-micro text-ia-muted mb-3 ps-3 m-0 d-flex flex-wrap gap-x-3 gap-y-1">
          <li><strong>1.</strong> Download a target ZIP (Landmarks or a room&rsquo;s hotspot ZIP).</li>
          <li><strong>2.</strong> Drop its images into the compiler — the 3-digit filenames <em>lock the alphabetical order</em>, so the compiler&rsquo;s Index matches the list below.</li>
          <li><strong>3.</strong> Click <em>Start</em> → <em>Download</em> <code>targets.mind</code>.</li>
          <li><strong>4.</strong> Upload that <code>.mind</code>: floor-plan <code>.mind</code> for Landmarks, or the room footer upload for a room&rsquo;s .mind.</li>
        </ol>

        <div class="row g-3">
          <div class="col-lg-8">
            <div class="border rounded overflow-hidden bg-white">
              <iframe src="https://mindarcompiler.vercel.app/tools/compile" title="InnovaAR Compiler" class="w-100 d-block" style="height:62vh;border:0" referrerpolicy="no-referrer" loading="lazy"></iframe>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="ia-micro fw-semibold mb-1">Index → AR database mapping (training order)</div>
            <div class="ia-micro text-ia-muted mb-2">Same order &amp; numbering as the ZIPs below. The compiler assigns Index = position in drop order; here is which AR id each Index binds to.</div>
            <div class="d-grid gap-2" style="max-height:47vh;overflow:auto;padding-right:4px">
              <?php foreach ($compilerSets as $set): ?>
                <div class="border rounded p-2">
                  <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <span class="fw-semibold" style="font-size:12px"><?= h($set['label']) ?></span>
                    <span class="badge <?= $set['kind'] === 'landmarks' ? 'badge-primary' : 'badge-live' ?>"><?= count($set['rows']) ?> target(s)</span>
                  </div>
                  <div class="ia-micro text-ia-muted mb-1"><?= h($set['hint']) ?></div>
                  <table class="table table-sm table-ia align-middle mb-0" style="font-size:11px">
                    <thead><tr><th style="width:38px">Index</th><th>File in ZIP</th><th class="text-end">AR id</th></tr></thead>
                    <tbody>
                    <?php foreach ($set['rows'] as $r): ?>
                      <tr>
                        <td class="fw-bold"><?= (int) $r['order'] ?></td>
                        <td><span class="fw-semibold"><?= h($r['file']) ?></span><span class="ia-micro text-ia-muted d-block text-truncate" style="max-width:180px"><?= h($r['name']) ?></span></td>
                        <td class="text-end text-ia-muted">#<?= (int) $r['id'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endforeach; ?>
              <?php if (!$compilerSets): ?>
                <p class="text-muted small mb-0 py-2">No target images yet on this floor plan — add images first (Take from 360 or upload), then the mapping appears here.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-ia" data-bs-dismiss="modal">Close</button>
        <a class="btn btn-grad px-4" href="https://mindarcompiler.vercel.app/tools/compile" target="_blank" rel="noopener"><?= ia_icon('link', 14) ?> Open compiler in new tab</a>
      </div>
    </div>
  </div>
</div>

<style>
  .ar-cap-crosshair {
    position: absolute; left: 50%; top: 50%; width: 0; height: 0; pointer-events: none; z-index: 2;
  }
  .ar-cap-crosshair::before,
  .ar-cap-crosshair::after {
    content: ""; position: absolute; background: #ffd54d;
    box-shadow: 0 0 4px rgba(0, 0, 0, 0.9), 0 0 1px rgba(0, 0, 0, 0.9);
  }
  .ar-cap-crosshair::before { width: 2px; height: 34px; left: -1px; top: -17px; }
  .ar-cap-crosshair::after  { width: 34px; height: 2px; top: -1px; left: -17px; }
  .ar-cap-crosshair .ring { position: absolute; border: 1px solid rgba(255, 213, 77, 0.7); border-radius: 50%; width: 26px; height: 26px; left: -13px; top: -13px; box-shadow: 0 0 4px rgba(0, 0, 0, 0.9); }
</style>

<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>

<!-- ------------------------------ JS ------------------------------ -->
<?php if (isset($selectedFp) && $selectedFp): ?>
<script>
(function () {
  var fpJson = <?= $fpJson ?>;
  var base   = window.location.pathname;
  var bSel   = document.getElementById('ar-building');
  var fpSel  = document.getElementById('ar-floorplan');

  function fillFloorPlans() {
    fpSel.innerHTML = '<option value="0">— overview (floor-plan summary) —</option>';
    var b = parseInt(bSel.value, 10);
    fpJson.slice().sort(function (a, b2) {
      return (a.floor_level || '').localeCompare(b2.floor_level || '', undefined, { numeric: true }) || a.title.localeCompare(b2.title);
    }).forEach(function (p) {
      var isB = b < 0 ? p.building_id === 0 : (b === 0 || p.building_id === b);
      if (isB) {
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.title + (p.floor_level ? ' (' + p.floor_level + ')' : '');
        if (parseInt('<?= (int) $selectedFp ?>', 10) === p.id || '<?= (int) $selectedFp ?>' === String(p.id)) opt.selected = true;
        fpSel.appendChild(opt);
      }
    });
    fpSel.disabled = false;
  }

  bSel.addEventListener('change', function () {
    fillFloorPlans();
    var fp = parseInt(fpSel.value, 10) || 0;
    window.location = base + '?b=' + bSel.value + (fp ? '&fp=' + fp : '');
  });

  fpSel.addEventListener('change', function () {
    var fp = parseInt(fpSel.value, 10) || 0;
    window.location = base + '?b=' + bSel.value + (fp ? '&fp=' + fp : '');
  });

  if (bSel.value !== '0') fillFloorPlans();

  // ── Take from 360: choose the view, then capture ──
  var VFOV = 60; // degrees (matches the server-side render)
  var HFOV = 2 * Math.atan(480 / (270 / Math.tan(VFOV * Math.PI / 360))) * 180 / Math.PI; // 16:9 horizon
  var capCanvas = document.getElementById('ar-cap-canvas');
  var capCtx = capCanvas ? capCanvas.getContext('2d') : null;
  var capStage = document.getElementById('ar-cap-stage');
  var capReadout = document.getElementById('ar-cap-readout');
  var capImg = null, capData = null, capYaw = 0, capPitch = 0, capEqW = 0, capEqH = 0;
  var capDrag = false, capLX = 0, capLY = 0, capW = 0, capH = 0;

  function capNorm(v) { return ((v % 360) + 360) % 360; }

  function capDraw() {
    if (!capCtx || !capImg || !capImg.naturalWidth) return;
    var scaleY = capH / (VFOV / 180 * capEqH);
    var scaleX = capW / (HFOV / 360 * capEqW);
    capPitch = Math.max(-55, Math.min(55, capPitch));
    var srcX = capNorm(capYaw) / 360 * capEqW;
    var srcY = capEqH / 2 + (capPitch / 180) * capEqH;
    capCtx.clearRect(0, 0, capW, capH);
    capCtx.save();
    capCtx.translate(capW / 2 - srcX * scaleX, capH / 2 - srcY * scaleY);
    var w = capEqW * scaleX, h = capEqH * scaleY;
    for (var i = -2; i <= 2; i++) capCtx.drawImage(capImg, i * w, 0, w, h);
    capCtx.restore();
    capReadout.textContent = 'Yaw ' + capNorm(capYaw).toFixed(1) + '° · Pitch ' + capPitch.toFixed(1) + '° — drag to look around';
  }

  if (capCanvas && capCtx) {
    capCanvas.addEventListener('pointerdown', function (e) {
      capDrag = true; capLX = e.clientX; capLY = e.clientY;
      capCanvas.setPointerCapture(e.pointerId); capCanvas.style.cursor = 'grabbing';
    });
    capCanvas.addEventListener('pointermove', function (e) {
      if (!capDrag) return;
      capYaw += (e.clientX - capLX) * HFOV / capW;
      capPitch -= (e.clientY - capLY) * VFOV / capH;
      capLX = e.clientX; capLY = e.clientY;
      capDraw();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      capCanvas.addEventListener(ev, function () {
        capDrag = false; capCanvas.style.cursor = 'grab';
      });
    });
  }

  var capModalEl = document.getElementById('ar-cap-modal');
  if (capModalEl) {
    capModalEl.addEventListener('show.bs.modal', function (e) {
      var btn = e.relatedTarget;
      if (!btn || !capCtx) { e.preventDefault(); return; }
      capData = { target: btn.getAttribute('data-ar-take360') };
      capYaw = parseFloat(btn.getAttribute('data-yaw')) || 0;
      capPitch = parseFloat(btn.getAttribute('data-pitch')) || 0;
      var url = btn.getAttribute('data-eq') || '';
      if (!url) { e.preventDefault(); return; }
      capW = capStage.clientWidth || 720;
      capH = Math.round(capW * 9 / 16);
      capCanvas.width = capW; capCanvas.height = capH;
      capReadout.textContent = 'Loading 360…';
      if (capImg) capImg.onload = null;
      capImg = new Image();
      capImg.onload = function () {
        capEqW = capImg.naturalWidth; capEqH = capImg.naturalHeight;
        capDraw();
      };
      capImg.src = url;
    });
    document.getElementById('ar-cap-capture').addEventListener('click', function () {
      if (!capData) return;
      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Capturing…';
      var f = document.createElement('form');
      f.method = 'post'; f.className = 'd-none';
      [['ar_action', 'take_360'], ['target_id', capData.target],
       ['yaw', capNorm(capYaw).toFixed(2)], ['pitch', capPitch.toFixed(2)]
      ].forEach(function (kv) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = kv[0]; h.value = kv[1];
        f.appendChild(h);
      });
      document.body.appendChild(f);
      f.submit();
    });
  }

  window.arSetMediaPicker = function (pickerId, url, filename) {
    var fullUrl = url ? ((/^(https?:)?\/\//i.test(url) || url.indexOf('data:') === 0) ? url : ((window.IA_BASE_URL || '').replace(/\/$/, '') + '/' + url.replace(/^\/+/, ''))) : '';
    document.getElementById(pickerId + '_url').value = url || '';
    document.getElementById(pickerId + '_label').innerHTML = url ? '<span class="text-truncate">' + (filename || url.split('/').pop()) + '</span>' : '<span class="text-muted">No media selected</span>';
    var sub = document.getElementById(pickerId + '_sub'); if (sub) sub.textContent = url || 'Choose a file or enter a link';
    var preview = document.getElementById(pickerId + '_preview');
    if (preview) preview.innerHTML = url ? '<img src="' + fullUrl + '">' : '<i class="fa-regular fa-image" style="font-size: 22px; color: #aab2c0;"></i>';
  };

  document.addEventListener('click', function (e) {
    var edit = e.target.closest('[data-ar-edit]');
    if (edit) {
      document.getElementById('ar-info-id').value = edit.getAttribute('data-ar-edit');
      document.getElementById('ar-info-name').value = edit.getAttribute('data-name') || '';
      document.getElementById('ar-info-title').value = edit.getAttribute('data-title') || '';
      document.getElementById('ar-info-desc').value = edit.getAttribute('data-desc') || '';
      var pimg = edit.getAttribute('data-pimg') || '';
      arSetMediaPicker('<?= h($pickerInfoId) ?>', pimg, pimg ? pimg.split('/').pop() : '');
      var m = new bootstrap.Modal(document.getElementById('ar-info-modal'));
      m.show();
      return;
    }
    var img = e.target.closest('[data-ar-img]');
    if (img) {
      var current = img.getAttribute('data-img') || '';
      arSetMediaPicker('<?= h($pickerImgId ?? '') ?>', current, current ? current.split('/').pop() : '');
      document.getElementById('ar-img-target').value = img.getAttribute('data-ar-img');
      var mm = new bootstrap.Modal(document.getElementById('ar-img-modal'));
      mm.show();
      return;
    }
    var cap = e.target.closest('[data-ar-take360]');
    if (cap) {
      capReadout.textContent = 'Loading 360…';
      var mc = new bootstrap.Modal(document.getElementById('ar-cap-modal'));
      mc.show();
      return;
    }
  });
})();
</script>
<?php endif; ?>

<?php if (!isset($selectedFp) || !$selectedFp): ?>
<script>
(function () {
  var fpJson = <?= $fpJson ?>;
  var base   = window.location.pathname;
  var bSel   = document.getElementById('ar-building');
  var fpSel  = document.getElementById('ar-floorplan');

  function fillFloorPlans() {
    fpSel.innerHTML = '<option value="0">— overview (floor-plan summary) —</option>';
    var b = parseInt(bSel.value, 10);
    fpJson.slice().sort(function (a, b2) {
      return (a.floor_level || '').localeCompare(b2.floor_level || '', undefined, { numeric: true }) || a.title.localeCompare(b2.title);
    }).forEach(function (p) {
      var isB = b < 0 ? p.building_id === 0 : (b === 0 || p.building_id === b);
      if (isB) {
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.title + (p.floor_level ? ' (' + p.floor_level + ')' : '');
        fpSel.appendChild(opt);
      }
    });
    fpSel.disabled = false;
  }

  bSel.addEventListener('change', function () {
    fillFloorPlans();
    var fp = parseInt(fpSel.value, 10) || 0;
    window.location = base + '?b=' + bSel.value + (fp ? '&fp=' + fp : '');
  });

  fpSel.addEventListener('change', function () {
    var fp = parseInt(fpSel.value, 10) || 0;
    if (fp) window.location = base + '?b=' + bSel.value + '&fp=' + fp;
  });

  fillFloorPlans();
})();
</script>
<?php endif; ?>