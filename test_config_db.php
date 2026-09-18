<?php
/**
 * Test database connection using config.php
 */
require_once __DIR__ . '/includes/config.php';

echo "=== DATABASE CONNECTION TEST (via config.php) ===\n\n";
echo "Using configuration:\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_PORT: " . DB_PORT . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_PASS: " . (DB_PASS ? '***' : 'empty') . "\n\n";

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ CONNECTION SUCCESSFUL!\n\n";
    
    // Test query
    echo "Running test query...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✅ Query successful! Found {$result['count']} users.\n\n";
    
    // Show all tables
    echo "Available tables:\n";
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ CONNECTION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
