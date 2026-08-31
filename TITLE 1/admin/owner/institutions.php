<?php
require_once __DIR__ . '/../../includes/auth.php';
require_system_staff();
require_page('owner.institutions', 'system.institutions');
/**
 * Innovatech PH — owner/system: institutions (create generates project folder).
 */

$fmErr = null;
$fmMsg = null;

$isOwner = current_role() === 'owner';
$canManage = in_array(current_role(), ['owner', 'system_admin'], true);

// ------------------------------- actions -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['inst_action'] ?? '';

    if ($action === 'create') {
        if (!$canManage) { flash('error', 'System staff can only view institutions.'); redirect('admin/owner/institutions'); }
        $name = trim($_POST['name'] ?? '');
        $short = trim($_POST['short_name'] ?? '');
        $type = (string) ($_POST['institution_type'] ?? 'college');
        if ($type === 'other') {
            $custom = trim($_POST['institution_type_other'] ?? '');
            if ($custom !== '') {
                $type = $custom;
            }
        }
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $country = trim($_POST['country'] ?? 'Philippines') ?: 'Philippines';
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $mode = '360_rotation'; // landing mode is configured later by the org admin (settings → Landing)
        $assignAdminId = (int) ($_POST['assign_admin_id'] ?? 0);

        if ($name === '') {
            $fmErr = 'Institution name is required.';
        } elseif (strlen($name) < 3) {
            $fmErr = 'Institution name is too short.';
        } else {
            // validate the selected admin account is a real, active org admin
            $adminUser = null;
            if ($assignAdminId > 0) {
                $adminUser = crud()->raw(
                    "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                     WHERE u.id=:id AND u.deleted_at IS NULL AND u.is_active=1 AND r.slug IN ('admin','staff')",
                    ['id' => $assignAdminId]
                )->fetch();
                if (!$adminUser) {
                    $fmErr = 'The selected admin account is not valid.';
                }
            }
            if (!$fmErr) {
                $folder = null;
                try {
                    db_transaction(function ($tx) use (&$adminUserId, &$folder, $name, $short, $type, $address, $city, $province, $country, $latitude, $longitude, $contactEmail, $contactPhone, $mode, $assignAdminId) {
                        // 1. unique slug
                        $baseSlug = slugify($name);
                        $slug = $baseSlug;
                        $n = 2;
                        while (crud()->raw("SELECT 1 FROM institutions WHERE slug=:s", ['s' => $slug])->fetch()) {
                            $slug = $baseSlug . '-' . $n++;
                        }

                        // 2. unique folder
                        $folder = unique_folder($slug, ORG_ROOT);

                        // 3. copy template pack → organizations/{folder}
                        if (!rcopy(TEMPLATE_PACK, ORG_ROOT . '/' . $folder)) {
                            throw new RuntimeException('Failed to generate project folder.');
                        }

                        // brand the brief into index.html + config.json
                        $indexHtml = ORG_ROOT . '/' . $folder . '/index.html';
                        $configJson = ORG_ROOT . '/' . $folder . '/config.json';
                        if (is_file($indexHtml)) {
                            $html = file_get_contents($indexHtml);
                            $html = str_replace('{{NAME}}', $name, $html);
                            $html = str_replace('{{SHORT}}', $short !== '' ? $short : strtoupper(substr($slug, 0, 5)), $html);
                            $html = str_replace('__EQUIRECT__', 'assets/panos/example.jpg', $html);
                            $html = str_replace('__YAW__', '100', $html);
                            $html = str_replace('__PITCH__', '10', $html);
                            $html = str_replace('__FLOORPLAN__', 'assets/floorplans/example.jpg', $html);
                            file_put_contents($indexHtml, $html);
                        }
                        if (is_file($configJson)) {
                            $cfg = json_decode((string) file_get_contents($configJson), true);
                            if (is_array($cfg)) {
                                $cfg['institution_id'] = null;
                                $cfg['name'] = $name;
                                $cfg['short_name'] = $short;
                                $cfg['landing_mode'] = $mode;
                                file_put_contents($configJson, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            }
                        }

                        // 4. insert institution
                        $institutionId = crud()->insert('institutions', [
                            'slug' => $slug,
                            'name' => $name,
                            'short_name' => $short ?: null,
                            'institution_type' => $type,
                            'address' => $address ?: null,
                            'city' => $city ?: null,
                            'province' => $province ?: null,
                            'country' => $country,
                            'latitude' => $latitude !== '' ? $latitude : null,
                            'longitude' => $longitude !== '' ? $longitude : null,
                            'contact_email' => $contactEmail ?: null,
                            'contact_phone' => $contactPhone ?: null,
                            'folder_path' => 'organizations/' . $folder,
                            'landing_mode' => $mode,
                            'is_active' => 1,
                            'created_by' => (int) current_user()['id'],
                        ]);

                        // 5. assign an existing org account to the institution (no role change)
                        if ($assignAdminId > 0) {
                            crud()->update('users', ['institution_id' => $institutionId], ['id' => $assignAdminId]);
                            $adminUserId = $assignAdminId;
                        }

                        // 6. audit
                        crud()->insert('audit_logs', [
                            'actor_user_id' => (int) current_user()['id'],
                            'institution_id' => $institutionId,
                            'action' => 'institution.create',
                            'module' => 'institutions',
                            'entity_type' => 'institution',
                            'entity_id' => $institutionId,
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        ]);
                    });

                    flash('success', "Institution created. Project folder generated at organizations/{$folder}.");
                } catch (Throwable $e) {
                    // rollback folder if DB failed partway
                    if (isset($folder) && is_dir(ORG_ROOT . '/' . $folder)) {
                        rrmdir(ORG_ROOT . '/' . $folder);
                    }
                    $fmErr = 'Create failed: ' . $e->getMessage();
                }
            }
        }
        if ($fmErr) flash('error', $fmErr);
        redirect('admin/owner/institutions');
    }

    if ($action === 'toggle') {
        if (!$canManage) { flash('error', 'System staff can only view institutions.'); redirect('admin/owner/institutions'); }
        $id = (int) ($_POST['id'] ?? 0);
        $field = $_POST['field'] === 'publish' ? 'is_published' : 'is_active';
        crud()->raw("UPDATE institutions SET {$field} = 1 - {$field} WHERE id=:id", ['id' => $id]);
        if ($field === 'is_published') {
            sync_institution_config($id);
            // refresh the active institution session snapshot if this is the managed org
            if (($id === (int) ($_SESSION['user']['institution']['id'] ?? 0))) {
                $_SESSION['user']['institution']['is_published'] = 1 - (int) ($_SESSION['user']['institution']['is_published'] ?? 0);
            }
        }
        flash('success', 'Updated.');
        redirect('admin/owner/institutions');
    }

    if ($action === 'edit') {
        if (!$canManage) { flash('error', 'System staff can only view institutions.'); redirect('admin/owner/institutions'); }
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $short = trim($_POST['short_name'] ?? '');
        $type = (string) ($_POST['institution_type'] ?? 'college');
        if ($type === 'other') {
            $custom = trim($_POST['institution_type_other'] ?? '');
            if ($custom !== '') {
                $type = $custom;
            }
        }
        $assignAdminId = (int) ($_POST['assign_admin_id'] ?? 0);
        
        crud()->update('institutions', [
            'name' => $name,
            'short_name' => $short ?: null,
            'institution_type' => $type
        ], ['id' => $id]);

        if ($assignAdminId > 0) {
            // Assign the new admin to this institution
            crud()->update('users', ['institution_id' => $id], ['id' => $assignAdminId]);
        } elseif (isset($_POST['unassign_admin_id']) && (int)$_POST['unassign_admin_id'] > 0) {
            // Unassign specific admin if requested
            crud()->update('users', ['institution_id' => null], ['id' => (int)$_POST['unassign_admin_id']]);
        }
        
        flash('success', 'Institution updated.');
        redirect('admin/owner/institutions');
    }

    if ($action === 'delete') {
        if (!$canManage) { flash('error', 'System staff can only view institutions.'); redirect('admin/owner/institutions'); }
        $id = (int) ($_POST['id'] ?? 0);
        $row = crud()->get('institutions', $id);
        $deleteFolder = isset($_POST['delete_folder']) ? 1 : 0;
        if ($row) {
            crud()->update('institutions', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
            if ($deleteFolder && !empty($row['folder_path'])) {
                $abs = ROOT_PATH . '/' . ltrim($row['folder_path'], '/');
                if (str_starts_with(realpath($abs) ?: $abs, ORG_ROOT) && is_dir($abs)) {
                    rrmdir($abs);
                }
            }
            flash('success', 'Institution archived.');
        }
        redirect('admin/owner/institutions');
    }
}

$pageTitle = 'Institutions';
$pageSub = 'Create clients — each institution auto-generates its own project folder';
$active = 'Institutions';
$bodyClass = 'page-institutions';

require_once __DIR__ . '/../layout/header.php';

$institutions = crud()->raw(
    "SELECT i.*,
            (SELECT COUNT(*) FROM users u WHERE u.institution_id=i.id AND u.deleted_at IS NULL) AS user_count,
            (SELECT COUNT(*) FROM tour_scenes s WHERE s.institution_id=i.id AND s.deleted_at IS NULL) AS scene_count
     FROM institutions i WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC"
)->fetchAll();

// assignable org admin accounts (owner-created under Accounts), searchable in the modal
$assignable = crud()->raw(
    "SELECT u.id, u.email, u.first_name, u.last_name, u.institution_id,
            i.name AS inst_name
     FROM users u JOIN roles r ON r.id = u.role_id
     LEFT JOIN institutions i ON i.id = u.institution_id
     WHERE u.deleted_at IS NULL AND u.is_active = 1
       AND r.slug IN ('admin', 'staff')
     ORDER BY r.id ASC, u.first_name ASC"
)->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
  </div>
  <?php if ($canManage): ?>
  <button class="btn btn-grad px-4" data-bs-toggle="modal" data-bs-target="#inst-create"><?= ia_icon('school', 16) ?> New institution</button>
  <?php endif; ?>
</div>

<div class="ia-card">
  <div class="table-responsive">
    <table class="table table-ia">
      <thead><tr><th>Institution</th><th>Folder</th><th>Landing</th><th>Scenes</th><th>Admins</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($institutions as $inst): ?>
          <tr>
            <td>
              <div class="fw-bold"><?= h($inst['name']) ?></div>
              <div class="fs-125 text-ia-muted"><?= h(ucfirst($inst['institution_type'])) ?> · <?= h($inst['city'] ?: '—') ?></div>
            </td>
            <td>
              <div class="fs-13"><?= h(str_replace('organizations/', '', $inst['folder_path'])) ?></div>
              <div class="fs-12 text-ia-muted"><?= is_dir(ROOT_PATH . '/' . ltrim($inst['folder_path'], '/')) ? 'on disk' : '<span class="text-danger">missing</span>' ?></div>
            </td>
            <td><span class="badge badge-surface"><?= $inst['landing_mode'] === 'floor_plan' ? 'Floor plan' : '360 rotation' ?></span></td>
            <td class="text-ia-muted"><?= (int) $inst['scene_count'] ?></td>
            <td class="text-ia-muted"><?= (int) $inst['user_count'] ?></td>
            <td>
              <span class="badge <?= ((int) $inst['is_published'] === 1 && (int) $inst['is_active'] === 1) ? 'badge-live' : ((int) $inst['is_active'] === 1 ? 'badge-draft' : 'badge-off') ?>">
                <?= (int) $inst['is_published'] === 1 ? 'published' : 'draft' ?>
              </span>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                <a class="btn btn-sm btn-outline-ia" href="<?= h(org_url($inst['slug'])) ?>" target="_blank" title="Open landing"><?= ia_icon('globe', 13) ?></a>
                <button class="btn btn-sm btn-outline-ia" data-bs-toggle="modal" data-bs-target="#inst-edit-<?= (int) $inst['id'] ?>" title="Edit Info & Admin"><?= ia_icon('settings', 13) ?></button>
                <?php if ($canManage): ?>
                <form method="post" class="d-inline"><input type="hidden" name="inst_action" value="toggle"><input type="hidden" name="id" value="<?= (int) $inst['id'] ?>"><input type="hidden" name="field" value="publish">
                  <button class="btn btn-sm btn-outline-ia" title="<?= (int) $inst['is_published'] ? 'Unpublish' : 'Publish' ?>">
                    <?= (int) $inst['is_published'] ? ia_icon('shield', 13) . ' unpublish' : ia_icon('rocket', 13) . ' publish' ?>
                  </button>
                </form>
                <?php endif; ?>
                <?php if ($canManage): ?>
                <form method="post" class="d-inline" data-delete-form data-confirm="Archive '<?= h($inst['name']) ?>'?">
                  <input type="hidden" name="inst_action" value="delete"><input type="hidden" name="id" value="<?= (int) $inst['id'] ?>">
                  <button class="btn btn-sm btn-outline-ia text-danger" title="Archive"><?= ia_icon('x', 13) ?></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$institutions): ?><tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><?= ia_icon('school', 26) ?></div><h4>No institutions yet</h4><p>Click “New institution” to create your first client.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach ($institutions as $inst): ?>
          <div class="modal fade" id="inst-edit-<?= (int) $inst['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Edit Institution — <?= h($inst['name']) ?></h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <form method="post">
                <input type="hidden" name="inst_action" value="edit">
                <input type="hidden" name="id" value="<?= (int) $inst['id'] ?>">
                <div class="modal-body">
                  <div class="row g-3 mb-3">
                    <div class="col-md-8"><label class="form-label">Institution name</label><input class="form-control" name="name" value="<?= h($inst['name']) ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Short name</label><input class="form-control" name="short_name" value="<?= h($inst['short_name']) ?>"></div>
                  </div>
                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <label class="form-label">Type</label>
                      <?php
                        $typeOpts = ['school' => 'School', 'college' => 'College', 'university' => 'University'];
                        $curType = (string) ($inst['institution_type'] ?? 'college');
                        $curKey = strtolower($curType);
                        $isKnown = array_key_exists($curKey, $typeOpts);
                      ?>
                      <select class="form-select" name="institution_type" id="inst-type-edit-<?= (int) $inst['id'] ?>">
                        <?php foreach ($typeOpts as $val => $lab): ?>
                          <option value="<?= h($val) ?>" <?= $isKnown && $curKey === $val ? 'selected' : '' ?>><?= h($lab) ?></option>
                        <?php endforeach; ?>
                        <option value="other" <?= !$isKnown ? 'selected' : '' ?>>Other…</option>
                      </select>
                    </div>
                    <div class="col-md-8 inst-type-edit-other <?= $isKnown ? 'd-none' : '' ?>" id="inst-type-edit-other-<?= (int) $inst['id'] ?>">
                      <label class="form-label">Specify type</label>
                      <input class="form-control" name="institution_type_other" value="<?= $isKnown ? '' : h($curType) ?>" placeholder="e.g. Technical-Vocational Institute">
                    </div>
                  </div>

                  <div class="mt-4 mb-2 section-caption">Assigned admins</div>
                  <?php
                  $currAdmins = array_filter($assignable, fn($a) => $a['institution_id'] == $inst['id']);
                  if ($currAdmins): ?>
                    <ul class="list-group mb-3">
                    <?php foreach ($currAdmins as $ca): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center fs-13">
                        <?= h($ca['first_name'] . ' ' . $ca['last_name']) ?> (<?= h($ca['email']) ?>)
                        <label class="d-flex align-items-center gap-2 m-0 fs-12 cursor-pointer">
                           <input type="checkbox" name="unassign_admin_id" value="<?= (int) $ca['id'] ?>"> Unassign
                        </label>
                      </li>
                    <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <p class="text-muted fs-13">No admins assigned yet.</p>
                  <?php endif; ?>

                  <label class="form-label">Assign new admin</label>
                  <input type="text" class="form-control mb-2" id="assign-admin-search-edit-<?= (int) $inst['id'] ?>" placeholder="Search typed name or email…">
                  <select class="form-select" name="assign_admin_id" id="assign-admin-select-edit-<?= (int) $inst['id'] ?>">
                    <option value="">— assign an admin —</option>
                    <?php foreach ($assignable as $aa): ?>
                      <?php if(empty($aa['institution_id'])): ?>
                      <option value="<?= (int) $aa['id'] ?>" data-search="<?= h(strtolower($aa['first_name'] . ' ' . $aa['last_name'] . ' ' . $aa['email'])) ?>">
                        <?= h($aa['first_name'] . ' ' . $aa['last_name']) ?> — <?= h($aa['email']) ?>
                      </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-ia" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-grad px-4" type="submit">Save changes</button>
                </div>
              </form>
            </div></div>
          </div>
