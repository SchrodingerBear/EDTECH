<?php
/**
 * Innovatech PH — logout.
 */
require_once __DIR__ . '/../includes/auth.php';

do_logout();
redirect('admin/index');