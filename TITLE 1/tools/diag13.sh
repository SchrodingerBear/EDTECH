#!/bin/bash
curl -s -c /tmp/ow.c -b /tmp/ow.c -X POST -D /tmp/d3.h -o /tmp/d3.html -d "x=1" "http://localhost/G7%204D%20THESIS/TITLE%201/admin/owner/_dbg3.php"
echo "== status =="; head -1 /tmp/d3.h; grep -i "^location" /tmp/d3.h | head -1
echo "== body size =="; wc -c < /tmp/d3.html
echo "== log =="; tail -3 /var/log/apache2/error.log 2>/dev/null | grep "DBG3"
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_dbg3.php"