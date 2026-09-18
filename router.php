<?php
/**
 * PHP built-in server router — replaces .htaccess mod_rewrite for local dev.
 * Usage (run from INSIDE the TITLE 2 folder):
 *   php -S 127.0.0.1:8001 router.php
 *
 * Replicates the .htaccess rules:
 *  1. Block protected folders/files (includes/, database/, .env, etc.)
 *  2. Serve real static files directly (css, js, images, etc.)
 *  3. Extensionless clean URLs → append .php
 *  4. Trailing-slash directory → index.php inside it
 */

$uri    = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root   = __DIR__;
$file   = $root . $uri;

/* --- 1. Block sensitive paths --------------------------------------------- */
$blocked = ['.git', '.env', 'includes/', 'database/'];
foreach ($blocked as $b) {
    if (str_starts_with(ltrim($uri, '/'), $b)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1>';
        return true;
    }
}

/* --- 2. Serve real static files directly ----------------------------------- */
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

/* --- 3. Extensionless clean URL → try <path>.php --------------------------- */
if (!str_ends_with($uri, '/') && !str_contains(basename($uri), '.')) {
    $phpFile = $file . '.php';
    if (file_exists($phpFile)) {
        require $phpFile;
        return true;
    }
}

/* --- 4. Directory with index.php ------------------------------------------ */
if (is_dir($file)) {
    $idx = rtrim($file, '/') . '/index.php';
    if (file_exists($idx)) {
        require $idx;
        return true;
    }
}

/* --- 5. Root / → index.php ------------------------------------------------ */
if ($uri === '/' || $uri === '') {
    require $root . '/index.php';
    return true;
}

/* --- 6. 404 --------------------------------------------------------------- */
http_response_code(404);
echo '<h1>404 Not Found</h1><p>The requested resource <code>' . htmlspecialchars($uri) . '</code> was not found.</p>';
return true;
