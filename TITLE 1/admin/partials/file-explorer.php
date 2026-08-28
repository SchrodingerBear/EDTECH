<?php
/**
 * Innovatech PH — reusable file explorer partial.
 * Usage: set $fmRoot (absolute path) + $fmRootUrl (web URL to root) then include.
 * All operations are confined to $fmRoot. No path traversal escapes it.
 */

$fmRoot = rtrim($fmRoot ?? '', '/');
$fmRootUrl = rtrim($fmRootUrl ?? '', '/');

$fmErr = null;
$fmMsg = null;

// Normalize + confine the current requested path.
$relative = trim((string) ($_GET['path'] ?? ''), '/');
$currentDir = $relative === '' ? $fmRoot : $fmRoot . '/' . $relative;
$realRoot = realpath($fmRoot);
$realCurrent = realpath($currentDir);

if (!$realCurrent || !$realRoot || str_starts_with($realCurrent, $realRoot) === false) {
    $relative = '';
    $currentDir = $fmRoot;
    $realCurrent = realpath($fmRoot);
}

// ------------------------------- actions (POST) -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['fm_action'] ?? '') !== '') {
    $target = rtrim((string) $_POST['fm_path'], '/');
    $realTarget = realpath($target);
    $inside = $realTarget && str_starts_with($realTarget, $realRoot);

    try {
        switch ($_POST['fm_action']) {
            case 'mkdir':
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '' || !preg_match('/^[A-Za-z0-9 _\-.]+$/', $name)) {
                    $fmErr = 'Invalid folder name.';
                } elseif (file_exists($currentDir . '/' . $name)) {
                    $fmErr = 'Name already exists.';
                } else {
                    mkdir($currentDir . '/' . $name, 0775, true);
                    $fmMsg = 'Folder created.';
                }
                break;

            case 'upload':
                if (!empty($_FILES['files']['name'][0])) {
                    $count = 0;
                    $total = count($_FILES['files']['name']);
                    for ($i = 0; $i < $total; $i++) {
                        $tmp = $_FILES['files']['tmp_name'][$i];
                        $name = basename($_FILES['files']['name'][$i]);
                        if (is_uploaded_file($tmp) && $name !== '') {
                            move_uploaded_file($tmp, $currentDir . '/' . $name);
                            $count++;
                        }
                    }
                    $fmMsg = "$count file(s) uploaded.";
                }
                break;

            case 'rename':
                $old = (string) ($_POST['old'] ?? '');
                $new = basename((string) ($_POST['new'] ?? ''));
                $oldPath = $currentDir . '/' . $old;
                if ($new === '' || str_contains($new, '/') || !file_exists($oldPath)) {
                    $fmErr = 'Invalid rename.';
                } elseif (file_exists($currentDir . '/' . $new)) {
                    $fmErr = 'Destination already exists.';
                } else {
                    rename($oldPath, $currentDir . '/' . $new);
                    $fmMsg = 'Renamed.';
                }
                break;

            case 'delete':
                $name = basename((string) ($_POST['name'] ?? ''));
                $path = $currentDir . '/' . $name;
                if ($name !== '' && $inside && file_exists($path)) {
                    is_dir($path) ? rrmdir($path) : unlink($path);
                    $fmMsg = 'Deleted.';
                }
                break;

            case 'delete_folder':
                // Owner-only helper: delete whole organization folder.
                $folder = basename((string) ($_POST['folder'] ?? ''));
                if ($folder !== '' && is_dir($fmRoot . '/' . $folder)) {
                    rrmdir($fmRoot . '/' . $folder);
                    $fmMsg = "Folder '$folder' deleted.";
                }
                break;
        }
    } catch (Throwable $e) {
        $fmErr = 'Operation failed: ' . $e->getMessage();
    }

    header('Location: ' . url($_SERVER['PHP_SELF']) . '?path=' . rawurlencode($relative));
    exit;
}

// ----------------------------------- listings -----------------------------------
$items = [];
foreach (scandir($currentDir) as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === '.DS_Store') {
        continue;
    }
    $full = $currentDir . '/' . $entry;
    $items[] = [
        'name' => $entry,
        'dir' => is_dir($full),
        'size' => is_file($full) ? filesize($full) : null,
        'mime' => is_file($full) ? (function_exists('mime_content_type') ? mime_content_type($full) : '') : '',
    ];
}
usort($items, fn ($a, $b) => ($b['dir'] <=> $a['dir']) ?: strcasecmp($a['name'], $b['name']));

$breadcrumb = [];
if ($relative !== '') {
    $parts = explode('/', $relative);
    $acc = '';
    foreach ($parts as $i => $part) {
        $acc = $acc === '' ? $part : $acc . '/' . $part;
        $breadcrumb[] = ['label' => $part, 'path' => $acc, 'last' => ($i === count($parts) - 1)];
    }
}

// image previews served inline
$previewExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
?>

