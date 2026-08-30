<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
/**
 * Lavadora — business settings.
 */
$pageTitle = 'Business Settings';
$pageSub = 'Shop information and landing page content';
$active = 'Business Settings';

require_owner();
require_page('settings');

$c = crud();
$settings = $c->get('settings', 1) ?? [];

$uploadsDir = ROOT_PATH . '/public/uploads';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['form'] ?? '';

  if ($action === 'settings') {
    $data = [
      'business_name' => trim($_POST['business_name'] ?? '') ?: APP_NAME,
      'tagline' => trim($_POST['tagline'] ?? '') ?: null,
      'address' => trim($_POST['address'] ?? '') ?: null,
      'phone' => trim($_POST['phone'] ?? '') ?: null,
      'email' => trim($_POST['email'] ?? '') ?: null,
      'delivery_fee' => (float) ($_POST['delivery_fee'] ?? 0),
      'landing_html' => json_encode([
        'hero_headline' => trim($_POST['hero_headline'] ?? ''),
        'hero_sub' => trim($_POST['hero_sub'] ?? ''),
        'announcement' => trim($_POST['announcement'] ?? ''),
      ], JSON_UNESCAPED_UNICODE),
    ];

    // Logo upload
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
      $path = save_upload('logo', $uploadsDir, $settings['logo_path'] ?? null);
      if ($path) $data['logo_path'] = $path;
    }
    // Hero upload
    if (!empty($_FILES['hero']['tmp_name']) && is_uploaded_file($_FILES['hero']['tmp_name'])) {
      $path = save_upload('hero', $uploadsDir, $settings['hero_image_path'] ?? null);
      if ($path) $data['hero_image_path'] = $path;
    }

    $c->update('settings', $data, ['id' => 1]);
    audit('settings.update', 'settings');
    flash('success', 'Business settings saved.');
    redirect('settings');
  }
}

$landing = json_decode($settings['landing_html'] ?? 'null', true) ?: [];
$settings = $c->get('settings', 1);

require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Business details</h3></div>
      <div class="card-body card-body-px">
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="form" value="settings">
          <div class="col-md-6"><label class="form-label">Business name</label><input class="form-control" name="business_name" value="<?= h($settings['business_name']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="<?= h($settings['tagline'] ?? '') ?>"></div>
          <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= h($settings['address'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= h($settings['phone'] ?? '') ?>"></div>
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
          <div class="col-md-6">
            <label class="form-label">Hero background</label>
            <input class="form-control" type="file" name="hero" accept="image/*">
            <?php if ($settings['hero_image_path']): ?>
              <img src="<?= h(url($settings['hero_image_path'])) ?>" alt="hero" class="setting-thumb mt-2">
            <?php endif; ?>
          </div>

          <hr class="my-2">
          <h6 class="mt-1">Public landing page content</h6>
          <div class="col-12"><label class="form-label">Hero headline</label><input class="form-control" name="hero_headline" value="<?= h($landing['hero_headline'] ?? 'Fresh, clean laundry at your doorstep') ?>"></div>
          <div class="col-12"><label class="form-label">Hero subtitle</label><textarea class="form-control" name="hero_sub" rows="2"><?= h($landing['hero_sub'] ?? '') ?></textarea></div>
          <div class="col-12"><label class="form-label">Announcement bar</label><input class="form-control" name="announcement" value="<?= h($landing['announcement'] ?? '') ?>" placeholder="e.g. Free delivery on orders over ₱500"></div>

          <div class="col-12"><button class="btn btn-grad" type="submit"><?= ia_icon('save', 15) ?> Save settings</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="ia-card">
      <div class="card-head"><h3>Public site link</h3></div>
      <div class="card-body card-body-px">
        <p class="ia-micro">Your customers can view services and book pickups on your public site.</p>
        <a class="btn btn-outline-ia w-100" href="<?= url('/') ?>" target="_blank"><?= ia_icon('globe', 15) ?> Open public site</a>
      </div>
    </div>
  </div>
</div>

<?php
// Local helper for uploads
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
require __DIR__ . '/layout/footer.php';
?>
