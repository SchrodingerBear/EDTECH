<?php
/**
 * Script to remove last_name field from customers table
 * Run this script via: http://localhost/TITLE2/database/remove_lastname.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Removing last_name field from customers table...\n";
    
    // Check if last_name column exists
    $columns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    if (!in_array('last_name', $columnNames)) {
        echo "last_name column does not exist. Nothing to do.\n";
        echo "\n=== SUCCESS ===\n";
        echo "Customers table already has no last_name field.\n";
        exit;
    }
    
    // Update existing data to combine first and last name into first_name
    echo "Combining first_name and last_name into first_name...\n";
    $pdo->exec("UPDATE customers SET first_name = CONCAT(first_name, ' ', last_name) WHERE last_name IS NOT NULL AND last_name != ''");
    echo "Updated existing customer names.\n";
    
    // Drop the last_name column
    echo "Dropping last_name column...\n";
    $pdo->exec("ALTER TABLE customers DROP COLUMN last_name");
    echo "Removed last_name column from customers table.\n";
    
    echo "\n=== SUCCESS ===\n";
    echo "last_name field has been removed from customers table.\n";
    echo "All existing names have been combined into first_name.\n";
    echo "Customer records now only contain: first_name, phone, email, address, notes\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
