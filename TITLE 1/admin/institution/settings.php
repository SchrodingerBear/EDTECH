<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin', 'staff');
require_page('admin.settings');
/**
 * Innovatech PH — admin: visual environment (landing mode, starting point, theme, popups).
 */
$pageTitle = 'Settings';
$pageSub = 'Landing mode, starting point and the visual theme';
$active = 'Settings';
$bodyClass = 'page-settings';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];

// theme row (lazy-create)
$theme = crud()->get('institution_themes', ['institution_id' => $iid]);
if (!$theme) {
    crud()->insert('institution_themes', ['institution_id' => $iid]);
    $theme = ['primary_color' => '#1a365d', 'secondary_color' => '#ed8936', 'accent_color' => '#38b2ac',
              'font_family' => null, 'popup_animation' => 'fade', 'marker_style' => 'circle',
              'infographic_style' => 'card', 'custom_css' => null, 'theme_json' => null];
}
$themeJson = json_decode($theme['theme_json'] ?? 'null', true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['settings_action'] ?? '';
    try {
        if ($action === 'landing') {
            $mode = ($_POST['landing_mode'] ?? '360_rotation') === 'floor_plan' ? 'floor_plan' : '360_rotation';
            $sceneId = (int) ($_POST['starting_scene_id'] ?? 0) ?: null;
            $planId = (int) ($_POST['starting_floor_plan_id'] ?? 0) ?: null;
            $landscape = isset($_POST['require_landscape_mobile']) ? 1 : 0;

            if ($mode === 'floor_plan' && !$planId) throw new RuntimeException('A starting floor plan is required for floor-plan landing.');
            if ($mode === '360_rotation' && !$sceneId) throw new RuntimeException('A starting 360 scene is required for rotation landing.');

            crud()->update('institutions', [
                'landing_mode' => $mode,
                'starting_scene_id' => $sceneId,
                'starting_floor_plan_id' => $planId,
                'require_landscape_mobile' => $landscape,
            ], ['id' => $iid]);
            crud()->update('tour_scenes', ['is_landing_start' => 0], ['institution_id' => $iid]);
            if ($sceneId) crud()->update('tour_scenes', ['is_landing_start' => 1], ['id' => $sceneId, 'institution_id' => $iid]);
            crud()->update('floor_plans', ['is_campus_landing' => 0], ['institution_id' => $iid]);
            if ($planId) crud()->update('floor_plans', ['is_campus_landing' => 1], ['id' => $planId, 'institution_id' => $iid]);

            // refresh session cache
            foreach (['landing_mode', 'starting_scene_id', 'starting_floor_plan_id'] as $c) {
                $_SESSION['user']['institution'][$c] = $c === 'landing_mode' ? $mode : ($c === 'starting_scene_id' ? $sceneId : $planId);
            }
            sync_institution_config($iid);
            flash('success', 'Landing configuration saved.');
        }

        if ($action === 'profile') {
            $type = (string) ($_POST['institution_type'] ?? 'school');
            if ($type === 'other') {
                $custom = trim($_POST['institution_type_other'] ?? '');
                if ($custom !== '') {
                    $type = $custom;
                }
            }

            // Handle cover image upload via media picker
            $cover = handle_media_picker('cover_image', trim($inst['folder_path'], '/') . '/assets');
            $coverPath = $cover ? org_url($inst['slug'], 'assets/' . basename($cover)) : ($inst['cover_image_path'] ?? null);

            crud()->update('institutions', [
                'name'             => trim($_POST['name'] ?? '') ?: $inst['name'],
                'short_name'       => trim($_POST['short_name'] ?? '') ?: null,
                'institution_type' => $type,
                'description'      => trim($_POST['description'] ?? '') ?: null,
                'address'          => trim($_POST['address'] ?? '') ?: null,
                'city'             => trim($_POST['city'] ?? '') ?: null,
                'province'         => trim($_POST['province'] ?? '') ?: null,
                'country'          => trim($_POST['country'] ?? 'Philippines') ?: 'Philippines',
                'latitude'         => trim($_POST['latitude'] ?? '') !== '' ? trim($_POST['latitude']) : $inst['latitude'] ?? null,
                'longitude'        => trim($_POST['longitude'] ?? '') !== '' ? trim($_POST['longitude']) : $inst['longitude'] ?? null,
                'website_url'      => trim($_POST['website_url'] ?? '') ?: null,
                'contact_email'    => trim($_POST['contact_email'] ?? '') ?: null,
                'contact_phone'    => trim($_POST['contact_phone'] ?? '') ?: null,
                'cover_image_path' => $coverPath,
            ], ['id' => $iid]);
            foreach (['name' => trim($_POST['name'] ?? '') ?: $inst['name'], 'short_name' => trim($_POST['short_name'] ?? '') ?: null, 'institution_type' => $type] as $c => $v) {
                $_SESSION['user']['institution'][$c] = $v;
            }
            sync_institution_config($iid);
            flash('success', 'Profile updated.');
        }

        if ($action === 'publish') {
            $published = isset($_POST['is_published']) ? 1 : 0;
            crud()->update('institutions', ['is_published' => $published], ['id' => $iid]);
            $_SESSION['user']['institution']['is_published'] = $published;
            sync_institution_config($iid);
            flash('success', 'Publish state updated.');
        }

        if ($action === 'theme') {
            crud()->update('institution_themes', [
                'primary_color' => trim($_POST['primary_color'] ?? '') ?: '#1a365d',
                'secondary_color' => trim($_POST['secondary_color'] ?? '') ?: '#ed8936',
                'accent_color' => trim($_POST['accent_color'] ?? '') ?: '#38b2ac',
                'font_family' => trim($_POST['font_family'] ?? '') ?: null,
                'popup_animation' => 'fade', 'marker_style' => 'circle', 'infographic_style' => 'card',
                'custom_css' => ($_POST['custom_css'] ?? '') !== '' ? trim($_POST['custom_css']) : null,
                'theme_json' => json_encode([
                    'logo_size_percent' => (float) ($_POST['logo_size_percent'] ?? 8),
                    'popup_corner_radius' => (int) ($_POST['popup_corner_radius'] ?? 14),
                    'marker_glow' => isset($_POST['marker_glow']) ? 1 : 0,
                    'infograph_font_size' => (int) ($_POST['infograph_font_size'] ?? 15),
                    'landing_gradient' => trim($_POST['landing_gradient'] ?? '') ?: null,
                ]),
            ], ['institution_id' => $iid]);
            sync_institution_config($iid);
            flash('success', 'Theme saved. Your campus landing will render with these tokens.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/institution/settings');
}

// reload after writes
$inst = crud()->get('institutions', $iid);
$theme = crud()->get('institution_themes', ['institution_id' => $iid]);
$themeJson = json_decode($theme['theme_json'] ?? 'null', true) ?: [];

$scenes = crud()->select('tour_scenes', 'id, title', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY title');
$plans = crud()->select('floor_plans', 'id, title', ['institution_id' => $iid], 'ORDER BY title');

$tabs = [
    'landing' => ['icon' => 'camera', 'label' => 'Landing'],
    'profile' => ['icon' => 'file', 'label' => 'Profile'],
    'theme'   => ['icon' => 'paint', 'label' => 'Theme'],
];
?>
<ul class="nav nav-tabs nav-ia mb-3" id="set-tabs" role="tablist">
  <?php $first = true; foreach ($tabs as $key => $t): ?>
    <li class="nav-item">
      <button class="nav-link <?= $first ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $key ?>" id="tab-btn-<?= $key ?>">
        <?= ia_icon($t['icon'], 14) ?> <?= h($t['label']) ?>
      </button>
    </li>
  <?php $first = false; endforeach; ?>
</ul>

<div class="tab-content">
  <!-- ============ LANDING ============ -->
  <div class="tab-pane fade show active" id="tab-landing" role="tabpanel">
    <form method="post" class="ia-card">
      <input type="hidden" name="settings_action" value="landing">
      <div class="card-head"><h3>Landing mode & starting point</h3></div>
      <div class="card-body d-grid gap-4">
        <div class="row g-3">
          <?php foreach (['360_rotation' => '360° rotation', 'floor_plan' => 'Floor plan'] as $val => $label): ?>
            <div class="col-md-6">
              <label class="mode-card">
                <input type="radio" name="landing_mode" value="<?= $val ?>" <?= ($inst['landing_mode'] ?? '360_rotation') === $val ? 'checked' : '' ?>>
                <span class="mode-box">
                  <span class="mode-icon"><?= ia_icon($val === '360_rotation' ? 'camera' : 'map', 22) ?></span>
                  <span class="mode-title"><?= $label ?></span>
                  <span class="mode-sub"><?= $val === '360_rotation' ? 'Full-screen A-Frame starting scene' : 'Image + responsive circular markers' ?></span>
                </span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Starting 360 scene <span class="text-muted">(rotation)</span></label>
            <select class="form-select" name="starting_scene_id">
              <option value="">— none —</option>
              <?php foreach ($scenes as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) ($inst['starting_scene_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Starting floor plan <span class="text-muted">(floor plan)</span></label>
            <select class="form-select" name="starting_floor_plan_id">
              <option value="">— none —</option>
              <?php foreach ($plans as $fp): ?>
                <option value="<?= (int) $fp['id'] ?>" <?= (int) ($inst['starting_floor_plan_id'] ?? 0) === (int) $fp['id'] ? 'selected' : '' ?>><?= h($fp['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="require_landscape_mobile" id="ls-mobile" <?= (int) ($inst['require_landscape_mobile'] ?? 1) === 1 ? 'checked' : '' ?>>
          <label class="form-check-label" for="ls-mobile">Force landscape fullscreen on mobile</label>
        </div>
        <div class="d-flex justify-content-end"><button class="btn btn-grad px-4"><?= ia_icon('save', 15) ?> Save landing</button></div>
      </div>
    </form>

    <form method="post" class="ia-card mt-4">
      <input type="hidden" name="settings_action" value="publish">
      <div class="card-head"><h3>Publishing</h3></div>
      <div class="card-body d-flex align-items-center justify-content-between gap-3">
        <div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_published" id="pub" <?= (int) ($inst['is_published'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="pub">Campus landing is live for visitors</label>
          </div>
          <p class="text-muted mb-0 fs-125">Public link: <code><?= h(org_url($inst['slug'], '')) ?></code></p>
          <?php if ((int) ($inst['is_published'] ?? 0) !== 1): ?>
            <p class="text-muted mb-0 fs-125 mt-1">Visitors currently see a <em>"not published"</em> notice. Use <strong>Preview tour</strong> to check the real landing.</p>
          <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
          <?php if ((int) ($inst['is_published'] ?? 0) !== 1): ?>
            <a class="btn btn-outline-ia" target="_blank" href="<?= h(org_url($inst['slug'], '') . '?preview=1') ?>"><?= ia_icon('eye', 15) ?> Preview tour</a>
          <?php endif; ?>
          <button class="btn btn-grad px-4"><?= ia_icon('rocket', 15) ?> Save publish state</button>
        </div>
      </div>
    </form>
  </div>

  <!-- ============ PROFILE ============ -->
  <div class="tab-pane fade" id="tab-profile" role="tabpanel">
    <form method="post" class="ia-card" enctype="multipart/form-data">
      <input type="hidden" name="settings_action" value="profile">
      <div class="card-head"><h3>Institution profile</h3></div>
      <div class="card-body d-grid gap-3">
        <div class="row g-3">
          <div class="col-md-7"><label class="form-label">Full name</label><input class="form-control" name="name" value="<?= h($inst['name']) ?>" required></div>
          <div class="col-md-2"><label class="form-label">Short name</label><input class="form-control" name="short_name" value="<?= h($inst['short_name'] ?? '') ?>"></div>
          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select class="form-select" name="institution_type" id="set-type">
              <?php
              $curType = $inst['institution_type'] ?? 'school';
              $stdTypes = ['school', 'college', 'university'];
              $isStd = in_array($curType, $stdTypes, true);
              ?>
              <?php foreach ($stdTypes as $t): ?>
                <option value="<?= $t ?>" <?= $curType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
              <option value="other" <?= !$isStd ? 'selected' : '' ?>>Other…</option>
            </select>
          </div>
          <div class="col-md-3 <?= $isStd ? 'd-none' : '' ?>" id="set-type-other-wrap">
            <label class="form-label">Specify type</label>
            <input class="form-control" name="institution_type_other" value="<?= !$isStd ? h($curType) : '' ?>" placeholder="e.g. Technical-Vocational Institute">
          </div>
        </div>
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-1">Description</label>
            <button type="button" class="btn btn-sm btn-outline-ia" data-ai-gen data-ai-type="institution" data-ai-id="<?= (int) $inst['id'] ?>" data-ai-target-el="inst-desc"><?= ia_icon('wand', 13) ?> Generate AI</button>
          </div>
          <textarea class="form-control" name="description" id="inst-desc" rows="3"><?= h($inst['description'] ?? '') ?></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label">Address</label>
            <div class="pw-group">
              <input class="form-control" name="address" id="inst-addr" value="<?= h($inst['address'] ?? '') ?>" readonly onclick="IAAddr.open()" title="Click to search or pin on the map">
              <button type="button" class="btn btn-outline-ia btn-shrink" onclick="IAAddr.open()"><?= ia_icon('map', 14) ?> Pick on map</button>
            </div>
          </div>
          <div class="col-md-3"><label class="form-label">City</label><input class="form-control" name="city" id="inst-city" value="<?= h($inst['city'] ?? '') ?>"></div>
          <div class="col-md-3"><label class="form-label">Province</label><input class="form-control" name="province" id="inst-province" value="<?= h($inst['province'] ?? '') ?>"></div>
          <div class="col-md-2"><label class="form-label">Country</label><input class="form-control" name="country" id="inst-country" value="<?= h($inst['country'] ?? 'Philippines') ?>"></div>
          <div class="col-md-2"><label class="form-label">Latitude</label><input class="form-control" name="latitude" id="inst-lat" value="<?= h($inst['latitude'] ?? '') ?>" placeholder="e.g. 14.5534344"></div>
          <div class="col-md-2"><label class="form-label">Longitude</label><input class="form-control" name="longitude" id="inst-lng" value="<?= h($inst['longitude'] ?? '') ?>" placeholder="e.g. 121.0496843"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-5"><label class="form-label">Website</label><input class="form-control" name="website_url" value="<?= h($inst['website_url'] ?? '') ?>" placeholder="https://"></div>
          <div class="col-md-4"><label class="form-label">Contact email</label><input class="form-control" name="contact_email" value="<?= h($inst['contact_email'] ?? '') ?>"></div>
          <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="contact_phone" value="<?= h($inst['contact_phone'] ?? '') ?>"></div>
        </div>
        <div>
          <!-- Cover image media picker -->
          <div>
            <?php
            $pickerName = 'cover_image';
            $pickerValue = ''; // The path is typically stored as a full URL, so we leave it empty to avoid confusion with relative paths
            $pickerLabel = 'Cover image (appears on the main platform landing page)';
            $pickerHelp = 'Recommended 1200×800 (landscape).';
            require __DIR__ . '/../layout/media-picker.php';
            ?>
            <?php if (!empty($inst['cover_image_path'])): ?>
              <div class="mt-2">
                <span class="text-muted small d-block mb-1">Current cover:</span>
                <img src="<?= h($inst['cover_image_path']) ?>" alt="Cover" class="cover-thumb">
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex justify-content-end"><button class="btn btn-grad px-4"><?= ia_icon('save', 15) ?> Save profile</button></div>
      </div>
    </form>
  </div>

  <!-- ============ THEME ============ -->
  <div class="tab-pane fade" id="tab-theme" role="tabpanel">
    <form method="post" class="ia-card">
      <input type="hidden" name="settings_action" value="theme">
      <div class="card-head"><h3>Visual theme <span class="text-muted">(lightweight drag-drop tokens)</span></h3></div>
      <div class="card-body d-grid gap-3">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Primary color</label><input type="color" class="form-control form-control-color" name="primary_color" value="<?= h($theme['primary_color']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Secondary color</label><input type="color" class="form-control form-control-color" name="secondary_color" value="<?= h($theme['secondary_color']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Accent color</label><input type="color" class="form-control form-control-color" name="accent_color" value="<?= h($theme['accent_color']) ?>"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Font family</label>
            <select class="form-select" name="font_family">
              <?php foreach (['System UI', 'Inter', 'Poppins', 'Georgia', 'Arial'] as $f): ?>
                <option <?= ($theme['font_family'] ?? '') === $f ? 'selected' : '' ?>><?= h($f) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Landing gradient (CSS)</label><input class="form-control" name="landing_gradient" value="<?= h($themeJson['landing_gradient'] ?? '') ?>" placeholder="linear-gradient(180deg,#001834,#003a5c)"></div>
        </div>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Logo size %</label><input type="number" step="0.5" class="form-control" name="logo_size_percent" value="<?= h($themeJson['logo_size_percent'] ?? 8) ?>"></div>
          <div class="col-md-3"><label class="form-label">Popup radius (px)</label><input type="number" class="form-control" name="popup_corner_radius" value="<?= h($themeJson['popup_corner_radius'] ?? 14) ?>"></div>
          <div class="col-md-3"><label class="form-label">Infograph font (px)</label><input type="number" class="form-control" name="infograph_font_size" value="<?= h($themeJson['infograph_font_size'] ?? 15) ?>"></div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="marker_glow" id="glow" <?= !empty($themeJson['marker_glow']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="glow">Marker glow</label>
            </div>
          </div>
        </div>
        <div>
          <label class="form-label">Custom CSS (optional)</label>
          <textarea class="form-control code-area" name="custom_css" rows="4"><?= h($theme['custom_css'] ?? '') ?></textarea>
          <div class="form-text">Runs only on your own institution landing.</div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="theme-preview"><span class="sw" style="--sw:<?= h($theme['primary_color']) ?>"></span><span class="sw" style="--sw:<?= h($theme['secondary_color']) ?>"></span><span class="sw" style="--sw:<?= h($theme['accent_color']) ?>"></span></span>
          <button class="btn btn-grad px-4"><?= ia_icon('save', 15) ?> Save theme</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- address picker modal -->
<div class="modal fade" id="ia-addr-modal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Find the institution address</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
      <div class="p-3 divider-bottom">
        <div class="input-icon">
          <span class="icon"><?= ia_icon('search', 16) ?></span>
          <input type="text" class="form-control" id="ia-addr-search" placeholder="Search a specific address, street, campus…">
        </div>
        <div id="ia-addr-results" class="d-none mt-2"></div>
      </div>
      <div id="ia-addr-map"></div>
    </div>
    <div class="modal-footer">
      <span class="text-muted me-auto fs-125" id="ia-addr-status">Search or click the map to place a pin, then apply.</span>
      <button class="btn btn-outline-ia" type="button" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-grad px-4" type="button" id="ia-addr-apply">Apply address</button>
    </div>
  </div></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const typeSel = document.getElementById('set-type')
  const otherWrap = document.getElementById('set-type-other-wrap')
  if (typeSel && otherWrap) {
    const sync = () => { otherWrap.classList.toggle('d-none', typeSel.value !== 'other') }
    typeSel.addEventListener('change', sync)
    sync()
  }
  document.querySelectorAll('.mode-card input').forEach((r) => {
    r.addEventListener('change', () => {
      const fp = r.value === 'floor_plan'
      document.querySelector('select[name="starting_floor_plan_id"]').closest('div').style.opacity = fp ? 1 : .45
      document.querySelector('select[name="starting_scene_id"]').closest('div').style.opacity = fp ? .45 : 1
    })
  })
});

/* -------------------- Leaflet + Nominatim address picker ------------------- */
(function () {
  var MAP_MODAL = document.getElementById('ia-addr-modal');
  var mapEl = document.getElementById('ia-addr-map');
  var searchEl = document.getElementById('ia-addr-search');
  var resultsEl = document.getElementById('ia-addr-results');
  var statusEl = document.getElementById('ia-addr-status');
  var applyBtn = document.getElementById('ia-addr-apply');
  if (!MAP_MODAL || !mapEl) return;

  var map = null, marker = null, picked = null, debounce = null;

  function latLng() {
    return picked ? L.latLng(picked.lat, picked.lon) : L.latLng(13.6, 121.0);
  }

  function ensureMap() {
    if (map) return;
    map = L.map(mapEl).setView(latLng(), 6);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    marker = L.marker(latLng(), { draggable: true }).addTo(map);
    marker.on('dragend', function () {
      var p = marker.getLatLng();
      reverse(p.lat, p.lng);
    });
    map.on('click', function (e) {
      marker.setLatLng(e.latlng);
      reverse(e.latlng.lat, e.latlng.lng);
    });
    setTimeout(function () { map.invalidateSize(); }, 250);
  }

  function reverse(lat, lon) {
    statusEl.textContent = 'Looking up that point…';
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lon + '&zoom=18&addressdetails=1')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        picked = { lat: lat, lon: lon, display_name: d.display_name || '', address: d.address || {} };
        fillStatus();
      })
      .catch(function () { statusEl.textContent = 'Reverse lookup failed — try typing the address instead.'; });
  }

  function fillStatus() {
    if (!picked) return;
    statusEl.textContent = picked.display_name ? picked.display_name : (picked.lat.toFixed(6) + ', ' + picked.lon.toFixed(6));
  }

  searchEl.addEventListener('input', function () {
    clearTimeout(debounce);
    var q = searchEl.value.trim();
    if (q.length < 4) { resultsEl.classList.add('d-none'); resultsEl.innerHTML = ''; return; }
    debounce = setTimeout(function () {
      fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&q=' + encodeURIComponent(q) + '&limit=6&addressdetails=1&countrycodes=ph')
        .then(function (r) { return r.json(); })
        .catch(function () { return []; })
        .then(function (items) { renderResults(items); });
    }, 350);
  });

  function renderResults(items) {
    resultsEl.innerHTML = '';
    if (!items || !items.length) {
resultsEl.classList.remove('d-none');
    resultsEl.innerHTML = '<div class="text-muted fs-13 ia-addr-empty">No exact match — try a different keyword, or pin the campus on the map below.</div>';
    return;
  }
  resultsEl.classList.remove('d-none');
    items.forEach(function (it) {
      var name = document.createElement('div');
      name.className = 'ia-addr-result';
      name.textContent = it.display_name;
      name.addEventListener('click', function () {
        selected(it);
        resultsEl.classList.add('d-none');
        resultsEl.innerHTML = '';
        searchEl.value = '';
      });
      resultsEl.appendChild(name);
    });
  }

  function selected(it) {
    picked = { lat: parseFloat(it.lat), lon: parseFloat(it.lon), display_name: it.display_name, address: it.address || {} };
    ensureMap();
    marker.setLatLng([picked.lat, picked.lon]);
    map.setView([picked.lat, picked.lon], 16);
    fillStatus();
  }

  window.IAAddr = {
    open: function () {
      if (!picked) {
        var existingLat = document.getElementById('inst-lat').value;
        var existingLng = document.getElementById('inst-lng').value;
        if (existingLat && existingLng) {
          picked = { lat: parseFloat(existingLat), lon: parseFloat(existingLng) };
        }
      }
      ensureMap();
      if (picked) { marker.setLatLng([picked.lat, picked.lon]); map.setView([picked.lat, picked.lon], 16); }
      var bs = new bootstrap.Modal(MAP_MODAL);
      bs.show();
    }
  };

  applyBtn.addEventListener('click', function () {
    if (!picked) { statusEl.textContent = 'Please pick a location first.'; return; }
    var a = picked.address || {};
    var city = a.city || a.town || a.municipality || a.county || '';
    var prov = a.state || a.province || a.region || '';
    var addr = picked.display_name || '';
    document.getElementById('inst-addr').value = addr;
    document.getElementById('inst-city').value = city;
    document.getElementById('inst-province').value = prov;
    if (a.country) document.getElementById('inst-country').value = a.country;
    document.getElementById('inst-lat').value = picked.lat.toFixed(7);
    document.getElementById('inst-lng').value = picked.lon.toFixed(7);
    var bs = bootstrap.Modal.getInstance(MAP_MODAL);
    if (bs) bs.hide();
    document.getElementById('inst-addr').dispatchEvent(new Event('input', { bubbles: true }));
  });
})();


</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>