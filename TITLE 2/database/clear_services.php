<?php
/**
 * Script to clear all services and add single 30 per kilo service
 * Run this script via: http://localhost/TITLE2/database/clear_services.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting service cleanup...\n";
    
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "Disabled foreign key checks.\n";
    
    // First, delete all existing services
    $stmt = $pdo->prepare("DELETE FROM services");
    $stmt->execute();
    echo "Deleted all existing services.\n";
    
    // Reset auto-increment
    $stmt = $pdo->prepare("ALTER TABLE services AUTO_INCREMENT = 1");
    $stmt->execute();
    echo "Reset services auto-increment.\n";
    
    // Insert single service: 30 per kilo
    $stmt = $pdo->prepare("INSERT INTO services (name, unit, price, icon, description, is_active, created_at, updated_at) 
                          VALUES (:name, :unit, :price, :icon, :description, :is_active, NOW(), NOW())");
    $stmt->execute([
        'name' => 'Laundry Service',
        'unit' => 'kg',
        'price' => 30.00,
        'icon' => 'droplets',
        'description' => 'Professional laundry service at 30 per kilogram',
        'is_active' => 1
    ]);
    echo "Added single service: Laundry Service at 30 per kilo.\n";
    
    // Get the new service ID
    $newServiceId = $pdo->lastInsertId();
    echo "New service ID: " . $newServiceId . "\n";
    
    // Update all order_items to use the new service ID
    $stmt = $pdo->prepare("UPDATE order_items SET service_id = ?");
    $stmt->execute([$newServiceId]);
    echo "Updated all order items to use new service ID.\n";
    
    // Optional: Clear inventory usage related to old services
    $stmt = $pdo->prepare("DELETE FROM inventory_usage");
    $stmt->execute();
    echo "Cleared inventory usage.\n";
    
    // Reset auto-increment for inventory_usage
    $stmt = $pdo->prepare("ALTER TABLE inventory_usage AUTO_INCREMENT = 1");
    $stmt->execute();
    echo "Reset inventory_usage auto-increment.\n";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Re-enabled foreign key checks.\n";
    
    echo "\n=== SUCCESS ===\n";
    echo "Services have been cleared and single 30 per kilo service added.\n";
    echo "All existing order items have been updated to use the new service.\n";
    echo "You can now visit /admin/services to see the changes.\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    // Make sure to re-enable foreign key checks even on error
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Throwable $e2) {
        // Ignore errors during cleanup
    }
}
