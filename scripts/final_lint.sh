#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1"
echo "== PHP lint =="
ok=1
for f in includes/functions.php admin/owner/accounts.php admin/owner/institutions.php admin/institution/settings.php admin/layout/header.php admin/layout/nav.php admin/index.php admin/reset-password.php admin/profile.php admin/owner/settings.php; do
  out=$(php -l "$f" 2>&1)
  if echo "$out" | grep -q "No syntax errors"; then echo "  ok  $f"; else echo "  FAIL $f"; echo "$out"; ok=0; fi
done
echo "== JS syntax (node) =="
node --check admin/assets/js/dashboard.js && echo "  ok  dashboard.js" || echo "  FAIL dashboard.js"
echo "done ok=$ok"