<?php
/**
 * Innovatech PH — staff: floor plan upload + marker placement (click to position).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.floorplans');
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Floor Plans';
$pageSub = 'Upload campus maps and drop markers (percentages keep them responsive)';
$active = 'Floor Plans';

$inst = current_institution();
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];
$orgDir = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/floorplans';
$planId = (int) ($_GET['plan'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['sfp_action'] ?? '';
    try {
        if ($action === 'upload') {
            if (empty($_FILES['image']['name'])) throw new RuntimeException('Choose an image.');
            $title = trim($_POST['title'] ?? '') ?: 'Campus Map';
            if (!is_dir($orgDir)) mkdir($orgDir, 0775, true);
            $size = @getimagesize($_FILES['image']['tmp_name']);
            $w = (int) ($size[0] ?? 1200); $h = (int) ($size[1] ?? 900);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $name = random_token(6) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $orgDir . '/' . $name);
            $newPlanId = crud()->insert('floor_plans', [
                'institution_id' => $iid, 'title' => $title, 'image_path' => 'assets/floorplans/' . $name,
                'original_width' => $w, 'original_height' => $h, 'aspect_ratio' => $w / max(1, $h),
                'object_fit' => 'contain', 'created_by' => $me,
            ]);
            audit('floor_plans.create', 'content', 'floor_plan', $newPlanId);
            flash('success', 'Floor plan uploaded.');
            redirect('admin/staff/floor-plans');
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = crud()->raw('SELECT image_path FROM floor_plans WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $id, 'iid' => $iid])->fetch();
            $img = $row['image_path'] ?? null;
            crud()->delete('floor_plans', ['id' => $id, 'institution_id' => $iid]);
            if ($img && is_file(ROOT_PATH . '/' . ltrim($img, '/'))) unlink(ROOT_PATH . '/' . ltrim($img, '/'));
            flash('success', 'Floor plan removed.');
            redirect('admin/staff/floor-plans');
        }
        if ($action === 'marker-add') {
            $label = trim($_POST['label'] ?? '');
            if ($label === '') throw new RuntimeException('Marker label required.');
            crud()->insert('floor_plan_markers', [
                'institution_id' => $iid, 'floor_plan_id' => (int) ($_POST['plan_id'] ?? 0), 'label' => $label,
                'x_percent' => (float) ($_POST['x'] ?? 50), 'y_percent' => (float) ($_POST['y'] ?? 50),
                'size_percent' => 4, 'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                'popup_html' => trim($_POST['popup_html'] ?? '') ?: null, 'sort_order' => 0,
            ]);
            flash('success', 'Marker added.');
            redirect('admin/staff/floor-plans?plan=' . (int) ($_POST['plan_id'] ?? 0));
        }
        if ($action === 'marker-save') {
            $id = (int) ($_POST['id'] ?? 0);
            crud()->update('floor_plan_markers',
                [
                    'x_percent' => max(0, min(100, (float) ($_POST['x'] ?? 0))),
                    'y_percent' => max(0, min(100, (float) ($_POST['y'] ?? 0))),
                    'label' => trim($_POST['label'] ?? '') ?: 'Marker',
                    'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                    'popup_html' => trim($_POST['popup_html'] ?? '') ?: null,
                ],
                ['id' => $id, 'institution_id' => $iid]
            );
            flash('success', 'Marker saved.');
            redirect('admin/staff/floor-plans?plan=' . (int) ($_POST['plan_id'] ?? 0));
        }
        if ($action === 'marker-delete') {
            crud()->delete('floor_plan_markers', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            flash('success', 'Marker removed.');
            redirect('admin/staff/floor-plans?plan=' . (int) ($_POST['plan_id'] ?? 0));
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('admin/staff/floor-plans' . ($planId ? '?plan=' . $planId : ''));
    }
}

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

<?php if ($plan): ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <a class="back-link" href="floor-plans">← All floor plans</a>
      <div class="d-flex align-items-center gap-2 mt-1"><h3 class="mb-0" style="font-weight:800"><?= h($plan['title']) ?></h3><span class="badge badge-draft"><?= count($markers) ?> markers</span></div>
    </div>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#mk-modal" data-x="50" data-y="50"><?= ia_icon('map', 16) ?> Add marker</button>
  </div>

  <div class="ia-card p-3">
    <div id="stage" style="aspect-ratio:<?= (float) $plan['aspect_ratio'] ?>;position:relative;max-width:100%;max-height:70vh;margin:0 auto">
      <img src="<?= h(org_url($inst['slug'], $plan['image_path'])) ?>" style="width:100%;height:100%;object-fit:contain;border-radius:12px;display:block;pointer-events:none" alt="floor plan">
      <?php foreach ($markers as $m): ?>
        <button type="button" class="mk-dot" data-mid="<?= (int) $m['id'] ?>" data-name="<?= h($m['label'], ENT_QUOTES) ?>"
          style="left:<?= (float) $m['x_percent'] ?>%;top:<?= (float) $m['y_percent'] ?>%"></button>
      <?php endforeach; ?>
      <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);font-size:12px;color:#fff;background:rgba(20,22,40,.72);padding:6px 12px;border-radius:999px">Click a dot to reposition: first click a dot, then click the new spot on the map.</div>
    </div>
  </div>

  <div class="ia-card mt-3"><div class="card-body">
    <h5 class="fw-bold mb-3">Markers</h5>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($markers as $m): ?>
        <span class="px-3 py-2 rounded-3 d-flex align-items-center gap-2" style="background:var(--ia-surface-2)">
          <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold" data-edit-marker="<?= (int) $m['id'] ?>"
            data-name="<?= h($m['label'], ENT_QUOTES) ?>" data-x="<?= (float) $m['x_percent'] ?>" data-y="<?= (float) $m['y_percent'] ?>"
            data-pt="<?= h($m['popup_title'], ENT_QUOTES) ?>" data-ph="<?= h($m['popup_html'], ENT_QUOTES) ?>" data-bs-toggle="modal" data-bs-target="#mk-modal"><?= h($m['label']) ?></button>
          <form method="post" data-delete-form data-confirm="Delete marker '<?= h($m['label']) ?>'?">
            <input type="hidden" name="sfp_action" value="marker-delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-sm btn-outline-ia text-danger px-1" title="Delete"><?= ia_icon('x', 12) ?></button>
          </form>
        </span>
      <?php endforeach; ?>
      <?php if (!$markers): ?><p class="text-muted mb-0" style="font-size:13px">No markers — click Add marker to start placing pins.</p><?php endif; ?>
    </div>
  </div></div>
<?php else: ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <p class="mb-1" style="color:var(--ia-muted);font-size:13.5px"><?= count($plans) ?> floor plan(s)</p>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#up-modal"><?= ia_icon('upload', 16) ?> Upload floor plan</button>
  </div>
  <div class="ia-card">
    <div class="table-responsive"><table class="table table-ia">
      <thead><tr><th>Plan</th><th>Preview</th><th>Markers</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach (($plans ?: []) as $p): ?>
          <tr>
            <td style="font-weight:700"><?= h($p['title']) ?></td>
            <td><img src="<?= h(org_url($inst['slug'], $p['image_path'])) ?>" style="width:110px;height:52px;object-fit:contain;border-radius:8px;border:1px solid var(--ia-border)" alt=""></td>
            <td style="color:var(--ia-muted)"><?= (int) $p['mc'] ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-grad" href="floor-plans?plan=<?= (int) $p['id'] ?>"><?= ia_icon('map', 13) ?> Markers</a>
                <form method="post" data-delete-form data-confirm="Delete floor plan '<?= h($p['title']) ?>'?">
                  <input type="hidden" name="sfp_action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($plans) === 0): ?>
          <tr><td colspan="4"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No floor plans</h4><p>Upload a campus map image first — then open it to place markers.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>

<!-- upload modal -->
<div class="modal fade" id="up-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Upload floor plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="sfp_action" value="upload">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Ground Floor Map"></div>
        <div><label class="form-label">Image</label><input class="form-control" type="file" name="image" accept="image/*" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Upload</button></div>
    </form>
  </div></div>
</div>

<!-- marker modal -->
<div class="modal fade" id="mk-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Marker</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div id="mk-both">
      <form method="post" id="mk-create">
        <input type="hidden" name="sfp_action" value="marker-add">
        <input type="hidden" name="plan_id" value="<?= (int) ($plan['id'] ?? 0) ?>">
        <input type="hidden" name="x" id="mk-x-new"><input type="hidden" name="y" id="mk-y-new">
        <div class="modal-body d-grid gap-3">
          <div><label class="form-label">Label</label><input class="form-control" name="label" required placeholder="Library"></div>
          <div class="row g-3">
            <div class="col-6"><label class="form-label">Popup title</label><input class="form-control" name="popup_title"></div>
            <div class="col-6"><label class="form-label">Popup content</label><input class="form-control" name="popup_html"></div>
          </div>
          <p style="font-size:12.5px;color:var(--ia-muted);margin:0">A new pin appears at the center — click it on the map to move it, then edit here.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Add marker</button></div>
      </form>
    </div>
  </div></div>
</div>

<style>
  .mk-dot { position:absolute; width:34px; height:34px; border:0; border-radius:50%; cursor:pointer; transform:translate(-50%,-50%);
    background:radial-gradient(circle at 32% 28%, var(--ia-accent), var(--ia-primary));
    box-shadow:0 4px 14px rgba(0,0,0,.4), 0 0 0 3px rgba(255,255,255,.85); transition:transform .15s; }
  .mk-dot:hover { transform:translate(-50%,-50%) scale(1.18); }
  .mk-dot.moving { outline:3px dashed var(--ia-warning); outline-offset:3px; }
  @media (min-width: 768px) {
    .mk-dot::after { content:attr(data-name); position:absolute; left:50%; bottom:100%; transform:translateX(-50%);
      background:var(--ia-surface); border:1px solid var(--ia-border); color:var(--ia-text); padding:3px 8px; margin-bottom:6px;
      border-radius:8px; font-size:11px; white-space:nowrap; font-weight:600; opacity:0; pointer-events:none; }
    .mk-dot:hover::after { opacity:1; }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const stage = document.getElementById('stage')
  if (!stage) return
  let moving = null
  document.querySelectorAll('.mk-dot').forEach((d) => {
    d.addEventListener('click', () => {
      document.querySelectorAll('.mk-dot').forEach(x => x.classList.remove('moving'))
      if (moving === d) { moving = null; return }
      moving = d; d.classList.add('moving')
    })
  })
  stage.addEventListener('click', (e) => {
    if (!moving) return
    if (e.target === stage.querySelector('img') || e.target.id === 'stage') {
      const r = stage.getBoundingClientRect()
      const fix = (el) => {
        const r2 = el.getBoundingClientRect()
        return ((e.clientX - r2.left) / r2.width * 100).toFixed(3)
      }
      const x = ((e.clientX - r.left) / r.width * 100).toFixed(3)
      const y = ((e.clientY - r.top) / r.height * 100).toFixed(3)
      const id = moving.dataset.mid
      fetch(location.href.split('?')[0], {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ sfp_action: 'marker-save', id, x, y, label: moving.dataset.name, plan_id: <?= (int) ($plan['id'] ?? 0) ?> }).toString(),
        credentials: 'same-origin'
      }).then(() => location.reload())
      }
    }
  })
  document.getElementById('mk-modal')?.addEventListener('show.bs.modal', (e) => {
    const b = e.relatedTarget
    document.getElementById('mk-x-new').value = b.dataset.x || 50
    document.getElementById('mk-y-new').value = b.dataset.y || 50
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>