<?php endforeach; ?>

<!-- create modal -->
<div class="modal fade" id="inst-create" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">New institution</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="post" id="inst-create-form">
      <input type="hidden" name="inst_action" value="create">
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-8"><label class="form-label">Institution name</label><input class="form-control" name="name" required placeholder="e.g. University / College name"></div>
          <div class="col-md-4"><label class="form-label">Short name</label><input class="form-control" name="short_name" placeholder="e.g. EXU"></div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select" name="institution_type" id="inst-type">
              <option value="school">School</option>
              <option value="college">College</option>
              <option value="university">University</option>
              <option value="other">Other…</option>
            </select>
          </div>
          <div class="col-md-8 d-none" id="inst-type-other-wrap">
            <label class="form-label">Specify type</label>
            <input class="form-control" name="institution_type_other" placeholder="e.g. Technical-Vocational Institute, Seminary…">
          </div>
        </div>

        <div class="mt-4 mb-2 section-caption">Location</div>
        <p class="text-muted fs-125 mt-n6">Pick the campus on the map or search an address — the full address, city, province and coordinates are filled automatically.</p>
        <div class="row g-3">
          <div class="col-12"><label class="form-label">Address</label>
            <div class="pw-group">
              <input class="form-control" name="address" id="inst-addr" placeholder="e.g. 2324 Taft Ave, Malate" readonly onclick="IAAddr.open()" title="Click to search or pin on the map">
              <button type="button" class="btn btn-outline-ia btn-shrink" onclick="IAAddr.open()"><?= ia_icon('map', 14) ?> Pick on map</button>
            </div>
          </div>
          <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" id="inst-city"></div>
          <div class="col-md-4"><label class="form-label">Province</label><input class="form-control" name="province" id="inst-province"></div>
          <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" name="country" id="inst-country" value="Philippines"></div>
          <input type="hidden" name="latitude" id="inst-lat">
          <input type="hidden" name="longitude" id="inst-lng">
        </div>

        <div class="mt-4 mb-2 section-caption">Assigned admin</div>
        <p class="text-muted fs-125 mt-n6">Pick an existing account from the list; it gets attached to this institution.</p>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Admin account</label>
            <input type="text" class="form-control mb-2" id="assign-admin-search" placeholder="Search typed name or email…">
            <select class="form-select" name="assign_admin_id" id="assign-admin-select">
              <option value="">— assign later —</option>
              <?php foreach ($assignable as $aa): ?>
                <option value="<?= (int) $aa['id'] ?>"
                  data-search="<?= h(strtolower($aa['first_name'] . ' ' . $aa['last_name'] . ' ' . $aa['email'])) ?>"
                  <?= !empty($aa['inst_name']) ? 'disabled title="Already assigned to ' . h($aa['inst_name']) . '"' : '' ?>>
                  <?= h($aa['first_name'] . ' ' . $aa['last_name']) ?> — <?= h($aa['email']) ?><?= !empty($aa['inst_name']) ? ' (assigned: ' . h($aa['inst_name']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Only accounts without an institution yet are selectable. Create more under <a href="<?= url('admin/owner/accounts') ?>">Accounts</a>.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-ia" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-grad px-4" type="submit">Create & generate folder</button>
      </div>
    </form>
  </div></div>
</div>

<!-- address picker modal -->
<div class="modal fade" id="ia-addr-modal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Find the institution address</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
      <div class="p-3 divider-bottom">
        <div class="input-icon">
          <span class="icon"><?= ia_icon('search', 16) ?></span>
          <input type="text" class="form-control" id="ia-addr-search" placeholder="Search a specific address, street, campus…">
        </div>
        <div id="ia-addr-results" class="d-none mt-2"></div>
      </div>
      <div id="ia-addr-map"></div>
    </div>
    <div class="modal-footer">
      <span class="text-muted me-auto fs-125" id="ia-addr-status">Search or click the map to place a pin, then apply.</span>
      <button class="btn btn-outline-ia" type="button" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-grad px-4" type="button" id="ia-addr-apply">Apply address</button>
    </div>
  </div></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  /* ------------------------- institution type "Other…" ---------------------- */
  (function () {
    var typeSel = document.getElementById('inst-type');
    var otherWrap = document.getElementById('inst-type-other-wrap');
    var otherInput = otherWrap ? otherWrap.querySelector('input[name="institution_type_other"]') : null;
    function syncType() {
      var show = typeSel && typeSel.value === 'other';
      if (otherWrap) otherWrap.style.display = show ? '' : 'none';
      if (show && otherInput) otherInput.focus();
    }
    if (typeSel) typeSel.addEventListener('change', syncType);

    document.querySelectorAll('select[id^="inst-type-edit-"]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var wrap = document.getElementById(this.id.replace('inst-type-edit-', 'inst-type-edit-other-'));
        if (wrap) wrap.style.display = this.value === 'other' ? '' : 'none';
      });
    });

    document.querySelectorAll('input[id^="assign-admin-search"]').forEach(function(search) {
      var sel = document.getElementById(search.id.replace('search', 'select'));
      if (!sel) return;
      search.addEventListener('input', function() {
        var q = search.value.trim().toLowerCase();
        Array.prototype.forEach.call(sel.options, function (o) {
          if (o.value === '') { o.hidden = false; return; }
          o.hidden = o.getAttribute('data-search') ? o.getAttribute('data-search').indexOf(q) === -1 : q.length > 0;
        });
      });
    });
  })();

  /* -------------------- Leaflet + Nominatim address picker ------------------- */
  (function () {
    var MAP_MODAL = document.getElementById('ia-addr-modal');
    var mapEl = document.getElementById('ia-addr-map');
    var searchEl = document.getElementById('ia-addr-search');
    var resultsEl = document.getElementById('ia-addr-results');
    var statusEl = document.getElementById('ia-addr-status');
    var applyBtn = document.getElementById('ia-addr-apply');
    if (!MAP_MODAL || !mapEl) return;

    var map = null, marker = null, picked = null, debounce = null;

    function latLng() {
      return picked ? L.latLng(picked.lat, picked.lon) : L.latLng(13.6, 121.0);
    }

    function ensureMap() {
      if (map) return;
      map = L.map(mapEl).setView(latLng(), 6);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(map);
      marker = L.marker(latLng(), { draggable: true }).addTo(map);
      marker.on('dragend', function () {
        var p = marker.getLatLng();
        reverse(p.lat, p.lng);
      });
      map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        reverse(e.latlng.lat, e.latlng.lng);
      });
      setTimeout(function () { map.invalidateSize(); }, 250);
    }

    function reverse(lat, lon) {
      statusEl.textContent = 'Looking up that point…';
      fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lon + '&zoom=18&addressdetails=1')
        .then(function (r) { return r.json(); })
        .then(function (d) {
          picked = { lat: lat, lon: lon, display_name: d.display_name || '', address: d.address || {} };
          fillStatus();
        })
        .catch(function () { statusEl.textContent = 'Reverse lookup failed — try typing the address instead.'; });
    }

    function fillStatus() {
      if (!picked) return;
      statusEl.textContent = picked.display_name ? picked.display_name : (picked.lat.toFixed(6) + ', ' + picked.lon.toFixed(6));
    }

    searchEl.addEventListener('input', function () {
      clearTimeout(debounce);
      var q = searchEl.value.trim();
      if (q.length < 4) { resultsEl.style.display = 'none'; resultsEl.innerHTML = ''; return; }
      debounce = setTimeout(function () {
        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&q=' + encodeURIComponent(q) + '&limit=6&addressdetails=1&countrycodes=ph')
          .then(function (r) { return r.json(); })
          .catch(function () { return []; })
          .then(function (items) { renderResults(items); });
      }, 350);
    });

    function renderResults(items) {
      resultsEl.innerHTML = '';
      if (!items || !items.length) {
resultsEl.classList.remove('d-none');
      resultsEl.innerHTML = '<div class="text-muted fs-13 ia-addr-nomatch">No exact match — try a different keyword, or pin the campus on the map below.</div>';
      return;
      }
      resultsEl.classList.remove('d-none');
      items.forEach(function (it) {
        var name = document.createElement('div');
        name.className = 'ia-addr-result';
        name.textContent = it.display_name;
        name.addEventListener('click', function () {
          selected(it);
          resultsEl.classList.add('d-none');
          resultsEl.innerHTML = '';
          searchEl.value = '';
        });
        resultsEl.appendChild(name);
      });
    }

    function selected(it) {
      picked = { lat: parseFloat(it.lat), lon: parseFloat(it.lon), display_name: it.display_name, address: it.address || {} };
      ensureMap();
      marker.setLatLng([picked.lat, picked.lon]);
      map.setView([picked.lat, picked.lon], 16);
      fillStatus();
    }

    window.IAAddr = {
      open: function () {
        ensureMap();
        if (picked) { marker.setLatLng([picked.lat, picked.lon]); map.setView([picked.lat, picked.lon], 16); }
        var bs = new bootstrap.Modal(MAP_MODAL);
        bs.show();
      }
    };

    applyBtn.addEventListener('click', function () {
      if (!picked) { statusEl.textContent = 'Please pick a location first.'; return; }
      var a = picked.address || {};
      var city = a.city || a.town || a.municipality || a.county || '';
      var prov = a.state || a.province || a.region || '';
      var addr = picked.display_name || '';
      document.getElementById('inst-addr').value = addr;
      document.getElementById('inst-city').value = city;
      document.getElementById('inst-province').value = prov;
      if (a.country) document.getElementById('inst-country').value = a.country;
      document.getElementById('inst-lat').value = picked.lat.toFixed(7);
      document.getElementById('inst-lng').value = picked.lon.toFixed(7);
      var bs = bootstrap.Modal.getInstance(MAP_MODAL);
      if (bs) bs.hide();
      document.getElementById('inst-addr').dispatchEvent(new Event('input', { bubbles: true }));
    });
  })();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>