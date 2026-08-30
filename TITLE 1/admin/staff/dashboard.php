<?php
/**
 * Innovatech PH — staff: overview.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.dashboard');
$pageTitle = 'Staff Overview';
$pageSub = display_name(current_user()) . ' · ' . (current_institution()['name'] ?? '—');
$active = 'Overview';
$bodyClass = 'page-staff-dashboard';
require_once __DIR__ . '/../layout/header.php';

$iid = (int) current_institution()['id'];
$me = (int) current_user()['id'];

$facilities = crud()->count('facilities',     ['institution_id' => $iid]);
$floorPlans = crud()->count('floor_plans',    ['institution_id' => $iid]);
$scenes     = crud()->count('tour_scenes',    ['institution_id' => $iid, 'deleted_at' => ['IS', null]]);
$media      = crud()->count('media_assets',   ['institution_id' => $iid]);
$myStitch   = crud()->count('ai_stitch_jobs', ['institution_id' => $iid, 'created_by' => $me]);

$recentStitch = crud()->raw(
    'SELECT j.*, (SELECT COUNT(*) FROM cubemap_faces f WHERE f.job_id=j.id) fc FROM ai_stitch_jobs j WHERE j.institution_id=:iid ORDER BY j.created_at DESC LIMIT 4',
    [':iid' => $iid]
)->fetchAll();

$recentMedia = crud()->raw(
    'SELECT m.* FROM media_assets m WHERE m.institution_id=:iid ORDER BY m.created_at DESC LIMIT 8',
    [':iid' => $iid]
)->fetchAll();

$stats = [
    ['label' => 'Facilities', 'num' => $facilities, 'icon' => 'building', 'to' => 'admin/staff/facilities'],
    ['label' => 'Floor plans', 'num' => $floorPlans, 'icon' => 'map', 'to' => 'admin/staff/floor-plans'],
    ['label' => '360 scenes', 'num' => $scenes, 'icon' => 'camera', 'to' => 'admin/staff/ai-stitch'],
    ['label' => 'Media files', 'num' => $media, 'icon' => 'image', 'to' => 'admin/staff/uploads'],
];
?>
<div class="row g-3">
  <?php foreach ($stats as $s): ?>
    <div class="col-6 col-xl-3">
      <a href="<?= url($s['to']) ?>" class="text-decoration-none">
        <div class="ia-stat">
          <div class="stat-icon"><?= ia_icon($s['icon'], 21) ?></div>
          <div class="stat-num"><?= $s['num'] ?></div>
          <div class="stat-label"><?= h($s['label']) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mt-1">
  <div class="col-lg-7">
    <div class="ia-card">
      <div class="card-head"><h3>Stitch jobs <span class="text-muted fs-12">(<?= $myStitch ?> by you)</span></h3>
        <a class="btn btn-grad btn-sm" href="ai-stitch"><?= ia_icon('camera', 14) ?> New job</a>
      </div>
      <div class="table-responsive">
        <table class="table table-ia">
          <thead><tr><th>Job</th><th>Faces</th><th>Status</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($recentStitch as $j): ?>
              <tr>
                <td class="fw-semibold">#<?= (int) $j['id'] ?> <span class="text-ia-muted">· <?= h($j['source_type']) ?></span></td>
                <td class="text-ia-muted"><?= (int) $j['fc'] ?>/6</td>
                <td>
                  <?php if ($j['status'] === 'completed'): ?>
                    <span class="badge badge-live"><?= h($j['status']) ?></span>
                  <?php elseif (in_array($j['status'], ['failed'], true)): ?>
                    <span class="badge badge-dead">failed</span>
                  <?php else: ?>
                    <span class="badge badge-draft"><?= h($j['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-ia-muted text-nowrap"><?= h(date('M j, g:i A', strtotime($j['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentStitch): ?>
              <tr><td colspan="4"><div class="empty-state"><div class="empty-icon"><?= ia_icon('camera', 22) ?></div><h4>No stitch jobs yet</h4><p>Capture six cube faces (or upload them) to auto-stitch a 360 panorama.</p></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="ia-card">
      <div class="card-head"><h3>Recent uploads</h3><a class="text-muted fs-13 text-decoration-none" href="uploads">view all</a></div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($recentMedia as $m): ?>
            <?php if ($m['file_path'] && preg_match('/\.(jpe?g|png|gif|webp)$/i', $m['file_path'])): ?>
              <img src="<?= h(org_url(current_institution()['slug'], $m['file_path'])) ?>" class="avatar-sm rounded-3 thumb-64" alt="media">
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$recentMedia): ?>
            <p class="text-muted mb-0 fs-13">No media yet — upload photos for buildings, rooms and floor plans.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head"><h3>Quick Guides</h3></div>
      <div class="card-body card-body-px">
        <div class="guide-item">
            <strong>Campus Content</strong>
            <div>Add buildings, areas, and 360 virtual tours.</div>
        </div>
        <div class="guide-item">
            <strong>Floor Plans & Media</strong>
            <div>Upload image assets and drop navigation markers on maps.</div>
        </div>
        <div class="guide-item">
            <strong>AI Tools</strong>
            <div>Use AI to stitch panoramas or generate facility descriptions.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>