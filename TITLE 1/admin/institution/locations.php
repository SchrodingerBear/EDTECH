<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.locations');
/**
 * Innovatech PH — admin: rooms & campus areas.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Rooms & Areas';
$pageSub = 'Classrooms, offices, quads and landmarks';
$active = 'Rooms & Areas';

$pdo = db();
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['loc_action'] ?? '';
    try {
        if ($action === 'room-create' || $action === 'room-edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $buildingId = (int) ($_POST['building_id'] ?? 0) ?: null;
            $roomType = trim($_POST['room_type'] ?? '');
            $floor = trim($_POST['floor_label'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $capacity = (int) ($_POST['capacity'] ?? 0) ?: null;
            if ($name === '') throw new RuntimeException('Room name required.');
            // Handle featured image via media picker
            $featured = handle_media_picker('featured_image', trim($inst['folder_path'], '/') . '/assets/rooms');
            
            if ($action === 'room-create') {
                $pdo->prepare("INSERT INTO rooms (institution_id, building_id, name, code, floor_label, room_type, capacity, featured_image_path)
                               VALUES (:iid,:bid,:name,:code,:floor,:type,:cap,:feat)")
                    ->execute(['iid' => $iid, 'bid' => $buildingId, 'name' => $name, 'code' => $code ?: null, 'floor' => $floor ?: null, 'type' => $roomType ?: null, 'cap' => $capacity, 'feat' => $featured]);
                flash('success', 'Room added.');
            } else {
                if ($featured) {
                    $pdo->prepare("UPDATE rooms SET name=:name, building_id=:bid, code=:code, floor_label=:floor, room_type=:type, capacity=:cap, featured_image_path=:feat WHERE id=:id AND institution_id=:iid")
                        ->execute(['name' => $name, 'bid' => $buildingId, 'code' => $code ?: null, 'floor' => $floor ?: null, 'type' => $roomType ?: null, 'cap' => $capacity, 'feat' => $featured, 'id' => $id, 'iid' => $iid]);
                } else {
                    $pdo->prepare("UPDATE rooms SET name=:name, building_id=:bid, code=:code, floor_label=:floor, room_type=:type, capacity=:cap WHERE id=:id AND institution_id=:iid")
                        ->execute(['name' => $name, 'bid' => $buildingId, 'code' => $code ?: null, 'floor' => $floor ?: null, 'type' => $roomType ?: null, 'cap' => $capacity, 'id' => $id, 'iid' => $iid]);
                }
                flash('success', 'Room updated.');
            }
        }
        if ($action === 'room-delete') {
            $pdo->prepare("UPDATE rooms SET deleted_at=NOW() WHERE id=:id AND institution_id=:iid")
                ->execute(['id' => (int) ($_POST['id'] ?? 0), 'iid' => $iid]);
            flash('success', 'Room archived.');
        }
        if ($action === 'area-create') {
            $pdo->prepare("INSERT INTO campus_areas (institution_id, building_id, name, area_type, description)
                           VALUES (:iid,:bid,:name,:type,:desc)")
                ->execute(['iid' => $iid, 'bid' => (int) ($_POST['building_id'] ?? 0) ?: null, 'name' => trim($_POST['name'] ?? ''), 'type' => trim($_POST['area_type'] ?? '') ?: null, 'desc' => trim($_POST['description'] ?? '') ?: null]);
            flash('success', 'Area added.');
        }
        if ($action === 'area-delete') {
            $pdo->prepare("DELETE FROM campus_areas WHERE id=:id AND institution_id=:iid")
                ->execute(['id' => (int) ($_POST['id'] ?? 0), 'iid' => $iid]);
            flash('success', 'Area removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/locations');
}

$buildings = $pdo->prepare("SELECT id, name FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name");
$buildings->execute([$iid]);
$buildings = $buildings->fetchAll();

$rooms = $pdo->prepare("SELECT r.*, b.name AS building_name FROM rooms r LEFT JOIN buildings b ON b.id=r.building_id
                        WHERE r.institution_id=? AND r.deleted_at IS NULL ORDER BY r.name");
$rooms->execute([$iid]);
$rooms = $rooms->fetchAll();

$areas = $pdo->prepare("SELECT a.*, b.name AS building_name FROM campus_areas a LEFT JOIN buildings b ON b.id=a.building_id
                        WHERE a.institution_id=? ORDER BY a.name");
$areas->execute([$iid]);
$areas = $areas->fetchAll();
?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="ia-card">
      <div class="card-head"><h3>Rooms</h3>
        <button class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#room-modal" data-mode="create">+ Add room</button>
      </div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>Room</th><th>Building</th><th>Type</th><th>Floor</th><th>Capacity</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($rooms as $r): ?>
              <tr>
                <td>
                  <div style="font-weight:600"><?= h($r['name']) ?></div>
                  <?php if ($r['code']): ?><div style="font-size:12px;color:var(--ia-muted)"><?= h($r['code']) ?></div><?php endif; ?>
                </td>
                <td style="color:var(--ia-muted)"><?= h($r['building_name'] ?? '—') ?></td>
                <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($r['room_type'] ?: '—') ?></span></td>
                <td style="color:var(--ia-muted)"><?= h($r['floor_label'] ?: '—') ?></td>
                <td style="color:var(--ia-muted)"><?= (int) $r['capacity'] ?: '—' ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#room-modal"
                      data-mode="edit" data-id="<?= (int) $r['id'] ?>" data-name="<?= h($r['name'], ENT_QUOTES) ?>" data-building="<?= (int) $r['building_id'] ?>"
                      data-code="<?= h($r['code'], ENT_QUOTES) ?>" data-floor="<?= h($r['floor_label'], ENT_QUOTES) ?>" data-type="<?= h($r['room_type'], ENT_QUOTES) ?>" data-cap="<?= (int) $r['capacity'] ?>"><?= ia_icon('file', 13) ?></button>
                    <form method="post" class="d-inline" data-delete-form data-confirm="Archive '<?= h($r['name']) ?>'?">
                      <input type="hidden" name="loc_action" value="room-delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rooms): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No rooms</h4><p>Add classrooms, offices and labs.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="ia-card">
      <div class="card-head"><h3>Campus areas</h3>
        <button class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#area-modal">+ Add</button>
      </div>
      <div class="card-body d-grid gap-2" style="padding:16px">
        <?php foreach ($areas as $a): ?>
          <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background:var(--ia-surface-2)">
            <div class="flex-grow-1">
              <div style="font-weight:600;font-size:13.5px"><?= h($a['name']) ?></div>
              <div style="font-size:12px;color:var(--ia-muted)"><?= h($a['area_type'] ?: 'area') ?><?= $a['building_name'] ? ' · ' . h($a['building_name']) : '' ?></div>
            </div>
            <form method="post" data-delete-form data-confirm="Remove area <?= h($a['name']) ?>?">
              <input type="hidden" name="loc_action" value="area-delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$areas): ?><p class="text-muted" style="font-size:13.5px">Quad, parking, sports fields, gates — add campus landmarks here.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- room modal -->
<div class="modal fade" id="room-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Room</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="loc_action" value="room-create" id="room-action">
      <input type="hidden" name="id" value="0" id="room-id">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Room name</label><input class="form-control" name="name" id="room-name" required></div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Building</label>
            <select class="form-select" name="building_id" id="room-building">
              <option value="">— none —</option>
              <?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Room type</label><input class="form-control" name="room_type" id="room-type" placeholder="classroom"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" name="code" id="room-code"></div>
          <div class="col-md-4"><label class="form-label">Floor</label><input class="form-control" name="floor_label" id="room-floor" placeholder="2F"></div>
          <div class="col-md-4"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" id="room-cap"></div>
        </div>
        <div>
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

<!-- area modal -->
<div class="modal fade" id="area-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Campus area</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="loc_action" value="area-create">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Name</label><input class="form-control" name="name" required placeholder="Main Quadrangle"></div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Type</label><input class="form-control" name="area_type" placeholder="quad">
          </div>
          <div class="col-md-6">
            <label class="form-label">Building</label>
            <select class="form-select" name="building_id"><option value="">— none —</option><?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Add</button></div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('room-modal').addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget
    const mode = btn.dataset.mode || 'create'
    document.getElementById('room-action').value = mode
    document.getElementById('room-id').value = btn.dataset.id || 0
    document.getElementById('room-name').value = btn.dataset.name || ''
    document.getElementById('room-building').value = btn.dataset.building || ''
    document.getElementById('room-code').value = btn.dataset.code || ''
    document.getElementById('room-floor').value = btn.dataset.floor || ''
    document.getElementById('room-type').value = btn.dataset.type || ''
    document.getElementById('room-cap').value = btn.dataset.cap || 0
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>