#!/bin/bash
echo "== body checks =="
grep -o 'Institution(s)\|New institution\|Owner Overview\|Page via Extensionless\|update\|san-lorenzo\|Warning' /tmp/b.html | sort | uniq -c | head
echo "== first 600 chars =="
head -c 600 /tmp/b.html
echo
echo "== db state =="
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT id,name,folder_path,created_by FROM institutions" 2>/dev/null