#!/bin/bash
curl -s -c /tmp/ow.c -b /tmp/ow.c -X POST -d "x=1" -o /tmp/dbg.html "http://localhost/G7%204D%20THESIS/TITLE%201/admin/owner/_dbg.php"
echo "== body ends =="; tail -c 80 /tmp/dbg.html; echo
echo "== dbg log =="; tail -6 /var/log/apache2/error.log 2>/dev/null | grep "DBG"
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_dbg.php"