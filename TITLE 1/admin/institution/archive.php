<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_page('admin.archive');
/**
 * Innovatech PH — admin: Archive & Restore for Buildings, Rooms, Tours, Floor Plans.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Archive & Restore';
$pageSub = 'Recover deleted content for your institution';
$active = 'Archive & Restore';

$pdo = db();
$iid = (int) current_institution()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'], $_POST['restore_type'])) {
    $id = (int) $_POST['restore_id'];
    $type = $_POST['restore_type'];
    
    $tables = [
        'building' => 'buildings',
        'room' => 'rooms',
        'tour' => 'tour_scenes',
        'floorplan' => 'floor_plans'
    ];
    
    if (isset($tables[$type])) {
        $table = $tables[$type];
        $pdo->prepare("UPDATE {$table} SET deleted_at = NULL WHERE id = ? AND institution_id = ?")->execute([$id, $iid]);
        flash('success', ucfirst(str_replace('_', ' ', $type)) . ' restored successfully.');
    }
    
    redirect('admin/institution/archive?tab=' . $type);
}

$tab = $_GET['tab'] ?? 'building';
$archived = [];

if ($tab === 'building') {
    $archived = $pdo->prepare("SELECT id, name, deleted_at FROM buildings WHERE institution_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC");
} elseif ($tab === 'room') {
    $archived = $pdo->prepare("SELECT r.id, r.name, b.name as building_name, r.deleted_at FROM rooms r LEFT JOIN buildings b ON b.id = r.building_id WHERE r.institution_id = ? AND r.deleted_at IS NOT NULL ORDER BY r.deleted_at DESC");
} elseif ($tab === 'tour') {
    $archived = $pdo->prepare("SELECT id, title as name, deleted_at FROM tour_scenes WHERE institution_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC");
} elseif ($tab === 'floorplan') {
    $archived = $pdo->prepare("SELECT id, title as name, deleted_at FROM floor_plans WHERE institution_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC");
}

if ($archived) {
    $archived->execute([$iid]);
    $archived = $archived->fetchAll();
}
?>

<div class="ia-card mb-4">
    <div class="card-head d-flex align-items-center justify-content-between">
        <div class="d-flex gap-3">
            <a href="?tab=building" class="text-decoration-none <?= $tab === 'building' ? 'fw-bold border-bottom border-2 border-primary pb-2 text-body' : 'text-muted' ?>">Buildings</a>
            <a href="?tab=room" class="text-decoration-none <?= $tab === 'room' ? 'fw-bold border-bottom border-2 border-primary pb-2 text-body' : 'text-muted' ?>">Rooms & Areas</a>
            <a href="?tab=tour" class="text-decoration-none <?= $tab === 'tour' ? 'fw-bold border-bottom border-2 border-primary pb-2 text-body' : 'text-muted' ?>">360 Tours</a>
            <a href="?tab=floorplan" class="text-decoration-none <?= $tab === 'floorplan' ? 'fw-bold border-bottom border-2 border-primary pb-2 text-body' : 'text-muted' ?>">Floor Plans</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-ia">
            <thead>
                <tr>
                    <th>Name</th>
                    <?php if ($tab === 'room'): ?><th>Building</th><?php endif; ?>
                    <th>Deleted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archived as $item): ?>
                    <tr>
                        <td class="fw-bold"><?= h($item['name']) ?></td>
                        <?php if ($tab === 'room'): ?>
                            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($item['building_name'] ?? '—') ?></span></td>
                        <?php endif; ?>
                        
                        <td class="text-muted"><?= h(date('M j, Y g:i A', strtotime($item['deleted_at']))) ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="restore_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="restore_type" value="<?= $tab ?>">
                                <button type="submit" class="btn btn-sm btn-outline-ia"><?= ia_icon('refresh', 14) ?> Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($archived)): ?>
                    <tr>
                        <td colspan="<?= $tab === 'room' ? 4 : 3 ?>">
                            <div class="empty-state">
                                <div class="empty-icon"><?= ia_icon('refresh', 24) ?></div>
                                <h4>No archived records</h4>
                                <p>Deleted items will appear here.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
