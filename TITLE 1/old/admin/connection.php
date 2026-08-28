<?php


$servername = "localhost"; // Replace with your server name
$username = "root";        // Replace with your database username
$password = "innovatechph";            // Replace with your database password
$dbname = "nu_museum";     
// PDO connection
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
