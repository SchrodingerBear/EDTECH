<?php
/**
 * PHP built-in server router — replaces .htaccess mod_rewrite for local dev.
 * Usage (run from INSIDE the TITLE 1 folder):
 *   php -S 127.0.0.1:8000 router.php
 *
 * Replicates the .htaccess rules:
 *  1. Block protected folders/files (includes/, database/, .env, etc.)
 *  2. Serve real static files directly (css, js, images, etc.)
 *  3. /organizations/{slug}/... → organizations/index.php?org={slug}
 *  4. Extensionless clean URLs → append .php
 *  5. Trailing-slash directory → index.php inside it
 */

$uri    = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root   = __DIR__;
$file   = $root . $uri;

/* --- 1. Block sensitive paths --------------------------------------------- */
$blocked = ['.git', '.env', 'includes/', 'database/', 'old/', 'templates/', '.cursor'];
foreach ($blocked as $b) {
    if (str_starts_with(ltrim($uri, '/'), $b)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1>';
        return true;
    }
}

/* --- 2. Serve real static files directly ----------------------------------- */
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
/* --- 3. Backward Compatibility Redirect for /organizations/{slug} ---- */
if (preg_match('#^/organizations/([a-z0-9\-]+)(?:/.*)?$#i', $uri, $m)) {
    $slug  = $m[1];
    $orgFile = $root . '/organizations/' . $slug;
    
    // If it's literally asking for a real file inside the folder (like assets), let it pass
    if (file_exists($root . $uri) && !is_dir($root . $uri)) {
        return false;
    }
    
    // Otherwise redirect to the parameter URL to fix relative paths
    header('Location: /organizations/index?org=' . urlencode($slug), true, 301);
    exit;
}

/* --- 4. Extensionless clean URL → try <path>.php --------------------------- */
if (!str_ends_with($uri, '/') && !str_contains(basename($uri), '.')) {
    $phpFile = $file . '.php';
    if (file_exists($phpFile)) {
        require $phpFile;
        return true;
    }
}

/* --- 5. Directory with index.php ------------------------------------------ */
if (is_dir($file)) {
    $idx = rtrim($file, '/') . '/index.php';
    if (file_exists($idx)) {
        require $idx;
        return true;
    }
}

/* --- 6. Root / → index.php ------------------------------------------------ */
if ($uri === '/' || $uri === '') {
    require $root . '/index.php';
    return true;
}

/* --- 7. 404 --------------------------------------------------------------- */
http_response_code(404);
echo '<h1>404 Not Found</h1><p>The requested resource <code>' . htmlspecialchars($uri) . '</code> was not found.</p>';
return true;
