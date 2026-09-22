#!/bin/bash
mysql -uroot -pinnovatechph innovatech_campus -e "SHOW CREATE TABLE users\G" 2>/dev/null | grep -i "institution" | head -8