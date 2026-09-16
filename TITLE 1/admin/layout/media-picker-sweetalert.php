<?php
/**
 * Innovatech PH — SweetAlert-based Media Picker Component
 * Uses SweetAlert2 instead of Bootstrap modal to avoid modal conflicts
 * 
 * Expected vars:
 *   $pickerName      (string)  field base name, e.g. 'cover_image'
 *   $pickerValue     (string)  current value (existing path/URL)
 *   $pickerLabel     (string)  human label shown above
 *   $pickerHelp      (string)  optional extra help text
 *   $pickerAccept    (string)  file accept attr, default 'image/*'
 *   $pickerSizeGuide (string)  optional size/aspect ratio hint
 */
$pickerName = $pickerName ?? 'media_file';
$pickerValue = $pickerValue ?? '';
$pickerLabel = $pickerLabel ?? 'Image';
$pickerHelp = $pickerHelp ?? '';
$pickerAccept = $pickerAccept ?? 'image/*';
$pickerSizeGuide = $pickerSizeGuide ?? '';
$pickerId = 'picker_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pickerName) . '_' . substr(md5($pickerName . $pickerLabel), 0, 6);
$pickerLibrary = $pickerLibrary ?? 'auto';
$pickerPreview = $pickerValue !== '' ? media_url((string) $pickerValue) : '';

// Auto-derive size guide from field name if not provided
if (!$pickerSizeGuide) {
    $autoGuides = [
        'equirect' => '2:1 equirectangular panorama — min 4096×2048px',
        'cover' => '16:9 recommended — min 1280×720px, ideal 1920×1080px',
        'featured' => '4:3 or 16:9 — min 800×450px',
        'hero' => '16:9 full-width — recommended 1920×1080px or wider',
        'logo' => 'Square or transparent PNG — recommended 512×512px',
        'floor' => 'Any aspect ratio — min 1024px wide, PNG/SVG preferred for sharp lines',
        'image' => 'Any image format (PNG, JPG, WebP) — e.g. min 800×600px',
        'bg' => '16:9 or wider — min 1920×1080px, JPEG recommended',
        'thumbnail' => '4:3 — recommended 800×600px',
    ];
    foreach ($autoGuides as $key => $guide) {
        if (str_contains(strtolower($pickerName), $key) || str_contains(strtolower($pickerLabel), $key)) {
            $pickerSizeGuide = $guide;
            break;
        }
    }
}

// Library: platform (owner website) uses public/ + assets/; org dashboards use the tenant folder.
$scopedFiles = [];
$activeInst = function_exists('resolve_active_institution') ? resolve_active_institution() : null;
$usePlatform = $pickerLibrary === 'platform' || ($pickerLibrary === 'auto' && !$activeInst);
if ($usePlatform) {
    $scopedFiles = list_image_library(['public', 'assets']);
} elseif ($activeInst && !empty($activeInst['folder_path'])) {
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
                        'path' => trim($activeInst['folder_path'], '/') . '/' . $rel,
                        'url' => org_url($activeInst['slug'], $rel)
                    ];
                }
            }
        }
    }
}

