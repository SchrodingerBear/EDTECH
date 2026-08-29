<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ar');
/**
 * Innovatech PH — admin: Augmented Reality waypoint + visual target management.
 * X-ray style: markers visible through walls by heading_deg (compass direction).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Augmented Reality';
$pageSub   = 'Manage AR waypoints, compass directions, and visual tracking targets';
$active    = 'Augmented Reality';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid    = (int) $inst['id'];
$me     = (int) current_user()['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
$arDir  = $orgAbs . '/assets/ar_targets';

$cardinals = ['N'=>0,'NE'=>45,'E'=>90,'SE'=>135,'S'=>180,'SW'=>225,'W'=>270,'NW'=>315];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['ar_action'] ?? '';
    try {
        if ($action === 'waypoint-create' || $action === 'waypoint-edit') {
            $id         = (int) ($_POST['id'] ?? 0);
            $name       = trim($_POST['name'] ?? '');
            $buildingId = (int) ($_POST['building_id'] ?? 0) ?: null;
            $roomId     = (int) ($_POST['room_id'] ?? 0) ?: null;
            $sceneId    = (int) ($_POST['scene_id'] ?? 0) ?: null;
            $heading    = isset($_POST['heading_deg']) && $_POST['heading_deg'] !== '' ? (float) $_POST['heading_deg'] : null;
            $radius     = max(1.0, (float) ($_POST['detect_radius_m'] ?? 12));
            $direction  = trim($_POST['direction_label'] ?? '') ?: null;
            $title      = trim($_POST['overlay_title'] ?? '') ?: null;
            $html       = trim($_POST['overlay_html'] ?? '') ?: null;
            $active_wp  = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '') throw new RuntimeException('Waypoint name is required.');
            $mindPath = null;
            if (!empty($_FILES['mind_file']['name'])) {
                if (!is_dir($arDir)) mkdir($arDir, 0775, true);
                $ext = strtolower(pathinfo($_FILES['mind_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'mind') throw new RuntimeException('Only .mind files are accepted.');
                $fname = random_token(8) . '.mind';
                move_uploaded_file($_FILES['mind_file']['tmp_name'], $arDir . '/' . $fname);
                $mindPath = 'assets/ar_targets/' . $fname;
            }
            if ($action === 'waypoint-create') {
                crud()->insert('ar_waypoints', [
                    'institution_id'=>$iid, 'name'=>$name, 'building_id'=>$buildingId, 'room_id'=>$roomId,
                    'scene_id'=>$sceneId, 'heading_deg'=>$heading, 'detect_radius_m'=>$radius,
                    'direction_label'=>$direction, 'overlay_title'=>$title, 'overlay_html'=>$html,
                    'visual_target_path'=>$mindPath, 'is_active'=>$active_wp
                ]);
                flash('success', 'AR waypoint created.');
            } else {
                $updates = ['name'=>$name,'building_id'=>$buildingId,'room_id'=>$roomId,'scene_id'=>$sceneId,'heading_deg'=>$heading,'detect_radius_m'=>$radius,'direction_label'=>$direction,'overlay_title'=>$title,'overlay_html'=>$html,'is_active'=>$active_wp];
                if ($mindPath) { $updates['visual_target_path'] = $mindPath; }
                crud()->update('ar_waypoints', $updates, ['id'=>$id, 'institution_id'=>$iid]);
                flash('success', 'Waypoint updated.');
            }
        }
        if ($action === 'waypoint-delete') {
            $id  = (int) ($_POST['id'] ?? 0);
            $vt = crud()->raw("SELECT visual_target_path FROM ar_waypoints WHERE id=:id AND institution_id=:iid", ['id'=>$id,'iid'=>$iid])->fetchColumn();
            crud()->delete('ar_waypoints', ['id'=>$id,'iid'=>$iid]);
            if ($vt) { $abs = $orgAbs.'/'.ltrim($vt,'/'); if (is_file($abs)) @unlink($abs); }
            flash('success', 'Waypoint removed.');
        }
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('admin/institution/ar');
}

$buildings = crud()->select('buildings', 'id, name', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY name');
$rooms = crud()->raw("SELECT r.id, r.name, r.building_id, b.name AS building_name FROM rooms r LEFT JOIN buildings b ON b.id=r.building_id WHERE r.institution_id=:iid AND r.deleted_at IS NULL ORDER BY r.name", ['iid' => $iid])->fetchAll();
$scenes = crud()->select('tour_scenes', 'id, title', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY title');
$waypoints = crud()->raw("SELECT w.*,b.name AS building_name,r.name AS room_name,s.title AS scene_title FROM ar_waypoints w LEFT JOIN buildings b ON b.id=w.building_id LEFT JOIN rooms r ON r.id=w.room_id LEFT JOIN tour_scenes s ON s.id=w.scene_id WHERE w.institution_id=:iid ORDER BY b.name,w.name", ['iid' => $iid])->fetchAll();
?>
<div class="ia-card mb-4" style="border-left:4px solid var(--ia-accent)">
  <div class="card-body" style="padding:20px">
    <div class="d-flex gap-3 align-items-start">
      <span class="ia-avatar" style="width:46px;height:46px;border-radius:14px;flex-shrink:0"><?= ia_icon('scan-eye',22) ?></span>
      <div>
        <h4 style="font-weight:800;margin:0 0 6px">How AR navigation works</h4>
        <p style="margin:0;color:var(--ia-muted);font-size:13.5px;max-width:700px">
          Students open the AR camera. The system reads their compass heading and renders
          <strong>X-ray overlays</strong> for every waypoint along that heading — even through walls.
          Attach a <code>.mind</code> visual target to enable image-tracking so students can point the camera
          at a physical poster and snap to that location automatically. Compass access is prompted on first use.
        </p>
        <div class="d-flex flex-wrap gap-2 mt-3" style="font-size:12.5px">
          <?php foreach(['N 0°','NE 45°','E 90°','SE 135°','S 180°','SW 225°','W 270°','NW 315°'] as $c): ?>
            <span class="badge" style="background:var(--ia-surface-2);color:var(--ia-text);font-weight:600"><?= $c ?></span>
          <?php endforeach ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-0" style="color:var(--ia-muted);font-size:13.5px"><?= count($waypoints) ?> waypoint(s)</p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#wp-modal" data-mode="create">
    <?= ia_icon('scan-eye',15) ?> Add waypoint
  </button>
</div>

<div class="ia-card mb-4">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>Waypoint</th><th>Building</th><th>Room</th><th>Heading</th><th>Direction</th><th>360 Scene</th><th>.mind</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach($waypoints as $wp): ?>
          <tr>
            <td style="font-weight:700"><?= h($wp['name']) ?></td>
            <td style="color:var(--ia-muted)"><?= h($wp['building_name']??'—') ?></td>
            <td style="color:var(--ia-muted);font-size:12.5px"><?= h($wp['room_name']??'—') ?></td>
            <td><?= $wp['heading_deg']!==null ? '<span class="badge" style="background:var(--ia-surface-2);color:var(--ia-text)">'.h($wp['heading_deg']).'°</span>' : '<span style="color:var(--ia-muted)">—</span>' ?></td>
            <td><?= $wp['direction_label'] ? '<span class="badge badge-live">'.h($wp['direction_label']).'</span>' : '<span style="color:var(--ia-muted)">—</span>' ?></td>
            <td style="font-size:12.5px;color:var(--ia-muted)"><?= h($wp['scene_title']??'—') ?></td>
            <td><?= $wp['visual_target_path'] ? '<span class="badge badge-live">uploaded</span>' : '<span class="badge badge-off">none</span>' ?></td>
            <td><?= (int)$wp['is_active']===1 ? '<span class="badge badge-live">on</span>' : '<span class="badge badge-draft">off</span>' ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#wp-modal" data-mode="edit"
                  data-id="<?= (int)$wp['id'] ?>" data-name="<?= h($wp['name'],ENT_QUOTES) ?>"
                  data-building="<?= (int)$wp['building_id'] ?>" data-room="<?= (int)$wp['room_id'] ?>"
                  data-scene="<?= (int)$wp['scene_id'] ?>" data-heading="<?= (float)($wp['heading_deg']??'') ?>"
                  data-radius="<?= (float)($wp['detect_radius_m']??12) ?>" data-direction="<?= h($wp['direction_label'],ENT_QUOTES) ?>"
                  data-title="<?= h($wp['overlay_title'],ENT_QUOTES) ?>" data-html="<?= h($wp['overlay_html'],ENT_QUOTES) ?>"
                  data-active="<?= (int)$wp['is_active'] ?>"><?= ia_icon('file',13) ?></button>
                <form method="post" class="d-inline" data-delete-form data-confirm="Remove waypoint '<?= h($wp['name']) ?>'?">
                  <input type="hidden" name="ar_action" value="waypoint-delete"><input type="hidden" name="id" value="<?= (int)$wp['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x',13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if(!$waypoints): ?><tr><td colspan="9"><div class="empty-state"><div class="empty-icon"><?= ia_icon('scan-eye',28) ?></div><h4>No AR waypoints yet</h4><p>Add a waypoint to enable X-ray AR overlays for students.</p></div></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<!-- compass dial -->
<div class="ia-card">
  <div class="card-head"><h3>Compass overview</h3></div>
  <div class="card-body" style="padding:20px">
    <div class="d-flex flex-wrap gap-3">
      <?php $grouped=[];foreach($waypoints as $wp){$d=$wp['direction_label']?:'?';$grouped[$d][]=$wp['name'];} ?>
      <?php foreach($cardinals as $label=>$deg): ?>
        <?php $wps=$grouped[$label]??[]; ?>
        <div style="background:var(--ia-surface-2);border-radius:14px;padding:14px 18px;min-width:120px">
          <div style="font-weight:800;font-size:18px;color:var(--ia-accent);margin-bottom:4px"><?= $label ?></div>
          <div style="font-size:11.5px;color:var(--ia-muted);margin-bottom:6px"><?= $deg ?>°</div>
          <?php if($wps): ?><?php foreach($wps as $wn): ?><div style="font-size:12px;font-weight:600;color:var(--ia-text)"><?= h($wn) ?></div><?php endforeach ?>
          <?php else: ?><div style="font-size:12px;color:var(--ia-muted)">none</div><?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- modal -->
<div class="modal fade" id="wp-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="wp-modal-title">Add AR Waypoint</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="ar_action" value="waypoint-create" id="wp-action">
      <input type="hidden" name="id" value="0" id="wp-id">
      <div class="modal-body d-grid gap-3">
        <div class="row g-3">
          <div class="col-md-9"><label class="form-label">Waypoint name <span class="text-danger">*</span></label><input class="form-control" name="name" id="wp-name" required placeholder="Main Entrance — North Gate"></div>
          <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="is_active" id="wp-active" checked><label class="form-check-label" for="wp-active">Active</label></div></div>
        </div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Building</label><select class="form-select" name="building_id" id="wp-building"><option value="">— any —</option><?php foreach($buildings as $b): ?><option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach ?></select></div>
          <div class="col-md-4"><label class="form-label">Room</label><select class="form-select" name="room_id" id="wp-room"><option value="">— any —</option><?php foreach($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['name']).' '.($r['building_name']?'('.$r['building_name'].')':'') ?></option><?php endforeach ?></select></div>
          <div class="col-md-4"><label class="form-label">Link to 360 scene</label><select class="form-select" name="scene_id" id="wp-scene"><option value="">— none —</option><?php foreach($scenes as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['title']) ?></option><?php endforeach ?></select></div>
        </div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Compass heading (degrees)</label><input type="number" class="form-control" name="heading_deg" id="wp-heading" min="0" max="359" step="0.5" placeholder="0 = North"><div class="form-text">0°=N · 90°=E · 180°=S · 270°=W</div></div>
          <div class="col-md-4"><label class="form-label">Cardinal direction label</label><select class="form-select" name="direction_label" id="wp-direction"><option value="">— choose —</option><?php foreach(array_keys($cardinals) as $cl): ?><option value="<?= $cl ?>"><?= $cl ?></option><?php endforeach ?></select><div class="form-text">Used for X-ray grouping</div></div>
          <div class="col-md-4"><label class="form-label">Detection radius (m)</label><input type="number" class="form-control" name="detect_radius_m" id="wp-radius" min="1" step="0.5" value="12"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Overlay title</label><input class="form-control" name="overlay_title" id="wp-overlay-title" placeholder="Science Building"></div>
          <div class="col-md-6"><label class="form-label">Overlay description (HTML ok)</label><textarea class="form-control" name="overlay_html" id="wp-overlay-html" rows="2"></textarea></div>
        </div>
        <div>
          <label class="form-label"><?= ia_icon('upload',14) ?> Visual tracking target <code>.mind</code></label>
          <input type="file" class="form-control" name="mind_file" id="wp-mind" accept=".mind">
          <div class="form-text">Generate at <a href="https://hiukim.github.io/mind-ar-js-doc/tools/compile" target="_blank" rel="noopener">MindAR Image Compiler</a>. One target per waypoint. Leave blank to keep existing.</div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Save waypoint</button></div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const modal=document.getElementById('wp-modal');
  modal.addEventListener('show.bs.modal',(e)=>{
    const btn=e.relatedTarget;
    const mode=btn?(btn.dataset.mode||'create'):'create';
    document.getElementById('wp-action').value=mode==='edit'?'waypoint-edit':'waypoint-create';
    document.getElementById('wp-modal-title').textContent=mode==='edit'?'Edit AR Waypoint':'Add AR Waypoint';
    if(mode==='edit'&&btn){
      document.getElementById('wp-id').value=btn.dataset.id||0;
      document.getElementById('wp-name').value=btn.dataset.name||'';
      document.getElementById('wp-building').value=btn.dataset.building||'';
      document.getElementById('wp-room').value=btn.dataset.room||'';
      document.getElementById('wp-scene').value=btn.dataset.scene||'';
      document.getElementById('wp-heading').value=btn.dataset.heading||'';
      document.getElementById('wp-radius').value=btn.dataset.radius||12;
      document.getElementById('wp-direction').value=btn.dataset.direction||'';
      document.getElementById('wp-overlay-title').value=btn.dataset.title||'';
      document.getElementById('wp-overlay-html').value=btn.dataset.html||'';
      document.getElementById('wp-active').checked=btn.dataset.active=='1';
    }else{
      ['wp-id','wp-name','wp-building','wp-room','wp-scene','wp-heading','wp-direction','wp-overlay-title','wp-overlay-html'].forEach(id=>{
        const el=document.getElementById(id);if(el)el.value=id==='wp-id'?0:id==='wp-radius'?12:'';
      });
      document.getElementById('wp-radius').value=12;
      document.getElementById('wp-active').checked=true;
    }
    document.getElementById('wp-mind').value='';
  });
  const dirSel=document.getElementById('wp-direction');
  const hm={N:0,NE:45,E:90,SE:135,S:180,SW:225,W:270,NW:315};
  dirSel.addEventListener('change',()=>{const d=hm[dirSel.value];if(d!==undefined)document.getElementById('wp-heading').value=d;});
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
