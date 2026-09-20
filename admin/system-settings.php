<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — system settings (owner only).
 * Shop info, logo, delivery fee. Inventory config is on a separate page.
 */
$pageTitle = 'System Settings';
$pageSub = 'Shop information and system configuration';
$active = 'System Settings';

require_owner();
require_page('system_settings');

$c = crud();
$settings = $c->get('settings', 1) ?? [];

$uploadsDir = ROOT_PATH . '/public/uploads';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  if ($action === 'settings') {
    $data = [
      'business_name' => trim($_POST['business_name'] ?? '') ?: APP_NAME,
      'address' => trim($_POST['address'] ?? '') ?: null,
      'phone' => trim($_POST['phone'] ?? '') ?: null,
      'email' => trim($_POST['email'] ?? '') ?: null,
      'delivery_fee' => (float) ($_POST['delivery_fee'] ?? 0),
    ];

    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
      $path = save_upload('logo', $uploadsDir, $settings['logo_path'] ?? null);
      if ($path) $data['logo_path'] = $path;
    }

    $c->update('settings', $data, ['id' => 1]);
    audit('system_settings.update', 'settings');
    flash('success', 'System settings saved.');
    redirect('system-settings');
  }

  if ($action === 'pickup_order') {
    $receiptToken = trim($_POST['receipt_token'] ?? '');
    
    if (empty($receiptToken)) {
      flash('danger', 'Please enter a receipt token or order ID.');
    } else {
      // Try to find order by receipt token or order ID
      $order = null;
      
      // First try by receipt token
      try {
        $columns = db()->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
        $columnNames = array_column($columns, 'Field');
        
        if (in_array('receipt_token', $columnNames)) {
          $order = $c->raw(
            "SELECT * FROM laundry_orders WHERE receipt_token = ?",
            [$receiptToken]
          )->fetch();
        }
      } catch (Throwable $e) {
        // Continue to try by order ID
      }
      
      // If not found by token, try by order ID
      if (!$order && is_numeric($receiptToken)) {
        $order = $c->get('laundry_orders', (int) $receiptToken);
      }
      
      if (!$order) {
        flash('danger', 'Order not found. Please check the receipt token or order ID.');
      } else {
        // Update order status to completed (picked up)
        $c->update('laundry_orders', ['status' => 'completed'], ['id' => $order['id']]);
        audit('order.status', 'orders', 'order', $order['id']);
        flash('success', 'Order #' . h($order['order_no']) . ' marked as picked up/completed.');
      }
    }
    redirect('system-settings');
  }
}

$settings = $c->get('settings', 1);

function save_upload(string $field, string $dir, ?string $old): ?string
{
  try {
    $info = pathinfo($_FILES[$field]['name']);
    $ext = strtolower($info['extension'] ?? '');
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
    if (!in_array($ext, $allowed, true)) return null;
    $name = $field . '_' . date('Ymd_His') . '_' . random_token(4) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
      if ($old && str_starts_with($old, 'public/uploads/') && basename($old) !== $name) {
        @unlink(ROOT_PATH . '/' . $old);
      }
      return 'public/uploads/' . $name;
    }
  } catch (Throwable $e) {
  }
  return null;
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="ia-card">
      <div class="card-head"><h3>Shop information</h3></div>
      <div class="card-body card-body-px">
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="form" value="settings">
          <div class="col-12"><label class="form-label">Business name</label><input class="form-control" name="business_name" value="<?= h($settings['business_name']) ?>"></div>
          <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= h($settings['address'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= h($settings['phone'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" value="<?= h($settings['email'] ?? '') ?>"></div>
          <div class="col-md-6">
            <label class="form-label">Default delivery fee (₱)</label>
            <input class="form-control" type="number" step="0.01" min="0" name="delivery_fee" value="<?= h($settings['delivery_fee']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Logo</label>
            <input class="form-control" type="file" name="logo" accept="image/*">
            <?php if ($settings['logo_path']): ?>
              <img src="<?= h('../' . $settings['logo_path']) ?>" alt="logo" class="setting-thumb mt-2">
            <?php endif; ?>
          </div>
          <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save settings</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="ia-card">
      <div class="card-head"><h3>Order Pickup</h3></div>
      <div class="card-body card-body-px">
        <form method="post" class="row g-3">
          <input type="hidden" name="form" value="pickup_order">
          <div class="col-12">
            <label class="form-label">Receipt Token / Order ID</label>
            <input class="form-control" name="receipt_token" placeholder="Enter receipt token or order ID" required>
            <small class="text-muted">Enter the receipt token from the customer's receipt link or the order ID</small>
          </div>
          <div class="col-12">
            <button class="btn btn-success" type="submit"><?= ia_icon('check', 15) ?> Mark as Picked Up</button>
          </div>
        </form>
      </div>
    </div>
    
    <div class="ia-card mt-4">
      <div class="card-head"><h3>Quick links</h3></div>
      <div class="card-body d-grid gap-2 card-body-px">
        <a class="btn btn-outline-ia" href="services">Manage services &amp; pricing per kg</a>
        <a class="btn btn-outline-ia" href="inventory-settings">Configure inventory consumption</a>
        <a class="btn btn-outline-ia" href="inventory">Manage inventory stock</a>
        <a class="btn btn-outline-ia" href="accounts">Manage staff accounts</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
