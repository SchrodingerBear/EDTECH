<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
try {
    $pdo->exec("ALTER TABLE `floor_plan_markers` MODIFY COLUMN `marker_type` enum('scene','entrance','exit','compass','360','floorplan','ar') NOT NULL DEFAULT 'scene'");
    echo "Successfully updated marker_type enum in floor_plan_markers.\n";
} catch (Exception $e) {
    echo "Error updating table: " . $e->getMessage() . "\n";
}
