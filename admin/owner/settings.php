<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/** SMART: owner — platform settings (SMTP, directories, email debug). */
$pageTitle = 'System Settings';
$pageSub = 'System setup and configuration';
$active = 'System Settings';
$bodyClass = 'page-owner-settings';
require_once __DIR__ . '/../layout/header.php';

$settings = crud()->get('platform_settings', 1) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $host = trim($_POST['smtp_host'] ?? '');
        $port = (int) ($_POST['smtp_port'] ?? 465);
        $smtpUser = trim($_POST['smtp_username'] ?? '');
        $newPass = ($_POST['smtp_password'] ?? '') !== '';
        $fromEmail = trim($_POST['smtp_from_email'] ?? '');
        $fromName = trim($_POST['smtp_from_name'] ?? '');
        $contact = trim($_POST['contact_email'] ?? '');

        if ($host !== '' && !in_array($port, [25, 465, 587], true)) {
            flash('error', 'Invalid SMTP port. Use 465 (SSL), 587 (STARTTLS), or 25.');
            redirect('admin/owner/settings#smtp');
        }
        if ($host !== '' && $smtpUser === '') {
            flash('error', 'SMTP host is set but username is empty. Fill in the SMTP username too.');
            redirect('admin/owner/settings#smtp');
        }
        if ($host !== '' && $newPass && trim($_POST['smtp_password']) === '') {
            flash('error', 'Password cannot be blank.');
            redirect('admin/owner/settings#smtp');
        }

        crud()->update('platform_settings', [
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_username' => $smtpUser,
            'smtp_password_enc' => $newPass
                ? base64_encode($_POST['smtp_password'])
                : ($settings['smtp_password_enc'] ?? ''),
            'smtp_from_name' => $fromName,
            'smtp_from_email' => $fromEmail,
            'contact_email' => $contact,
        ], ['id' => 1]);
        flash('success', 'Settings saved. Emails will flow through SMTP when configured.');
        redirect('admin/owner/settings#smtp');
    }

    if ($action === 'test_email') {
        $me = current_user();
        $to = trim($_POST['test_to'] ?? '') ?: ($me['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid test recipient email.');
            redirect('admin/owner/settings#smtp');
        }
        $ok = send_email($to, '[Innovatech PH] SMTP test — ' . date('Y-m-d H:i'), '<p>This is a test email from the Innovatech PH System Settings page (' . date('c') . ').</p>');
        if ($ok) {
            // Distinguish "sent via SMTP" vs "spooled" by comparing log lines.
            $lastLine = email_log_tail(1)[0] ?? '';
            $via = str_contains($lastLine, 'via SMTP') ? 'via SMTP' : '(check the email debug log below)';
            flash('success', "Test email sent to {$to} {$via}. Check the inbox and the debug log below.");
        } else {
            flash('error', "Test email to {$to} FAILED. See the email debug log below for the SMTP conversation.");
        }
        redirect('admin/owner/settings#smtp');
    }

    if ($action === 'save_template') {
        $slug = trim($_POST['slug'] ?? '');
        if ($slug === '') {
            flash('error', 'Missing template slug.');
            redirect('admin/owner/settings#email-templates');
        }
        crud()->update('email_templates', [
            'subject' => trim($_POST['subject'] ?? ''),
            'body_html' => ($_POST['body_html'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ], ['slug' => $slug]);
        flash('success', 'Template "' . $slug . '" saved.');
        redirect('admin/owner/settings#email-templates');
    }

    if ($action === 'test_credentials') {
        $me = current_user();
        $u = crud()->get('users', (int) $me['id']);
        $inst = resolve_active_institution();
        $sent = $u ? send_credentials($u, 'owner', 'Demo' . random_token(6), $inst['name'] ?? APP_NAME) : false;
        flash($sent ? 'success' : 'error',
            $sent
                ? 'Test credentials email queued to ' . h($me['email']) . ' — check the inbox & debug log.'
                : 'Test credentials email FAILED — see the SMTP debug log below.');
        redirect('admin/owner/settings#email-templates');
    }

    if ($action === 'test_reset') {
        $me = current_user();
        $u = crud()->get('users', (int) $me['id']);
        $tpl = crud()->get('email_templates', ['slug' => 'password_reset']);
        $name = $u ? display_name($u) : 'there';
        $url = url('admin/reset-password?token=' . random_token(24));
        $vars = [
            '{{name}}' => $name,
            '{{email}}' => (string) ($u['email'] ?? $me['email']),
            '{{link}}' => '<a href="' . h($url) . '">' . h($url) . '</a>',
        ];
        $subject = strtr((string) ($tpl['subject'] ?? 'Reset your password'), $vars);
        $body = strtr((string) ($tpl['body_html'] ?? ''), $vars);
        $sent = send_email((string) $me['email'], $subject, $body);
        flash($sent ? 'success' : 'error',
            $sent
                ? 'Test password-reset email queued to ' . h($me['email']) . ' — check the inbox & debug log.'
                : 'Test password-reset email FAILED — see the SMTP debug log below.');
        redirect('admin/owner/settings#email-templates');
    }

    if ($action === 'test_ticket_status') {
        $me = current_user();
        [$sent, $to] = send_ticket_status_email([
            'id' => 1,
            'contact_email' => (string) $me['email'],
            'created_by' => (int) $me['id'],
            'subject' => 'Test support ticket',
            'message' => 'This is a test ticket-status notification sent from System Settings.',
            'status' => 'in_progress',
        ]);
        flash($sent ? 'success' : 'error',
            $sent
                ? 'Test ticket-status email queued to ' . h($to) . ' — check the inbox & debug log.'
                : 'Test ticket-status email FAILED — see the SMTP debug log below.');
        redirect('admin/owner/settings#email-templates');
    }
}

$smtpConfigured = !empty($settings['smtp_host']) && !empty($settings['smtp_username']);
$logTail = email_log_tail(40);

/* --------------------------- email templates --------------------------- */
$tplParams = [
    'admin_invite' => ['name', 'email', 'username', 'password', 'role', 'institution', 'link', 'login_link'],
    'staff_invite' => ['name', 'email', 'username', 'password', 'role', 'institution', 'link', 'login_link'],
    'password_reset' => ['name', 'email', 'link'],
    'support_ticket_status' => ['name', 'email', 'ticket_id', 'ticket_subject', 'ticket_message', 'ticket_status', 'link'],
    'new_ticket' => ['name', 'email', 'ticket_id', 'ticket_subject', 'ticket_message', 'priority', 'institution', 'link'],
];
$paramHelp = [
    'name' => 'Recipient display name',
    'email' => 'Recipient email address',
    'username' => 'Account username',
    'password' => 'Generated login password',
    'role' => 'Account role label',
    'institution' => 'Institution name',
    'link' => 'Confirmation / password-reset link (tickets: link to the support desk)',
    'login_link' => 'Admin login URL',
    'ticket_id' => 'Support ticket number',
    'ticket_subject' => 'Ticket subject line',
    'ticket_message' => 'Ticket message body',
    'ticket_status' => 'Ticket status label',
    'priority' => 'Ticket priority (Low / Normal / High)',
];
$templates = crud()->select('email_templates', '*', [], 'ORDER BY slug');
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="ia-card" id="smtp">
      <div class="card-head">
        <h3>SMTP / outgoing email</h3>
        <span class="badge <?= $smtpConfigured ? 'badge-live' : 'badge-off' ?>"><?= $smtpConfigured ? 'Configured' : 'Not configured' ?></span>
      </div>
      <div class="card-body">
        <form method="post" class="d-grid gap-3">
          <input type="hidden" name="action" value="save">
          <div class="row g-3">
            <div class="col-md-7"><label class="form-label">SMTP host</label>
              <input class="form-control" name="smtp_host" value="<?= h($settings['smtp_host'] ?? '') ?>" placeholder="smtp.hostinger.com"></div>
            <div class="col-md-5"><label class="form-label">Port (465 SSL / 587 STARTTLS / 25)</label>
              <input type="number" class="form-control" name="smtp_port" value="<?= (int) ($settings['smtp_port'] ?? 465) ?>"></div>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Username (full email)</label>
              <input class="form-control" name="smtp_username" value="<?= h($settings['smtp_username'] ?? '') ?>" placeholder="support@yourdomain.com"></div>
            <div class="col-md-6"><label class="form-label">Password (leave blank to keep current)</label>
              <div class="pw-group">
                <input type="password" class="form-control" name="smtp_password" id="smtp-pw" autocomplete="new-password">
                <button type="button" class="btn btn-outline-ia btn-shrink pw-eye" data-pw="smtp-pw" title="Show password"><?= ia_icon('eye', 14) ?></button>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">From name</label>
              <input class="form-control" name="smtp_from_name" value="<?= h($settings['smtp_from_name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">From email</label>
              <input type="email" class="form-control" name="smtp_from_email" value="<?= h($settings['smtp_from_email'] ?? '') ?>"></div>
          </div>
          <div><?php $contact = $settings['contact_email'] ?? ''; ?>
            <label class="form-label">Contact / reply-to email</label>
            <input type="email" class="form-control" name="contact_email" value="<?= h($contact) ?>" placeholder="support@yourdomain.com"></div>
          <div class="text-end"><button class="btn btn-grad px-4" type="submit">Save SMTP</button></div>
        </form>

        <hr class="my-4">

        <form method="post" class="d-grid gap-3">
          <input type="hidden" name="action" value="test_email">
          <div class="d-flex flex-wrap align-items-end gap-3">
            <div class="flex-grow-1">
              <label class="form-label">Send test email to</label>
              <input type="email" class="form-control" name="test_to" value="<?= h(current_user()['email'] ?? '') ?>">
            </div>
            <button class="btn btn-outline-ia" type="submit"><?= ia_icon('mail', 14) ?> Send test email</button>
          </div>
          <div class="form-text">Tests the current SMTP config end-to-end. The SMTP conversation is recorded in the debug log below.</div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="ia-card">
      <div class="card-head"><h3>Directories</h3></div>
      <div class="card-body d-grid gap-2 ia-meta-lg">
        <div class="d-flex justify-content-between py-2 divider-bottom">
          <span class="text-muted">Organization root</span><code>organizations/</code>
        </div>
        <div class="d-flex justify-content-between py-2 divider-bottom">
          <span class="text-muted">Template pack</span><code>templates/org_pack/</code>
        </div>
        <div class="d-flex justify-content-between py-2">
          <span class="text-muted">Email spool (/&#8203;logs)</span><code>storage/</code>
        </div>
      </div>
    </div>

    <div class="ia-card mt-4">
      <div class="card-head">
        <h3>Email debug log</h3>
        <span class="ia-meta-sm">storage/logs/email.log</span>
      </div>
      <div class="card-body">
        <?php if ($logTail): ?>
          <pre class="code-area log-view" style="max-height:340px;overflow:auto;font-size:11px;line-height:1.45"><?= h(implode("\n", $logTail)) ?></pre>
        <?php else: ?>
          <p class="ia-meta-md mb-0">No log entries yet. Send a test email to populate this log.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mt-0">
  <div class="col-lg-12">
    <div class="ia-card" id="email-templates">
      <div class="card-head">
        <h3>Email templates &amp; test sends</h3>
        <span class="ia-meta-sm">rendered by the SMTP config above and delivered to <?= h(current_user()['email'] ?? 'you') ?> on test</span>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
          <form method="post"><input type="hidden" name="action" value="test_credentials"><button class="btn btn-outline-ia"><?= ia_icon('users', 14) ?> Test credentials email</button></form>
          <form method="post"><input type="hidden" name="action" value="test_reset"><button class="btn btn-outline-ia"><?= ia_icon('refresh', 14) ?> Test password-reset email</button></form>
          <form method="post"><input type="hidden" name="action" value="test_ticket_status"><button class="btn btn-outline-ia"><?= ia_icon('move3d', 14) ?> Test ticket-status email</button></form>
        </div>

        <div class="row g-4">
          <?php foreach ($templates as $tpl): ?>
            <?php $params = $tplParams[$tpl['slug']] ?? []; ?>
            <div class="col-lg-6">
              <div class="ia-card">
                <div class="card-head">
                  <h3><code style="font-size:14px"><?= h($tpl['slug']) ?></code></h3>
                  <span class="badge <?= (int) $tpl['is_active'] === 1 ? 'badge-live' : 'badge-off' ?>"><?= (int) $tpl['is_active'] === 1 ? 'Active' : 'Disabled' ?></span>
                </div>
                <form method="post" class="card-body d-grid gap-3">
                  <input type="hidden" name="action" value="save_template">
                  <input type="hidden" name="slug" value="<?= h($tpl['slug']) ?>">
                  <div><label class="form-label">Subject</label><input class="form-control" name="subject" value="<?= h($tpl['subject']) ?>"></div>
                  <div>
                    <label class="form-label">Body (HTML)</label>
                    <div class="mb-2">
                      <span class="ia-meta-md d-block mb-1">Available placeholders — click a chip to copy:</span>
                      <?php foreach ($params as $p): ?>
                        <button type="button" class="ph-chip" data-param="{{<?= h($p) ?>}}" title="<?= h($paramHelp[$p] ?? '') ?>">{{<?= h($p) ?>}}</button>
                      <?php endforeach; ?>
                      <?php if (!$params): ?>
                        <span class="ia-meta-md">No placeholders registered for this template.</span>
                      <?php endif; ?>
                    </div>
                    <textarea class="form-control code-area" name="body_html" rows="8"><?= h($tpl['body_html']) ?></textarea>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="act-<?= h($tpl['slug']) ?>" <?= (int) $tpl['is_active'] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label ia-meta-lg" for="act-<?= h($tpl['slug']) ?>">Template active</label>
                  </div>
                  <div class="text-end"><button class="btn btn-grad px-4" type="submit"><?= ia_icon('save', 14) ?> Save</button></div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <details class="ia-card mt-4">
          <summary class="card-head p-3" style="cursor:pointer"><?= ia_icon('info', 15) ?> Parameter guide — what each {{placeholder}} means</summary>
          <div class="card-body">
            <table class="table table-sm">
              <thead><tr><th style="width:180px">Placeholder</th><th>What it renders with</th><th>Used by</th></tr></thead>
              <tbody>
                <?php foreach ($paramHelp as $p => $desc): ?>
                  <?php $usedBy = array_keys(array_filter($tplParams, fn($list) => in_array($p, $list, true))); ?>
                  <tr>
                    <td><code>{{<?= h($p) ?>}}</code></td>
                    <td><?= h($desc) ?></td>
                    <td class="ia-meta-md"><?= h(implode(', ', $usedBy)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p class="ia-meta-md mb-0">Any placeholder left in a template renders as an empty string if unused. A test send above is the fastest way to verify your templates.</p>
          </div>
        </details>
      </div>
    </div>
  </div>
</div>

<div class="ia-card mt-4" id="ai-chat">
  <div class="card-head">
    <h3>AI Test — Text & Image Generation</h3>
    <div class="d-flex gap-2 align-items-center">
      <span class="badge badge-live" id="ai-status">Ready</span>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="ai-mode-switch">
        <label class="form-check-label small" for="ai-mode-switch">Use OpenRouter AI</label>
      </div>
    </div>
  </div>
  <div class="card-body">
    <!-- AI Mode Tabs -->
    <ul class="nav nav-pills mb-3" id="ai-tabs">
      <li class="nav-item">
        <button class="nav-link active" data-tab="text" id="tab-text">Text Description</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-tab="image" id="tab-image">Image Generation</button>
      </li>
    </ul>

    <style>
    #ai-tabs {
      display: flex;
      gap: 8px;
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    #ai-tabs .nav-link {
      cursor: pointer;
      padding: 8px 16px;
      border-radius: 8px;
      background: rgba(248, 250, 252, 0.5);
      border: 1px solid rgba(148, 163, 184, 0.2);
      color: #0f172a;
      transition: all 0.2s ease;
      font-size: 13px;
      font-weight: 500;
    }
    
    #ai-tabs .nav-link:hover {
      background: rgba(248, 250, 252, 0.8);
    }
    
    #ai-tabs .nav-link.active {
      background: #3b82f6;
      color: #fff;
      border-color: #3b82f6;
    }
    
    .ai-panel {
      display: none;
    }
    
    .ai-panel:not(.hidden) {
      display: block;
    }
    
    .form-switch {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .form-switch .form-check-input {
      width: 36px;
      height: 20px;
    }
    </style>

    <!-- Text Generation Panel -->
    <div id="panel-text" class="ai-panel">
      <div class="chat-box" id="ai-chat-log">
        <div class="chat-msg ai">Hi — I generate campus descriptions using OpenRouter AI (Llama 3.2 3B). Type a location name like "Main Library" or "Computer Lab" to see generative AI in action. The response will be natural and varied, not template-based.</div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <input class="form-control" id="ai-prompt" placeholder="Enter location name or description request…">
        <button class="btn btn-grad flex-shrink-0" id="ai-send"><?= ia_icon('send', 15) ?> Generate</button>
      </div>
      <div class="form-text mt-2">
        <span id="ai-mode-text">Using OpenRouter AI with Llama 3.2 3B model (generative AI)</span>
        <span id="ai-mode-text-or" class="mx-2">|</span>
        <span id="ai-api-status">OpenRouter: <span class="text-muted">Not configured</span></span>
      </div>
    </div>

    <!-- Image Generation Panel -->
    <div id="panel-image" class="ai-panel hidden">
      <div class="mb-3">
        <label class="form-label">Generation Prompt</label>
        <textarea class="form-control" id="ai-image-prompt" rows="3" placeholder="Describe the image you want to generate (e.g., 'A modern computer laboratory with rows of computers')"></textarea>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-grad" id="ai-generate-image"><?= ia_icon('wand', 15) ?> Generate Image</button>
      </div>
      <div class="mt-3" id="ai-image-result" style="display:none;">
        <label class="form-label small fw-semibold">Generated Image:</label>
        <div class="border rounded-3 p-2 text-center bg-light">
          <img id="ai-generated-image" class="img-fluid rounded-2" style="max-height: 300px;">
        </div>
        <div class="mt-2">
          <button class="btn btn-sm btn-outline-ia" id="ai-download-image">Download</button>
        </div>
      </div>
      <div class="form-text mt-2">
        Using <a href="https://openrouter.ai/sourceful/riverflow-v2.5-fast" target="_blank">Riverflow V2.5 Fast</a> model (~$0.019/image) for text-to-image generation. Note: For editing/repairing EXISTING stitched panoramas (e.g., removing damaged WiFi icons), use the AI Stitch page (360 Capture Tool).
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= url('assets/css/jodit.min.css') ?>" />
<script src="<?= url('assets/js/jodit.min.js') ?>"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('textarea[name="body_html"]').forEach(function (el) {
      Jodit.make(el, { height: 260, toolbarButtonSize: "small" });
    });
    document.querySelectorAll('.ph-chip').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var p = btn.getAttribute('data-param');
        var done = function () {
          btn.classList.add('is-copied');
          btn.textContent = 'Copied ✓';
          setTimeout(function () { btn.classList.remove('is-copied'); btn.textContent = p; }, 900);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(p).then(done).catch(done);
        } else {
          var ta = document.createElement('textarea');
          ta.value = p; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); } catch (e) {}
          document.body.removeChild(ta);
          done();
        }
      });
    });
  });
