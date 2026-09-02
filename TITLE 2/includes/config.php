<?php
/**
 * Lavadora — Laundry Management System
 * Global configuration. Single source of truth for paths, DB, sessions and app settings.
 */

declare(strict_types=1);

ob_start();

// Environment (.env) loader — no external library.
$envPath = __DIR__ . '/../.env';
foreach (array_filter(file_exists($envPath) ? file($envPath) : [], fn ($l) => trim($l) !== '' && $l[0] !== '#' && str_contains($l, '=')) as $__line) {
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

const APP_NAME = 'Lavadora';
const APP_PRODUCT = 'Laundry Management System';

/* ---------------------------------- paths ---------------------------------- */
define('ROOT_PATH', dirname(__DIR__));

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

/* ---------------------------------- database -------------------------------- */
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) (env('DB_PORT', '3306') ?: '3306'));
define('DB_NAME', env('DB_NAME', 'lavadora_laundry'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', 'innovatech'));

/* ---------------------------------- session --------------------------------- */
const SESSION_NAME = 'lavadora_session';
const SESSION_LIFETIME = 60 * 60 * 8; // 8 hours
