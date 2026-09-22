<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin_staff();
/**
 * Innovatech PH — shared support ticket desk.
 * Available to every admin/owner role; opening a ticket emails the platform
 * owner(s) + the support desk (contact email in System Settings) automatically.
 */
$pageTitle = 'Support Tickets';
$pageSub = 'Report issues and manage the workspace support queue';
$active = 'Support Tickets';
$bodyClass = 'page-support';
require_once __DIR__ . '/../layout/header.php';

$me = current_user();

/* --------------------------------- handlers --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'create_ticket') {
      $subject = trim($_POST['subject'] ?? '');
      if ($subject === '') throw new RuntimeException('Ticket subject is required.');
      $contact = trim($_POST['contact_email'] ?? '');
      if ($contact === '') {
        $ps = crud()->get('platform_settings', 1);
        $contact = trim($ps['contact_email'] ?? '');
      }
      if ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Invalid contact email.');
      }
      $priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high'], true) ? $_POST['priority'] : 'normal';
      $iid = resolve_active_institution()['id'] ?? null;

      $id = crud()->insert('support_tickets', [
        'subject' => $subject,
        'message' => trim($_POST['message'] ?? ''),
        'contact_email' => $contact !== '' ? $contact : null,
        'institution_id' => $iid ? (int) $iid : null,
        'status' => 'open',
        'priority' => $priority,
        'created_by' => (int) $me['id'],
      ]);

      $instName = null;
      if ($iid) {
        $inst = crud()->get('institutions', (int) $iid);
        $instName = $inst['name'] ?? null;
      }
      [$notified, $toList] = send_ticket_notification_email([
        'id' => $id,
        'subject' => $subject,
        'message' => trim($_POST['message'] ?? ''),
        'contact_email' => $contact !== '' ? $contact : ($me['email'] ?? ''),
        'priority' => $priority,
        'created_by' => (int) $me['id'],
      ], $instName);
      flash($notified ? 'success' : 'error',
        $notified
          ? "Support ticket #{$id} opened — the owner &amp; support desk were emailed ({$toList})."
          : "Support ticket #{$id} opened, but the notification email was NOT sent" . ($toList !== '' ? " to {$toList}" : '') . '. Check the email debug log.'
      );
    }

    if ($action === 'set_status') {
      $id = (int) ($_POST['id'] ?? 0);
      $status = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'resolved', 'closed'], true) ? $_POST['status'] : 'open';
      $ticket = crud()->get('support_tickets', $id);
      if (!$ticket) throw new RuntimeException('Ticket not found.');
      $old = $ticket['status'];
      crud()->update('support_tickets', ['status' => $status], ['id' => $id]);
      if ($old !== $status) {
        $ticket['status'] = $status;
        [$sent, $to] = send_ticket_status_email($ticket);
        flash($sent ? 'success' : 'error',
          $sent
            ? "Ticket #{$id} set to \"{$status}\" — status email sent to {$to}."
            : "Ticket #{$id} set to \"{$status}\", but the status email was NOT sent" . ($to !== '' ? " to {$to}" : ' (no contact email set)') . ". Check the email debug log."
        );
      } else {
        flash('success', "Ticket #{$id} status unchanged.");
      }
    }
  } catch (Throwable $e) {
    flash('error', $e->getMessage());
  }
  redirect('admin/support');
}

/* ---------------------------------- data ---------------------------------- */
$tickets = crud()->raw(
  "SELECT t.*, i.name AS institution_name
   FROM support_tickets t
   LEFT JOIN institutions i ON i.id = t.institution_id
   ORDER BY FIELD(t.status,'open','in_progress','resolved','closed'), t.created_at DESC"
)->fetchAll();
$openCount = 0;
foreach ($tickets as $t) if (in_array($t['status'], ['open', 'in_progress'], true)) $openCount++;

$statusLabels = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$statusBadge = ['open' => 'badge-live', 'in_progress' => 'badge-draft', 'resolved' => 'badge-grad', 'closed' => 'badge-off'];
$priorityLabels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];
$autoOpen = ($_GET['open'] ?? '') === '1';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <p class="mb-0 ia-meta-lg"><?= count($tickets) ?> ticket(s) total</p>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#ticket-modal"><?= ia_icon('plus', 15) ?> New ticket</button>
</div>

<div class="ia-card">
  <div class="card-body">
    <?php if (count($tickets) === 0): ?>
      <div class="empty-state"><div class="empty-icon"><?= ia_icon('life-buoy', 26) ?></div><h4>No support tickets yet</h4><p>Open a ticket and the owner &amp; support desk will be notified by email.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>#</th><th>Subject</th><th>From</th><th>Priority</th><th>Status</th><th>Opened</th><th style="min-width:230px">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
              <tr>
                <td class="ia-meta-md">#<?= (int) $t['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= h($t['subject']) ?></div>
                  <?php if (!empty($t['message'])): ?><div class="ia-meta-md text-truncate" style="max-width:360px" title="<?= h($t['message']) ?>"><?= h($t['message']) ?></div><?php endif; ?>
                </td>
                <td>
                  <div class="ia-meta-md"><?= h($t['contact_email'] ?? '—') ?></div>
                  <?php if (!empty($t['institution_name'])): ?><div class="ia-meta-md"><?= h($t['institution_name']) ?></div><?php endif; ?>
                </td>
                <td><span class="badge badge-soft"><?= h($priorityLabels[$t['priority']] ?? $t['priority']) ?></span></td>
                <td><span class="badge <?= $statusBadge[$t['status']] ?? 'badge-off' ?>"><?= h(ucwords(str_replace('_', ' ', $t['status']))) ?></span></td>
                <td class="ia-meta-md"><?= h(date('M j, g:i A', strtotime($t['created_at']))) ?></td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <select class="form-select form-select-sm" name="status" style="width:auto">
                      <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $t['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-ia px-2" title="Save status & email submitter"><?= ia_icon('mail', 13) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- new ticket modal -->
<div class="modal fade" id="ticket-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Open a support ticket</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <input type="hidden" name="action" value="create_ticket">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Subject</label><input class="form-control" name="subject" required placeholder="Short summary of the issue"></div>
        <div><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" placeholder="Details…"></textarea></div>
        <div class="row g-3">
          <div class="col-md-7"><label class="form-label">Contact email</label><input type="email" class="form-control" name="contact_email" placeholder="person@campus.edu (defaults to platform contact)"></div>
          <div class="col-md-5"><label class="form-label">Priority</label>
            <select class="form-select" name="priority">
              <?php foreach ($priorityLabels as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-text">The owner and the Innovatech support desk are notified by email the moment this ticket is opened.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-ia" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-grad px-4" type="submit"><?= ia_icon('plus', 14) ?> Open ticket</button></div>
    </form>
  </div></div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    <?php if ($autoOpen): ?>
      new bootstrap.Modal(document.getElementById('ticket-modal')).show();
    <?php endif; ?>
  });
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>