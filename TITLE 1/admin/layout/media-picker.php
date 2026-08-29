<?php
/**
 * Innovatech PH — Universal Tabbed Modal Media Picker Component
 * Provides a clean inline trigger and an interactive Bootstrap Modal with 3 Tabs:
 *   Tab 1: Upload New File
 *   Tab 2: Select from Institution Files (Scoped & Secure)
 *   Tab 3: Paste Direct URL or Relative Path
 * 
 * Expected vars:
 *   $pickerName      (string)  field base name, e.g. 'cover_image'
 *   $pickerValue     (string)  current value (existing path/URL)
 *   $pickerLabel     (string)  human label shown above
 *   $pickerHelp      (string)  optional extra help text
 *   $pickerAccept    (string)  file accept attr, default 'image/*'
 *   $pickerSizeGuide (string)  optional size/aspect ratio hint
 */
$pickerName      = $pickerName      ?? 'media_file';
$pickerValue     = $pickerValue     ?? '';
$pickerLabel     = $pickerLabel     ?? 'Image';
$pickerHelp      = $pickerHelp      ?? '';
$pickerAccept    = $pickerAccept    ?? 'image/*';
$pickerSizeGuide = $pickerSizeGuide ?? '';
$pickerId        = 'picker_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pickerName) . '_' . substr(md5($pickerName . $pickerLabel), 0, 6);

// Auto-derive size guide from field name if not provided
if (!$pickerSizeGuide) {
    $autoGuides = [
        'equirect'  => '2:1 equirectangular panorama — recommended 4096×2048px or 8192×4096px (JPEG/PNG)',
        'cover'     => '16:9 recommended — min 1280×720px, ideal 1920×1080px',
        'featured'  => '4:3 or 16:9 — min 800×450px',
        'hero'      => '16:9 full-width — recommended 1920×1080px or wider',
        'logo'      => 'Square or transparent PNG — recommended 512×512px',
        'floor'     => 'Any aspect ratio — min 1024px wide, PNG/SVG preferred for sharp lines',
        'image'     => 'Any image format (PNG, JPG, WebP) — min 800px wide recommended',
        'bg'        => '16:9 or wider — min 1920×1080px, JPEG recommended',
        'thumbnail' => '4:3 — recommended 800×600px',
    ];
    foreach ($autoGuides as $key => $guide) {
        if (str_contains(strtolower($pickerName), $key) || str_contains(strtolower($pickerLabel), $key)) {
            $pickerSizeGuide = $guide;
            break;
        }
    }
}

// Get scoped files list for the current institution
$scopedFiles = [];
$activeInst = function_exists('resolve_active_institution') ? resolve_active_institution() : null;
if ($activeInst && !empty($activeInst['folder_path'])) {
    $instDir = ROOT_PATH . '/' . trim($activeInst['folder_path'], '/');
    if (is_dir($instDir)) {
        $rdi = new RecursiveDirectoryIterator($instDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $rii = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'])) {
                    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($instDir) + 1));
                    $scopedFiles[] = [
                        'name' => basename($rel),
                        'path' => $rel,
                        'url'  => org_url($activeInst['slug'], $rel)
                    ];
                }
            }
        }
    }
}
?>
<div class="ia-media-picker-wrapper mb-3" id="wrap_<?= h($pickerId) ?>">
    <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
        <span><?= h($pickerLabel) ?></span>
        <?php if ($pickerSizeGuide): ?>
            <span class="badge" style="background:rgba(91,91,214,0.15); color:var(--ia-primary); font-weight:600; font-size:11px;">
                <?= h($pickerSizeGuide) ?>
            </span>
        <?php endif; ?>
    </label>

    <!-- Trigger preview / select bar -->
    <div class="d-flex align-items-center gap-3 p-2 border rounded-3" style="background:var(--ia-surface-2); border-color:var(--ia-border) !important;">
        <div id="<?= h($pickerId) ?>_preview" style="width:52px; height:52px; border-radius:8px; border:1px solid var(--ia-border); display:grid; place-items:center; background:var(--ia-surface); overflow:hidden; flex-shrink:0;">
            <?php if ($pickerValue && preg_match('/\.(jpe?g|png|webp|gif|svg|avif)$/i', $pickerValue)): ?>
                <img src="<?= h($pickerValue) ?>" style="width:100%; height:100%; object-fit:cover;" alt="preview">
            <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            <?php endif; ?>
        </div>

        <div class="flex-grow-1 overflow-hidden" style="min-width:0;">
            <div id="<?= h($pickerId) ?>_label" class="fw-semibold text-truncate" style="font-size:13px;">
                <?= $pickerValue ? h(basename($pickerValue)) : '<span class="text-muted">No media selected</span>' ?>
            </div>
            <div id="<?= h($pickerId) ?>_sub" class="text-muted text-truncate" style="font-size:11px;">
                <?= $pickerValue ? h($pickerValue) : 'Choose a file or enter a link' ?>
            </div>
        </div>

        <div class="d-flex gap-1 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#modal_<?= h($pickerId) ?>">
                <?= ia_icon('image', 14) ?> Choose Media
            </button>
            <?php if ($pickerValue): ?>
                <button type="button" class="btn btn-sm btn-outline-ia text-danger" onclick="clearMediaPicker('<?= h($pickerId) ?>')">
                    <?= ia_icon('x', 14) ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden form input for submission -->
    <input type="hidden" name="<?= h($pickerName) ?>_url" id="<?= h($pickerId) ?>_url" value="<?= h($pickerValue) ?>">

    <?php if ($pickerHelp): ?>
        <div class="form-text mt-1"><?= $pickerHelp ?></div>
    <?php endif; ?>
