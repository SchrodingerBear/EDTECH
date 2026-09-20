<?php
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

do_logout();
echo json_encode(['success' => true]);