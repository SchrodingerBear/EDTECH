<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
require_page('owner.website');
/**
 * Innovatech PH — owner: public website branding + full landing content editor.
 */
require_once __DIR__ . '/../layout/header.php';

$pageTitle = 'Website Settings';
$pageSub   = 'Branding, hero image, and all landing page content';
$active    = 'Website Settings';

$s = crud()->get('platform_settings', 1) ?? [];
$landingJson = json_decode($s['landing_html'] ?? 'null', true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'product_name'  => trim($_POST['product_name'] ?? ''),
        'company_name'  => trim($_POST['company_name'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'updated_by'    => (int) current_user()['id'],
    ];

    // Handle hero upload via media picker
    $hero = handle_media_picker('hero', 'public');
    if ($hero) $fields['hero_image_path'] = $hero;

    $why_bg = handle_media_picker('why_bg', 'public');
    $cta_bg = handle_media_picker('cta_bg', 'public');
    $quote_bg = handle_media_picker('quote_bg', 'public');

    // Editable landing content stored as JSON in landing_html column
    $lj = [
        'hero_headline' => trim($_POST['hero_headline'] ?? ''),
        'hero_sub'      => trim($_POST['hero_sub'] ?? ''),
        'stats'         => [
            [trim($_POST['stat1_val'] ?? ''), trim($_POST['stat1_label'] ?? '')],
            [trim($_POST['stat2_val'] ?? ''), trim($_POST['stat2_label'] ?? '')],
            [trim($_POST['stat3_val'] ?? ''), trim($_POST['stat3_label'] ?? '')],
            [trim($_POST['stat4_val'] ?? ''), trim($_POST['stat4_label'] ?? '')],
        ],
        'quote'       => trim($_POST['quote_text'] ?? ''),
        'quote_name'  => trim($_POST['quote_name'] ?? ''),
        'quote_role'  => trim($_POST['quote_role'] ?? ''),
        'features'    => [],
        'why'         => [],
        'cta'         => [
            'headline' => trim($_POST['cta_headline'] ?? ''),
            'sub'      => trim($_POST['cta_sub'] ?? ''),
            'btn_text' => trim($_POST['cta_btn_text'] ?? ''),
        ]
    ];
    
    if ($why_bg) $lj['why_bg'] = $why_bg;
    elseif (isset($landingJson['why_bg'])) $lj['why_bg'] = $landingJson['why_bg'];
    
    if ($cta_bg) $lj['cta_bg'] = $cta_bg;
    elseif (isset($landingJson['cta_bg'])) $lj['cta_bg'] = $landingJson['cta_bg'];

    if ($quote_bg) $lj['quote_bg'] = $quote_bg;
    elseif (isset($landingJson['quote_bg'])) $lj['quote_bg'] = $landingJson['quote_bg'];

    for ($i = 0; $i < 6; $i++) {
        $feature_icon = handle_media_picker('feature' . $i . '_icon', 'public/assets') ?: trim($_POST["feature{$i}_icon_sel"] ?? '');
        $lj['features'][] = [
            trim($_POST["feature{$i}_title"] ?? ''),
            trim($_POST["feature{$i}_text"] ?? ''),
            $feature_icon ?: (isset($landingJson['features'][$i][2]) ? $landingJson['features'][$i][2] : ''),
        ];
    }
    
    for ($i = 0; $i < 4; $i++) {
        $lj['why'][] = [
            trim($_POST["why{$i}_title"] ?? ''),
            trim($_POST["why{$i}_text"] ?? '')
        ];
    }
    
    $fields['landing_html'] = json_encode($lj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    crud()->update('platform_settings', $fields, ['id' => 1]);
    flash('success', 'Website settings saved.');
    redirect('admin/owner/website-settings');
}

$hero = !empty($s['hero_image_path']) ? url($s['hero_image_path']) : url('public/campus-hero.png');
?>
<div class="row g-4">
  <!-- LEFT: forms -->
  <div class="col-lg-7">

    <!-- Branding -->
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Brand & contact</h3></div>
      <div class="card-body">
        <form method="post" class="d-grid gap-3" enctype="multipart/form-data">
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
            <input type="email" class="form-control" name="contact_email" value="<?= h($s['contact_email'] ?? '') ?>">
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

          <!-- Hero copy -->
          <hr>
          <h5 style="font-weight:700">Hero section copy</h5>
          <div>
            <label class="form-label">Headline</label>
            <input class="form-control" name="hero_headline" value="<?= h($landingJson['hero_headline'] ?? 'Explore. Experience. Innovate.') ?>">
          </div>
          <div>
            <label class="form-label">Sub-headline</label>
            <textarea class="form-control" name="hero_sub" rows="2"><?= h($landingJson['hero_sub'] ?? 'Make a great first impression, at any scale. Give every prospective student a real sense of belonging before they even set foot on campus.') ?></textarea>
          </div>
          
          <!-- Why Section -->
          <hr>
          <h5 style="font-weight:700">Why Choose Us Section</h5>
          <div>
            <?php
            $pickerName = 'why_bg';
            $pickerValue = $landingJson['why_bg'] ?? '';
            $pickerLabel = 'Background Image';
            $pickerHelp = 'Background for the Why section. A dark overlay will be applied automatically.';
            require __DIR__ . '/../layout/media-picker.php';
            ?>
          </div>
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
            <div class="col-md-4"><label class="form-label">Item <?= $i + 1 ?> Title</label><input class="form-control" name="why<?= $i ?>_title" value="<?= h($wt) ?>"></div>
            <div class="col-md-8"><label class="form-label">Item <?= $i + 1 ?> Description</label><input class="form-control" name="why<?= $i ?>_text" value="<?= h($wd) ?>"></div>
          </div>
          <?php endfor ?>
          
          <!-- CTA Section -->
          <hr>
          <h5 style="font-weight:700">Call to Action (CTA) Section</h5>
          <div>
            <?php
            $pickerName = 'cta_bg';
            $pickerValue = $landingJson['cta_bg'] ?? '';
            $pickerLabel = 'Background Image';
            $pickerHelp = 'Background for the CTA section. A dark overlay will be applied automatically.';
            require __DIR__ . '/../layout/media-picker.php';
            ?>
          </div>
          <div><label class="form-label">Headline</label><input class="form-control" name="cta_headline" value="<?= h($landingJson['cta']['headline'] ?? 'Your next chapter starts here') ?>"></div>
          <div><label class="form-label">Sub-headline</label><input class="form-control" name="cta_sub" value="<?= h($landingJson['cta']['sub'] ?? 'Bring your campus online. Talk to our team about creating an experience your students will remember.') ?>"></div>
          <div><label class="form-label">Button Text</label><input class="form-control" name="cta_btn_text" value="<?= h($landingJson['cta']['btn_text'] ?? 'Contact support') ?>"></div>

          <!-- Stats -->
          <hr>
          <h5 style="font-weight:700">Stats bar</h5>
          <?php
          $statsDefaults = [['24+','Partner schools'],['180+','Tours created'],['4.8k','Places described by AI'],['100%','AR-ready platform']];
          $stats = $landingJson['stats'] ?? $statsDefaults;
          for ($i = 0; $i < 4; $i++):
            $si = $i + 1;
            [$sv, $sl] = array_pad($stats[$i] ?? [], 2, '');
          ?>
          <div class="row g-2">
            <div class="col-md-3"><label class="form-label">Stat <?= $si ?> value</label><input class="form-control" name="stat<?= $si ?>_val" value="<?= h($sv) ?>"></div>
            <div class="col-md-9"><label class="form-label">Stat <?= $si ?> label</label><input class="form-control" name="stat<?= $si ?>_label" value="<?= h($sl) ?>"></div>
          </div>
          <?php endfor ?>

          <!-- Quote -->
          <hr>
          <h5 style="font-weight:700">Testimonial quote</h5>
          <div>
            <?php
            $pickerName = 'quote_bg';
            $pickerValue = $landingJson['quote_bg'] ?? '';
            $pickerLabel = 'Quote Card Background Image';
            $pickerHelp = 'Optional background image for the testimonial quote card. A dark scrim overlay will be applied automatically.';
            require __DIR__ . '/../layout/media-picker.php';
            ?>
          </div>
          <div><label class="form-label">Quote text</label><textarea class="form-control" name="quote_text" rows="3"><?= h($landingJson['quote'] ?? '"Innovatech helps us give prospective students a real sense of belonging before they even set foot on campus."') ?></textarea></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Person name</label><input class="form-control" name="quote_name" value="<?= h($landingJson['quote_name'] ?? 'Maria Angela Reyes') ?>"></div>
            <div class="col-md-6"><label class="form-label">Role / institution</label><input class="form-control" name="quote_role" value="<?= h($landingJson['quote_role'] ?? 'Director of Admissions · Partner school') ?>"></div>
          </div>

          <!-- Features -->
          <hr>
          <h5 style="font-weight:700">Features Marquee</h5>
          <p class="text-muted" style="font-size:13px;margin-bottom:12px">These 6 cards scroll horizontally. Ensure titles are short and descriptions are punchy. Images are sourced from the default <code>assets/</code> folder.</p>
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
          $iconOptions = ['move3d', 'navigation', 'sparkles', 'compass', 'chart', 'layers', 'building', 'map', 'camera', 'school'];
          for ($i = 0; $i < 6; $i++):
              [$ft, $fd, $fi] = array_pad($features[$i] ?? [], 3, '');
          ?>
          <div class="p-3 mb-2" style="background:var(--ia-surface-2);border-radius:10px">
            <div class="row g-2 mb-2">
              <div class="col-md-6"><label class="form-label" style="font-size:12px">Feature <?= $i + 1 ?> Title</label><input class="form-control form-control-sm" name="feature<?= $i ?>_title" value="<?= h($ft) ?>"></div>
              <div class="col-md-6">
                  <label class="form-label" style="font-size:12px">Icon (Media Picker)</label>
                  <?php
                  $pickerName = "feature{$i}_icon";
                  $pickerValue = !in_array($fi, $iconOptions) ? $fi : '';
                  $pickerLabel = 'Custom Icon (or pick default below)';
                  $pickerHelp = '';
                  require __DIR__ . '/../layout/media-picker.php';
                  ?>
                  <select class="form-select form-select-sm mt-1" name="feature<?= $i ?>_icon_sel">
                      <option value="">- Use custom uploaded icon -</option>
                      <?php foreach ($iconOptions as $icon): ?>
                          <option value="<?= h($icon) ?>" <?= $fi === $icon ? 'selected' : '' ?>><?= h($icon) ?> (default)</option>
                      <?php endforeach; ?>
                  </select>
              </div>
            </div>
            <div>
                <label class="form-label" style="font-size:12px">Description</label>
                <input class="form-control form-control-sm" name="feature<?= $i ?>_text" value="<?= h($fd) ?>">
            </div>
          </div>
          <?php endfor ?>

          <div class="text-end"><button class="btn btn-grad px-4" type="submit">Save all settings</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- RIGHT: preview + info -->
  <div class="col-lg-5">
    <div class="ia-card mb-4">
      <div class="card-head"><h3>Preview</h3><a class="back-link" href="<?= url('/') ?>" target="_blank">Open site</a></div>
      <div class="card-body">
        <img src="<?= h($hero) ?>" alt="Hero preview" style="width:100%;border-radius:12px;border:1px solid var(--ia-border)">
        <ul class="mt-3 d-grid gap-2" style="font-size:13.5px;list-style:none;padding:0">
          <li><strong>Company:</strong> <?= h($s['company_name'] ?? '') ?></li>
          <li><strong>Product:</strong> <?= h($s['product_name'] ?? '') ?></li>
          <li><strong>Email:</strong> <?= h($s['contact_email'] ?? '') ?></li>
        </ul>
      </div>
    </div>

    <div class="ia-card mb-4">
      <div class="card-head"><h3>Partner campuses</h3><a class="back-link" href="institutions">Manage</a></div>
      <div class="card-body" style="font-size:13.5px">
        <p class="mb-0">Published, active institutions automatically appear in the landing "Partner Campuses" section. Org admins upload their own cover image in <em>Settings → Profile → Cover image</em>.</p>
      </div>
    </div>

    <div class="ia-card">
      <div class="card-head"><h3>Per-institution branding</h3></div>
      <div class="card-body d-grid gap-2" style="font-size:13.5px">
        <p class="text-muted" style="font-size:13px">Colors, fonts, popup style, and logo for a specific client live in their own dashboard:</p>
        <a class="btn btn-outline-ia" href="<?= url('admin/institution/settings') ?>"><?= ia_icon('palette', 15) ?> Theme & Landing</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
