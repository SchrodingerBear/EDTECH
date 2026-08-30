<?php
/**
 * Innovatech PH — reusable file explorer partial.
 * Usage: set $fmRoot (absolute path) + $fmRootUrl (web URL to root) then include.
 * All operations are confined to $fmRoot. No path traversal escapes it.
 */

$fmRoot = rtrim($fmRoot ?? '', '/');
$fmRootUrl = rtrim($fmRootUrl ?? '', '/');
$fmTab = $tab ?? ($_GET['tab'] ?? '');

$fmLink = static function (array $q = []) use ($fmTab): string {
    if ($fmTab !== '') {
        $q = ['tab' => $fmTab] + $q;
    }
    return '?' . http_build_query($q);
};

$fmErr = null;
$fmMsg = null;

if ($fmRoot !== '' && !is_dir($fmRoot)) {
    @mkdir($fmRoot, 0775, true);
}

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

    $q = [];
    if ($fmTab !== '') {
        $q['tab'] = $fmTab;
    }
    if ($relative !== '') {
        $q['path'] = $relative;
    }
    $base = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: url($_SERVER['PHP_SELF']);
    header('Location: ' . $base . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

// ----------------------------------- listings -----------------------------------
$items = [];
$listDir = is_dir($currentDir) ? $currentDir : null;
foreach ($listDir ? (scandir($currentDir) ?: []) : [] as $entry) {
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

<?php if ($fmErr): ?><div class="alert border-0 rounded-4 py-2 small alert-soft-danger"><?= h($fmErr) ?></div><?php endif; ?>
    <?php if ($fmMsg): ?><div class="alert border-0 rounded-4 py-2 small alert-soft-success"><?= h($fmMsg) ?></div><?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <nav class="d-flex align-items-center gap-1 flex-wrap fs-135">
    <a class="back-link" href="<?= h($fmLink(['path' => ''])) ?>"><?= ia_icon('home', 14) ?> <?= h(basename($fmRoot)) ?></a>
    <?php foreach ($breadcrumb as $cr): ?>
      <span class="text-ia-muted">/</span>
      <?php if ($cr['last']): ?>
        <a class="back-link" href="<?= h($fmLink(['path' => $cr['path']])) ?>"><?= h($cr['label']) ?></a>
      <?php else: ?>
        <span class="back-link text-ia-muted"><?= h($cr['label']) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="ms-auto d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-ia btn-sm" data-bs-toggle="modal" data-bs-target="#fmod-mkdir"><?= ia_icon('folder', 15) ?> New folder</button>
    <form method="post" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2">
      <input type="hidden" name="fm_action" value="upload">
      <input type="hidden" name="fm_path" value="<?= h($currentDir) ?>">
      <label class="btn btn-sm btn-grad mb-0 cursor-pointer">
        <?= ia_icon('image', 15) ?> Upload files
        <input type="file" name="files[]" multiple hidden onchange="this.form.submit()">
      </label>
    </form>
  </div>
</div>

<div class="ia-card">
  <?php if ($items): ?>
  <div class="table-responsive">
    <table class="table table-ia">
      <thead>
        <tr>
          <th>Name</th><th>Type</th><th>Size</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): $ext = strtolower(pathinfo($it['name'], PATHINFO_EXTENSION)); ?>
          <tr>
            <td>
              <?php if ($it['dir']): ?>
                <a class="d-inline-flex align-items-center gap-2" class="text-reset fw-semibold" href="<?= h($fmLink(['path' => ($relative ? $relative . '/' : '') . $it['name']])) ?>">
                  <?= ia_icon('folder', 17) ?> <?= h($it['name']) ?>
                </a>
              <?php elseif (in_array($ext, $previewExts, true)): ?>
                <a class="d-inline-flex align-items-center gap-2" class="text-reset fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#fmod-preview" data-preview="<?= h($fmRootUrl . '/' . ($relative ? $relative . '/' : '') . rawurlencode($it['name'])) ?>" data-name="<?= h($it['name']) ?>">
                  <?= ia_icon('image', 17) ?> <?= h($it['name']) ?>
                </a>
              <?php else: ?>
                <span class="d-inline-flex align-items-center gap-2"><?= ia_icon('file', 17) ?> <?= h($it['name']) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-surface"><?= $it['dir'] ? 'folder' : ($it['mime'] ?: 'file') ?></span></td>
            <td class="text-ia-muted"><?= $it['dir'] ? '—' : human_bytes($it['size']) ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <form method="post" class="d-inline" id="rename-form-<?= $it['name'] === '' ? 'x' : md5($it['name']) ?>">
                  <input type="hidden" name="fm_action" value="rename">
                  <input type="hidden" name="fm_path" value="<?= h($currentDir) ?>">
                  <input type="hidden" name="old" value="<?= h($it['name']) ?>">
                  <input type="hidden" name="new" value="">
                  <button class="btn btn-sm btn-outline-ia" type="button"
                    data-fm-rename
                    data-old="<?= h($it['name'], ENT_QUOTES) ?>"
                    data-form="rename-form-<?= md5($it['name']) ?>"
                    title="Rename"><?= ia_icon('file', 13) ?></button>
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
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= ia_icon('folder', 26) ?></div><h4>Empty folder</h4><p>Upload files or create a folder to get started.</p></div>
  <?php endif; ?>
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
    <div class="modal-body text-center p-0"><img id="preview-img" src="" alt="" class="fm-preview-img"></div>
  </div></div>
</div>

<!-- rename modal -->
<div class="modal fade" id="fmod-rename" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Rename</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <label class="form-label fs-13">Current name: <strong id="fm-rename-old"></strong></label>
      <input id="fm-rename-input" class="form-control mt-1" type="text" placeholder="New name" autocomplete="off">
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline-ia btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-grad btn-sm" id="fm-rename-ok">Rename</button>
    </div>
  </div></div>
</div>

<script>
  document.querySelectorAll('[data-bs-toggle="modal"][data-preview]').forEach((el) => {
    el.addEventListener('click', () => {
      document.getElementById('preview-img').src = el.dataset.preview
      document.getElementById('preview-name').textContent = el.dataset.name
    })
  })

  /* -------- rename modal -------- */
  let _renameTarget = null
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-fm-rename]')
    if (!btn) return
    _renameTarget = btn
    document.getElementById('fm-rename-old').textContent = btn.dataset.old
    const input = document.getElementById('fm-rename-input')
    input.value = btn.dataset.old
    bootstrap.Modal.getOrCreateInstance(document.getElementById('fmod-rename')).show()
    setTimeout(() => { input.focus(); input.select() }, 300)
  })
  document.getElementById('fm-rename-ok').addEventListener('click', () => {
    if (!_renameTarget) return
    const val = document.getElementById('fm-rename-input').value.trim()
    if (!val) return
    const form = document.getElementById(_renameTarget.dataset.form)
    form.querySelector('input[name="new"]').value = val
    bootstrap.Modal.getInstance(document.getElementById('fmod-rename')).hide()
    form.submit()
  })
  document.getElementById('fm-rename-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('fm-rename-ok').click() }
  })
</script>