#!/bin/bash
curl -s -c /tmp/ow.c -b /tmp/ow.c -D /tmp/d4.h -o /tmp/d4.html "http://localhost/G7%204D%20THESIS/TITLE%201/admin/owner/_dbg4.php"
echo "== status =="; head -1 /tmp/d4.h
echo "== log =="; tail -3 /var/log/apache2/error.log 2>/dev/null | grep "DBG4"
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_dbg4.php"