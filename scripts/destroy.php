<?php
// Ensure this only runs on localhost for security
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;

if (!$isLocalhost) {
    http_response_code(403);
    die("Forbidden: This script can only be run on localhost.");
}

// 1. Destroy PHP Session
session_start();
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 2. Unset all other PHP cookies
foreach ($_COOKIE as $key => $value) {
    setcookie($key, '', time() - 3600, '/');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destroy Environment Data</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1a1a1a; color: #fff; padding: 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #2d2d2d; border-radius: 12px; padding: 2rem; text-align: center; border: 1px solid #444; max-width: 500px; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h1 { color: #ff4757; margin-top: 0; }
        p { color: #a4b0be; }
        .success { color: #2ed573; font-weight: bold; margin-top: 1rem; }
        pre { background: #111; padding: 1rem; border-radius: 6px; text-align: left; overflow-x: auto; color: #2ed573; font-size: 0.9rem;}
    </style>
</head>
<body>

    <div class="card">
        <h1>💥 Data Destroyed</h1>
        <p>PHP Session and cookies have been cleared.</p>
        <p>Clearing client-side storage...</p>
        <div id="log" class="success"></div>
    </div>

    <script>
        const log = document.getElementById('log');
        const writeLog = (msg) => log.innerHTML += `<div>✓ ${msg}</div>`;

        try {
            // 1. Clear LocalStorage
            localStorage.clear();
            writeLog('localStorage cleared');

            // 2. Clear SessionStorage
            sessionStorage.clear();
            writeLog('sessionStorage cleared');

            // 3. Clear IndexedDB (Common ones, can't reliably loop all without knowing names, but we try)
            // Just a placeholder note, usually localStorage is enough for basic web apps.

            // 4. Clear Cache Storage (Service Workers)
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    for (let name of names) {
                        caches.delete(name);
                    }
                    writeLog('Service Worker Caches cleared');
                });
            }

            // 5. Clear client-side cookies just to be sure
            document.cookie.split(";").forEach(function(c) { 
                document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
            });
            writeLog('Client-side cookies cleared');

            setTimeout(() => {
                log.innerHTML += `<div style="margin-top: 20px; color: #fff;">Redirecting to home in 3 seconds...</div>`;
                setTimeout(() => {
                    window.location.href = '/';
                }, 3000);
            }, 1000);

        } catch (e) {
            log.innerHTML += `<div style="color: #ff4757;">Error clearing client data: ${e.message}</div>`;
        }
    </script>
</body>
</html>
