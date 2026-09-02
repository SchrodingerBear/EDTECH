<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — employees (staff roster).
 */
$pageTitle = 'Staff Roster';
$pageSub = 'Manage laundry employees';
$active = 'Staff Roster';

require_owner();
require_page('employees');

$c = crud();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  if (in_array($action, ['create', 'update'], true)) {
    $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
    $data = [
      'first_name' => trim($_POST['first_name'] ?? ''),
      'phone' => trim($_POST['phone'] ?? '') ?: null,
      'position' => trim($_POST['position'] ?? '') ?: null,
      'salary' => (float) ($_POST['salary'] ?? 0),
      'hire_date' => !empty($_POST['hire_date']) ? $_POST['hire_date'] : null,
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($data['first_name'] === '') {
      flash('danger', 'Name is required.');
    } else {
      if ($id > 0) {
        $c->update('employees', $data, ['id' => $id]);
        audit('employee.update', 'employees', 'employee', $id);
        flash('success', 'Employee updated.');
      } else {
        $id = $c->insert('employees', $data);
        audit('employee.create', 'employees', 'employee', $id);
        flash('success', 'Employee added.');
      }
    }
    redirect('employees');
  }

  if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $count = $c->count('laundry_orders', ['assigned_employee_id' => $id]);
    if ($count > 0) {
      flash('danger', 'This employee has ' . $count . ' assigned order(s). Reassign them before deleting.');
    } else {
      $c->delete('employees', ['id' => $id]);
      audit('employee.delete', 'employees', 'employee', $id);
      flash('success', 'Employee removed.');
    }
    redirect('employees');
  }
}

$employees = $c->raw(
  "SELECT e.*,
          (SELECT COUNT(*) FROM laundry_orders o WHERE o.assigned_employee_id = e.id) AS jobs
   FROM employees e ORDER BY e.is_active DESC, e.first_name"
)->fetchAll();

$edit = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
  $edit = $c->get('employees', (int) $_GET['edit']);
}

require_once __DIR__ . '/layout/header.php';
?>

<?php if ($edit || (isset($_GET['action']) && $_GET['action'] === 'new')): $isEdit = (bool) $edit; $f = $edit ?? []; ?>
  <div class="ia-card">
    <div class="card-head"><h3><?= $isEdit ? 'Edit employee' : 'Add employee' ?></h3><a class="back-link" href="employees">← Back</a></div>
    <div class="card-body card-body-px">
      <form method="post" class="row g-3">
        <input type="hidden" name="form" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><?php endif; ?>
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="first_name" required value="<?= h($f['first_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= h($f['phone'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Position</label><input class="form-control" name="position" value="<?= h($f['position'] ?? '') ?>" placeholder="e.g. Laundry Operator"></div>
        <div class="col-md-4"><label class="form-label">Monthly salary (₱)</label><input class="form-control" type="number" step="0.01" min="0" name="salary" value="<?= h($f['salary'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Hire date</label><input class="form-control" type="date" name="hire_date" value="<?= h($f['hire_date'] ?? '') ?>"></div>
        <div class="col-12 d-flex align-items-end">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($f['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active / Currently employed</label>
          </div>
        </div>
        <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save</button></div>
      </form>
    </div>
  </div>
<?php require __DIR__ . '/layout/footer.php'; return; endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-8"><h5 class="mb-0 mt-1">Employees (<?= count($employees) ?>)</h5></div>
  <div class="col-md-4 text-md-end"><a class="btn btn-grad" href="employees?action=new"><?= ia_icon('plus', 15) ?> Add employee</a></div>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia" data-force-datatable>
      <thead><tr><th>Name</th><th>Position</th><th>Phone</th><th>Salary</th><th>Jobs</th><th>Hired</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($employees as $em): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="ia-avatar ia-avatar-sm"><?= h(strtoupper(mb_substr($em['first_name'][0] ?? '', 0, 1))) ?></div>
                <div class="fw-semibold"><?= h($em['first_name']) ?></div>
              </div>
            </td>
            <td class="text-ia-muted"><?= h($em['position'] ?? '—') ?></td>
            <td class="text-ia-muted"><?= h($em['phone'] ?? '—') ?></td>
            <td><?= peso($em['salary']) ?></td>
            <td class="text-ia-muted"><?= (int) $em['jobs'] ?></td>
            <td class="text-ia-muted text-nowrap"><?= $em['hire_date'] ? h(date('M j, Y', strtotime($em['hire_date']))) : '—' ?></td>
            <td><span class="badge <?= $em['is_active'] ? 'badge-live' : 'badge-off' ?>"><?= $em['is_active'] ? 'active' : 'inactive' ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-icon" href="employees?edit=<?= (int) $em['id'] ?>"><?= ia_icon('edit', 15) ?></a>
              <?php if ((int) $em['jobs'] === 0): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this employee?');">
                  <input type="hidden" name="form" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $em['id'] ?>">
                  <button class="btn btn-sm btn-icon text-danger" type="submit"><?= ia_icon('trash', 15) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$employees): ?><tr><td colspan="8"><div class="empty-state"><h4>No employees yet</h4><p>Add your laundry team to start assigning orders.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