</div>

<!-- Modal Component -->
<div class="modal fade" id="modal_<?= h($pickerId) ?>" tabindex="-1" aria-labelledby="modalLabel_<?= h($pickerId) ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:var(--ia-surface); border:1px solid var(--ia-border); border-radius:18px;">
            <div class="modal-header border-bottom" style="border-color:var(--ia-border) !important;">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modalLabel_<?= h($pickerId) ?>"><?= h($pickerLabel) ?></h5>
                    <p class="text-muted small mb-0">Select or upload media for this field</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs px-3 pt-2" id="tab_<?= h($pickerId) ?>" role="tablist" style="border-color:var(--ia-border);">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="upload-tab-<?= h($pickerId) ?>" data-bs-toggle="tab" data-bs-target="#tab-upload-<?= h($pickerId) ?>" type="button" role="tab">
                            📁 Upload File
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="files-tab-<?= h($pickerId) ?>" data-bs-toggle="tab" data-bs-target="#tab-files-<?= h($pickerId) ?>" type="button" role="tab">
                            🗄️ My System Files (<?= count($scopedFiles) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="url-tab-<?= h($pickerId) ?>" data-bs-toggle="tab" data-bs-target="#tab-url-<?= h($pickerId) ?>" type="button" role="tab">
                            🔗 Direct URL / Path
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4">
                    <!-- TAB 1: Upload File -->
                    <div class="tab-pane fade show active" id="tab-upload-<?= h($pickerId) ?>" role="tabpanel">
                        <div class="text-center p-4 border rounded-3" style="background:var(--ia-surface-2); border-style:dashed !important; border-color:var(--ia-border) !important;">
                            <div class="mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--ia-primary);"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </div>
                            <h6 class="fw-bold">Upload an image file</h6>
                            <p class="text-muted small mb-3">Accepted formats: PNG, JPG, WebP, SVG, AVIF</p>
                            
                            <input class="form-control form-control-sm mx-auto" style="max-width:320px;"
                                   type="file"
                                   name="<?= h($pickerName) ?>_upload"
                                   id="<?= h($pickerId) ?>_file"
                                   accept="<?= h($pickerAccept) ?>"
                                   onchange="handleDirectFileUpload(this, '<?= h($pickerId) ?>')">

                            <?php if ($pickerSizeGuide): ?>
                                <div class="badge mt-3" style="background:rgba(91,91,214,0.12); color:var(--ia-primary); font-size:11px;">
                                    💡 <?= h($pickerSizeGuide) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 2: Scoped System Files -->
                    <div class="tab-pane fade" id="tab-files-<?= h($pickerId) ?>" role="tabpanel">
                        <?php if (!empty($scopedFiles)): ?>
                            <div class="row g-2" style="max-height:300px; overflow-y:auto;">
                                <?php foreach ($scopedFiles as $sf): ?>
                                    <div class="col-6 col-sm-4 col-md-3">
                                        <div class="p-2 border rounded-2 text-center position-relative cursor-pointer h-100 hover-lift"
                                             style="background:var(--ia-surface-2); border-color:var(--ia-border) !important; cursor:pointer;"
                                             onclick="selectScopedFile('<?= h($pickerId) ?>', '<?= h($sf['path']) ?>', '<?= h($sf['url']) ?>', '<?= h($sf['name']) ?>')">
                                            <div style="height:64px; border-radius:4px; overflow:hidden; background:#000; margin-bottom:6px;">
                                                <img src="<?= h($sf['url']) ?>" style="width:100%; height:100%; object-fit:cover;" alt="">
                                            </div>
                                            <div class="small fw-semibold text-truncate" style="font-size:11px;"><?= h($sf['name']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">No images uploaded in your institution folder yet.</p>
                                <small>Use Tab 1 to upload new files or visit <a href="files" target="_blank">File Manager</a>.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 3: Paste URL -->
                    <div class="tab-pane fade" id="tab-url-<?= h($pickerId) ?>" role="tabpanel">
                        <label class="form-label small fw-semibold">Paste web link or project relative path</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="<?= h($pickerId) ?>_manual_input" placeholder="https://example.com/image.jpg or assets/image.jpg" value="<?= h($pickerValue) ?>">
                            <button class="btn btn-grad" type="button" onclick="applyManualUrl('<?= h($pickerId) ?>')">Apply Link</button>
                        </div>
                        <div class="form-text small">Supports external HTTPS URLs and internal repository paths.</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top" style="border-color:var(--ia-border) !important;">
                <button type="button" class="btn btn-sm btn-outline-ia" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Move the media picker modal to body to avoid Bootstrap nested modal z-index/backdrop issues
(function() {
    var modalId = "modal_<?= h($pickerId) ?>";
    var m = document.getElementById(modalId);
    if (m && m.parentElement !== document.body) {
        document.body.appendChild(m);
    }
})();
</script>

<script>
if (typeof window.mediaPickerInit === 'undefined') {
    window.mediaPickerInit = true;

    window.selectScopedFile = function(pickerId, relPath, fullUrl, fileName) {
        document.getElementById(pickerId + '_url').value = relPath;
        document.getElementById(pickerId + '_label').textContent = fileName;
        document.getElementById(pickerId + '_sub').textContent = relPath;
        document.getElementById(pickerId + '_preview').innerHTML = '<img src="' + fullUrl + '" style="width:100%; height:100%; object-fit:cover;">';
        
        const modalEl = document.getElementById('modal_' + pickerId);
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    };

    window.handleDirectFileUpload = function(input, pickerId) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById(pickerId + '_url').value = ''; // Direct upload takes precedence
            document.getElementById(pickerId + '_label').textContent = file.name;
            document.getElementById(pickerId + '_sub').textContent = 'Selected for upload (' + (file.size / 1024).toFixed(1) + ' KB)';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(pickerId + '_preview').innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover;">';
            };
            reader.readAsDataURL(file);

            const modalEl = document.getElementById('modal_' + pickerId);
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    };

    window.applyManualUrl = function(pickerId) {
        const val = document.getElementById(pickerId + '_manual_input').value.trim();
        if (val) {
            document.getElementById(pickerId + '_url').value = val;
            const name = val.split('/').pop() || val;
            document.getElementById(pickerId + '_label').textContent = name;
            document.getElementById(pickerId + '_sub').textContent = val;
            document.getElementById(pickerId + '_preview').innerHTML = '<img src="' + val + '" style="width:100%; height:100%; object-fit:cover;" onerror="this.src=\'data:image/svg+xml;utf8,<svg xmlns=\\\'http://www.w3.org/2000/svg\\\' width=\\\'24\\\' height=\\\'24\\\' viewBox=\\\'0 0 24 24\\\' fill=\\\'none\\\' stroke=\\\'%23999\\\' stroke-width=\\\'2\\\'><rect x=\\\'3\\\' y=\\\'3\\\' width=\\\'18\\\' height=\\\'18\\\' rx=\\\'2\\\'/></svg>\'">';
        }
        const modalEl = document.getElementById('modal_' + pickerId);
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    };

    window.clearMediaPicker = function(pickerId) {
        document.getElementById(pickerId + '_url').value = '';
        const fileInput = document.getElementById(pickerId + '_file');
        if (fileInput) fileInput.value = '';
        document.getElementById(pickerId + '_label').innerHTML = '<span class="text-muted">No media selected</span>';
        document.getElementById(pickerId + '_sub').textContent = 'Choose a file or enter a link';
        document.getElementById(pickerId + '_preview').innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
    };
}
</script>
