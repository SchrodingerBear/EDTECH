<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.dashboard');
/**
 * Innovatech PH — admin: institution overview.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Institution Dashboard';
$pageSub = 'Manage your campus content';
$active = 'Overview';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];

$stats = [
    'buildings'  => crud()->count('buildings',  ['institution_id' => $iid, 'deleted_at' => ['IS', null]]),
    'rooms'      => crud()->count('rooms',       ['institution_id' => $iid]),
    'scenes'     => crud()->count('tour_scenes', ['institution_id' => $iid, 'deleted_at' => ['IS', null]]),
    'floorplans' => crud()->count('floor_plans', ['institution_id' => $iid]),
];

$mode = $inst['landing_mode'] ?? '360_rotation';
$folder = trim($inst['folder_path'] ?? '', '/');
$folderOk = $folder !== '' && is_dir(ROOT_PATH . '/' . $folder);

// landing start info
$startInfo = null;
if ($mode === '360_rotation' && !empty($inst['starting_scene_id'])) {
    $startInfo = crud()->raw(
        'SELECT id, title FROM tour_scenes WHERE id=:id AND institution_id=:iid LIMIT 1',
        [':id' => (int) $inst['starting_scene_id'], ':iid' => $iid]
    )->fetch();
} elseif ($mode === 'floor_plan' && !empty($inst['starting_floor_plan_id'])) {
    $startInfo = crud()->raw(
        'SELECT id, title FROM floor_plans WHERE id=:id AND institution_id=:iid LIMIT 1',
        [':id' => (int) $inst['starting_floor_plan_id'], ':iid' => $iid]
    )->fetch();
}

$recentScenes = crud()->raw(
    'SELECT id, title, featured_image_path, is_landing_start FROM tour_scenes WHERE institution_id=:iid AND deleted_at IS NULL ORDER BY sort_order, id DESC LIMIT 5',
    [':iid' => $iid]
)->fetchAll();
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
  <div>
    <h3 style="font-weight:800;letter-spacing:-.02em"><?= h($inst['name']) ?></h3>
    <p class="mb-0" style="color:var(--ia-muted);font-size:13.5px">
      <?= h(ucfirst($inst['institution_type'])) ?> · Landing: <strong><?= $mode === 'floor_plan' ? 'Floor plan' : '360 rotation' ?></strong>
      <?php if ($folder): ?> · Folder: <code><?= h($folder) ?></code> <?= $folderOk ? '' : '<span class="text-danger">(missing)</span>' ?><?php endif; ?>
    </p>
  </div>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-outline-ia" href="<?= h(org_url($inst['slug'])) ?>" target="_blank"><?= ia_icon('globe', 15) ?> Open landing</a>
  </div>
</div>

<div class="row g-4 mb-4">
  <?php $cards = [
      ['Buildings', $stats['buildings'], 'building', 'buildings'],
      ['Rooms & areas', $stats['rooms'], 'map', 'locations'],
      ['360 Scenes', $stats['scenes'], 'camera', 'tours'],
      ['Floor plans', $stats['floorplans'], 'compass', 'floor-plans'],
  ]; ?>
  <?php foreach ($cards as [$label, $num, $icon, $href]): ?>
    <div class="col-6 col-xl-3">
      <a class="ia-stat d-block" href="<?= $href ?>">
        <div class="stat-icon"><?= ia_icon($icon) ?></div>
        <div class="stat-num"><?= (int) $num ?></div>
        <div class="stat-label"><?= h($label) ?></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="ia-card">
      <div class="card-head"><h3>Landing starting point</h3><a class="back-link" href="settings">Configure</a></div>
      <div class="card-body">
        <?php if ($startInfo): ?>
          <div class="d-flex align-items-center gap-3">
            <span class="ia-avatar" style="width:46px;height:46px;border-radius:14px"><?= ia_icon($mode === '360_rotation' ? 'camera' : 'map', 20) ?></span>
            <div>
              <div style="font-weight:700"><?= h($startInfo['title']) ?></div>
              <div style="font-size:12.5px;color:var(--ia-muted)">Visitors start here when they open <?= h($inst['short_name'] ?: $inst['name']) ?></div>
            </div>
          </div>
        <?php else: ?>
          <div class="empty-state" style="padding:28px">
            <h4>No starting point set</h4>
            <p>Pick a 360 scene (or a floor plan) as the landing start.</p>
            <a class="btn btn-grad btn-sm" href="settings">Set now</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Recent scenes</h3><a class="back-link" href="tours">Manage</a></div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>Scene</th><th>Featured</th><th>Start</th></tr></thead>
          <tbody>
            <?php foreach ($recentScenes as $sc): ?>
              <tr>
                <td style="font-weight:600"><?= h($sc['title']) ?><?= $sc['featured_image_path'] ? '' : ' <span class="text-muted">(no featured image)</span>' ?></td>
                <td><?= $sc['featured_image_path'] ? '<span class="badge badge-live">set</span>' : '<span class="badge badge-draft">pending</span>' ?></td>
                <td><?= (int) $sc['is_landing_start'] === 1 ? '<span class="badge badge-live">start</span>' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentScenes): ?><tr><td colspan="3"><div class="empty-state"><h4>No scenes yet</h4><p>Add your first 360 scene.</p></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="ia-card">
      <div class="card-head"><h3>Quick actions</h3></div>
      <div class="card-body d-grid gap-2" style="padding:18px">
        <a class="btn btn-grad" href="tours">+ Add 360 scene</a>
        <a class="btn btn-outline-ia" href="buildings">Manage buildings</a>
        <a class="btn btn-outline-ia" href="floor-plans">Floor plan markers</a>
        <a class="btn btn-outline-ia" href="ai">AI stitch tool</a>
        <a class="btn btn-outline-ia" href="files">Organization files</a>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Quick Guides</h3></div>
      <div class="card-body" style="padding:18px">
        <p class="text-muted" style="font-size:13px; margin-bottom:15px;">Follow these steps to set up your virtual campus:</p>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">1. Content Foundation</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Add <a href="buildings">Buildings</a> first, then create <a href="locations">Rooms & Areas</a> inside them.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">2. Immersive Media</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Upload <a href="floor-plans">Floor Plans</a> and place markers, or upload panoramas in <a href="tours">360 Tours</a>.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">3. Interactive Elements</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Use the Studio in Floor Plans to add markers, and use the <a href="ar">Augmented Reality</a> tool for campus navigation.</div>
        </div>
        <div style="margin-bottom:12px;">
            <strong style="color:var(--ia-text)">4. Theme & Landing</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Go to <a href="settings">Theme & Landing</a> to set your starting point, colors, and landing mode.</div>
        </div>
        <div>
            <strong style="color:var(--ia-text)">5. Enhance with AI</strong>
            <div style="font-size:13px;color:var(--ia-muted)">Optionally use <a href="ai">AI Tools</a> to stitch panoramas or generate facility descriptions.</div>
        </div>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Publish status</h3></div>
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <div style="font-weight:700"><?= (int) $inst['is_published'] === 1 ? 'Live for visitors' : 'Unpublished' ?></div>
            <div style="font-size:12.5px;color:var(--ia-muted)">Controlled by the platform owner</div>
          </div>
          <span class="badge <?= (int) $inst['is_published'] === 1 ? 'badge-live' : 'badge-off' ?>"><?= (int) $inst['is_published'] === 1 ? 'live' : 'draft' ?></span>
        </div>
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div style="font-weight:700">Landscape lock on mobile</div>
            <div style="font-size:12.5px;color:var(--ia-muted)">AR / 360 landing is fullscreen landscape</div>
          </div>
          <span class="badge badge-live">on</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>