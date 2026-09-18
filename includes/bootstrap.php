<?php
/**
 * Lavadora — public landing bootstrap.
 * Reads settings + services (and validates the DB connection).
 */

require_once __DIR__ . '/functions.php'; // h(), url(), db(), crud(), peso()

$landing = [
    'business_name' => APP_NAME,
    'tagline' => 'Fresh, clean laundry at your doorstep',
    'contact_email' => 'hello@lavadora.ph',
    'phone' => '0917-000-0000',
    'address' => 'Philippines',
    'logo_path' => null,
    'hero_image_path' => null,
    'delivery_fee' => 0.00,
    'login_url' => 'admin/index.php',
    'hero_headline' => 'Fresh, clean laundry',
    'hero_sub' => 'Professional wash, dry, fold and iron services. Schedule a pickup in minutes.',
    'announcement' => '',
];

$services = [];
try {
    $settings = crud()->get('settings', 1);
    if ($settings) {
        foreach (['business_name', 'tagline', 'address', 'phone', 'email', 'logo_path', 'hero_image_path', 'delivery_fee'] as $key) {
            if (!empty($settings[$key])) {
                $landing[$key] = $settings[$key];
            }
        }
        $lj = json_decode($settings['landing_html'] ?? 'null', true) ?: [];
        if (!empty($lj['hero_headline'])) $landing['hero_headline'] = $lj['hero_headline'];
        if (!empty($lj['hero_sub']))      $landing['hero_sub']      = $lj['hero_sub'];
        if (!empty($lj['announcement']))  $landing['announcement']  = $lj['announcement'];
    }

    $services = crud()->select('services', '*', ['is_active' => 1], 'ORDER BY name');
} catch (Throwable $e) {
    // Landing still works before the schema is imported.
}
