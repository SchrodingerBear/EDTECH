<?php
/**
 * Static file server with caching headers for PHP built-in server
 * Usage: php -S 0.0.0.0:8000 -t . static-server.php
 */

$requestedFile = __DIR__ . $_SERVER['REQUEST_URI'];

// Security: prevent directory traversal (simplified for PHP built-in server)
if (strpos($_SERVER['REQUEST_URI'], '..') !== false) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// If file doesn't exist, is a directory, or is a PHP file, let PHP handle it normally
if (!file_exists($requestedFile) || is_dir($requestedFile) || $extension === 'php') {
    return false; // Let PHP built-in server handle it
}

// Get file extension
$pathInfo = pathinfo($requestedFile);
$extension = strtolower($pathInfo['extension'] ?? '');

// Set appropriate MIME type
$mimeTypes = [
    'css' => 'text/css',
    'js' => 'application/javascript',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',
];

$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $mimeType);

// Set caching headers
if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'])) {
    // 7 days for images
    header('Cache-Control: public, max-age=604800');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');
} elseif (in_array($extension, ['css', 'js', 'woff', 'woff2', 'ttf', 'eot'])) {
    // 1 day for CSS, JS, and fonts
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
} else {
    // 1 hour for other static files
    header('Cache-Control: public, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
}

// Enable compression for text-based files
if (in_array($extension, ['css', 'js', 'svg', 'html', 'json'])) {
    header('Content-Encoding: gzip');
    ob_start('ob_gzhandler');
}

// Serve the file
readfile($requestedFile);
exit;