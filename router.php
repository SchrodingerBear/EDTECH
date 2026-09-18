<?php
/**
 * PHP built-in server router for the PARENT folder (G7 4D THESIS).
 * Run from inside the G7 4D THESIS folder:
 *
 *   php -S 127.0.0.1:8000 router.php
 *
 * Handles routing for both TITLE 1 and TITLE 2.
 */

$docRoot = __DIR__;
$rawUri  = $_SERVER['REQUEST_URI'] ?? '/';
$uri     = urldecode(parse_url($rawUri, PHP_URL_PATH));

// ── Determine which project is being accessed ──────────────────────────────
$projects = [
    '/TITLE 1' => 'TITLE 1',
    '/TITLE 2' => 'TITLE 2',
];

$projectDir  = null;
$projectBase = null;
$subUri      = $uri;

foreach ($projects as $urlBase => $folder) {
    if ($uri === $urlBase || str_starts_with($uri, $urlBase . '/')) {
        $projectBase = $urlBase;
        $projectDir  = $docRoot . '/' . $folder;
        $subUri      = substr($uri, strlen($urlBase)) ?: '/';
        break;
    }
}

// ── Inject BASE_URL into the project's env so config.php picks it up ───────
$injectBaseUrl = function () use ($projectBase) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $base   = rtrim($scheme . '://' . $host . $projectBase, '/');
    $_ENV['ROUTER_BASE_URL'] = $base;
    putenv('ROUTER_BASE_URL=' . $base);
};

// ── No matching project ───────────────────────────────────────────────────
if ($projectDir === null) {
    $file = $docRoot . $uri;
    if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
        return false;
    }
    http_response_code(404);
    echo '<h1>404 Not Found</h1><p>No project matched: <code>' . htmlspecialchars($uri) . '</code></p>';
    return true;
}

// ── Security: block sensitive paths ───────────────────────────────────────
$blocked = ['/.git', '/.env', '/includes/', '/database/', '/old/', '/templates/', '/.cursor'];
foreach ($blocked as $b) {
    if ($subUri === $b || str_starts_with($subUri, $b)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1>';
        return true;
    }
}

$physicalFile = $projectDir . $subUri;

// ── MIME types map ────────────────────────────────────────────────────────
$mimes = [
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'mjs'  => 'application/javascript',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2'=> 'font/woff2',
    'ttf'  => 'font/ttf',
    'otf'  => 'font/otf',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'map'  => 'application/json',
    'glb'  => 'model/gltf-binary',
    'gltf' => 'model/gltf+json',
    'html' => 'text/html',
    'txt'  => 'text/plain',
];

$serveStatic = function (string $filePath) use ($mimes) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        return true;
    }
    return false;
};

// ── Serve real static files directly (non-PHP) ────────────────────────────
if ($subUri !== '/' && file_exists($physicalFile) && !is_dir($physicalFile)) {
    $ext = strtolower(pathinfo($physicalFile, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
        if ($serveStatic($physicalFile)) return true;
        return false; // Let PHP built-in handle unknown static types
    }
    // .php file — fall through to serve via require below
}

// ── Removed TITLE 1 special: /organizations/{slug}/... logic ──────────────

// ── Extensionless clean URL → try <path>.php ──────────────────────────────
if ($subUri !== '/' && !str_ends_with($subUri, '/') && !str_contains(basename($subUri), '.')) {
    $phpFile = $projectDir . $subUri . '.php';
    if (file_exists($phpFile)) {
        $injectBaseUrl();
        chdir($projectDir);
        require $phpFile;
        return true;
    }
}

// ── Directory or project root → index.php ────────────────────────────────
if ($subUri === '/' || is_dir($physicalFile)) {
    $candidates = [
        rtrim($physicalFile, '/') . '/index.php',
        $projectDir . '/index.php',
    ];
    foreach ($candidates as $idx) {
        if (file_exists($idx)) {
            $injectBaseUrl();
            chdir($projectDir);
            require $idx;
            return true;
        }
    }
}

// ── Explicit .php file in URL ─────────────────────────────────────────────
if (file_exists($physicalFile) && str_ends_with($physicalFile, '.php')) {
    $injectBaseUrl();
    chdir($projectDir);
    require $physicalFile;
    return true;
}

// ── 404 ───────────────────────────────────────────────────────────────────
http_response_code(404);
echo '<h1>404 Not Found</h1><p><code>' . htmlspecialchars($uri) . '</code> was not found.</p>';
return true;
