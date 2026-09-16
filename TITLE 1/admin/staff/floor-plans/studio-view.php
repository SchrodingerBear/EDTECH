<?php if ($plan): ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <a class="back-link" href="floor-plans">← All floor plans</a>
      <div class="d-flex align-items-center gap-2 mt-1"><h3 class="mb-0 fw-800"><?= h($plan['title']) ?></h3><span class="badge badge-draft"><?= count($markers) ?> markers</span></div>
    </div>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#mk-modal" data-x="50" data-y="50"><?= ia_icon('map', 16) ?> Add marker</button>
  </div>

  <div class="ia-card p-3">
    <div id="stage" style="--fp-ar:<?= (float) $plan['aspect_ratio'] ?>">
      <img src="<?= h(org_url($inst['slug'], $plan['image_path'])) ?>" alt="floor plan">
      <?php foreach ($markers as $m): ?>
        <button type="button" class="mk-dot" data-mid="<?= (int) $m['id'] ?>" data-name="<?= h($m['label'], ENT_QUOTES) ?>"
          style="left:<?= (float) $m['x_percent'] ?>%;top:<?= (float) $m['y_percent'] ?>%"></button>
      <?php endforeach; ?>
      <div class="fp-hint">Click a dot to reposition: first click a dot, then click the new spot on the map.</div>
    </div>
  </div>

  <div class="ia-card mt-3"><div class="card-body">
    <h5 class="fw-bold mb-3">Markers</h5>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($markers as $m): ?>
        <span class="px-3 py-2 rounded-3 d-flex align-items-center gap-2 bg-surface-2">
          <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold" data-edit-marker="<?= (int) $m['id'] ?>"
            data-name="<?= h($m['label'], ENT_QUOTES) ?>" data-x="<?= (float) $m['x_percent'] ?>" data-y="<?= (float) $m['y_percent'] ?>"
            data-pt="<?= h($m['popup_title'], ENT_QUOTES) ?>" data-ph="<?= h($m['popup_html'], ENT_QUOTES) ?>" data-bs-toggle="modal" data-bs-target="#mk-modal"><?= h($m['label']) ?></button>
          <form method="post" data-delete-form data-confirm="Delete marker '<?= h($m['label']) ?>'?">
            <input type="hidden" name="sfp_action" value="marker-delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-sm btn-outline-ia text-danger px-1" title="Delete"><?= ia_icon('x', 12) ?></button>
          </form>
        </span>
      <?php endforeach; ?>
      <?php if (!$markers): ?><p class="text-muted mb-0 fs-13">No markers — click Add marker to start placing pins.</p><?php endif; ?>
    </div>
  </div></div>
<!-- marker modal -->
<div class="modal fade" id="mk-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Marker</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div id="mk-both">
      <form method="post" id="mk-create">
        <input type="hidden" name="sfp_action" value="marker-add">
        <input type="hidden" name="plan_id" value="<?= (int) ($plan['id'] ?? 0) ?>">
        <input type="hidden" name="x" id="mk-x-new"><input type="hidden" name="y" id="mk-y-new">
        <div class="modal-body d-grid gap-3">
          <div><label class="form-label">Label</label><input class="form-control" name="label" required placeholder="Library"></div>
          <div class="row g-3">
            <div class="col-6"><label class="form-label">Popup title</label><input class="form-control" name="popup_title"></div>
            <div class="col-6"><label class="form-label">Popup content</label><input class="form-control" name="popup_html"></div>
          </div>
          <p class="fs-125 text-ia-muted mb-0">A new pin appears at the center — click it on the map to move it, then edit here.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Add marker</button></div>
      </form>
    </div>
  </div></div>
</div>
