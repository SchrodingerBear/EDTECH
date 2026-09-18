<?php
/**
 * Lavadora — root entry.
 * Redirects straight to the login page. There is no public landing page.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
redirect('admin/index');
