<?php
/**
 * Innovatech PH — AR Target Manager: shared helpers.
 * Included by admin/institution/ar.php (and its early JSON endpoint).
 */

/** Idempotent schema bootstrap (same DDL as database/update.sql) so the page needs no manual SQL. */
function ar_ensure_schema(): bool
{
    try {
        crud()->raw(
            "CREATE TABLE IF NOT EXISTS `ar_targets` (
              `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
              `institution_id` int UNSIGNED NOT NULL,
              `ar_type` enum('360_ar_hotspot','floorplan_ar') NOT NULL,
              `building_id` int UNSIGNED DEFAULT NULL,
              `floor_plan_id` int UNSIGNED NOT NULL,
              `source_fingerprint` varchar(64) NOT NULL,
              `source_marker_id` int UNSIGNED DEFAULT NULL,
              `name` varchar(191) NOT NULL DEFAULT '',
              `target_scene_id` int UNSIGNED DEFAULT NULL,
              `target_floor_plan_id` int UNSIGNED DEFAULT NULL,
              `image_source` enum('auto','manual') NOT NULL DEFAULT 'auto',
              `image_path` varchar(255) DEFAULT NULL,
              `auto_image_path` varchar(255) DEFAULT NULL,
              `manual_image_path` varchar(255) DEFAULT NULL,
              `popup_title` varchar(191) DEFAULT NULL,
              `popup_description` text,
              `popup_image_path` varchar(255) DEFAULT NULL,
              `target_mind_path` varchar(255) DEFAULT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT '1',
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_inst_fp` (`institution_id`,`source_fingerprint`),
              KEY `idx_inst_fp` (`institution_id`,`floor_plan_id`),
              KEY `idx_ar_type` (`ar_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $col = crud()->raw(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'floor_plans' AND COLUMN_NAME = 'ar_mind_path'"
        )->fetchColumn();
        if ((int) $col === 0) {
            crud()->raw("ALTER TABLE `floor_plans` ADD COLUMN `ar_mind_path` varchar(255) DEFAULT NULL AFTER `image_path`");
        }
        $col = crud()->raw(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tour_scenes' AND COLUMN_NAME = 'ar_mind_path'"
        )->fetchColumn();
        if ((int) $col === 0) {
            crud()->raw("ALTER TABLE `tour_scenes` ADD COLUMN `ar_mind_path` varchar(255) DEFAULT NULL AFTER `featured_image_path`");
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Stable fingerprint of a marker — survives studio delete+reinsert while geometry is unchanged. */
function ar_source_fp(array $m): string
{
    return md5(implode('|', [
        (int) $m['floor_plan_id'],
        (string) ($m['marker_type'] ?? ''),
        (string) ($m['label'] ?? ''),
        round((float) ($m['x_percent'] ?? 0), 3),
        round((float) ($m['y_percent'] ?? 0), 3),
        (int) ($m['target_scene_id'] ?? 0),
        (int) ($m['target_floor_plan_id'] ?? 0),
        round((float) ($m['facing_angle'] ?? 0), 1),
    ]));
}

/** Map a floor-plan marker to an AR target type, or null when it is not AR-relevant. */
function ar_ar_type_for(array $m): ?string
{
    if (in_array($m['marker_type'] ?? '', ['scene', '360'], true) && !empty($m['target_scene_id'])) {
        return '360_ar_hotspot';
    }
    if (($m['marker_type'] ?? '') === 'floorplan' && !empty($m['target_floor_plan_id'])) {
        return 'floorplan_ar';
    }
    // An "AR Landmark" marker placed on a plan IS a landmark of that plan:
    // trained with the floor-plan .mind, its target floor plan = its own plan.
    if (($m['marker_type'] ?? '') === 'ar') {
        return 'floorplan_ar';
    }
    return null;
}

/** Create missing AR targets from floor-plan markers. Never deletes or overwrites existing targets. */
function ar_ensure_targets(int $iid): int
{
    $sources = crud()->raw(
        "SELECT m.*, fp.building_id
           FROM floor_plan_markers m
           JOIN floor_plans fp ON fp.id = m.floor_plan_id
          WHERE m.institution_id = :iid AND fp.deleted_at IS NULL
            AND (
                  m.marker_type = 'ar'
               OR (m.marker_type IN ('scene','360') AND m.target_scene_id IS NOT NULL)
               OR (m.marker_type = 'floorplan' AND m.target_floor_plan_id IS NOT NULL)
            )
          ORDER BY fp.id, IFNULL(m.sort_order,0), m.id",
        ['iid' => $iid]
    )->fetchAll();

    $fpMap = [];
    if ($sources) {
        $ids = array_values(array_unique(array_map(static fn ($m) => (int) $m['floor_plan_id'], $sources)));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach (crud()->raw("SELECT * FROM floor_plans WHERE id IN ($ph)", $ids)->fetchAll() as $r) {
            $fpMap[(int) $r['id']] = $r;
        }
    }

    $have = [];
    foreach (crud()->raw("SELECT source_fingerprint FROM ar_targets WHERE institution_id = :iid", ['iid' => $iid])->fetchAll() as $x) {
        $have[$x['source_fingerprint']] = true;
    }

$stmt = db()->prepare(
        "INSERT INTO ar_targets
           (institution_id, ar_type, building_id, floor_plan_id, source_fingerprint, source_marker_id,
            name, target_scene_id, target_floor_plan_id, image_source, auto_image_path, image_path,
            is_active, created_at, updated_at)
         VALUES
           (:iid, :type, :building_id, :fp_id, :fp, :marker_id, :name, :tscene, :tfp, 'auto', :ai_auto, :ai_auto2, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
           source_marker_id = IF(source_marker_id IS NULL OR source_marker_id <> VALUES(source_marker_id), VALUES(source_marker_id), source_marker_id),
           name = IF(CHAR_LENGTH(name) = 0, VALUES(name), name),
           auto_image_path = IF(VALUES(auto_image_path) IS NOT NULL, VALUES(auto_image_path), auto_image_path),
           image_path = IF(image_source = 'auto' AND VALUES(image_path) IS NOT NULL, VALUES(image_path), image_path),
           is_active = IF(is_active = 0, 0, 1)"
    );
    $created = 0;
    foreach ($sources as $m) {
        $t = ar_ar_type_for($m);
        if (!$t) { continue; }
        $fp = $fpMap[(int) $m['floor_plan_id']] ?? null;
        $finger = ar_source_fp($m);
        if (!isset($have[$finger])) { $created++; }
        $ai = null;
        $tfpId = null;
        if ($t === 'floorplan_ar') {
            // AR Landmark markers target their OWN plan (trained with that floor-plan .mind).
            $tfpId = (($m['marker_type'] ?? '') === 'ar')
                ? (int) $m['floor_plan_id']
                : ((int) ($m['target_floor_plan_id'] ?? 0) ?: null);
            $tfp = $tfpId ? ($fpMap[$tfpId] ?? null) : null;
            // Prefer the AR target image uploaded on the marker in the floor-plan studio;
            // only fall back to the linked floor plan's image when the marker has none.
            $ai = !empty($m['marker_image_path'])
                ? trim((string) $m['marker_image_path'])
                : ($tfp ? $tfp['image_path'] : null);
        }
        $stmt->execute([
            'iid' => $iid,
            'type' => $t,
            'building_id' => ($fp && !empty($fp['building_id'])) ? (int) $fp['building_id'] : null,
            'fp_id' => (int) $m['floor_plan_id'],
            'fp' => $finger,
            'marker_id' => (int) $m['id'],
            'name' => (string) ($m['label'] ?? ''),
            'tscene' => !empty($m['target_scene_id']) ? (int) $m['target_scene_id'] : null,
            'tfp' => $tfpId,
            'ai_auto' => $ai,
            'ai_auto2' => $ai,
        ]);
    }

    // Retire stale marker-derived landmarks. The studio saves markers by deleting
    // and re-inserting them (new row ids) and a marker's fingerprint changes when it
    // is re-saved, so a previous target can be left behind with an outdated auto
    // image. When a live AR Landmark marker on the same plan now yields a target
    // with the same name, deactivate the orphaned duplicate.
    $liveMarkerIds = [];
    $liveArNames = [];
    foreach ($sources as $m) {
        $liveMarkerIds[(int) $m['id']] = true;
        if (($m['marker_type'] ?? '') === 'ar') {
            $liveArNames[(int) $m['floor_plan_id']][trim((string) ($m['label'] ?? ''))] = true;
        }
    }
    foreach (crud()->raw(
        "SELECT id, floor_plan_id, name, source_marker_id FROM ar_targets
          WHERE institution_id = :iid AND ar_type = 'floorplan_ar'
            AND is_active = 1 AND source_marker_id IS NOT NULL",
        ['iid' => $iid]
    )->fetchAll() as $o) {
        if (isset($liveMarkerIds[(int) $o['source_marker_id']])) { continue; }
        $nm = trim((string) $o['name']);
        if (!empty($liveArNames[(int) $o['floor_plan_id']][$nm])) {
            crud()->update('ar_targets', ['is_active' => 0], ['id' => (int) $o['id'], 'institution_id' => $iid]);
        }
    }

    return $created;
}

/** Render a rectilinear look of a 360 scene at the given yaw/pitch (defaults to the scene's initial look). */
function ar_render_center_look(int $iid, array $scene, int $fpId, ?float $yawDeg = null, ?float $pitchDeg = null): ?string
{
    $inst = resolve_active_institution();
    if (!$inst) { return null; }
    $orgRel = trim($inst['folder_path'], '/');
    $orgAbs = ROOT_PATH . '/' . $orgRel;
    $eqRel = trim((string) ($scene['equirect_path'] ?? ''), '/');
    if ($eqRel === '') { return null; }
    $eqAbs = ROOT_PATH . '/' . $eqRel;
    if (!is_file($eqAbs)) { return null; }
    $ext = strtolower(pathinfo($eqAbs, PATHINFO_EXTENSION));

    $yawDeg = $yawDeg === null ? (float) ($scene['initial_yaw'] ?? 0) : (float) $yawDeg;
    $pitchDeg = $pitchDeg === null ? (float) ($scene['initial_pitch'] ?? 0) : (float) $pitchDeg;
    $yawDeg = fmod($yawDeg, 360.0);
    if ($yawDeg < 0) { $yawDeg += 360.0; }
    $pitchDeg = max(-89.9, min(89.9, $pitchDeg));

    $seed = md5($eqRel . '|' . round($yawDeg, 1) . '|' . round($pitchDeg, 1));
    $fname = 'center_fp' . (int) $fpId . '_s' . (int) $scene['id'] . '_' . $seed . '.jpg';
    $relOut = $orgRel . '/assets/ar_targets/centerlooks/' . $fname;
    $absOut = $orgAbs . '/assets/ar_targets/centerlooks/' . $fname;
    if (is_file($absOut) && filemtime($absOut) >= filemtime($eqAbs)) {
        return $relOut;
    }

    $src = ($ext === 'png') ? @imagecreatefrompng($eqAbs) : @imagecreatefromjpeg($eqAbs);
    if (!$src) { return null; }
    $eqW = imagesx($src);
    $eqH = imagesy($src);
    if ($eqW < 16 || $eqH < 8) { imagedestroy($src); return null; }
    if (!is_dir(dirname($absOut))) { @mkdir(dirname($absOut), 0775, true); }

    $W = 960; $H = 540;
    $vFov = deg2rad(60.0);
    $f = ($H / 2) / tan($vFov / 2);
    $yaw0 = deg2rad($yawDeg);
    $pitch0 = deg2rad($pitchDeg);
    $cosP = cos($pitch0); $sinP = sin($pitch0);
    $cosY = cos($yaw0); $sinY = sin($yaw0);
    $cx = $W / 2; $cy = $H / 2;

    $out = imagecreatetruecolor($W, $H);
    $byte = static function ($v) {
        return $v < 0 ? 0 : ($v > 255 ? 255 : (int) $v);
    };
    for ($py = 0; $py < $H; $py++) {
        for ($px = 0; $px < $W; $px++) {
            $dx = $px - $cx;
            $dy = $cy - $py;
            $dz = $f;
            $n = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            $dx /= $n; $dy /= $n; $dz /= $n;

            $y1 = $dy * $cosP - $dz * $sinP;
            $z1 = $dy * $sinP + $dz * $cosP;
            $x2 = $dx * $cosY + $z1 * $sinY;
            $z2 = -$dx * $sinY + $z1 * $cosY;

            $lat = asin(max(-1, min(1, $y1)));
            $lon = atan2($x2, $z2);
            $u = (($lon + M_PI) / (2 * M_PI)) * $eqW;
            $v = ((M_PI / 2 - $lat) / M_PI) * $eqH;
            if ($u < 0) { $u += $eqW; } elseif ($u >= $eqW) { $u -= $eqW; }
            $u = max(0, min($eqW - 1, $u));
            $v = max(0, min($eqH - 1, $v));
            $u0 = (int) floor($u); $v0 = (int) floor($v);
            $u1 = min($u0 + 1, $eqW - 1); $v1 = min($v0 + 1, $eqH - 1);
            $fu = $u - $u0; $fv = $v - $v0;

            $c00 = imagecolorat($src, $u0, $v0);
            $c10 = imagecolorat($src, $u1, $v0);
            $c01 = imagecolorat($src, $u0, $v1);
            $c11 = imagecolorat($src, $u1, $v1);
            $r = $byte((1 - $fu) * ((1 - $fv) * (($c00 >> 16) & 0xFF) + $fv * (($c01 >> 16) & 0xFF)) + $fu * ((1 - $fv) * (($c10 >> 16) & 0xFF) + $fv * (($c11 >> 16) & 0xFF)));
            $g = $byte((1 - $fu) * ((1 - $fv) * (($c00 >> 8) & 0xFF) + $fv * (($c01 >> 8) & 0xFF)) + $fu * ((1 - $fv) * (($c10 >> 8) & 0xFF) + $fv * (($c11 >> 8) & 0xFF)));
            $b = $byte((1 - $fu) * ((1 - $fv) * ($c00 & 0xFF) + $fv * ($c01 & 0xFF)) + $fu * ((1 - $fv) * ($c10 & 0xFF) + $fv * ($c11 & 0xFF)));
            imagesetpixel($out, $px, $py, ($r << 16) | ($g << 8) | $b);
        }
    }
    $ok = imagejpeg($out, $absOut, 88);
    imagedestroy($out);
    imagedestroy($src);
    if (!$ok) { @unlink($absOut); return null; }
    @chmod($absOut, 0666);
    return $relOut;
}

/** Capture a chosen view of the 360 scene (defaults to the scene's initial look) and save it as the target's AR image. */
function ar_take_from_360(int $iid, int $targetId, ?float $yaw = null, ?float $pitch = null): array
{
    $t = crud()->raw(
        "SELECT * FROM ar_targets WHERE id = :id AND institution_id = :iid AND is_active = 1",
        ['id' => $targetId, 'iid' => $iid]
    )->fetch();
    if (!$t) { return ['error' => 'Target not found.']; }
    if ($t['ar_type'] !== '360_ar_hotspot') { return ['error' => 'Take from 360 works on 360 AR Hotspots only.']; }

    $scene = null;
    if (!empty($t['target_scene_id'])) {
        $scene = crud()->raw(
            "SELECT * FROM tour_scenes WHERE id = :id AND deleted_at IS NULL",
            ['id' => (int) $t['target_scene_id']]
        )->fetch();
    }
    if (!$scene) { return ['error' => 'The linked 360 scene is missing.']; }

    $path = ar_render_center_look($iid, $scene, (int) $t['floor_plan_id'], $yaw, $pitch);
    if ($path === null) { return ['error' => 'Could not render from the equirect panorama.']; }

    crud()->update('ar_targets', [
        'auto_image_path' => $path,
        'image_path' => $path,
        'image_source' => 'auto',
    ], ['id' => $targetId, 'institution_id' => $iid]);

    return ['path' => $path];
}

/** Save an uploaded .mind file; returns project-relative path or null. */
function ar_save_mind(?array $file, string $dir, string $prefix): ?string
{
    if (!is_array($file) || empty($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'mind') { throw new RuntimeException('Only .mind files are accepted.'); }
    if ((int) $file['size'] > 20 * 1024 * 1024) { throw new RuntimeException('.mind file is too large (20 MB max).'); }
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = $prefix . '_' . random_token(8) . '.mind';
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the .mind file.');
    }
    @chmod($dir . '/' . $name, 0666);
    return 'assets/ar_targets/minds/' . $name;
}

/** Delete an old .mind file when it lives inside the org's ar_targets/minds folder. */
function ar_remove_mind(string $rel, string $orgAbs): void
{
    $abs = $orgAbs . '/' . ltrim($rel, '/');
    if (str_starts_with($abs, $orgAbs . '/assets/ar_targets/minds/') && is_file($abs)) {
        @unlink($abs);
    }
}

function ar_fp_clear_form(int $fpId): void
{
    echo '<input type="hidden" name="ar_action" value="fp_mind_clear">',
         '<input type="hidden" name="fp_id" value="', $fpId, '">',
         '<button class="btn btn-sm btn-outline-ia text-danger" type="submit">', ia_icon('trash', 12), ' Clear</button>';
}

function ar_target_mind_clear_form(int $tid): void
{
    echo '<input type="hidden" name="ar_action" value="target_mind_clear">',
         '<input type="hidden" name="target_id" value="', $tid, '">',
         '<button class="btn btn-link btn-sm p-0 text-danger" type="submit">', ia_icon('x', 12), ' remove</button>';
}

/** Room (360 scene) .mind path: the scene's own, falling back to a legacy per-target .mind still in use. */
function ar_room_mind_for(int $sceneId): ?string
{
    if ($sceneId <= 0) { return null; }
    $r = crud()->raw("SELECT ar_mind_path FROM tour_scenes WHERE id = :id AND deleted_at IS NULL", ['id' => $sceneId])->fetch();
    if ($r && !empty($r['ar_mind_path'])) { return $r['ar_mind_path']; }
    return crud()->raw(
        "SELECT MAX(target_mind_path) FROM ar_targets WHERE target_scene_id = :id AND target_mind_path IS NOT NULL",
        ['id' => $sceneId]
    )->fetchColumn() ?: null;
}

function ar_scene_mind_clear_form(int $sceneId): void
{
    echo '<input type="hidden" name="ar_action" value="scene_mind_clear">',
         '<input type="hidden" name="scene_id" value="', (int) $sceneId, '">',
         '<button class="btn btn-sm btn-outline-ia text-danger" type="submit" title="Remove this room .mind">', ia_icon('trash', 12), ' Clear</button>';
}

function ar_reset_auto_form(int $tid): void
{
    echo '<input type="hidden" name="ar_action" value="reset_auto_image">',
         '<input type="hidden" name="target_id" value="', $tid, '">',
         '<button class="btn btn-sm btn-outline-ia" type="submit" title="Reset to auto-generated image">', ia_icon('refresh', 12), ' Auto</button>';
}

function ar_delete_form(int $tid): void
{
    echo '<input type="hidden" name="ar_action" value="delete_target">',
         '<input type="hidden" name="target_id" value="', $tid, '">',
         '<button class="btn btn-sm btn-outline-ia text-danger" type="submit" title="Delete AR target">', ia_icon('trash', 12), '</button>';
}

/**
 * One row of the AR compiler training guide, mirroring the exact order + numbering
 * of the target-image ZIP (see ar-download-targets.php): ORDER BY name, id → %03d_.
 * The MindAR compiler assigns target INDEX in drop order, so this is the same index
 * the public app maps back to the AR database id.
 */
function ar_compiler_row(array $t, int $order): ?array
{
    if (empty($t['image_path'])) {
        return null;
    }
    $ext = strtolower(pathinfo((string) $t['image_path'], PATHINFO_EXTENSION));
    $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim((string) $t['name']));
    $safe = trim((string) preg_replace('/_+/', '_', (string) $safe), '_');
    if ($safe === '') {
        $safe = 'target';
    }
    return [
        'order' => $order,
        'file'  => sprintf('%03d_%s.%s', $order, $safe, $ext),
        'id'    => (int) $t['id'],
        'name'  => (string) $t['name'],
    ];
}