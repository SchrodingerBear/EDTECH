<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — laundry orders management.
 * Actions: list / new / edit / status / pay / delete / view (modal).
 */
$pageTitle = 'Orders';
$pageSub = 'Create, track and manage laundry orders';
$active = 'Orders';

if (current_role() === 'owner') require_page('orders'); else require_role('owner', 'staff');

$c = crud();

/* ------------------------------ POST handlers ------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  /* ---- CREATE / UPDATE ORDER ---- */
  if (in_array($action, ['create', 'update'], true)) {
    try {
      $isEdit = $action === 'update';
      $orderId = $isEdit ? (int) ($_POST['order_id'] ?? 0) : 0;

      $customerId = (int) ($_POST['customer_id'] ?? 0);

      // New customer inline
      if ($customerId === 0) {
        $fname = trim($_POST['c_first_name'] ?? '');
        $phone = trim($_POST['c_phone'] ?? '');
        
        if ($fname === '' || $phone === '') {
          throw new RuntimeException('New customer requires name and phone.');
        }
        
        $customerData = [
          'first_name' => $fname,
          'last_name' => '',  // Required by schema
          'phone' => $phone,
          'email' => trim($_POST['c_email'] ?? '') ?: null,
          'address' => trim($_POST['c_address'] ?? '') ?: null,
        ];
        
        $customerId = $c->insert('customers', $customerData);
        audit('customer.create', 'customers', 'customer', $customerId);
      }

      $status = $_POST['status'] ?? 'pending';
      if (!array_key_exists($status, order_statuses())) $status = 'pending';
      $payment = $_POST['payment_status'] ?? 'unpaid';
      if (!array_key_exists($payment, payment_statuses())) $payment = 'unpaid';
      $pickupType = $_POST['pickup_type'] ?? 'walk_in';
      if (!in_array($pickupType, ['walk_in', 'pickup', 'delivery'], true)) $pickupType = 'walk_in';

      $items = $_POST['items'] ?? [];
      $serviceIds = $_POST['service_id'] ?? [];
      $quantities = $_POST['quantity'] ?? [];
      $unitPrices = $_POST['unit_price'] ?? [];

      if (empty($items) || !is_array($items)) {
        throw new RuntimeException('Add at least one service item to the order.');
      }

      // Build order items with computed line totals
      $orderItems = [];
      $subtotal = 0;
      foreach ($items as $i => $svcId) {
        $svcId = (int) $svcId;
        if ($svcId <= 0) continue;
        $qty = (float) ($quantities[$i] ?? 1);
        if ($qty <= 0) continue;
        $price = (float) ($unitPrices[$i] ?? 0);
        $line = round($qty * $price, 2);
        $subtotal += $line;
        $orderItems[] = ['service_id' => $svcId, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $line];
      }
      if (!$orderItems) {
        throw new RuntimeException('No valid items were added.');
      }

      $deliveryFee = $pickupType === 'delivery' ? (float) ($_POST['delivery_fee'] ?? 0) : 0;
      if ($pickupType !== 'delivery') $deliveryFee = 0;
      $discount = (float) ($_POST['discount'] ?? 0);
      $total = round($subtotal + $deliveryFee - $discount, 2);
      if ($total < 0) $total = 0;

      $amountPaid = (float) ($_POST['amount_paid'] ?? 0);
      if ($amountPaid > $total) $amountPaid = $total;

      $empid = (int) ($_POST['assigned_employee_id'] ?? 0) ?: null;

      $base = [
        'customer_id' => $customerId,
        'assigned_employee_id' => $empid,
        'status' => $status,
        'payment_status' => $amountPaid >= $total ? 'paid' : ($amountPaid > 0 ? 'partial' : $payment),
        'pickup_type' => $pickupType,
        'delivery_address' => $pickupType === 'delivery' ? (trim($_POST['delivery_address'] ?? '') ?: null) : null,
        'pickup_date' => !empty($_POST['pickup_date']) ? $_POST['pickup_date'] : null,
        'notes' => trim($_POST['notes'] ?? '') ?: null,
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'discount' => $discount,
        'total' => $total,
        'amount_paid' => $amountPaid,
      ];

      $newOrderId = db_transaction(function (PDO $pdo) use ($c, $isEdit, $orderId, $base, $orderItems) {
        $crud = new DbCrud($pdo);
        if ($isEdit) {
          $crud->update('laundry_orders', $base, ['id' => $orderId]);
          $crud->delete('order_items', ['order_id' => $orderId]);
          foreach ($orderItems as $it) {
            $crud->insert('order_items', $it + ['order_id' => $orderId]);
          }
          return $orderId;
        } else {
          $orderNo = next_order_number(null, $pdo);
          $orderData = array_merge([
            'order_no' => $orderNo,
            'created_by' => current_user()['id'] ?? null,
          ], $base);
          
          // Only add receipt_token if the column exists
          try {
            $columns = $pdo->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
            $columnNames = array_column($columns, 'Field');
            if (in_array('receipt_token', $columnNames)) {
              $tempToken = generate_receipt_token(0, $orderNo, date('Y-m-d H:i:s'));
              $orderData['receipt_token'] = $tempToken;
            }
          } catch (Throwable $e) {
            // If column check fails, proceed without receipt token
          }
          
          $newId = $crud->insert('laundry_orders', $orderData);
          
          // Update receipt token with actual order ID if column exists
          try {
            $columns = $pdo->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
            $columnNames = array_column($columns, 'Field');
            if (in_array('receipt_token', $columnNames)) {
              $token = generate_receipt_token($newId, $orderNo, date('Y-m-d H:i:s'));
              $crud->update('laundry_orders', ['receipt_token' => $token], ['id' => $newId]);
            }
          } catch (Throwable $e) {
            // If update fails, proceed without receipt token
          }
          
          foreach ($orderItems as $it) {
            $crud->insert('order_items', $it + ['order_id' => $newId]);
          }
          return $newId;
        }
      });
      
      $orderId = $newOrderId;

      if ($isEdit) {
        audit('order.update', 'orders', 'order', $orderId);
        flash('success', 'Order ' . $orderId . ' updated.');
        redirect('admin/orders');
      } else {
        audit('order.create', 'orders', 'order', $orderId);
        flash('success', 'New order created.');
        redirect('admin/orders?action=new&created=1&order_id=' . $orderId);
      }
    } catch (Throwable $e) {
      flash('danger', 'Something went wrong: ' . $e->getMessage());
      // redirect('admin/orders');
    }
  }

  /* ---- CHANGE STATUS ---- */
  if ($action === 'status') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (array_key_exists($status, order_statuses()) && $orderId > 0) {
      db_transaction(function (PDO $pdo) use ($c, $orderId, $status) {
        $crud = new DbCrud($pdo);
        $order = $crud->get('laundry_orders', $orderId);
        if (!$order) return;
        $crud->update('laundry_orders', ['status' => $status], ['id' => $orderId]);

        // Smart inventory: when an order becomes 'completed' (claimed),
        // deduct stock based on configured per-kg usage rates.
        if ($status === 'completed' && $order['status'] !== 'completed') {
          deduct_inventory_for_order($crud, $order);
        }
      });
      audit('order.status', 'orders', 'order', $orderId);
      flash('success', 'Order status updated to ' . order_statuses()[$status] . '.');
    }
    redirect('admin/orders');
  }

  /* ---- RECORD PAYMENT ---- */
  if ($action === 'payment') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $order = $c->get('laundry_orders', $orderId);
    if ($order) {
      $add = (float) ($_POST['add_payment'] ?? 0);
      if ($add > 0) {
        $newPaid = min($order['total'], (float) $order['amount_paid'] + $add);
        $payStatus = abs($newPaid - (float) $order['total']) < 0.005 ? 'paid' : 'partial';
        $c->update('laundry_orders', ['amount_paid' => $newPaid, 'payment_status' => $payStatus], ['id' => $orderId]);
        audit('order.payment', 'orders', 'order', $orderId);
        flash('success', 'Payment of ' . peso($add) . ' recorded.');
      }
    }
    redirect('admin/orders');
  }

  /* ---- DELETE ---- */
  if ($action === 'delete') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    if ($orderId > 0) {
      $c->delete('order_items', ['order_id' => $orderId]);
      $c->delete('laundry_orders', ['id' => $orderId]);
      audit('order.delete', 'orders', 'order', $orderId);
      flash('success', 'Order deleted.');
    }
    redirect('admin/orders');
  }
}

