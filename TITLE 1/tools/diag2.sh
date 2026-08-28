#!/bin/bash
grep -n "ROOT_PATH\|BASE_URL" "/var/www/html/G7 4D THESIS/TITLE 1/includes/config.php" | head
echo "--- errors ---"
tail -40 /var/log/apache2/error.log | grep -i "institution" | tail -8
echo "--- audit ---"
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT id,action,entity_id,created_at FROM audit_logs ORDER BY id DESC LIMIT 5" 2>/dev/null
echo "--- org perms ---"
ls -la "/var/www/html/G7 4D THESIS/TITLE 1/organizations/" 2>/dev/null