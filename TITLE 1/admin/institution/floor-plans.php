<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.floorplans');
/**
 * Innovatech PH — admin: floor plans + responsive markers (percentage coords).
 * Markers are placed/dragged on an aspect-locked stage so alignment survives any screen.
 */
$pageTitle = 'Floor Plans';
$pageSub = 'Upload the campus map and drop circular markers (drag & drop studio)';
$active = 'Floor Plans';
$bodyClass = 'page-floor-plans';
require_once __DIR__ . '/../layout/header.php';

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

            crud()->insert('floor_plans', ['institution_id' => $iid, 'title' => $title, 'image_path' => $rel, 'original_width' => $w, 'original_height' => $h, 'aspect_ratio' => $w / max(1, $h), 'object_fit' => 'contain', 'created_by' => (int) current_user()['id']]);
            flash('success', 'Floor plan uploaded (aspect ratio locked).');
        }

        if ($action === 'update-image') {
            $id = (int) ($_POST['id'] ?? 0);
            $rel = handle_media_picker('image', trim($inst['folder_path'], '/') . '/assets/floorplans');
            if (!$rel) throw new RuntimeException('Choose a new image.');
            $abs = ROOT_PATH . '/' . ltrim($rel, '/');
            $size = @getimagesize($abs);
            $w = (int) ($size[0] ?? 1200);
            $h = (int) ($size[1] ?? 900);
            crud()->update('floor_plans', [
                'image_path'      => $rel,
                'original_width'  => $w,
                'original_height' => $h,
                'aspect_ratio'    => $w / max(1, $h),
            ], ['id' => $id, 'institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Floor plan image updated.');
        }

        if ($action === 'landing') {
            $id = (int) ($_POST['id'] ?? 0);
            crud()->update('institutions', ['landing_mode' => 'floor_plan', 'starting_scene_id' => null, 'starting_floor_plan_id' => $id], ['id' => $iid]);
            crud()->update('floor_plans', ['is_campus_landing' => 0], ['institution_id' => $iid]);
            crud()->update('floor_plans', ['is_campus_landing' => 1], ['id' => $id, 'institution_id' => $iid]);
            $_SESSION['user']['institution']['landing_mode'] = 'floor_plan';
            $_SESSION['user']['institution']['starting_floor_plan_id'] = $id;
            flash('success', 'This floor plan is now the landing.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $img = crud()->raw("SELECT image_path FROM floor_plans WHERE id=:id AND institution_id=:iid", ['id' => $id, 'iid' => $iid])->fetchColumn();
            crud()->delete('floor_plans', ['id' => $id, 'institution_id' => $iid]);
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
            crud()->insert('floor_plan_markers', [
                'institution_id' => $iid, 'floor_plan_id' => $planId, 'label' => $label,
                'x_percent' => (float) ($_POST['x'] ?? 50), 'y_percent' => (float) ($_POST['y'] ?? 50), 'size_percent' => 4,
                'target_room_id'  => (int) ($_POST['target_room_id'] ?? 0) ?: null,
                'target_building_id'  => (int) ($_POST['target_building_id'] ?? 0) ?: null,
                'target_facility_id'  => (int) ($_POST['target_facility_id'] ?? 0) ?: null,
                'target_scene_id'  => (int) ($_POST['target_scene_id'] ?? 0) ?: null,
                'target_floor_plan_id' => (int) ($_POST['target_floor_plan_id'] ?? 0) ?: null,
                'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                'popup_html' => trim($_POST['popup_html'] ?? '') ?: null, 'sort_order' => 0
            ]);
            sync_institution_config($iid);
            flash('success', 'Marker added. Drag it into place in the studio.');
        }

        if ($action === 'markers-save') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            foreach (($_POST['markers'] ?? []) as $m) {
                $mid = (int) ($m['id'] ?? 0);
                if (!$mid) continue;
                crud()->update('floor_plan_markers', [
                    'x_percent' => max(0, min(100, (float) ($m['x'] ?? 0))),
                    'y_percent' => max(0, min(100, (float) ($m['y'] ?? 0))),
                    'popup_title' => trim($m['popup_title'] ?? '') ?: null,
                    'popup_html' => trim($m['popup_html'] ?? '') ?: null,
                    'label' => trim($m['label'] ?? '') ?: 'Marker',
                ], ['id' => $mid, 'institution_id' => $iid]);
            }
            if (isset($_POST['marker_delete']) && $_POST['marker_delete'] !== '') {
                crud()->delete('floor_plan_markers', ['id' => (int) $_POST['marker_delete'], 'institution_id' => $iid]);
            }
            sync_institution_config($iid);
            flash('success', 'Marker positions saved.');
        }

        if ($action === 'marker-delete') {
            crud()->delete('floor_plan_markers', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
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
$plans = crud()->raw($plansQuery . ' ORDER BY fp.created_at DESC', $plansParams);

// studio data
$studio = null;
$markers = [];
if ($studioPlanId) {
    $studio = crud()->raw("SELECT * FROM floor_plans WHERE id=:id AND institution_id=:iid", ['id' => $studioPlanId, 'iid' => $iid])->fetch() ?: null;
    if ($studio) {
        $markers = crud()->raw("SELECT * FROM floor_plan_markers WHERE floor_plan_id=? AND institution_id=? ORDER BY sort_order, id", [$studioPlanId, $iid])->fetchAll();
    }
}

$buildings = crud()->raw("SELECT id,name FROM buildings WHERE institution_id=? AND deleted_at IS NULL ORDER BY name", [$iid]);
$rooms = crud()->raw("SELECT id,name FROM rooms WHERE institution_id=? AND deleted_at IS NULL ORDER BY name", [$iid]);
$scenes = crud()->raw("SELECT id,title as name FROM tour_scenes WHERE institution_id=? AND deleted_at IS NULL ORDER BY title", [$iid]);
$floorPlansList = crud()->raw("SELECT id,title as name FROM floor_plans WHERE institution_id=? AND deleted_at IS NULL AND id!=? ORDER BY title", [$iid, $studioPlanId ?: 0]);
?>

<?php if ($studio): ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <a class="back-link" href="floor-plans">← All floor plans</a>
      <div class="d-flex align-items-center gap-2 mt-1">
        <h3 class="mb-0 fw-800"><?= h($studio['title']) ?> — marker studio</h3>
        <?php if ($studio['is_campus_landing']): ?><span class="badge badge-live">landing</span><?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-ia btn-sm" data-bs-toggle="modal" data-bs-target="#fp-update-image-modal"><?= ia_icon('image', 14) ?> Change image</button>
      <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#marker-modal"><?= ia_icon('map', 16) ?> Add marker</button>
    </div>
  </div>

  <!-- update image modal -->
  <div class="modal fade" id="fp-update-image-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Change floor plan image</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="fp_action" value="update-image">
        <input type="hidden" name="id" value="<?= (int) $studio['id'] ?>">
        <div class="modal-body d-grid gap-3">
          <?php
          $pickerName  = 'image';
          $pickerValue = $studio['image_path'] ?? '';
          $pickerLabel = 'New Image (PNG/JPG)';
          $pickerHelp  = 'Aspect ratio and marker positions are preserved. Only the image file is replaced.';
          require __DIR__ . '/../layout/media-picker.php';
          ?>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Update image</button></div>
      </form>
    </div></div>
  </div>

  <form method="post">
    <input type="hidden" name="fp_action" value="markers-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <div class="ia-card p-3">
      <!-- aspect-locked stage: markers stay aligned on every screen -->
      <div id="map-stage" class="map-stage"
           style="--fp-ar: <?= $studio['aspect_ratio'] ?>">
        <img src="<?= h(media_url($studio['image_path'])) ?>" alt="floor plan"
             class="map-stage-img">
        <input type="hidden" name="markers-hash" id="markers-hash">

        <?php foreach ($markers as $mkIdx => $mk): ?>
          <div class="fp-marker-dot" data-id="<?= (int) $mk['id'] ?>"
               data-name="<?= h($mk['label'], ENT_QUOTES) ?>"
               style="left:<?= (float) $mk['x_percent'] ?>%;top:<?= (float) $mk['y_percent'] ?>%">
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

        <div class="fp-hint">Drag the dots to position markers. Click a dot to edit its label/popup. Save when done.</div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="d-flex gap-2 flex-wrap" id="marker-list">
          <?php foreach ($markers as $mk): ?>
            <button type="button" class="btn btn-sm btn-outline-ia" data-select-dot="<?= (int) $mk['id'] ?>">
              <span class="chip-dot"></span><?= h($mk['label']) ?>
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
      <p class="ia-meta-md mb-0">Select a marker dot or a chip above to edit its label and popup.</p>
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
  window._fpScenes = <?= json_encode(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], iterator_to_array($scenes)), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpPlans  = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], iterator_to_array($floorPlansList)), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script>
  (() => {
    const stage = document.getElementById('map-stage')
    const markers = stage.querySelectorAll('.fp-marker-dot')
    const fields = document.getElementById('marker-fields')
    let current = null

    const escapeHtml = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    const escapeAttr = (s) => Array.from(String(s ?? '')).map((c) => escapeHtml(c)).join('').replace(/'/g,'&#39;')

    const renderFields = (dot) => {
      const hv = (sel) => dot.querySelector(sel)?.value || ''
      const popT    = hv('input[name$="[popup_title]"]')
      const popH    = hv('input[name$="[popup_html]"]')
      const sceneId = hv('input[name$="[target_scene_id]"]')
      const fpId    = hv('input[name$="[target_floor_plan_id]"]')
      const bldId   = hv('input[name$="[target_building_id]"]')
      const roomId  = hv('input[name$="[target_room_id]"]')

      // Determine current click-action mode
      let mode = 'popup'
      if (sceneId && sceneId !== '0') mode = 'scene'
      else if (fpId && fpId !== '0') mode = 'floorplan'

      fields.innerHTML = `
        <div class="row g-3 mb-3">
          <div class="col-md-4"><label class="form-label fw-semibold">Label</label>
            <input class="form-control" value="${escapeAttr(dot.dataset.name)}" oninput="syncField(this,'label')"></div>
          <div class="col-md-4"><label class="form-label fw-semibold">Popup title</label>
            <input class="form-control" value="${escapeAttr(popT)}" oninput="syncField(this,'popup_title')"></div>
          <div class="col-md-4"><label class="form-label fw-semibold">Position</label>
            <div class="d-flex gap-2">
              <input class="form-control mk-in-x" placeholder="X%" value="${hv('.mk-x')}" oninput="syncPos(this,'x')">
              <input class="form-control mk-in-y" placeholder="Y%" value="${hv('.mk-y')}" oninput="syncPos(this,'y')">
            </div>
          </div>
        </div>
        <p class="form-label form-label-sm fw-semibold mb-2">Click action — what happens when visitor taps this marker?</p>
        <div class="d-flex gap-2 flex-wrap mb-3" id="mk-mode-tabs">
          <button type="button" class="btn btn-sm ${mode==='popup'?'btn-grad':'btn-outline-ia'}" data-mk-mode="popup">💬 Popup text</button>
          <button type="button" class="btn btn-sm ${mode==='scene'?'btn-grad':'btn-outline-ia'}" data-mk-mode="scene">🎥 360 Tour</button>
          <button type="button" class="btn btn-sm ${mode==='floorplan'?'btn-grad':'btn-outline-ia'}" data-mk-mode="floorplan">🗺 Sub-Floor Plan</button>
        </div>
        <div id="mk-panel-popup" class="mk-panel ${mode==='popup'?'':'d-none'}">
          <label class="form-label form-label-sm">Popup content (HTML or plain text)</label>
          <textarea class="form-control" rows="3" oninput="syncField(this,'popup_html')">${escapeHtml(popH)}</textarea>
        </div>
        <div id="mk-panel-scene" class="mk-panel ${mode==='scene'?'':'d-none'}">
          <label class="form-label form-label-sm">Link to 360 Tour scene</label>
          <select class="form-select" onchange="syncField(this,'target_scene_id')">
            <option value="">— none —</option>
            ${window._fpScenes.map(s=>`<option value="${s.id}" ${sceneId==s.id?'selected':''}>${escapeHtml(s.name)}</option>`).join('')}
          </select>
        </div>
        <div id="mk-panel-floorplan" class="mk-panel ${mode==='floorplan'?'':'d-none'}">
          <label class="form-label form-label-sm">Link to Sub-Floor Plan</label>
          <select class="form-select" onchange="syncField(this,'target_floor_plan_id')">
            <option value="">— none —</option>
            ${window._fpPlans.map(p=>`<option value="${p.id}" ${fpId==p.id?'selected':''}>${escapeHtml(p.name)}</option>`).join('')}
          </select>
        </div>
        <div class="mt-3 d-flex justify-content-end">
          <button type="button" class="btn btn-outline-ia btn-sm text-danger" onclick="deleteMarker()">Delete this marker</button>
        </div>
        <p class="mt-2 mb-0 ia-micro">Percentages keep pins aligned on every device — this is the responsive source of truth.</p>`

      // mode tab switcher
      fields.querySelectorAll('[data-mk-mode]').forEach(btn => {
        btn.addEventListener('click', () => {
          const m = btn.dataset.mkMode
          fields.querySelectorAll('[data-mk-mode]').forEach(b => b.className = b.className.replace('btn-grad','btn-outline-ia'))
          btn.className = btn.className.replace('btn-outline-ia','btn-grad')
          fields.querySelectorAll('[id^="mk-panel-"]').forEach(p => p.classList.add('d-none'))
          fields.querySelector(`#mk-panel-${m}`).classList.remove('d-none')
          // clear other targets when switching mode
          if (m !== 'scene') syncField({value:''}, 'target_scene_id')
          if (m !== 'floorplan') syncField({value:''}, 'target_floor_plan_id')
        })
      })

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
        const hiddenEl = box.querySelector(`input[name$="[${key}]"]`) || box.querySelector(`input[name$="${key}"]`)
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
    window.deleteMarker = async () => {
      if (!current) return
      const ok = await window.iaConfirm(`Delete marker "${current.dataset.name}"?`, 'Delete marker')
      if (!ok) return
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
      fields.innerHTML = '<div class="ia-meta-md mb-0">Marker deleted. Save to commit.</div>'
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
        <div class="ia-meta-md mt-1">
          <?= ia_icon('building', 13) ?>
          Showing floor plans — assign markers to
          <strong><?= h($filterBuilding['name'] ?? 'Building #'.$filterBuildingId) ?></strong>
          using the <em>Studio</em> editor
        </div>
      <?php else: ?>
        <p class="mb-0 ia-meta-lg"><?= $plans->rowCount() ?> floor plan(s)</p>
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
              <div class="fw-bold"><?= h($plan['title']) ?></div>
              <div class="ia-meta-sm"><?= (int) $plan['original_width'] ?>×<?= (int) $plan['original_height'] ?> · aspect <?= round((float) $plan['aspect_ratio'], 3) ?></div>
            </td>
            <td><img src="<?= h(media_url($plan['image_path'])) ?>" class="plan-thumb" alt=""></td>
            <td class="text-ia-muted"><?= (int) $plan['marker_count'] ?></td>
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

<?php require __DIR__ . '/../layout/footer.php'; ?>