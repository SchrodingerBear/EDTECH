<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.floorplans');
/**
 * Innovatech PH — admin: floor plans + responsive markers (percentage coords).
 * Markers are placed/dragged on an aspect-locked stage so alignment survives any screen.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Floor Plans';
$pageSub = 'Upload the campus map and drop circular markers (drag & drop studio)';
$active = 'Floor Plans';

$pdo = db();
$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];
$orgDir = ROOT_PATH . '/' . trim($inst['folder_path'], '/') . '/assets/floorplans';

$studioPlanId = (int) ($_GET['studio'] ?? 0);
$filterBuildingId = (int) ($_GET['building'] ?? 0);

// ------------------------------- actions -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['fp_action'] ?? '';
    try {
        if ($action === 'upload') {
            $title = trim($_POST['title'] ?? '') ?: 'Campus Map';
            $rel = handle_media_picker('image', trim($inst['folder_path'], '/') . '/assets/floorplans');
            if (!$rel) throw new RuntimeException('Choose an image.');

            $abs = ROOT_PATH . '/' . ltrim($rel, '/');
            $size = @getimagesize($abs);
            $w = (int) ($size[0] ?? 1200);
            $h = (int) ($size[1] ?? 900);

            $pdo->prepare(
                "INSERT INTO floor_plans (institution_id, title, image_path, original_width, original_height, aspect_ratio, object_fit, created_by)
                 VALUES (:iid,:t,:img,:w,:h,:ar,'contain',:me)"
            )->execute(['iid' => $iid, 't' => $title, 'img' => $rel, 'w' => $w, 'h' => $h, 'ar' => $w / max(1, $h), 'me' => (int) current_user()['id']]);
            flash('success', 'Floor plan uploaded (aspect ratio locked).');
        }

        if ($action === 'landing') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE institutions SET landing_mode='floor_plan', starting_scene_id=NULL, starting_floor_plan_id=:fp WHERE id=:iid")
                ->execute(['fp' => $id, 'iid' => $iid]);
            $pdo->prepare("UPDATE floor_plans SET is_campus_landing=0 WHERE institution_id=:iid")->execute(['iid' => $iid]);
            $pdo->prepare("UPDATE floor_plans SET is_campus_landing=1 WHERE id=:id AND institution_id=:iid")->execute(['id' => $id, 'iid' => $iid]);
            $_SESSION['user']['institution']['landing_mode'] = 'floor_plan';
            $_SESSION['user']['institution']['starting_floor_plan_id'] = $id;
            flash('success', 'This floor plan is now the landing.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = $pdo->prepare("SELECT image_path FROM floor_plans WHERE id=:id AND institution_id=:iid");
            $row->execute(['id' => $id, 'iid' => $iid]);
            $img = $row->fetchColumn();
            $pdo->prepare("DELETE FROM floor_plans WHERE id=:id AND institution_id=:iid")->execute(['id' => $id, 'iid' => $iid]);
            if ($img) {
                $abs = ROOT_PATH . '/' . ltrim($img, '/');
                if (str_starts_with($abs, $orgDir) && is_file($abs)) unlink($abs);
            }
            flash('success', 'Floor plan removed.');
        }

        if ($action === 'marker-add') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            if ($label === '') throw new RuntimeException('Marker label required.');
            $pdo->prepare(
                "INSERT INTO floor_plan_markers (institution_id, floor_plan_id, label, x_percent, y_percent, size_percent,
                        target_room_id, target_building_id, target_facility_id, target_scene_id, target_floor_plan_id, popup_title, popup_html, sort_order)
                 VALUES (:iid,:plan,:label,:x,:y,4,:tr,:tb,:tf,:ts,:tfp,:pt,:ph,0)"
            )->execute([
                'iid' => $iid, 'plan' => $planId, 'label' => $label,
                'x' => (float) ($_POST['x'] ?? 50), 'y' => (float) ($_POST['y'] ?? 50),
                'tr'  => (int) ($_POST['target_room_id'] ?? 0) ?: null,
                'tb'  => (int) ($_POST['target_building_id'] ?? 0) ?: null,
                'tf'  => (int) ($_POST['target_facility_id'] ?? 0) ?: null,
                'ts'  => (int) ($_POST['target_scene_id'] ?? 0) ?: null,
                'tfp' => (int) ($_POST['target_floor_plan_id'] ?? 0) ?: null,
                'pt' => trim($_POST['popup_title'] ?? '') ?: null,
                'ph' => trim($_POST['popup_html'] ?? '') ?: null,
            ]);
            sync_institution_config($iid);
            flash('success', 'Marker added. Drag it into place in the studio.');
        }

        if ($action === 'markers-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $upd = $pdo->prepare(
                "UPDATE floor_plan_markers SET x_percent=:x, y_percent=:y, popup_title=:pt, popup_html=:ph, label=:l WHERE id=:id AND institution_id=:iid"
            );
            foreach (($_POST['markers'] ?? []) as $m) {
                $mid = (int) ($m['id'] ?? 0);
                if (!$mid) continue;
                $upd->execute([
                    'x' => max(0, min(100, (float) ($m['x'] ?? 0))),
                    'y' => max(0, min(100, (float) ($m['y'] ?? 0))),
                    'pt' => trim($m['popup_title'] ?? '') ?: null,
                    'ph' => trim($m['popup_html'] ?? '') ?: null,
                    'l' => trim($m['label'] ?? '') ?: 'Marker',
                    'id' => $mid, 'iid' => $iid,
                ]);
            }
            if (isset($_POST['marker_delete']) && $_POST['marker_delete'] !== '') {
                $pdo->prepare("DELETE FROM floor_plan_markers WHERE id=:id AND institution_id=:iid")
                    ->execute(['id' => (int) $_POST['marker_delete'], 'iid' => $iid]);
            }
            sync_institution_config($iid);
            flash('success', 'Marker positions saved.');
        }

        if ($action === 'marker-delete') {
            $pdo->prepare("DELETE FROM floor_plan_markers WHERE id=:id AND institution_id=:iid")
                ->execute(['id' => (int) ($_POST['id'] ?? 0), 'iid' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Marker removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/floor-plans' . ($studioPlanId ? '?studio=' . $studioPlanId : ''));
}

$plansQuery = "SELECT fp.*, (SELECT COUNT(*) FROM floor_plan_markers m WHERE m.floor_plan_id=fp.id) AS marker_count,
                        (SELECT 1 FROM institutions i WHERE i.id=:iid2 AND i.starting_floor_plan_id=fp.id) AS is_start
                        FROM floor_plans fp WHERE fp.institution_id=:iid";
$plansParams = ['iid' => $iid, 'iid2' => $iid];
if ($filterBuildingId) {
    // Floor plans don't have a building_id directly; filter by plans that have markers pointing to this building
    // Show all plans that have at least one marker targeting this building, or all if not narrowed
    // For a simple UX: just show all plans with a notice that they can assign markers to that building
}
$plans = $pdo->prepare($plansQuery . ' ORDER BY fp.created_at DESC');
$plans->execute($plansParams);

// studio data
$studio = null;
$markers = [];
if ($studioPlanId) {
    $q = $pdo->prepare("SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid");
    $q->execute(['id' => $studioPlanId, 'iid' => $iid]);
    $studio = $q->fetch() ?: null;
    if ($studio) {
        $mk = $pdo->prepare("SELECT * FROM floor_plan_markers WHERE floor_plan_id=? AND institution_id=? ORDER BY sort_order, id");
        $mk->execute([$studioPlanId, $iid]);
        $markers = $mk->fetchAll();
    }
}

$buildings = $pdo->prepare("SELECT id,name FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name");
$buildings->execute([$iid]);
$rooms = $pdo->prepare("SELECT id,name FROM rooms WHERE institution_id=? AND deleted_at IS NULL ORDER BY name");
$rooms->execute([$iid]);
$scenes = $pdo->prepare("SELECT id,title as name FROM tour_scenes WHERE institution_id=? AND deleted_at IS NULL ORDER BY title");
$scenes->execute([$iid]);
$floorPlansList = $pdo->prepare("SELECT id,title as name FROM floor_plans WHERE institution_id=? AND deleted_at IS NULL AND id!=? ORDER BY title");
$floorPlansList->execute([$iid, $studioPlanId ?: 0]);
?>

<?php if ($studio): ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <a class="back-link" href="floor-plans">← All floor plans</a>
      <div class="d-flex align-items-center gap-2 mt-1">
        <h3 class="mb-0" style="font-weight:800"><?= h($studio['title']) ?> — marker studio</h3>
        <?php if ($studio['is_campus_landing']): ?><span class="badge badge-live">landing</span><?php endif; ?>
      </div>
    </div>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#marker-modal"><?= ia_icon('map', 16) ?> Add marker</button>
  </div>

  <form method="post">
    <input type="hidden" name="fp_action" value="markers-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <div class="ia-card p-3">
      <!-- aspect-locked stage: markers stay aligned on every screen -->
      <div id="map-stage" class="map-stage"
           style="aspect-ratio: <?= $studio['aspect_ratio'] ?>; position:relative; margin:0 auto; max-width:100%; max-height:66vh; user-select:none;">
        <img src="<?= h(org_url($inst['slug'], $studio['image_path'])) ?>" alt="floor plan"
             style="width:100%;height:100%;object-fit:contain;border-radius:12px;display:block;pointer-events:none;">
        <input type="hidden" name="markers-hash" id="markers-hash">

        <?php foreach ($markers as $mkIdx => $mk): ?>
          <div class="fp-marker-dot" data-id="<?= (int) $mk['id'] ?>"
               data-name="<?= h($mk['label'], ENT_QUOTES) ?>"
               style="position:absolute;left:<?= (float) $mk['x_percent'] ?>%;top:<?= (float) $mk['y_percent'] ?>%;transform:translate(-50%,-50%);width:36px;height:36px">
            <input type="hidden" name="markers[<?= $mkIdx ?>][id]" value="<?= (int) $mk['id'] ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][x]" value="<?= (float) $mk['x_percent'] ?>" class="mk-x">
            <input type="hidden" name="markers[<?= $mkIdx ?>][y]" value="<?= (float) $mk['y_percent'] ?>" class="mk-y">
            <input type="hidden" name="markers[<?= $mkIdx ?>][label]" value="<?= h($mk['label']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_building_id]" value="<?= h($mk['target_building_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_room_id]" value="<?= h($mk['target_room_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_scene_id]" value="<?= h($mk['target_scene_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][target_floor_plan_id]" value="<?= h($mk['target_floor_plan_id']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][popup_title]" value="<?= h($mk['popup_title']) ?>">
            <input type="hidden" name="markers[<?= $mkIdx ?>][popup_html]" value="<?= h($mk['popup_html']) ?>">
          </div>
        <?php endforeach; ?>

        <div class="map-hint">Drag the dots to position markers. Click a dot to edit its label/popup. Save when done.</div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="d-flex gap-2 flex-wrap" id="marker-list">
          <?php foreach ($markers as $mk): ?>
            <button type="button" class="btn btn-sm btn-outline-ia" data-select-dot="<?= (int) $mk['id'] ?>">
              <span class="dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--ia-primary);margin-right:6px"></span><?= h($mk['label']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-grad px-4" type="submit">Save marker positions</button>
      </div>
    </div>
  </form>

  <!-- marker fields panel -->
  <div class="ia-card mt-3">
    <div class="card-head"><h3>Selected marker</h3></div>
    <div class="card-body" id="marker-fields">
      <p class="text-muted mb-0" style="font-size:13px">Select a marker dot or a chip above to edit its label and popup.</p>
    </div>
  </div>

  <!-- add marker modal -->
  <div class="modal fade" id="marker-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add marker</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post">
        <input type="hidden" name="fp_action" value="marker-add">
        <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
        <input type="hidden" name="x" value="50"><input type="hidden" name="y" value="50">
        <div class="modal-body d-grid gap-3">
          <div><label class="form-label">Label</label><input class="form-control" name="label" required placeholder="Library"></div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Room</label>
              <select class="form-select" name="target_room_id"><option value="">— none —</option><?php foreach ($rooms->fetchAll() as $r): ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Building</label>
              <select class="form-select" name="target_building_id"><option value="">— none —</option><?php foreach ($buildings->fetchAll() as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Link to 360 Tour</label>
              <select class="form-select" name="target_scene_id"><option value="">— none —</option><?php foreach ($scenes->fetchAll() as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Link to Sub-Floor Plan</label>
              <select class="form-select" name="target_floor_plan_id"><option value="">— none —</option><?php foreach ($floorPlansList->fetchAll() as $fp): ?><option value="<?= (int) $fp['id'] ?>"><?= h($fp['name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="form-text mb-1">
            <strong>Click action priority:</strong> 360 Tour &gt; Sub-Floor Plan &gt; Popup. Set only one target for clean behavior.
          </div>
          <div><label class="form-label">Popup title</label><input class="form-control" name="popup_title" placeholder="Library hours &amp; info"></div>
          <div><label class="form-label">Popup content (HTML)</label><textarea class="form-control" name="popup_html" rows="3" placeholder="Open Mon–Fri 8am–6pm"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit" disabled id="marker-add-go">Add marker</button></div>
      </form>
    </div></div>
  </div>

  <script>
  (() => {
    const stage = document.getElementById('map-stage')
    const markers = stage.querySelectorAll('.fp-marker-dot')
    const fields = document.getElementById('marker-fields')
    let current = null

    const escapeHtml = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    const escapeAttr = (s) => Array.from(String(s ?? '')).map((c) => escapeHtml(c)).join('').replace(/'/g,'&#39;')

    const renderFields = (dot) => {
      const hidden = (k) => dot.querySelector(`.${k}`)?.value || ''
      const popT = dot.querySelector('input[name$="popup_title"]')?.value || ''
      const popH = dot.querySelector('input[name$="popup_html"]')?.value || ''
      fields.innerHTML = `
        <div class="row g-3 align-items-end">
          <div class="col-md-3"><label class="form-label">Label</label>
            <input class="form-control mk-in-label" value="${escapeAttr(dot.dataset.name)}" oninput="syncField(this,'label')"></div>
          <div class="col-md-3"><label class="form-label">Popup title</label>
            <input class="form-control" value="${escapeAttr(popT)}" oninput="syncField(this,'popup_title')"></div>
          <div class="col-md-6"><label class="form-label">Popup content</label>
            <input class="form-control" value="${escapeAttr(popH)}" oninput="syncField(this,'popup_html')"></div>
        </div>
        <div class="row g-3 mt-0">
          <div class="col-md-4"><label class="form-label">Position X%</label><input class="form-control mk-in-x" value="${hidden('mk-x')}" oninput="syncPos(this,'x')"></div>
          <div class="col-md-4"><label class="form-label">Position Y%</label><input class="form-control mk-in-y" value="${hidden('mk-y')}" oninput="syncPos(this,'y')"></div>
          <div class="col-md-4 d-flex align-items-end"><button type="button" class="btn btn-outline-ia text-danger w-100" onclick="deleteMarker()">Delete this marker</button></div>
        </div>
        <p class="mt-2 mb-0" style="font-size:12.5px;color:var(--ia-muted)">Percentages keep pins aligned on every device — this is the responsive source of truth.</p>`
      current = dot
    }
    window.syncField = (el, key) => {
      if (!current) return
      const box = current
      if (key === 'label') {
        const hiddenEl = box.querySelector('input[name$="][label]"]')
        if (hiddenEl) hiddenEl.value = el.value
        box.dataset.name = el.value
        const chip = document.querySelector(`[data-select-dot="${box.dataset.id}"]`)
        if (chip) chip.lastChild.textContent = el.value
      } else {
        const name = key === 'popup_title' ? 'markers[' + box.dataset.id + ']' : null
        const hiddenEl = box.querySelector(`input[name$="${key}"]`)
        if (hiddenEl) hiddenEl.value = el.value
      }
    }
    window.syncPos = (el, axis) => {
      if (!current) return
      const v = Math.max(0, Math.min(100, parseFloat(el.value) || 0))
      const hiddenEl = current.querySelector(`.mk-${axis}`)
      if (hiddenEl) hiddenEl.value = v
      current.style[axis === 'x' ? 'left' : 'top'] = v + '%'
      if (axis === 'x') current.querySelector('.mk-in-x') && (current.querySelector('.mk-in-x').value = v)
      else current.querySelector('.mk-in-y') && (current.querySelector('.mk-in-y').value = v)
    }

    // delete selected marker (removed from stage + flagged for delete on save)
    window.deleteMarker = () => {
      if (!current) return
      if (!confirm(`Delete marker "${current.dataset.name}"?`)) return
      const id = current.dataset.id
      const del = document.querySelector('input[name="marker_delete"]') || (() => {
        const el = document.createElement('input')
        el.type = 'hidden'; el.name = 'marker_delete'; el.value = ''
        document.getElementById('map-stage').appendChild(el)
        return el
      })()
      del.value = id
      current.remove()
      const chip = document.querySelector(`[data-select-dot="${id}"]`)
      if (chip) chip.remove()
      current = null
      fields.innerHTML = '<p class="text-muted mb-0" style="font-size:13px">Marker deleted. Save to commit.</p>'
    }

    // drag handlers
    markers.forEach((dot) => {
      dot.addEventListener('pointerdown', (e) => {
        e.preventDefault()
        dot.releasePointerCapture && dot.releasePointerCapture(e.pointerId)
        dot.setPointerCapture(e.pointerId)
        const move = (ev) => {
          const rect = stage.getBoundingClientRect()
          const x = Math.max(0, Math.min(100, ((ev.clientX - rect.left) / rect.width) * 100))
          const y = Math.max(0, Math.min(100, ((ev.clientY - rect.top) / rect.height) * 100))
          dot.querySelector('.mk-x').value = x.toFixed(4)
          dot.querySelector('.mk-y').value = y.toFixed(4)
          dot.style.left = x + '%'
          dot.style.top = y + '%'
        }
        move(e)
        const up = () => {
          dot.removeEventListener('pointermove', move)
          dot.removeEventListener('pointerup', up)
          if (current === dot) renderFields(dot)
        }
        dot.addEventListener('pointermove', move)
        dot.addEventListener('pointerup', up)
        renderFields(dot)
      })
      dot.addEventListener('click', (e) => { if (e.detail < 12) renderFields(dot) })
    })

    document.querySelectorAll('[data-select-dot]').forEach((chip) => {
      chip.addEventListener('click', () => {
        const id = chip.dataset.selectDot
        const dot = stage.querySelector(`.fp-marker-dot[data-id="${id}"]`)
        if (dot) renderFields(dot)
      })
    })

    // place new marker at 50/50 by default
    window.placeNewMarker = () => { document.querySelector('#marker-modal input[name="x"]').value = 50 }
    document.getElementById('marker-modal')?.addEventListener('shown.bs.modal', () => {
      document.getElementById('marker-add-go').disabled = false
    })
  })()
  </script>

<?php else: ?>
  <?php if ($filterBuildingId):
    $filterBuilding = null;
    foreach ($buildings->fetchAll() as $bb) { if ((int)$bb['id'] === $filterBuildingId) { $filterBuilding = $bb; break; } }
    $buildings->execute([$iid]); // re-run for modal
  endif; ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <?php if ($filterBuildingId): ?>
        <a class="back-link" href="buildings">← Buildings</a>
        <div style="font-size:13px;color:var(--ia-muted);margin-top:2px">
          <?= ia_icon('building', 13) ?>
          Showing floor plans — assign markers to
          <strong><?= h($filterBuilding['name'] ?? 'Building #'.$filterBuildingId) ?></strong>
          using the <em>Studio</em> editor
        </div>
      <?php else: ?>
        <p class="mb-0" style="color:var(--ia-muted);font-size:13.5px"><?= $plans->rowCount() ?> floor plan(s)</p>
      <?php endif ?>
    </div>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#fp-upload"><?= ia_icon('image', 16) ?> Upload floor plan</button>
  </div>


  <div class="ia-card">
    <table class="table table-ia">
      <thead><tr><th>Plan</th><th>Image</th><th>Markers</th><th>Landing</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($plans as $plan): ?>
          <tr>
            <td>
              <div style="font-weight:700"><?= h($plan['title']) ?></div>
              <div style="font-size:12px;color:var(--ia-muted)"><?= (int) $plan['original_width'] ?>×<?= (int) $plan['original_height'] ?> · aspect <?= round((float) $plan['aspect_ratio'], 3) ?></div>
            </td>
            <td><img src="<?= h(org_url($inst['slug'], $plan['image_path'])) ?>" style="width:120px;height:60px;object-fit:contain;border-radius:8px;border:1px solid var(--ia-border)" alt=""></td>
            <td style="color:var(--ia-muted)"><?= (int) $plan['marker_count'] ?></td>
            <td>
              <?php if ($plan['is_start']): ?><span class="badge badge-live">landing</span>
              <?php else: ?><span class="badge badge-draft">—</span><?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-grad" href="floor-plans?studio=<?= (int) $plan['id'] ?>"><?= ia_icon('map', 13) ?> Studio</a>
                <form method="post" class="d-inline">
                  <input type="hidden" name="fp_action" value="landing"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia" title="Set as landing"><?= ia_icon('rocket', 13) ?></button>
                </form>
                <form method="post" class="d-inline" data-delete-form data-confirm="Delete floor plan '<?= h($plan['title']) ?>'?">
                  <input type="hidden" name="fp_action" value="delete"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($plans->rowCount() === 0): ?>
          <tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No floor plans</h4><p>Upload a campus map, then drop circular markers on top of buildings, gates and landmarks.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- upload modal -->
<div class="modal fade" id="fp-upload" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Upload floor plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="fp_action" value="upload">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Ground Floor Map"></div>
        <div>
          <?php
          $pickerName = 'image';
          $pickerValue = '';
          $pickerLabel = 'Image (PNG/JPG)';
          $pickerHelp = '';
          require __DIR__ . '/../layout/media-picker.php';
          ?>
        </div>
        <div class="form-text">Aspect ratio is locked automatically. Markers are stored as percentages so they stay aligned at any screen size.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Upload</button></div>
    </form>
  </div></div>
</div>

<style>
  .fp-marker-dot { cursor: grab; touch-action: none; }
  .fp-marker-dot::after {
    content: attr(data-name); position: absolute; left: 50%; bottom: 100%; transform: translateX(-50%);
    background: var(--ia-surface); border:1px solid var(--ia-border); color: var(--ia-text);
    padding: 3px 8px; margin-bottom: 6px; border-radius: 8px; font-size: 11px; white-space: nowrap; font-weight:600;
    opacity: 0; pointer-events: none; transition: opacity .15s;
  }
  .fp-marker-dot:hover::after { opacity: 1; }
  .fp-marker-dot::before {
    content: ""; position:absolute; inset:0; border-radius:50%;
    background: radial-gradient(circle at 32% 28%, var(--ia-accent), var(--ia-primary));
    box-shadow: 0 4px 14px rgba(0,0,0,.4), 0 0 0 3px rgba(255,255,255,.85);
  }
  .map-hint { position:absolute; bottom:12px; left:50%; transform:translateX(-50%); font-size:12px; color:#fff; background:rgba(20,22,40,.72); padding:6px 12px; border-radius:999px; backdrop-filter: blur(8px); pointer-events:none; white-space:nowrap; }
</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>