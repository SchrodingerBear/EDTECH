<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
require_page('owner.archive');
/**
 * Innovatech PH — owner: Archive & Restore for Institutions and Accounts.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Archive & Restore';
$pageSub = 'Recover deleted institutions and user accounts';
$active = 'Archive & Restore';



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'], $_POST['restore_type'])) {
    $id = (int) $_POST['restore_id'];
    $type = $_POST['restore_type'];
    
    if ($type === 'institution') {
        crud()->update('institutions', ['deleted_at' => null], ['id' => $id]);
        flash('success', 'Institution restored successfully.');
    } elseif ($type === 'user') {
        crud()->update('users', ['deleted_at' => null], ['id' => $id]);
        flash('success', 'User account restored successfully.');
    }
    
    redirect('admin/owner/archive');
}

$tab = $_GET['tab'] ?? 'institutions';

if ($tab === 'users') {
    $archived = crud()->raw(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.deleted_at, r.name AS role_name, i.name AS institution_name
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         LEFT JOIN institutions i ON i.id = u.institution_id
         WHERE u.deleted_at IS NOT NULL
         ORDER BY u.deleted_at DESC"
    )->fetchAll();
} else {
    $archived = crud()->select('institutions i', 'i.id, i.name, i.slug, i.deleted_at', ['i.deleted_at' => ['IS NOT', null]], 'ORDER BY i.deleted_at DESC');
}
?>

<div class="ia-card mb-4">
    <div class="card-head d-flex align-items-center justify-content-between">
        <div class="d-flex gap-3">
            <a href="?tab=institutions" class="text-decoration-none <?= $tab === 'institutions' ? 'fw-bold border-bottom border-2 border-primary pb-2 text-body' : 'text-muted' ?>">Institutions</a>
            <a href="?tab=users" class="text-decoration-none <?= $tab === 'users' ? 'fw-bold border-bottom border-2 border-primary pb-2 text-body' : 'text-muted' ?>">Accounts</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-ia">
            <thead>
                <tr>
                    <?php if ($tab === 'users'): ?>
                        <th>Name</th><th>Email</th><th>Role</th><th>Institution</th><th>Deleted</th><th>Action</th>
                    <?php else: ?>
                        <th>Institution</th><th>Slug</th><th>Deleted</th><th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archived as $item): ?>
                    <tr>
                        <?php if ($tab === 'users'): ?>
                            <td class="fw-bold"><?= h($item['first_name'] . ' ' . $item['last_name']) ?></td>
                            <td><?= h($item['email']) ?></td>
                            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($item['role_name']) ?></span></td>
                            <td><?= h($item['institution_name'] ?? '—') ?></td>
                        <?php else: ?>
                            <td class="fw-bold"><?= h($item['name']) ?></td>
                            <td><span class="badge" style="background:var(--ia-surface-2)"><?= h($item['slug']) ?></span></td>
                        <?php endif; ?>
                        
                        <td class="text-muted"><?= h(date('M j, Y g:i A', strtotime($item['deleted_at']))) ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="restore_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="restore_type" value="<?= $tab === 'users' ? 'user' : 'institution' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-ia"><?= ia_icon('refresh', 14) ?> Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($archived)): ?>
                    <tr>
                        <td colspan="<?= $tab === 'users' ? 6 : 4 ?>">
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
