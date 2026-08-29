<?php
/**
 * CLI seed — demo accounts + one demo institution (idempotent).
 * Usage: php database/seed-demo.php
 */
declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
$_SERVER['HTTPS'] = 'off';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';


$demoPass = password_hash('password', PASSWORD_DEFAULT);
$owner = crud()->raw("SELECT id FROM users WHERE email='owner@innovatech.ph' AND deleted_at IS NULL")->fetch();
if (!$owner) {
    crud()->insert('users', [
        'role_id' => 1, 'institution_id' => null, 'email' => 'owner@innovatech.ph', 'username' => 'owner',
        'password_hash' => $demoPass, 'first_name' => 'Innovatech', 'last_name' => 'Owner', 'is_active' => 1
    ]);
    echo "seeded: owner@innovatech.ph\n";
}

// system accounts (no institution)
$sysAccs = [
    ['system.admin@innovatech.ph', 'system_admin', 'System', 'Admin'],
    ['system.staff@innovatech.ph', 'system_staff', 'System', 'Staff'],
];
foreach ($sysAccs as [$email, $roleSlug, $first, $last]) {
    $exists = crud()->raw("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=:e AND u.deleted_at IS NULL AND r.slug=:r", ['e' => $email, 'r' => $roleSlug])->fetch();
    if (!$exists) {
        crud()->raw(
            "INSERT INTO users (role_id, institution_id, email, username, password_hash, first_name, last_name, is_active)
             SELECT id, NULL, :e, :un, :ph, :f, :l, 1 FROM roles WHERE slug=:r",
            ['e' => $email, 'un' => slugify($first . '-' . $last), 'ph' => $demoPass, 'f' => $first, 'l' => $last, 'r' => $roleSlug]
        );
        echo "seeded: $email ($roleSlug)\n";
    }
}

// demo institution (only if none exist)
$count = (int) crud()->raw("SELECT COUNT(*) FROM institutions WHERE deleted_at IS NULL")->fetchColumn();
if ($count === 0) {
    $name = 'Immaculada Concepcion College';
    $slug = 'immaculada-concepcion-college';
    $folder = unique_folder($slug, ORG_ROOT);
    if (!rcopy(TEMPLATE_PACK, ORG_ROOT . '/' . $folder)) {
        throw new RuntimeException('rcopy failed');
    }
    $indexHtml = ORG_ROOT . '/' . $folder . '/index.html';
    if (is_file($indexHtml)) {
        $html = file_get_contents($indexHtml);
        $html = str_replace('{{NAME}}', $name, $html);
        $html = str_replace('{{SHORT}}', 'ICC', $html);
        $html = str_replace('__EQUIRECT__', 'assets/panos/example.jpg', $html);
        $html = str_replace('__YAW__', '100', $html);
        $html = str_replace('__PITCH__', '10', $html);
        $html = str_replace('__FLOORPLAN__', 'assets/floorplans/example.jpg', $html);
        file_put_contents($indexHtml, $html);
    }
    crud()->insert('institutions', [
        'slug' => $slug, 'name' => $name, 'short_name' => 'ICC', 'institution_type' => 'college',
        'city' => 'Manila', 'folder_path' => 'organizations/' . $folder, 'landing_mode' => '360_rotation',
        'is_active' => 1, 'created_by' => 1
    ]);
    $iid = (int) crud()->lastInsertId();

    $orgAccs = [
        ['admin@innovatech.ph', 'admin', 'Maria', 'Reyes'],
        ['staff@innovatech.ph', 'staff', 'Juan', 'Dela Cruz'],
    ];
    foreach ($orgAccs as [$email, $roleSlug, $first, $last]) {
        crud()->raw(
            "INSERT INTO users (role_id, institution_id, email, username, password_hash, first_name, last_name, is_active)
             SELECT id, :iid, :e, :un, :ph, :f, :l, 1 FROM roles WHERE slug=:r",
            ['iid' => $iid, 'e' => $email, 'un' => slugify($first . '-' . $last), 'ph' => $demoPass, 'f' => $first, 'l' => $last, 'r' => $roleSlug]
        );
        echo "seeded: $email ($roleSlug @ ICC)\n";
    }
} else {
    echo "institution skip: demo institution already exists\n";
}

echo "done\n";