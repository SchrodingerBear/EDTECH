<?php
/**
 * Script to remove last_name field from all tables (customers, users, employees)
 * Run this script via: http://localhost/TITLE2/database/remove_lastname_all.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Removing last_name field from all tables...\n";
    
    $tables = ['customers', 'users', 'employees'];
    
    foreach ($tables as $table) {
        echo "\n--- Processing {$table} ---\n";
        
        // Check if table exists
        try {
            $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        } catch (Throwable $e) {
            echo "Table {$table} does not exist. Skipping.\n";
            continue;
        }
        
        // Check if last_name column exists
        $columns = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll();
        $columnNames = array_column($columns, 'Field');
        
        if (!in_array('last_name', $columnNames)) {
            echo "last_name column does not exist in {$table}. Skipping.\n";
            continue;
        }
        
        // Update existing data to combine first and last name into first_name
        echo "Combining first_name and last_name into first_name...\n";
        $pdo->exec("UPDATE {$table} SET first_name = CONCAT(first_name, ' ', last_name) WHERE last_name IS NOT NULL AND last_name != ''");
        echo "Updated existing names in {$table}.\n";
        
        // Drop the last_name column
        echo "Dropping last_name column from {$table}...\n";
        $pdo->exec("ALTER TABLE {$table} DROP COLUMN last_name");
        echo "Removed last_name column from {$table}.\n";
    }
    
    echo "\n=== SUCCESS ===\n";
    echo "last_name field has been removed from all tables.\n";
    echo "All existing names have been combined into first_name.\n";
    echo "Tables now only contain first_name (no last_name).\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
