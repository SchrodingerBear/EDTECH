<?php
/**
 * Innovatech PH — global configuration.
 * Single source of truth for paths, DB, sessions and app settings.
 */

declare(strict_types=1);

// Buffer output globally so header()/redirect()/flash still work even after
// some output has been emitted (POST handlers run after header.php renders).
// NOTE: must be unconditional — PHP ships its own 4096-byte buffer that
// auto-flushes when full, so a guarded ob_start() would never be added
// (ob_get_level() is already 1) and headers would reach the client mid-render.
ob_start();

// --------------------------------------------------------------------------
// Environment (.env). Simple loader — no external library. Values fall back
// to the dev defaults below when .env is missing. .env is git-ignored and
// blocked by the root .htaccess.
// --------------------------------------------------------------------------
foreach (array_filter(file_exists(__DIR__ . '/.env') ? file(__DIR__ . '/.env') : [], fn ($l) => trim($l) !== '' && $l[0] !== '#' && str_contains($l, '=')) as $__line) {
    [$__k, $__v] = explode('=', trim($__line), 2);
    $__k = trim($__k);
    $__v = trim($__v);
    if ($__k === '') {
        continue;
    }
    if (!array_key_exists($__k, $_ENV)) {
        $_ENV[$__k] = str_replace(['"', "'"], '', $__v);
    }
}

/** Read a .env / environment value with a fallback default. */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? (getenv($key) ?: $default);
}

const APP_NAME = 'Innovatech PH';
const APP_PRODUCT = 'AI-Assisted AR 360° Virtual Campus Navigation';

/* ---------------------------------- paths ---------------------------------- */

// Absolute filesystem root of the project (no trailing slash).
define('ROOT_PATH', dirname(__DIR__));

// Public base URL without trailing slash. Overridden by APP_URL_ENV if set.
// Derived by matching SCRIPT_FILENAME's path under ROOT_PATH against SCRIPT_NAME,
// so it stays correct whether the project is served at the webroot or a subfolder.
define('BASE_URL', (function () {
    if ($env = env('APP_URL', '')) {
        return rtrim((string) $env, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
    $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $realFile = realpath($scriptFile) ?: $scriptFile;
    $rootBase = rtrim(str_replace('\\', '/', ROOT_PATH), '/');
    $prefix = '';

    if ($scriptFile !== '' && str_starts_with($scriptFile, $rootBase)) {
        $trail = substr($scriptFile, strlen($rootBase));
        if ($trail !== '' && str_ends_with($scriptName, $trail)) {
            $prefix = rtrim(substr($scriptName, 0, -strlen($trail)), '/');
        }
    }

    return $scheme . '://' . $host . $prefix;
})());

// Where generated institution project folders live (web-accessible).
define('ORG_ROOT', ROOT_PATH . '/organizations');
define('ORG_ROOT_URL', BASE_URL . '/organizations');

// Template pack copied into each new institution folder.
define('TEMPLATE_PACK', ROOT_PATH . '/templates/org_pack');

/* ---------------------------------- database -------------------------------- */

// DB connection values come from .env when present; the defaults below are the
// dev fallback so the app boots even before .env is created.
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) (env('DB_PORT', '3306') ?: '3306'));
define('DB_NAME', env('DB_NAME', 'innovatech_campus'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', 'innovatechph'));

/* ---------------------------------- session --------------------------------- */

const SESSION_NAME = 'innova_session';
const SESSION_LIFETIME = 60 * 60 * 8; // 8 hours