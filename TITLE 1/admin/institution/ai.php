<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ai');
/**
 * Innovatech PH — admin: AI stitch (cubemap → equirect) + AI info generation.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'AI Tools';
$pageSub = 'Stitch a 360 panorama from six faces · generate facility info';
$active = 'AI Tools';

$pdo = db();
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
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
            $pdo->prepare(
                "INSERT INTO ai_stitch_jobs (institution_id, created_by, source_type, status, guide_step)
                 VALUES (:iid,:me,:src,'draft','front')"
            )->execute(['iid' => $iid, 'me' => $me, 'src' => $source]);
            $jid = (int) $pdo->lastInsertId();
            audit('ai_stitch.job.create', 'ai', 'job', $jid);
            flash('success', 'Stitch job created. Upload six cube faces.');
            redirect('admin/institution/ai');
        }

        if ($action === 'face-upload') {
            $jid = (int) ($_POST['job_id'] ?? 0);
            $face = $_POST['face'] ?? '';
            $order = ['front', 'back', 'left', 'right', 'up', 'down'];
            if (!in_array($face, $order, true)) throw new RuntimeException('Invalid face.');
            if (empty($_FILES['face_file']['name'])) throw new RuntimeException('Choose a face image.');

            $job = $pdo->prepare("SELECT * FROM ai_stitch_jobs WHERE id=:id AND institution_id=:iid");
            $job->execute(['id' => $jid, 'iid' => $iid]);
            $job = $job->fetch();
            if (!$job) throw new RuntimeException('Job not found.');

            if (!is_dir($cubeAbs . '/' . $jid)) mkdir($cubeAbs . '/' . $jid, 0775, true);
            $ext = strtolower(pathinfo($_FILES['face_file']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $name = $face . '.' . ($ext === '' ? 'jpg' : $ext);
            move_uploaded_file($_FILES['face_file']['tmp_name'], $cubeAbs . '/' . $jid . '/' . $name);
            $rel = 'assets/cubemaps/' . $jid . '/' . $name;

            $pdo->prepare(
                "INSERT INTO cubemap_faces (job_id, face, image_path, captured_at) VALUES (:jid,:face,:img, NOW())
                 ON DUPLICATE KEY UPDATE image_path=:img2, captured_at=NOW()"
            )->execute(['jid' => $jid, 'face' => $face, 'img' => $rel, 'img2' => $rel]);

            // media audit row (kind cubemap_face)
            try {
                $pdo->prepare(
                    "INSERT INTO media_assets (institution_id, uploaded_by, kind, file_path, original_name) VALUES (:iid,:me,'cubemap_face',:rel,:orig)"
                )->execute(['iid' => $iid, 'me' => $me, 'rel' => $rel, 'orig' => $_FILES['face_file']['name']]);
            } catch (Throwable $e) { /* non-fatal */ }

            // advance guide step for in-app capture
            if (($job['source_type'] ?? '') === 'in_app_capture') {
                $next = $order[min(array_search($face, $order, true) + 1, 5)];
                $pdo->prepare("UPDATE ai_stitch_jobs SET guide_step=:gs, status=IF(:gs='done','ready','uploading') WHERE id=:id")
                    ->execute(['gs' => $next, 'id' => $jid]);
            } else {
                $pdo->prepare("UPDATE ai_stitch_jobs SET status='uploading' WHERE id=:id")->execute(['id' => $jid]);
            }
            flash('success', ucfirst($face) . ' face saved.');
            redirect('admin/institution/ai');
        }

        if ($action === 'stitch') {
            $jid = (int) ($_POST['job_id'] ?? 0);
            $faces = $pdo->prepare("SELECT face, image_path FROM cubemap_faces WHERE job_id=:jid");
            $faces->execute(['jid' => $jid]);
            $faces = $faces->fetchAll();
            if (count($faces) !== 6) throw new RuntimeException('All six faces are required before stitching.');

            $map = [];
            foreach ($faces as $f) {
                $abs = ROOT_PATH . '/' . ltrim($f['image_path'], '/');
                if (!is_file($abs)) throw new RuntimeException('A face file is missing on disk.');
                $map[$f['face']] = $abs;
            }
            if (!is_dir($panoAbs)) mkdir($panoAbs, 0775, true);
            $out = $panoAbs . '/stitch-' . $jid . '-' . random_token(4) . '.jpg';

            $pdo->prepare("UPDATE ai_stitch_jobs SET status='processing' WHERE id=:id")->execute(['id' => $jid]);
            $ok = cubemap_to_equirect($map, $out);
            if (!$ok) throw new RuntimeException('Stitch failed — check that faces are square JPEG/PNG images.');

            $rel = 'assets/panos/' . basename($out);
            $pdo->prepare(
                "UPDATE ai_stitch_jobs SET status='completed', output_equirect_path=:rel, guide_step='done', completed_at=NOW(), provider='builtin-gd', error_message=NULL WHERE id=:id"
            )->execute(['rel' => $rel, 'id' => $jid]);
            try {
                $st = @getimagesize($out);
                $pdo->prepare(
                    "INSERT INTO media_assets (institution_id, uploaded_by, kind, file_path, original_name, width, height) VALUES (:iid,:me,'pano',:rel,:orig,:w,:h)"
                )->execute(['iid' => $iid, 'me' => $me, 'rel' => $rel, 'orig' => 'stitched-360.jpg', 'w' => $st[0] ?? null, 'h' => $st[1] ?? null]);
            } catch (Throwable $e) { /* non-fatal */ }
            audit('ai_stitch.complete', 'ai', 'job', $jid);
            flash('success', 'Stitched! 2048×1024 equirect saved — attach it to a tour scene below.');
            redirect('admin/institution/ai');
        }

        if ($action === 'attach-scene') {
            $jid = (int) ($_POST['job_id'] ?? 0);
            $sid = (int) ($_POST['scene_id'] ?? 0) ?: null;
            $pdo->prepare("UPDATE ai_stitch_jobs SET output_scene_id=:sid WHERE id=:id AND institution_id=:iid")
                ->execute(['sid' => $sid, 'id' => $jid, 'iid' => $iid]);
            if ($sid) {
                $row = $pdo->prepare("SELECT output_equirect_path FROM ai_stitch_jobs WHERE id=:id");
                $row->execute(['id' => $jid]);
                $eq = $row->fetchColumn();
                if ($eq) {
                    $pdo->prepare("UPDATE tour_scenes SET equirect_path=:eq WHERE id=:sid AND institution_id=:iid")
                        ->execute(['eq' => $eq, 'sid' => $sid, 'iid' => $iid]);
                }
            }
            audit('ai_stitch.attach', 'ai', 'job', $jid);
            flash('success', 'Equirect attached to the selected scene.');
            redirect('admin/institution/ai');
        }

        if ($action === 'job-delete') {
            $jid = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM ai_stitch_jobs WHERE id=:id AND institution_id=:iid")->execute(['id' => $jid, 'iid' => $iid]);
            $dir = $cubeAbs . '/' . $jid;
            if (is_dir($dir)) rrmdir($dir);
            flash('success', 'Job removed.');
            redirect('admin/institution/ai');
        }

        /* ------------------------- AI info ------------------------- */
        if ($action === 'info-generate') {
            $targetType = $_POST['target_type'] ?? '';
            $targetId = (int) ($_POST['target_id'] ?? 0);
            $prompt = trim($_POST['prompt'] ?? '');
            $allowed = ['building', 'room', 'facility', 'campus_area', 'tour_scene'];
            if (!in_array($targetType, $allowed, true) || !$targetId) throw new RuntimeException('Pick a target.');

            $name = '';
            if ($targetType === 'building')   { $q = $pdo->prepare("SELECT name FROM buildings WHERE id=:id AND institution_id=:iid"); }
            if ($targetType === 'room')       { $q = $pdo->prepare("SELECT name FROM rooms WHERE id=:id AND institution_id=:iid"); }
            if ($targetType === 'facility')   { $q = $pdo->prepare("SELECT name FROM facilities WHERE id=:id AND institution_id=:iid"); }
            if ($targetType === 'campus_area'){ $q = $pdo->prepare("SELECT name FROM campus_areas WHERE id=:id AND institution_id=:iid"); }
            if ($targetType === 'tour_scene') { $q = $pdo->prepare("SELECT title AS name FROM tour_scenes WHERE id=:id AND institution_id=:iid"); }
            $q->execute(['id' => $targetId, 'iid' => $iid]);
            $name = $q->fetchColumn();
            if (!$name) throw new RuntimeException('Target not found.');

            // built-in generator (offline); replace with an LLM call API key later
            $extra = $prompt !== '' ? ' ' . $prompt : '';
            $out = sprintf(
                "%s is part of %s. %s features a welcoming, functional layout designed for the campus community%s. "
                . "Visitors can explore it through the 360° tour and locate it instantly on the campus floor plan.",
                $name, $inst['name'], $name, $extra
            );

            $pdo->prepare("INSERT INTO ai_info_jobs (institution_id, created_by, target_type, target_id, prompt, output_text, status)
                           VALUES (:iid,:me,:tt,:tid,:p,:out,'completed')")
                ->execute(['iid' => $iid, 'me' => $me, 'tt' => $targetType, 'tid' => $targetId, 'p' => $prompt ?: null, 'out' => $out]);

            $col = $targetType === 'tour_scene' ? 'title' : 'name';
            $tableMap = [
                'building' => 'buildings', 'room' => 'rooms', 'facility' => 'facilities',
                'campus_area' => 'campus_areas', 'tour_scene' => 'tour_scenes',
            ];
            $pdo->prepare("UPDATE {$tableMap[$targetType]} SET ai_description=:ai WHERE id=:id AND institution_id=:iid")
                ->execute(['ai' => $out, 'id' => $targetId, 'iid' => $iid]);
            audit('ai_info.generate', 'ai', $targetType, $targetId);
            flash('success', 'AI info generated and attached.');
            redirect('admin/institution/ai');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/ai');
}

// data ---------------------------------------------------------------
$jobs = $pdo->prepare(
    "SELECT j.*,
            (SELECT COUNT(*) FROM cubemap_faces f WHERE f.job_id=j.id) AS face_count,
            s.title AS scene_title
     FROM ai_stitch_jobs j LEFT JOIN tour_scenes s ON s.id=j.output_scene_id
     WHERE j.institution_id=? ORDER BY j.created_at DESC LIMIT 30"
);
$jobs->execute([$iid]);
$order = ['front', 'back', 'left', 'right', 'up', 'down'];
$facesByJob = [];
$faceRows = $pdo->prepare("SELECT job_id, face, image_path FROM cubemap_faces ORDER BY id");
$faceRows->execute();
foreach ($faceRows->fetchAll() as $fr) { $facesByJob[(int) $fr['job_id']][$fr['face']] = $fr['image_path']; }

$infoJobs = $pdo->prepare("SELECT i.*, u.email FROM ai_info_jobs i JOIN users u ON u.id=i.created_by WHERE i.institution_id=? ORDER BY i.created_at DESC LIMIT 15");
$infoJobs->execute([$iid]);

$scenesForAttach = $pdo->prepare("SELECT id,title FROM tour_scenes WHERE institution_id=? AND deleted_at IS NULL ORDER BY title");
$scenesForAttach->execute([$iid]);

$buildings = $pdo->prepare("SELECT id,name FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name"); $buildings->execute([$iid]);
$rooms = $pdo->prepare("SELECT id,name FROM rooms WHERE institution_id=? AND deleted_at IS NULL ORDER BY name"); $rooms->execute([$iid]);
$facilities = $pdo->prepare("SELECT id,name FROM facilities WHERE institution_id=? ORDER BY name"); $facilities->execute([$iid]);
$areas = $pdo->prepare("SELECT id,name FROM campus_areas WHERE institution_id=? ORDER BY name"); $areas->execute([$iid]);
$scenesForInfo = $pdo->prepare("SELECT id,title FROM tour_scenes WHERE institution_id=? AND deleted_at IS NULL ORDER BY title"); $scenesForInfo->execute([$iid]);

$statusBadge = ['draft' => 'badge-draft', 'uploading' => 'badge-draft', 'queued' => 'badge-draft', 'processing' => 'badge-live', 'completed' => 'badge-live', 'failed' => 'badge-dead'];
?>
<div class="row g-4">
  <!-- ============================ STITCH ============================ -->
  <div class="col-lg-8">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 style="font-weight:800">Stitch jobs</h4>
      <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#job-modal"><?= ia_icon('camera', 16) ?> New stitch job</button>
    </div>

    <?php foreach ($jobs as $job): ?>
      <?php $caps = array_keys($facesByJob[$job['id']] ?? []); $status = (int) $job['face_count']; ?>
      <div class="ia-card mb-3">
        <div class="card-head">
          <div class="d-flex align-items-center gap-2">
            <h3>#<?= (int) $job['id'] ?> <span class="text-muted" style="font-size:13px"><?= h($job['source_type']) ?></span></h3>
            <span class="badge <?= $statusBadge[$job['status']] ?? 'badge-draft' ?>"><?= h($job['status']) ?></span>
            <?php if ($job['source_type'] === 'in_app_capture' && in_array($job['guide_step'], $order, true)): ?>
              <span class="badge" style="background:var(--ia-surface-2)">next: <?= h($job['guide_step']) ?></span>
            <?php endif; ?>
          </div>
          <form method="post" data-delete-form data-confirm="Delete stitch job #<?= (int) $job['id'] ?>?">
            <input type="hidden" name="ai_action" value="job-delete"><input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
            <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
          </form>
        </div>

        <?php if ($job['status'] === 'completed' && $job['output_equirect_path']): ?>
          <div class="row g-3 align-items-center card-body-night">
            <div class="col-md-3">
              <img src="<?= h(org_url($inst['slug'], $job['output_equirect_path'])) ?>" class="rounded-3" style="width:100%;aspect-ratio:2/1;object-fit:cover;border:1px solid var(--ia-border)" alt="equirect">
            </div>
            <div class="col-md-5">
              <div class="fw-bold mb-1"><span class="badge badge-live">ready</span> 2:1 equirectangular panorama</div>
              <div class="text-muted" style="font-size:12.5px"><?= h($job['output_equirect_path']) ?></div>
              <?php if ($job['scene_title']): ?><div class="mt-1" style="font-size:12.5px">Scene: <?= h($job['scene_title']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="ai_action" value="attach-scene"><input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                <select class="form-select form-select-sm" name="scene_id">
                  <option value="">attach to scene…</option>
                  <?php foreach ($scenesForAttach as $sc): ?>
                    <option value="<?= (int) $sc['id'] ?>" <?= (int) ($job['output_scene_id'] ?? 0) === (int) $sc['id'] ? 'selected' : '' ?>><?= h($sc['title']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-grad" type="submit"><?= ia_icon('save', 13) ?></button>
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
                    <button class="btn btn-sm btn-light" type="button" data-replace-face="<?= h($face) ?>" data-job="<?= (int) $job['id'] ?>"><?= ia_icon('refresh', 12) ?></button>
                  </form>
                <?php else: ?>
                  <label class="cube-empty">
                    <input type="file" accept="image/*" class="d-none" data-face-upload data-job="<?= (int) $job['id'] ?>" data-face="<?= h($face) ?>">
                    <span class="cube-plus"><?= ia_icon('camera', 20) ?></span>
                    <span class="cube-label"><?= h($face) ?></span>
                  </label>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted" style="font-size:12.5px"><?= $status ?>/6 faces · <?= $job['source_type'] === 'in_app_capture' ? 'guided capture, in order' : 'free upload' ?></span>
            <?php if ($status === 6): ?>
              <form method="post">
                <input type="hidden" name="ai_action" value="stitch"><input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                <button class="btn btn-grad px-4" <?= $job['status'] === 'completed' ? 'disabled' : '' ?>><?= ia_icon('wand', 15) ?> Stitch now</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($jobs->rowCount() === 0): ?>
      <div class="ia-card"><div class="empty-state"><div class="empty-icon"><?= ia_icon('wand', 26) ?></div><h4>No stitch jobs</h4><p>Create a job, drop six cube faces (front/back/left/right/up/down) or capture in app, then hit Stitch.</p></div></div>
    <?php endif; ?>
  </div>

  <!-- ============================ AI INFO ============================ -->
  <div class="col-lg-4">
    <div class="ia-card">
      <div class="card-head"><h3>AI Info generator</h3></div>
      <form method="post" class="card-body d-grid gap-3">
        <input type="hidden" name="ai_action" value="info-generate">
        <div>
          <label class="form-label">Target type</label>
          <select class="form-select" name="target_type" id="ai-target-type">
            <option value="building">Building</option>
            <option value="room">Room</option>
            <option value="facility">Facility</option>
            <option value="campus_area">Campus area</option>
            <option value="tour_scene">360 scene</option>
          </select>
        </div>
        <div><label class="form-label">Target</label><select class="form-select" name="target_id" id="ai-target-id"></select></div>
        <div><label class="form-label">Prompt / notes <span class="text-muted">(optional)</span></label><textarea class="form-control" name="prompt" rows="3" placeholder="Emphasize the 24/7 study area…"></textarea></div>
        <button class="btn btn-grad"><?= ia_icon('wand', 15) ?> Generate description</button>
        <p class="text-muted mb-0" style="font-size:12px">Offline built-in generator — swap the token call for a real LLM API later.</p>
      </form>
    </div>

    <div class="ia-card mt-3">
      <div class="card-head"><h3>Recent generations</h3></div>
      <div class="card-body d-grid gap-2" style="max-height:330px;overflow:auto">
        <?php foreach ($infoJobs as $ij): ?>
          <div class="px-3 py-2 rounded-3" style="background:var(--ia-surface-2);font-size:12.5px">
            <div class="d-flex justify-content-between gap-2">
              <span class="fw-semibold"><?= h($ij['target_type']) ?> #<?= (int) $ij['target_id'] ?></span>
              <span class="badge badge-live"><?= h($ij['status']) ?></span>
            </div>
            <div class="text-muted mt-1"><?= h(mb_strimwidth($ij['output_text'] ?? '—', 0, 90, '…')) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if ($infoJobs->rowCount() === 0): ?><p class="text-muted" style="font-size:13px">No generations yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- new job modal -->
<div class="modal fade" id="job-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">New stitch job</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="ai_action" value="job-create">
      <div class="modal-body d-grid gap-3">
        <label class="mode-card-dashed">
          <input type="radio" name="source_type" value="cubemap_upload" checked>
          <span class="mode-box-2"><strong><?= ia_icon('upload', 16) ?> Cubemap upload</strong><small>Six square faces, upload all then stitch.</small></span>
        </label>
        <label class="mode-card-dashed">
          <input type="radio" name="source_type" value="in_app_capture">
          <span class="mode-box-2"><strong><?= ia_icon('camera', 16) ?> In-app guided capture</strong><small>Guided wizard: front → back → left → right → up → down.</small></span>
        </label>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Create job</button></div>
    </form>
  </div></div>
</div>

<style>
  .cube-grid { display:grid; grid-template-columns: repeat(6, 1fr); gap:10px; }
  .cube-cell { position:relative; aspect-ratio:1; border-radius:12px; overflow:hidden; border:1px dashed var(--ia-border); background:var(--ia-surface-2); display:flex; align-items:center; justify-content:center; }
  .cube-cell.filled { border-style:solid; }
  .cube-cell img { width:100%; height:100%; object-fit:cover; }
  .cube-empty { cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:4px; color:var(--ia-muted); width:100%; height:100%; justify-content:center; }
  .cube-cell:hover .cube-empty { color:var(--ia-primary); }
  .cube-plus { font-size:18px; }
  .cube-label { position:absolute; bottom:4px; left:6px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--ia-text); background:rgba(255,255,255,.85); padding:2px 6px; border-radius:6px; }
  .cube-re { position:absolute; top:4px; right:4px; opacity:0; transition:opacity .15s; }
  .cube-cell.filled:hover .cube-re { opacity:1; }
  .cube-grid { grid-template-columns: repeat(3, 1fr); }
  @media (min-width: 768px) { .cube-grid { grid-template-columns: repeat(6, 1fr); } }
  .mode-card-dashed { cursor:pointer; }
  .mode-card-dashed > input { position:absolute; opacity:0; }
  .mode-box-2 { display:block; gap:2px; padding:16px; border-radius:14px; border:2px dashed var(--ia-border); transition:all .15s; }
  .mode-box-2 small { display:block; color:var(--ia-muted); margin-top:4px; }
  .mode-card-dashed > input:checked + .mode-box-2 { border-style:solid; border-color:var(--ia-primary); box-shadow:0 0 0 4px var(--ia-primary-soft); }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // face uploads
  window.__aiTargets = {
    building: <?= json_enc(array_values(array_map(fn($b) => ['id'=>$b['id'],'label'=>$b['name']], $buildings->fetchAll()))) ?>,
    room: <?= json_enc(array_values(array_map(fn($r) => ['id'=>$r['id'],'label'=>$r['name']], $rooms->fetchAll()))) ?>,
    facility: <?= json_enc(array_values(array_map(fn($f) => ['id'=>$f['id'],'label'=>$f['name']], $facilities->fetchAll()))) ?>,
    campus_area: <?= json_enc(array_values(array_map(fn($a) => ['id'=>$a['id'],'label'=>$a['name']], $areas->fetchAll()))) ?>,
    tour_scene: <?= json_enc(array_values(array_map(fn($s) => ['id'=>$s['id'],'label'=>$s['title']], $scenesForInfo->fetchAll()))) ?>
  }
  const typeSel = document.getElementById('ai-target-type')
  const idSel = document.getElementById('ai-target-id')
  const fill = () => {
    const list = window.__aiTargets[typeSel.value] || []
    idSel.innerHTML = list.map(o => `<option value="${o.id}">${o.label}</option>`).join('')
  }
  typeSel.addEventListener('change', fill)
  fill()

  // submit face file through a hidden form
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