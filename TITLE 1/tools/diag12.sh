#!/bin/bash
curl -s -D /tmp/m.h -o /tmp/m.html "http://localhost/G7%204D%20THESIS/TITLE%201/admin/owner/_mini.php"
echo "== status =="; head -1 /tmp/m.h; grep -i "^location" /tmp/m.h | head -1
echo "== body =="; cat /tmp/m.html; echo
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_mini.php"