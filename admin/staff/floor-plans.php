<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
require_page('staff.floorplans');

$pageTitle = 'Floor Plans';
$pageSub = 'Manage campus maps and building floor plans';
$active = 'Floor Plans';
$bodyClass = 'page-institution-floorplans';
require_once __DIR__ . '/../layout/header.php';

$inst = resolve_active_institution();
if (!$inst) { http_response_code(404); require ROOT_PATH . '/admin/errors/404.php'; exit; }
$iid = (int) $inst['id'];

// Get all buildings for filter
$buildings = crud()->raw('SELECT id, name FROM buildings WHERE institution_id = :iid AND deleted_at IS NULL ORDER BY name', [':iid' => $iid])->fetchAll();

// Handle search and filters
$search = $_GET['search'] ?? '';
$buildingId = $_GET['building_id'] ?? '';

// Build query
$params = [':iid' => $iid];
$where = "fp.institution_id = :iid AND fp.deleted_at IS NULL";

if ($search !== '') {
    $where .= " AND fp.title LIKE :search";
    $params[':search'] = '%' . $search . '%';
}
if ($buildingId !== '') {
    if ($buildingId === 'campus') {
        $where .= " AND fp.building_id IS NULL AND fp.is_campus_landing = 1";
    } else {
        $where .= " AND fp.building_id = :bid";
        $params[':bid'] = (int) $buildingId;
    }
}

$floorPlans = crud()->raw("SELECT fp.*, b.name AS building_name FROM floor_plans fp LEFT JOIN buildings b ON fp.building_id = b.id WHERE $where ORDER BY fp.is_campus_landing DESC, fp.title ASC", $params)->fetchAll();

?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-800 ls-tight">Floor Plans</h3>
        <p class="mb-0 ia-meta-lg">Manage your interactive maps</p>
    </div>
    <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-grad" data-bs-toggle="modal" data-bs-target="#addFloorPlanModal">+ Add Floor Plan</button>
    </div>
</div>

<div class="ia-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search Floor Plans</label>
                <div class="input-group">
                    <span class="input-group-text"><?= ia_icon('search', 16) ?></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by title..." value="<?= h($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Filter by Building</label>
                <select name="building_id" class="form-select">
                    <option value="">All Locations</option>
                    <option value="campus" <?= $buildingId === 'campus' ? 'selected' : '' ?>>Main Campus (Default)</option>
                    <?php foreach ($buildings as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (string)$buildingId === (string)$b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (count($floorPlans) === 0): ?>
        <div class="col-12">
            <div class="empty-state">
                <h4>No floor plans found</h4>
                <p>Try adjusting your search or filters.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($floorPlans as $fp): ?>
        <div class="col-md-6 col-xl-4">
            <div class="ia-card h-100 d-flex flex-column">
                <div class="card-img-top" style="height: 200px; background: url('<?= h(url($fp['image_path'])) ?>') center/cover no-repeat; border-bottom: 1px solid rgba(0,0,0,0.1);"></div>
                <div class="card-body d-flex flex-column flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h4 class="fw-bold mb-0"><?= h($fp['title']) ?></h4>
                        <?php if ($fp['is_campus_landing'] == 1): ?>
                            <span class="badge bg-primary">Campus Default</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-4">
                        <?= $fp['building_name'] ? 'Building: ' . h($fp['building_name']) : 'Unassigned / Campus' ?>
                        <?= $fp['floor_level'] ? ' | Level: ' . h($fp['floor_level']) : '' ?>
                    </p>
                    <div class="mt-auto d-flex gap-2">
                        <a href="floor-plans/studio-view.php?id=<?= $fp['id'] ?>" class="btn btn-outline-ia flex-grow-1">Open Studio</a>
                        <button type="button" class="btn btn-outline-primary" onclick="editFloorPlan(<?= $fp['id'] ?>)">Edit</button>
                        <?php if ($fp['is_campus_landing'] == 0): ?>
                            <button class="btn btn-outline-danger" onclick="deleteFloorPlan(<?= $fp['id'] ?>)">Delete</button>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary disabled" title="Undeletable default campus map" disabled>Default</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Floor Plan Modal -->
<div class="modal fade" id="addFloorPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addFloorPlanForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Floor Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Ground Floor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Building</label>
                        <select name="building_id" class="form-select">
                            <option value="">Campus (No specific building)</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Floor Level</label>
                        <input type="text" name="floor_level" class="form-control" placeholder="e.g. 1st Floor, Basement">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Floor Plan Image <span class="text-danger">*</span></label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                        <div class="form-text text-muted">
                            Recommended: High quality landscape image (e.g., 1920x1080) for best static display across all devices.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveFp">Upload & Open Studio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Floor Plan Modal -->
<div class="modal fade" id="editFloorPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editFloorPlanForm">
                <input type="hidden" name="floor_plan_id" id="edit_fp_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Floor Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Building</label>
                        <select name="building_id" id="edit_building_id" class="form-select">
                            <option value="">Campus (No specific building)</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Floor Level</label>
                        <input type="text" name="floor_level" id="edit_floor_level" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Replace Image (Optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="form-text text-muted">
                            Leave this empty if you don't want to change the image. Note: changing the image aspect ratio may offset existing markers!
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnUpdateFp">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteFloorPlan(id) {
    if (confirm('Are you sure you want to delete this floor plan?')) {
        // Implement deletion logic via ajax or form POST
        alert('Delete functionality will be implemented in actions.php');
    }
}

document.getElementById('addFloorPlanForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveFp');
    btn.disabled = true;
    btn.textContent = 'Uploading...';
    
    const formData = new FormData(this);
    formData.append('action', 'create_floor_plan');
    
    fetch('floor-plan/actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'floor-plan/studio-view.php?id=' + data.floor_plan_id;
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = 'Upload & Open Studio';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Upload failed.');
        btn.disabled = false;
        btn.textContent = 'Upload & Open Studio';
    });
});

function editFloorPlan(id) {
    fetch('../institution/floor-plan/actions.php?action=get_floor_plan&floor_plan_id=' + id)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const fp = data.floor_plan;
            document.getElementById('edit_fp_id').value = fp.id;
            document.getElementById('edit_title').value = fp.title;
            document.getElementById('edit_building_id').value = fp.building_id || '';
            document.getElementById('edit_floor_level').value = fp.floor_level || '';
            const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editFloorPlanModal'));
            editModal.show();
        } else {
            alert('Failed to load floor plan data.');
        }
    });
}

document.getElementById('editFloorPlanForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnUpdateFp');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    const formData = new FormData(this);
    formData.append('action', 'edit_floor_plan');
    
    fetch('../institution/floor-plan/actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = 'Save Changes';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Update failed.');
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
