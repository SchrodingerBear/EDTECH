<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/**
 * Innovatech PH — owner: platform settings (SMTP, directories).
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'System Settings';
$pageSub = 'System setup and configuration';
$active = 'System Settings';

$settings = crud()->get('platform_settings', 1) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crud()->update('platform_settings', [
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_port' => (int) ($_POST['smtp_port'] ?? 587),
        'smtp_username' => trim($_POST['smtp_username'] ?? ''),
        'smtp_password_enc' => ($_POST['smtp_password'] ?? '') !== '' ? (string) base64_encode($_POST['smtp_password']) : ($settings['smtp_password_enc'] ?? ''),
        'smtp_from_name' => trim($_POST['smtp_from_name'] ?? ''),
        'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
    ], ['id' => 1]);
    flash('success', 'Settings saved. Emails will flow through SMTP when configured.');
    redirect('admin/owner/settings');
}
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="ia-card">
      <div class="card-head"><h3>SMTP / outgoing email</h3></div>
      <div class="card-body">
        <form method="post" class="d-grid gap-3">
          <div class="row g-3">
            <div class="col-md-7"><label class="form-label">SMTP host</label><input class="form-control" name="smtp_host" value="<?= h($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com"></div>
            <div class="col-md-5"><label class="form-label">Port</label><input type="number" class="form-control" name="smtp_port" value="<?= (int) ($settings['smtp_port'] ?? 587) ?>"></div>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="smtp_username" value="<?= h($settings['smtp_username'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Password (leave blank to keep)</label>
              <div class="pw-group">
                <input type="password" class="form-control" name="smtp_password" id="smtp-pw">
                <button type="button" class="btn btn-outline-ia btn-shrink pw-eye" data-pw="smtp-pw" title="Show password"><?= ia_icon('eye', 14) ?></button>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">From name</label><input class="form-control" name="smtp_from_name" value="<?= h($settings['smtp_from_name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">From email</label><input type="email" class="form-control" name="smtp_from_email" value="<?= h($settings['smtp_from_email'] ?? '') ?>"></div>
          </div>
          <div class="text-end"><button class="btn btn-grad px-4" type="submit">Save SMTP</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="ia-card">
      <div class="card-head"><h3>Directories</h3></div>
      <div class="card-body d-grid gap-2" style="font-size:13.5px">
        <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--ia-border)">
          <span class="text-muted">Organization root</span><code>organizations/</code>
        </div>
        <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--ia-border)">
          <span class="text-muted">Template pack</span><code>templates/org_pack/</code>
        </div>
        <div class="d-flex justify-content-between py-2">
          <span class="text-muted">A-Frame runtime</span><code>aframe.min.js (local)</code>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>