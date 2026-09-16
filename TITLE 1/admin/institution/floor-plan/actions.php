<?php
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
