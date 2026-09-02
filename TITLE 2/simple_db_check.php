<?php
// Simple direct database check - no config files
$host = 'localhost';
$port = 3306;
$dbname = 'lavadora_laundry';
$user = 'root';
$password = 'innovatech';

echo "Connecting to database...\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
    echo "✅ Connected successfully!\n";
    
    // Check if users table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "✅ Found $count users in database\n";
    
    // Check if services table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM services");
    $count = $stmt->fetchColumn();
    echo "✅ Found $count services in database\n";
    
    echo "\nDatabase is working perfectly!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
