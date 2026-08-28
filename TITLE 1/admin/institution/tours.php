<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.tours');
/**
 * Innovatech PH — admin: 360 tour scenes + room-to-room navigation + starting point.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = '360 Tours';
$pageSub = 'Scenes, featured images and the landing starting point';
$active = '360 Tours';

$pdo = db();
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];
$orgDir = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/scenes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['tour_action'] ?? '';
    try {
        if ($action === 'create' || $action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if ($title === '') throw new RuntimeException('Scene title required.');
            $buildingId = (int) ($_POST['building_id'] ?? 0) ?: null;
            $roomId = (int) ($_POST['room_id'] ?? 0) ?: null;
            $description = trim($_POST['description'] ?? '');
            $yaw = (float) ($_POST['initial_yaw'] ?? 0);
            $pitch = (float) ($_POST['initial_pitch'] ?? 0);

            $equirect = handle_media_picker('equirect_image', trim($inst['folder_path'], '/') . '/assets/scenes') ?: trim($_POST['equirect_path'] ?? '');
            $featured = handle_media_picker('featured_image', trim($inst['folder_path'], '/') . '/assets/scenes') ?: trim($_POST['featured_image_path'] ?? '');

            if ($action === 'create') {
                $pdo->prepare(
                    "INSERT INTO tour_scenes (institution_id, building_id, room_id, title, slug, description, equirect_path, featured_image_path, initial_yaw, initial_pitch, created_by)
                     VALUES (:iid,:bid,:rid,:title,:slug,:desc,:eq,:feat,:yaw,:pitch,:me)"
                )->execute([
                    'iid' => $iid, 'bid' => $buildingId, 'rid' => $roomId, 'title' => $title,
                    'slug' => slugify($title), 'desc' => $description ?: null, 'eq' => $equirect ?: null,
                    'feat' => $featured ?: null, 'yaw' => $yaw, 'pitch' => $pitch, 'me' => (int) current_user()['id'],
                ]);
                flash('success', 'Scene created.');
            } else {
                $pdo->prepare(
                    "UPDATE tour_scenes SET title=:title, building_id=:bid, room_id=:rid, description=:desc,
                            equirect_path=COALESCE(:eq, equirect_path), featured_image_path=COALESCE(:feat, featured_image_path),
                            initial_yaw=:yaw, initial_pitch=:pitch
                     WHERE id=:id AND institution_id=:iid"
                )->execute([
                    'title' => $title, 'bid' => $buildingId, 'rid' => $roomId, 'desc' => $description ?: null,
                    'eq' => $equirect ?: null, 'feat' => $featured ?: null, 'yaw' => $yaw, 'pitch' => $pitch,
                    'id' => $id, 'iid' => $iid,
                ]);
                flash('success', 'Scene updated.');
            }
        }
        if ($action === 'start') {
            // set one scene as landing start + update institution landing_mode + starting_scene_id
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tour_scenes SET is_landing_start=0 WHERE institution_id=:iid")->execute(['iid' => $iid]);
            $pdo->prepare("UPDATE tour_scenes SET is_landing_start=1 WHERE id=:id AND institution_id=:iid")->execute(['id' => $id, 'iid' => $iid]);
            $pdo->prepare("UPDATE institutions SET landing_mode='360_rotation', starting_scene_id=:sid WHERE id=:iid")
                ->execute(['sid' => $id, 'iid' => $iid]);
            $pdo->commit();
            // refresh session institution
            $_SESSION['user']['institution']['landing_mode'] = '360_rotation';
            $_SESSION['user']['institution']['starting_scene_id'] = $id;
            flash('success', 'Landing set to this scene (360 rotation).');
        }
        if ($action === 'delete') {
            $pdo->prepare("UPDATE tour_scenes SET deleted_at=NOW() WHERE id=:id AND institution_id=:iid")
                ->execute(['id' => (int) ($_POST['id'] ?? 0), 'iid' => $iid]);
            flash('success', 'Scene archived.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/tours');
}

$scenes = $pdo->prepare(
    "SELECT s.*, b.name AS building_name, r.name AS room_name
     FROM tour_scenes s
     LEFT JOIN buildings b ON b.id=s.building_id
     LEFT JOIN rooms r ON r.id=s.room_id
     WHERE s.institution_id=? AND s.deleted_at IS NULL ORDER BY s.sort_order, s.id DESC"
);
$scenes->execute([$iid]);
$scenes = $scenes->fetchAll();

$buildings = $pdo->prepare("SELECT id,name FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name");
$buildings->execute([$iid]);
$rooms = $pdo->prepare("SELECT id,name FROM rooms WHERE institution_id=? AND deleted_at IS NULL ORDER BY name");
$rooms->execute([$iid]);
$organizationsUrl = org_url($inst['slug'], 'assets/scenes');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-1" style="color:var(--ia-muted);font-size:13.5px"><?= count($scenes) ?> scene(s) · equirect & featured images go to <code><?= h($inst['slug']) ?>/assets/scenes/</code></p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#scene-modal" data-mode="create"><?= ia_icon('camera', 16) ?> Add scene</button>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>Scene</th><th>Location</th><th>Equirect</th><th>Featured</th><th>Start</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($scenes as $sc):
            $loc = array_filter([$sc['building_name'], $sc['room_name']]);
            ?>
          <tr>
            <td>
              <div style="font-weight:700"><?= h($sc['title']) ?></div>
              <div style="font-size:12px;color:var(--ia-muted)"><?= h($sc['slug']) ?></div>
            </td>
            <td style="color:var(--ia-muted)"><?= $loc ? h(implode(' · ', $loc)) : '—' ?></td>
            <td><?= $sc['equirect_path'] ? '<span class="badge badge-live">set</span>' : '<span class="badge badge-draft">missing</span>' ?></td>
            <td><?= $sc['featured_image_path'] ? '<span class="badge badge-live">set</span>' : '<span class="badge badge-draft">missing</span>' ?></td>
            <td><?= (int) $sc['is_landing_start'] === 1 ? '<span class="badge badge-live">start</span>' : '—' ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#scene-modal"
                  data-mode="edit" data-id="<?= (int) $sc['id'] ?>" data-title="<?= h($sc['title'], ENT_QUOTES) ?>"
                  data-building="<?= (int) $sc['building_id'] ?>" data-room="<?= (int) $sc['room_id'] ?>"
                  data-desc="<?= h($sc['description'], ENT_QUOTES) ?>" data-yaw="<?= (float) $sc['initial_yaw'] ?>" data-pitch="<?= (float) $sc['initial_pitch'] ?>"><?= ia_icon('file', 13) ?></button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="tour_action" value="start"><input type="hidden" name="id" value="<?= (int) $sc['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia" title="Make this the landing start"><?= ia_icon('rocket', 13) ?></button>
                </form>
                <form method="post" class="d-inline" data-delete-form data-confirm="Archive scene '<?= h($sc['title']) ?>'?">
                  <input type="hidden" name="tour_action" value="delete"><input type="hidden" name="id" value="<?= (int) $sc['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$scenes): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><?= ia_icon('camera', 26) ?></div><h4>No 360 scenes yet</h4><p>Upload a stitched panorama (or use the AI stitch tool) and set the starting point.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- scene modal -->
<div class="modal fade" id="scene-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">360 scene</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="tour_action" value="create" id="scene-action">
      <input type="hidden" name="id" value="0" id="scene-id">
      <div class="modal-body d-grid gap-3">
        <div class="row g-3">
          <div class="col-md-8"><label class="form-label">Scene title</label><input class="form-control" name="title" id="scene-title" required placeholder="Library Lobby"></div>
          <div class="col-md-4"><label class="form-label">Heading (yaw °)</label><input type="number" step="any" class="form-control" name="initial_yaw" id="scene-yaw" value="0"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Building</label>
            <select class="form-select" name="building_id" id="scene-building"><option value="">— none —</option><?php foreach ($buildings->fetchAll() as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Room</label>
            <select class="form-select" name="room_id" id="scene-room"><option value="">— none —</option><?php foreach ($rooms->fetchAll() as $r): ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <?php
            $pickerName = 'equirect_image';
            $pickerValue = '';
            $pickerLabel = 'Equirect/pano image';
            $pickerHelp = '2:1 equirectangular panorama (stiched by AI or camera).';
            require __DIR__ . '/../layout/media-picker.php';
            ?>
          </div>
          <div class="col-md-6">
            <?php
            $pickerName = 'featured_image';
            $pickerValue = '';
            $pickerLabel = 'Featured image';
            $pickerHelp = '';
            require __DIR__ . '/../layout/media-picker.php';
            ?>
          </div>
        </div>
        <div>
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" id="scene-desc" rows="3"></textarea>
        </div>
        <div class="row g-3" id="scene-pitch-row">
          <div class="col-md-4"><label class="form-label">Pitch °</label><input type="number" step="any" class="form-control" name="initial_pitch" id="scene-pitch" value="0"></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Save scene</button></div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('scene-modal')
  modal.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget
    const mode = btn.dataset.mode || 'create'
    document.getElementById('scene-action').value = mode
    document.getElementById('scene-id').value = btn.dataset.id || 0
    document.getElementById('scene-title').value = btn.dataset.title || ''
    document.getElementById('scene-building').value = btn.dataset.building || ''
    document.getElementById('scene-room').value = btn.dataset.room || ''
    document.getElementById('scene-desc').value = btn.dataset.desc || ''
    document.getElementById('scene-yaw').value = btn.dataset.yaw || 0
    document.getElementById('scene-pitch').value = btn.dataset.pitch || 0
    modal.querySelector('.modal-title').textContent = mode === 'edit' ? 'Edit scene' : 'Add 360 scene'
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>