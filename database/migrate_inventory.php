<?php
/**
 * Lavadora — incremental migration for the inventory & system changes.
 * Run ONCE against an existing database to add the new tables, permissions,
 * and seed data without dropping existing data.
 *
 *   CLI:  php database/migrate_inventory.php
 *   Web:  open /database/migrate_inventory.php (temporary)
 *
 * Safe to run multiple times (idempotent via IF NOT EXISTS checks).
 */

require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$out = [];
function logline(string $m): void { global $out; $out[] = $m; }
logline("Connected to DB: " . DB_NAME);

try {
    // ---------- 1. inventory_items ----------
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        category ENUM('detergent','softener','bleach','packaging','other') NOT NULL DEFAULT 'other',
        unit VARCHAR(32) NOT NULL DEFAULT 'ml',
        current_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
        minimum_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
        cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    logline("OK: inventory_items");

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_usage (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        service_id INT UNSIGNED NOT NULL,
        inventory_item_id INT UNSIGNED NOT NULL,
        usage_per_kg DECIMAL(10,4) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_usage_service_item (service_id, inventory_item_id),
        CONSTRAINT fk_usage_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
        CONSTRAINT fk_usage_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    logline("OK: inventory_usage");

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        inventory_item_id INT UNSIGNED NOT NULL,
        type ENUM('in','out','adjust') NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        reference VARCHAR(120) DEFAULT NULL,
        notes TEXT,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_movements_item (inventory_item_id),
        CONSTRAINT fk_movements_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE CASCADE,
        CONSTRAINT fk_movements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    logline("OK: inventory_movements");

    // ---------- 2. permissions ----------
    $insPerm = $pdo->prepare("INSERT IGNORE INTO permissions (slug, module, description) VALUES (?, ?, ?)");
    $insPerm->execute(['inventory.manage', 'laundry', 'View and manage inventory stock and settings']);
    $insPerm->execute(['settings.manage_system', 'laundry', 'Manage system settings']);
    logline("OK: permissions added");

    // staff role gets inventory.manage (role id 2)
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 2, id FROM permissions WHERE slug IN ('inventory.manage')");
    // owner already got everything on fresh install, but ensure on migrated DBs too
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 1, id FROM permissions WHERE slug IN ('inventory.manage', 'settings.manage_system')");
    logline("OK: role permissions linked");

    // ---------- 3. seed inventory items (only if empty) ----------
    $count = (int) $pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
    if ($count === 0) {
        $ins = $pdo->prepare("INSERT INTO inventory_items (name, category, unit, current_stock, minimum_stock, cost_per_unit, is_active) VALUES (?,?,?,?,?,?,?)");
        $ins->execute(['Laundry Detergent', 'detergent', 'ml', 5000, 1000, 0.15, 1]);
        $ins->execute(['Fabric Softener', 'softener', 'ml', 3000, 800, 0.20, 1]);
        $ins->execute(['Bleach', 'bleach', 'ml', 2000, 500, 0.10, 1]);
        $ins->execute(['Packaging Bags', 'packaging', 'pieces', 200, 50, 2.00, 1]);
        $ins->execute(['Stain Remover', 'other', 'ml', 1000, 300, 0.25, 1]);
        logline("OK: seeded inventory items");
    } else {
        logline("skip: inventory_items already populated ({$count} rows)");
    }

    // ---------- 4. seed usage rates (reference by slug/name to be robust) ----------
    $svcRows = [];
    foreach ($pdo->query("SELECT id, name FROM services")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $svcRows[$r['name']] = (int) $r['id'];
    }
    $itemRows = [];
    foreach ($pdo->query("SELECT id, name FROM inventory_items")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $itemRows[$r['name']] = (int) $r['id'];
    }
    $seed = [
        'Wash & Fold' => ['Laundry Detergent' => 15, 'Fabric Softener' => 10, 'Bleach' => 5],
        'Wash & Iron' => ['Laundry Detergent' => 15, 'Fabric Softener' => 10, 'Bleach' => 5, 'Stain Remover' => 3],
        'Dry Cleaning' => ['Laundry Detergent' => 20, 'Fabric Softener' => 15, 'Bleach' => 8, 'Stain Remover' => 5],
        'Comforter' => ['Laundry Detergent' => 25, 'Fabric Softener' => 20, 'Bleach' => 10],
        'Shoes & Sneakers' => ['Laundry Detergent' => 20, 'Fabric Softener' => 15, 'Stain Remover' => 5],
    ];
    $insUsage = $pdo->prepare("INSERT IGNORE INTO inventory_usage (service_id, inventory_item_id, usage_per_kg) VALUES (?,?,?)");
    $seeded = 0;
    foreach ($seed as $svcName => $items) {
        if (!isset($svcRows[$svcName])) continue;
        foreach ($items as $itemName => $rate) {
            if (!isset($itemRows[$itemName])) continue;
            $insUsage->execute([(int)$svcRows[$svcName], (int)$itemRows[$itemName], $rate]);
            $seeded++;
        }
    }
    logline("OK: seeded {$seeded} usage rates");

    // ---------- 5. update order status ENUM to add washing/drying ----------
    try {
        $pdo->exec("ALTER TABLE laundry_orders
            MODIFY status ENUM('pending','washing','drying','ready','completed','cancelled') NOT NULL DEFAULT 'pending'");
        logline("OK: order status enum expanded");
    } catch (Throwable $e) {
        logline("note: status enum already has washing/drying or skipped: " . $e->getMessage());
    }

    logline("--- Migration complete ---");
    $ok = true;
} catch (Throwable $e) {
    logline("ERROR: " . $e->getMessage());
    $ok = false;
}

// Output (CLI or web)
if (PHP_SAPI === 'cli') {
    echo implode("\n", $out) . "\n";
} else {
    echo "<pre style='font:14px/1.5 monospace;padding:20px'>" . htmlspecialchars(implode("\n", $out)) . "</pre>";
    echo "<p><strong>Migration {$ok}</strong></p>";
    // Optional lock — comment this out to keep the file usable offline.
    echo "<p>Delete this script after running.</p>";
}
