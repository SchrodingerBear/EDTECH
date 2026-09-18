<?php
/**
 * Innovatech PH product landing (owner-managed).
 * Reads platform_settings + published institutions when innovatech_campus exists.
 */

require_once __DIR__ . '/functions.php'; // h(), icon(), db(), crud()

$landing = [
    'company_name' => 'Innovatech PH',
    'product_name' => 'AI-Assisted AR 360° Virtual Campus Navigation',
    'contact_email' => 'support@innovatechservicesph.com',
    'logo_path' => null,
    'hero_image_path' => 'public/campus-hero.png',
    'login_url' => url('admin/index'),
];

$campuses = [];

$featureImages = [
    '360° Virtual Tours'            => 'assets/360° Virtual Tours.png',
    'AR Campus Overlays'            => 'assets/AR Campus Overlays.png',
    'AI-Assisted Descriptions'      => 'assets/AI Cubemap Stitching.png',
    'Room-to-Room Navigation'       => 'assets/360 AR Hotspot  Facility Information.png',
    'Admin Dashboard'               => 'assets/Admin Dashboard.png',
    'Multi-Tenant Ready'            => 'assets/Admin Dashboard.png',
];

$features = [
    ['360° Virtual Tours',          'Give every visitor an immersive, browser-based view of your campus.',          'move3d',    $featureImages['360° Virtual Tours']],
    ['AR Campus Overlays',          'Point visitors toward buildings, rooms, offices, and landmarks.',              'navigation',$featureImages['AR Campus Overlays']],
    ['AI-Assisted Descriptions',    'Turn a room, lab, or landmark into an engaging story in seconds.',             'sparkles',  $featureImages['AI-Assisted Descriptions']],
    ['Room-to-Room Navigation',     'Make getting around intuitive with connected, guided pathways.',               'compass',   $featureImages['Room-to-Room Navigation']],
    ['Admin Dashboard',             'Manage locations, content, analytics, and updates from one place.',            'chart',     $featureImages['Admin Dashboard']],
    ['Multi-Tenant Ready',          'Launch a beautiful, fully branded experience for every school.',               'layers',    $featureImages['Multi-Tenant Ready']],
];

$steps = [
    ['01', 'Capture', 'Shoot 360° photos with a smartphone or camera.'],
    ['02', 'Upload', 'Add your spaces, images, and campus details in the dashboard.'],
    ['03', 'Enhance', 'Let AI create descriptions and stitch connected spaces into one seamless campus experience.'],
    ['04', 'Publish', 'Share a branded, AR-ready virtual campus with the world.'],
];

$why = [
    ['Affordable', 'A smarter alternative to traditional 3D scanning services.'],
    ['Scalable', 'A reusable platform that grows with your campus.'],
    ['Fast', 'Go from first capture to live tour in days, not months.'],
    ['Yours', 'Your colors, your story, your branded experience.'],
];

try {
    $settings = crud()->get('platform_settings', 1);
    if ($settings) {
        foreach (['company_name', 'product_name', 'contact_email', 'logo_path'] as $key) {
            if (!empty($settings[$key])) {
                $landing[$key] = $settings[$key];
            }
        }
        // hero_image_path: stored as relative path, convert to absolute URL
        if (!empty($settings['hero_image_path'])) {
            $landing['hero_image_path'] = media_url($settings['hero_image_path']);
        }
        // Load editable content fields from landing_html JSON
        $lj = json_decode($settings['landing_html'] ?? 'null', true) ?: [];
        if (!empty($lj['hero_headline'])) $landing['hero_headline'] = $lj['hero_headline'];
        if (!empty($lj['hero_sub']))      $landing['hero_sub']      = $lj['hero_sub'];
        if (!empty($lj['stats']))         $landing['stats']         = $lj['stats'];
        if (!empty($lj['quote']))         $landing['quote']         = $lj['quote'];
        if (!empty($lj['quote_name']))    $landing['quote_name']    = $lj['quote_name'];
        if (!empty($lj['quote_role']))    $landing['quote_role']    = $lj['quote_role'];
        if (!empty($lj['cta']))           $landing['cta']           = $lj['cta'];

        // Background images — convert relative paths to absolute URLs
        foreach (['why_bg', 'cta_bg', 'quote_bg'] as $bgKey) {
            if (!empty($lj[$bgKey])) {
                $landing[$bgKey] = media_url($lj[$bgKey]);
            }
        }

        if (!empty($lj['why']) && is_array($lj['why'])) {
            $why = $lj['why'];
        }
        
        if (!empty($lj['features']) && is_array($lj['features'])) {
            $features = [];
            $imgValues = array_values($featureImages);
            foreach ($lj['features'] as $i => $f) {
                // Use the custom uploaded/selected image (index 3) if set, otherwise fall back to positional default
                $storedImg = $f[3] ?? '';
                $img = ($storedImg !== '') ? $storedImg : ($imgValues[$i] ?? $imgValues[0]);
                $icon = $f[2] ?? 'star';
                $features[] = [$f[0] ?? '', $f[1] ?? '', $icon, $img];
            }
        }
    }

    $rows = crud()->raw(
        "SELECT i.id, i.slug, i.name, i.short_name, i.institution_type, i.city, i.province,
                i.cover_image_path, i.is_published,
                (SELECT COUNT(*) FROM buildings b WHERE b.institution_id = i.id AND b.deleted_at IS NULL) AS building_count
         FROM institutions i
         WHERE i.deleted_at IS NULL AND i.is_active = 1 AND i.is_published = 1
         ORDER BY i.name"
    )->fetchAll();

    if ($rows) {
        $campuses = [];
        foreach ($rows as $row) {
            $type = $row['institution_type'] === 'university' ? 'University' : ($row['institution_type'] === 'college' ? 'College' : 'School');
            $location = trim(implode(', ', array_filter([$row['city'], $row['province']])));
            $campuses[] = [
                'id'        => (string) $row['id'],
                'slug'      => $row['slug'],
                'name'      => $row['name'],
                'short'     => $row['short_name'] ?: strtoupper(substr($row['slug'], 0, 4)),
                'location'  => $location !== '' ? $location : 'Philippines',
                'type'      => $type,
                'buildings' => (int) $row['building_count'],
                'status'    => 'live',
                'cover'     => $row['cover_image_path'] ?: $landing['hero_image_path'],
                'url'       => ORG_ROOT_URL . '/index?org=' . rawurlencode($row['slug']),
                'description' => $row['description'] ?? '',
            ];
        }
    }
} catch (Throwable $e) {
    // Landing still works before the new schema is imported.
}
