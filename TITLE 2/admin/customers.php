<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — customers management.
 */
$pageTitle = 'Customers';
$pageSub = 'Manage your laundry customers';
$active = 'Customers';

if (current_role() === 'owner') require_page('customers'); else require_role('owner', 'staff');

$c = crud();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  if (in_array($action, ['create', 'update'], true)) {
    $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
    
    $data = [
      'first_name' => trim($_POST['first_name'] ?? ''),
      'phone' => trim($_POST['phone'] ?? ''),
      'email' => trim($_POST['email'] ?? '') ?: null,
      'address' => trim($_POST['address'] ?? '') ?: null,
      'notes' => trim($_POST['notes'] ?? '') ?: null,
    ];
    
    if ($data['first_name'] === '' || $data['phone'] === '') {
      flash('danger', 'Name and phone are required.');
    } else {
      if ($id > 0) {
        $c->update('customers', $data, ['id' => $id]);
        audit('customer.update', 'customers', 'customer', $id);
        flash('success', 'Customer updated.');
      } else {
        $id = $c->insert('customers', $data);
        audit('customer.create', 'customers', 'customer', $id);
        flash('success', 'Customer added.');
      }
    }
    redirect('customers');
  }

  if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
      $count = $c->count('laundry_orders', ['customer_id' => $id]);
      if ($count > 0) {
        flash('danger', 'This customer has ' . $count . ' order(s) and cannot be deleted.');
      } else {
        $c->delete('customers', ['id' => $id]);
        audit('customer.delete', 'customers', 'customer', $id);
        flash('success', 'Customer deleted.');
      }
    }
    redirect('customers');
  }
}

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

$customers = $c->raw(
  "SELECT cu.*,
          (SELECT COUNT(*) FROM laundry_orders o WHERE o.customer_id = cu.id) AS order_count,
          (SELECT COALESCE(SUM(total),0) FROM laundry_orders o WHERE o.customer_id = cu.id AND o.status='completed') AS total_spent
   FROM customers cu
   WHERE ? = '' OR cu.first_name LIKE ? OR cu.phone LIKE ? OR cu.email LIKE ?
   ORDER BY cu.created_at DESC", [$q, $like, $like, $like]
)->fetchAll();

$edit = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
  $edit = $c->get('customers', (int) $_GET['edit']);
}



require_once __DIR__ . '/layout/header.php';
?>

<?php if ($edit || (isset($_GET['action']) && $_GET['action'] === 'new')): $isEdit = (bool) $edit; $f = $edit ?? []; ?>
  <div class="ia-card">
    <div class="card-head"><h3><?= $isEdit ? 'Edit customer' : 'Add customer' ?></h3><a class="back-link" href="customers">← Back</a></div>
    <div class="card-body card-body-px">
      <form method="post" class="row g-3">
        <input type="hidden" name="form" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><?php endif; ?>
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="first_name" required value="<?= h($f['first_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" required value="<?= h($f['phone'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" value="<?= h($f['email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= h($f['address'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= h($f['notes'] ?? '') ?></textarea></div>
        <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save</button></div>
      </form>
    </div>
  </div>
<?php require __DIR__ . '/layout/footer.php'; return; endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <form method="get" class="search-inline w-100">
      <div class="input-group">
        <input class="form-control" type="text" name="q" value="<?= h($q) ?>" placeholder="Search customers...">
        <button class="btn btn-grad" type="submit"><?= ia_icon('search', 14) ?></button>
      </div>
    </form>
  </div>
  <div class="col-md-5 text-md-end">
    <a class="btn btn-grad" href="customers?action=new"><?= ia_icon('plus', 15) ?> Add customer</a>
  </div>
</div>

<div class="ia-card">
  <div class="card-head"><h3>Customers (<?= count($customers) ?>)</h3></div>
  <div class="table-responsive">
    <table class="table table-ia" data-force-datatable>
      <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Orders</th><th>Total spent</th><th>Added</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($customers as $cm): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="ia-avatar ia-avatar-sm"><?= h(strtoupper(mb_substr($cm['first_name'][0] ?? '', 0, 1))) ?></div>
                <div class="fw-semibold"><?= h($cm['first_name']) ?></div>
              </div>
            </td>
            <td class="text-ia-muted"><?= h($cm['phone']) ?></td>
            <td class="text-ia-muted"><?= h($cm['email'] ?? '—') ?></td>
            <td><?= (int) $cm['order_count'] ?></td>
            <td class="fw-semibold"><?= peso($cm['total_spent']) ?></td>
            <td class="text-ia-muted text-nowrap"><?= h(date('M j, Y', strtotime($cm['created_at']))) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-icon" href="customers?edit=<?= (int) $cm['id'] ?>"><?= ia_icon('edit', 15) ?></a>
              <?php if ((int) $cm['order_count'] === 0): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer?');">
                  <input type="hidden" name="form" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $cm['id'] ?>">
                  <button class="btn btn-sm btn-icon text-danger" type="submit"><?= ia_icon('trash', 15) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="7"><div class="empty-state"><h4>No customers yet</h4><p>Add your first customer to get started.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
