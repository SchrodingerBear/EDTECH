#!/bin/bash
ROOT="/var/www/html/G7 4D THESIS/TITLE 1"
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM institutions WHERE id>1" 2>/dev/null
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM users WHERE email='marco.rodriguez@innovatech.ph'" 2>/dev/null
chmod -R a+rwX "$ROOT/organizations" 2>&1 | head -5
for d in "$ROOT/organizations/"*; do
  [ "$d" = "$ROOT/organizations/immaculada-concepcion-college" ] && continue
  echo "rm $d => $(rm -rf "$d" 2>&1 && echo OK || echo FAIL)"
done
echo "== folders now =="
ls "$ROOT/organizations/"