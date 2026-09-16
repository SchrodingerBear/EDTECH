<?php else: ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <p class="mb-1 ia-meta-lg"><?= count($plans) ?> floor plan(s)</p>
    <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#up-modal"><?= ia_icon('upload', 16) ?> Upload floor plan</button>
  </div>
  <div class="ia-card">
    <div class="table-responsive"><table class="table table-ia">
      <thead><tr><th>Plan</th><th>Preview</th><th>Markers</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach (($plans ?: []) as $p): ?>
          <tr>
            <td class="fw-bold"><?= h($p['title']) ?></td>
            <td><img src="<?= h(org_url($inst['slug'], $p['image_path'])) ?>" class="plan-thumb-sm" alt=""></td>
            <td class="text-ia-muted"><?= (int) $p['mc'] ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-grad" href="floor-plans?plan=<?= (int) $p['id'] ?>"><?= ia_icon('map', 13) ?> Markers</a>
                <form method="post" data-delete-form data-confirm="Delete floor plan '<?= h($p['title']) ?>'?">
                  <input type="hidden" name="sfp_action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger"><?= ia_icon('x', 13) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($plans) === 0): ?>
          <tr><td colspan="4"><div class="empty-state"><div class="empty-icon"><?= ia_icon('map', 26) ?></div><h4>No floor plans</h4><p>Upload a campus map image first — then open it to place markers.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
<!-- upload modal -->
<div class="modal fade" id="up-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Upload floor plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="sfp_action" value="upload">
      <div class="modal-body d-grid gap-3">
        <div><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Ground Floor Map"></div>
        <div><label class="form-label">Image</label><input class="form-control" type="file" name="image" accept="image/*" required></div>
      </div>
      <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Upload</button></div>
    </form>
  </div></div>
</div>
