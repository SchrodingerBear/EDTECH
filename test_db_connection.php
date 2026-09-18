<?php
/**
 * Test database connection
 * Run this to check if database credentials are working
 */

echo "Testing database connection...\n";

// Test with .env values
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo ".env file found at: " . $envFile . "\n";
    echo ".env contents:\n";
    echo file_get_contents($envFile);
} else {
    echo ".env file NOT found at: " . $envFile . "\n";
}

echo "\n--- Testing connection ---\n";

try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/functions.php';
    
    echo "Config loaded:\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_PORT: " . DB_PORT . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    echo "DB_PASS: " . (DB_PASS ? "***" : "empty") . "\n";
    
    $pdo = db();
    echo "✅ Database connection successful!\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✅ Query successful! Found " . $result['count'] . " users.\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
