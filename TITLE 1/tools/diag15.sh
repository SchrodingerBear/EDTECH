#!/bin/bash
grep -rn "flush\|implicit_flush\|ob_flush\|ob_end\|ob_get_level\|ob_start" "/var/www/html/G7 4D THESIS/TITLE 1/admin/" "/var/www/html/G7 4D THESIS/TITLE 1/includes/" 2>/dev/null | grep -v "^Binary"
echo "--- htaccess files ---"
find "/var/www/html/G7 4D THESIS/TITLE 1" -name ".htaccess" -maxdepth 3 2>/dev/null
for f in $(find "/var/www/html/G7 4D THESIS/TITLE 1" -name ".htaccess" -maxdepth 3 2>/dev/null); do
  echo "== $f =="
  cat "$f"
done
echo "--- php ini values ---"
php -i 2>/dev/null | grep -i "output_buffering\|implicit_flush\|output_handler" | head -5