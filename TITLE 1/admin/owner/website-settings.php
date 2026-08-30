<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
require_page('owner.website');
/**
 * Innovatech PH — owner: public website branding + full landing content editor.
 */

$pageTitle = 'Website Settings';
$pageSub = 'Branding, hero image, and all landing page content';
$active = 'Website Settings';
$bodyClass = 'page-website-settings';
$active = 'Website Settings';

$s = crud()->get('platform_settings', 1) ?? [];
$landingJson = json_decode($s['landing_html'] ?? 'null', true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['tab_action'] ?? 'all';
  $fields = ['updated_by' => (int) current_user()['id']];
  $lj = $landingJson;
  $imageWarnings = [];

  $pick = function (string $name, string $dir) use (&$imageWarnings): ?string {
    try {
      $path = handle_media_picker($name, $dir);
      if (!empty($GLOBALS['media_picker_warnings']) && is_array($GLOBALS['media_picker_warnings'])) {
        foreach ($GLOBALS['media_picker_warnings'] as $w) {
          $imageWarnings[] = $w;
        }
        $GLOBALS['media_picker_warnings'] = [];
      }
      return $path;
    } catch (Throwable $e) {
      $imageWarnings[] = "$name: " . $e->getMessage();
      return null;
    }
  };

  if ($action === 'brand' || $action === 'all') {
    $fields['product_name'] = trim($_POST['product_name'] ?? '');
    $fields['company_name'] = trim($_POST['company_name'] ?? '');
    $fields['contact_email'] = trim($_POST['contact_email'] ?? '');
    $hero = $pick('hero', 'public/uploads');
    if ($hero !== null && $hero !== '') {
      $fields['hero_image_path'] = $hero;
    }
    $logo = $pick('logo', 'public/uploads');
    if ($logo !== null && $logo !== '') {
      $fields['logo_path'] = $logo;
    }
  }

  if ($action === 'hero' || $action === 'all') {
    $lj['hero_headline'] = trim($_POST['hero_headline'] ?? '');
    $lj['hero_sub'] = trim($_POST['hero_sub'] ?? '');
  }

  if ($action === 'why' || $action === 'all') {
    $lj['why'] = [];
    for ($i = 0; $i < 4; $i++) {
      $lj['why'][] = [
        trim($_POST["why{$i}_title"] ?? ''),
        trim($_POST["why{$i}_text"] ?? '')
      ];
    }
  }

  if ($action === 'cta' || $action === 'all') {
    $lj['cta'] = [
      'headline' => trim($_POST['cta_headline'] ?? ''),
      'sub' => trim($_POST['cta_sub'] ?? ''),
      'btn_text' => trim($_POST['cta_btn_text'] ?? ''),
    ];
    $lj['stats'] = [
      [trim($_POST['stat1_val'] ?? ''), trim($_POST['stat1_label'] ?? '')],
      [trim($_POST['stat2_val'] ?? ''), trim($_POST['stat2_label'] ?? '')],
      [trim($_POST['stat3_val'] ?? ''), trim($_POST['stat3_label'] ?? '')],
      [trim($_POST['stat4_val'] ?? ''), trim($_POST['stat4_label'] ?? '')],
    ];
  }

  if ($action === 'quote' || $action === 'all') {
    $lj['quote'] = trim($_POST['quote_text'] ?? '');
    $lj['quote_name'] = trim($_POST['quote_name'] ?? '');
    $lj['quote_role'] = trim($_POST['quote_role'] ?? '');
  }

  if ($action === 'features' || $action === 'all') {
    $lj['features'] = [];
    for ($i = 0; $i < 6; $i++) {
      $featImg = $pick("feature{$i}_image", 'public/uploads');
      // Keep existing stored image if no new upload/selection made
      if ($featImg === null || $featImg === '') {
        $featImg = $landingJson['features'][$i][3] ?? '';
      }
      $lj['features'][] = [
        trim($_POST["feature{$i}_title"] ?? ''),
        trim($_POST["feature{$i}_text"] ?? ''),
        '', // icon slot (unused)
        $featImg, // image path
      ];
    }
  }

  $fields['landing_html'] = json_encode($lj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  crud()->update('platform_settings', $fields, ['id' => 1]);
  if ($imageWarnings) {
    flash('error', 'Settings saved, but an image did not update: ' . implode(' ', $imageWarnings));
  } else {
    flash('success', 'Website settings saved.');
  }
  redirect('admin/owner/website-settings');
}

require_once __DIR__ . '/../layout/header.php';

$hero = media_url($s['hero_image_path'] ?? '', 'public/campus-hero.png');
$heroBust = $hero . (str_contains($hero, '?') ? '&' : '?') . 'v=' . urlencode((string) ($s['updated_at'] ?? time()));
$pickerLibrary = 'platform';
?>
<div class="row g-4">
  <!-- LEFT: forms -->
  <div class="col-lg-7">

    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab"
          data-bs-target="#tab-brand">Brand</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hero">Hero</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-why">Why Us</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cta">CTA</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-quote">Quote</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab"
          data-bs-target="#tab-features">Features</button></li>
    </ul>

    <div class="tab-content">

      <!-- Branding -->
      <div class="tab-pane fade show active" id="tab-brand">
        <div class="ia-card mb-4">
          <div class="card-head">
            <h3>Brand & contact</h3>
          </div>
          <div class="card-body">
            <form method="post" class="d-grid gap-3" enctype="multipart/form-data"><input type="hidden"
                name="tab_action" value="brand">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Company name</label>
                  <input class="form-control" name="company_name" value="<?= h($s['company_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Product name</label>
                  <input class="form-control" name="product_name" value="<?= h($s['product_name'] ?? '') ?>">
                </div>
              </div>
              <div>
                <label class="form-label">Contact email</label>
                <input type="email" class="form-control" name="contact_email"
                  value="<?= h($s['contact_email'] ?? '') ?>">
              </div>

              <!-- Hero image upload -->
              <div>
                <?php
                $pickerName = 'hero';
                $pickerValue = $s['hero_image_path'] ?? '';
                $pickerLabel = 'Hero Image';
                $pickerHelp = 'Replaces public/campus-hero.*. Recommended 1400×900+.';
                require __DIR__ . '/../layout/media-picker.php';
                ?>
              </div>

              <!-- Logo upload -->
              <div>
                <?php
                $pickerName = 'logo';
                $pickerValue = $s['logo_path'] ?? '';
                $pickerLabel = 'Brand Logo';
                $pickerHelp = 'Replaces the default 3D box icon across the site headers and footers.';
                require __DIR__ . '/../layout/media-picker.php';
                ?>
              </div>

              <button type="submit" class="btn btn-grad w-100 mt-2">Save Brand Settings</button>
            </form>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-hero">
        <div class="ia-card mb-4">
          <div class="card-head">
            <h3>Hero Content</h3>
          </div>
          <div class="card-body">
            <form method="post" class="d-grid gap-3" enctype="multipart/form-data"><input type="hidden"
                name="tab_action" value="hero">
              <!-- Hero copy -->
              <h5 class="fw-bold">Hero section copy</h5>
              <div>
                <label class="form-label">Headline</label>
                <input class="form-control" name="hero_headline"
                  value="<?= h($landingJson['hero_headline'] ?? 'Explore. Experience. Innovate.') ?>">
              </div>
              <div>
                <label class="form-label">Sub-headline</label>
                <textarea class="form-control" name="hero_sub"
                  rows="2"><?= h($landingJson['hero_sub'] ?? 'Make a great first impression, at any scale. Give every prospective student a real sense of belonging before they even set foot on campus.') ?></textarea>
              </div>

              <button type="submit" class="btn btn-grad w-100 mt-2">Save Hero Settings</button>
            </form>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-why">
        <div class="ia-card mb-4">
          <div class="card-head">
            <h3>Why Choose Us</h3>
          </div>
          <div class="card-body">
            <form method="post" class="d-grid gap-3" enctype="multipart/form-data"><input type="hidden"
                name="tab_action" value="why">
              <!-- Why Section -->
              <h5 class="fw-bold">Why Choose Us Section</h5>
              <?php
              $whyDefaults = [
                ['Affordable', 'A smarter alternative to traditional 3D scanning services.'],
                ['Scalable', 'A reusable platform that grows with your campus.'],
                ['Fast', 'Go from first capture to live tour in days, not months.'],
                ['Yours', 'Your colors, your story, your branded experience.']
              ];
              $why = $landingJson['why'] ?? $whyDefaults;
              for ($i = 0; $i < 4; $i++):
                [$wt, $wd] = array_pad($why[$i] ?? [], 2, '');
                ?>
                <div class="row g-2">
                  <div class="col-md-4"><label class="form-label">Item <?= $i + 1 ?> Title</label><input
                      class="form-control" name="why<?= $i ?>_title" value="<?= h($wt) ?>"></div>
                  <div class="col-md-8"><label class="form-label">Item <?= $i + 1 ?> Description</label><input
                      class="form-control" name="why<?= $i ?>_text" value="<?= h($wd) ?>"></div>
                </div>
              <?php endfor ?>

              <button type="submit" class="btn btn-grad w-100 mt-2">Save Why Us</button>
            </form>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-cta">
        <div class="ia-card mb-4">
          <div class="card-head">
            <h3>Call to Action</h3>
          </div>
          <div class="card-body">
            <form method="post" class="d-grid gap-3" enctype="multipart/form-data"><input type="hidden"
                name="tab_action" value="cta">
              <!-- CTA Section -->
              <h5 class="fw-bold">Call to Action (CTA) Section</h5>
              <div><label class="form-label">Headline</label><input class="form-control" name="cta_headline"
                  value="<?= h($landingJson['cta']['headline'] ?? 'Your next chapter starts here') ?>"></div>
              <div><label class="form-label">Sub-headline</label><input class="form-control" name="cta_sub"
                  value="<?= h($landingJson['cta']['sub'] ?? 'Bring your campus online. Talk to our team about creating an experience your students will remember.') ?>">
              </div>
              <div><label class="form-label">Button Text</label><input class="form-control" name="cta_btn_text"
                  value="<?= h($landingJson['cta']['btn_text'] ?? 'Contact support') ?>"></div>

              <!-- Stats -->
              <h5 class="fw-bold">Stats bar</h5>
              <?php
              $statsDefaults = [['24+', 'Partner schools'], ['180+', 'Tours created'], ['4.8k', 'Places described by AI'], ['100%', 'AR-ready platform']];
              $stats = $landingJson['stats'] ?? $statsDefaults;
              for ($i = 0; $i < 4; $i++):
                $si = $i + 1;
                [$sv, $sl] = array_pad($stats[$i] ?? [], 2, '');
                ?>
                <div class="row g-2">
                  <div class="col-md-3"><label class="form-label">Stat <?= $si ?> value</label><input class="form-control"
                      name="stat<?= $si ?>_val" value="<?= h($sv) ?>"></div>
                  <div class="col-md-9"><label class="form-label">Stat <?= $si ?> label</label><input class="form-control"
                      name="stat<?= $si ?>_label" value="<?= h($sl) ?>"></div>
                </div>
              <?php endfor ?>

              <button type="submit" class="btn btn-grad w-100 mt-2">Save CTA & Stats</button>
            </form>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-quote">
        <div class="ia-card mb-4">
          <div class="card-head">
            <h3>Quote</h3>
          </div>
          <div class="card-body">
            <form method="post" class="d-grid gap-3" enctype="multipart/form-data"><input type="hidden"
                name="tab_action" value="quote">
              <!-- Quote -->
              <h5 class="fw-bold">Testimonial quote</h5>
              <div><label class="form-label">Quote text</label><textarea class="form-control" name="quote_text"
                  rows="3"><?= h($landingJson['quote'] ?? '"Innovatech helps us give prospective students a real sense of belonging before they even set foot on campus."') ?></textarea>
              </div>
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Person name</label><input class="form-control"
                    name="quote_name" value="<?= h($landingJson['quote_name'] ?? 'Maria Angela Reyes') ?>"></div>
                <div class="col-md-6"><label class="form-label">Role / institution</label><input class="form-control"
                    name="quote_role"
                    value="<?= h($landingJson['quote_role'] ?? 'Director of Admissions · Partner school') ?>"></div>
              </div>
              <button type="submit" class="btn btn-grad w-100 mt-2">Save Quote</button>
            </form>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-features">
        <div class="ia-card mb-4">
          <div class="card-head">
            <h3>Features</h3>
          </div>
          <div class="card-body">
            <form method="post" class="d-grid gap-3" enctype="multipart/form-data"><input type="hidden"
                name="tab_action" value="features">

              <!-- Features -->
              <h5 class="fw-bold">Features Marquee</h5>
              <p class="text-muted fs-13 mb-3">These 6 cards scroll horizontally. Ensure
                titles are short and descriptions are punchy. Images are sourced from the default <code>assets/</code>
                folder.</p>
              <?php
              $featuresDefault = [
                ['360° Virtual Tours', 'Give every visitor an immersive, browser-based view of your campus.', 'move3d'],
                ['AR Campus Overlays', 'Point visitors toward buildings, rooms, offices, and landmarks.', 'navigation'],
                ['AI-Assisted Descriptions', 'Turn a room, lab, or landmark into an engaging story in seconds.', 'sparkles'],
                ['Room-to-Room Navigation', 'Make getting around intuitive with connected, guided pathways.', 'compass'],
                ['Admin Dashboard', 'Manage locations, content, analytics, and updates from one place.', 'chart'],
                ['Multi-Tenant Ready', 'Launch a beautiful, fully branded experience for every school.', 'layers'],
              ];
              $features = $landingJson['features'] ?? $featuresDefault;
              for ($i = 0; $i < 6; $i++):
                $fSlot   = $features[$i] ?? [];
                $ft      = $fSlot[0] ?? '';
                $fd      = $fSlot[1] ?? '';
                $fImgVal = $fSlot[3] ?? '';
              ?>
                <div class="p-3 mb-2 surface-soft">
                  <div class="row g-2 mb-2">
                    <div class="col-12"><label class="form-label fs-12">Feature <?= $i + 1 ?> Title</label><input class="form-control form-control-sm" name="feature<?= $i ?>_title" value="<?= h($ft) ?>"></div>
                  </div>
                  <div class="mb-2">
                    <label class="form-label fs-12">Description</label>
                    <input class="form-control form-control-sm" name="feature<?= $i ?>_text" value="<?= h($fd) ?>">
                  </div>
                  <div>
                    <?php
                    $pickerName  = "feature{$i}_image";
                    $pickerValue = $fImgVal;
                    $pickerLabel = 'Card Image';
                    $pickerHelp  = '';
                    require __DIR__ . '/../layout/media-picker.php';
                    ?>
                  </div>
                </div>
              <?php endfor ?>

              <div class="text-end"><button class="btn btn-grad px-4" type="submit">Save all settings</button></div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: preview + info -->
  <div class="col-lg-5">
    <div class="ia-card mb-4">
      <div class="card-head">
        <h3>Preview</h3><a class="back-link" href="<?= url('/') ?>" target="_blank">Open site ↗</a>
      </div>
      <div class="card-body p-0 preview-body">
        <iframe id="site-preview-iframe"
          src="<?= url('/') ?>"
          class="site-preview-iframe"
          loading="lazy"
          title="Live site preview">
        </iframe>
      </div>
    </div>

    <div class="ia-card mb-4">
      <div class="card-head">
        <h3>Partner campuses</h3><a class="back-link" href="institutions">Manage</a>
      </div>
      <div class="card-body ia-meta-lg">
        <p class="mb-0">Published, active institutions automatically appear in the landing "Partner Campuses" section.
          Org admins upload their own cover image in <em>Settings → Profile → Cover image</em>.</p>
      </div>
    </div>


  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>