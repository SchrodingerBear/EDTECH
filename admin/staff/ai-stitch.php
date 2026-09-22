<?php
/**
 * Innovatech PH — staff: AI stitch (guided capture → 360 panorama).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.aistitch');
$pageTitle = 'AI Stitch';
$pageSub = 'Six faces in, one seamless 360 panorama out';
$active = 'AI Stitch';
$bodyClass = 'page-staff-aistitch';
require_once __DIR__ . '/../layout/header.php';

$inst = current_institution();
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
$cubeAbs = $orgAbs . '/assets/cubemaps';
$panoAbs = $orgAbs . '/assets/scenes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['stitch_action'] ?? '';
    try {
        if ($action === 'job-create') {
            $source = ($_POST['source_type'] ?? 'cubemap_upload') === 'in_app_capture' ? 'in_app_capture' : 'cubemap_upload';
            crud()->insert('ai_stitch_jobs', ['institution_id' => $iid, 'created_by' => $me, 'source_type' => $source, 'status' => 'draft', 'guide_step' => 'front']);
            audit('ai_stitch.job.create', 'ai', 'job', (int) crud()->lastInsertId());
            flash('success', 'Stitch job created.');
            redirect('admin/staff/ai-stitch');
        }
        if ($action === 'face-upload') {
            $jid = (int) ($_POST['job_id'] ?? 0);
            $face = $_POST['face'] ?? '';
            $order = ['front', 'back', 'left', 'right', 'up', 'down'];
            if (!in_array($face, $order, true)) throw new RuntimeException('Invalid face.');
            if (empty($_FILES['face_file']['name'])) throw new RuntimeException('Choose a face image.');
            if (!is_dir($cubeAbs . '/' . $jid)) mkdir($cubeAbs . '/' . $jid, 0775, true);
            $ext = strtolower(pathinfo($_FILES['face_file']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $name = $face . '.' . ($ext === '' ? 'jpg' : $ext);
            move_uploaded_file($_FILES['face_file']['tmp_name'], $cubeAbs . '/' . $jid . '/' . $name);
            $rel = 'assets/cubemaps/' . $jid . '/' . $name;
            crud()->raw("INSERT INTO cubemap_faces (job_id, face, image_path, captured_at) VALUES (:jid,:face,:img,NOW()) ON DUPLICATE KEY UPDATE image_path=:img2, captured_at=NOW()", ['jid' => $jid, 'face' => $face, 'img' => $rel, 'img2' => $rel]);
            try {
                crud()->insert('media_assets', ['institution_id' => $iid, 'uploaded_by' => $me, 'kind' => 'cubemap_face', 'file_path' => $rel, 'original_name' => $_FILES['face_file']['name']]);
            } catch (Throwable $e) {}
            crud()->update('ai_stitch_jobs', ['status' => 'uploading', 'guide_step' => $order[min(array_search($face, $order, true) + 1, 5)]], ['id' => $jid]);
            flash('success', ucfirst($face) . ' face saved.');
            redirect('admin/staff/ai-stitch');
        }
        if ($action === 'stitch') {
            $jid = (int) ($_POST['job_id'] ?? 0);
            $faces = crud()->select('cubemap_faces', 'face, image_path', ['job_id' => $jid]);
            if (count($faces) !== 6) throw new RuntimeException('All six faces are required.');
            $map = [];
            foreach ($faces as $f) {
                $abs = ROOT_PATH . '/' . ltrim($f['image_path'], '/');
                if (!is_file($abs)) throw new RuntimeException('A face file is missing on disk.');
                $map[$f['face']] = $abs;
            }
            if (!is_dir($panoAbs)) mkdir($panoAbs, 0775, true);
            $out = $panoAbs . '/stitch-' . $jid . '-' . random_token(4) . '.jpg';
            crud()->update('ai_stitch_jobs', ['status' => 'processing'], ['id' => $jid]);
            if (!cubemap_to_equirect($map, $out)) throw new RuntimeException('Stitch failed — faces must be square JPEG/PNG.');

            // Optional AI image repair for damaged objects in the stitched panorama
            // NOTE: This feature repairs/edits the EXISTING stitched image, not generate new images
            // Used to fix damaged WiFi icons, remove artifacts, or repair broken elements
            $repairPrompt = trim($_POST['repair_prompt'] ?? '');
            if ($repairPrompt !== '' && env('OPENROUTER_IMAGE_KEY', '') !== '') {
                try {
                    $repairedPath = repair_image_with_openrouter($out, $repairPrompt);
                    if ($repairedPath && is_file($repairedPath)) {
                        @unlink($out);
                        $out = $repairedPath;
                    }
                } catch (Throwable $e) {
                    // Log repair failure but continue with original stitched image
                    error_log('AI image repair failed: ' . $e->getMessage());
                }
            }

            $rel = 'assets/scenes/' . basename($out);
            crud()->raw("UPDATE ai_stitch_jobs SET status='completed', output_equirect_path=:rel, guide_step='done', completed_at=NOW(), provider='builtin-gd' WHERE id=:id", ['rel' => $rel, 'id' => $jid]);
            try {
                $st = @getimagesize($out);
                crud()->insert('media_assets', ['institution_id' => $iid, 'uploaded_by' => $me, 'kind' => 'pano', 'file_path' => $rel, 'original_name' => 'stitched-360.jpg', 'width' => $st[0] ?? null, 'height' => $st[1] ?? null]);
            } catch (Throwable $e) {}
            audit('ai_stitch.complete', 'ai', 'job', $jid);
            flash('success', 'Stitched! Equirect ready — an admin can attach it to a tour scene.');
            redirect('admin/staff/ai-stitch');
        }
        if ($action === 'delete') {
            $jid = (int) ($_POST['id'] ?? 0);
            $dir = $cubeAbs . '/' . $jid;
            crud()->delete('ai_stitch_jobs', ['id' => $jid, 'institution_id' => $iid]);
            if (is_dir($dir)) rrmdir($dir);
            flash('success', 'Job removed.');
            redirect('admin/staff/ai-stitch');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('admin/staff/ai-stitch');
    }
}

$jobs = crud()->raw("SELECT j.*, (SELECT COUNT(*) FROM cubemap_faces f WHERE f.job_id=j.id) fc FROM ai_stitch_jobs j WHERE j.institution_id=:iid ORDER BY j.created_at DESC LIMIT 20", ['iid' => $iid]);

$facesByJob = [];
$faceRows = crud()->raw("SELECT job_id, face, image_path FROM cubemap_faces ORDER BY id");
foreach ($faceRows as $fr) { $facesByJob[(int) $fr['job_id']][$fr['face']] = $fr['image_path']; }

$order = ['front', 'back', 'left', 'right', 'up', 'down'];
$b = ['draft' => 'badge-draft', 'uploading' => 'badge-draft', 'queued' => 'badge-draft', 'processing' => 'badge-live', 'completed' => 'badge-live', 'failed' => 'badge-dead'];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-1 ia-meta-lg"><?= $jobs->rowCount() ?> job(s) · capture or upload the 6 cube faces in order</p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#job-modal"><?= ia_icon('camera', 16) ?> New job</button>
</div>

<div class="row g-3">
  <?php foreach ($jobs as $job): ?>
    <div class="col-lg-6">
      <div class="ia-card h-100">
        <div class="card-head">
          <div>
            <h3>#<?= (int) $job['id'] ?> <?= h($job['source_type']) ?></h3>
            <?php if ($job['source_type'] === 'in_app_capture' && in_array($job['guide_step'], $order, true)): ?>
              <span class="badge badge-surface">next: <?= h($job['guide_step']) ?></span>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge <?= $b[$job['status']] ?? 'badge-draft' ?>"><?= h($job['status']) ?></span>
            <form method="post" data-delete-form data-confirm="Delete job #<?= (int) $job['id'] ?>?">
              <input type="hidden" name="stitch_action" value="delete"><input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
              <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
            </form>
          </div>
        </div>

        <?php if ($job['status'] === 'completed' && $job['output_equirect_path']): ?>
          <div class="p-3 text-center">
            <img src="<?= h(org_url($inst['slug'], $job['output_equirect_path'])) ?>" class="equirect-thumb rounded-3" alt="result">
            <p class="text-muted mt-2 mb-0 fs-125"><?= h($job['output_equirect_path']) ?> — inform your admin to set it as a tour scene.</p>
          </div>
        <?php else: ?>
          <div class="cube-grid-staff">
            <?php foreach ($order as $face):
                $have = isset($facesByJob[$job['id']][$face]); ?>
              <div class="scube <?= $have ? 'done' : '' ?>">
                <?php if ($have): ?>
                  <img src="<?= h(org_url($inst['slug'], $facesByJob[$job['id']][$face])) ?>" alt="face">
                  <span class="scube-label"><?= h($face) ?></span>
                <?php else: ?>
                  <label class="scube-empty">
                    <input type="file" accept="image/*" class="d-none" data-stitch-upload data-job="<?= (int) $job['id'] ?>" data-face="<?= h($face) ?>">
                    <span><?= ia_icon('camera', 20) ?></span>
                    <span class="scube-label"><?= h($face) ?></span>
                  </label>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="p-3 d-flex justify-content-end gap-2 align-items-center">
            <?php if ((int) $job['fc'] === 6): ?>
              <form method="post" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="stitch_action" value="stitch"><input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                <input type="text" name="repair_prompt" class="form-control form-control-sm" placeholder="AI repair prompt (e.g., 'Remove damaged WiFi icon from existing image')" style="max-width: 300px;">
                <button class="btn btn-grad px-4" <?= $job['status'] === 'completed' ? 'disabled' : '' ?>><?= ia_icon('wand', 15) ?> Stitch now</button>
              </form>
            <?php else: ?>
              <span class="text-muted fs-125"><?= (int) $job['fc'] ?>/6 faces</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($jobs->rowCount() === 0): ?>
    <div class="col-12"><div class="ia-card"><div class="empty-state"><div class="empty-icon"><?= ia_icon('camera', 26) ?></div><h4>No stitch jobs</h4><p>Capture six directions around a spot (front, back, left, right, up, down) and auto-stitch a panorama.</p></div></div></div>
  <?php endif; ?>
</div>

<div class="modal fade" id="job-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">New stitch job</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="stitch_action" value="job-create">
      <div class="modal-body d-grid gap-3">
        <label class="sj-opt">
          <input type="radio" name="source_type" value="cubemap_upload" checked>
          <span class="sj-box"><strong><?= ia_icon('upload', 15) ?> Cubemap upload</strong><small>Six square faces uploaded from your device.</small></span>
        </label>
        <label class="sj-opt">
          <input type="radio" name="source_type" value="in_app_capture">
          <span class="sj-box"><strong><?= ia_icon('camera', 15) ?> Guided capture</strong><small>Wizard order: front → back → left → right → up → down.</small></span>
        </label>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Create job</button></div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-stitch-upload]').forEach((inp) => {
    inp.addEventListener('change', () => {
      if (!inp.files[0]) return
      const fd = new FormData()
      fd.append('stitch_action', 'face-upload')
      fd.append('job_id', inp.dataset.job)
      fd.append('face', inp.dataset.face)
      fd.append('face_file', inp.files[0])
      fetch(location.href.split('?')[0], { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(() => location.reload())
    })
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>