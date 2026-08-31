<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.locations');
/**
 * Innovatech PH — admin: locations (rooms & campus areas).
 */
$pageTitle = 'Locations';
$pageSub = 'Rooms, offices, quads and landmarks';
$active = 'Locations';
$bodyClass = 'page-locations';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['loc_action'] ?? '';
    $type = $_POST['loc_type'] ?? 'room';
    try {
        if ($action === 'loc-create' || $action === 'loc-edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $buildingId = (int) ($_POST['building_id'] ?? 0) ?: null;
            $category = trim($_POST['category'] ?? '');
            if ($name === '') throw new RuntimeException('Location name required.');
            $type = $type === 'area' ? 'area' : 'room';

            if ($action === 'loc-create') {
                if ($type === 'room') {
                    crud()->insert('rooms', [
                        'institution_id' => $iid, 'building_id' => $buildingId, 'name' => $name,
                        'code' => trim($_POST['code'] ?? '') ?: null, 'floor_label' => trim($_POST['floor_label'] ?? '') ?: null,
                        'room_type' => $category ?: null, 'capacity' => (int) ($_POST['capacity'] ?? 0) ?: null,
                        'featured_image_path' => handle_media_picker('featured_image', trim($inst['folder_path'], '/') . '/assets/rooms')
                    ]);
                    flash('success', 'Room location added.');
                } else {
                    crud()->insert('campus_areas', [
                        'institution_id' => $iid, 'building_id' => $buildingId,
                        'name' => $name, 'area_type' => $category ?: null,
                        'description' => trim($_POST['description'] ?? '') ?: null
                    ]);
                    flash('success', 'Area location added.');
                }
            } else {
                if ($type === 'room') {
                    $updates = ['name' => $name, 'building_id' => $buildingId, 'code' => trim($_POST['code'] ?? '') ?: null, 'floor_label' => trim($_POST['floor_label'] ?? '') ?: null, 'room_type' => $category ?: null, 'capacity' => (int) ($_POST['capacity'] ?? 0) ?: null];
                    $featured = handle_media_picker('featured_image', trim($inst['folder_path'], '/') . '/assets/rooms');
                    if ($featured) $updates['featured_image_path'] = $featured;
                    crud()->update('rooms', $updates, ['id' => $id, 'institution_id' => $iid]);
                    flash('success', 'Room location updated.');
                } else {
                    crud()->update('campus_areas', [
                        'name' => $name, 'building_id' => $buildingId,
                        'area_type' => $category ?: null, 'description' => trim($_POST['description'] ?? '') ?: null
                    ], ['id' => $id, 'institution_id' => $iid]);
                    flash('success', 'Area location updated.');
                }
            }
        }
        if ($action === 'loc-delete') {
            if ($type === 'room') {
                crud()->raw('UPDATE rooms SET deleted_at=NOW() WHERE id=:id AND institution_id=:iid', ['id' => (int) ($_POST['id'] ?? 0), 'iid' => $iid])->execute();
                flash('success', 'Room location archived.');
            } else {
                crud()->delete('campus_areas', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
                flash('success', 'Area location removed.');
            }
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/locations');
}

$buildings = crud()->select('buildings', 'id, name', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY name');
$rows = crud()->raw(
    "SELECT r.id, r.name, r.code, r.floor_label, r.room_type AS category, r.capacity, r.description, r.featured_image_path, r.building_id, b.name AS building_name, 'room' AS kind
       FROM rooms r LEFT JOIN buildings b ON b.id=r.building_id
      WHERE r.institution_id=:iid1 AND r.deleted_at IS NULL
     UNION ALL
     SELECT a.id, a.name, NULL, NULL, a.area_type, NULL, a.description, NULL, a.building_id, b.name, 'area'
       FROM campus_areas a LEFT JOIN buildings b ON b.id=a.building_id
      WHERE a.institution_id=:iid2",
    [':iid1' => $iid, ':iid2' => $iid])->fetchAll();
usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));
$typeFilter = $_GET['type'] ?? 'all';
if (in_array($typeFilter, ['room', 'area'], true)) {
    $rows = array_values(array_filter($rows, fn($r) => $r['kind'] === $typeFilter));
}
$roomCount = count(array_filter($rows, fn($r) => $r['kind'] === 'room'));
$areaCount = count(array_filter($rows, fn($r) => $r['kind'] === 'area'));
?>
<div class="row g-4">
  <div class="col-12">
    <div class="ia-card">
      <div class="card-head">
        <h3>Locations</h3>
        <div class="d-flex gap-2 align-items-center">
          <div class="btn-group btn-group-sm" role="group" aria-label="Filter by type">
            <a class="btn <?= $typeFilter === 'all' ? 'btn-grad' : 'btn-outline-ia' ?>" href="<?= url('admin/institution/locations') ?>">All <?= $roomCount + $areaCount ?></a>
            <a class="btn <?= $typeFilter === 'room' ? 'btn-grad' : 'btn-outline-ia' ?>" href="<?= url('admin/institution/locations?type=room') ?>">Rooms <?= $roomCount ?></a>
            <a class="btn <?= $typeFilter === 'area' ? 'btn-grad' : 'btn-outline-ia' ?>" href="<?= url('admin/institution/locations?type=area') ?>">Areas <?= $areaCount ?></a>
          </div>
          <input type="search" class="form-control form-control-sm" id="loc-search" placeholder="Search locations…" style="max-width:220px">
          <button class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#location-modal" data-mode="create">+ Add location</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead>
            <tr>
              <th>Location</th><th>Type</th><th>Category</th><th>Building</th><th>Details</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
              $isRoom = $r['kind'] === 'room';
              $details = $isRoom
                  ? trim(($r['floor_label'] ?: '—') . ' · ' . ((int) $r['capacity'] ? (int) $r['capacity'] . ' seats' : ''), ' ·')
                  : mb_strimwidth((string) ($r['description'] ?? ''), 0, 60, '…');
              ?>
              <tr class="loc-row" data-kind="<?= $r['kind'] ?>" data-name="<?= h(mb_strtolower($r['name'])) ?>">
                <td>
                  <div class="fw-semibold"><?= h($r['name']) ?></div>
                  <?php if ($r['code']): ?><div class="fs-12 text-ia-muted"><?= h($r['code']) ?></div><?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $isRoom ? 'badge-surface' : 'badge-soft-info' ?>"><?= $isRoom ? 'Room' : 'Area' ?></span>
                </td>
                <td class="text-ia-muted"><?= h($r['category'] ?: '—') ?></td>
                <td class="text-ia-muted"><?= h($r['building_name'] ?? '—') ?></td>
                <td class="text-ia-muted"><?= h($details ?: '—') ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#location-modal"
                      data-mode="edit" data-kind="<?= $r['kind'] ?>" data-id="<?= (int) $r['id'] ?>" data-name="<?= h($r['name'], ENT_QUOTES) ?>" data-building="<?= (int) $r['building_id'] ?>"
                      data-category="<?= h($r['category'], ENT_QUOTES) ?>" data-code="<?= h($r['code'], ENT_QUOTES) ?>" data-floor="<?= h($r['floor_label'], ENT_QUOTES) ?>"
                      data-cap="<?= (int) $r['capacity'] ?>" data-desc="<?= h($r['description'], ENT_QUOTES) ?>"><?= ia_icon('file', 13) ?></button>
                    <form method="post" class="d-inline" data-delete-form data-confirm="<?= $isRoom ? 'Archive' : 'Remove' ?> '<?= h($r['name']) ?>'?">
                      <input type="hidden" name="loc_action" value="loc-delete">
                      <input type="hidden" name="loc_type" value="<?= $r['kind'] ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No locations</h4><p>Add buildings first, then create room or area locations inside them.</p></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- location modal -->
<div class="modal fade" id="location-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Location</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="loc_action" value="loc-create" id="loc-action">
      <input type="hidden" name="id" value="0" id="loc-id">
      <div class="modal-body d-grid gap-3">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select class="form-select" name="loc_type" id="loc-type">
              <option value="room">Room</option>
              <option value="area">Area</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label" id="loc-name-label">Room name</label>
            <input class="form-control" name="name" id="loc-name" required>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Building</label>
            <select class="form-select" name="building_id" id="loc-building">
              <option value="">— none —</option>
              <?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" id="loc-cat-label">Room type</label>
            <input class="form-control" name="category" id="loc-category" placeholder="classroom">
          </div>
        </div>

        <div class="row g-3 loc-room-fields">
          <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" name="code" id="loc-code"></div>
          <div class="col-md-4"><label class="form-label">Floor</label><input class="form-control" name="floor_label" id="loc-floor" placeholder="2F"></div>
          <div class="col-md-4"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" id="loc-cap"></div>
        </div>

        <div class="loc-area-fields d-none">
          <div>
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label mb-1">Description</label>
              <button type="button" class="btn btn-sm btn-outline-ia" data-ai-gen data-ai-type="campus_area" data-ai-id-el="loc-id" data-ai-target-el="loc-desc"><?= ia_icon('wand', 13) ?> Generate AI</button>
            </div>
            <textarea class="form-control" name="description" id="loc-desc" rows="3"></textarea>
          </div>
        </div>

        <div class="loc-room-fields">
          <?php
          $pickerName = 'featured_image';
          $pickerValue = '';
          $pickerLabel = 'Featured Image';
          $pickerHelp = 'Used in the campus directory card (if applicable).';
          require __DIR__ . '/../layout/media-picker.php';
          ?>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Save</button></div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const typeSel = document.getElementById('loc-type')
  const action  = document.getElementById('loc-action')
  const roomFields = document.querySelectorAll('.loc-room-fields')
  const areaFields = document.querySelectorAll('.loc-area-fields')
  const nameLabel  = document.getElementById('loc-name-label')
  const catLabel   = document.getElementById('loc-cat-label')
  let editKind = null

  function syncType() {
    const isRoom = typeSel.value === 'room'
    roomFields.forEach(el => el.classList.toggle('d-none', !isRoom))
    areaFields.forEach(el => el.classList.toggle('d-none', isRoom))
    nameLabel.textContent = isRoom ? 'Room name' : 'Area name'
    catLabel.textContent  = isRoom ? 'Room type (classroom, lab, office…)' : 'Area type (quad, field, gate…)'
    if (editKind === null || editKind === typeSel.value) {
      action.value = (document.getElementById('loc-id').value ? 'loc-edit' : 'loc-create')
    } else {
      action.value = 'loc-create'
      document.getElementById('loc-id').value = 0
      editKind = null
    }
  }
  typeSel.addEventListener('change', syncType)

  const modal = document.getElementById('location-modal')
  modal.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget
    const mode = btn.dataset.mode || 'create'
    const kind = btn.dataset.kind || 'room'
    editKind = kind
    typeSel.value = kind
    document.getElementById('loc-id').value = btn.dataset.id || 0
    document.getElementById('loc-action').value = (mode === 'edit' ? 'loc-edit' : 'loc-create')
    document.getElementById('loc-name').value = btn.dataset.name || ''
    document.getElementById('loc-building').value = btn.dataset.building || ''
    document.getElementById('loc-category').value = btn.dataset.category || ''
    document.getElementById('loc-code').value = btn.dataset.code || ''
    document.getElementById('loc-floor').value = btn.dataset.floor || ''
    document.getElementById('loc-cap').value = btn.dataset.cap || 0
    document.getElementById('loc-desc').value = btn.dataset.desc || ''
    syncType()
  })

  document.getElementById('loc-search').addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase()
    document.querySelectorAll('.loc-row').forEach(row => {
      row.classList.toggle('d-none', q !== '' && !String(row.dataset.name).includes(q))
    })
  })

  
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>