<?php
require_once __DIR__ . '/../../includes/auth.php';
require_owner();
/**
 * Innovatech PH — owner: Support Tickets.
 * The support desk moved to the shared /admin/support page (available to all
 * admin/owner roles); email templates + test sends now live in System Settings.
 * Kept as a redirect so old bookmarks to admin/owner/emails still work.
 */
redirect('admin/support');