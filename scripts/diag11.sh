#!/bin/bash
curl -s -c /tmp/ow.c -b /tmp/ow.c -X POST -D /tmp/dbg2.h -o /tmp/dbg2.html -d "x=1" "http://localhost/G7%204D%20THESIS/TITLE%201/admin/owner/_dbg2.php"
echo "== status =="; head -1 /tmp/dbg2.h; grep -i "^location" /tmp/dbg2.h | head -1
echo "== body size =="; wc -c < /tmp/dbg2.html
echo "== tail =="; tail -c 100 /tmp/dbg2.html; echo
echo "== log =="; tail -4 /var/log/apache2/error.log 2>/dev/null | grep "DBG2"
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_dbg2.php"