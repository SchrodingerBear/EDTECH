<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.buildings');
/**
 * Innovatech PH — admin: buildings CRUD (AI description generator included).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Buildings';
$pageSub = 'Structures → Floor Plans → Rooms → Facilities';
$active = 'Buildings';

$pdo = db();
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['bdg_action'] ?? '';
    try {
        if ($action === 'create' || $action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            // Handle featured image via media picker
            $featured = handle_media_picker('featured_image', trim($inst['folder_path'], '/') . '/assets/buildings');
            
            if ($action === 'create') {
                $pdo->prepare("INSERT INTO buildings (institution_id, name, code, description, featured_image_path, sort_order)
                               VALUES (:iid,:name,:code,:desc,:feat,0)")
                    ->execute(['iid' => $iid, 'name' => $name, 'code' => $code ?: null, 'desc' => $description ?: null, 'feat' => $featured]);
                flash('success', 'Building added.');
            } else {
                if ($featured) {
                    $pdo->prepare("UPDATE buildings SET name=:name, code=:code, description=:desc, featured_image_path=:feat WHERE id=:id AND institution_id=:iid")
                        ->execute(['name' => $name, 'code' => $code ?: null, 'desc' => $description ?: null, 'feat' => $featured, 'id' => $id, 'iid' => $iid]);
                } else {
                    $pdo->prepare("UPDATE buildings SET name=:name, code=:code, description=:desc WHERE id=:id AND institution_id=:iid")
                        ->execute(['name' => $name, 'code' => $code ?: null, 'desc' => $description ?: null, 'id' => $id, 'iid' => $iid]);
                }
                flash('success', 'Building updated.');
            }
        }
        if ($action === 'delete') {
            $pdo->prepare("UPDATE buildings SET deleted_at=NOW() WHERE id=:id AND institution_id=:iid")
                ->execute(['id' => (int) ($_POST['id'] ?? 0), 'iid' => $iid]);
            flash('success', 'Building archived.');
        }
        if ($action === 'ai') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = (string) ($_POST['name'] ?? '');
            $desc = (string) ($_POST['desc'] ?? '');
            // mock-aware AI generation; real API hooks in later
            $ai = "The $name building at " . ($inst['name'] ?? 'campus') . " is a key part of campus life. " .
                  ($desc !== '' ? rtrim($desc, '.') . '. ' : '') .
                  "Visitors commonly look for it when exploring facilities, offices and learning spaces. " .
                  "Use the 360° tour to walk inside and see what this building offers.";
            $pdo->prepare("UPDATE buildings SET ai_description=:a WHERE id=:id AND institution_id=:iid")
                ->execute(['a' => $ai, 'id' => $id, 'iid' => $iid]);
            flash('success', 'AI description generated.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/buildings');
}

$buildings = $pdo->prepare("SELECT b.*, (SELECT COUNT(*) FROM rooms r WHERE r.building_id=b.id) AS room_count
                            FROM buildings b WHERE b.institution_id=? AND b.deleted_at IS NULL ORDER BY b.sort_order, b.name");
$buildings->execute([$iid]);
$buildings = $buildings->fetchAll();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-1" style="color:var(--ia-muted);font-size:13.5px"><?= count($buildings) ?> building(s)</p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#bdg-modal" data-mode="create"><?= ia_icon('building', 16) ?> Add building</button>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>Building</th><th>Code</th><th>Rooms</th><th>AI description</th><th>Floor Plans</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($buildings as $b): ?>
          <tr>
            <td style="font-weight:700"><?= h($b['name']) ?></td>
            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($b['code'] ?: '—') ?></span></td>
            <td style="color:var(--ia-muted)"><?= (int) $b['room_count'] ?></td>
            <td><?= $b['ai_description'] ? '<span class="badge badge-live">yes</span>' : '<span class="badge badge-off">none</span>' ?></td>
            <td><a class="btn btn-sm btn-outline-ia" href="floor-plans?building=<?= (int)$b['id'] ?>" title="View floor plans for this building"><?= ia_icon('compass', 13) ?> Floor Plans</a></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#bdg-modal"
                  data-mode="edit" data-id="<?= (int) $b['id'] ?>" data-name="<?= h($b['name'], ENT_QUOTES) ?>" data-code="<?= h($b['code'], ENT_QUOTES) ?>" data-desc="<?= h($b['description'], ENT_QUOTES) ?>"><?= ia_icon('file', 13) ?></button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="bdg_action" value="ai"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <input type="hidden" name="name" value="<?= h($b['name']) ?>"><input type="hidden" name="desc" value="<?= h($b['description']) ?>">
                  <button class="btn btn-sm btn-outline-ia" title="Generate AI description"><?= ia_icon('sparkles', 13) ?></button>
                </form>
                <form method="post" class="d-inline" data-delete-form data-confirm="Archive '<?= h($b['name']) ?>'?">
                  <input type="hidden" name="bdg_action" value="delete"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$buildings): ?><tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><?= ia_icon('building', 26) ?></div><h4>No buildings yet</h4><p>Organize your campus before adding scenes.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- create/edit modal -->
<div class="modal fade" id="bdg-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Building</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="bdg_action" value="create" id="bdg-action">
      <input type="hidden" name="id" value="0" id="bdg-id">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Name</label><input class="form-control" name="name" id="bdg-name" required></div>
        <div><label class="form-label">Code</label><input class="form-control" name="code" id="bdg-code" placeholder="B-LIB"></div>
        <div><label class="form-label">Description</label><textarea class="form-control" name="description" id="bdg-desc" rows="4"></textarea></div>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('bdg-modal')
  modal.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget
    const mode = btn.dataset.mode || 'create'
    document.getElementById('bdg-action').value = mode
    document.getElementById('bdg-id').value = btn.dataset.id || 0
    document.getElementById('bdg-name').value = btn.dataset.name || ''
    document.getElementById('bdg-code').value = btn.dataset.code || ''
    document.getElementById('bdg-desc').value = btn.dataset.desc || ''
    modal.querySelector('.modal-title').textContent = mode === 'edit' ? 'Edit building' : 'Add building'
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>