<?php
define('ROOT_PATH', '/var/www/html/G7 4D THESIS/TITLE 1');

function compute_relative_prefix($callerFile) {
    $root = str_replace('\\', '/', ROOT_PATH);
    $callerFile = str_replace('\\', '/', $callerFile);
    if (str_starts_with($callerFile, $root)) {
        $rel = substr($callerFile, strlen($root) + 1); // +1 to remove leading slash
        $depth = substr_count($rel, '/');
        return $depth > 0 ? str_repeat('../', $depth) : '';
    }
    return '';
}

echo "admin/index.php -> " . compute_relative_prefix(ROOT_PATH . '/admin/index.php') . "\n";
echo "admin/user/edit.php -> " . compute_relative_prefix(ROOT_PATH . '/admin/user/edit.php') . "\n";
echo "index.php -> " . compute_relative_prefix(ROOT_PATH . '/index.php') . "\n";
