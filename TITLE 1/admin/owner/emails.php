<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/**
 * Innovatech PH — owner: email templates.
 */
$pageTitle = 'Email Templates';
$pageSub = 'Invites and system emails sent by the platform';
$active = 'Email Templates';
$bodyClass = 'page-owner-emails';
require_once __DIR__ . '/../layout/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $slug = $_POST['slug'] ?? '';
  crud()->update('email_templates', [
    'subject' => trim($_POST['subject'] ?? ''),
    'body_html' => ($_POST['body_html'] ?? ''),
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
  ], ['slug' => $slug]);
  flash('success', 'Template "' . $slug . '" saved.');
  redirect('admin/owner/emails');
}

$templates = crud()->select('email_templates', '*', [], 'ORDER BY slug');
?>
<div class="row g-4">
  <?php foreach ($templates as $tpl): ?>
    <div class="col-lg-6">
      <div class="ia-card">
        <div class="card-head">
          <h3><?= h($tpl['slug']) ?></h3>
          <span
            class="badge <?= (int) $tpl['is_active'] === 1 ? 'badge-live' : 'badge-off' ?>"><?= (int) $tpl['is_active'] === 1 ? 'Active' : 'Disabled' ?></span>
        </div>
        <form method="post" class="card-body d-grid gap-3">
          <input type="hidden" name="slug" value="<?= h($tpl['slug']) ?>">
          <div><label class="form-label">Subject</label><input class="form-control" name="subject"
              value="<?= h($tpl['subject']) ?>"></div>
          <div>
            <label class="form-label">Body (HTML) — {{link}} placeholder available</label>
            <textarea class="form-control code-area" name="body_html" rows="6"><?= h($tpl['body_html']) ?></textarea>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="act-<?= h($tpl['slug']) ?>" <?= (int) $tpl['is_active'] === 1 ? 'checked' : '' ?>>
            <label class="form-check-label ia-meta-lg" for="act-<?= h($tpl['slug']) ?>">Template
              active</label>
          </div>
          <div class="text-end"><button class="btn btn-grad px-4" type="submit">Save</button></div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jodit/3.24.2/jodit.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jodit/3.24.2/jodit.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('textarea[name="body_html"]').forEach(function (el) {
      Jodit.make(el, {
        height: 300,
        toolbarButtonSize: "small"
      });
    });
  });
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>