<?php
/**
 * Innovatech PH — staff: media uploads (featured/gallery images).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.uploads');
$pageTitle = 'Media Uploads';
$pageSub = 'Featured and gallery images for buildings, rooms and tours';
$active = 'Media Uploads';
$bodyClass = 'page-staff-uploads';
require_once __DIR__ . '/../layout/header.php';

$inst = current_institution();
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];
$uploadsAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['media_action'] ?? '';
    try {
        if ($action === 'upload') {
            $kind = $_POST['kind'] ?? 'gallery';
            $count = 0;
            if (!empty($_FILES['files']['name'][0])) {
                if (!is_dir($uploadsAbs)) mkdir($uploadsAbs, 0775, true);
                $total = count($_FILES['files']['name']);
                for ($i = 0; $i < $total; $i++) {
                    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $orig = basename($_FILES['files']['name'][$i]);
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    $name = random_token(6) . '.' . ($ext === '' ? 'jpg' : $ext);
                    move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadsAbs . '/' . $name);
                    $rel = 'assets/uploads/' . $name;
                    $st = @getimagesize($uploadsAbs . '/' . $name);
                    crud()->insert('media_assets', [
                        'institution_id' => $iid, 'uploaded_by' => $me, 'kind' => $kind,
                        'file_path' => $rel, 'original_name' => $orig,
                        'mime_type' => $_FILES['files']['type'][$i] ?? null,
                        'width' => $st[0] ?? null, 'height' => $st[1] ?? null,
                    ]);
                    $count++;
                }
            }
            if ($count === 0) throw new RuntimeException('No files uploaded.');
            audit('media.upload', 'media', 'media', null);
            flash('success', "$count file(s) uploaded.");
        }
        if ($action === 'delete') {
            $mid = (int) ($_POST['id'] ?? 0);
            $asset = crud()->raw('SELECT file_path FROM media_assets WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $mid, 'iid' => $iid])->fetch();
            $img = $asset['file_path'] ?? null;
            crud()->delete('media_assets', ['id' => $mid, 'institution_id' => $iid]);
            if ($img && str_starts_with($img, 'assets/uploads/')) {
                $abs = ROOT_PATH . '/' . ltrim($img, '/');
                if (is_file($abs)) unlink($abs);
            }
            flash('success', 'Media removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/staff/uploads');
}

$media = crud()->select('media_assets', '*', ['institution_id' => $iid], 'ORDER BY created_at DESC LIMIT 200');

$kinds = ['gallery' => 'Gallery', 'featured' => 'Featured', 'pano' => 'Panorama', 'floor_plan' => 'Floor plan', 'ar_target' => 'AR target', 'logo' => 'Logo', 'other' => 'Other'];
$badge = ['gallery' => 'badge-draft', 'featured' => 'badge-live', 'pano' => 'badge-live', 'floor_plan' => 'badge-live', 'ar_target' => 'badge-live', 'logo' => 'badge-live', 'other' => 'badge-off'];
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <p class="mb-1 ia-meta-lg"><?= count($media) ?> asset(s) in <code>assets/uploads/</code></p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#up-modal"><?= ia_icon('upload', 16) ?> Upload media</button>
</div>

<div class="ia-card">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-3">
      <?php foreach ($media as $m): ?>
        <?php $isImg = preg_match('/\.(jpe?g|png|gif|webp|avif)$/i', $m['file_path'] ?? ''); ?>
        <div class="media-tile">
          <?php if ($isImg): ?>
            <img src="<?= h(org_url($inst['slug'], $m['file_path'])) ?>" alt="media">
          <?php else: ?>
            <div class="media-file"><?= ia_icon('file', 24) ?><span><?= h(strtoupper(pathinfo($m['file_path'] ?? '', PATHINFO_EXTENSION))) ?></span></div>
          <?php endif; ?>
          <div class="media-meta">
            <span class="badge <?= $badge[$m['kind']] ?? 'badge-off' ?>"><?= h($kinds[$m['kind']] ?? $m['kind']) ?></span>
            <form method="post" data-delete-form data-confirm="Delete this media?">
              <input type="hidden" name="media_action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button class="btn btn-sm btn-outline-ia text-danger px-2"><?= ia_icon('x', 12) ?></button>
            </form>
          </div>
          <div class="media-path"><?= h($m['file_path']) ?></div>
        </div>
      <?php endforeach; ?>

      <?php if (count($media) === 0): ?>
        <div class="w-100"><div class="empty-state"><div class="empty-icon"><?= ia_icon('image', 26) ?></div><h4>No media yet</h4><p>Batch upload photos for buildings, rooms, facilities and tours.</p></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="up-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Upload media</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="media_action" value="upload">
      <div class="modal-body d-grid gap-3">
        <div>
          <label class="form-label">Category</label>
          <select class="form-select" name="kind">
            <?php foreach ($kinds as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <input class="form-control" type="file" name="files[]" multiple accept="image/*">
          <div class="form-text">Select several at once. Stored under <code>assets/uploads/</code> in your organization folder.</div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit"><?= ia_icon('upload', 15) ?> Upload</button></div>
    </form>
  </div></div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>