<?php
/**
 * Innovatech PH — Universal Media Picker Component
 * Expected vars: $pickerName, $pickerValue, $pickerLabel, $pickerHelp, $pickerAccept
 */
$pickerName = $pickerName ?? 'media_file';
$pickerValue = $pickerValue ?? '';
$pickerLabel = $pickerLabel ?? 'Image';
$pickerHelp = $pickerHelp ?? '';
$pickerAccept = $pickerAccept ?? 'image/*';
$pickerId = 'picker_' . preg_replace('/[^a-zA-Z0-9]/', '', $pickerName);
?>
<div class="ia-media-picker mb-3">
    <label class="form-label"><?= h($pickerLabel) ?></label>
    
    <div class="d-flex gap-2 mb-2 align-items-center">
        <!-- Direct File Upload -->
        <div class="flex-grow-1">
            <input class="form-control form-control-sm" type="file" name="<?= h($pickerName) ?>_upload" id="<?= h($pickerId) ?>_file" accept="<?= h($pickerAccept) ?>" onchange="document.getElementById('<?= h($pickerId) ?>_url').value = '';">
        </div>
        <span class="text-muted small">OR</span>
        <!-- URL/Path Input -->
        <div class="flex-grow-1">
            <input class="form-control form-control-sm" type="text" name="<?= h($pickerName) ?>_url" id="<?= h($pickerId) ?>_url" value="<?= h($pickerValue) ?>" placeholder="e.g. public/assets/image.jpg or https://..." oninput="document.getElementById('<?= h($pickerId) ?>_file').value = '';">
        </div>
    </div>
    
    <?php if ($pickerHelp): ?>
        <div class="form-text mt-1"><?= $pickerHelp ?></div>
    <?php endif; ?>
    
    <div class="form-text text-muted" style="font-size:11px;">
        <i class="ti ti-info-circle"></i> You can upload a file, paste an external link, or copy a path from the <a href="files" target="_blank">File Manager</a>.
    </div>
</div>
