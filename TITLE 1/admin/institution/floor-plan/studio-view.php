  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <a class="back-link" href="floor-plans">← All floor plans</a>
      <div class="d-flex align-items-center gap-2 mt-1">
        <h3 class="mb-0 fw-800"><?= h($studio['title']) ?> — interactive studio</h3>
        <?php if ($studio['is_campus_landing']): ?><span class="badge badge-live">landing</span><?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-ia btn-sm" data-bs-toggle="modal" data-bs-target="#fp-update-image-modal"><?= ia_icon('image', 14) ?> Change image</button>
      <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#marker-modal"><?= ia_icon('map', 16) ?> Add marker</button>
    </div>
  </div>

  <!-- Unified mode toolbar -->
  <div class="ia-card p-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <div class="btn-group flex-wrap" id="ar-mode-tabs" role="group">
        <button type="button" class="btn btn-sm btn-grad" data-ar-mode="move"><?= ia_icon('move3d', 13) ?> Move</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="face"><?= ia_icon('refresh', 13) ?> Face</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="route"><?= ia_icon('map', 13) ?> Route</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="compass"><?= ia_icon('compass', 13) ?> Compass</button>
        <button type="button" class="btn btn-sm btn-outline-ia" data-ar-mode="connections"><?= ia_icon('link', 13) ?> Exit Links</button>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="ia-meta-md" id="mode-hint">Drag any element to reposition it. Click to edit.</span>
        <button class="btn btn-grad btn-sm px-3" id="studio-save-btn" type="button">Save all</button>
      </div>
    </div>
  </div>

  <!-- update image modal -->
  <div class="modal fade" id="fp-update-image-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Change floor plan image</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="fp_action" value="update-image">
        <input type="hidden" name="id" value="<?= (int) $studio['id'] ?>">
        <div class="modal-body d-grid gap-3">
          <?php
          $pickerName  = 'image';
          $pickerValue = $studio['image_path'] ?? '';
          $pickerLabel = 'New Image (PNG/JPG)';
          $pickerHelp  = 'Aspect ratio and marker positions are preserved. Only the image file is replaced.';
          require __DIR__ . '/../layout/media-picker-sweetalert.php';
          ?>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit">Update image</button></div>
      </form>
    </div></div>
  </div>

  <!-- Map stage -->
  <div class="ia-card p-3">
    <div id="map-stage" class="map-stage" style="--fp-ar: <?= $studio['aspect_ratio'] ?>">
      <img src="<?= h(media_url($studio['image_path'])) ?>" alt="floor plan" class="map-stage-img">
      <div id="fp-path-layer"></div>

      <?php foreach ($markers as $mk): ?>
        <?php $mtype = in_array($mk['marker_type'] ?? '', ['scene', 'entrance', 'exit'], true) ? $mk['marker_type'] : 'scene'; ?>
        <div class="fp-marker-dot" data-id="<?= (int) $mk['id'] ?>"
             data-mktype="<?= $mtype ?>"
             data-name="<?= h($mk['label'], ENT_QUOTES) ?>"
             style="left:<?= (float) $mk['x_percent'] ?>%;top:<?= (float) $mk['y_percent'] ?>%">
          <span class="fp-facing-arrow" style="--facing: <?= (float) ($mk['facing_angle'] ?? 0) ?>deg"></span>
          <span class="fp-mktype-badge"><?= $mtype === 'entrance' ? 'IN' : ($mtype === 'exit' ? 'OUT' : 'SC') ?></span>
          <input type="hidden" class="mk-x" value="<?= (float) $mk['x_percent'] ?>">
          <input type="hidden" class="mk-y" value="<?= (float) $mk['y_percent'] ?>">
          <input type="hidden" class="mk-label" value="<?= h($mk['label']) ?>">
          <input type="hidden" class="mk-type" value="<?= $mtype ?>">
          <input type="hidden" class="mk-facing" value="<?= (float) ($mk['facing_angle'] ?? 0) ?>">
          <input type="hidden" class="mk-building" value="<?= h($mk['target_building_id']) ?>">
          <input type="hidden" class="mk-room" value="<?= h($mk['target_room_id']) ?>">
          <input type="hidden" class="mk-scene" value="<?= h($mk['target_scene_id']) ?>">
          <input type="hidden" class="mk-fp" value="<?= h($mk['target_floor_plan_id']) ?>">
          <input type="hidden" class="mk-popup-title" value="<?= h($mk['popup_title']) ?>">
          <input type="hidden" class="mk-popup-html" value="<?= h($mk['popup_html']) ?>">
        </div>
      <?php endforeach; ?>

      <?php foreach ($waypoints as $wp): ?>
        <div class="fp-wp-dot<?= $wp['type'] === 'corner' ? ' is-corner' : '' ?>"
             data-wp-id="<?= (int) $wp['id'] ?>"
             data-name="<?= h($wp['label'], ENT_QUOTES) ?>"
             style="left:<?= (float) $wp['x_percent'] ?>%;top:<?= (float) $wp['y_percent'] ?>%">
          <input type="hidden" class="wp-x" value="<?= (float) $wp['x_percent'] ?>">
          <input type="hidden" class="wp-y" value="<?= (float) $wp['y_percent'] ?>">
          <input type="hidden" class="wp-label" value="<?= h($wp['label']) ?>">
          <input type="hidden" class="wp-type" value="<?= $wp['type'] ?>">
        </div>
      <?php endforeach; ?>

      <div id="fp-compass" class="fp-compass-rose" style="--north: <?= (float) ($studio['north_angle'] ?? 0) ?>deg; left:85%; top:15%">
        <div class="compass-center" id="compass-center"></div>
        <div class="compass-dir" data-dir="N">N</div>
        <div class="compass-dir" data-dir="NE">NE</div>
        <div class="compass-dir" data-dir="E">E</div>
        <div class="compass-dir" data-dir="SE">SE</div>
        <div class="compass-dir" data-dir="S">S</div>
        <div class="compass-dir" data-dir="SW">SW</div>
        <div class="compass-dir" data-dir="W">W</div>
        <div class="compass-dir" data-dir="NW">NW</div>
        <svg class="compass-ring" viewBox="-60 -60 120 120"><circle cx="0" cy="0" r="56" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"/><circle cx="0" cy="0" r="30" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="1"/></svg>
      </div>

      <div class="fp-hint" id="fp-hint">Drag dots to reposition them. Click a marker to edit its properties.</div>
    </div>
  </div>

  <!-- Properties panel -->
  <div class="ia-card mt-3" id="props-panel" style="display:none">
    <div class="d-flex align-items-center justify-content-between">
      <h3 id="props-title" class="mb-0">Properties</h3>
      <button type="button" class="btn-close" id="props-close" aria-label="Close"></button>
    </div>
    <div class="mt-2" id="props-body"></div>
  </div>

  <!-- Hidden forms for server submission -->
  <form method="post" id="fp-markers-form" style="display:none">
    <input type="hidden" name="fp_action" value="markers-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
  </form>
  <form method="post" id="fp-routes-form" style="display:none">
    <input type="hidden" name="fp_action" value="routes-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
    <input type="hidden" name="routes" id="routes-json">
    <input type="hidden" name="routes_delete" id="routes-delete-ids" value="">
    <input type="hidden" name="waypoints_delete" id="waypoints-delete-ids" value="">
  </form>
  <form method="post" id="fp-compass-form" style="display:none">
    <input type="hidden" name="fp_action" value="compass-save">
    <input type="hidden" name="id" value="<?= (int) $studio['id'] ?>">
    <input type="hidden" name="north_angle" id="north-angle-input" value="<?= (float) ($studio['north_angle'] ?? 0) ?>">
  </form>
  <form method="post" id="fp-connections-form" style="display:none">
    <input type="hidden" name="fp_action" value="connection-save">
    <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
  </form>

  <!-- add marker modal -->
  <div class="modal fade" id="marker-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add marker</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post">
        <input type="hidden" name="fp_action" value="marker-add">
        <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
        <input type="hidden" name="x" value="50"><input type="hidden" name="y" value="50">
        <div class="modal-body d-grid gap-3">
          <div><label class="form-label">Label</label><input class="form-control" name="label" required placeholder="Library"></div>
          <div>
            <label class="form-label">AR Marker type</label>
            <select class="form-select" name="marker_type">
              <option value="scene">Scene (a 360 / AR point of interest)</option>
              <option value="entrance">Entrance (visitor enters here)</option>
              <option value="exit">Exit (visitor leaves / can link to another floor)</option>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Room</label>
              <select class="form-select" name="target_room_id"><option value="">— none —</option><?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Building</label>
              <select class="form-select" name="target_building_id"><option value="">— none —</option><?php foreach ($buildings as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Link to 360 Tour</label>
              <select class="form-select" name="target_scene_id"><option value="">— none —</option><?php foreach ($scenes as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Link to Sub-Floor Plan</label>
              <select class="form-select" name="target_floor_plan_id"><option value="">— none —</option><?php foreach ($floorPlansList as $fp): ?><option value="<?= (int) $fp['id'] ?>"><?= h($fp['name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="form-text mb-1">
            <strong>Click action priority:</strong> 360 Tour &gt; Sub-Floor Plan &gt; Popup. Set only one target for clean behavior.
          </div>
          <div><label class="form-label">Popup title</label><input class="form-control" name="popup_title" placeholder="Library hours &amp; info"></div>
          <div><label class="form-label">Popup content (HTML)</label><textarea class="form-control" name="popup_html" rows="3" placeholder="Open Mon–Fri 8am–6pm"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-grad px-4" type="submit" disabled id="marker-add-go">Add marker</button></div>
      </form>
    </div></div>
  </div>

  <script>
  window._fpScenes = <?= json_encode(array_map(function($s) { return ['id' => $s['id'], 'name' => $s['name']]; }, $scenes), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpPlans  = <?= json_encode(array_map(function($p) { return ['id' => $p['id'], 'name' => $p['name']]; }, $floorPlansList), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpBuildings = <?= json_encode(array_map(function($b) { return ['id' => $b['id'], 'name' => $b['name']]; }, $buildings), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpRooms     = <?= json_encode(array_map(function($r) { return ['id' => $r['id'], 'name' => $r['name']]; }, $rooms), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpConnections = <?= json_encode($connections, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  window._fpPaths = <?= json_encode(array_map(function($p) { return ['id' => $p['id'], 'name' => $p['name'], 'nodes' => json_decode($p['nodes_json'] ?? '[]', true) ?: []]; }, $paths), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  </script>
