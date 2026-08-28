#!/bin/bash
grep -o "account created\|already in use\|Unsupported role\|Only the owner[^<]*" /tmp/bc.html | head -3
echo "---AID NOW---"
mysql -uroot -pinnovatechph innovatech_campus -N -e "SELECT id,email,role_id,institution_id,deleted_at FROM users WHERE email LIKE 'juan%'" 2>/dev/null
echo "---full hdr---"
head -12 /tmp/hc.txt
echo "---flash in body---"
grep -o 'flash-data">[^<]*' /tmp/bc.html | head -1
echo "---body has accounts page?---"
grep -c "Admins, staff and system accounts\|acct-row\|Accounts" /tmp/bc.html