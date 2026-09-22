<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.tours');
/**
 * Innovatech PH — admin: 360 Tour Hotspot Studio.
 * Visual placement of info/navigation hotspots on a 360 scene using A-Frame.
 */
$pageTitle = 'Tour Studio';
$pageSub   = 'Place interactive hotspots on your 360 scene';
$active    = '360 Tours';
$bodyClass = 'page-tour-studio';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid  = (int) $inst['id'];

$sceneId = (int) ($_GET['scene'] ?? 0);
if (!$sceneId) { flash('error', 'No scene selected.'); redirect('admin/institution/tours'); }

$scene = crud()->raw('SELECT * FROM tour_scenes WHERE id=:id AND institution_id=:iid AND deleted_at IS NULL LIMIT 1', ['id' => $sceneId, 'iid' => $iid])->fetch();
if (!$scene) { flash('error', 'Scene not found.'); redirect('admin/institution/tours'); }

// ── handle actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['hs_action'] ?? '';
    try {
        if ($action === 'add') {
            $mediaPath = handle_media_picker('media_path', trim($inst['folder_path'], '/') . '/assets/hotspots') ?: trim($_POST['media_path'] ?? '');
            crud()->insert('scene_hotspots', [
                'institution_id' => $iid, 'from_scene_id' => $sceneId, 'to_scene_id' => (int) ($_POST['to_scene_id'] ?? 0) ?: null,
                'hotspot_type' => in_array($_POST['hotspot_type'] ?? '', ['navigation','info','facility','media']) ? $_POST['hotspot_type'] : 'info',
                'label' => trim($_POST['label'] ?? '') ?: 'Info', 'body_html' => trim($_POST['body_html'] ?? '') ?: null,
                'yaw' => (float) ($_POST['yaw'] ?? 0), 'pitch' => (float) ($_POST['pitch'] ?? 0),
                'media_path' => $mediaPath ?: null
            ]);
            flash('success', 'Hotspot added.');
        }
        if ($action === 'edit') {
            $mediaPath = handle_media_picker('media_path', trim($inst['folder_path'], '/') . '/assets/hotspots') ?: trim($_POST['media_path'] ?? '');
            crud()->update('scene_hotspots', [
                'label' => trim($_POST['label'] ?? '') ?: 'Info',
                'body_html' => trim($_POST['body_html'] ?? '') ?: null,
                'yaw' => (float) ($_POST['yaw'] ?? 0), 'pitch' => (float) ($_POST['pitch'] ?? 0),
                'to_scene_id' => (int) ($_POST['to_scene_id'] ?? 0) ?: null,
                'hotspot_type' => in_array($_POST['hotspot_type'] ?? '', ['navigation','info','facility','media']) ? $_POST['hotspot_type'] : 'info',
                'media_path' => $mediaPath ?: null
            ], ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            flash('success', 'Hotspot updated.');
        }
        if ($action === 'delete') {
            crud()->delete('scene_hotspots', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            flash('success', 'Hotspot removed.');
        }
        if ($action === 'set_start') {
            crud()->update('tour_scenes', [
                'initial_yaw' => (float) ($_POST['yaw'] ?? 0),
                'initial_pitch' => (float) ($_POST['pitch'] ?? 0),
            ], ['id' => $sceneId, 'institution_id' => $iid]);
            flash('success', 'Starting viewpoint saved.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/tour-studio?scene=' . $sceneId);
}

// Load hotspots
$hotspots = crud()->raw(
    'SELECT sh.*, ts.title AS to_scene_title FROM scene_hotspots sh LEFT JOIN tour_scenes ts ON ts.id = sh.to_scene_id WHERE sh.from_scene_id=:sid AND sh.institution_id=:iid ORDER BY sh.id',
    ['sid' => $sceneId, 'iid' => $iid]
)->fetchAll();

$otherScenes = crud()->raw(
    'SELECT id, title FROM tour_scenes WHERE institution_id=:iid AND id!=:sid AND deleted_at IS NULL ORDER BY title',
    ['iid' => $iid, 'sid' => $sceneId]
)->fetchAll();

$equirectUrl = $scene['equirect_path'] ? media_url($scene['equirect_path']) : '';
$hotspotsJson = json_encode($hotspots, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
?>
<div class="d-flex align-items-center gap-3 mb-3">
  <a class="back-link" href="tours">← All scenes</a>
  <div>
    <h3 class="fw-800 mb-0"><?= h($scene['title']) ?> — Hotspot Studio</h3>
    <p class="mb-0 ia-meta-md">Click anywhere in the 360 view to place a hotspot.</p>
  </div>
  <form method="post" id="form-set-start" class="d-none">
    <input type="hidden" name="hs_action" value="set_start">
    <input type="hidden" name="yaw" id="start-yaw" value="0">
    <input type="hidden" name="pitch" id="start-pitch" value="0">
  </form>
  <button class="btn btn-outline-ia btn-sm ms-auto" id="btn-set-start"><?= ia_icon('camera', 13) ?> Set as Starting Point</button>
  <button class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#hs-modal" data-mode="add">+ Add hotspot</button>
</div>

<?php if (!$equirectUrl): ?>
  <div class="ia-card">
    <div class="card-body empty-state">
      <div class="empty-icon"><?= ia_icon('camera', 32) ?></div>
      <h4>No panorama uploaded</h4>
      <p>Go back and upload an equirectangular image for this scene first.</p>
      <a class="btn btn-grad btn-sm" href="tours">Upload panorama</a>
    </div>
  </div>
<?php else: ?>

<div class="row g-4">
  <!-- ═══ 360 VIEWER ═══ -->
  <div class="col-xl-8">
    <div class="ia-card">
      <div class="card-head"><h3>360° Viewer</h3><span class="ia-meta-sm">Click a hotspot to select · Double-click viewer to place new</span></div>
      <div class="studio-frame">
        <div id="studio-viewer">
          <a-scene id="a-studio" embedded vr-mode-ui="enabled:false" renderer="antialias:true">
            <a-assets>
              <img id="studio-pano" src="<?= h($equirectUrl) ?>" crossorigin="anonymous">
            </a-assets>
            <a-sky id="studio-sky" src="#studio-pano"></a-sky>
            <!-- hotspots rendered by JS -->
            <a-entity id="camera-rig">
              <a-camera position="0 0 0" look-controls wasd-controls="enabled:false" id="studio-cam"></a-camera>
            </a-entity>
          </a-scene>
        </div>
        <div id="studio-crosshair">
          <i class="fa-solid fa-crosshairs text-muted" style="font-size: 20px;"></i>
        </div>
        <div class="studio-hint">
          Drag to look · Double-click to place hotspot at crosshair
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ HOTSPOT LIST ═══ -->
  <div class="col-xl-4">
    <div class="ia-card h-100">
      <div class="card-head"><h3>Hotspots <span class="badge badge-surface"><?= count($hotspots) ?></span></h3></div>
      <?php if ($hotspots): ?>
        <div class="hots-list">
          <?php foreach ($hotspots as $hs): ?>
            <div class="hotspot-row d-flex align-items-start gap-3 px-3 py-2 divider-bottom" style="cursor: pointer;" id="hsrow-<?= (int)$hs['id'] ?>" data-yaw="<?= (float)$hs['yaw'] ?>" data-pitch="<?= (float)$hs['pitch'] ?>">
              <div class="hotspot-icon-badge type-<?= h($hs['hotspot_type']) ?>">
                <?= ia_icon($hs['hotspot_type'] === 'navigation' ? 'arrow' : ($hs['hotspot_type'] === 'info' ? 'info' : 'camera'), 14) ?>
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-bold fs-135"><?= h($hs['label']) ?></div>
                <div class="ia-micro">
                  Yaw <?= round((float)$hs['yaw'], 1) ?>° · Pitch <?= round((float)$hs['pitch'], 1) ?>°
                  <?php if ($hs['to_scene_title']): ?> · → <?= h($hs['to_scene_title']) ?><?php endif ?>
                </div>
              </div>
              <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#hs-modal"
                  data-mode="edit"
                  data-id="<?= (int)$hs['id'] ?>"
                  data-label="<?= h($hs['label'], ENT_QUOTES) ?>"
                  data-type="<?= h($hs['hotspot_type']) ?>"
                  data-to="<?= (int)$hs['to_scene_id'] ?>"
                  data-yaw="<?= (float)$hs['yaw'] ?>"
                  data-pitch="<?= (float)$hs['pitch'] ?>"
                  data-media="<?= h($hs['media_path'] ?? '', ENT_QUOTES) ?>"
                  data-body="<?= h($hs['body_html'] ?? '', ENT_QUOTES) ?>"><?= ia_icon('file', 12) ?></button>
                <form method="post" class="d-inline" data-delete-form data-confirm="Delete hotspot '<?= h($hs['label']) ?>'?">
                  <input type="hidden" name="hs_action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$hs['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 12) ?></button>
                </form>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <div class="empty-state empty-state-lg">
          <div class="empty-icon"><?= ia_icon('info', 24) ?></div>
          <h4>No hotspots yet</h4>
          <p>Double-click the viewer or click "Add hotspot" to place your first one.</p>
        </div>
      <?php endif ?>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- ══════════ ADD / EDIT MODAL ══════════ -->
<div class="modal fade" id="hs-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Hotspot</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="hs_action" value="add" id="hs-action">
        <input type="hidden" name="id" value="0" id="hs-id">
        <div class="modal-body d-grid gap-3">
          <div>
            <label class="form-label">Label</label>
            <input class="form-control" name="label" id="hs-label" required placeholder="Library Entrance">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Type</label>
              <select class="form-select" name="hotspot_type" id="hs-type">
                <option value="info">Info</option>
                <option value="navigation">Navigation (→ scene)</option>
                <option value="media">Media</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Yaw °</label>
              <input type="number" step="0.1" class="form-control" name="yaw" id="hs-yaw" value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Pitch °</label>
              <input type="number" step="0.1" class="form-control" name="pitch" id="hs-pitch" value="0">
            </div>
          </div>
          <div id="hs-scene-wrap">
            <label class="form-label">Navigate to scene</label>
            <select class="form-select" name="to_scene_id" id="hs-to">
              <option value="">— same scene (info only) —</option>
              <?php foreach ($otherScenes as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= h($s['title']) ?></option>
              <?php endforeach ?>
            </select>
            <div class="form-text">Only applies to Navigation type hotspots.</div>
          </div>
          <div id="hs-media-wrap">
            <?php
            $pickerName = 'media_path';
            $pickerValue = '';
            $pickerLabel = 'Media Image';
            $pickerHelp = 'Image to display for Media type hotspots.';
            require __DIR__ . '/../layout/media-picker-sweetalert.php';
            ?>
          </div>
          <div id="hs-info-wrap">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label mb-1">Popup body (HTML allowed)</label>
              <button type="button" class="btn btn-sm btn-outline-ia" data-ai-gen data-ai-type="hotspot" data-ai-id-el="hs-id" data-ai-target-el="hs-body"><?= ia_icon('wand', 13) ?> Generate AI</button>
            </div>
            <textarea class="form-control" name="body_html" id="hs-body" rows="4" placeholder="Opening hours, description, link…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-grad px-4" type="submit"><?= ia_icon('save', 14) ?> Save hotspot</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?= url('/organizations/' . $inst['slug'] . '/assets/aframe.min.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const hotspots  = <?= $hotspotsJson ?>;
  const sceneId   = <?= $sceneId ?>;
  const initialYaw   = <?= (float) ($scene['initial_yaw'] ?? 0) ?>;
  const initialPitch = <?= (float) ($scene['initial_pitch'] ?? 0) ?>;
  const aScene    = document.getElementById('a-studio');
  const aSky      = document.getElementById('studio-sky');

  // ── render existing hotspots as A-Frame entities ──
  function buildHotspots() {
    document.querySelectorAll('.studio-hs').forEach(e => e.remove());
    hotspots.forEach(hs => {
      const entity = document.createElement('a-entity');
      entity.className = 'studio-hs';
      entity.setAttribute('position', yawPitchToXYZ(hs.yaw, hs.pitch, 8));
      entity.innerHTML = `
        <a-sphere radius="0.28" color="${hs.hotspot_type === 'navigation' ? '#5b5bd6' : '#38b2ac'}" opacity="0.92"
          class="clickable"
          data-hs-id="${hs.id}"
          event-set__mouseenter="scale: 1.3 1.3 1.3"
          event-set__mouseleave="scale: 1 1 1">
        </a-sphere>
        <a-text value="${escapeAframe(hs.label)}" align="center" position="0 0.42 0" color="#fff" width="3" wrap-count="20"></a-text>
      `;
      aScene.appendChild(entity);
    });
  }

  function escapeAframe(s) { return (s || '').replace(/"/g, '&quot;'); }

  // convert spherical coords to Cartesian on a sphere of radius r
  function yawPitchToXYZ(yawDeg, pitchDeg, r) {
    const y = (yawDeg  || 0) * Math.PI / 180;
    const p = (pitchDeg || 0) * Math.PI / 180;
    const x = r * Math.cos(p) * Math.sin(y);
    const z = -r * Math.cos(p) * Math.cos(y);
    const vY = r * Math.sin(p);
    return `${x.toFixed(4)} ${vY.toFixed(4)} ${z.toFixed(4)}`;
  }

  // ── get current yaw/pitch from camera ──
  function getCameraLook() {
    const cam = document.getElementById('studio-cam');
    if (!cam) return { yaw: 0, pitch: 0 };
    const lc = cam.components['look-controls'];
    if (lc) {
      return {
        yaw: -(lc.yawObject.rotation.y * 180 / Math.PI),
        pitch: lc.pitchObject.rotation.x * 180 / Math.PI
      };
    }
    const rot = cam.getAttribute('rotation');
    return { yaw: -(rot.y || 0), pitch: (rot.x || 0) };
  }

  // ── double-click viewer → pre-fill modal at current look direction ──
  const viewer = document.getElementById('studio-viewer');
  viewer.addEventListener('dblclick', () => {
    const look = getCameraLook();
    document.getElementById('hs-action').value = 'add';
    document.getElementById('hs-id').value = 0;
    document.getElementById('hs-label').value = '';
    document.getElementById('hs-yaw').value = look.yaw.toFixed(1);
    document.getElementById('hs-pitch').value = look.pitch.toFixed(1);
    document.getElementById('hs-body').value = '';
    document.getElementById('hs-to').value = '';
    document.getElementById('hs-type').value = 'info';
    const modal = new bootstrap.Modal(document.getElementById('hs-modal'));
    modal.show();
  });

  const hsTypeSelect = document.getElementById('hs-type');
  function toggleModalFields() {
    const t = hsTypeSelect.value;
    document.getElementById('hs-scene-wrap').style.display = t === 'navigation' ? 'block' : 'none';
    document.getElementById('hs-media-wrap').style.display = t === 'media' ? 'block' : 'none';
    const infoWrap = document.getElementById('hs-info-wrap');
    if (infoWrap) infoWrap.style.display = t === 'info' ? 'block' : 'none';
  }
  hsTypeSelect.addEventListener('change', toggleModalFields);

  // ── modal wiring ──
  document.getElementById('hs-modal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    if (!btn) return;
    const mode = btn.dataset.mode || 'add';
    document.getElementById('hs-action').value = mode;
    document.getElementById('hs-id').value    = btn.dataset.id || 0;
    document.getElementById('hs-label').value = btn.dataset.label || '';
    document.getElementById('hs-type').value  = btn.dataset.type || 'info';
    document.getElementById('hs-to').value    = btn.dataset.to || '';
    document.getElementById('hs-yaw').value   = btn.dataset.yaw || 0;
    document.getElementById('hs-pitch').value = btn.dataset.pitch || 0;
    document.getElementById('hs-body').value  = btn.dataset.body || '';
    document.getElementById('hs-modal').querySelector('.modal-title').textContent = mode === 'edit' ? 'Edit hotspot' : 'Add hotspot';

    const mediaWrap = document.getElementById('wrap_picker_media_path');
    if (mediaWrap) {
      if (mode === 'edit' && btn.dataset.media) {
        if (typeof setMediaPicker === 'function') {
          setMediaPicker(mediaWrap.id, btn.dataset.media, btn.dataset.media.split('/').pop());
        }
      } else {
        if (typeof clearMediaPicker === 'function') {
          clearMediaPicker('picker_media_path');
        }
      }
    }
    
    // update dynamic fields visibility
    toggleModalFields();
  });

  // ── "Set as Starting Point" top button ──
  document.getElementById('btn-set-start')?.addEventListener('click', () => {
    const look = getCameraLook();
    document.getElementById('start-yaw').value = look.yaw.toFixed(2);
    document.getElementById('start-pitch').value = look.pitch.toFixed(2);
    document.getElementById('form-set-start').submit();
  });

  // ── "Add hotspot" top button: use current camera direction ──
  document.querySelector('[data-mode="add"]')?.addEventListener('click', () => {
    if (!aScene.hasLoaded) return;
    const look = getCameraLook();
    document.getElementById('hs-yaw').value   = look.yaw.toFixed(1);
    document.getElementById('hs-pitch').value = look.pitch.toFixed(1);
  });

  // Face the configured initial heading so the camera looks the direction set in Tours.
  function applyInitialHeading() {
    const cam = document.getElementById('studio-cam');
    if (!cam) return;
    const lc = cam.components['look-controls'];
    if (lc) {
      lc.pitchObject.rotation.x = (initialPitch || 0) * Math.PI / 180;
      lc.yawObject.rotation.y = -(initialYaw || 0) * Math.PI / 180;
    } else {
      const rig = document.getElementById('camera-rig');
      if (rig) rig.setAttribute('rotation', `0 ${initialYaw} 0`);
      if (initialPitch) cam.setAttribute('rotation', `${initialPitch} 0 0`);
    }
  }

  // Build after A-Frame is ready
  aScene.addEventListener('loaded', () => {
    applyInitialHeading();
    buildHotspots();
  });
  if (aScene.hasLoaded) {
    applyInitialHeading();
    buildHotspots();
  }

  // Center camera when clicking hotspot row
  document.querySelectorAll('.hotspot-row').forEach(row => {
    row.addEventListener('click', e => {
      if (e.target.closest('button') || e.target.closest('a')) return;
      const cam = document.getElementById('studio-cam');
      const lc = cam && cam.components['look-controls'];
      if (lc) {
        lc.pitchObject.rotation.x = parseFloat(row.dataset.pitch || 0) * Math.PI / 180;
        lc.yawObject.rotation.y = -parseFloat(row.dataset.yaw || 0) * Math.PI / 180;
      }
    });
  });

  // ── Live Yaw/Pitch Indicator ──
  const viewerWrap = document.getElementById('studio-viewer');
  if (viewerWrap) {
    const liveCoords = document.createElement('div');
    liveCoords.style.cssText = 'position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: #fff; padding: 4px 8px; border-radius: 4px; font-family: monospace; z-index: 10; font-size: 12px; pointer-events: none;';
    viewerWrap.appendChild(liveCoords);
    
    function updateLiveCoords() {
      const look = getCameraLook();
      liveCoords.textContent = `Yaw: ${look.yaw.toFixed(1)}° | Pitch: ${look.pitch.toFixed(1)}°`;
      requestAnimationFrame(updateLiveCoords);
    }
    updateLiveCoords();
  }
});

function setMediaPicker(wrapId, url, filename) {
  const wrap = document.getElementById(wrapId)
  if (!wrap) return
  const pickerId = wrapId.replace(/^wrap_/, '')
  const fullUrl = url ? (url.startsWith('http') ? url : (window.IA_BASE_URL + '/' + url.replace(/^\/+/, ''))) : ''
  document.getElementById(pickerId + '_url').value = url || ''
  document.getElementById(pickerId + '_label').innerHTML = url
    ? '<span class="text-truncate">' + (filename || url.split('/').pop()) + '</span>'
    : '<span class="text-muted">No media selected</span>'
  document.getElementById(pickerId + '_sub').textContent = url || 'Choose a file or enter a link'
  const preview = document.getElementById(pickerId + '_preview')
  if (preview) {
    preview.innerHTML = url
      ? '<img src="' + fullUrl + '" alt="preview">'
      : '<i class="fa-regular fa-image" style="font-size: 22px; color: #aab2c0;"></i>'
  }
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
