<?php
/**
 * Innovatech PH — staff: facilities (locations with info + AI descriptions).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.facilities');
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Facilities';
$pageSub = 'Libraries, cafeterias, gyms — link them to buildings, rooms or areas';
$active = 'Facilities';

$inst = current_institution();
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/facilities';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['fac_action'] ?? '';
    try {
        if ($action === 'create' || $action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Facility name required.');
            $desc = trim($_POST['description'] ?? '');
            $hours = trim($_POST['hours'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $info = json_encode([
                'hours' => $hours ?: null,
                'phone' => $phone ?: null,
                'capacity' => (int) ($_POST['capacity'] ?? 0) ?: null,
            ]);
            $featured = '';

            if (!empty($_FILES['image']['name'])) {
                if (!is_dir($orgAbs)) mkdir($orgAbs, 0775, true);
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
                $nameF = random_token(6) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $orgAbs . '/' . $nameF);
                $featured = 'assets/facilities/' . $nameF;
                try {
                    crud()->insert('media_assets', ['institution_id' => $iid, 'uploaded_by' => $me, 'kind' => 'featured', 'file_path' => $featured, 'original_name' => $_FILES['image']['name']]);
                } catch (Throwable $e) {}
            }

            if ($action === 'create') {
                $newId = crud()->insert('facilities', [
                        'institution_id' => $iid, 'building_id' => (int) ($_POST['building_id'] ?? 0) ?: null,
                        'room_id' => (int) ($_POST['room_id'] ?? 0) ?: null,
                        'campus_area_id' => (int) ($_POST['campus_area_id'] ?? 0) ?: null,
                        'name' => $name, 'description' => $desc ?: null,
                        'featured_image_path' => $featured ?: null, 'info_json' => $info, 'created_by' => $me,
                    ]);
                audit('facilities.create', 'content', 'facility', $newId);
                flash('success', 'Facility added.');
            } else {
                crud()->raw(
                    'UPDATE facilities SET name=:name, building_id=:bi, room_id=:ri, campus_area_id=:ai, description=:desc, featured_image_path=COALESCE(:img, featured_image_path), info_json=:info WHERE id=:id AND institution_id=:iid',
                    ['name' => $name, 'bi' => (int) ($_POST['building_id'] ?? 0) ?: null, 'ri' => (int) ($_POST['room_id'] ?? 0) ?: null,
                     'ai' => (int) ($_POST['campus_area_id'] ?? 0) ?: null, 'desc' => $desc ?: null, 'img' => $featured ?: null,
                     'info' => $info, 'id' => $id, 'iid' => $iid]
                )->execute();
                audit('facilities.update', 'content', 'facility', $id);
                flash('success', 'Facility updated.');
            }
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            crud()->delete('facilities', ['id' => $id, 'institution_id' => $iid]);
            audit('facilities.delete', 'content', 'facility', $id);
            flash('success', 'Facility removed.');
        }
        if ($action === 'ai-describe') {
            $id = (int) ($_POST['id'] ?? 0);
            $fac = crud()->raw('SELECT name, description FROM facilities WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $id, 'iid' => $iid])->fetch();
            if (!$fac) throw new RuntimeException('Facility not found.');
            $out = sprintf(
                "%s serves as an essential campus facility at %s%s. Find its full details, opening hours and directions on the campus floor plan, or explore it through the 360° tour.",
                $fac['name'],
                $inst['name'],
                trim((string) $fac['description']) !== '' ? ' — ' . rtrim($fac['description'], '.') . '.' : ''
            );
            crud()->update('facilities', ['ai_description' => $out], ['id' => $id, 'institution_id' => $iid]);
            crud()->insert('ai_info_jobs', ['institution_id' => $iid, 'created_by' => $me, 'target_type' => 'facility', 'target_id' => $id, 'prompt' => null, 'output_text' => $out, 'status' => 'completed']);
            audit('ai_info.generate', 'ai', 'facility', $id);
            flash('success', 'AI description generated.');
        }
        if ($action === 'remove-image') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = crud()->raw('SELECT featured_image_path FROM facilities WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $id, 'iid' => $iid])->fetch();
            $img = $row['featured_image_path'] ?? null;
            crud()->update('facilities', ['featured_image_path' => null], ['id' => $id, 'institution_id' => $iid]);
            if ($img && is_file(ROOT_PATH . '/' . ltrim($img, '/'))) unlink(ROOT_PATH . '/' . ltrim($img, '/'));
            flash('success', 'Featured image removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/staff/facilities');
}

$facilities = crud()->raw(
    'SELECT f.*, b.name building_name, r.name room_name, a.name area_name FROM facilities f LEFT JOIN buildings b ON b.id=f.building_id LEFT JOIN rooms r ON r.id=f.room_id LEFT JOIN campus_areas a ON a.id=f.campus_area_id WHERE f.institution_id=:iid ORDER BY f.name',
    [':iid' => $iid]
)->fetchAll();

$buildings = crud()->select('buildings', 'id,name', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY name');
$rooms = crud()->select('rooms', 'id,name', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY name');
$areas = crud()->select('campus_areas', 'id,name', ['institution_id' => $iid], 'ORDER BY name');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-1" style="color:var(--ia-muted);font-size:13.5px"><?= count($facilities) ?> facility record(s)</p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#fac-modal" data-mode="create"><?= ia_icon('building', 16) ?> Add facility</button>
</div>

<div class="row g-3">
  <?php foreach ($facilities as $f):
      $info = json_decode($f['info_json'] ?? 'null', true) ?: [];
      $loc = trim(($f['building_name'] ?? '') . ' ' . ($f['room_name'] ?? '') . ' ' . ($f['area_name'] ?? ''));
      ?>
    <div class="col-md-6 col-xl-4">
      <div class="fac-card">
        <div class="fac-img">
          <?php if ($f['featured_image_path']): ?>
            <img src="<?= h(org_url($inst['slug'], $f['featured_image_path'])) ?>" alt="">
          <?php else: ?>
            <div class="fac-img-placeholder"><?= ia_icon('image', 26) ?></div>
          <?php endif; ?>
        </div>
        <div class="fac-body">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <h4 class="fac-title"><?= h($f['name']) ?></h4>
              <div class="fac-loc"><?= h($loc ?: 'Campus') ?></div>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#fac-modal"
                data-mode="edit" data-id="<?= (int) $f['id'] ?>" data-name="<?= h($f['name'], ENT_QUOTES) ?>"
                data-building="<?= (int) $f['building_id'] ?>" data-room="<?= (int) $f['room_id'] ?>" data-area="<?= (int) $f['campus_area_id'] ?>"
                data-desc="<?= h($f['description'], ENT_QUOTES) ?>" data-hours="<?= h($info['hours'] ?? '', ENT_QUOTES) ?>"
                data-phone="<?= h($info['phone'] ?? '', ENT_QUOTES) ?>" data-cap="<?= (int) ($info['capacity'] ?? 0) ?>"><?= ia_icon('file', 13) ?></button>
              <form method="post" data-delete-form data-confirm="Delete facility <?= h($f['name']) ?>?">
                <input type="hidden" name="fac_action" value="delete"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
              </form>
            </div>
          </div>
          <?php if ($f['ai_description']): ?>
            <p class="fac-ai"><?= h(mb_strimwidth($f['ai_description'], 0, 180, '…')) ?></p>
          <?php elseif ($f['description']): ?>
            <p class="fac-desc"><?= h(mb_strimwidth($f['description'], 0, 130, '…')) ?></p>
          <?php endif; ?>
          <div class="fac-tags">
            <?php if ($info['hours'] ?? null): ?><span class="badge" style="background:var(--ia-surface-2)"><?= ia_icon('clock', 11) ?> <?= h($info['hours']) ?></span><?php endif; ?>
            <?php if ($info['phone'] ?? null): ?><span class="badge" style="background:var(--ia-surface-2)"><?= ia_icon('info', 11) ?> <?= h($info['phone']) ?></span><?php endif; ?>
            <?php if ($info['capacity'] ?? null): ?><span class="badge" style="background:var(--ia-surface-2)"><?= (int) $info['capacity'] ?> seats</span><?php endif; ?>
            <?php if (!$f['featured_image_path']): ?><span class="badge badge-draft">no image</span><?php endif; ?>
          </div>
          <div class="fac-actions">
            <?php if (!$f['ai_description']): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="fac_action" value="ai-describe"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button class="btn btn-sm btn-outline-ia"><?= ia_icon('wand', 13) ?> AI describe</button>
              </form>
            <?php else: ?>
              <span class="badge badge-live"><?= ia_icon('sparkles', 11) ?> AI info ready</span>
            <?php endif; ?>
            <?php if ($f['featured_image_path']): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="fac_action" value="remove-image"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button class="btn btn-sm btn-outline-ia" title="Remove featured image"><?= ia_icon('x', 12) ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (count($facilities) === 0): ?>
    <div class="col-12"><div class="ia-card"><div class="empty-state"><div class="empty-icon"><?= ia_icon('building', 26) ?></div><h4>No facilities yet</h4><p>Add libraries, cafeterias, clinics, gyms — each can carry hours, contact and an AI description.</p></div></div></div>
  <?php endif; ?>
</div>

<!-- facility modal -->
<div class="modal fade" id="fac-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Facility</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="fac_action" value="create" id="fac-action">
      <input type="hidden" name="id" value="0" id="fac-id">
      <div class="modal-body d-grid gap-3">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" id="fac-name" required placeholder="Main Library"></div>
          <div class="col-md-6"><label class="form-label">Featured image</label><input class="form-control" type="file" name="image" accept="image/*"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Building</label>
            <select class="form-select" name="building_id" id="fac-building"><option value="">— none —</option><?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Room</label>
            <select class="form-select" name="room_id" id="fac-room"><option value="">— none —</option><?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Campus area</label>
            <select class="form-select" name="campus_area_id" id="fac-area"><option value="">— none —</option><?php foreach ($areas as $a): ?><option value="<?= (int) $a['id'] ?>"><?= h($a['name']) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div><label class="form-label">Description</label><textarea class="form-control" name="description" id="fac-desc" rows="3"></textarea></div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Operating hours</label><input class="form-control" name="hours" id="fac-hours" placeholder="Mon–Fri 8am–6pm"></div>
          <div class="col-md-4"><label class="form-label">Contact</label><input class="form-control" name="phone" id="fac-phone"></div>
          <div class="col-md-4"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" id="fac-cap"></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Save facility</button></div>
    </form>
  </div></div>
</div>

<style>
  .fac-card { background: var(--ia-surface); border:1px solid var(--ia-border); border-radius: var(--ia-radius); overflow:hidden; box-shadow: var(--ia-shadow); height:100%; display:flex; flex-direction:column; transition:transform .2s, box-shadow .2s; }
  .fac-card:hover { transform: translateY(-3px); box-shadow: var(--ia-shadow-lg); }
  .fac-img { aspect-ratio:16/9; background: var(--ia-surface-2); overflow:hidden; }
  .fac-img img { width:100%; height:100%; object-fit:cover; }
  .fac-img-placeholder { width:100%; height:100%; display:grid; place-items:center; color:var(--ia-muted); }
  .fac-body { padding:16px; display:flex; flex-direction:column; gap:9px; flex-grow:1; }
  .fac-title { font-size:16px; font-weight:800; margin:0; }
  .fac-loc { font-size:12.5px; color:var(--ia-muted); }
  .fac-desc, .fac-ai { font-size:13px; color:var(--ia-muted); margin:0; line-height:1.55; }
  .fac-ai { color: var(--ia-text); }
  .fac-tags { display:flex; flex-wrap:wrap; gap:6px; }
  .fac-actions { margin-top:auto; padding-top:6px; display:flex; gap:6px; align-items:center; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('fac-modal').addEventListener('show.bs.modal', (e) => {
    const b = e.relatedTarget, m = b.dataset.mode || 'create'
    document.getElementById('fac-action').value = m
    document.getElementById('fac-id').value = b.dataset.id || 0
    document.getElementById('fac-name').value = b.dataset.name || ''
    document.getElementById('fac-building').value = b.dataset.building || ''
    document.getElementById('fac-room').value = b.dataset.room || ''
    document.getElementById('fac-area').value = b.dataset.area || ''
    document.getElementById('fac-desc').value = b.dataset.desc || ''
    document.getElementById('fac-hours').value = b.dataset.hours || ''
    document.getElementById('fac-phone').value = b.dataset.phone || ''
    document.getElementById('fac-cap').value = b.dataset.cap || 0
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>