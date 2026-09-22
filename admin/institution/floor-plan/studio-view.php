<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_admin();
require_page('admin.floorplans');

$fpId = $_GET['id'] ?? null;
if (!$fpId) {
    header('Location: ../floor-plans.php');
    exit;
}

$inst = resolve_active_institution();
if (!$inst) {
    http_response_code(404);
    require ROOT_PATH . '/admin/errors/404.php';
    exit;
}
$iid = (int) $inst['id'];

$floorPlan = crud()->raw('SELECT * FROM floor_plans WHERE id = :id AND institution_id = :iid', [':id' => $fpId, ':iid' => $iid])->fetch();
if (!$floorPlan) {
    http_response_code(404);
    require ROOT_PATH . '/admin/errors/404.php';
    exit;
}

// Fetch lists for bindings
$scenes = crud()->raw('SELECT s.id, s.title, s.building_id, s.room_id, s.campus_area_id, b.name AS building_name, r.name AS room_name, a.name AS area_name FROM tour_scenes s LEFT JOIN buildings b ON b.id=s.building_id LEFT JOIN rooms r ON r.id=s.room_id LEFT JOIN campus_areas a ON a.id=s.campus_area_id WHERE s.institution_id = :iid AND s.deleted_at IS NULL ORDER BY s.title', [':iid' => $iid])->fetchAll();
$otherFloorPlans = crud()->raw('SELECT id, title FROM floor_plans WHERE institution_id = :iid AND deleted_at IS NULL AND id != :id ORDER BY title', [':iid' => $iid, ':id' => $fpId])->fetchAll();
$buildings = crud()->raw('SELECT id, name FROM buildings WHERE institution_id = :iid AND deleted_at IS NULL ORDER BY name', [':iid' => $iid])->fetchAll();
$rooms = crud()->raw('SELECT id, name, building_id FROM rooms WHERE institution_id = :iid AND deleted_at IS NULL ORDER BY name', [':iid' => $iid])->fetchAll();
$areas = crud()->raw('SELECT id, name, building_id FROM campus_areas WHERE institution_id = :iid AND deleted_at IS NULL ORDER BY name', [':iid' => $iid])->fetchAll();

$pageTitle = 'Studio: ' . h($floorPlan['title']);
$pageSub = 'Place circular markers and link 360 tour scenes';
$active = 'Floor Plans';
$bodyClass = 'page-floor-plans page-institution-floorplans-studio';

// Enqueue styles for studio
$extraHead = '<link rel="stylesheet" href="studio.css?v=4">';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <div>
        <a href="../floor-plans.php" class="back-link d-inline-flex align-items-center gap-1 mb-1 text-decoration-none">
            <?= ia_icon('arrow-left', 14) ?> Back to Floor Plans
        </a>
        <h3 class="fw-800 ls-tight mb-0"><?= h($floorPlan['title']) ?> — Studio</h3>
    </div>
    <div class="ms-auto d-flex gap-2">
        <button id="save-studio-btn" class="btn btn-grad px-4">
            <?= ia_icon('check', 16) ?> Save Changes
        </button>
    </div>
</div>

<div class="row g-3" style="min-height: calc(100vh - 200px);">
    <!-- Tools Sidebar -->
    <div class="col-lg-3 col-xl-2">
        <div class="ia-card h-100 p-3">
            <h6 class="fw-bold mb-2">Marker Tools</h6>
            <p class="text-muted small mb-3">Click a tool then click the map, or drag directly onto the floor plan.</p>
            
            <div class="marker-tools-grid mb-3" id="marker-tools-list">
                <button type="button" class="marker-tool draggable" draggable="true" data-type="360" title="360° Scene marker">
                    <span class="tool-icon tool-icon-360">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </span>
                    <span class="tool-label">360° Scene</span>
                </button>

                <button type="button" class="marker-tool draggable" draggable="true" data-type="floorplan" title="Link to another floor plan">
                    <span class="tool-icon tool-icon-floorplan">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                    </span>
                    <span class="tool-label">Floor Plan</span>
                </button>

                <button type="button" class="marker-tool draggable" draggable="true" data-type="ar" title="AR Landmark marker">
                    <span class="tool-icon tool-icon-ar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                    <span class="tool-label">AR Landmark</span>
                </button>

                <button type="button" class="marker-tool draggable" draggable="true" data-type="compass" title="Compass direction marker">
                    <span class="tool-icon tool-icon-compass">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                    </span>
                    <span class="tool-label">Compass</span>
                </button>

                <button type="button" class="marker-tool draggable" draggable="true" data-type="info" title="Information popup marker">
                    <span class="tool-icon tool-icon-info">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </span>
                    <span class="tool-label">Information</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Canvas Workspace -->
    <div class="col-lg-6 col-xl-7">
        <div class="ia-card h-100 p-0 overflow-hidden d-flex flex-column" id="studio-canvas-container" style="background: #0f172a;">
            <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between gap-2 flex-wrap text-light"
                    style="background: rgba(0,0,0,0.25); min-height: 42px;">
                    <div class="small text-muted" id="canvas-status-hint">
                        Drag dots to reposition. Click a marker to edit its properties.
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                        <div id="marker-filter-bar" class="d-flex align-items-center gap-1"></div>
                        <div class="badge bg-primary text-white px-2 py-1" id="active-tool-badge" style="display:none;">
                            Placement Mode: Click map to place (<span id="active-tool-name"></span>)
                        </div>
                    </div>
                </div>
            <div class="p-3 d-flex align-items-center justify-content-center flex-grow-1 overflow-auto position-relative" style="min-height: 520px;">
                <div id="studio-canvas" class="map-stage position-relative" style="--fp-ar: <?= (float) ($floorPlan['aspect_ratio'] ?: 1.777) ?>; width: 100%;">
                    <img src="<?= h(url($floorPlan['image_path'])) ?>" alt="floor plan" class="map-stage-img" draggable="false">
                    <div id="markers-layer" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events: none;">
                        <!-- Markers will be rendered here via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Properties Panel -->
    <div class="col-lg-3 col-xl-3">
        <div class="ia-card h-100 p-3" id="properties-panel">
            <h6 class="fw-bold mb-3 border-bottom pb-2" id="props-panel-title">Properties</h6>
            <div id="properties-content">
                <div class="text-muted text-center py-5">
                    <div class="mb-2"><?= ia_icon('map-pin', 32) ?></div>
                    Select a marker or choose a tool on the left to place one.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Data for JS -->
<script>
    const STUDIO_DATA = {
        baseUrl: <?= json_encode(BASE_URL) ?>,
        floorPlanId: <?= (int)$fpId ?>,
        institutionId: <?= (int)$iid ?>,
        scenes: <?= json_encode($scenes, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
        floorPlans: <?= json_encode($otherFloorPlans, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
        buildings: <?= json_encode($buildings, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
        rooms: <?= json_encode($rooms, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
        areas: <?= json_encode($areas, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<script src="studio.js?v=2"></script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>
