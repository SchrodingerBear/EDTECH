<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.ar');
/**
 * Innovatech PH — admin: Augmented Reality Target Manager.
 * Building-centric: buildings are the highest unit. Each building holds floor plans;
 * each floor plan holds markers with an optional marker image (the visual target used
 * to train MindAR .mind files). A building's floor-plan marker images can be downloaded
 * as an ordered ZIP for .mind training.
 */
$pageTitle = 'AR Targets';
$pageSub   = 'Buildings → floor plans → marker images (MindAR .mind training)';
$active    = 'Augmented Reality';
$bodyClass = 'page-ar';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid    = (int) $inst['id'];
$me     = (int) current_user()['id'];
$orgAbs = ROOT_PATH . '/' . trim($inst['folder_path'], '/');
$arDir  = $orgAbs . '/assets/ar_targets';
$mkImgDir = $arDir . '/marker_images';

// ---------------------------- actions ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['ar_action'] ?? '';
    try {
        if ($action === 'fp-assoc') {
            $fpId = (int) ($_POST['fp_id'] ?? 0);
            $bld  = (int) ($_POST['building_id'] ?? 0) ?: null;
            $lvl  = trim($_POST['floor_level'] ?? '');
            crud()->update('floor_plans', ['building_id' => $bld, 'floor_level' => $lvl ?: null], ['id' => $fpId, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Floor plan assigned to building.');
        }

        if ($action === 'mk-image') {
            $mid = (int) ($_POST['marker_id'] ?? 0);
            if (!$mid) throw new RuntimeException('Missing marker.');
            if (empty($_FILES['marker_image']['tmp_name'])) throw new RuntimeException('Choose an image.');
            if (!is_dir($mkImgDir)) mkdir($mkImgDir, 0775, true);
            $ext = strtolower(pathinfo($_FILES['marker_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) throw new RuntimeException('Only JPG/PNG/WebP accepted.');
            // remove old image
            $old = crud()->raw("SELECT marker_image_path FROM floor_plan_markers WHERE id=:id AND institution_id=:iid", ['id' => $mid, 'iid' => $iid])->fetchColumn();
            if ($old) { $absOld = $orgAbs . '/' . ltrim($old, '/'); if (str_starts_with($absOld, $mkImgDir) && is_file($absOld)) @unlink($absOld); }
            $fname = 'mk_' . $mid . '.' . $ext;
            move_uploaded_file($_FILES['marker_image']['tmp_name'], $mkImgDir . '/' . $fname);
            crud()->update('floor_plan_markers', ['marker_image_path' => 'assets/ar_targets/marker_images/' . $fname], ['id' => $mid, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Marker image saved (visual target for .mind training).');
        }

        if ($action === 'mk-image-clear') {
            $mid = (int) ($_POST['marker_id'] ?? 0);
            $old = crud()->raw("SELECT marker_image_path FROM floor_plan_markers WHERE id=:id AND institution_id=:iid", ['id' => $mid, 'iid' => $iid])->fetchColumn();
            if ($old) { $absOld = $orgAbs . '/' . ltrim($old, '/'); if (str_starts_with($absOld, $mkImgDir) && is_file($absOld)) @unlink($absOld); }
            crud()->update('floor_plan_markers', ['marker_image_path' => null], ['id' => $mid, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Marker image removed.');
        }
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('admin/institution/ar');
}

// ---------------------------- data ----------------------------
$buildings = crud()->raw("SELECT * FROM buildings WHERE institution_id=:iid AND deleted_at IS NULL ORDER BY name", ['iid' => $iid])->fetchAll();

// floor plans (with building assoc + marker counts, ordered by building then floor level)
$plansStmt = crud()->raw(
    "SELECT fp.*,
            (SELECT COUNT(*) FROM floor_plan_markers m WHERE m.floor_plan_id=fp.id) AS marker_count
       FROM floor_plans fp
      WHERE fp.institution_id=:iid AND fp.deleted_at IS NULL
      ORDER BY fp.building_id IS NULL, fp.building_id, fp.floor_level, fp.title",
    ['iid' => $iid]
);
$floorPlanRaw = $plansStmt->fetchAll();

// markers per floor plan, ordered numerically by id (stable order)
$markersAll = crud()->raw(
    "SELECT * FROM floor_plan_markers WHERE institution_id=:iid ORDER BY floor_plan_id, IFNULL(sort_order,0), id",
    ['iid' => $iid]
)->fetchAll();

// group markers by floor plan
$markersByFp = [];
foreach ($markersAll as $m) { $markersByFp[(int) $m['floor_plan_id']][] = $m; }

// patrol target images? (waypoint images) — count for waypoint section
$waypoints = crud()->raw(
    "SELECT w.*,b.name AS building_name,r.name AS room_name,s.title AS scene_title
       FROM ar_waypoints w
       LEFT JOIN buildings b ON b.id=w.building_id
       LEFT JOIN rooms r ON r.id=w.room_id
       LEFT JOIN tour_scenes s ON s.id=w.scene_id
      WHERE w.institution_id=:iid ORDER BY b.name,w.name", ['iid' => $iid])->fetchAll();
?>

<div class="ia-card mb-4 ia-card-accent">
  <div class="card-body card-body-lg">
    <div class="d-flex gap-3 align-items-start">
      <span class="ia-avatar ia-avatar-lg"><?= ia_icon('scan-eye',22) ?></span>
      <div>
        <h4 class="fw-800 mb-6px">AR Target Manager</h4>
        <p class="mb-0 ia-meta-lg mw-700">
          <strong>Buildings</strong> are the highest unit. Assign each floor plan to a building and set its
          <strong>floor level</strong>. On every marker you can set a <strong>marker image</strong> — that image is the
          visual target you send to the <a href="https://hiukim.github.io/mind-ar-js-doc/tools/compile" target="_blank" rel="noopener">MindAR Compiler</a>
          to build a <code>.mind</code> file. Then download a building's marker images
          <strong>ordered numerically</strong> (with floor level) for sequential training.
        </p>
      </div>
    </div>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="mb-0 ia-meta-lg"><?= count($buildings) ?> building(s) · <?= count($floorPlanRaw) ?> floor plan(s)</p>
</div>

<?php foreach ($buildings as $bi => $b): ?>
<div class="ia-card mb-3">
  <div class="card-head d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h3 class="mb-0">
      <?= ia_icon('building', 17) ?> <?= h($b['name']) ?>
      <?php
        $bplans = array_values(array_filter($floorPlanRaw, fn($p) => (int) ($p['building_id'] ?? 0) === (int) $b['id']));
      ?>
      <span class="badge badge-soft ms-1"><?= count($bplans) ?> floor plan(s)</span>
    </h3>
    <div class="d-flex gap-2">
      <?php if ($bplans): ?>
      <a class="btn btn-grad btn-sm px-3" href="ar-download?building=<?= (int) $b['id'] ?>"><?= ia_icon('download', 14) ?> Download marker images (.mind set)</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <?php if (!$bplans): ?>
      <div class="empty-state py-4"><div class="empty-icon"><?= ia_icon('map', 22) ?></div><h5>No floor plans yet</h5><p>Assign a floor plan to this building below (Unassigned floor plans).</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-ia align-middle">
          <thead><tr><th>Floor plan</th><th>Floor level</th><th>Markers</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($bplans as $fp): ?>
            <tr>
              <td class="fw-bold"><?= h($fp['title']) ?></td>
              <td>
                <form method="post" class="d-flex gap-2 align-items-center">
                  <input type="hidden" name="ar_action" value="fp-assoc">
                  <input type="hidden" name="fp_id" value="<?= (int) $fp['id'] ?>">
                  <select class="form-select form-select-sm" name="floor_level" style="max-width:120px">
                    <option value="">—</option>
                    <?php foreach (['G','1','2','3','4','5','Basement','Roof'] as $fl): ?>
                      <option value="<?= $fl ?>" <?= ($fp['floor_level'] ?? '') === $fl ? 'selected' : '' ?>><?= h($fl) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-outline-ia" title="Save floor level"><?= ia_icon('check', 13) ?></button>
                </form>
              </td>
              <td><?= (int) $fp['marker_count'] ?><button class="btn btn-sm btn-link p-0 ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#fp-markers-<?= (int) $fp['id'] ?>" aria-expanded="false"><?= ia_icon('chevron', 13) ?> manage</button></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-ia" href="floor-plans?studio=<?= (int) $fp['id'] ?>" target="_blank"><?= ia_icon('map', 13) ?> Open studio</a>
              </td>
            </tr>
            <tr class="row-no-pad">
              <td colspan="4" class="p-0">
                <div class="collapse p-3" id="fp-markers-<?= (int) $fp['id'] ?>">
                  <?php $fpm = $markersByFp[(int) $fp['id']] ?? []; ?>
                  <?php if (!$fpm): ?>
                    <p class="ia-meta-md mb-0">No markers on this floor plan yet. Open the studio to add markers, then set an image on each one here.</p>
                  <?php else: ?>
                    <div class="row g-3">
                      <?php foreach ($fpm as $mi => $mk): ?>
                        <div class="col-md-6 col-xl-4">
                          <div class="border rounded p-3 h-100 d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-center">
                              <span class="fw-bold"><?= h($mk['label']) ?></span>
                              <span class="badge <?= $mk['marker_type']==='entrance'?'badge-live':($mk['marker_type']==='exit'?'badge-danger':'badge-soft') ?>"><?= h($mk['marker_type']??'scene') ?></span>
                            </div>
                            <div class="ia-micro text-ia-muted">Order #<?= $mi + 1 ?> · <?= (float)$mk['x_percent'] ?>%,<?= (float)$mk['y_percent'] ?>%</div>
                            <?php if ($mk['marker_image_path']): ?>
                              <img src="<?= h(media_url($mk['marker_image_path'])) ?>" class="rounded border" style="width:100%;height:110px;object-fit:cover" alt="">
                              <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="ar_action" value="mk-image-clear">
                                <input type="hidden" name="marker_id" value="<?= (int) $mk['id'] ?>">
                                <button class="btn btn-sm btn-outline-ia text-danger w-100">Remove image</button>
                              </form>
                            <?php else: ?>
                              <form method="post" enctype="multipart/form-data" class="d-grid gap-2">
                                <input type="hidden" name="ar_action" value="mk-image">
                                <input type="hidden" name="marker_id" value="<?= (int) $mk['id'] ?>">
                                <input type="file" class="form-control form-control-sm" name="marker_image" accept="image/png,image/jpeg,image/webp" required>
                                <button class="btn btn-sm btn-outline-ia"><?= ia_icon('upload', 13) ?> Set marker image (visual target)</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php
// unassigned floor plans
$unassigned = array_values(array_filter($floorPlanRaw, fn($p) => empty($p['building_id'])));
?>
<?php if ($unassigned): ?>
<div class="ia-card mb-3">
  <div class="card-head"><h3 class="mb-0"><?= ia_icon('inbox', 16) ?> Unassigned floor plans</h3></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-ia align-middle">
        <thead><tr><th>Floor plan</th><th>Assign to building</th><th>Floor level</th></tr></thead>
        <tbody>
        <?php foreach ($unassigned as $fp): ?>
          <tr>
            <td class="fw-bold"><?= h($fp['title']) ?></td>
            <td>
              <form method="post" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="ar_action" value="fp-assoc">
                <input type="hidden" name="fp_id" value="<?= (int) $fp['id'] ?>">
                <select class="form-select form-select-sm" name="building_id" style="max-width:240px" required>
                  <option value="" disabled selected>— choose building —</option>
                  <?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm" name="floor_level" style="max-width:110px">
                  <option value="">—</option>
                  <?php foreach (['G','1','2','3','4','5','Basement','Roof'] as $fl): ?><option value="<?= $fl ?>"><?= h($fl) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-grad"><?= ia_icon('check', 13) ?> Assign</button>
              </form>
            </td>
            <td class="ia-micro text-ia-muted">Floor level helps order the training set per floor.</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
