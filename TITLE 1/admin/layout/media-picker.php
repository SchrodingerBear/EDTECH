<?php
/**
 * Innovatech PH — Universal Media Picker Component
 * Expected vars:
 *   $pickerName    (string)  field base name, e.g. 'cover_image'
 *   $pickerValue   (string)  current value (existing path/URL)
 *   $pickerLabel   (string)  human label shown above
 *   $pickerHelp    (string)  optional extra help text
 *   $pickerAccept  (string)  file accept attr, default 'image/*'
 *   $pickerSizeGuide (string) optional size/aspect ratio hint, e.g. '16:9 recommended (1280×720px+)'
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
?>
<div class="ia-media-picker mb-2">
    <label class="form-label fw-semibold"><?= h($pickerLabel) ?></label>

    <?php if ($pickerSizeGuide): ?>
    <div class="d-flex align-items-start gap-2 mb-2 px-2 py-2" style="background:rgba(91,91,214,.08);border:1px solid rgba(91,91,214,.2);border-radius:8px;font-size:12px;color:var(--ia-muted)">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= h($pickerSizeGuide) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($pickerValue): ?>
    <div class="mb-2 d-flex align-items-center gap-2" style="font-size:12px;color:var(--ia-muted)">
        <?php if (preg_match('/\.(jpe?g|png|webp|gif|svg|avif)$/i', $pickerValue)): ?>
            <img src="<?= h($pickerValue) ?>" style="height:40px;width:auto;border-radius:6px;border:1px solid var(--ia-border);object-fit:cover" alt="current">
        <?php endif ?>
        <span>Current: <code style="font-size:11px"><?= h(basename($pickerValue)) ?></code></span>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 align-items-center flex-wrap">
        <!-- Direct File Upload -->
        <div class="flex-grow-1" style="min-width:180px">
            <label class="form-label small mb-1" style="color:var(--ia-muted)">Upload file</label>
            <input class="form-control form-control-sm"
                   type="file"
                   name="<?= h($pickerName) ?>_upload"
                   id="<?= h($pickerId) ?>_file"
                   accept="<?= h($pickerAccept) ?>"
                   onchange="document.getElementById('<?= h($pickerId) ?>_url').value = '';">
        </div>
        <div class="text-muted small align-self-end pb-1">or</div>
        <!-- URL/Path Input -->
        <div class="flex-grow-1" style="min-width:180px">
            <label class="form-label small mb-1" style="color:var(--ia-muted)">URL / path</label>
            <input class="form-control form-control-sm"
                   type="text"
                   name="<?= h($pickerName) ?>_url"
                   id="<?= h($pickerId) ?>_url"
                   value="<?= h($pickerValue) ?>"
                   placeholder="https://… or assets/image.jpg"
                   oninput="document.getElementById('<?= h($pickerId) ?>_file').value = '';">
        </div>
    </div>

    <?php if ($pickerHelp): ?>
        <div class="form-text mt-1"><?= $pickerHelp ?></div>
    <?php endif; ?>

    <div class="form-text mt-1" style="font-size:11px;color:var(--ia-muted)">
        Upload a file, paste an external URL, or copy a path from the <a href="files" target="_blank">File Manager</a>.
    </div>
</div>
