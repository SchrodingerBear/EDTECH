<?php
/**
 * Migration: Add panoramas table for 360 camera app
 * Run this to create the new table in your database
 */
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db();

    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'panoramas'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'panoramas' already exists.\n";
        exit;
    }

    // Create panoramas table
    $sql = "
    CREATE TABLE `panoramas` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `institution_id` INT UNSIGNED NOT NULL,
      `created_by` INT UNSIGNED NOT NULL,
      `title` VARCHAR(255) DEFAULT NULL,
      `description` TEXT DEFAULT NULL,
      `equirect_path` VARCHAR(255) NOT NULL,
      `thumbnail_path` VARCHAR(255) DEFAULT NULL,
      `capture_data` JSON DEFAULT NULL,
      `width` INT UNSIGNED DEFAULT NULL,
      `height` INT UNSIGNED DEFAULT NULL,
      `file_size` INT UNSIGNED DEFAULT NULL,
      `status` ENUM('draft', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'draft',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_panoramas_institution` (`institution_id`),
      KEY `idx_panoramas_status` (`status`),
      CONSTRAINT `fk_panoramas_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_panoramas_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✓ Table 'panoramas' created successfully.\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