</script>

<script>
window.addEventListener('DOMContentLoaded', function() {
  var log = document.getElementById('ai-chat-log')
  var promptEl = document.getElementById('ai-prompt')
  var sendBtn = document.getElementById('ai-send')
  var api = '<?= h(url('admin/ai-chat')) ?>'
  var modeSwitch = document.getElementById('ai-mode-switch')
  var aiStatus = document.getElementById('ai-status')
  var aiModeText = document.getElementById('ai-mode-text')
  var aiApiStatus = document.getElementById('ai-api-status')
  
  // Tab switching
  document.querySelectorAll('[data-tab]').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('[data-tab]').forEach(function(t) {
        t.classList.remove('active')
      })
      tab.classList.add('active')
      document.querySelectorAll('.ai-panel').forEach(function(p) {
        p.classList.add('hidden')
      })
      var panel = document.getElementById('panel-' + tab.dataset.tab)
      if (panel) {
        panel.classList.remove('hidden')
      }
    })
  })

  // Check OpenRouter API status
  var checkOpenRouterStatus = async function() {
    var textKey = '<?= h(env('OPENROUTER_TEXT_KEY', '')) ?>'
    var imageKey = '<?= h(env('OPENROUTER_IMAGE_KEY', '')) ?>'
    
    var status = ''
    if (textKey !== '' && imageKey !== '') {
      status = 'OpenRouter: <span class="text-success">Text & Image configured ✓</span>'
      // Auto-enable OpenRouter mode if keys are configured
      modeSwitch.checked = true
      aiModeText.textContent = 'Using OpenRouter AI (Llama 3.2 3B)'
      aiStatus.textContent = 'AI Ready'
    } else if (textKey !== '') {
      status = 'OpenRouter: <span class="text-success">Text configured ✓</span> (Image: Not configured)'
      modeSwitch.checked = true
      aiModeText.textContent = 'Using OpenRouter AI (Llama 3.2 3B)'
      aiStatus.textContent = 'AI Ready'
    } else if (imageKey !== '') {
      status = 'OpenRouter: <span class="text-warning">Image configured</span> (Text: Not configured)'
      aiModeText.textContent = 'Using template-based generation (instant, no API key needed)'
      aiStatus.textContent = 'Ready'
    } else {
      status = 'OpenRouter: <span class="text-muted">Not configured</span>'
      aiModeText.textContent = 'Using template-based generation (instant, no API key needed)'
      aiStatus.textContent = 'Ready'
    }
    
    aiApiStatus.innerHTML = status
  }
  checkOpenRouterStatus()

  // Mode switch
  modeSwitch.addEventListener('change', function() {
    if (modeSwitch.checked) {
      aiModeText.textContent = 'Using OpenRouter AI (Llama 3.2 3B)'
      aiStatus.className = 'badge badge-live'
      aiStatus.textContent = 'AI Ready'
    } else {
      aiModeText.textContent = 'Using template-based generation (instant, no API key needed)'
      aiStatus.className = 'badge badge-live'
      aiStatus.textContent = 'Ready'
    }
  })

  var chat = function(role, text) {
    var d = document.createElement('div')
    d.className = 'chat-msg ' + role
    d.textContent = text
    log.appendChild(d)
    log.scrollTop = log.scrollHeight
    return d
  }

  async function doSend() {
    var text = promptEl.value.trim()
    if (!text) return
    chat('user', text)
    promptEl.value = ''
    var replyLine = chat('ai', '')
    replyLine.classList.add('streaming')
    sendBtn.disabled = true
    
    try {
      if (modeSwitch.checked) {
        // Use OpenRouter AI via backend
        const apiKey = '<?= h(env('OPENROUTER_TEXT_KEY', '')) ?>'
        if (!apiKey) {
          replyLine.textContent = '⚠️ OpenRouter Text API key not configured. Add OPENROUTER_TEXT_KEY to .env file or use template mode.'
          replyLine.classList.remove('streaming')
          sendBtn.disabled = false
          return
        }
        
        const fd = new FormData()
        fd.append('message', text)
        fd.append('use_openrouter', 'true')
        const res = await fetch(api, { method: 'POST', body: fd })
        const data = await res.json()
        replyLine.textContent = (data && data.reply) ? data.reply : 'No reply.'
      } else {
        // Use local template system
        const fd = new FormData()
        fd.append('message', text)
        fd.append('use_openrouter', 'false')
        const res = await fetch(api, { method: 'POST', body: fd })
        const data = await res.json()
        replyLine.textContent = (data && data.reply) ? data.reply : 'No reply.'
      }
    } catch (e) {
      replyLine.textContent = '\u26a0 Error: ' + (e?.message || e)
    } finally {
      replyLine.classList.remove('streaming')
      sendBtn.disabled = false
      log.scrollTop = log.scrollHeight
    }
  }

  sendBtn.addEventListener('click', doSend)
  promptEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') doSend() })

  // Image generation
  var imagePrompt = document.getElementById('ai-image-prompt')
  var generateImageBtn = document.getElementById('ai-generate-image')
  var imageResult = document.getElementById('ai-image-result')
  var generatedImage = document.getElementById('ai-generated-image')
  var downloadImageBtn = document.getElementById('ai-download-image')

  generateImageBtn.addEventListener('click', async function() {
    var prompt = imagePrompt.value.trim()
    var apiKey = '<?= h(env('OPENROUTER_IMAGE_KEY', '')) ?>'
    
    if (!apiKey) {
      alert('OpenRouter Image API key not configured. Add OPENROUTER_IMAGE_KEY to .env file.')
      return
    }
    
    if (!prompt) {
      alert('Please enter a prompt.')
      return
    }

    generateImageBtn.disabled = true
    generateImageBtn.textContent = 'Generating...'
    
    try {
      var payload = {
        model: 'sourceful/riverflow-v2.5-fast',
        prompt: prompt || 'Generate a campus scene image',
        resolution: '1K',
        aspect_ratio: '16:9',
        background: 'auto',
        n: 1
      }

      var res = await fetch('https://openrouter.ai/api/v1/images', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + apiKey,
          'Content-Type': 'application/json',
          'HTTP-Referer': window.location.origin,
          'X-Title': 'Innovatech PH Image Generation'
        },
        body: JSON.stringify(payload)
      })

      var data = await res.json()
      
      if (data.data && data.data[0] && data.data[0].b64_json) {
        var imageData = data.data[0].b64_json
        generatedImage.src = 'data:image/jpeg;base64,' + imageData
        imageResult.style.display = 'block'
        generateImageBtn.textContent = 'Generate Image'
        generateImageBtn.disabled = false
      } else {
        throw new Error(data.error && data.error.message ? data.error.message : 'No image generated')
      }
    } catch (e) {
      alert('Image generation failed: ' + e.message)
      generateImageBtn.textContent = 'Generate Image'
      generateImageBtn.disabled = false
    }
  })

  downloadImageBtn.addEventListener('click', function() {
    var link = document.createElement('a')
    link.download = 'ai-generated-image.jpg'
    link.href = generatedImage.src
    link.click()
  })
})
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>