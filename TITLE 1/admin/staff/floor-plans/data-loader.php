<?php
$plans = crud()->raw(
    'SELECT fp.*, (SELECT COUNT(*) FROM floor_plan_markers m WHERE m.floor_plan_id=fp.id) mc FROM floor_plans fp WHERE fp.institution_id=:iid ORDER BY fp.created_at DESC',
    [':iid' => $iid]
)->fetchAll();

$plan = null; $markers = [];
if ($planId) {
    $plan = crud()->raw('SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $planId, 'iid' => $iid])->fetch() ?: null;
    if ($plan) {
        $markers = crud()->select('floor_plan_markers', '*', ['floor_plan_id' => $planId, 'institution_id' => $iid], 'ORDER BY sort_order, id');
    }
}
?>
