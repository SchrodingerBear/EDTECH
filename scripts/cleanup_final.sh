#!/bin/bash
curl -s "http://localhost/G7%204D%20THESIS/TITLE%201/tools/_clean_final.php"
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/tools/_clean_final.php"
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM institutions WHERE id>1" 2>/dev/null
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM users WHERE email='marco.rodriguez@innovatech.ph'" 2>/dev/null
echo "--- db institutions ---"
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT id,name FROM institutions" 2>/dev/null
echo "--- folders ---"
ls "/var/www/html/G7 4D THESIS/TITLE 1/organizations/"