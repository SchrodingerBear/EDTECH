<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.floorplans');
/**
 * Innovatech PH — admin: floor plans + responsive markers (percentage coords).
 * Markers are placed/dragged on an aspect-locked stage so alignment survives any screen.
 */
$pageTitle = 'Floor Plans';
$pageSub = 'Upload the campus map and drop circular markers (drag & drop studio)';
$active = 'Floor Plans';
$bodyClass = 'page-floor-plans';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];
$orgDir = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/floorplans';

$studioPlanId = (int) ($_GET['studio'] ?? 0);
$filterBuildingId = (int) ($_GET['building'] ?? 0);

// ------------------------------- actions -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['fp_action'] ?? '';
    try {
        if ($action === 'upload') {
            $title = trim($_POST['title'] ?? '') ?: 'Campus Map';
            $rel = handle_media_picker('image', trim($inst['folder_path'], '/') . '/assets/floorplans');
            if (!$rel) throw new RuntimeException('Choose an image.');

            $abs = ROOT_PATH . '/' . ltrim($rel, '/');
            $size = @getimagesize($abs);
            $w = (int) ($size[0] ?? 1200);
            $h = (int) ($size[1] ?? 900);

            crud()->insert('floor_plans', ['institution_id' => $iid, 'title' => $title, 'image_path' => $rel, 'original_width' => $w, 'original_height' => $h, 'aspect_ratio' => $w / max(1, $h), 'object_fit' => 'contain', 'created_by' => (int) current_user()['id']]);
            flash('success', 'Floor plan uploaded (aspect ratio locked).');
        }

        if ($action === 'update-image') {
            $id = (int) ($_POST['id'] ?? 0);
            $rel = handle_media_picker('image', trim($inst['folder_path'], '/') . '/assets/floorplans');
            if (!$rel) throw new RuntimeException('Choose a new image.');
            $abs = ROOT_PATH . '/' . ltrim($rel, '/');
            $size = @getimagesize($abs);
            $w = (int) ($size[0] ?? 1200);
            $h = (int) ($size[1] ?? 900);
            crud()->update('floor_plans', [
                'image_path'      => $rel,
                'original_width'  => $w,
                'original_height' => $h,
                'aspect_ratio'    => $w / max(1, $h),
            ], ['id' => $id, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Floor plan image updated.');
        }

        if ($action === 'landing') {
            $id = (int) ($_POST['id'] ?? 0);
            crud()->update('institutions', ['landing_mode' => 'floor_plan', 'starting_scene_id' => null, 'starting_floor_plan_id' => $id], ['id' => $iid]);
            crud()->update('floor_plans', ['is_campus_landing' => 0], ['institution_id' => $iid]);
            crud()->update('floor_plans', ['is_campus_landing' => 1], ['id' => $id, 'institution_id' => $iid]);
            $_SESSION['user']['institution']['landing_mode'] = 'floor_plan';
            $_SESSION['user']['institution']['starting_floor_plan_id'] = $id;
            flash('success', 'This floor plan is now the landing.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $img = crud()->raw("SELECT image_path FROM floor_plans WHERE id=:id AND institution_id=:iid", ['id' => $id, 'iid' => $iid])->fetchColumn();
            crud()->delete('floor_plans', ['id' => $id, 'institution_id' => $iid]);
            if ($img) {
                $abs = ROOT_PATH . '/' . ltrim($img, '/');
                if (str_starts_with($abs, $orgDir) && is_file($abs)) unlink($abs);
            }
            flash('success', 'Floor plan removed.');
        }

        if ($action === 'marker-add') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            if ($label === '') throw new RuntimeException('Marker label required.');
            $mtype = in_array($_POST['marker_type'] ?? '', ['scene', 'entrance', 'exit'], true) ? $_POST['marker_type'] : 'scene';
            crud()->insert('floor_plan_markers', [
                'institution_id' => $iid, 'floor_plan_id' => $planId, 'label' => $label,
                'marker_type' => $mtype,
                'x_percent' => (float) ($_POST['x'] ?? 50), 'y_percent' => (float) ($_POST['y'] ?? 50), 'size_percent' => 4,
                'target_room_id'  => (int) ($_POST['target_room_id'] ?? 0) ?: null,
                'target_building_id'  => (int) ($_POST['target_building_id'] ?? 0) ?: null,
                'target_facility_id'  => (int) ($_POST['target_facility_id'] ?? 0) ?: null,
                'target_scene_id'  => (int) ($_POST['target_scene_id'] ?? 0) ?: null,
                'target_floor_plan_id' => (int) ($_POST['target_floor_plan_id'] ?? 0) ?: null,
                'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                'popup_html' => trim($_POST['popup_html'] ?? '') ?: null, 'sort_order' => 0
            ]);
            sync_institution_config($iid);
            flash('success', 'Marker added. Drag it into place in the studio.');
        }

        if ($action === 'markers-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            foreach (($_POST['markers'] ?? []) as $m) {
                $mid = (int) ($m['id'] ?? 0);
                if (!$mid) continue;
                $mtype = in_array($m['marker_type'] ?? '', ['scene', 'entrance', 'exit'], true) ? $m['marker_type'] : 'scene';
                crud()->update('floor_plan_markers', [
                    'x_percent' => max(0, min(100, (float) ($m['x'] ?? 0))),
                    'y_percent' => max(0, min(100, (float) ($m['y'] ?? 0))),
                    'popup_title' => trim($m['popup_title'] ?? '') ?: null,
                    'popup_html' => trim($m['popup_html'] ?? '') ?: null,
                    'label' => trim($m['label'] ?? '') ?: 'Marker',
                    'marker_type' => $mtype,
                    'facing_angle' => max(0, min(360, (float) ($m['facing_angle'] ?? 0))),
                    'target_scene_id'  => (int) ($m['target_scene_id'] ?? 0) ?: null,
                    'target_floor_plan_id' => (int) ($m['target_floor_plan_id'] ?? 0) ?: null,
                    'target_building_id' => (int) ($m['target_building_id'] ?? 0) ?: null,
                    'target_room_id'   => (int) ($m['target_room_id'] ?? 0) ?: null,
                ], ['id' => $mid, 'institution_id' => $iid]);
            }
            if (isset($_POST['marker_delete']) && $_POST['marker_delete'] !== '') {
                crud()->delete('floor_plan_markers', ['id' => (int) $_POST['marker_delete'], 'institution_id' => $iid]);
            }
            sync_institution_config($iid);
            flash('success', 'Marker positions saved.');
        }

        if ($action === 'marker-delete') {
            crud()->delete('floor_plan_markers', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Marker removed.');
        }

        if ($action === 'compass-save') {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new RuntimeException('Missing floor plan.');
            $na = max(0, min(360, (float) ($_POST['north_angle'] ?? 0)));
            crud()->update('floor_plans', ['north_angle' => $na], ['id' => $id, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Compass north saved.');
        }

        if ($action === 'waypoint-add') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            if ($label === '') $label = 'Waypoint ' . ((int) (crud()->get('fp_waypoints', ['floor_plan_id' => $planId, 'institution_id' => $iid])['count'] ?? 0) + 1);
            crud()->insert('fp_waypoints', [
                'institution_id' => $iid, 'floor_plan_id' => $planId, 'label' => $label,
                'x_percent' => (float) ($_POST['x'] ?? 50), 'y_percent' => (float) ($_POST['y'] ?? 50),
                'type' => in_array($_POST['type'] ?? '', ['normal', 'corner'], true) ? $_POST['type'] : 'normal',
            ]);
            sync_institution_config($iid);
            flash('success', 'Waypoint added. Drag it into place and use Path mode to chain them.');
        }

        if ($action === 'waypoints-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            foreach (($_POST['waypoints'] ?? []) as $w) {
                $wid = (int) ($w['id'] ?? 0);
                if (!$wid) continue;
                crud()->update('fp_waypoints', [
                    'x_percent' => max(0, min(100, (float) ($w['x'] ?? 0))),
                    'y_percent' => max(0, min(100, (float) ($w['y'] ?? 0))),
                    'label' => trim($w['label'] ?? '') ?: 'Waypoint',
                    'type' => in_array($w['type'] ?? '', ['normal', 'corner'], true) ? $w['type'] : 'normal',
                ], ['id' => $wid, 'institution_id' => $iid]);
            }
            if (isset($_POST['waypoint_delete']) && $_POST['waypoint_delete'] !== '') {
                crud()->delete('fp_waypoints', ['id' => (int) $_POST['waypoint_delete'], 'institution_id' => $iid]);
            }
            sync_institution_config($iid);
            flash('success', 'Waypoints saved.');
        }

        if ($action === 'waypoint-delete') {
            crud()->delete('fp_waypoints', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Waypoint removed.');
        }

        if ($action === 'path-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $name = trim($_POST['name'] ?? '') ?: 'Route';
            $pid = (int) ($_POST['path_id'] ?? 0);
            $nodes = $_POST['nodes'] ?? [];
            $nodesJson = json_encode(array_values(array_filter(array_map('intval', $nodes))), JSON_UNESCAPED_UNICODE);
            if ($pid) {
                crud()->update('fp_navigation_paths', ['name' => $name, 'nodes_json' => $nodesJson], ['id' => $pid, 'institution_id' => $iid]);
            } else {
                crud()->insert('fp_navigation_paths', [
                    'institution_id' => $iid, 'floor_plan_id' => $planId, 'name' => $name,
                    'nodes_json' => $nodesJson, 'created_by' => (int) current_user()['id'],
                ]);
            }
            sync_institution_config($iid);
            flash('success', 'Path saved.');
        }

        if ($action === 'path-delete') {
            crud()->delete('fp_navigation_paths', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Path removed.');
        }

        if ($action === 'connection-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $fromMarker = (int) ($_POST['from_marker_id'] ?? 0);
            if (!$fromMarker) throw new RuntimeException('Choose an exit marker.');
            $cid = (int) ($_POST['connection_id'] ?? 0);
            if ($cid) {
                crud()->update('fp_connections', [
                    'from_marker_id' => $fromMarker,
                    'to_floor_plan_id' => (int) ($_POST['to_floor_plan_id'] ?? 0) ?: null,
                    'to_marker_id' => (int) ($_POST['to_marker_id'] ?? 0) ?: null,
                    'to_scene_id' => (int) ($_POST['to_scene_id'] ?? 0) ?: null,
                    'to_building_id' => (int) ($_POST['to_building_id'] ?? 0) ?: null,
                    'note' => trim($_POST['note'] ?? '') ?: null,
                ], ['id' => $cid, 'institution_id' => $iid]);
            } else {
                crud()->insert('fp_connections', [
                    'institution_id' => $iid, 'from_floor_plan_id' => $planId, 'from_marker_id' => $fromMarker,
                    'to_floor_plan_id' => (int) ($_POST['to_floor_plan_id'] ?? 0) ?: null,
                    'to_marker_id' => (int) ($_POST['to_marker_id'] ?? 0) ?: null,
                    'to_scene_id' => (int) ($_POST['to_scene_id'] ?? 0) ?: null,
                    'to_building_id' => (int) ($_POST['to_building_id'] ?? 0) ?: null,
                    'note' => trim($_POST['note'] ?? '') ?: null,
                ]);
            }
            sync_institution_config($iid);
            flash('success', 'Exit connection saved.');
        }

        if ($action === 'connection-delete') {
            crud()->delete('fp_connections', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Exit connection removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/floor-plans' . ($studioPlanId ? '?studio=' . $studioPlanId : ''));
}

$plansQuery = "SELECT fp.*, (SELECT COUNT(*) FROM floor_plan_markers m WHERE m.floor_plan_id=fp.id) AS marker_count,
                        (SELECT 1 FROM institutions i WHERE i.id=:iid2 AND i.starting_floor_plan_id=fp.id) AS is_start
                        FROM floor_plans fp WHERE fp.institution_id=:iid";
$plansParams = ['iid' => $iid, 'iid2' => $iid];
if ($filterBuildingId) {
    // Floor plans don't have a building_id directly; filter by plans that have markers pointing to this building
    // Show all plans that have at least one marker targeting this building, or all if not narrowed
    // For a simple UX: just show all plans with a notice that they can assign markers to that building
}
$plans = crud()->raw($plansQuery . ' ORDER BY fp.created_at DESC', $plansParams);

// studio data
$studio = null;
$markers = [];
$waypoints = [];
$paths = [];
$connections = [];
if ($studioPlanId) {
    $studio = crud()->raw("SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid", ['id' => $studioPlanId, 'iid' => $iid])->fetch() ?: null;
    if ($studio) {
        $markers = crud()->raw("SELECT * FROM floor_plan_markers WHERE floor_plan_id=? AND institution_id=? ORDER BY sort_order, id", [$studioPlanId, $iid])->fetchAll();
        $waypoints = crud()->raw("SELECT * FROM fp_waypoints WHERE floor_plan_id=? AND institution_id=? ORDER BY sort_order, id", [$studioPlanId, $iid])->fetchAll();
        $paths = crud()->raw("SELECT * FROM fp_navigation_paths WHERE floor_plan_id=? AND institution_id=? ORDER BY id", [$studioPlanId, $iid])->fetchAll();
        $connections = crud()->raw("SELECT * FROM fp_connections WHERE from_floor_plan_id=? AND institution_id=? ORDER BY id", [$studioPlanId, $iid])->fetchAll();
    }
}

$buildings = crud()->raw("SELECT id,name FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name", [$iid])->fetchAll();
$rooms = crud()->raw("SELECT id,name FROM rooms WHERE institution_id=? AND deleted_at IS NULL ORDER BY name", [$iid])->fetchAll();
$scenes = crud()->raw("SELECT id,title as name FROM tour_scenes WHERE institution_id=? AND deleted_at IS NULL ORDER BY title", [$iid])->fetchAll();
$floorPlansList = crud()->raw("SELECT id,title as name FROM floor_plans WHERE institution_id=? AND deleted_at IS NULL AND id!=? ORDER BY title", [$iid, $studioPlanId ?: 0])->fetchAll();
?>

<?php if ($studio): ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <a class="back-link" href="floor-plans">← All floor plans</a>
      <div class="d-flex align-items-center gap-2 mt-1">
        <h3 class="mb-0 fw-800"><?= h($studio['title']) ?> — marker studio</h3>
        <?php if ($studio['is_campus_landing']): ?><span class="badge badge-live">landing</span><?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-ia btn-sm" data-bs-toggle="modal" data-bs-target="#fp-update-image-modal"><?= ia_icon('image', 14) ?> Change image</button>
      <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#marker-modal"><?= ia_icon('map', 16) ?> Add marker</button>
    </div>
  </div>

  <!-- AR studio mode toolbar -->
  <div class="ia-card p-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="ia-meta-md fw-semibold me-1"><?= ia_icon('cube', 14) ?> AR Studio mode:</span>
      <div class="btn-group flex-wrap" id="ar-mode-tabs" role="group">
        <button type="button" class="btn btn-sm btn-grad" data-ar-mode="markers"><?= ia_icon('geo', 13) ?> AR Markers</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="waypoints"><?= ia_icon('route', 13) ?> Waypoints</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="paths"><?= ia_icon('signpost', 13) ?> Paths</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="connections"><?= ia_icon('door', 13) ?> Exit Links</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="compass"><?= ia_icon('compass', 13) ?> Compass</button>
      </div>
      <div class="ms-auto d-none" id="compass-readout">
        <form method="post" class="d-flex align-items-center gap-2">
          <input type="hidden" name="fp_action" value="compass-save">
          <input type="hidden" name="id" value="<?= (int) $studio['id'] ?>">
          <input type="hidden" name="north_angle" id="north-angle-input" value="<?= (float) ($studio['north_angle'] ?? 0) ?>">
          <span class="ia-meta-md">North angle:</span>
          <strong id="compass-value"><?= (float) ($studio['north_angle'] ?? 0) ?>°</strong>
          <button class="btn btn-grad btn-sm px-3" type="submit">Save compass</button>
        </form>
      </div>
    </div>
  </div>

  <!-- update image modal -->
  <div class="modal fade" id="fp-update-image-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Change floor plan image</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="fp_action" value="update-image">
        <input type="hidden" name="id" value="<?= (int) $studio['id'] ?>">
        <div class="modal-body d-grid gap-3">
          <?php
          $pickerName  = 'image';
          $pickerValue = $studio['image_path'] ?? '';
          $pickerLabel = 'New Image (PNG/JPG)';
          $pickerHelp  = 'Aspect ratio and marker positions are preserved. Only the image file is replaced.';
          require __DIR__ . '/../layout/media-picker.php';
          ?>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Update image</button></div>
      </form>
    </div></div>
  </div>

  <!-- MARKERS FORM -->
  <form method="post" id="fp-markers-form">
    <input type="hidden" name="fp_action" value="markers-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <div class="ia-card p-3">
      <div id="map-stage" class="map-stage"
           style="--fp-ar: <?= $studio['aspect_ratio'] ?>">
        <img src="<?= h(media_url($studio['image_path'])) ?>" alt="floor plan"
             class="map-stage-img">
        <div id="fp-path-layer"></div>
        <input type="hidden" name="markers-hash" id="markers-hash">

        <?php foreach ($markers as $mkIdx => $mk): ?>
          <?php $mtype = in_array($mk['marker_type'] ?? '', ['scene', 'entrance', 'exit'], true) ? $mk['marker_type'] : 'scene'; ?>
          <div class="fp-marker-dot" data-id="<?= (int) $mk['id'] ?>"
               data-mktype="<?= $mtype ?>"
               data-name="<?= h($mk['label'], ENT_QUOTES) ?>"
               style="left:<?= (float) $mk['x_percent'] ?>%;top:<?= (float) $mk['y_percent'] ?>%">
            <span class="fp-facing-arrow" style="--facing: <?= (float) ($mk['facing_angle'] ?? 0) ?>deg"></span>
            <span class="fp-mktype-badge"><?= $mtype === 'entrance' ? 'IN' : ($mtype === 'exit' ? 'OUT' : 'SC') ?></span>
            <input type="hidden" name="markers[<?= $mkIdx ?>][id]" value="<?= (int) $mk['id'] ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][x]" value="<?= (float) $mk['x_percent'] ?>" class="mk-x">
            <input type="hidden" name="markers[<?= $mkIdx ?>][y]" value="<?= (float) $mk['y_percent'] ?>" class="mk-y">
            <input type="hidden" name="markers[<?= $mkIdx ?>][label]" value="<?= h($mk['label']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][marker_type]" value="<?= $mtype ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][facing_angle]" value="<?= (float) ($mk['facing_angle'] ?? 0) ?>" class="mk-facing">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_building_id]" value="<?= h($mk['target_building_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_room_id]" value="<?= h($mk['target_room_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_scene_id]" value="<?= h($mk['target_scene_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_floor_plan_id]" value="<?= h($mk['target_floor_plan_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][popup_title]" value="<?= h($mk['popup_title']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][popup_html]" value="<?= h($mk['popup_html']) ?>">
          </div>
        <?php endforeach; ?>

        <?php foreach ($waypoints as $wpIdx => $wp): ?>
          <div class="fp-wp-dot<?= $wp['type'] === 'corner' ? ' is-corner' : '' ?>"
               data-wp-id="<?= (int) $wp['id'] ?>"
               data-name="<?= h($wp['label'], ENT_QUOTES) ?>"
               style="left:<?= (float) $wp['x_percent'] ?>%;top:<?= (float) $wp['y_percent'] ?>%">
            <input type="hidden" name="waypoints[<?= $wpIdx ?>][id]" value="<?= (int) $wp['id'] ?>">
            <input type="hidden" name="waypoints[<?= $wpIdx ?>][x]" value="<?= (float) $wp['x_percent'] ?>" class="wp-x">
            <input type="hidden" name="waypoints[<?= $wpIdx ?>][y]" value="<?= (float) $wp['y_percent'] ?>" class="wp-y">
            <input type="hidden" name="waypoints[<?= $wpIdx ?>][label]" value="<?= h($wp['label']) ?>">
            <input type="hidden" name="waypoints[<?= $wpIdx ?>][type]" value="<?= $wp['type'] ?>" class="wp-type">
          </div>
        <?php endforeach; ?>

        <div id="fp-compass" class="fp-compass" style="--north: <?= (float) ($studio['north_angle'] ?? 0) ?>deg">
          <div class="fp-compass-dial"></div>
          <div class="fp-compass-needle"></div>
          <div class="fp-compass-n">N</div>
        </div>

        <div class="fp-hint" id="fp-hint">Drag dots to position them. Click a marker to edit its link, type &amp; facing. Use the AR Studio toolbar to switch layers.</div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="d-flex gap-2 flex-wrap" id="marker-list">
          <?php foreach ($markers as $mk): ?>
            <button type="button" class="btn btn-sm btn-outline-ia" data-select-dot="<?= (int) $mk['id'] ?>">
              <span class="chip-dot"></span><?= h($mk['label']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-grad px-4" type="submit">Save marker positions</button>
      </div>
    </div>
  </form>

  <!-- WAYPOINTS FORM -->
  <form method="post" id="fp-waypoints-form" class="d-none">
    <input type="hidden" name="fp_action" value="waypoints-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <div class="ia-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <div>
          <h5 class="mb-1">Waypoints</h5>
          <p class="ia-meta-md mb-0">Waypoints form the walking graph the AR guide follows. Corners (⤺) are curve points on turns.</p>
        </div>
        <button type="submit" class="btn btn-grad px-4">Save waypoints</button>
      </div>
      <div id="wp-list" class="d-grid gap-2"></div>
      <input type="hidden" name="waypoint_delete" id="waypoint_delete" value="">
    </div>
  </form>

  <!-- PATHS FORM -->
  <form method="post" id="fp-paths-form" class="d-none">
    <input type="hidden" name="fp_action" value="path-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <div class="ia-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <div>
          <h5 class="mb-1">Navigation Paths</h5>
          <p class="ia-meta-md mb-0">Chain waypoints in order. Click a waypoint on the map to append it to the current path.</p>
        </div>
        <button type="submit" class="btn btn-grad px-4">Save path</button>
      </div>
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <input type="hidden" name="path_id" id="path-id" value="">
        <input class="form-control" name="name" id="path-name" placeholder="e.g. Main Gate → Library" style="max-width:280px">
        <select class="form-select" id="path-picker" style="max-width:220px">
          <option value="">+ New path</option>
          <?php foreach ($paths as $p): ?><option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-ia" id="path-clear">Clear route</button>
        <button type="button" class="btn btn-outline-ia text-danger" id="path-delete-btn">Delete path</button>
      </div>
      <div id="path-nodes" class="d-flex gap-2 flex-wrap align-items-center"></div>
      <input type="hidden" name="nodes" id="path-nodes-input">
    </div>
  </form>

  <!-- CONNECTIONS FORM -->
  <form method="post" id="fp-connections-form" class="d-none">
    <input type="hidden" name="fp_action" value="connection-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <div class="ia-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <div>
          <h5 class="mb-1">Exit Connections</h5>
          <p class="ia-meta-md mb-0">Link an <strong>exit</strong> marker to a destination (another floor plan, building or 360 scene). This is optional — not all exits need a link.</p>
        </div>
        <button type="submit" class="btn btn-grad px-4">Save connection</button>
      </div>
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <input type="hidden" name="connection_id" id="con-id" value="">
        <select class="form-select" id="con-marker-picker" style="max-width:240px" required>
          <option value="">Choose an exit marker…</option>
          <?php foreach ($markers as $mk): if (($mk['marker_type'] ?? '') === 'exit'): ?>
            <option value="<?= (int) $mk['id'] ?>"><?= h($mk['label']) ?></option>
          <?php endif; endforeach; ?>
        </select>
        <select class="form-select" name="to_floor_plan_id" id="con-fp" style="max-width:220px">
          <option value="">→ Sub-floor plan…</option>
          <?php foreach ($floorPlansList as $fp): ?><option value="<?= (int) $fp['id'] ?>"><?= h($fp['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="to_scene_id" id="con-scene" style="max-width:220px">
          <option value="">→ 360 scene…</option>
          <?php foreach ($scenes as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="to_marker_id" id="con-marker-to" style="max-width:220px">
          <option value="">→ entrance marker…</option>
          <?php foreach ($markers as $mk): if (($mk['marker_type'] ?? '') === 'entrance'): ?>
            <option value="<?= (int) $mk['id'] ?>"><?= h($mk['label']) ?></option>
          <?php endif; endforeach; ?>
        </select>
        <select class="form-select" name="to_building_id" id="con-building" style="max-width:200px">
          <option value="">→ building…</option>
          <?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex gap-2 mb-3">
        <input class="form-control" name="note" id="con-note" placeholder="Note (optional)" style="max-width:340px">
        <button type="button" class="btn btn-outline-ia text-danger" id="con-delete-btn">Delete connection</button>
      </div>
      <div id="con-list" class="d-flex gap-2 flex-wrap"></div>
    </div>
  </form>

  <!-- marker fields panel -->
  <div class="ia-card mt-3">
    <div class="card-head"><h3>Selected marker</h3></div>
    <div class="card-body" id="marker-fields">
      <p class="ia-meta-md mb-0">Select a marker dot or a chip above to edit its label and popup.</p>
    </div>
  </div>

  <!-- add marker modal -->
  <div class="modal fade" id="marker-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add marker</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post">
        <input type="hidden" name="fp_action" value="marker-add">
        <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
        <input type="hidden" name="x" value="50"><input type="hidden" name="y" value="50">
        <div class="modal-body d-grid gap-3">
          <div><label class="form-label">Label</label><input class="form-control" name="label" required placeholder="Library"></div>
          <div>
            <label class="form-label">AR Marker type</label>
            <select class="form-select" name="marker_type">
              <option value="scene">Scene (a 360 / AR point of interest)</option>
              <option value="entrance">Entrance (visitor enters here)</option>
              <option value="exit">Exit (visitor leaves / can link to another floor)</option>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Room</label>
              <select class="form-select" name="target_room_id"><option value="">— none —</option><?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Building</label>
              <select class="form-select" name="target_building_id"><option value="">— none —</option><?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Link to 360 Tour</label>
              <select class="form-select" name="target_scene_id"><option value="">— none —</option><?php foreach ($scenes as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Link to Sub-Floor Plan</label>
              <select class="form-select" name="target_floor_plan_id"><option value="">— none —</option><?php foreach ($floorPlansList as $fp): ?><option value="<?= (int) $fp['id'] ?>"><?= h($fp['name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="form-text mb-1">
            <strong>Click action priority:</strong> 360 Tour &gt; Sub-Floor Plan &gt; Popup. Set only one target for clean behavior.
          </div>
          <div><label class="form-label">Popup title</label><input class="form-control" name="popup_title" placeholder="Library hours &amp; info"></div>
          <div><label class="form-label">Popup content (HTML)</label><textarea class="form-control" name="popup_html" rows="3" placeholder="Open Mon–Fri 8am–6pm"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit" disabled id="marker-add-go">Add marker</button></div>
      </form>
    </div></div>
  </div>

  <script>
  window._fpScenes = <?= json_encode(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], $scenes), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpPlans  = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $floorPlansList), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpWaypoints = <?= json_encode($waypoints, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpPaths      = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'nodes' => json_decode($p['nodes_json'] ?? '[]', true) ?: []], $paths), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpConnections = <?= json_encode($connections, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpBuildings = <?= json_encode(array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name']], $buildings), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpRooms     = <?= json_encode(array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name']], $rooms), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpNorth     = <?= (float) ($studio['north_angle'] ?? 0) ?>;
  </script>
  <script>
  (() => {
    const stage = document.getElementById('map-stage')
    const fields = document.getElementById('marker-fields')
    const pathLayer = document.getElementById('fp-path-layer')
    const compassEl = document.getElementById('fp-compass')
    let current = null

    const escapeHtml = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    const escapeAttr = (s) => Array.from(String(s ?? '')).map((c) => escapeHtml(c)).join('').replace(/'/g,'&#39;')
    const qsa = (s) => Array.from(stage.querySelectorAll(s))

    // ── AR Studio mode switching ──────────────────────────────
    let mode = 'markers'
    function setMode(m) {
      mode = m
      Array.from(document.querySelectorAll('[data-ar-mode]')).forEach(b => {
        b.classList.toggle('btn-grad', b.dataset.arMode === m)
        b.classList.toggle('btn-outline-ia', b.dataset.arMode !== m)
      })
      document.getElementById('fp-markers-form').classList.toggle('d-none', m !== 'markers')
      document.getElementById('fp-waypoints-form').classList.toggle('d-none', m !== 'waypoints')
      document.getElementById('fp-paths-form').classList.toggle('d-none', m !== 'paths')
      document.getElementById('fp-connections-form').classList.toggle('d-none', m !== 'connections')
      document.getElementById('compass-readout').classList.toggle('d-none', m !== 'compass')
      qsa('.fp-wp-dot').forEach(d => d.classList.toggle('mode-visible', m === 'waypoints' || m === 'paths'))
      qsa('.fp-marker-dot').forEach(d => d.classList.toggle('mode-visible', m === 'markers' || m === 'connections'))
      compassEl.classList.toggle('mode-visible', m === 'compass')
      if (m === 'paths') drawPaths()
      if (m === 'markers') clearMkSvg()
      if (m === 'waypoints') { renderWpList(); clearMkSvg(); drawPaths() }
    }
    document.getElementById('ar-mode-tabs').addEventListener('click', (e) => {
      const b = e.target.closest('[data-ar-mode]')
      if (b) setMode(b.dataset.arMode)
    })

    const setFacing = (dot, deg) => {
      deg = ((parseFloat(deg) || 0) + 360) % 360
      const h = dot.querySelector('.mk-facing')
      if (h) h.value = deg.toFixed(1)
      const arrow = dot.querySelector('.fp-facing-arrow')
      if (arrow) arrow.style.setProperty('--facing', deg + 'deg')
    }

    // ── Marker fields panel ───────────────────────────────────
    const renderFields = (dot) => {
      const hv = (sel) => dot.querySelector(sel)?.value || ''
      const popT = hv('input[name$="[popup_title]"]')
      const popH = hv('input[name$="[popup_html]"]')
      const sceneId = hv('input[name$="[target_scene_id]"]')
      const fpId = hv('input[name$="[target_floor_plan_id]"]')
      const bldId = hv('input[name$="[target_building_id]"]')
      const roomId = hv('input[name$="[target_room_id]"]')
      const mtype = dot.dataset.mktype || 'scene'
      const facing = hv('.mk-facing')

      let amode = 'popup'
      if (sceneId && sceneId !== '0') amode = 'scene'
      else if (fpId && fpId !== '0') amode = 'floorplan'

      fields.innerHTML = `
        <div class="row g-3 mb-3">
          <div class="col-md-3"><label class="form-label fw-semibold">Label</label>
            <input class="form-control" value="${escapeAttr(dot.dataset.name)}" oninput="syncField(this,'label')"></div>
          <div class="col-md-3"><label class="form-label fw-semibold">AR type</label>
            <select class="form-select" onchange="this.setAttribute('data-newtype',this.value); syncField(this,'marker_type'); applyMkType(this.value)">
              <option value="scene" ${mtype==='scene'?'selected':''}>Scene</option>
              <option value="entrance" ${mtype==='entrance'?'selected':''}>Entrance</option>
              <option value="exit" ${mtype==='exit'?'selected':''}>Exit</option>
            </select></div>
          <div class="col-md-3"><label class="form-label fw-semibold">Facing (°)</label>
            <input class="form-control" value="${escapeAttr(facing)}" oninput="syncFacing(this)"></div>
          <div class="col-md-3"><label class="form-label fw-semibold">Position</label>
            <div class="d-flex gap-2">
              <input class="form-control mk-in-x" placeholder="X%" value="${hv('.mk-x')}" oninput="syncPos(this,'x')">
              <input class="form-control mk-in-y" placeholder="Y%" value="${hv('.mk-y')}" oninput="syncPos(this,'y')">
            </div>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><label class="form-label fw-semibold">Building</label>
            <select class="form-select" onchange="syncField(this,'target_building_id')">
              <option value="">— none —</option>
              ${window._fpBuildings.map(b=>`<option value="${b.id}" ${bldId==b.id?'selected':''}>${escapeHtml(b.name)}</option>`).join('')}
            </select></div>
          <div class="col-md-4"><label class="form-label fw-semibold">Room</label>
            <select class="form-select" onchange="syncField(this,'target_room_id')">
              <option value="">— none —</option>
              ${window._fpRooms.map(r=>`<option value="${r.id}" ${roomId==r.id?'selected':''}>${escapeHtml(r.name)}</option>`).join('')}
            </select></div>
          <div class="col-md-4"><label class="form-label fw-semibold">Popup title</label>
            <input class="form-control" value="${escapeAttr(popT)}" oninput="syncField(this,'popup_title')"></div>
        </div>
        <p class="form-label form-label-sm fw-semibold mb-2">Click action — what happens when visitor taps this marker?</p>
        <div class="d-flex gap-2 flex-wrap mb-3">
          ${['popup','scene','floorplan'].map(mk => `<button type="button" class="btn btn-sm ${amode===mk?'btn-grad':'btn-outline-ia'}" data-mk-mode="${mk}">${mk==='popup'?'💬 Popup':mk==='scene'?'🎥 360 Tour':'🗺 Sub-Floor Plan'}</button>`).join('')}
        </div>
        <div id="mk-panel-popup" class="mk-panel ${amode==='popup'?'':'d-none'}">
          <label class="form-label form-label-sm">Popup content (HTML or plain text)</label>
          <textarea class="form-control" rows="2" oninput="syncField(this,'popup_html')">${escapeHtml(popH)}</textarea>
        </div>
        <div id="mk-panel-scene" class="mk-panel ${amode==='scene'?'':'d-none'}">
          <label class="form-label form-label-sm">Link to 360 Tour scene</label>
          <select class="form-select" onchange="syncField(this,'target_scene_id')">
            <option value="">— none —</option>
            ${window._fpScenes.map(s=>`<option value="${s.id}" ${sceneId==s.id?'selected':''}>${escapeHtml(s.name)}</option>`).join('')}
          </select>
        </div>
        <div id="mk-panel-floorplan" class="mk-panel ${amode==='floorplan'?'':'d-none'}">
          <label class="form-label form-label-sm">Link to Sub-Floor Plan</label>
          <select class="form-select" onchange="syncField(this,'target_floor_plan_id')">
            <option value="">— none —</option>
            ${window._fpPlans.map(p=>`<option value="${p.id}" ${fpId==p.id?'selected':''}>${escapeHtml(p.name)}</option>`).join('')}
          </select>
        </div>
        <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span class="ia-micro">${mtype==='entrance'?'Entrance = where visitors enter this floor. ':mtype==='exit'?'Exit = where visitors leave — link it in Exit Links mode. ':''}Drag the arrow to set the facing direction the scene/feature eyes.</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger" onclick="deleteMarker()">Delete this marker</button>
        </div>`

      fields.querySelectorAll('[data-mk-mode]').forEach(btn => {
        btn.addEventListener('click', () => {
          const m = btn.dataset.mkMode
          fields.querySelectorAll('[data-mk-mode]').forEach(b => b.className = b.className.replace('btn-grad','btn-outline-ia'))
          btn.className = btn.className.replace('btn-outline-ia','btn-grad')
          fields.querySelectorAll('[id^="mk-panel-"]').forEach(p => p.classList.add('d-none'))
          fields.querySelector(`#mk-panel-${m}`).classList.remove('d-none')
          if (m !== 'scene') syncField({value:''}, 'target_scene_id')
          if (m !== 'floorplan') syncField({value:''}, 'target_floor_plan_id')
        })
      })
      current = dot
    }

    window.syncField = (el, key) => {
      if (!current) return
      const box = current
      if (key === 'label') {
        const hiddenEl = box.querySelector('input[name$="][label]"]')
        if (hiddenEl) hiddenEl.value = el.value
        box.dataset.name = el.value
        const chip = document.querySelector(`[data-select-dot="${box.dataset.id}"]`)
        if (chip) chip.lastChild.textContent = el.value
      } else {
        const hiddenEl = box.querySelector(`input[name$="[${key}]"]`) || box.querySelector(`input[name$="${key}"]`)
        if (hiddenEl) hiddenEl.value = el.value
      }
    }
    window.applyMkType = (t) => {
      const dot = current
      if (!dot) return
      dot.dataset.mktype = t
      const badge = dot.querySelector('.fp-mktype-badge')
      if (badge) badge.textContent = t === 'entrance' ? 'IN' : (t === 'exit' ? 'OUT' : 'SC')
    }
    window.syncFacing = (el) => { if (current) setFacing(current, el.value) }
    window.syncPos = (el, axis) => {
      if (!current) return
      const v = Math.max(0, Math.min(100, parseFloat(el.value) || 0))
      const hiddenEl = current.querySelector(`.mk-${axis}`)
      if (hiddenEl) hiddenEl.value = v
      current.style[axis === 'x' ? 'left' : 'top'] = v + '%'
      if (axis === 'x') current.querySelector('.mk-in-x') && (current.querySelector('.mk-in-x').value = v)
      else current.querySelector('.mk-in-y') && (current.querySelector('.mk-in-y').value = v)
    }
    window.deleteMarker = async () => {
      if (!current) return
      const ok = await window.iaConfirm(`Delete marker "${current.dataset.name}"?`, 'Delete marker')
      if (!ok) return
      const id = current.dataset.id
      const del = document.querySelector('input[name="marker_delete"]') || (() => { const el = document.createElement('input'); el.type='hidden'; el.name='marker_delete'; el.value=''; stage.appendChild(el); return el })()
      del.value = id
      current.remove()
      const chip = document.querySelector(`[data-select-dot="${id}"]`)
      if (chip) chip.remove()
      current = null
      fields.innerHTML = '<div class="ia-meta-md mb-0">Marker deleted. Save to commit.</div>'
    }

    // ── dragging ──────────────────────────────────────────────
    function makeDraggable(dot, xEl, yEl, onUp) {
      dot.addEventListener('pointerdown', (e) => {
        e.preventDefault()
        let dragging = false
        const downX = e.clientX, downY = e.clientY
        const move = (ev) => {
          const dx = ev.clientX - downX, dy = ev.clientY - downY
          if (!dragging && Math.hypot(dx, dy) < 5) return
          dragging = true
          const rect = stage.getBoundingClientRect()
          const x = Math.max(0, Math.min(100, ((ev.clientX - rect.left) / rect.width) * 100))
          const y = Math.max(0, Math.min(100, ((ev.clientY - rect.top) / rect.height) * 100))
          xEl.value = x.toFixed(4); yEl.value = y.toFixed(4)
          dot.style.left = x + '%'; dot.style.top = y + '%'
        }
        const up = () => {
          dot.removeEventListener('pointermove', move)
          dot.removeEventListener('pointerup', up)
          onUp && onUp(dragging)
        }
        dot.addEventListener('pointermove', move)
        dot.addEventListener('pointerup', up)
      })
    }

    qsa('.fp-marker-dot').forEach((dot) => {
      makeDraggable(dot, dot.querySelector('.mk-x'), dot.querySelector('.mk-y'), (dragging) => { if (dragging && current === dot) renderFields(dot) })
      dot.addEventListener('pointerup', () => { if (current !== dot) renderFields(dot) })
      const arrow = dot.querySelector('.fp-facing-arrow')
      if (arrow) {
        arrow.addEventListener('pointerdown', (e) => {
          e.preventDefault(); e.stopPropagation()
          const rotate = (ev) => {
            const r = stage.getBoundingClientRect()
            const cx = r.left + r.width / 2, cy = r.top + r.height / 2
            const ang = (Math.atan2(ev.clientY - cy, ev.clientX - cx) * 180 / Math.PI + 90 + 360) % 360
            setFacing(dot, ang)
          }
          const up = () => { document.removeEventListener('pointermove', rotate); document.removeEventListener('pointerup', up) }
          document.addEventListener('pointermove', rotate)
          document.addEventListener('pointerup', up)
        })
      }
    })
    document.querySelectorAll('[data-select-dot]').forEach((chip) => {
      chip.addEventListener('click', () => {
        const dot = stage.querySelector(`.fp-marker-dot[data-id="${chip.dataset.selectDot}"]`)
        if (dot) renderFields(dot)
      })
    })

    // ── Waypoints ─────────────────────────────────────────────
    const wpName = (d) => {
      const l = d.querySelector('input[name$="][label]"]')
      return (l ? l.value : '') || d.dataset.name || 'Waypoint'
    }
    function renderWpList() {
      const wrap = document.getElementById('wp-list')
      wrap.innerHTML = ''
      qsa('.fp-wp-dot').forEach(d => {
        const id = d.dataset.wpId
        const row = document.createElement('div')
        row.className = 'd-flex align-items-center gap-2 p-2 border rounded'
        const isCorner = d.classList.contains('is-corner')
        row.innerHTML = `
          <span class="badge rounded-pill ${isCorner?'text-bg-warning':'text-bg-secondary'}">${isCorner?'⤺ corner':'· node'}</span>
          <input class="form-control form-control-sm wp-name" data-wp="${id}" value="${escapeAttr(wpName(d))}" style="max-width:220px">
          <span class="ia-micro">${Math.round(parseFloat(d.querySelector('.wp-x').value))}%, ${Math.round(parseFloat(d.querySelector('.wp-y').value))}%</span>
          <label class="form-check ms-auto mb-0 d-flex align-items-center gap-1">
            <input class="form-check-input m-0 wp-corner" data-wp="${id}" type="checkbox" ${isCorner?'checked':''}> Corner
          </label>
          <button type="button" class="btn btn-sm btn-outline-ia text-danger wp-del" data-wp="${id}">Remove</button>`
        wrap.appendChild(row)
      })
      const addRow = document.createElement('div')
      addRow.className = 'd-flex align-items-center gap-2'
      addRow.innerHTML = `
        <input class="form-control wp-new-name" placeholder="New waypoint label (auto)" style="max-width:240px">
        <button type="button" class="btn btn-sm btn-outline-ia" id="wp-add-btn">+ Add waypoint</button>`
      wrap.appendChild(addRow)
      addRow.querySelector('#wp-add-btn').addEventListener('click', () => addWaypoint())
    }
    // waypoint name/corner edits sync hidden inputs
    document.getElementById('wp-list').addEventListener('input', (e) => {
      const d = stage.querySelector(`.fp-wp-dot[data-wp-id="${e.target.dataset.wp}"]`)
      if (!d) return
      if (e.target.classList.contains('wp-name')) {
        const l = d.querySelector('input[name$="][label]"]')
        if (l) l.value = e.target.value
        d.dataset.name = e.target.value
      }
    })
    document.getElementById('wp-list').addEventListener('change', (e) => {
      const d = stage.querySelector(`.fp-wp-dot[data-wp-id="${e.target.dataset.wp}"]`)
      if (!d) return
      if (e.target.classList.contains('wp-corner')) {
        d.classList.toggle('is-corner', e.target.checked)
        const t = d.querySelector('input[name$="][type]"]'); if (t) t.value = e.target.checked ? 'corner' : 'normal'
      }
    })
    document.getElementById('wp-list').addEventListener('click', (e) => {
      const btn = e.target.closest('.wp-del')
      if (!btn) return
      const d = stage.querySelector(`.fp-wp-dot[data-wp-id="${btn.dataset.wp}"]`)
      if (!d) return
      ;(async () => {
        const ok = await window.iaConfirm(`Remove waypoint "${d.dataset.name}"?`, 'Remove waypoint')
        if (!ok) return
        const del = document.getElementById('waypoint_delete')
        if (del) del.value = btn.dataset.wp
        d.remove()
        renderWpList()
      })()
    })
    function addWaypoint() {
      const nameInput = document.querySelector('.wp-new-name')
      const name = nameInput ? nameInput.value.trim() : ''
      const f = document.createElement('form')
      f.method = 'post'
      f.innerHTML = `
        <input type="hidden" name="fp_action" value="waypoint-add">
        <input type="hidden" name="plan_id" value="${document.querySelector('#fp-waypoints-form input[name="plan_id"]').value}">
        <input type="hidden" name="x" value="50"><input type="hidden" name="y" value="50">
        <input type="hidden" name="type" value="normal">
        <input type="hidden" name="label" value="${escapeAttr(name || 'Waypoint')}">`
      document.body.appendChild(f); f.submit()
    }

    // waypoint dragging + path-mode append
    bindWaypointDrag()
    function bindWaypointDrag() {
      qsa('.fp-wp-dot').forEach((d) => {
        if (d.dataset.dragBound) return
        d.dataset.dragBound = '1'
        makeDraggable(d, d.querySelector('.wp-x'), d.querySelector('.wp-y'), () => { if (mode === 'paths') drawPaths() })
        d.addEventListener('click', () => {
          if (mode !== 'paths') return
          const id = d.dataset.wpId
          if (id === currentPathNodes[currentPathNodes.length - 1]) return
          currentPathNodes.push(id)
          drawPaths()
        })
      })
    }

    // ── Paths ─────────────────────────────────────────────────
    let currentPathNodes = []
    const wpById = () => { const o = {}; qsa('.fp-wp-dot').forEach(d => o[d.dataset.wpId] = { x: parseFloat(d.querySelector('.wp-x').value), y: parseFloat(d.querySelector('.wp-y').value), label: wpName(d) }); return o }
    function clearMkSvg() { const s = document.getElementById('mk-path-svg'); if (s) s.remove() }
    function drawPaths() {
      clearMkSvg()
      const wp = wpById()
      const pts = currentPathNodes.map(id => wp[id]).filter(Boolean)
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
      svg.setAttribute('id', 'mk-path-svg')
      svg.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:2;overflow:visible'
      svg.setAttribute('viewBox', '0 0 100 100')
      svg.setAttribute('preserveAspectRatio', 'none')
      if (pts.length > 1) {
        const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline')
        poly.setAttribute('points', pts.map(p => `${p.x},${p.y}`).join(' '))
        poly.setAttribute('fill', 'none')
        poly.setAttribute('stroke', 'rgba(33,150,243,.9)')
        poly.setAttribute('stroke-width', '0.55')
        poly.setAttribute('stroke-linejoin', 'round')
        svg.appendChild(poly)
        // node markers
        pts.forEach(p => {
          const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle')
          c.setAttribute('cx', p.x); c.setAttribute('cy', p.y); c.setAttribute('r', '1.2')
          c.setAttribute('fill', '#2196f3')
          svg.appendChild(c)
        })
      }
      pathLayer.appendChild(svg)
      const wrap = document.getElementById('path-nodes')
      wrap.innerHTML = ''
      currentPathNodes.forEach(id => {
        const p = wp[id]
        if (!p) return
        const chip = document.createElement('span')
        chip.className = 'badge rounded-pill text-bg-primary'
        chip.textContent = `${p.label} (${id})`
        wrap.appendChild(chip)
      })
      document.getElementById('path-nodes-input').value = JSON.stringify(currentPathNodes)
    }
    function loadPathIntoEditor(p) {
      currentPathNodes = p ? (p.nodes || []).slice() : []
      document.getElementById('path-name').value = p ? p.name : ''
      document.getElementById('path-id').value = p ? p.id : ''
      drawPaths()
    }
    document.getElementById('path-picker').addEventListener('change', (e) => {
      const p = window._fpPaths.find(x => String(x.id) === e.target.value)
      loadPathIntoEditor(p)
    })
    document.getElementById('path-clear').addEventListener('click', () => loadPathIntoEditor(null))
    document.getElementById('path-delete-btn').addEventListener('click', async () => {
      const pid = document.getElementById('path-id').value
      if (!pid) { await window.iaConfirm('Select a path first.', 'Delete path'); return }
      const ok = await window.iaConfirm('Delete this path?', 'Delete path')
      if (!ok) return
      const f = document.createElement('form')
      f.method = 'post'
      f.innerHTML = `<input type="hidden" name="fp_action" value="path-delete"><input type="hidden" name="plan_id" value="${document.querySelector('#fp-paths-form input[name="plan_id"]').value}"><input type="hidden" name="id" value="${pid}">`
      document.body.appendChild(f); f.submit()
    })
    // path editor: clicking waypoint appends (handled in bindWaypointDrag)

    // ── Connections ───────────────────────────────────────────
    function renderConList() {
      const wrap = document.getElementById('con-list')
      wrap.innerHTML = ''
      window._fpConnections.forEach(c => {
        const btn = document.createElement('button')
        btn.type = 'button'
        btn.className = 'btn btn-sm btn-outline-ia'
        btn.dataset.con = c.id
        const dest = c.to_floor_plan_id ? 'floor ' + c.to_floor_plan_id : (c.to_scene_id ? 'scene ' + c.to_scene_id : (c.to_building_id ? 'building ' + c.to_building_id : (c.to_marker_id ? 'entrance ' + c.to_marker_id : '…')))
        btn.textContent = `exit #${c.from_marker_id} → ${dest}`
        btn.addEventListener('click', () => loadConnection(c))
        wrap.appendChild(btn)
      })
      window._fpConnections.forEach(c => {
        const del = document.createElement('button')
        del.type = 'button'
        del.className = 'btn btn-sm btn-outline-ia text-danger'
        del.dataset.con = c.id
        del.textContent = '— #' + c.id
        del.addEventListener('click', async () => {
          const ok = await window.iaConfirm('Delete this connection?', 'Delete connection')
          if (!ok) return
          const f = document.createElement('form')
          f.method = 'post'
          f.innerHTML = `<input type="hidden" name="fp_action" value="connection-delete"><input type="hidden" name="plan_id" value="${document.querySelector('#fp-connections-form input[name="plan_id"]').value}"><input type="hidden" name="id" value="${c.id}">`
          document.body.appendChild(f); f.submit()
        })
        wrap.appendChild(del)
      })
    }
    function loadConnection(c) {
      document.getElementById('con-id').value = c.id
      document.getElementById('con-marker-picker').value = c.from_marker_id || ''
      document.getElementById('con-fp').value = c.to_floor_plan_id || ''
      document.getElementById('con-scene').value = c.to_scene_id || ''
      document.getElementById('con-marker-to').value = c.to_marker_id || ''
      document.getElementById('con-building').value = c.to_building_id || ''
      document.getElementById('con-note').value = c.note || ''
    }
    document.getElementById('con-delete-btn').addEventListener('click', async () => {
      const cid = document.getElementById('con-id').value
      if (!cid) { await window.iaConfirm('Load a connection first.', 'Delete connection'); return }
      const ok = await window.iaConfirm('Delete this exit connection?', 'Delete connection')
      if (!ok) return
      const f = document.createElement('form')
      f.method = 'post'
      f.innerHTML = `<input type="hidden" name="fp_action" value="connection-delete"><input type="hidden" name="plan_id" value="${document.querySelector('#fp-connections-form input[name="plan_id"]').value}"><input type="hidden" name="id" value="${cid}">`
      document.body.appendChild(f); f.submit()
    })

    // ── Compass ───────────────────────────────────────────────
    function bindCompass() {
      const needle = compassEl.querySelector('.fp-compass-needle')
      let north = window._fpNorth || 0
      const apply = (deg) => {
        north = ((deg % 360) + 360) % 360
        compassEl.style.setProperty('--north', north + 'deg')
        needle.style.transform = `rotate(${north}deg)`
        document.getElementById('compass-value').textContent = Math.round(north) + '°'
        document.getElementById('north-angle-input').value = north.toFixed(1)
      }
      const fromEvent = (ev) => {
        const r = compassEl.getBoundingClientRect()
        const cx = r.left + r.width / 2, cy = r.top + r.height / 2
        apply((Math.atan2(ev.clientY - cy, ev.clientX - cx) * 180 / Math.PI + 90 + 360) % 360)
      }
      compassEl.addEventListener('pointerdown', (e) => {
        e.preventDefault()
        fromEvent(e)
        const move = (ev) => fromEvent(ev)
        const up = () => { document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up) }
        document.addEventListener('pointermove', move)
        document.addEventListener('pointerup', up)
      })
      apply(north)
    }

    // ── init ──────────────────────────────────────────────────
    setMode('markers')
    renderWpList()
    renderConList()
    bindCompass()
    loadPathIntoEditor(null)
  })()  </script>
<?php else: ?>
  <?php if ($filterBuildingId):
    $filterBuilding = null;
    foreach ($buildings as $bb) { if ((int)$bb['id'] === $filterBuildingId) { $filterBuilding = $bb; break; } }
    $buildings->execute([$iid]); // re-run for modal
  endif; ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <?php if ($filterBuildingId): ?>
        <a class="back-link" href="buildings">← Buildings</a>
        <div class="ia-meta-md mt-1">
          <?= ia_icon('building', 13) ?>
          Showing floor plans — assign markers to
          <strong><?= h($filterBuilding['name'] ?? 'Building #'.$filterBuildingId) ?></strong>
          using the <em>Studio</em> editor
        </div>
      <?php else: ?>
        <p class="mb-0 ia-meta-lg"><?= $plans->rowCount() ?> floor plan(s)</p>
      <?php endif ?>
    </div>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#fp-upload"><?= ia_icon('image', 16) ?> Upload floor plan</button>
  </div>


  <div class="ia-card">
    <table class="table table-ia">
      <thead><tr><th>Plan</th><th>Image</th><th>Markers</th><th>Landing</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($plans as $plan): ?>
          <tr>
            <td>
              <div class="fw-bold"><?= h($plan['title']) ?></div>
              <div class="ia-meta-sm"><?= (int) $plan['original_width'] ?>×<?= (int) $plan['original_height'] ?> · aspect <?= round((float) $plan['aspect_ratio'], 3) ?></div>
            </td>
            <td><img src="<?= h(media_url($plan['image_path'])) ?>" class="plan-thumb" alt=""></td>
            <td class="text-ia-muted"><?= (int) $plan['marker_count'] ?></td>
            <td>
              <?php if ($plan['is_start']): ?><span class="badge badge-live">landing</span>
              <?php else: ?><span class="badge badge-draft">—</span><?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-grad" href="floor-plans?studio=<?= (int) $plan['id'] ?>"><?= ia_icon('map', 13) ?> Studio</a>
                <form method="post" class="d-inline">
                  <input type="hidden" name="fp_action" value="landing"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia" title="Set as landing"><?= ia_icon('rocket', 13) ?></button>
                </form>
                <form method="post" class="d-inline" data-delete-form data-confirm="Delete floor plan '<?= h($plan['title']) ?>'?">
                  <input type="hidden" name="fp_action" value="delete"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($plans->rowCount() === 0): ?>
          <tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No floor plans</h4><p>Upload a campus map, then drop circular markers on top of buildings, gates and landmarks.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- upload modal -->
<div class="modal fade" id="fp-upload" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Upload floor plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="fp_action" value="upload">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Ground Floor Map"></div>
        <div>
          <?php
          $pickerName = 'image';
          $pickerValue = '';
          $pickerLabel = 'Image (PNG/JPG)';
          $pickerHelp = '';
          require __DIR__ . '/../layout/media-picker.php';
          ?>
        </div>
        <div class="form-text">Aspect ratio is locked automatically. Markers are stored as percentages so they stay aligned at any screen size.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Upload</button></div>
    </form>
  </div></div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>