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

$pdo = db();
$demoPass = password_hash('password', PASSWORD_DEFAULT);
$owner = $pdo->query("SELECT id FROM users WHERE email='owner@innovatech.ph' AND deleted_at IS NULL")->fetch();
if (!$owner) {
    $pdo->prepare(
        "INSERT INTO users (role_id, institution_id, email, username, password_hash, first_name, last_name, is_active)
         VALUES (1, NULL, 'owner@innovatech.ph', 'owner', :ph, 'Innovatech', 'Owner', 1)"
    )->execute(['ph' => $demoPass]);
    echo "seeded: owner@innovatech.ph\n";
}

// system accounts (no institution)
$sysAccs = [
    ['system.admin@innovatech.ph', 'system_admin', 'System', 'Admin'],
    ['system.staff@innovatech.ph', 'system_staff', 'System', 'Staff'],
];
foreach ($sysAccs as [$email, $roleSlug, $first, $last]) {
    $exists = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=:e AND u.deleted_at IS NULL AND r.slug=:r");
    $exists->execute(['e' => $email, 'r' => $roleSlug]);
    if (!$exists->fetch()) {
        $pdo->prepare(
            "INSERT INTO users (role_id, institution_id, email, username, password_hash, first_name, last_name, is_active)
             SELECT id, NULL, :e, :un, :ph, :f, :l, 1 FROM roles WHERE slug=:r"
        )->execute(['e' => $email, 'un' => slugify($first . '-' . $last), 'ph' => $demoPass, 'f' => $first, 'l' => $last, 'r' => $roleSlug]);
        echo "seeded: $email ($roleSlug)\n";
    }
}

// demo institution (only if none exist)
$count = (int) $pdo->query("SELECT COUNT(*) FROM institutions WHERE deleted_at IS NULL")->fetchColumn();
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
    $pdo->prepare(
        "INSERT INTO institutions (slug, name, short_name, institution_type, city, folder_path, landing_mode, is_active, created_by)
         VALUES (:s, :n, 'ICC', 'college', 'Manila', :f, '360_rotation', 1, 1)"
    )->execute(['s' => $slug, 'n' => $name, 'f' => 'organizations/' . $folder]);
    $iid = (int) $pdo->lastInsertId();

    $orgAccs = [
        ['admin@innovatech.ph', 'admin', 'Maria', 'Reyes'],
        ['staff@innovatech.ph', 'staff', 'Juan', 'Dela Cruz'],
    ];
    foreach ($orgAccs as [$email, $roleSlug, $first, $last]) {
        $pdo->prepare(
            "INSERT INTO users (role_id, institution_id, email, username, password_hash, first_name, last_name, is_active)
             SELECT id, :iid, :e, :un, :ph, :f, :l, 1 FROM roles WHERE slug=:r"
        )->execute(['iid' => $iid, 'e' => $email, 'un' => slugify($first . '-' . $last), 'ph' => $demoPass, 'f' => $first, 'l' => $last, 'r' => $roleSlug]);
        echo "seeded: $email ($roleSlug @ ICC)\n";
    }
} else {
    echo "institution skip: demo institution already exists\n";
}

echo "done\n";