<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();

// Simply update the updated_at timestamp on platform_settings to act as a cache buster for images.
crud()->raw("UPDATE platform_settings SET updated_at = NOW()");
flash('success', 'Platform cache cleared.');
redirect('admin/owner/dashboard');
