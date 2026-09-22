<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/**
 * Innovatech PH — owner: product landing editor (platform_settings).
 */
$pageTitle = 'Landing Editor';
$pageSub = 'The Innovatech PH product site shown at the root';
$active = 'Landing Editor';
$bodyClass = 'page-landing-editor';
require_once __DIR__ . '/../layout/header.php';

$settings = crud()->get('platform_settings', 1) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crud()->update('platform_settings', [
        'product_name' => trim($_POST['product_name'] ?? ''),
        'company_name' => trim($_POST['company_name'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'hero_image_path' => trim($_POST['hero_image_path'] ?? ''),
        'landing_html' => ($_POST['landing_html'] ?? ''),
        'updated_by' => (int) current_user()['id'],
    ], ['id' => 1]);
    flash('success', 'Landing settings saved. The product landing now reads these values.');
    redirect('admin/owner/landing');
}

$heroFallback = url('public/campus-hero.png');
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="ia-card">
      <div class="card-head"><h3>Brand & copy</h3></div>
      <div class="card-body">
        <form method="post" class="d-grid gap-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Company name</label>
              <input class="form-control" name="company_name" value="<?= h($settings['company_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Product name</label>
              <input class="form-control" name="product_name" value="<?= h($settings['product_name'] ?? '') ?>">
            </div>
          </div>
          <div>
            <label class="form-label">Contact email</label>
            <input class="form-control" name="contact_email" value="<?= h($settings['contact_email'] ?? '') ?>">
          </div>
          <div>
            <label class="form-label">Hero image URL</label>
            <input class="form-control" name="hero_image_path" value="<?= h($settings['hero_image_path'] ?? $heroFallback) ?>">
            <div class="form-text">Files live in <code>public/</code> — e.g. <code>public/campus-hero.png</code></div>
          </div>
          <div>
            <label class="form-label">Custom landing HTML (optional, appended before footer)</label>
            <textarea class="form-control code-area" name="landing_html" rows="8"><?= h($settings['landing_html'] ?? '') ?></textarea>
          </div>
          <div class="text-end"><button class="btn btn-grad px-4" type="submit">Save landing settings</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Preview</h3><a class="back-link" href="<?= url('/') ?>" target="_blank">Open site</a></div>
      <div class="card-body">
        <img src="<?= h($settings['hero_image_path'] ? url($settings['hero_image_path']) : $heroFallback) ?>" alt="Hero preview" class="hero-preview">
        <ul class="mt-3 d-grid gap-2 landing-facts">
          <li><strong>Company:</strong> <?= h($settings['company_name'] ?? '') ?></li>
          <li><strong>Product:</strong> <?= h($settings['product_name'] ?? '') ?></li>
          <li><strong>Email:</strong> <?= h($settings['contact_email'] ?? '') ?></li>
        </ul>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Partner campuses</h3><a class="back-link" href="institutions">Manage</a></div>
      <div class="card-body ia-meta-lg">
        Published, active institutions automatically appear in the landing's "Partner Campuses" section.
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>