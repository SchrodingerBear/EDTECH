<?php
/**
 * Script to add e-receipt system to the database
 * Run this script via: http://localhost/TITLE2/database/add_receipt_system.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Adding e-receipt system to database...\n";
    
    // Add receipt tracking columns to laundry_orders table
    $sql = "ALTER TABLE laundry_orders 
            ADD COLUMN IF NOT EXISTS receipt_token VARCHAR(32) UNIQUE DEFAULT NULL COMMENT 'Unique token for public receipt access',
            ADD COLUMN IF NOT EXISTS receipt_sent_at DATETIME DEFAULT NULL COMMENT 'When receipt was sent to customer',
            ADD COLUMN IF NOT EXISTS receipt_viewed_at DATETIME DEFAULT NULL COMMENT 'When customer first viewed receipt',
            ADD COLUMN IF NOT EXISTS receipt_view_count INT UNSIGNED DEFAULT 0 COMMENT 'How many times receipt was viewed'";
    
    // Since MySQL doesn't support IF NOT EXISTS for ADD COLUMN, we need to check first
    $columns = $pdo->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    $alterStatements = [];
    
    if (!in_array('receipt_token', $columnNames)) {
        $alterStatements[] = "ADD COLUMN receipt_token VARCHAR(32) UNIQUE DEFAULT NULL COMMENT 'Unique token for public receipt access'";
    }
    if (!in_array('receipt_sent_at', $columnNames)) {
        $alterStatements[] = "ADD COLUMN receipt_sent_at DATETIME DEFAULT NULL COMMENT 'When receipt was sent to customer'";
    }
    if (!in_array('receipt_viewed_at', $columnNames)) {
        $alterStatements[] = "ADD COLUMN receipt_viewed_at DATETIME DEFAULT NULL COMMENT 'When customer first viewed receipt'";
    }
    if (!in_array('receipt_view_count', $columnNames)) {
        $alterStatements[] = "ADD COLUMN receipt_view_count INT UNSIGNED DEFAULT 0 COMMENT 'How many times receipt was viewed'";
    }
    
    if (!empty($alterStatements)) {
        $alterSQL = "ALTER TABLE laundry_orders " . implode(', ', $alterStatements);
        $pdo->exec($alterSQL);
        echo "Added receipt tracking columns to laundry_orders table.\n";
    } else {
        echo "Receipt tracking columns already exist.\n";
    }
    
    // Create index for receipt token
    $indexes = $pdo->query("SHOW INDEX FROM laundry_orders")->fetchAll();
    $indexNames = array_column($indexes, 'Key_name');
    
    if (!in_array('idx_receipt_token', $indexNames)) {
        $pdo->exec("CREATE INDEX idx_receipt_token ON laundry_orders(receipt_token)");
        echo "Created index for receipt_token.\n";
    } else {
        echo "Index for receipt_token already exists.\n";
    }
    
    // Generate receipt tokens for existing orders
    $stmt = $pdo->query("SELECT id, order_no, created_at FROM laundry_orders WHERE receipt_token IS NULL");
    $orders = $stmt->fetchAll();
    
    if (!empty($orders)) {
        $updateStmt = $pdo->prepare("UPDATE laundry_orders SET receipt_token = ? WHERE id = ?");
        
        foreach ($orders as $order) {
            $token = substr(md5($order['id'] . $order['order_no'] . $order['created_at']), 0, 16) . $order['id'];
            $updateStmt->execute([$token, $order['id']]);
        }
        
        echo "Generated receipt tokens for " . count($orders) . " existing orders.\n";
    } else {
        echo "All orders already have receipt tokens.\n";
    }
    
    echo "\n=== SUCCESS ===\n";
    echo "E-receipt system has been added to the database.\n";
    echo "You can now use the receipt functionality.\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
