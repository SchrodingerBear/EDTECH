<?php
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
$areas = crud()->raw("SELECT id,name FROM campus_areas WHERE institution_id=? AND deleted_at IS NULL ORDER BY name", [$iid])->fetchAll();
$scenes = crud()->raw("SELECT id,title as name FROM tour_scenes WHERE institution_id=? AND deleted_at IS NULL ORDER BY title", [$iid])->fetchAll();
$floorPlansList = crud()->raw("SELECT id,title as name FROM floor_plans WHERE institution_id=? AND deleted_at IS NULL AND id!=? ORDER BY title", [$iid, $studioPlanId ?: 0])->fetchAll();
?>
