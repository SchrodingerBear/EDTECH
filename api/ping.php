<?php
/**
 * api/ping.php — Lightweight connectivity check
 * Used by offline-detector.js to verify actual server reachability (not just navigator.onLine)
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
echo json_encode(['status' => 'ok', 'ts' => time()]);
