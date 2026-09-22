<?php
/**
 * Innovatech PH — Unified organization landing page
 * 
 * PURE DATABASE-DRIVEN approach.
 * No config.json dependency - everything from DB.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Prevent all caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');

// Extract org slug from query parameter (?org=slug) set by .htaccess rewrite
$slug = $_GET['org'] ?? null;

// Fallback: try to extract from REQUEST_URI (for direct requests)
if (!$slug) {
  $pathParts = array_filter(explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/')));
  $orgIndex = array_search('organizations', $pathParts, true);
  $slug = $pathParts[$orgIndex + 1] ?? null;
}

if (!$slug || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
  http_response_code(404);
  die('Organization not found.');
}

// Query DB for org — DB is source of truth
$org = crud()->raw(
  "SELECT id, name, short_name, is_published, landing_mode, folder_path 
     FROM institutions 
     WHERE slug = :slug AND deleted_at IS NULL",
  ['slug' => $slug]
)->fetch();

if (!$org) {
  http_response_code(404);
  die('Organization not found.');
}

$iid = (int) $org['id'];

// Check admin preview mode
$isPreview = ($_GET['preview'] ?? '') === '1';

// If not published, show not-published overlay
if (!(int) $org['is_published'] && !$isPreview) {
  ?><!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0b0d16">
    <title><?= htmlspecialchars($org['name']) ?> · Virtual Campus</title>
    <link rel="stylesheet" href="<?= url('organizations/' . htmlspecialchars($slug) . '/assets/style.css') ?>">
  </head>

  <body>
    <div id="not-published" class="np-overlay">
      <div class="np-card">
        <div class="brand-chip"><span class="dot"></span> <?= htmlspecialchars($org['short_name'] ?: $org['name']) ?>
        </div>
        <div class="np-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 22h14" />
            <path d="M5 2h14" />
            <path d="M17 22v-4.17a2 2 0 0 0-.59-1.42L12 12l-4.41 4.41A2 2 0 0 0 7 17.83V22" />
            <path d="M7 2v4.17a2 2 0 0 0 .59 1.42L12 12l4.41-4.41A2 2 0 0 0 17 6.17V2" />
          </svg>
        </div>
        <h1 class="np-title"><?= htmlspecialchars($org['name']) ?></h1>
        <p class="np-msg">This virtual campus experience is not yet published.<br>Please check back soon.</p>
        <p class="np-foot">Powered by Innovatech PH</p>
      </div>
    </div>
  </body>

  </html>
  <?php
  exit;
}

// Published — load ALL data from DB (pure database-driven)
$iid = (int) $org['id'];

// Query theme settings from DB
$themeResult = crud()->select('institution_themes', '*', ['institution_id' => $iid]);
if (is_array($themeResult)) {
  $theme = $themeResult[0] ?? [];
} else {
  $theme = $themeResult->fetch() ?: [];
}

if (!$theme) {
  $theme = [
    'primary_color' => '#1a365d',
    'secondary_color' => '#ed8936',
    'accent_color' => '#38b2ac',
    'popup_animation' => 'fade',
    'marker_style' => 'circle',
    'infographic_style' => 'card'
  ];
}

// Query scenes from DB
try {
  $scenesResult = crud()->select(
    'tour_scenes',
    '*',
    ['institution_id' => $iid, 'deleted_at' => ['IS', null]],
    'ORDER BY sort_order ASC'
  );
  if (is_array($scenesResult)) {
    $scenes = $scenesResult;
  } else {
    $scenes = $scenesResult->fetchAll();
  }
} catch (Exception $e) {
  $scenes = [];
}

// Query hotspots from DB
try {
  $hotspotsResult = crud()->select(
    'scene_hotspots',
    '*',
    ['institution_id' => $iid],
    'ORDER BY from_scene_id, id ASC'
  );
  if (is_array($hotspotsResult)) {
    $hotspots = $hotspotsResult;
  } else {
    $hotspots = $hotspotsResult->fetchAll();
  }
} catch (Exception $e) {
  $hotspots = [];
}

// Query floor plans from DB
try {
  $floorPlansResult = crud()->select(
    'floor_plans',
    '*',
    ['institution_id' => $iid, 'deleted_at' => ['IS', null]],
    'ORDER BY is_campus_landing DESC, id ASC'
  );
  if (is_array($floorPlansResult)) {
    $floorPlans = $floorPlansResult;
  } else {
    $floorPlans = $floorPlansResult->fetchAll();
  }
} catch (Exception $e) {
  $floorPlans = [];
}

// Query floor plan markers from DB
try {
  $markersResult = crud()->select(
    'floor_plan_markers',
    '*',
    ['institution_id' => $iid],
    'ORDER BY floor_plan_id, id ASC'
  );
  if (is_array($markersResult)) {
    $markers = $markersResult;
  } else {
    $markers = $markersResult->fetchAll();
  }
} catch (Exception $e) {
  $markers = [];
}

// Build config dynamically from DB
$config = [
  'institution_id' => $iid,
  'name' => $org['name'],
  'short_name' => $org['short_name'],
  'landing_mode' => $org['landing_mode'],
  'require_landscape_mobile' => true,
  'published' => true,
  'theme' => [
    'primary' => is_array($theme) ? ($theme['primary_color'] ?? '#1a365d') : '#1a365d',
    'secondary' => is_array($theme) ? ($theme['secondary_color'] ?? '#ed8936') : '#ed8936',
    'accent' => is_array($theme) ? ($theme['accent_color'] ?? '#38b2ac') : '#38b2ac',
    'popup_animation' => is_array($theme) ? ($theme['popup_animation'] ?? 'fade') : 'fade',
    'marker_glow' => false
  ],
  'scenes' => [],
  'starting_scene' => null
];

// Build scenes array
$scenesMap = [];
foreach ($scenes as $scene) {
  $scenesMap[(int) $scene['id']] = [
    'id' => (int) $scene['id'],
    'title' => $scene['title'],
    'slug' => $scene['slug'],
    'description' => $scene['description'],
    'ai_description' => $scene['ai_description'],
    'featured_image_path' => $scene['featured_image_path'],
    'equirect_path' => $scene['equirect_path'],
    'initial_yaw' => (float) $scene['initial_yaw'],
    'initial_pitch' => (float) $scene['initial_pitch'],
    'hotspots' => []
  ];

  // Set as starting scene if marked
  if ((int) $scene['is_landing_start']) {
    $config['starting_scene'] = [
      'id' => (int) $scene['id'],
      'title' => $scene['title'],
      'equirect_path' => $scene['equirect_path'],
      'initial_yaw' => (float) $scene['initial_yaw'],
      'initial_pitch' => (float) $scene['initial_pitch']
    ];
  }
}

// Attach hotspots to scenes
foreach ($hotspots as $hs) {
  $fromSceneId = (int) $hs['from_scene_id'];
  if (isset($scenesMap[$fromSceneId])) {
    $scenesMap[$fromSceneId]['hotspots'][] = [
      'id' => (int) $hs['id'],
      'label' => $hs['label'],
      'hotspot_type' => $hs['hotspot_type'],
      'yaw' => (float) $hs['yaw'],
      'pitch' => (float) $hs['pitch'],
      'body_html' => $hs['body_html'],
      'media_path' => $hs['media_path'] ?? null,
      'to_scene_id' => $hs['to_scene_id'] ? (int) $hs['to_scene_id'] : null,
      'target_facility_id' => $hs['target_facility_id'] ? (int) $hs['target_facility_id'] : null
    ];
  }
}

$config['scenes'] = $scenesMap;

// If no starting scene set, use first scene
if (!$config['starting_scene'] && !empty($scenesMap)) {
  $firstScene = reset($scenesMap);
  $config['starting_scene'] = [
    'id' => $firstScene['id'],
    'title' => $firstScene['title'],
    'equirect_path' => $firstScene['equirect_path'],
    'initial_yaw' => $firstScene['initial_yaw'],
    'initial_pitch' => $firstScene['initial_pitch']
  ];
}

// Add floor plans to config
$config['floor_plans'] = [];
$markersByFloorPlan = [];

foreach ($markers as $m) {
  $fpId = (int) $m['floor_plan_id'];
  if (!isset($markersByFloorPlan[$fpId])) {
    $markersByFloorPlan[$fpId] = [];
  }
  $markersByFloorPlan[$fpId][] = [
    'id'                  => (int) $m['id'],
    'x'                   => (float) $m['x_percent'],
    'y'                   => (float) $m['y_percent'],
    'label'               => $m['label'],
    'marker_type'         => $m['marker_type'],      // scene|360|floorplan|ar|compass|info|room|area
    'target_type'         => $m['target_type'],      // kept for back-compat
    'target_scene_id'     => $m['target_scene_id']      ? (int) $m['target_scene_id']      : null,
    'target_floor_plan_id'=> $m['target_floor_plan_id'] ? (int) $m['target_floor_plan_id'] : null,
    'target_room_id'      => $m['target_room_id']       ? (int) $m['target_room_id']       : null,
    'target_area_id'      => $m['target_area_id']       ? (int) $m['target_area_id']       : null,
    'target_facility_id'  => $m['target_facility_id']   ? (int) $m['target_facility_id']   : null,
    'facing_angle'        => isset($m['facing_angle'])  ? (float) $m['facing_angle']        : 0,
    'size_percent'        => $m['size_percent'] ?? 4,
    'popup_title'         => $m['popup_title'] ?? null,
    'popup_html'          => $m['popup_html'] ?? null,
    'marker_image_path'   => $m['marker_image_path'] ?? null
  ];
}

foreach ($floorPlans as $fp) {
  $fpId = (int) $fp['id'];
  $config['floor_plans'][$fpId] = [
    'id' => $fpId,
    'title' => $fp['title'],
    'image_path' => $fp['image_path'],
    'original_width' => (int) $fp['original_width'],
    'original_height' => (int) $fp['original_height'],
    'aspect_ratio' => (float) $fp['aspect_ratio'],
    'object_fit' => $fp['object_fit'],
    'north_angle' => (float) $fp['north_angle'],
    'is_campus_landing' => (bool) $fp['is_campus_landing'],
    'ar_mind_path' => $fp['ar_mind_path'] ?? null,
    'markers' => $markersByFloorPlan[$fpId] ?? []
  ];

  // Set as starting floor plan if marked
  if ((bool) $fp['is_campus_landing']) {
    $config['starting_floor_plan'] = [
      'id' => $fpId,
      'title' => $fp['title'],
      'image_path' => $fp['image_path']
    ];
  }
}

// If no starting floor plan set, use first one
if (!$config['starting_floor_plan'] && !empty($config['floor_plans'])) {
  $firstFP = reset($config['floor_plans']);
  $config['starting_floor_plan'] = [
    'id' => $firstFP['id'],
    'title' => $firstFP['title'],
    'image_path' => $firstFP['image_path']
  ];
}

// Function to normalize paths - convert to absolute URLs with base path
function normalizePaths(&$arr, $slug)
{
  if (!is_array($arr))
    return;

  $pathKeys = ['equirect_path', 'featured_image_path', 'image_path', 'pano_url', 'sub_fp_image', 'floor_plan_image', 'scene_equirect', 'thumbnail_path', 'media_path', 'marker_image_path', 'ar_mind_path'];

  foreach ($arr as $k => &$v) {
    if (is_string($v)) {
      // Skip if already absolute URL (http) or empty
      if (!$v || strpos($v, 'http') === 0)
        continue;

      // Skip if already has full base path (starts with /G7 or similar)
      if (preg_match('#^/[A-Z0-9%]+#', $v))
        continue;

      // Build the organization-relative path
      $orgPath = 'organizations/' . $slug . '/';

      // If path already has organizations/slug/ prefix, keep it
      if (preg_match('#^organizations/' . preg_quote($slug, '#') . '/#', $v)) {
        $relativePath = $v;
      }
      // If path starts with slug/, add organizations/ prefix
      elseif (preg_match('#^' . preg_quote($slug, '#') . '/#', $v)) {
        $relativePath = 'organizations/' . $v;
      }
      // Otherwise, add full organizations/slug/ prefix
      else {
        $relativePath = $orgPath . ltrim($v, '/');
      }

      // Use url() helper to get full absolute URL with base path
      if (in_array($k, $pathKeys)) {
        $v = url($relativePath);
      }
    } elseif (is_array($v)) {
      normalizePaths($v, $slug);
    }
  }
}
normalizePaths($config, $slug);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#0b0d16">
  <title><?= htmlspecialchars($org['name']) ?> · Virtual Campus</title>
  <link rel="stylesheet" href="<?= url('organizations/' . htmlspecialchars($slug) . '/assets/style.css') ?>">
</head>

<body>
  <!-- Landscape rotation suggestion modal -->
  <div id="landscape-modal" class="landscape-modal hidden">
    <div class="landscape-content">
      <div class="landscape-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="18" height="12" x="3" y="6" rx="2" ry="2"/>
          <path d="M12 18v-6"/>
          <path d="m16 14-4-4-4 4"/>
        </svg>
      </div>
      <h3>Rotate to Landscape</h3>
      <p>For the best immersive experience, please rotate your device to landscape mode.</p>
      <button id="landscape-dismiss" class="btn btn-grad">Continue Anyway</button>
    </div>
  </div>

  <!-- =========================== 360 MODE (A-Frame) =========================== -->
  <div id="mode-360" class="mode hidden">
    <a-scene id="aframe-scene" embedded vr-mode-ui="enabled: false"
      renderer="antialias: true; colorManagement: true; highRefreshRate: true; precision: high;">
      <a-assets>
        <img id="pano" crossorigin="anonymous">
      </a-assets>
      <a-sky id="sky" src="#pano" rotation="0 0 0"></a-sky>
      <a-entity id="camera-rig">
        <a-camera position="0 0 0" look-controls="touchEnabled: true; mouseEnabled: true; reverseMouseDrag: true; reverseTouchDrag: true" wasd-controls="enabled:false">
          <a-cursor raycaster="objects: .clickable" far="100"></a-cursor>
        </a-camera>
      </a-entity>
    </a-scene>

    <div class="hud bottom hint" id="scene-hint">Drag to look around</div>

    <!-- Info modal -->
    <div id="info-modal" class="info-modal hidden">
      <div class="info-modal-content">
        <div class="info-modal-header">
          <h3 id="info-title"></h3>
          <button class="info-modal-close" id="info-close">×</button>
        </div>
        <div class="info-modal-body" id="info-body"></div>
      </div>
    </div>
  </div>

  <!-- ========================= FLOOR-PLAN MODE ========================= -->
  <div id="mode-floor" class="mode hidden">
    <div class="fp-stage" id="fp-stage">
      <div class="fp-wrap" id="fp-wrap">
        <img id="fp-image" src="" alt="<?= htmlspecialchars($org['name']) ?> floor plan">
        <div id="fp-markers" class="fp-markers"></div>
      </div>
    </div>
  </div>

  <!-- ========================= SHARED HUD (both modes) ========================= -->
  <div class="hud top-left">
    <button class="round-btn" id="btn-back" title="Go back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg></button>
    <button class="round-btn" id="btn-scenes-toggle" title="Toggle 360° scenes"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
    <button class="round-btn" id="btn-search-toggle" title="Search locations"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
    <button class="round-btn" id="btn-fullscreen-toggle" title="Toggle fullscreen"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg></button>
    <a href="<?= url('organizations/ar.php?org=' . urlencode($slug)) ?>" class="round-btn" title="AR Navigation"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path><path d="M15 6.5a4 4 0 1 1-4 4 4 4 0 0 1 4-4z"></path><path d="M18 2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h12z"></path></svg></a>
  </div>
  <div class="hud top-right">
    <div class="brand-chip"><span class="dot"></span> <span
        id="fp-title"><?= htmlspecialchars($org['short_name'] ?: $org['name']) ?></span></div>
  </div>

  <!-- Collapsible bottom landscape panel for 360 scenes (Shared by both modes) -->
  <div id="fp-scene-sidebar" class="fp-scene-sidebar">
    <div class="fp-sidebar-header">
      <span class="fp-sidebar-badge">360° Scenes</span>
      <button class="fp-sidebar-close" id="btn-sidebar-close" title="Hide panel"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
    </div>
    <div id="fp-scene-list" class="fp-scene-list"></div>
  </div>

  <!-- ================================ LOADER ================================ -->
  <div id="loader" class="loader">
    <div class="spinner"></div>
    <p><?= htmlspecialchars($org['name']) ?></p>
  </div>

  <!-- ============================== SEARCH MODAL ============================== -->
  <div id="search-modal" class="search-modal hidden">
    <div class="search-backdrop" id="search-backdrop"></div>
    <div class="search-content">
      <div class="search-header">
        <h3>Search Locations</h3>
        <button class="round-btn" id="btn-search-close"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
      </div>
      <div class="search-input-wrapper">
        <input type="text" id="search-input" placeholder="Search buildings, rooms, floor plans, 360 scenes..." autocomplete="off">
      </div>
      <div class="search-results" id="search-results">
        <div class="search-empty">Type to search locations...</div>
      </div>
    </div>
  </div>

  <!-- ====================== CONFIG EMBEDDED IN PAGE ====================== -->
  <script>
    window.__CONFIG__ = <?= json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    window.__CONFIG__.aframe_url = '<?= url('organizations/' . htmlspecialchars($slug) . '/assets/aframe.min.js') ?>';
  </script>

  <!-- Extracted application logic for 360 and floor plan viewing -->
  <script src="<?= url('organizations/app.js') ?>?v=<?= time() ?>"></script>
</body>

</html>