/* -------------------------------- GET data --------------------------------- */

$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = 'WHERE 1=1';
$params = [];
if ($statusFilter !== '' && array_key_exists($statusFilter, order_statuses())) {
  $where .= ' AND o.status = :status';
  $params['status'] = $statusFilter;
}
if ($q !== '') {
  $where .= " AND (o.order_no LIKE :q1 OR cu.first_name LIKE :q2 OR cu.phone LIKE :q3)";
  $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
}

$orders = $c->raw(
  "SELECT o.*, cu.first_name AS customer_name, cu.phone AS customer_phone,
          e.first_name AS emp_first,
              (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items
       FROM laundry_orders o
       JOIN customers cu ON cu.id = o.customer_id
       LEFT JOIN employees e ON e.id = o.assigned_employee_id
       $where
       ORDER BY o.created_at DESC", $params
)->fetchAll();

$services = $c->select('services', '*', ['is_active' => 1], 'ORDER BY name');
$employees = $c->select('employees', '*', ['is_active' => 1], 'ORDER BY first_name');
$customers = $c->select('customers', '*', [], 'ORDER BY first_name');

// Check if receipt system is set up
$receiptSystemEnabled = false;
try {
  $columns = db()->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
  $columnNames = array_column($columns, 'Field');
  $receiptSystemEnabled = in_array('receipt_token', $columnNames);
} catch (Throwable $e) {
  $receiptSystemEnabled = false;
}

// Editing state
$editOrder = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
  $editOrder = $c->get('laundry_orders', (int) $_GET['edit']);
  if ($editOrder) $editOrder['items'] = $c->select('order_items', '*', ['order_id' => $editOrder['id']]);
}