<?php if ($fmErr): ?><div class="alert alert-danger border-0 rounded-4 py-2 small" style="background:rgba(239,68,68,.12);color:var(--ia-danger)"><?= h($fmErr) ?></div><?php endif; ?>
<?php if ($fmMsg): ?><div class="alert alert-success border-0 rounded-4 py-2 small" style="background:rgba(16,185,129,.12);color:var(--ia-success)"><?= h($fmMsg) ?></div><?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <nav class="d-flex align-items-center gap-1 flex-wrap" style="font-size:13.5px">
    <a class="back-link" href="?path="><?= ia_icon('home', 14) ?> <?= h(basename($fmRoot)) ?></a>
    <?php foreach ($breadcrumb as $cr): ?>
      <span style="color:var(--ia-muted)">/</span>
      <?php if ($cr['last']): ?>
        <a class="back-link" href="?path=<?= rawurlencode($cr['path']) ?>"><?= h($cr['label']) ?></a>
      <?php else: ?>
        <span class="back-link" style="color:var(--ia-muted)"><?= h($cr['label']) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="ms-auto d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-ia btn-sm" data-bs-toggle="modal" data-bs-target="#fmod-mkdir"><?= ia_icon('folder', 15) ?> New folder</button>
    <form method="post" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2">
      <input type="hidden" name="fm_action" value="upload">
      <input type="hidden" name="fm_path" value="<?= h($currentDir) ?>">
      <label class="btn btn-sm btn-grad mb-0" style="cursor:pointer">
        <?= ia_icon('image', 15) ?> Upload files
        <input type="file" name="files[]" multiple hidden onchange="this.form.submit()">
      </label>
    </form>
  </div>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead>
        <tr>
          <th>Name</th><th>Type</th><th>Size</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$relative): ?> <!-- org folders listing for owner root -->
          <tr><td colspan="4" style="color:var(--ia-muted);font-size:13px">Organization project folders are listed below. Folders are auto-generated when an institution is created.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $it): $ext = strtolower(pathinfo($it['name'], PATHINFO_EXTENSION)); ?>
          <tr>
            <td>
              <?php if ($it['dir']): ?>
                <a class="d-inline-flex align-items-center gap-2" style="color:inherit;font-weight:600" href="?path=<?= rawurlencode(($relative ? $relative . '/' : '') . $it['name']) ?>">
                  <?= ia_icon('folder', 17) ?> <?= h($it['name']) ?>
                </a>
              <?php elseif (in_array($ext, $previewExts, true)): ?>
                <a class="d-inline-flex align-items-center gap-2" style="color:inherit;font-weight:600" href="#" data-bs-toggle="modal" data-bs-target="#fmod-preview" data-preview="<?= h($fmRootUrl . '/' . ($relative ? $relative . '/' : '') . rawurlencode($it['name'])) ?>" data-name="<?= h($it['name']) ?>">
                  <?= ia_icon('image', 17) ?> <?= h($it['name']) ?>
                </a>
              <?php else: ?>
                <span class="d-inline-flex align-items-center gap-2"><?= ia_icon('file', 17) ?> <?= h($it['name']) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $it['dir'] ? '' : '' ?>" style="background:var(--ia-surface-2)"><?= $it['dir'] ? 'folder' : ($it['mime'] ?: 'file') ?></span></td>
            <td style="color:var(--ia-muted)"><?= $it['dir'] ? '—' : human_bytes($it['size']) ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <form method="post" class="d-inline"><input type="hidden" name="fm_action" value="rename"><input type="hidden" name="fm_path" value="<?= h($currentDir) ?>"><input type="hidden" name="old" value="<?= h($it['name']) ?>">
                  <button class="btn btn-sm btn-outline-ia" type="button" onclick="this.form.querySelector('input[name=new]').value=prompt('New name:', '<?= h($it['name']) ?>'); this.form.submit()" title="Rename"><?= ia_icon('file', 13) ?></button>
                  <input type="hidden" name="new" value="">
                </form>
                <?php if (is_file($currentDir . '/' . $it['name'])): ?>
                  <a class="btn btn-sm btn-outline-ia" target="_blank" rel="noopener" href="<?= h($fmRootUrl . '/' . ($relative ? $relative . '/' : '') . rawurlencode($it['name'])) ?>" title="Download"><?= ia_icon('rocket', 13) ?></a>
                <?php endif; ?>
                <form method="post" class="d-inline" data-delete-form data-confirm="Delete '<?= h($it['name']) ?>'? This cannot be undone.">
                  <input type="hidden" name="fm_action" value="delete">
                  <input type="hidden" name="fm_path" value="<?= h($currentDir) ?>">
                  <input type="hidden" name="name" value="<?= h($it['name']) ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger" type="submit" title="Delete"><?= ia_icon('x', 14) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
          <tr><td colspan="4"><div class="empty-state"><div class="empty-icon"><?= ia_icon('folder', 26) ?></div><h4>Empty folder</h4><p>Upload files or create a folder to get started.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- mkdir modal -->
<div class="modal fade" id="fmod-mkdir" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">New folder</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="fm_action" value="mkdir"><input type="hidden" name="fm_path" value="<?= h($currentDir) ?>">
        <input name="name" class="form-control" placeholder="Folder name" required>
      </div>
      <div class="modal-footer"><button class="btn btn-grad btn-sm px-3" type="submit">Create</button></div>
    </form>
  </div></div>
</div>

<!-- preview modal -->
<div class="modal fade" id="fmod-preview" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="preview-name"></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-center p-0"><img id="preview-img" src="" alt="" style="max-width:100%;max-height:76vh;border-radius:0 0 18px 18px"></div>
  </div></div>
</div>

<script>
  document.querySelectorAll('[data-bs-toggle="modal"][data-preview]').forEach((el) => {
    el.addEventListener('click', () => {
      document.getElementById('preview-img').src = el.dataset.preview
      document.getElementById('preview-name').textContent = el.dataset.name
    })
  })
</script>