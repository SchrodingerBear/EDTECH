<?php
/**
 * Innovatech PH — staff: AI info descriptions for buildings, rooms, facilities and areas.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.aiinfo');
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'AI Info';
$pageSub = 'Generate visitor-friendly descriptions in one click';
$active = 'AI Info';

$pdo = db();
$inst = current_institution();
$iid = (int) $inst['id'];
$me = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['aiinfo_action'] ?? '';
    try {
        if ($action === 'generate') {
            $targetType = $_POST['target_type'] ?? '';
            $targetId = (int) ($_POST['target_id'] ?? 0);
            $prompt = trim($_POST['prompt'] ?? '');
            $allowed = ['building', 'room', 'facility', 'campus_area'];
            if (!in_array($targetType, $allowed, true) || !$targetId) throw new RuntimeException('Pick a target.');
            $tableMap = ['building' => 'buildings', 'room' => 'rooms', 'facility' => 'facilities', 'campus_area' => 'campus_areas'];
            $q = $pdo->prepare("SELECT name FROM {$tableMap[$targetType]} WHERE id=:id AND institution_id=:iid");
            $q->execute(['id' => $targetId, 'iid' => $iid]);
            $name = $q->fetchColumn();
            if (!$name) throw new RuntimeException('Target not found.');

            $out = sprintf(
                "%s is a key part of %s. Visitors can find it on the campus floor plan, view photos in the media section, and explore it through the 360° virtual tour%s.",
                $name, $inst['name'], $prompt !== '' ? ' — ' . $prompt : ''
            );
            $pdo->prepare("INSERT INTO ai_info_jobs (institution_id, created_by, target_type, target_id, prompt, output_text, status) VALUES (:iid,:me,:tt,:tid,:p,:out,'completed')")
                ->execute(['iid' => $iid, 'me' => $me, 'tt' => $targetType, 'tid' => $targetId, 'p' => $prompt ?: null, 'out' => $out]);
            $tableMap = ['building' => 'buildings', 'room' => 'rooms', 'facility' => 'facilities', 'campus_area' => 'campus_areas'];
            $pdo->prepare("UPDATE {$tableMap[$targetType]} SET ai_description=:ai WHERE id=:id AND institution_id=:iid")
                ->execute(['ai' => $out, 'id' => $targetId, 'iid' => $iid]);
            audit('ai_info.generate', 'ai', $targetType, $targetId);
            flash('success', 'AI description generated.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/staff/ai-info');
}

$jobs = $pdo->prepare("SELECT i.*, u.email FROM ai_info_jobs i JOIN users u ON u.id=i.created_by WHERE i.institution_id=? ORDER BY i.created_at DESC LIMIT 20");
$jobs->execute([$iid]);

$tables = [
    'building'    => "SELECT id, name, COALESCE(ai_description,'') AS ai FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name",
    'room'        => "SELECT id, name, COALESCE(ai_description,'') AS ai FROM rooms WHERE institution_id=? AND deleted_at IS NULL ORDER BY name",
    'facility'    => "SELECT id, name, COALESCE(ai_description,'') AS ai FROM facilities WHERE institution_id=? ORDER BY name",
    'campus_area' => "SELECT id, name, COALESCE(ai_description,'') AS ai FROM campus_areas WHERE institution_id=? ORDER BY name",
];
$rows = [];
foreach ($tables as $t => $sql) {
    $st = $pdo->prepare($sql);
    $st->execute([$iid]);
    $rows[$t] = $st->fetchAll();
}
?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="ia-card">
      <div class="card-head"><h3>Generate description</h3></div>
      <form method="post" class="card-body d-grid gap-3">
        <input type="hidden" name="aiinfo_action" value="generate">
        <div>
          <label class="form-label">Target type</label>
          <select class="form-select" name="target_type" id="ai-type">
            <?php foreach ($tables as $t => $sql): ?>
              <option value="<?= h($t) ?>"><?= h(ucwords(str_replace('_', ' ', $t))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Target</label>
          <select class="form-select" name="target_id" id="ai-target"></select>
        </div>
        <div>
          <label class="form-label">Extra notes <span class="text-muted">(optional)</span></label>
          <textarea class="form-control" name="prompt" rows="3" placeholder="Quiet study areas, 24/7 access…"></textarea>
        </div>
        <button class="btn btn-grad"><?= ia_icon('wand', 15) ?> Generate & attach</button>
        <p class="text-muted mb-0" style="font-size:12px">Runs on a built-in generator — swap for an LLM API token later.</p>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="row g-3">
      <?php foreach ($rows as $t => $items): ?>
        <div class="col-md-6">
          <div class="ia-card h-100">
            <div class="card-head"><h3><?= h(ucwords(str_replace('_', ' ', $t))) ?> <span class="text-muted" style="font-size:12px">(<?= count($items) ?>)</span></h3></div>
            <div class="card-body d-grid gap-2" style="max-height:280px;overflow:auto">
              <?php foreach ($items as $it): ?>
                <div class="px-3 py-2 rounded-3 d-flex justify-content-between align-items-center gap-2" style="background:var(--ia-surface-2)">
                  <span class="fw-semibold" style="font-size:13.5px"><?= h($it['name']) ?></span>
                  <?php if (trim((string) $it['ai']) !== ''): ?>
                    <span class="badge badge-live"><?= ia_icon('sparkles', 11) ?> ready</span>
                  <?php else: ?>
                    <span class="badge badge-draft">no ai</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (!$items): ?><p class="text-muted" style="font-size:13px">Nothing yet in this category.</p><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="ia-card mt-4">
  <div class="card-head"><h3>Generation history</h3></div>
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>Target</th><th>Status</th><th>By</th><th>When</th><th>Output</th></tr></thead>
      <tbody>
        <?php foreach ($jobs as $j): ?>
          <tr>
            <td style="white-space:nowrap"><span class="badge" style="background:var(--ia-surface-2)"><?= h($j['target_type']) ?></span> #<?= (int) $j['target_id'] ?></td>
            <td><span class="badge badge-live"><?= h($j['status']) ?></span></td>
            <td style="color:var(--ia-muted)"><?= h($j['email']) ?></td>
            <td style="color:var(--ia-muted);white-space:nowrap"><?= h(date('M j, g:i A', strtotime($j['created_at']))) ?></td>
            <td style="color:var(--ia-muted);max-width:320px"><?= h(mb_strimwidth($j['output_text'] ?? '—', 0, 90, '…')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($jobs->rowCount() === 0): ?>
          <tr><td colspan="5"><div class="empty-state"><h4>No generations yet</h4><p>Pick a building, room or facility and hit Generate.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = <?= json_enc($rows) ?>
  const typeSel = document.getElementById('ai-type')
  const idSel = document.getElementById('ai-target')
  const fill = () => {
    const list = rows[typeSel.value] || []
    idSel.innerHTML = list.map(o => `<option value="${o.id}">${o.name}</option>`).join('')
  }
  typeSel.addEventListener('change', fill)
  fill()
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>