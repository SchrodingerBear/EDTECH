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
              <img src="<?= h(url($settings['logo_path'])) ?>" alt="logo" class="setting-thumb mt-2">
            <?php endif; ?>
          </div>
          <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save settings</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="ia-card">
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