// View modal data
$viewOrder = null;
if (isset($_GET['view']) && (int) $_GET['view'] > 0) {
  $vo = $c->get('laundry_orders', (int) $_GET['view']);
  if ($vo) {
    $cust = $c->get('customers', (int) $vo['customer_id']);
    $viewOrder = $vo;
    $viewOrder['customer'] = $cust ? $cust['first_name'] : '—';
    $viewOrder['customer_phone'] = $cust['phone'] ?? '—';
    $viewOrder['customer_address'] = $cust['address'] ?? '—';
    $viewOrder['items'] = $c->raw(
      "SELECT oi.*, s.name AS service_name, s.unit FROM order_items oi
       JOIN services s ON s.id = oi.service_id WHERE oi.order_id = :id", ['id' => $vo['id']]
    )->fetchAll();
  }
}

require_once __DIR__ . '/layout/header.php';
?>

<?php if ($viewOrder): $vo = $viewOrder; ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="ia-card">
        <div class="card-head">
          <h3>Order <?= h($vo['order_no']) ?></h3>
          <a class="back-link" href="orders">← Back to orders</a>
        </div>
        <div class="card-body card-body-px">
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="ia-micro">Status</div>
              <span class="badge <?= status_badge($vo['status']) ?> fs-14"><?= h(order_statuses()[$vo['status']] ?? $vo['status']) ?></span>
              <span class="badge <?= $vo['payment_status'] === 'paid' ? 'badge-success' : ($vo['payment_status'] === 'partial' ? 'badge-warn' : 'badge-off') ?> fs-14 mx-1"><?= h(ucfirst($vo['payment_status'])) ?></span>
            </div>
            <div class="col-md-6 text-md-end">
              <div class="ia-micro">Created</div>
              <div class="fw-semibold"><?= h(date('M j, Y g:i A', strtotime($vo['created_at']))) ?></div>
            </div>
          </div>

          <table class="table table-ia">
            <thead><tr><th>Service</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Line total</th></tr></thead>
            <tbody>
              <?php foreach ($vo['items'] as $it): ?>
                <tr>
                  <td><?= h($it['service_name']) ?></td>
                  <td class="text-end"><?= rtrim(rtrim(number_format($it['quantity'], 2, '.', ''), '0'), '.') ?> <?= h($it['unit']) ?></td>
                  <td class="text-end"><?= peso($it['unit_price']) ?></td>
                  <td class="text-end fw-semibold"><?= peso($it['line_total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="d-flex justify-content-end">
            <table class="totals-table">
              <tr><td>Subtotal</td><td class="text-end"><?= peso($vo['subtotal']) ?></td></tr>
              <tr><td>Delivery fee</td><td class="text-end"><?= peso($vo['delivery_fee']) ?></td></tr>
              <tr><td>Discount</td><td class="text-end">-<?= peso($vo['discount']) ?></td></tr>
              <tr class="total"><td><strong>Total</strong></td><td class="text-end"><strong><?= peso($vo['total']) ?></strong></td></tr>
              <tr><td>Amount paid</td><td class="text-end"><?= peso($vo['amount_paid']) ?></td></tr>
              <tr><td>Balance</td><td class="text-end"><?= peso($vo['total'] - $vo['amount_paid']) ?></td></tr>
            </table>
          </div>

          <?php if (!empty($vo['notes'])): ?>
            <hr class="my-4">
            <div class="ia-micro mb-1">Notes</div>
            <p class="mb-0"><?= nl2br(h($vo['notes'])) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="ia-card mb-4">
        <div class="card-head"><h3>Customer</h3></div>
        <div class="card-body card-body-px">
          <h5 class="mb-1"><?= h($vo['customer']) ?></h5>
          <div class="ia-micro text-ia-muted mb-2"><?= h($vo['customer_phone']) ?></div>
          <?php if (!empty($vo['customer_address'])): ?><div class="ia-micro text-ia-muted"><?= h($vo['customer_address']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="ia-card">
        <div class="card-head"><h3>Update</h3></div>
        <div class="card-body card-body-px d-grid gap-3">
          <form method="post" class="d-grid gap-2">
            <input type="hidden" name="form" value="status">
            <input type="hidden" name="order_id" value="<?= (int) $vo['id'] ?>">
            <label class="form-label mb-0">Change status</label>
            <div class="input-group">
              <select class="form-select" name="status">
                <?php foreach (order_statuses() as $slug => $label): ?>
                  <option value="<?= $slug ?>" <?= $vo['status'] === $slug ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-grad" type="submit">Update</button>
            </div>
          </form>

          <?php if ($vo['total'] - $vo['amount_paid'] > 0.004): ?>
            <form method="post" class="d-grid gap-2">
              <input type="hidden" name="form" value="payment">
              <input type="hidden" name="order_id" value="<?= (int) $vo['id'] ?>">
              <label class="form-label mb-0">Record payment</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input class="form-control" type="number" step="0.01" min="0.01" name="add_payment" value="<?= h($vo['total'] - $vo['amount_paid']) ?>">
                <button class="btn btn-grad" type="submit">Pay</button>
              </div>
            </form>
          <?php endif; ?>

          <a class="btn btn-outline-ia" href="orders?edit=<?= (int) $vo['id'] ?>"><?= ia_icon('edit', 15) ?> Edit order</a>

          <?php if (!empty($vo['receipt_token'])): 
            $receiptUrl = '../receipt.php?token=' . $vo['receipt_token'];
          ?>
            <a class="btn btn-outline-success" href="<?= h($receiptUrl) ?>" target="_blank"><?= ia_icon('eye', 15) ?> View Receipt</a>
          <?php endif; ?>

          <form method="post" onsubmit="return confirm('Delete this order permanently?');">
            <input type="hidden" name="form" value="delete">
            <input type="hidden" name="order_id" value="<?= (int) $vo['id'] ?>">
            <button class="btn btn-outline-danger w-100" type="submit"><?= ia_icon('trash', 15) ?> Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php require __DIR__ . '/layout/footer.php'; return; endif; ?>

<?php if ($editOrder): $e = $editOrder; ?>
  <?php $formAction = 'update'; ?>
<?php else: ?>
  <?php $formAction = 'create'; $e = null; ?>
<?php endif; ?>

<?php if ($formAction === 'update' || isset($_GET['action']) && $_GET['action'] === 'new'): ?>
  <?php
  $isNew = $formAction === 'create';
  $fields = $e ?? [];
  $pick = $fields['pickup_type'] ?? 'walk_in';
  ?>
  <div class="ia-card mb-4">
    <div class="card-head">
      <h3><?= $isNew ? 'New laundry order' : 'Edit order ' . ($fields['order_no'] ?? '') ?></h3>
      <a class="back-link" href="orders">← Back to orders</a>
    </div>
    <div class="card-body card-body-px">
      <form method="post" id="order-form">
        <input type="hidden" name="form" value="<?= $formAction ?>">
        <?php if (!$isNew): ?><input type="hidden" name="order_id" value="<?= (int) $fields['id'] ?>"><?php endif; ?>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Customer</label>
            <select class="form-select" name="customer_id" id="customer-select">
              <option value="0">+ Add new customer (basic info)</option>
              <?php foreach ($customers as $cm): ?>
                <option value="<?= (int) $cm['id'] ?>" <?= ($fields['customer_id'] ?? null) == $cm['id'] ? 'selected' : '' ?>>
                  <?= h($cm['first_name'] . ' (' . $cm['phone'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Pickup type</label>
            <select class="form-select" name="pickup_type" id="pickup-type">
              <option value="walk_in" <?= $pick === 'walk_in' ? 'selected' : '' ?>>Walk-in</option>
              <option value="pickup" <?= $pick === 'pickup' ? 'selected' : '' ?>>Pickup</option>
              <option value="delivery" <?= $pick === 'delivery' ? 'selected' : '' ?>>Delivery</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Pickup date</label>
            <input type="date" class="form-control" name="pickup_date" value="<?= h($fields['pickup_date'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-2 mt-1" id="new-customer-fields" style="display:none">
          <div class="col-12">
            <div class="ia-card-light p-3">
              <div class="ia-micro mb-2 fw-semibold text-ia-primary">+ New customer — basic info only</div>
              <div class="row g-2">
                <div class="col-md-6"><input class="form-control" name="c_first_name" placeholder="Name *"></div>
                <div class="col-md-6"><input class="form-control" name="c_phone" placeholder="Phone *" inputmode="tel"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Assign staff</label>
            <select class="form-select" name="assigned_employee_id">
              <option value="">— None —</option>
              <?php foreach ($employees as $em): ?>
                <option value="<?= (int) $em['id'] ?>" <?= ($fields['assigned_employee_id'] ?? null) == $em['id'] ? 'selected' : '' ?>>
                  <?= h($em['first_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" id="delivery-address-label">Delivery address</label>
            <input class="form-control" name="delivery_address" id="delivery-address" value="<?= h($fields['delivery_address'] ?? '') ?>" placeholder="Delivery address">
          </div>
        </div>

        <hr class="my-4">

        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="mb-0">Order items</h5>
          <button type="button" class="btn btn-sm btn-grad" id="add-item"><?= ia_icon('plus', 14) ?> Add item</button>
        </div>
        <div id="items-container">
          <?php
          $existingItems = $fields['items'] ?? [];
          if (!$existingItems) $existingItems = [['service_id' => '', 'quantity' => 1, 'unit_price' => '']];
          foreach ($existingItems as $it):
            $svcId = isset($it['service_id']) ? (int) $it['service_id'] : 0;
            $svg = $services;
            ?>
            <div class="row g-2 item-row mb-2">
              <div class="col-md-5">
               <select class="form-select item-service" name="items[]">
                  <?php foreach ($services as $sv): ?>
                    <option value="<?= (int) $sv['id'] ?>" data-price="<?= h($sv['price']) ?>" <?= $svcId === (int) $sv['id'] ? 'selected' : '' ?>>
                      <?= h($sv['name'] . ' — ' . peso($sv['price'] . '/' . $sv['unit'])) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2"><input class="form-control item-qty" type="number" step="0.01" min="0.01" name="quantity[]" value="<?= h($it['quantity'] ?? 1) ?>"></div>
              <div class="col-md-2"><input class="form-control item-price" type="number" step="0.01" min="0" name="unit_price[]" value="<?= h($it['unit_price'] ?? '') ?>"></div>
              <div class="col-md-2 text-end"><span class="item-line form-control-plaintext"><?= peso($it['line_total'] ?? 0) ?></span></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm btn-remove-item"><?= ia_icon('trash', 14) ?></button></div>
            </div>
          <?php endforeach; ?>
        </div>

        <hr class="my-4">

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Subtotal</label>
            <div class="form-control-plaintext fs-5 fw-semibold" id="calc-subtotal"><?= peso($fields['subtotal'] ?? 0) ?></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Delivery fee</label>
            <div class="input-group"><span class="input-group-text">₱</span><input class="form-control" type="number" step="0.01" min="0" name="delivery_fee" id="calc-delivery" value="<?= h($fields['delivery_fee'] ?? '0') ?>"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Discount</label>
            <div class="input-group"><span class="input-group-text">₱</span><input class="form-control" type="number" step="0.01" min="0" name="discount" id="calc-discount" value="<?= h($fields['discount'] ?? '0') ?>"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Total</label>
            <div class="form-control-plaintext fs-5 fw-bold text-ia-primary" id="calc-total"><?= peso($fields['total'] ?? 0) ?></div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <?php foreach (order_statuses() as $slug => $label): ?>
                <option value="<?= $slug ?>" <?= ($fields['status'] ?? 'pending') === $slug ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Amount paid</label>
            <div class="input-group"><span class="input-group-text">₱</span><input class="form-control" type="number" step="0.01" min="0" name="amount_paid" id="calc-paid" value="<?= h($fields['amount_paid'] ?? '0') ?>"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Payment status</label>
            <select class="form-select" name="payment_status">
              <?php foreach (payment_statuses() as $slug => $label): ?>
                <option value="<?= $slug ?>" <?= ($fields['payment_status'] ?? 'unpaid') === $slug ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2"><?= h($fields['notes'] ?? '') ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> <?= $isNew ? 'Create order' : 'Save changes' ?></button>
            <a class="btn btn-outline-ia" href="orders">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>

<?php if ($isNew && isset($_GET['created']) && isset($_GET['order_id'])): 
  $createdOrder = $c->get('laundry_orders', (int)$_GET['order_id']);
  if ($createdOrder && !empty($createdOrder['receipt_token'])):
    $receiptUrl = '../receipt.php?token=' . $createdOrder['receipt_token'];
?>
<!-- Receipt Modal with QR Code -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-grad text-white">
        <h5 class="modal-title"><?= ia_icon('clipboard', 18) ?> Order Created Successfully</h5>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-7">
            <div class="ia-card">
              <div class="card-head"><h5>Order Details</h5></div>
              <div class="card-body card-body-px">
                <div class="row g-2 mb-3">
                  <div class="col-6"><span class="text-ia-muted">Order #</span></div>
                  <div class="col-6 text-end fw-bold fs-5"><?= h($createdOrder['order_no']) ?></div>
                  <div class="col-6"><span class="text-ia-muted">Customer</span></div>
                  <div class="col-6 text-end"><?= h($createdOrder['customer_name'] ?? 'Walk-in') ?></div>
                  <div class="col-6"><span class="text-ia-muted">Total</span></div>
                  <div class="col-6 text-end fw-bold text-ia-primary fs-5"><?= peso($createdOrder['total']) ?></div>
                  <div class="col-6"><span class="text-ia-muted">Paid</span></div>
                  <div class="col-6 text-end"><?= peso($createdOrder['amount_paid']) ?></div>
                  <div class="col-6"><span class="text-ia-muted">Balance</span></div>
                  <div class="col-6 text-end fw-bold <?= ($createdOrder['total'] - $createdOrder['amount_paid']) > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= peso($createdOrder['total'] - $createdOrder['amount_paid']) ?>
                  </div>
                  <div class="col-6"><span class="text-ia-muted">Status</span></div>
                  <div class="col-6 text-end"><span class="badge <?= status_badge($createdOrder['status']) ?>"><?= h(order_statuses()[$createdOrder['status']] ?? $createdOrder['status']) ?></span></div>
                  <div class="col-6"><span class="text-ia-muted">Payment</span></div>
                  <div class="col-6 text-end"><span class="badge <?= $createdOrder['payment_status'] === 'paid' ? 'badge-success' : ($createdOrder['payment_status'] === 'partial' ? 'badge-warn' : 'badge-off') ?>"><?= h(ucfirst($createdOrder['payment_status'])) ?></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-5">
            <div class="ia-card text-center p-4">
              <h6 class="mb-3">Customer Receipt & Tracking</h6>
              <p class="ia-micro text-ia-muted mb-3">Scan QR code or share link for order tracking</p>
              <div id="qr-code" class="mb-3"></div>
              <div class="d-grid gap-2">
                <a href="<?= h($receiptUrl) ?>" target="_blank" class="btn btn-grad">
                  <?= ia_icon('eye', 15) ?> View Receipt
                </a>
                <button class="btn btn-outline-ia" onclick="copyReceiptLink('<?= h($receiptUrl) ?>')">
                  <?= ia_icon('copy', 15) ?> Copy Link
                </button>
                <button class="btn btn-outline-secondary" onclick="printQR()">
                  <?= ia_icon('printer', 15) ?> Print QR
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-grad" data-bs-dismiss="modal">Continue</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    modal.show();
    
    // Generate QR code
    var qr = new QRCode(document.getElementById('qr-code'), {
      text: '<?= h($receiptUrl) ?>',
      width: 200,
      height: 200,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.H
    });
    
    window.copyReceiptLink = function(url) {
      navigator.clipboard.writeText(url).then(function() {
        alert('Receipt link copied to clipboard!');
      });
    };
    
    window.printQR = function() {
      var printWindow = window.open('', '', 'width=400,height=500');
      var qrImg = document.querySelector('#qr-code img');
      if (qrImg) {
        printWindow.document.write('<html><head><title>QR Code - ' + '<?= h($createdOrder['order_no']) ?>' + '</title></head><body style="text-align:center;padding:20px">');
        printWindow.document.write('<h3>Order: ' + '<?= h($createdOrder['order_no']) ?>' + '</h3>');
        printWindow.document.write('<img src="' + qrImg.src + '" alt="QR Code" style="max-width:100%">');
        printWindow.document.write('<p>Scan to track order</p>');
        printWindow.document.close();
        printWindow.print();
      }
    };
  });
</script>
<?php endif; endif; ?>
<script>window.ORDER_SERVICES = <?= json_enc(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'price' => (float)$s['price'], 'unit' => $s['unit']], $services)) ?>;</script>
<script src="../assets/js/admin-orders.js"></script>
<?php require __DIR__ . '/layout/footer.php'; return; endif; ?>

<!-- ------------------------------ LIST VIEW ------------------------------ -->
<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm <?= $statusFilter === '' ? 'btn-grad' : 'btn-outline-ia' ?>" href="orders">All</a>
      <?php foreach (order_statuses() as $slug => $label): ?>
        <a class="btn btn-sm <?= $statusFilter === $slug ? 'btn-grad' : 'btn-outline-ia' ?>" href="orders?status=<?= $slug ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-md-5 text-md-end">
    <a class="btn btn-grad" href="orders?action=new"><?= ia_icon('plus', 15) ?> New order</a>
  </div>
</div>

<div class="ia-card">
  <div class="card-head">
    <h3>All orders (<?= count($orders) ?>)</h3>
    <form method="get" class="search-inline">
      <div class="input-group input-group-sm">
        <input class="form-control" type="text" name="q" value="<?= h($q) ?>" placeholder="Search order #, name, phone">
        <button class="btn btn-grad" type="submit"><?= ia_icon('search', 14) ?></button>
      </div>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-ia" id="orders-table" data-force-datatable>
      <thead>
        <tr>
          <th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Pay</th><th>Status</th><th>Staff</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><a class="back-link" href="orders?view=<?= (int) $o['id'] ?>"><?= h($o['order_no']) ?></a></td>
            <td>
              <div class="fw-semibold"><?= h($o['customer_name']) ?></div>
              <div class="ia-micro text-ia-muted"><?= h($o['customer_phone']) ?></div>
            </td>
            <td class="text-ia-muted"><?= (int) $o['items'] ?></td>
            <td class="fw-semibold"><?= peso($o['total']) ?></td>
            <td>
              <?php if ((float) $o['total'] - (float) $o['amount_paid'] > 0.004): ?>
                <a class="badge <?= $o['payment_status'] === 'partial' ? 'badge-warn' : 'badge-off' ?>" href="orders?view=<?= (int) $o['id'] ?>" title="Record payment"><?= h(ucfirst($o['payment_status'])) ?></a>
              <?php else: ?>
                <span class="badge badge-success">paid</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= status_badge($o['status']) ?>"><?= h(order_statuses()[$o['status']] ?? $o['status']) ?></span></td>
            <td class="text-ia-muted"><?= $o['emp_first'] ? h($o['emp_first'] . ' ' . ($o['emp_last'] ?? '')) : '—' ?></td>
            <td class="text-ia-muted text-nowrap"><?= h(date('M j, Y', strtotime($o['created_at']))) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-icon" href="orders?view=<?= (int) $o['id'] ?>" title="View"><?= ia_icon('eye', 15) ?></a>
              <a class="btn btn-sm btn-icon" href="orders?edit=<?= (int) $o['id'] ?>" title="Edit"><?= ia_icon('edit', 15) ?></a>
              <?php if (!empty($o['receipt_token'])): ?>
                <a class="btn btn-sm btn-icon text-success" href="../receipt.php?token=<?= h($o['receipt_token']) ?>" target="_blank" title="View Receipt"><?= ia_icon('mail', 15) ?></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
          <tr><td colspan="9"><div class="empty-state"><h4>No orders found</h4><p>Try adjusting your filters or create a new order.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  window.ORDER_SERVICES = <?= json_enc(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'price' => (float)$s['price'], 'unit' => $s['unit']], $services)) ?>;
</script>
<script src="../assets/js/admin-orders.js"></script>
<!-- Offline bridge for orders -->
<script type="module" src="../assets/js/ui/orders.offline.js"></script>

<?php require __DIR__ . '/layout/footer.php'; ?>
