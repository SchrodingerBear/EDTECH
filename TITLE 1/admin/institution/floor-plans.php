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

        if ($action === 'studio-save-all') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $na = max(0, min(360, (float) ($_POST['north_angle'] ?? 0)));
            crud()->update('floor_plans', ['north_angle' => $na], ['id' => $planId, 'institution_id' => $iid]);
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
            $routes = json_decode($_POST['routes'] ?? '[]', true) ?: [];
            $existingWpIds = array_column(crud()->raw("SELECT id FROM fp_waypoints WHERE floor_plan_id=? AND institution_id=?", [$planId, $iid])->fetchAll() ?: [], 'id');
            foreach ($routes as $ri => $route) {
                $routeName = trim($route['name'] ?? '') ?: ('Route ' . ($ri + 1));
                $routeNodes = $route['nodes'] ?? [];
                $nodeIds = [];
                foreach ($routeNodes as $node) {
                    $wpId = (int) ($node['id'] ?? 0);
                    $x = max(0, min(100, (float) ($node['x'] ?? 50)));
                    $y = max(0, min(100, (float) ($node['y'] ?? 50)));
                    $isCorner = !empty($node['corner']);
                    $type = $isCorner ? 'corner' : 'normal';
                    if ($wpId && in_array($wpId, $existingWpIds)) {
                        crud()->update('fp_waypoints', ['x_percent' => $x, 'y_percent' => $y, 'type' => $type], ['id' => $wpId, 'institution_id' => $iid]);
                        $nodeIds[] = $wpId;
                    } else {
                        $label = trim($node['label'] ?? '') ?: ($routeName . ' #' . count($nodeIds));
                        $newId = crud()->insert('fp_waypoints', [
                            'institution_id' => $iid, 'floor_plan_id' => $planId,
                            'label' => $label, 'x_percent' => $x, 'y_percent' => $y, 'type' => $type,
                        ]);
                        $nodeIds[] = $newId;
                        $existingWpIds[] = $newId;
                    }
                }
                $nodesJson = json_encode(array_values(array_filter(array_map('intval', $nodeIds))));
                $existingPathId = (int) ($route['id'] ?? 0);
                if ($existingPathId) {
                    crud()->update('fp_navigation_paths', ['name' => $routeName, 'nodes_json' => $nodesJson], ['id' => $existingPathId, 'institution_id' => $iid]);
                } else {
                    crud()->insert('fp_navigation_paths', [
                        'institution_id' => $iid, 'floor_plan_id' => $planId,
                        'name' => $routeName, 'nodes_json' => $nodesJson,
                        'created_by' => (int) current_user()['id'],
                    ]);
                }
            }
            $deleteIds = array_filter(array_map('intval', explode(',', $_POST['routes_delete'] ?? '')));
            foreach ($deleteIds as $delId) crud()->delete('fp_navigation_paths', ['id' => $delId, 'institution_id' => $iid]);
            $wpDeleteIds = array_filter(array_map('intval', explode(',', $_POST['waypoints_delete'] ?? '')));
            foreach ($wpDeleteIds as $delWpId) crud()->delete('fp_waypoints', ['id' => $delWpId, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Studio saved.');
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

        if ($action === 'routes-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $routes  = json_decode($_POST['routes'] ?? '[]', true) ?: [];
            $existingWpIds = array_column($waypoints ?? [], 'id');

            foreach ($routes as $ri => $route) {
                $routeName = trim($route['name'] ?? '') ?: ('Route ' . ($ri + 1));
                $routeNodes = $route['nodes'] ?? [];
                $nodeIds = [];

                foreach ($routeNodes as $node) {
                    $wpId = (int) ($node['id'] ?? 0);
                    $x = max(0, min(100, (float) ($node['x'] ?? 50)));
                    $y = max(0, min(100, (float) ($node['y'] ?? 50)));
                    $isCorner = !empty($node['corner']);
                    $type = $isCorner ? 'corner' : 'normal';

                    if ($wpId && in_array($wpId, $existingWpIds)) {
                        crud()->update('fp_waypoints', [
                            'x_percent' => $x, 'y_percent' => $y, 'type' => $type,
                        ], ['id' => $wpId, 'institution_id' => $iid]);
                        $nodeIds[] = $wpId;
                    } else {
                        $label = trim($node['label'] ?? '') ?: ($routeName . ' #' . ($ri + 1) . '-' . count($nodeIds));
                        $newId = crud()->insert('fp_waypoints', [
                            'institution_id' => $iid, 'floor_plan_id' => $planId,
                            'label' => $label, 'x_percent' => $x, 'y_percent' => $y,
                            'type' => $type,
                        ]);
                        $nodeIds[] = $newId;
                        $existingWpIds[] = $newId;
                    }
                }

                $nodesJson = json_encode(array_values(array_filter(array_map('intval', $nodeIds))), JSON_UNESCAPED_UNICODE);
                $existingPathId = (int) ($route['id'] ?? 0);
                if ($existingPathId) {
                    crud()->update('fp_navigation_paths', ['name' => $routeName, 'nodes_json' => $nodesJson], ['id' => $existingPathId, 'institution_id' => $iid]);
                } else {
                    crud()->insert('fp_navigation_paths', [
                        'institution_id' => $iid, 'floor_plan_id' => $planId,
                        'name' => $routeName, 'nodes_json' => $nodesJson,
                        'created_by' => (int) current_user()['id'],
                    ]);
                }
            }

            $deleteIds = array_filter(array_map('intval', explode(',', $_POST['routes_delete'] ?? '')));
            foreach ($deleteIds as $delId) {
                crud()->delete('fp_navigation_paths', ['id' => $delId, 'institution_id' => $iid]);
            }

            $wpDeleteIds = array_filter(array_map('intval', explode(',', $_POST['waypoints_delete'] ?? '')));
            foreach ($wpDeleteIds as $delWpId) {
                crud()->delete('fp_waypoints', ['id' => $delWpId, 'institution_id' => $iid]);
            }

            sync_institution_config($iid);
            flash('success', 'Routes saved.');
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
        <h3 class="mb-0 fw-800"><?= h($studio['title']) ?> — interactive studio</h3>
        <?php if ($studio['is_campus_landing']): ?><span class="badge badge-live">landing</span><?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-ia btn-sm" data-bs-toggle="modal" data-bs-target="#fp-update-image-modal"><?= ia_icon('image', 14) ?> Change image</button>
      <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#marker-modal"><?= ia_icon('map', 16) ?> Add marker</button>
    </div>
  </div>

  <!-- Unified mode toolbar -->
  <div class="ia-card p-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <div class="btn-group flex-wrap" id="ar-mode-tabs" role="group">
        <button type="button" class="btn btn-sm btn-grad" data-ar-mode="move"><?= ia_icon('move3d', 13) ?> Move</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="face"><?= ia_icon('refresh', 13) ?> Face</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="route"><?= ia_icon('map', 13) ?> Route</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="compass"><?= ia_icon('compass', 13) ?> Compass</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="connections"><?= ia_icon('link', 13) ?> Exit Links</button>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="ia-meta-md" id="mode-hint">Drag any element to reposition it. Click to edit.</span>
        <button class="btn btn-grad btn-sm px-3" id="studio-save-btn" type="button">Save all</button>
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

  <!-- Map stage -->
  <div class="ia-card p-3">
    <div id="map-stage" class="map-stage" style="--fp-ar: <?= $studio['aspect_ratio'] ?>">
      <img src="<?= h(media_url($studio['image_path'])) ?>" alt="floor plan" class="map-stage-img">
      <div id="fp-path-layer"></div>

      <?php foreach ($markers as $mk): ?>
        <?php $mtype = in_array($mk['marker_type'] ?? '', ['scene', 'entrance', 'exit'], true) ? $mk['marker_type'] : 'scene'; ?>
        <div class="fp-marker-dot" data-id="<?= (int) $mk['id'] ?>"
             data-mktype="<?= $mtype ?>"
             data-name="<?= h($mk['label'], ENT_QUOTES) ?>"
             style="left:<?= (float) $mk['x_percent'] ?>%;top:<?= (float) $mk['y_percent'] ?>%">
          <span class="fp-facing-arrow" style="--facing: <?= (float) ($mk['facing_angle'] ?? 0) ?>deg"></span>
          <span class="fp-mktype-badge"><?= $mtype === 'entrance' ? 'IN' : ($mtype === 'exit' ? 'OUT' : 'SC') ?></span>
          <input type="hidden" class="mk-x" value="<?= (float) $mk['x_percent'] ?>">
          <input type="hidden" class="mk-y" value="<?= (float) $mk['y_percent'] ?>">
          <input type="hidden" class="mk-label" value="<?= h($mk['label']) ?>">
          <input type="hidden" class="mk-type" value="<?= $mtype ?>">
          <input type="hidden" class="mk-facing" value="<?= (float) ($mk['facing_angle'] ?? 0) ?>">
          <input type="hidden" class="mk-building" value="<?= h($mk['target_building_id']) ?>">
          <input type="hidden" class="mk-room" value="<?= h($mk['target_room_id']) ?>">
          <input type="hidden" class="mk-scene" value="<?= h($mk['target_scene_id']) ?>">
          <input type="hidden" class="mk-fp" value="<?= h($mk['target_floor_plan_id']) ?>">
          <input type="hidden" class="mk-popup-title" value="<?= h($mk['popup_title']) ?>">
          <input type="hidden" class="mk-popup-html" value="<?= h($mk['popup_html']) ?>">
        </div>
      <?php endforeach; ?>

      <?php foreach ($waypoints as $wp): ?>
        <div class="fp-wp-dot<?= $wp['type'] === 'corner' ? ' is-corner' : '' ?>"
             data-wp-id="<?= (int) $wp['id'] ?>"
             data-name="<?= h($wp['label'], ENT_QUOTES) ?>"
             style="left:<?= (float) $wp['x_percent'] ?>%;top:<?= (float) $wp['y_percent'] ?>%">
          <input type="hidden" class="wp-x" value="<?= (float) $wp['x_percent'] ?>">
          <input type="hidden" class="wp-y" value="<?= (float) $wp['y_percent'] ?>">
          <input type="hidden" class="wp-label" value="<?= h($wp['label']) ?>">
          <input type="hidden" class="wp-type" value="<?= $wp['type'] ?>">
        </div>
      <?php endforeach; ?>

      <div id="fp-compass" class="fp-compass-rose" style="--north: <?= (float) ($studio['north_angle'] ?? 0) ?>deg; left:85%; top:15%">
        <div class="compass-center" id="compass-center"></div>
        <div class="compass-dir" data-dir="N">N</div>
        <div class="compass-dir" data-dir="NE">NE</div>
        <div class="compass-dir" data-dir="E">E</div>
        <div class="compass-dir" data-dir="SE">SE</div>
        <div class="compass-dir" data-dir="S">S</div>
        <div class="compass-dir" data-dir="SW">SW</div>
        <div class="compass-dir" data-dir="W">W</div>
        <div class="compass-dir" data-dir="NW">NW</div>
        <svg class="compass-ring" viewBox="-60 -60 120 120"><circle cx="0" cy="0" r="56" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"/><circle cx="0" cy="0" r="30" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="1"/></svg>
      </div>

      <div class="fp-hint" id="fp-hint">Drag dots to reposition them. Click a marker to edit its properties.</div>
    </div>
  </div>

  <!-- Properties panel -->
  <div class="ia-card mt-3" id="props-panel" style="display:none">
    <div class="d-flex align-items-center justify-content-between">
      <h3 id="props-title" class="mb-0">Properties</h3>
      <button type="button" class="btn-close" id="props-close" aria-label="Close"></button>
    </div>
    <div class="mt-2" id="props-body"></div>
  </div>

  <!-- Hidden forms for server submission -->
  <form method="post" id="fp-markers-form" style="display:none">
    <input type="hidden" name="fp_action" value="markers-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
  </form>
  <form method="post" id="fp-routes-form" style="display:none">
    <input type="hidden" name="fp_action" value="routes-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <input type="hidden" name="routes" id="routes-json">
    <input type="hidden" name="routes_delete" id="routes-delete-ids" value="">
    <input type="hidden" name="waypoints_delete" id="waypoints-delete-ids" value="">
  </form>
  <form method="post" id="fp-compass-form" style="display:none">
    <input type="hidden" name="fp_action" value="compass-save">
    <input type="hidden" name="id" value="<?= (int) $studio['id'] ?>">
    <input type="hidden" name="north_angle" id="north-angle-input" value="<?= (float) ($studio['north_angle'] ?? 0) ?>">
  </form>
  <form method="post" id="fp-connections-form" style="display:none">
    <input type="hidden" name="fp_action" value="connection-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
  </form>

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
  window._fpBuildings = <?= json_encode(array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name']], $buildings), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpRooms     = <?= json_encode(array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name']], $rooms), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpConnections = <?= json_encode($connections, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpPaths = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'nodes' => json_decode($p['nodes_json'] ?? '[]', true) ?: []], $paths), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script>
  (() => {
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    const stage = document.getElementById('map-stage')
    const qsa = (sel) => Array.from(stage.querySelectorAll(sel))
    const propsBody = document.getElementById('props-body')
    const propsTitle = document.getElementById('props-title')
    const propsPanel = document.getElementById('props-panel')
    const pathLayer = document.getElementById('fp-path-layer')
    const compassEl = document.getElementById('fp-compass')
    const stageRect = () => stage.getBoundingClientRect()

    let mode = 'move', selectedEl = null, selectedType = null, northAngle = <?= (float) ($studio['north_angle'] ?? 0) ?>
    let routeCounter = <?= count($waypoints) + count($paths) + 1 ?>

    const markerData = {}
    qsa('.fp-marker-dot').forEach(d => {
      markerData[d.dataset.id] = {
        label: d.querySelector('.mk-label')?.value || d.dataset.name,
        type: d.dataset.mktype,
        facing: parseFloat(d.querySelector('.mk-facing')?.value) || 0,
        building: d.querySelector('.mk-building')?.value || '',
        room: d.querySelector('.mk-room')?.value || '',
        scene: d.querySelector('.mk-scene')?.value || '',
        fp: d.querySelector('.mk-fp')?.value || '',
        popupTitle: d.querySelector('.mk-popup-title')?.value || '',
        popupHtml: d.querySelector('.mk-popup-html')?.value || ''
      }
    })

    const routes = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'nodes' => array_map(fn($nid) => ['id' => (int)$nid], json_decode($p['nodes_json'] ?? '[]', true) ?: [])], $paths), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    const wpDotById = {}
    qsa('.fp-wp-dot').forEach(d => { wpDotById[d.dataset.wpId] = d })

    routes.forEach(r => {
      const resolvedNodes = []
      r.nodes.forEach(n => {
        if (n.id && wpDotById[String(n.id)]) {
          const dot = wpDotById[String(n.id)]
          resolvedNodes.push({ id: n.id, x: parseFloat(dot.querySelector('.wp-x').value), y: parseFloat(dot.querySelector('.wp-y').value), corner: dot.querySelector('.wp-type')?.value === 'corner' })
        }
      })
      r.nodes = resolvedNodes
    })

    let deletedRouteIds = [], deletedWpIds = []

    /* ── Utilities ─────────────────────────────────────── */
    function pct(ev) {
      const r = stageRect()
      return { x: Math.max(0, Math.min(100, ((ev.clientX - r.left) / r.width) * 100)), y: Math.max(0, Math.min(100, ((ev.clientY - r.top) / r.height) * 100)) }
    }
    function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y) }
    function pointToSegDist(p, a, b) {
      const dx = b.x - a.x, dy = b.y - a.y, lenSq = dx * dx + dy * dy
      if (lenSq === 0) return dist(p, a)
      let t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq
      t = Math.max(0, Math.min(1, t))
      return dist(p, { x: a.x + t * dx, y: a.y + t * dy })
    }

    /* ── Mode switching ────────────────────────────────── */
    const hints = { move: 'Drag any element to reposition. Click to edit properties.', face: 'Click a marker to show facing handle. Drag the arrow to rotate.', route: 'Click on the map to place route points. Click a line to bend it.', compass: 'Drag the compass rose to set North direction.', connections: 'Click an exit marker to create a link to another floor or scene.' }

    function setMode(m) {
      mode = m
      deselect()
      document.querySelectorAll('[data-ar-mode]').forEach(b => {
        b.classList.toggle('btn-grad', b.dataset.arMode === m)
        b.classList.toggle('btn-outline-ia', b.dataset.arMode !== m)
      })
      document.getElementById('mode-hint').textContent = hints[m] || ''
      qsa('.fp-marker-dot').forEach(d => {
        d.style.pointerEvents = 'all'
        d.style.opacity = ''
        const arrow = d.querySelector('.fp-facing-arrow')
        if (arrow) arrow.style.opacity = m === 'face' ? '0.6' : ''
      })
      qsa('.fp-wp-dot').forEach(d => { d.style.pointerEvents = 'all'; d.style.opacity = '' })
      compassEl.classList.toggle('mode-visible', m === 'compass')
      stage.style.cursor = m === 'route' ? 'crosshair' : ''
      renderRoutes()
    }

    document.getElementById('ar-mode-tabs').addEventListener('click', e => {
      const b = e.target.closest('[data-ar-mode]')
      if (b) setMode(b.dataset.arMode)
    })

    /* ── Selection ─────────────────────────────────────── */
    function deselect() {
      if (selectedEl) selectedEl.classList.remove('fp-selected')
      selectedEl = null; selectedType = null
      propsPanel.style.display = 'none'
    }
    function selectElement(el, type) {
      if (!el) return
      deselect()
      selectedEl = el; selectedType = type
      el.classList.add('fp-selected')
    }

    /* ── Draggable ─────────────────────────────────────── */
    function makeDraggable(dot, xEl, yEl, onDrag) {
      dot.addEventListener('pointerdown', e => {
        if (e.target.closest('.fp-facing-arrow')) return
        e.preventDefault(); e.stopPropagation()
        let dragging = false
        const downX = e.clientX, downY = e.clientY
        const onMove = ev => {
          const dx = ev.clientX - downX, dy = ev.clientY - downY
          if (!dragging && Math.hypot(dx, dy) < 5) return
          dragging = true
          const p = pct(ev)
          xEl.value = p.x.toFixed(4); yEl.value = p.y.toFixed(4)
          dot.style.left = p.x + '%'; dot.style.top = p.y + '%'
          if (onDrag) onDrag()
        }
        const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
        document.addEventListener('pointermove', onMove)
        document.addEventListener('pointerup', onUp)
      })
    }

    /* ── Marker interactions ───────────────────────────── */
    qsa('.fp-marker-dot').forEach(dot => {
      const xEl = dot.querySelector('.mk-x'), yEl = dot.querySelector('.mk-y')
      makeDraggable(dot, xEl, yEl, () => {
        if (selectedEl === dot && selectedType === 'marker') renderMarkerProps(dot)
      })
      dot.addEventListener('click', e => {
        e.stopPropagation()
        if (mode === 'move' || mode === 'face') {
          selectElement(dot, 'marker')
          renderMarkerProps(dot)
        } else if (mode === 'connections') {
          const md = markerData[dot.dataset.id]
          if (md && md.type === 'exit') { selectElement(dot, 'marker'); renderExitLinkProps(dot) }
        }
      })
      const arrow = dot.querySelector('.fp-facing-arrow')
      if (arrow) {
        arrow.addEventListener('pointerdown', e => {
          e.preventDefault(); e.stopPropagation()
          const rotate = ev => {
            const r = stageRect()
            const ang = (Math.atan2(ev.clientY - (r.top + r.height / 2), ev.clientX - (r.left + r.width / 2)) * 180 / Math.PI + 90 + 360) % 360
            setFacing(dot, ang)
          }
          const up = () => { document.removeEventListener('pointermove', rotate); document.removeEventListener('pointerup', up) }
          document.addEventListener('pointermove', rotate)
          document.addEventListener('pointerup', up)
        })
      }
    })

    function setFacing(dot, deg) {
      deg = ((deg % 360) + 360) % 360
      dot.querySelector('.mk-facing').value = deg.toFixed(1)
      dot.querySelector('.fp-facing-arrow').style.setProperty('--facing', deg + 'deg')
      if (markerData[dot.dataset.id]) markerData[dot.dataset.id].facing = deg
      if (selectedEl === dot) { const inp = document.getElementById('mk-facing-input'); if (inp) inp.value = Math.round(deg) }
    }

    function renderMarkerProps(dot) {
      const md = markerData[dot.dataset.id]
      propsTitle.textContent = 'Marker — ' + (md.label || dot.dataset.name)
      propsPanel.style.display = ''
      let amode = 'popup'
      if (md.scene && md.scene !== '0') amode = 'scene'
      else if (md.fp && md.fp !== '0') amode = 'floorplan'
      propsBody.innerHTML = `
        <div class="row g-2 mb-2">
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Label</label>
            <input class="form-control form-control-sm" id="mk-label-input" value="${esc(md.label)}"></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">AR type</label>
            <select class="form-select form-select-sm" id="mk-type-input">
              <option value="scene" ${md.type==='scene'?'selected':''}>Scene</option>
              <option value="entrance" ${md.type==='entrance'?'selected':''}>Entrance</option>
              <option value="exit" ${md.type==='exit'?'selected':''}>Exit</option>
            </select></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Facing (°)</label>
            <input class="form-control form-control-sm" id="mk-facing-input" type="number" min="0" max="360" step="1" value="${Math.round(md.facing)}"></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Position</label>
            <div class="form-control form-control-sm" style="cursor:default;opacity:.7">${parseFloat(dot.querySelector('.mk-x').value).toFixed(1)}%, ${parseFloat(dot.querySelector('.mk-y').value).toFixed(1)}%</div>
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Building</label>
            <select class="form-select form-select-sm" id="mk-bld-input"><option value="">— none —</option>
            ${window._fpBuildings.map(b=>`<option value="${b.id}" ${md.building==b.id?'selected':''}>${esc(b.name)}</option>`).join('')}</select></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Room</label>
            <select class="form-select form-select-sm" id="mk-room-input"><option value="">— none —</option>
            ${window._fpRooms.map(r=>`<option value="${r.id}" ${md.room==r.id?'selected':''}>${esc(r.name)}</option>`).join('')}</select></div>
          <div class="col-md-6"><label class="form-label fw-semibold mb-1">Popup title</label>
            <input class="form-control form-control-sm" id="mk-ptitle-input" value="${esc(md.popupTitle)}"></div>
        </div>
        <p class="form-label form-label-sm fw-semibold mb-1">Click action</p>
        <div class="d-flex gap-1 flex-wrap mb-2">
          ${['popup','scene','floorplan'].map(m=>`<button type="button" class="btn btn-xs ${amode===m?'btn-grad':'btn-outline-ia'}" data-mk-act="${m}">${m==='popup'?'Popup':m==='scene'?'360 Tour':'Sub-Floor Plan'}</button>`).join('')}
        </div>
        <div id="mk-act-popup" class="${amode!=='popup'?'d-none':''}">
          <textarea class="form-control form-control-sm" id="mk-popup-input" rows="2">${esc(md.popupHtml)}</textarea>
        </div>
        <div id="mk-act-scene" class="${amode!=='scene'?'d-none':''}">
          <select class="form-select form-select-sm" id="mk-scene-input"><option value="">— none —</option>
          ${window._fpScenes.map(s=>`<option value="${s.id}" ${md.scene==s.id?'selected':''}>${esc(s.name)}</option>`).join('')}</select>
        </div>
        <div id="mk-act-fp" class="${amode!=='floorplan'?'d-none':''}">
          <select class="form-select form-select-sm" id="mk-fp-input"><option value="">— none —</option>
          ${window._fpPlans.map(p=>`<option value="${p.id}" ${md.fp==p.id?'selected':''}>${esc(p.name)}</option>`).join('')}</select>
        </div>
        <div class="mt-2 d-flex justify-content-between align-items-center">
          <span class="ia-micro">${md.type==='entrance'?'Entrance — where visitors enter.':md.type==='exit'?'Exit — link it in Exit Links mode.':'Drag the arrow on the map to set facing.'}</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger" id="mk-delete-btn">Delete marker</button>
        </div>`

      propsBody.querySelectorAll('[data-mk-act]').forEach(btn => {
        btn.addEventListener('click', () => {
          const m = btn.dataset.mkAct
          propsBody.querySelectorAll('[data-mk-act]').forEach(b => { b.classList.remove('btn-grad'); b.classList.add('btn-outline-ia') })
          btn.classList.remove('btn-outline-ia'); btn.classList.add('btn-grad')
          ;['popup','scene','floorplan'].forEach(k => {
            const el = document.getElementById('mk-act-' + k)
            if (el) el.classList.toggle('d-none', k !== m)
          })
          if (m !== 'scene') { md.scene = ''; const si = document.getElementById('mk-scene-input'); if (si) si.value = '' }
          if (m !== 'floorplan') { md.fp = ''; const fi = document.getElementById('mk-fp-input'); if (fi) fi.value = '' }
          if (m !== 'popup') { md.popupHtml = ''; const pi = document.getElementById('mk-popup-input'); if (pi) pi.value = '' }
        })
      })

      const sync = (key, val) => { md[key] = val; dot.querySelector('.mk-' + (key === 'popupTitle' ? 'popup-title' : key === 'popupHtml' ? 'popup-html' : key)) && (dot.querySelector('.mk-' + (key === 'popupTitle' ? 'popup-title' : key === 'popupHtml' ? 'popup-html' : key)).value = val) }
      document.getElementById('mk-label-input').addEventListener('input', e => { sync('label', e.target.value); dot.dataset.name = e.target.value; propsTitle.textContent = 'Marker — ' + e.target.value })
      document.getElementById('mk-type-input').addEventListener('change', e => { sync('type', e.target.value); dot.dataset.mktype = e.target.value; const b = dot.querySelector('.fp-mktype-badge'); if (b) b.textContent = e.target.value === 'entrance' ? 'IN' : e.target.value === 'exit' ? 'OUT' : 'SC' })
      document.getElementById('mk-facing-input').addEventListener('input', e => { const v = ((parseFloat(e.target.value) || 0) + 360) % 360; setFacing(dot, v) })
      document.getElementById('mk-bld-input').addEventListener('change', e => sync('building', e.target.value))
      document.getElementById('mk-room-input').addEventListener('change', e => sync('room', e.target.value))
      document.getElementById('mk-ptitle-input').addEventListener('input', e => sync('popupTitle', e.target.value))
      document.getElementById('mk-popup-input')?.addEventListener('input', e => sync('popupHtml', e.target.value))
      document.getElementById('mk-scene-input')?.addEventListener('change', e => sync('scene', e.target.value))
      document.getElementById('mk-fp-input')?.addEventListener('change', e => sync('fp', e.target.value))
      document.getElementById('mk-delete-btn').addEventListener('click', async () => {
        if (!confirm('Delete marker "' + md.label + '"?')) return
        dot.remove(); delete markerData[dot.dataset.id]; deselect()
      })
    }

    /* ── Waypoint interactions ─────────────────────────── */
    qsa('.fp-wp-dot').forEach(dot => {
      const xEl = dot.querySelector('.wp-x'), yEl = dot.querySelector('.wp-y')
      makeDraggable(dot, xEl, yEl)
      dot.addEventListener('click', e => {
        e.stopPropagation()
        if (mode === 'move') { selectElement(dot, 'waypoint'); renderWaypointProps(dot) }
      })
    })
    function renderWaypointProps(dot) {
      const lbl = dot.querySelector('.wp-label')?.value || dot.dataset.name
      const isCorner = dot.querySelector('.wp-type')?.value === 'corner'
      propsTitle.textContent = 'Waypoint — ' + lbl
      propsPanel.style.display = ''
      propsBody.innerHTML = `
        <div class="d-flex gap-2 align-items-end flex-wrap">
          <div><label class="form-label fw-semibold mb-1">Label</label>
            <input class="form-control form-control-sm" id="wp-label-input" value="${esc(lbl)}" style="max-width:220px"></div>
          <div class="form-check ms-2 mb-1"><input class="form-check-input" type="checkbox" id="wp-corner-input" ${isCorner?'checked':''}><label class="form-check-label" for="wp-corner-input">Corner (elbow)</label></div>
          <span class="ia-micro mb-1">${parseFloat(dot.querySelector('.wp-x').value).toFixed(1)}%, ${parseFloat(dot.querySelector('.wp-y').value).toFixed(1)}%</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger ms-auto" id="wp-delete-btn">Remove</button>
        </div>`
      document.getElementById('wp-label-input').addEventListener('input', e => { dot.querySelector('.wp-label').value = e.target.value; dot.dataset.name = e.target.value })
      document.getElementById('wp-corner-input').addEventListener('change', e => { dot.classList.toggle('is-corner', e.target.checked); dot.querySelector('.wp-type').value = e.target.checked ? 'corner' : 'normal'; renderRoutes() })
      document.getElementById('wp-delete-btn').addEventListener('click', async () => {
        if (!confirm('Remove waypoint "' + lbl + '"?')) return
        deletedWpIds.push(dot.dataset.wpId); dot.remove(); delete wpDotById[dot.dataset.wpId]; deselect()
      })
    }

    /* ── Route drawing ─────────────────────────────────── */
    stage.addEventListener('click', e => {
      if (mode !== 'route') return
      if (e.target.closest('.fp-marker-dot') || e.target.closest('.fp-wp-dot') || e.target.closest('.fp-compass-rose') || e.target.closest('.fp-route-node')) return
      const p = pct(e)
      const existing = hitTestRouteNode(p)
      if (existing) { selectElement(existing.route.nodes[existing.idx] ? stage.querySelector(`[data-route-node="${existing.route.nodes[existing.idx].id || ''}"]`) : null, 'route'); return }
      let route = routes.find(r => r.nodes.length < 2)
      if (!route) route = createRoute()
      route.nodes.push({ x: p.x, y: p.y, corner: false, id: 0, label: route.name + ' #' + route.nodes.length })
      renderRoutes(); renderRouteProps(route)
    })

    function hitTestRouteNode(p) {
      for (const r of routes) for (let i = 0; i < r.nodes.length; i++) if (dist(p, r.nodes[i]) < 3.5) return { route: r, idx: i }
      return null
    }

    stage.addEventListener('dblclick', e => {
      if (mode !== 'route') return
      if (e.target.closest('.fp-marker-dot') || e.target.closest('.fp-wp-dot') || e.target.closest('.fp-compass-rose')) return
      const p = pct(e)
      for (const r of routes) {
        for (let i = 0; i < r.nodes.length - 1; i++) {
          if (pointToSegDist(p, r.nodes[i], r.nodes[i + 1]) < 3) {
            r.nodes.splice(i + 1, 0, { ...p, corner: true, id: 0, label: 'bend' })
            renderRoutes(); return
          }
        }
      }
    })

    function createRoute() {
      routeCounter++
      const r = { id: 0, name: 'Route ' + routeCounter, nodes: [] }
      routes.push(r)
      return r
    }

    function renderRoutes() {
      pathLayer.querySelectorAll('.fp-route-group').forEach(g => g.remove())
      routes.forEach(r => {
        if (r.nodes.length < 2) return
        const g = document.createElement('div')
        g.className = 'fp-route-group'
        g.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:3;overflow:visible'
        const ns = 'http://www.w3.org/2000/svg'
        const svg = document.createElementNS(ns, 'svg')
        svg.setAttribute('viewBox', '0 0 100 100')
        svg.setAttribute('preserveAspectRatio', 'none')
        svg.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible'
        const poly = document.createElementNS(ns, 'polyline')
        poly.setAttribute('points', r.nodes.map(n => n.x + ',' + n.y).join(' '))
        poly.setAttribute('fill', 'none')
        poly.setAttribute('stroke', 'rgba(33,150,243,.85)')
        poly.setAttribute('stroke-width', '0.4')
        poly.setAttribute('stroke-linejoin', 'round')
        poly.setAttribute('stroke-linecap', 'round')
        svg.appendChild(poly)
        g.appendChild(svg)
        r.nodes.forEach((n, i) => {
          const nd = document.createElement('div')
          nd.className = 'fp-route-node' + (n.corner ? ' is-corner' : '')
          nd.style.cssText = `position:absolute;left:${n.x}%;top:${n.y}%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:${n.corner?'#f7c948':'#2196f3'};border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);cursor:grab;touch-action:none;z-index:4;pointer-events:all`
          nd.dataset.routeNode = n.id || ('new-' + i)
          g.appendChild(nd)
          nd.addEventListener('pointerdown', ev => {
            ev.preventDefault(); ev.stopPropagation()
            const onMove = mev => { const pp = pct(mev); n.x = pp.x; n.y = pp.y; nd.style.left = pp.x + '%'; nd.style.top = pp.y + '%'; renderRoutes() }
            const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
            document.addEventListener('pointermove', onMove)
            document.addEventListener('pointerup', onUp)
          })
          nd.addEventListener('click', ev => { ev.stopPropagation(); if (mode === 'route') { selectElement(nd, 'route'); renderRouteProps(r, i) } })
        })
        pathLayer.appendChild(g)
      })
    }

    function renderRouteProps(route, nodeIdx) {
      propsTitle.textContent = route.name
      propsPanel.style.display = ''
      propsBody.innerHTML = `
        <div class="d-flex gap-2 align-items-end flex-wrap mb-2">
          <div><label class="form-label fw-semibold mb-1">Route name</label>
            <input class="form-control form-control-sm" id="rt-name-input" value="${esc(route.name)}" style="max-width:260px"></div>
          <span class="ia-micro mb-1">${route.nodes.length} point${route.nodes.length !== 1 ? 's' : ''}</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger ms-auto" id="rt-delete-btn">Delete route</button>
        </div>
        <div id="rt-nodes" class="d-flex gap-1 flex-wrap mb-1"></div>
        <div class="ia-micro">Click on map to add points. Double-click a line to create a bend. Drag points to reposition.</div>`
      document.getElementById('rt-name-input').addEventListener('input', e => { route.name = e.target.value; propsTitle.textContent = e.target.value })
      document.getElementById('rt-delete-btn').addEventListener('click', async () => {
        if (!confirm('Delete "' + route.name + '"?')) return
        if (route.id) deletedRouteIds.push(route.id)
        routes.splice(routes.indexOf(route), 1); deselect(); renderRoutes()
      })
      const wrap = document.getElementById('rt-nodes')
      route.nodes.forEach((n, i) => {
        const chip = document.createElement('span')
        chip.className = 'badge rounded-pill ' + (n.corner ? 'text-bg-warning' : 'text-bg-primary')
        chip.style.cursor = 'pointer'
        chip.textContent = (n.corner ? '⤺ ' : '') + (i + 1)
        chip.title = `Point ${i + 1} — ${n.x.toFixed(1)}%, ${n.y.toFixed(1)}%` + (n.corner ? ' (corner)' : '')
        chip.addEventListener('click', () => { n.corner = !n.corner; renderRoutes(); renderRouteProps(route, i) })
        wrap.appendChild(chip)
        if (i < route.nodes.length - 1) {
          const arrow = document.createElement('span')
          arrow.style.cssText = 'color:var(--ia-muted,#999);font-size:11px'
          arrow.textContent = '→'
          wrap.appendChild(arrow)
        }
      })
    }

    /* ── Compass rose (directional markers) ─────────────── */
    const compassRose = document.getElementById('fp-compass')
    const compassDirs = compassRose.querySelectorAll('.compass-dir')
    const compassCenter = document.getElementById('compass-center')
    const roseRadiusPct = 36.67
    const DIR_ANGLES = { N: 0, NE: 45, E: 90, SE: 135, S: 180, SW: 225, W: 270, NW: 315 }

    function positionRose() {
      const rad = northAngle * Math.PI / 180
      compassDirs.forEach(d => {
        const dar = (DIR_ANGLES[d.dataset.dir] || 0) * Math.PI / 180
        const total = dar + rad
        d.style.left = (50 + Math.sin(total) * roseRadiusPct) + '%'
        d.style.top = (50 - Math.cos(total) * roseRadiusPct) + '%'
      })
    }
    function applyNorth(deg) {
      northAngle = ((parseFloat(deg) || 0) % 360 + 360) % 360
      compassRose.style.setProperty('--north', northAngle + 'deg')
      document.getElementById('north-angle-input').value = northAngle.toFixed(1)
      positionRose()
    }

    compassDirs.forEach(dir => {
      dir.addEventListener('pointerdown', e => {
        e.preventDefault(); e.stopPropagation()
        const startAng = northAngle
        const onMove = ev => {
          const r = compassRose.getBoundingClientRect()
          const cx = r.left + r.width / 2, cy = r.top + r.height / 2
          const ang = (Math.atan2(ev.clientY - cy, ev.clientX - cx) * 180 / Math.PI + 90 + 360) % 360
          const dirBase = DIR_ANGLES[dir.dataset.dir] || 0
          applyNorth((ang - dirBase + 360) % 360)
        }
        const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
        document.addEventListener('pointermove', onMove)
        document.addEventListener('pointerup', onUp)
      })
    })

    compassCenter.addEventListener('pointerdown', e => {
      e.preventDefault(); e.stopPropagation()
      let dragging = false
      const downX = e.clientX, downY = e.clientY
      const startLeft = parseFloat(compassRose.style.left) || 85
      const startTop = parseFloat(compassRose.style.top) || 15
      const onMove = ev => {
        const dx = ev.clientX - downX, dy = ev.clientY - downY
        if (!dragging && Math.hypot(dx, dy) < 3) return
        dragging = true
        const r = stageRect()
        const newLeft = startLeft + (dx / r.width) * 100
        const newTop = startTop + (dy / r.height) * 100
        compassRose.style.left = Math.max(5, Math.min(95, newLeft)) + '%'
        compassRose.style.top = Math.max(5, Math.min(95, newTop)) + '%'
      }
      const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
      document.addEventListener('pointermove', onMove)
      document.addEventListener('pointerup', onUp)
    })
    positionRose()

    /* ── Exit links ────────────────────────────────────── */
    function renderExitLinkProps(dot) {
      const md = markerData[dot.dataset.id]
      const existing = window._fpConnections.find(c => c.from_marker_id == dot.dataset.id)
      propsTitle.textContent = 'Exit Link — ' + md.label
      propsPanel.style.display = ''
      propsBody.innerHTML = `
        <div class="d-flex gap-2 flex-wrap mb-2">
          <div><label class="form-label fw-semibold mb-1">Sub-floor plan</label>
            <select class="form-select form-select-sm" id="el-fp"><option value="">— none —</option>
            ${window._fpPlans.map(p=>`<option value="${p.id}" ${existing?.to_floor_plan_id==p.id?'selected':''}>${esc(p.name)}</option>`).join('')}</select></div>
          <div><label class="form-label fw-semibold mb-1">360 scene</label>
            <select class="form-select form-select-sm" id="el-scene"><option value="">— none —</option>
            ${window._fpScenes.map(s=>`<option value="${s.id}" ${existing?.to_scene_id==s.id?'selected':''}>${esc(s.name)}</option>`).join('')}</select></div>
          <div><label class="form-label fw-semibold mb-1">Entrance marker</label>
            <select class="form-select form-select-sm" id="el-ent"><option value="">— none —</option>
            ${qsa('.fp-marker-dot[data-mktype="entrance"]').map(d=>`<option value="${d.dataset.id}" ${existing?.to_marker_id==d.dataset.id?'selected':''}>${esc(markerData[d.dataset.id]?.label||d.dataset.name)}</option>`).join('')}</select></div>
          <div><label class="form-label fw-semibold mb-1">Building</label>
            <select class="form-select form-select-sm" id="el-bld"><option value="">— none —</option>
            ${window._fpBuildings.map(b=>`<option value="${b.id}" ${existing?.to_building_id==b.id?'selected':''}>${esc(b.name)}</option>`).join('')}</select></div>
        </div>
        <div class="d-flex gap-2 align-items-end">
          <div class="flex-grow-1"><label class="form-label fw-semibold mb-1">Note</label>
            <input class="form-control form-control-sm" id="el-note" value="${esc(existing?.note||'')}" placeholder="Optional note"></div>
          <button type="button" class="btn btn-grad btn-sm px-3" id="el-save">Save link</button>
          ${existing ? '<button type="button" class="btn btn-outline-ia btn-sm text-danger" id="el-del">Delete</button>' : ''}
        </div>`
      document.getElementById('el-save').addEventListener('click', () => {
        const f = document.getElementById('fp-connections-form')
        let html = `<input type="hidden" name="fp_action" value="connection-save">
          <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
          <input type="hidden" name="from_marker_id" value="${dot.dataset.id}">`
        if (existing) html += `<input type="hidden" name="connection_id" value="${existing.id}">`
        html += `<input type="hidden" name="to_floor_plan_id" value="${document.getElementById('el-fp').value}">
          <input type="hidden" name="to_scene_id" value="${document.getElementById('el-scene').value}">
          <input type="hidden" name="to_marker_id" value="${document.getElementById('el-ent').value}">
          <input type="hidden" name="to_building_id" value="${document.getElementById('el-bld').value}">
          <input type="hidden" name="note" value="${document.getElementById('el-note').value}">`
        f.innerHTML = html; f.submit()
      })
      if (existing) {
        document.getElementById('el-del').addEventListener('click', async () => {
          if (!confirm('Delete this exit link?')) return
          const f = document.getElementById('fp-connections-form')
          f.innerHTML = `<input type="hidden" name="fp_action" value="connection-delete"><input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>"><input type="hidden" name="id" value="${existing.id}">`
          f.submit()
        })
      }
    }

    /* ── Close props panel ─────────────────────────────── */
    document.getElementById('props-close').addEventListener('click', deselect)

    /* ── Stage click deselect ──────────────────────────── */
    stage.addEventListener('click', e => { if (mode !== 'route' && !e.target.closest('.fp-marker-dot') && !e.target.closest('.fp-wp-dot') && !e.target.closest('.fp-compass-rose') && !e.target.closest('.fp-route-node')) deselect() })

    /* ── Save all ──────────────────────────────────────── */
    document.getElementById('studio-save-btn').addEventListener('click', () => {
      const planId = <?= (int) $studio['id'] ?>
      const f = document.createElement('form')
      f.method = 'post'
      let html = `<input type="hidden" name="fp_action" value="studio-save-all"><input type="hidden" name="plan_id" value="${planId}">`
      html += `<input type="hidden" name="north_angle" value="${northAngle.toFixed(1)}">`
      let mi = 0
      qsa('.fp-marker-dot').forEach(d => {
        const md = markerData[d.dataset.id]
        if (!md) return
        html += `<input type="hidden" name="markers[${mi}][id]" value="${d.dataset.id}">
          <input type="hidden" name="markers[${mi}][x]" value="${d.querySelector('.mk-x').value}">
          <input type="hidden" name="markers[${mi}][y]" value="${d.querySelector('.mk-y').value}">
          <input type="hidden" name="markers[${mi}][label]" value="${esc(md.label)}">
          <input type="hidden" name="markers[${mi}][marker_type]" value="${md.type}">
          <input type="hidden" name="markers[${mi}][facing_angle]" value="${md.facing}">
          <input type="hidden" name="markers[${mi}][target_building_id]" value="${md.building}">
          <input type="hidden" name="markers[${mi}][target_room_id]" value="${md.room}">
          <input type="hidden" name="markers[${mi}][target_scene_id]" value="${md.scene}">
          <input type="hidden" name="markers[${mi}][target_floor_plan_id]" value="${md.fp}">
          <input type="hidden" name="markers[${mi}][popup_title]" value="${esc(md.popupTitle)}">
          <input type="hidden" name="markers[${mi}][popup_html]" value="${esc(md.popupHtml)}">`
        mi++
      })
      html += `<input type="hidden" name="routes" value="${esc(JSON.stringify(routes.map(r => ({ id: r.id || 0, name: r.name, nodes: r.nodes.map(n => ({ id: n.id || 0, x: n.x, y: n.y, corner: n.corner, label: n.label || '' })) }))))}">`
      html += `<input type="hidden" name="routes_delete" value="${deletedRouteIds.join(',')}">`
      html += `<input type="hidden" name="waypoints_delete" value="${deletedWpIds.join(',')}">`
      f.innerHTML = html
      document.body.appendChild(f)
      f.submit()
    })

    /* ── Init ──────────────────────────────────────────── */
    setMode('move')
    renderRoutes()
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