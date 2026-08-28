#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1"
echo "== who requires which includes =="
grep -rn --include='*.php' -E "require.*includes/(config|db|functions|auth|db_functions)\.php" -- . | grep -v '/tools/' | grep -v '^./includes/'
echo ""
echo "== templates that include config/db/functions (org_pack, index, landing) =="
grep -rln --include='*.php' -E "includes/(config|db|functions|auth)" templates 2>/dev/null
echo ""
echo "== directories =="
ls includes/