<?php
/**
 * Direct database connection test - bypassing all config
 */
echo "=== DIRECT DATABASE CONNECTION TEST ===\n\n";

$host = 'localhost';
$port = 3306;
$dbname = 'lavadora_laundry';
$user = 'root';
$password = 'innovatech';

echo "Attempting to connect to MySQL...\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Database: $dbname\n";
echo "User: $user\n";
echo "Password: " . ($password ? '***' : 'empty') . "\n\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
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
