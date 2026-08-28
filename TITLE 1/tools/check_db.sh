#!/bin/bash
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT id,slug,name,short_name,institution_type,folder_path,landing_mode FROM institutions" 2>/dev/null
echo "--- dirs ---"
ls "/var/www/html/G7 4D THESIS/TITLE 1/organizations/" 2>/dev/null
echo "--- users ---"
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT u.id,u.email,r.slug AS role,u.institution_id FROM users u JOIN roles r ON r.id=u.role_id" 2>/dev/null