<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ai');
/**
 * Innovatech PH — admin: AI stitch (cubemap → equirect) + AI info generation.
 */
$pageTitle = 'AI Tools';
$pageSub = 'Stitch a 360 panorama from six cube faces';
$active = 'AI Tools';
$bodyClass = 'page-ai';
require_once __DIR__ . '/../layout/header.php';

// Check for flash messages from URL parameters
$flashType = $_GET['flash_type'] ?? null;
$flashMessage = $_GET['flash_message'] ?? null;

$inst = resolve_active_institution();
if (!$inst) {
  http_response_code(404);
  require ROOT_PATH . '/admin/errors/404.php';
  exit;
}
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
$cubeAbs = $orgAbs . '/assets/cubemaps';
$panoAbs = $orgAbs . '/assets/panos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['ai_action'] ?? '';
  try {
    /* ------------------------- stitch job ------------------------- */
    if ($action === 'job-create') {
      $source = ($_POST['source_type'] ?? 'cubemap_upload') === 'in_app_capture' ? 'in_app_capture' : 'cubemap_upload';
      $jid = crud()->insert('ai_stitch_jobs', [
        'institution_id' => $iid,
        'created_by' => $me,
        'source_type' => $source,
        'status' => 'draft',
        'guide_step' => 'front'
      ]);
      audit('ai_stitch.job.create', 'ai', 'job', $jid);
      flash('success', 'Stitch job created. Upload six cube faces.');
      redirect('admin/institution/ai');
    }

    if ($action === 'face-upload') {
      $jid = (int) ($_POST['job_id'] ?? 0);
      $face = $_POST['face'] ?? '';
      $order = ['front', 'back', 'left', 'right', 'up', 'down'];
      if (!in_array($face, $order, true))
        throw new RuntimeException('Invalid face.');
      if (empty($_FILES['face_file']['name']))
        throw new RuntimeException('Choose a face image.');

      $job = crud()->raw('SELECT * FROM ai_stitch_jobs WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $jid, 'iid' => $iid])->fetch();
      if (!$job)
        throw new RuntimeException('Job not found.');

      if (!is_dir($cubeAbs . '/' . $jid))
        mkdir($cubeAbs . '/' . $jid, 0775, true);
      $ext = strtolower(pathinfo($_FILES['face_file']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
      $name = $face . '.' . ($ext === '' ? 'jpg' : $ext);
      move_uploaded_file($_FILES['face_file']['tmp_name'], $cubeAbs . '/' . $jid . '/' . $name);
      $rel = 'assets/cubemaps/' . $jid . '/' . $name;

      crud()->raw("INSERT INTO cubemap_faces (job_id, face, image_path, captured_at) VALUES (:jid,:face,:img, NOW()) ON DUPLICATE KEY UPDATE image_path=:img2, captured_at=NOW()", ['jid' => $jid, 'face' => $face, 'img' => $rel, 'img2' => $rel])->execute();

      // media audit row (kind cubemap_face)
      try {
        crud()->insert('media_assets', [
          'institution_id' => $iid,
          'uploaded_by' => $me,
          'kind' => 'cubemap_face',
          'file_path' => $rel,
          'original_name' => $_FILES['face_file']['name']
        ]);
      } catch (Throwable $e) { /* non-fatal */
      }

      // advance guide step for in-app capture
      if (($job['source_type'] ?? '') === 'in_app_capture') {
        $next = $order[min(array_search($face, $order, true) + 1, 5)];
        crud()->raw("UPDATE ai_stitch_jobs SET guide_step=:gs, status=IF(:gs='done','ready','uploading') WHERE id=:id", ['gs' => $next, 'id' => $jid])->execute();
      } else {
        crud()->update('ai_stitch_jobs', ['status' => 'uploading'], ['id' => $jid]);
      }
      flash('success', ucfirst($face) . ' face saved.');
      redirect('admin/institution/ai');
    }

    if ($action === 'stitch') {
      $jid = (int) ($_POST['job_id'] ?? 0);
      $faces = crud()->select('cubemap_faces', 'face, image_path', ['job_id' => $jid]);
      if (count($faces) !== 6)
        throw new RuntimeException('All six faces are required before stitching.');

      $map = [];
      foreach ($faces as $f) {
        $abs = ROOT_PATH . '/' . ltrim($f['image_path'], '/');
        if (!is_file($abs))
          throw new RuntimeException('A face file is missing on disk.');
        $map[$f['face']] = $abs;
      }
      if (!is_dir($panoAbs))
        mkdir($panoAbs, 0775, true);
      $out = $panoAbs . '/stitch-' . $jid . '-' . random_token(4) . '.jpg';

      crud()->update('ai_stitch_jobs', ['status' => 'processing'], ['id' => $jid]);
      $ok = cubemap_to_equirect($map, $out);
      if (!$ok)
        throw new RuntimeException('Stitch failed — check that faces are square JPEG/PNG images.');

      $rel = 'assets/panos/' . basename($out);
      crud()->raw("UPDATE ai_stitch_jobs SET status='completed', output_equirect_path=:rel, guide_step='done', completed_at=NOW(), provider='builtin-gd', error_message=NULL WHERE id=:id", ['rel' => $rel, 'id' => $jid])->execute();
      try {
        $st = @getimagesize($out);
        crud()->insert('media_assets', [
          'institution_id' => $iid,
          'uploaded_by' => $me,
          'kind' => 'pano',
          'file_path' => $rel,
          'original_name' => 'stitched-360.jpg',
          'width' => $st[0] ?? null,
          'height' => $st[1] ?? null
        ]);
      } catch (Throwable $e) { /* non-fatal */
      }
      audit('ai_stitch.complete', 'ai', 'job', $jid);
      flash('success', 'Stitched! 2048×1024 equirect saved — attach it to a tour scene below.');
      redirect('admin/institution/ai');
    }

    if ($action === 'attach-scene') {
      $jid = (int) ($_POST['job_id'] ?? 0);
      $sid = (int) ($_POST['scene_id'] ?? 0) ?: null;
      crud()->update('ai_stitch_jobs', ['output_scene_id' => $sid], ['id' => $jid, 'institution_id' => $iid]);
      if ($sid) {
        $eq = crud()->raw("SELECT output_equirect_path FROM ai_stitch_jobs WHERE id=:id", ['id' => $jid])->fetchColumn();
        if ($eq) {
          crud()->update('tour_scenes', ['equirect_path' => $eq], ['id' => $sid, 'institution_id' => $iid]);
        }
      }
      audit('ai_stitch.attach', 'ai', 'job', $jid);
      flash('success', 'Equirect attached to the selected scene.');
      redirect('admin/institution/ai');
    }

    if ($action === 'job-delete') {
      $jid = (int) ($_POST['id'] ?? 0);
      crud()->delete('ai_stitch_jobs', ['id' => $jid, 'institution_id' => $iid]);
      $dir = $cubeAbs . '/' . $jid;
      if (is_dir($dir))
        rrmdir($dir);
      flash('success', 'Job removed.');
      redirect('admin/institution/ai');
    }

    if ($action === 'attach-panorama') {
      $pid = (int) ($_POST['panorama_id'] ?? 0);
      $sid = (int) ($_POST['scene_id'] ?? 0) ?: null;

      if ($sid) {
        $pano = crud()->select('panoramas', 'equirect_path', ['id' => $pid, 'institution_id' => $iid])->fetch();
        if ($pano) {
          // Use full organization path format for tour_scenes
          $fullPath = trim($inst['folder_path'], '/') . '/' . $pano['equirect_path'];
          crud()->update('tour_scenes', ['equirect_path' => $fullPath], ['id' => $sid, 'institution_id' => $iid]);
          audit('panorama.attach', 'ai', 'panorama', $pid);
          flash('success', 'Panorama attached to scene.');
        } else {
          flash('error', 'Panorama not found.');
        }
      }
      redirect('admin/institution/ai');
    }

    if ($action === 'panorama-delete') {
      $pid = (int) ($_POST['id'] ?? 0);
      crud()->delete('panoramas', ['id' => $pid, 'institution_id' => $iid]);
      flash('success', 'Panorama removed.');
      redirect('admin/institution/ai');
    }
  } catch (Throwable $e) {
    flash('error', $e->getMessage());
  }
  redirect('admin/institution/ai');
}

// data ---------------------------------------------------------------
$jobsResult = crud()->raw("SELECT j.*, (SELECT COUNT(*) FROM cubemap_faces f WHERE f.job_id=j.id) AS face_count, s.title AS scene_title FROM ai_stitch_jobs j LEFT JOIN tour_scenes s ON s.id=j.output_scene_id WHERE j.institution_id=:iid ORDER BY j.created_at DESC LIMIT 30", ['iid' => $iid]);
$jobs = is_array($jobsResult) ? $jobsResult : $jobsResult->fetchAll();

$order = ['front', 'back', 'left', 'right', 'up', 'down'];
$facesByJob = [];
$faceRowsResult = crud()->raw("SELECT job_id, face, image_path FROM cubemap_faces ORDER BY id");
$faceRows = is_array($faceRowsResult) ? $faceRowsResult : $faceRowsResult->fetchAll();
foreach ($faceRows as $fr) {
  $facesByJob[(int) $fr['job_id']][$fr['face']] = $fr['image_path'];
}

$scenesForAttachResult = crud()->select('tour_scenes', 'id,title', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY title');
$scenesForAttach = is_array($scenesForAttachResult) ? $scenesForAttachResult : $scenesForAttachResult->fetchAll();

// Get saved panoramas from 360 camera app
$panoramasResult = crud()->select('panoramas', '*', ['institution_id' => $iid], 'ORDER BY created_at DESC LIMIT 30');
$panoramas = is_array($panoramasResult) ? $panoramasResult : $panoramasResult->fetchAll();

$statusBadge = ['draft' => 'badge-draft', 'uploading' => 'badge-draft', 'queued' => 'badge-draft', 'processing' => 'badge-live', 'completed' => 'badge-live', 'failed' => 'badge-dead'];
?>
<div class="row g-4">
  <!-- Flash Messages -->
  <?php if ($flashType && $flashMessage): ?>
  <div class="col-12">
    <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
      <?= h($flashMessage) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- ============================ STITCH ============================ -->
  <div class="col-12">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-800">360 Camera Panoramas</h4>
      <a href="<?= url('360_cam') ?>" target="_blank" class="btn btn-grad px-4">
        <i class="fas fa-camera me-2"></i> Create 360
      </a>
    </div>

    <?php foreach ($jobs as $job): ?>
      <?php $caps = array_keys($facesByJob[$job['id']] ?? []);
      $status = (int) $job['face_count']; ?>
      <div class="ia-card mb-3">
        <div class="card-head">
          <div class="d-flex align-items-center gap-2">
            <h3>#<?= (int) $job['id'] ?> <span class="text-muted fs-13"><?= h($job['source_type']) ?></span></h3>
            <span class="badge <?= $statusBadge[$job['status']] ?? 'badge-draft' ?>"><?= h($job['status']) ?></span>
            <?php if ($job['source_type'] === 'in_app_capture' && in_array($job['guide_step'], $order, true)): ?>
              <span class="badge badge-surface">
                <a href="<?= url('360_cam') ?>" target="_blank" class="text-white text-decoration-none">Open 360 Camera
                  App</a>
              </span>
            <?php endif; ?>
          </div>
          <form method="post" data-delete-form data-confirm="Delete stitch job #<?= (int) $job['id'] ?>?">
            <input type="hidden" name="ai_action" value="job-delete"><input type="hidden" name="id"
              value="<?= (int) $job['id'] ?>">
            <button class="btn btn-sm btn-outline-ia text-danger"><i class="fas fa-trash"></i></button>
          </form>
        </div>

        <?php if ($job['status'] === 'completed' && $job['output_equirect_path']): ?>
          <div class="row g-3 align-items-center card-body-night">
            <div class="col-md-3">
              <img src="<?= h(org_url($inst['slug'], $job['output_equirect_path'])) ?>" class="equirect-thumb rounded-3"
                alt="equirect">
            </div>
            <div class="col-md-5">
              <div class="fw-bold mb-1"><span class="badge badge-live">ready</span> 2:1 equirectangular panorama</div>
              <div class="text-muted fs-125"><?= h($job['output_equirect_path']) ?></div>
              <?php if ($job['scene_title']): ?>
                <div class="mt-1 fs-125">Scene: <?= h($job['scene_title']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="ai_action" value="attach-scene"><input type="hidden" name="job_id"
                  value="<?= (int) $job['id'] ?>">
                <select class="form-select form-select-sm" name="scene_id">
                  <option value="">attach to scene…</option>
                  <?php foreach ($scenesForAttach as $sc): ?>
                    <option value="<?= (int) $sc['id'] ?>" <?= (int) ($job['output_scene_id'] ?? 0) === (int) $sc['id'] ? 'selected' : '' ?>><?= h($sc['title']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-grad" type="submit"><i class="fas fa-save"></i></button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <div class="cube-grid">
            <?php foreach ($order as $face): ?>
              <?php $have = in_array($face, $caps, true); ?>
              <div class="cube-cell <?= $have ? 'filled' : '' ?>">
                <?php if ($have):
                  $img = $facesByJob[$job['id']][$face];
                  $n = pathinfo($img, PATHINFO_FILENAME); ?>
                  <img src="<?= h(org_url($inst['slug'], $img)) ?>" alt="<?= h($face) ?>">
                  <span class="cube-label"><?= h($face) ?></span>
                  <form method="post" class="cube-re" data-delete-form data-confirm="Replace <?= h($face) ?> face?">
                    <input type="hidden" name="ai_action" value="face-replace-flag">
                    <button class="btn btn-sm btn-light" type="button" data-replace-face="<?= h($face) ?>"
                      data-job="<?= (int) $job['id'] ?>"><i class="fas fa-sync-alt"></i></button>
                  </form>
                <?php else: ?>
                  <label class="cube-empty">
                    <input type="file" accept="image/*" class="d-none" data-face-upload data-job="<?= (int) $job['id'] ?>"
                      data-face="<?= h($face) ?>">
                    <span class="cube-plus"><i class="fas fa-camera fa-lg"></i></span>
                    <span class="cube-label"><?= h($face) ?></span>
                  </label>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted fs-125"><?= $status ?>/6 faces ·
              <?= $job['source_type'] === 'in_app_capture' ? 'guided capture, in order' : 'free upload' ?></span>
            <?php if ($status === 6): ?>
              <form method="post">
                <input type="hidden" name="ai_action" value="stitch"><input type="hidden" name="job_id"
                  value="<?= (int) $job['id'] ?>">
                <button class="btn btn-grad px-4" <?= $job['status'] === 'completed' ? 'disabled' : '' ?>><i class="fas fa-magic me-2"></i> Stitch now</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>


  </div>

  <!-- ============================ 360 CAMERA PANORAMAS ============================ -->
  <div class="col-12 mt-4">

    <?php foreach ($panoramas as $pano): ?>
      <div class="ia-card mb-3">
        <div class="card-head">
          <div class="d-flex align-items-center gap-2">
            <h3><?= h($pano['title'] ?? 'Untitled') ?></h3>
            <span class="badge badge-live"><?= h($pano['status']) ?></span>
          </div>
          <div class="text-muted fs-13"><?= date('M j, Y g:i A', strtotime($pano['created_at'])) ?></div>
        </div>

        <div class="row g-3 align-items-center card-body-night">
          <div class="col-md-3">
            <?php if ($pano['thumbnail_path']): ?>
              <?php $tUrl = str_starts_with($pano['thumbnail_path'], 'http') ? $pano['thumbnail_path'] : url($inst['folder_path'] . '/' . $pano['thumbnail_path']); ?>
              <img src="<?= h($tUrl) ?>" class="equirect-thumb rounded-3"
                alt="thumbnail">
            <?php elseif ($pano['equirect_path']): ?>
              <?php $eUrl = str_starts_with($pano['equirect_path'], 'http') ? $pano['equirect_path'] : url($inst['folder_path'] . '/' . $pano['equirect_path']); ?>
              <img src="<?= h($eUrl) ?>" class="equirect-thumb rounded-3"
                alt="panorama">
            <?php else: ?>
              <div class="equirect-thumb rounded-3 bg-light d-flex align-items-center justify-content-center">
                <span class="text-muted">No preview</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-5">
            <div class="fw-bold mb-1"><span class="badge badge-live">ready</span> 360° Equirectangular Panorama</div>
            <div class="text-muted fs-125"><?= h($pano['equirect_path']) ?></div>
            <?php if ($pano['description']): ?>
              <div class="mt-1 fs-125 text-muted"><?= h($pano['description']) ?></div><?php endif; ?>
            <?php if ($pano['width'] && $pano['height']): ?>
              <div class="mt-1 fs-125 text-muted">Resolution: <?= (int) $pano['width'] ?>×<?= (int) $pano['height'] ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="ai_action" value="attach-panorama"><input type="hidden" name="panorama_id"
                value="<?= (int) $pano['id'] ?>">
              <select class="form-select form-select-sm" name="scene_id">
                <option value="">attach to scene…</option>
                <?php foreach ($scenesForAttach as $sc): ?>
                  <option value="<?= (int) $sc['id'] ?>"><?= h($sc['title']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-grad" type="submit"><i class="fas fa-save"></i></button>
            </form>
            <div class="mt-2 d-flex gap-2">
              <a href="<?= h(url($inst['folder_path'] . '/' . $pano['equirect_path'])) ?>" download
                class="btn btn-sm btn-outline-ia">Download</a>
              <form method="post" data-delete-form data-confirm="Delete panorama?">
                <input type="hidden" name="ai_action" value="panorama-delete"><input type="hidden" name="id"
                  value="<?= (int) $pano['id'] ?>">
                <button class="btn btn-sm btn-outline-ia text-danger"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (count($panoramas) === 0): ?>
      <div class="ia-card">
        <div class="empty-state">
          <div class="empty-icon"><i class="fas fa-camera fa-2x"></i></div>
          <h4>No 360 panoramas</h4>
          <p>Use the 360 Camera App to capture and save panoramas. They will appear here automatically.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- new job modal -->
<div class="modal fade" id="job-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New stitch job</h5><button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="ai_action" value="job-create">
        <div class="modal-body d-grid gap-3">
          <label class="mode-card-dashed">
            <input type="radio" name="source_type" value="cubemap_upload" checked>
            <span class="mode-box-2"><strong><i class="fas fa-upload me-2"></i> Cubemap upload</strong><small>Six square
                faces, upload all then stitch.</small></span>
          </label>
          <label class="mode-card-dashed">
            <input type="radio" name="source_type" value="in_app_capture">
            <span class="mode-box-2"><strong><i class="fas fa-camera me-2"></i> 360 Camera App</strong><small>Automatic
                36-point capture with guided alignment. Opens in new tab.</small></span>
          </label>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Create job</button></div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // face uploads
    document.querySelectorAll('[data-face-upload]').forEach((inp) => {
      inp.addEventListener('change', () => {
        if (!inp.files[0]) return
        const fd = new FormData()
        fd.append('ai_action', 'face-upload')
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