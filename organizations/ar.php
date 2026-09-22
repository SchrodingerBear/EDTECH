<?php
/**
 * Innovatech PH — Organization AR Mode (standalone page)
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');

$slug = $_GET['org'] ?? null;
if (!$slug) {
  $pathParts = array_filter(explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/')));
  $orgIndex = array_search('organizations', $pathParts, true);
  $slug = $pathParts[$orgIndex + 1] ?? null;
}

if (!$slug || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
  http_response_code(404);
  die('Organization not found.');
}

$org = crud()->raw(
  "SELECT id, name, short_name, is_published, folder_path 
     FROM institutions 
     WHERE slug = :slug AND deleted_at IS NULL",
  ['slug' => $slug]
)->fetch();

if (!$org) { http_response_code(404); die('Organization not found.'); }

$iid = (int) $org['id'];
$isPreview = ($_GET['preview'] ?? '') === '1';

if (!(int) $org['is_published'] && !$isPreview) {
  ?><!DOCTYPE html>
  <html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title><?= htmlspecialchars($org['name']) ?> · AR</title>
  <link rel="stylesheet" href="<?= url('organizations/' . htmlspecialchars($slug) . '/assets/style.css') ?>">
  </head><body>
  <div id="not-published" class="np-overlay"><div class="np-card">
    <div class="brand-chip"><span class="dot"></span> <?= htmlspecialchars($org['short_name'] ?: $org['name']) ?></div>
    <h1 class="np-title"><?= htmlspecialchars($org['name']) ?></h1>
    <p class="np-msg">This virtual campus experience is not yet published.<br>Please check back soon.</p>
    <p class="np-foot">Powered by Innovatech PH</p>
  </div></div></body></html><?php
  exit;
}

$themeResult = crud()->select('institution_themes', '*', ['institution_id' => $iid]);
$theme = (is_array($themeResult) ? $themeResult[0] : $themeResult->fetch()) ?: [];
if (!$theme) $theme = ['primary_color'=>'#1a365d','secondary_color'=>'#ed8936','accent_color'=>'#38b2ac'];

$floorPlansResult = crud()->select('floor_plans', '*', ['institution_id' => $iid, 'deleted_at' => ['IS', null]], 'ORDER BY is_campus_landing DESC, id ASC');
$floorPlans = is_array($floorPlansResult) ? $floorPlansResult : $floorPlansResult->fetchAll();

$markersResult = crud()->select('floor_plan_markers', '*', ['institution_id' => $iid], 'ORDER BY floor_plan_id, id ASC');
$markers = is_array($markersResult) ? $markersResult : $markersResult->fetchAll();

$arTargetsResult = crud()->raw(
  "SELECT t.* FROM ar_targets t
   WHERE t.institution_id = :iid AND t.is_active = 1 AND t.image_path IS NOT NULL AND t.image_path <> ''
   ORDER BY t.floor_plan_id, t.name, t.id",
  ['iid' => $iid]
)->fetchAll();

// Fetch facing_angle from floor_plan_markers for landmarks (via source_marker_id)
$markerAngles = [];
if ($arTargetsResult) {
  $markerIds = array_values(array_unique(array_filter(array_map(fn($t) => (int)($t['source_marker_id'] ?? 0), $arTargetsResult))));
  if ($markerIds) {
    $ph = implode(',', array_fill(0, count($markerIds), '?'));
    $markerRows = crud()->raw("SELECT id, facing_angle FROM floor_plan_markers WHERE id IN ($ph)", $markerIds)->fetchAll();
    foreach ($markerRows as $mr) $markerAngles[(int)$mr['id']] = (float)($mr['facing_angle'] ?? 0);
  }
}

$arTargetsByFloorPlan = [];
foreach ($arTargetsResult as $t) {
  $fpId = (int) $t['floor_plan_id'];
  if (!isset($arTargetsByFloorPlan[$fpId])) $arTargetsByFloorPlan[$fpId] = ['landmarks' => [], 'hotspots' => []];
  $targetData = ['id'=>(int)$t['id'],'name'=>$t['name'],'ar_type'=>$t['ar_type'],'target_scene_id'=>(int)($t['target_scene_id']??0),'target_floor_plan_id'=>(int)($t['target_floor_plan_id']??0),'image_path'=>$t['image_path'],'popup_title'=>$t['popup_title']??null,'popup_description'=>$t['popup_description']??null,'popup_image_path'=>$t['popup_image_path']??null,'target_mind_path'=>$t['target_mind_path']??null,'facing_angle'=>$markerAngles[(int)($t['source_marker_id'] ?? 0)] ?? 0];
  if ($t['ar_type']==='floorplan_ar') $arTargetsByFloorPlan[$fpId]['landmarks'][] = $targetData;
  elseif ($t['ar_type']==='360_ar_hotspot') $arTargetsByFloorPlan[$fpId]['hotspots'][] = $targetData;
}

$arMindMappings = [];
foreach ($floorPlans as $fp) {
  $fpId = (int) $fp['id'];
  if (empty($fp['ar_mind_path'])) continue;
  if (!isset($arTargetsByFloorPlan[$fpId])) continue;
  $mapping = [];
  $landmarks = $arTargetsByFloorPlan[$fpId]['landmarks'];
  usort($landmarks, fn($a,$b)=> strcmp($a['name'],$b['name']) ?: $a['id']-$b['id']);
  foreach ($landmarks as $idx => $t) $mapping[] = ['index'=>$idx,'target'=>$t];
  $arMindMappings[$fpId] = $mapping;
}

$buildingsResult = crud()->raw("SELECT id, name FROM buildings WHERE institution_id = :iid AND deleted_at IS NULL ORDER BY name", ['iid'=>$iid])->fetchAll();
$buildings = array_map(fn($b)=>['id'=>(int)$b['id'],'name'=>$b['name']], $buildingsResult);

$configFloorPlans = [];
$markersByFloorPlan = [];
foreach ($markers as $m) {
  $fpId = (int) $m['floor_plan_id'];
  if (!isset($markersByFloorPlan[$fpId])) $markersByFloorPlan[$fpId] = [];
  $markersByFloorPlan[$fpId][] = ['id'=>(int)$m['id'],'x'=>(float)$m['x_percent'],'y'=>(float)$m['y_percent'],'label'=>$m['label'],'marker_type'=>$m['marker_type'],'target_type'=>$m['target_type'],'target_scene_id'=>$m['target_scene_id']?(int)$m['target_scene_id']:null,'target_floor_plan_id'=>$m['target_floor_plan_id']?(int)$m['target_floor_plan_id']:null,'target_room_id'=>$m['target_room_id']?(int)$m['target_room_id']:null,'target_area_id'=>$m['target_area_id']?(int)$m['target_area_id']:null,'target_facility_id'=>$m['target_facility_id']?(int)$m['target_facility_id']:null,'facing_angle'=>isset($m['facing_angle'])?(float)$m['facing_angle']:0,'size_percent'=>$m['size_percent']??4,'popup_title'=>$m['popup_title']??null,'popup_html'=>$m['popup_html']??null,'marker_image_path'=>$m['marker_image_path']??null];
}

foreach ($floorPlans as $fp) {
  $fpId = (int) $fp['id'];
  $configFloorPlans[$fpId] = ['id'=>$fpId,'title'=>$fp['title'],'image_path'=>$fp['image_path'],'original_width'=>(int)$fp['original_width'],'original_height'=>(int)$fp['original_height'],'aspect_ratio'=>(float)$fp['aspect_ratio'],'object_fit'=>$fp['object_fit'],'north_angle'=>(float)$fp['north_angle'],'is_campus_landing'=>(bool)$fp['is_campus_landing'],'ar_mind_path'=>$fp['ar_mind_path']??null,'markers'=>$markersByFloorPlan[$fpId]??[]];
}

$startingFp = null;
foreach ($configFloorPlans as $fp) { if ($fp['is_campus_landing']) { $startingFp = $fp; break; } }
if (!$startingFp && !empty($configFloorPlans)) $startingFp = reset($configFloorPlans);

function normalizePaths(&$arr, $slug) {
  if (!is_array($arr)) return;
  $pathKeys = ['equirect_path','featured_image_path','image_path','pano_url','sub_fp_image','floor_plan_image','scene_equirect','thumbnail_path','media_path','marker_image_path','ar_mind_path'];
  foreach ($arr as $k => &$v) {
    if (is_string($v)) {
      if (!$v || strpos($v,'http')===0) continue;
      if (preg_match('#^/[A-Z0-9%]+#',$v)) continue;
      $orgPath = 'organizations/'.$slug.'/';
      // Fix: if already has org path, don't add again
      if (preg_match('#^organizations/'.preg_quote($slug,'#').'/#',$v)) {
        $relativePath = $v;
      } elseif (preg_match('#^'.preg_quote($slug,'#').'/#',$v)) {
        $relativePath = 'organizations/'.$v;
      } elseif (preg_match('#^assets/#',$v)) {
        // Paths stored as just "assets/..." - prepend org path
        $relativePath = $orgPath.$v;
      } else {
        $relativePath = $orgPath.ltrim($v,'/');
      }
      if (in_array($k,$pathKeys)) $v = url($relativePath);
    } elseif (is_array($v)) { normalizePaths($v,$slug); }
  }
}
normalizePaths($configFloorPlans, $slug);

$arFloorPlans = [];
foreach ($configFloorPlans as $fpId => $fp) {
  $mindUrl = $fp['ar_mind_path'] ?? null;
  $mapping = $arMindMappings[$fpId] ?? [];
  $arFloorPlans[] = ['id'=>$fpId,'building_id'=>(int)($fp['building_id']??0),'floor_level'=>(string)($fp['floor_level']??''),'title'=>$fp['title'],'mind_url'=>$mindUrl,'north_angle'=>(float)$fp['north_angle'],'target_mapping'=>$mapping];
}
$defaultArFpId = $startingFp['id'] ?? ($arFloorPlans[0]['id'] ?? null);

$config = [
  'institution_id'=>$iid,'name'=>$org['name'],'short_name'=>$org['short_name'],
  'theme'=>['primary'=>$theme['primary_color']??'#1a365d','secondary'=>$theme['secondary_color']??'#ed8936','accent'=>$theme['accent_color']??'#38b2ac'],
  'ar'=>['buildings'=>$buildings,'floor_plans'=>$arFloorPlans,'default_floor_plan_id'=>$defaultArFpId,'mindar_url'=>url('organizations/'.htmlspecialchars($slug).'/assets/mindar-image-aframe.prod.js')],
  'aframe_url'=>url('organizations/'.htmlspecialchars($slug).'/assets/aframe.min.js'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, touch-action: manipulation">
  <meta name="theme-color" content="#0b0d16">
  <title><?= htmlspecialchars($org['name']) ?> · AR Navigation</title>
  <link rel="stylesheet" href="<?= url('organizations/' . htmlspecialchars($slug) . '/assets/style.css') ?>">
</head>
<body>
  <div id="mode-ar" class="mode">
    <a-scene id="ar-scene" embedded vr-mode-ui="enabled: false"
      renderer="colorManagement: true; physicallyCorrectLights" color-space="sRGB"
      device-orientation-permission-ui="enabled: false">
      <a-camera position="0 0 0" look-controls="enabled: false"></a-camera>
    </a-scene>

    <div id="ar-loading" class="ar-loading">
      <div class="ar-spinner"></div>
      <p id="ar-loading-text">Loading AR&hellip;</p>
    </div>

    <div class="hud top-left" id="ar-hud-top-left">
      <a href="<?= url('organizations/'.htmlspecialchars($slug)) ?>" class="round-btn" id="btn-ar-back" title="Back to Campus"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></a>
      <button class="round-btn" id="btn-fullscreen-toggle" title="Toggle fullscreen"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg></button>
      <div class="ar-building-select-wrap">
        <label for="ar-building-select" class="visually-hidden">Building</label>
        <select id="ar-building-select" class="ar-select" disabled><option value="0">Loading&hellip;</option></select>
      </div>
      <div class="ar-floorplan-select-wrap">
        <label for="ar-floorplan-select" class="visually-hidden">Floor Plan</label>
        <select id="ar-floorplan-select" class="ar-select" disabled><option value="0">Select building first</option></select>
      </div>
    </div>
    <div class="hud top-right" id="ar-hud-top-right">
      <div class="ar-location-chip" id="ar-location-chip">
        <span class="ar-location-icon"></span>
        <span class="ar-location-text">Current location: Unknown</span>
      </div>
    </div>
    <div class="hud bottom-left" id="ar-hud-bottom-left">
      <div class="ar-compass-wrap" title="Compass">
        <svg class="ar-compass-svg" viewBox="0 0 40 40" aria-hidden="true">
          <g stroke="#fff" stroke-width="1.5" stroke-linejoin="round">
            <path d="M20 2.5 L27.5 20 L20 17.4 L12.5 20 Z" fill="#e11d48"/>
            <path d="M20 37.5 L27.5 20 L20 22.6 L12.5 20 Z" fill="#94a3b8"/>
          </g>
          <circle cx="20" cy="20" r="3.4" fill="#fff" stroke="#64748b" stroke-width="1.4"/>
        </svg>
        <span class="ar-compass-label" id="ar-compass-dir">N</span>
      </div>
    </div>

    <div id="ar-result-card" class="ar-result-card hidden">
      <button class="ar-result-close" aria-label="Close">&times;</button>
      <div class="ar-result-image-wrap"><img id="ar-result-image" src="" alt=""></div>
      <div class="ar-result-content">
        <h3 id="ar-result-title"></h3>
        <p id="ar-result-description" class="ar-result-desc"></p>
        <div class="ar-result-actions" id="ar-result-actions"></div>
      </div>
    </div>
  </div>

  <script>
    window.__CONFIG__ = <?= json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  </script>
  <script src="<?= url('organizations/ar.js') ?>?v=<?= time() ?>"></script>
</body>
</html>