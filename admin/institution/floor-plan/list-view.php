  <?php if ($filterBuildingId):
    $filterBuilding = null;
    foreach ($buildings as $bb) { if ((int)$bb['id'] === $filterBuildingId) { $filterBuilding = $bb; break; } }
    $buildings->execute([$iid]); // re-run for modal
  endif; ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <?php if ($filterBuildingId): ?>
        <a class="back-link" href="buildings">← Buildings</a>
        <div class="ia-meta-md mt-1">
          <?= ia_icon('building', 13) ?>
          Showing floor plans — assign markers to
          <strong><?= h($filterBuilding['name'] ?? 'Building #'.$filterBuildingId) ?></strong>
          using the <em>Studio</em> editor
        </div>
      <?php else: ?>
        <p class="mb-0 ia-meta-lg"><?= $plans->rowCount() ?> floor plan(s)</p>
      <?php endif ?>
    </div>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#fp-upload"><?= ia_icon('image', 16) ?> Upload floor plan</button>
  </div>


  <div class="ia-card">
    <table class="table table-ia">
      <thead><tr><th>Plan</th><th>Image</th><th>Markers</th><th>Landing</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($plans as $plan): ?>
          <tr>
            <td>
              <div class="fw-bold"><?= h($plan['title']) ?></div>
              <div class="ia-meta-sm"><?= (int) $plan['original_width'] ?>×<?= (int) $plan['original_height'] ?> · aspect <?= round((float) $plan['aspect_ratio'], 3) ?></div>
            </td>
            <td><img src="<?= h(media_url($plan['image_path'])) ?>" class="plan-thumb" alt=""></td>
            <td class="text-ia-muted"><?= (int) $plan['marker_count'] ?></td>
            <td>
              <?php if ($plan['is_start']): ?><span class="badge badge-live">landing</span>
              <?php else: ?><span class="badge badge-draft">—</span><?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-grad" href="floor-plans?studio=<?= (int) $plan['id'] ?>"><?= ia_icon('map', 13) ?> Studio</a>
                <form method="post" class="d-inline">
                  <input type="hidden" name="fp_action" value="landing"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia" title="Set as landing"><?= ia_icon('rocket', 13) ?></button>
                </form>
                <form method="post" class="d-inline" data-delete-form data-confirm="Delete floor plan '<?= h($plan['title']) ?>'?">
                  <input type="hidden" name="fp_action" value="delete"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($plans->rowCount() === 0): ?>
          <tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No floor plans</h4><p>Upload a campus map, then drop circular markers on top of buildings, gates and landmarks.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<!-- upload modal -->
<div class="modal fade" id="fp-upload" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Upload floor plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="fp_action" value="upload">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Ground Floor Map"></div>
        <div>
          <?php
          $pickerName = 'image';
          $pickerValue = '';
          $pickerLabel = 'Image (PNG/JPG)';
          $pickerHelp = '';
          require __DIR__ . '/../layout/media-picker-sweetalert.php';
          ?>
        </div>
        <div class="form-text">Aspect ratio is locked automatically. Markers are stored as percentages so they stay aligned at any screen size.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Upload</button></div>
    </form>
  </div></div>
</div>
