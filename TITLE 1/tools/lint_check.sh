#!/bin/bash
echo "== php -l =="
for f in \
  "admin/owner/accounts.php" \
  "admin/owner/institutions.php" \
  "admin/help/index.php" \
  "includes/config.php" ; do
  php -l "/var/www/html/G7 4D THESIS/TITLE 1/$f" 2>&1 | grep -v "^$" | head -1
done