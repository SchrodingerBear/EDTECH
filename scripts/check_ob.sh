#!/bin/bash
grep -rn "ob_start\|output_buffering" "/var/www/html/G7 4D THESIS/TITLE 1/includes/" 2>/dev/null
php -r 'echo "output_buffering=".ini_get("output_buffering").PHP_EOL;'