// Prepare files data for JavaScript
$filesJson = json_encode($scopedFiles, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
?>
<div class="ia-media-picker-wrapper mb-3" id="wrap_<?= h($pickerId) ?>">
    <label class="form-label fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?= h($pickerLabel) ?></span>
        <?php if ($pickerSizeGuide): ?>
            <span class="badge text-wrap text-start mp-size-guide">
                <?= h($pickerSizeGuide) ?>
            </span>
        <?php endif; ?>
    </label>

    <!-- Trigger preview / select bar -->
    <div class="d-flex align-items-center flex-wrap gap-2 gap-sm-3 p-2 border rounded-3 mp-bar">

        <div class="d-flex align-items-center gap-2 gap-sm-3 flex-grow-1 min-w-0 overflow-hidden">
            <div id="<?= h($pickerId) ?>_preview" class="mp-preview">
                <?php if ($pickerPreview && preg_match('/\.(jpe?g|png|webp|gif|svg|avif)(\?|$)/i', $pickerPreview)): ?>
                    <img src="<?= h($pickerPreview) ?>" alt="preview">
                <?php else: ?>
                    <i class="fa-regular fa-image" style="font-size: 22px; color: #aab2c0;"></i>
                <?php endif; ?>
            </div>

            <div class="flex-grow-1 min-w-0 overflow-hidden">
                <div id="<?= h($pickerId) ?>_label" class="fw-semibold text-truncate fs-13">
                    <?= $pickerValue ? h(basename($pickerValue)) : '<span class="text-muted">No media selected</span>' ?>
                </div>
                <div id="<?= h($pickerId) ?>_sub" class="text-muted text-truncate fs-11">
                    <?= $pickerValue ? h($pickerValue) : 'Choose a file, library image, or paste a link' ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-1 flex-shrink-0 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-ia"
                onclick="openSweetMediaPicker('<?= h($pickerId) ?>', <?= htmlspecialchars($filesJson, ENT_QUOTES, 'UTF-8') ?>, '<?= h($pickerLabel) ?>', '<?= h($pickerSizeGuide) ?>', '<?= $usePlatform ? 'Project files' : 'My system files' ?>')">
                <?= ia_icon('image', 14) ?> Choose Media
            </button>
            <?php if ($pickerValue): ?>
                <button type="button" class="btn btn-sm btn-outline-ia text-danger"
                    onclick="clearMediaPicker('<?= h($pickerId) ?>')">
                    <?= ia_icon('x', 14) ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden form input for submission -->
    <input type="hidden" name="<?= h($pickerName) ?>_url" id="<?= h($pickerId) ?>_url" value="<?= h($pickerValue) ?>">
    <input type="file" name="<?= h($pickerName) ?>_upload" id="<?= h($pickerId) ?>_file_real"
        accept="<?= h($pickerAccept) ?>" class="d-none"
        onchange="handleDirectFileUpload(this, '<?= h($pickerId) ?>')" style="max-width: 100%">

    <?php if ($pickerHelp): ?>
        <div class="form-text mt-1"><?= $pickerHelp ?></div>
    <?php endif; ?>
</div>

<script>
    if (typeof window.sweetMediaPickerInit === 'undefined') {
        window.sweetMediaPickerInit = true;

        window.mediaPreviewSrc = function (val) {
            if (!val) return '';
            if (/^(https?:)?\/\//i.test(val) || val.indexOf('data:') === 0) return val;
            var base = (window.IA_BASE_URL || '').replace(/\/$/, '');
            return base + '/' + val.replace(/^\/+/, '');
        };

        window.openSweetMediaPicker = function(pickerId, files, label, sizeGuide, filesLabel) {
            var filesHtml = '';
            if (files && files.length > 0) {
                filesHtml = '<div class="row g-2" style="max-height: 300px; overflow-y: auto;">';
                files.forEach(function(file) {
                    filesHtml += '<div class="col-6 col-sm-4 col-md-3">' +
                        '<div class="p-2 border rounded-2 text-center position-relative h-100" style="cursor: pointer;" ' +
                        'onclick="selectScopedFileSwal(\'' + pickerId + '\', \'' + file.path + '\', \'' + file.url + '\', \'' + file.name + '\')">' +
                        '<div style="height: 60px; overflow: hidden;">' +
                        '<img src="' + file.url + '" style="width: 100%; height: 100%; object-fit: cover;" alt="">' +
                        '</div>' +
                        '<div class="small fw-semibold text-truncate fs-11 mt-1">' + file.name + '</div>' +
                        '</div></div>';
                });
                filesHtml += '</div>';
            } else {
                filesHtml = '<div class="text-center py-4 text-muted">' +
                    '<p class="mb-0">No images found.</p>' +
                    '<small>Use Upload tab to add files.</small></div>';
            }

            var uploadHtml = '<div class="text-center p-4 border rounded-3">' +
                '<div class="mb-3">' +
                '<i class="fa-solid fa-cloud-arrow-up" style="font-size: 40px; color: #6a707f;"></i>' +
                '</div>' +
                '<h6 class="fw-bold">Upload an image file</h6>' +
                '<p class="text-muted small mb-3">Accepted formats: PNG, JPG, WebP, SVG, AVIF</p>' +
                '<button type="button" class="btn btn-outline-ia" ' +
                'onclick="document.getElementById(\'' + pickerId + '_file_real\').click()">Browse Files...</button>';
            
            if (sizeGuide) {
                uploadHtml += '<div class="badge mt-3" style="background: #f8f9fa; border: 1px solid #dee2e6;">' + sizeGuide + '</div>';
            }
            uploadHtml += '</div>';

            Swal.fire({
                title: label,
                html: '<div class="swal-media-picker">' +
                    '<div class="nav nav-tabs mb-3">' +
                    '<button class="nav-link active" id="swal-upload-tab-' + pickerId + '" onclick="switchSwalTab(\'' + pickerId + '\', \'upload\')">Upload File</button>' +
                    '<button class="nav-link" id="swal-files-tab-' + pickerId + '" onclick="switchSwalTab(\'' + pickerId + '\', \'files\')">' + filesLabel + ' (' + (files ? files.length : 0) + ')</button>' +
                    '<button class="nav-link" id="swal-url-tab-' + pickerId + '" onclick="switchSwalTab(\'' + pickerId + '\', \'url\')">Direct URL / Path</button>' +
                    '</div>' +
                    '<div id="swal-upload-content-' + pickerId + '">' + uploadHtml + '</div>' +
                    '<div id="swal-files-content-' + pickerId + '" style="display: none;">' + filesHtml + '</div>' +
                    '<div id="swal-url-content-' + pickerId + '" style="display: none;">' +
                    '<label class="form-label small fw-semibold">Paste web link or project relative path</label>' +
                    '<div class="input-group mb-3">' +
                    '<input type="text" class="form-control" id="' + pickerId + '_manual_input" ' +
                    'placeholder="https://example.com/image.jpg or assets/image.jpg" ' +
                    'value="' + (document.getElementById(pickerId + '_url').value || '') + '">' +
                    '<button class="btn btn-grad" type="button" onclick="applyManualUrlSwal(\'' + pickerId + '\')">Apply Link</button>' +
                    '</div>' +
                    '<div class="form-text small">Supports external HTTPS URLs and internal repository paths.</div>' +
                    '</div>' +
                    '</div>',
                width: '600px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'media-picker-swal'
                }
            });
        };

        window.switchSwalTab = function(pickerId, tab) {
            document.getElementById('swal-upload-content-' + pickerId).style.display = tab === 'upload' ? 'block' : 'none';
            document.getElementById('swal-files-content-' + pickerId).style.display = tab === 'files' ? 'block' : 'none';
            document.getElementById('swal-url-content-' + pickerId).style.display = tab === 'url' ? 'block' : 'none';
            
            document.getElementById('swal-upload-tab-' + pickerId).classList.toggle('active', tab === 'upload');
            document.getElementById('swal-files-tab-' + pickerId).classList.toggle('active', tab === 'files');
            document.getElementById('swal-url-tab-' + pickerId).classList.toggle('active', tab === 'url');
        };

        window.selectScopedFileSwal = function(pickerId, relPath, fullUrl, fileName) {
            document.getElementById(pickerId + '_url').value = relPath;
            document.getElementById(pickerId + '_file_real').value = '';
            document.getElementById(pickerId + '_label').textContent = fileName;
            document.getElementById(pickerId + '_sub').textContent = relPath;
            document.getElementById(pickerId + '_preview').innerHTML = '<img src="' + (fullUrl || mediaPreviewSrc(relPath)) + '">';
            Swal.close();
        };

        window.handleDirectFileUpload = function (input, pickerId) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate image dimensions
                const img = new Image();
                img.onload = function() {
                    if (img.width < 800 || img.height < 450) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Image too small',
                            text: 'Minimum dimensions: 800×450px'
                        });
                        input.value = '';
                        return;
                    }
                    
                    // Calculate aspect ratio
                    const ratio = img.width / img.height;
                    let hasWarning = false;
                    if (ratio < 0.75 || ratio > 2.0) {
                        hasWarning = true;
                        Swal.fire({
                            icon: 'warning',
                            title: 'Unusual aspect ratio',
                            text: 'Recommended: 4:3 or 16:9'
                        });
                    }
                    
                    // Continue with upload...
                    document.getElementById(pickerId + '_url').value = '';
                    document.getElementById(pickerId + '_label').textContent = file.name;
                    document.getElementById(pickerId + '_sub').textContent = `${img.width}×${img.height}px`;
                    
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        document.getElementById(pickerId + '_preview').innerHTML = '<img src="' + e.target.result + '">';
                    };
                    reader.readAsDataURL(file);
                    
                    if (!hasWarning) {
                        Swal.close();
                    }
                };
                img.src = URL.createObjectURL(file);
            }
        };

        window.applyManualUrlSwal = function (pickerId) {
            const val = document.getElementById(pickerId + '_manual_input').value.trim();
            if (val) {
                document.getElementById(pickerId + '_url').value = val;
                const name = val.split('/').pop() || val;
                document.getElementById(pickerId + '_label').textContent = name;
                document.getElementById(pickerId + '_sub').textContent = val;
                document.getElementById(pickerId + '_file_real').value = '';
                const img = document.createElement('img');
                img.src = mediaPreviewSrc(val);
                img.onerror = function() { this.outerHTML = '<i class="fa-regular fa-image" style="font-size: 22px; color: #aab2c0;"></i>'; };
                const prev = document.getElementById(pickerId + '_preview');
                prev.innerHTML = '';
                prev.appendChild(img);
            }
            Swal.close();
        };

        window.clearMediaPicker = function (pickerId) {
            document.getElementById(pickerId + '_url').value = '';
            const fileInput = document.getElementById(pickerId + '_file_real');
            if (fileInput) fileInput.value = '';
            document.getElementById(pickerId + '_label').innerHTML = '<span class="text-muted">No media selected</span>';
            document.getElementById(pickerId + '_sub').textContent = 'Choose a file or enter a link';
            document.getElementById(pickerId + '_preview').innerHTML = '<i class="fa-regular fa-image" style="font-size: 22px; color: #aab2c0;"></i>';
        };
    }
</script>

<style>
    .swal-media-picker .nav-tabs {
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 1rem;
    }
    .swal-media-picker .nav-link {
        border: none;
        border-bottom: 2px solid transparent;
        color: #6c757d;
        padding: 0.5rem 1rem;
        cursor: pointer;
        background: none;
    }
    .swal-media-picker .nav-link:hover {
        color: #0d6efd;
    }
    .swal-media-picker .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background: none;
    }
    .media-picker-swal {
        border-radius: 12px !important;
    }
</style>