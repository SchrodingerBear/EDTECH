#!/bin/bash
B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/ow.c /tmp/j.html /tmp/j.h
curl -s -c /tmp/ow.c -b /tmp/ow.c -d "form=login&email=owner@innovatech.ph&password=password" -o /dev/null "$B/admin/index"
curl -s -c /tmp/ow.c -b /tmp/ow.c -D /tmp/j.h -o /tmp/j.html \
  --data-urlencode "acc_action=create" \
  --data-urlencode "role=admin" \
  --data-urlencode "institution_id=" \
  --data-urlencode "first_name=Juan" \
  --data-urlencode "last_name=Dela Cruz" \
  --data-urlencode "email=juan.admin@innovatech.ph" \
  --data-urlencode "password=password" \
  "$B/admin/owner/accounts"
echo "== status/h =="; head -1 /tmp/j.h; grep -i "^location" /tmp/j.h
echo "== body size =="; wc -c < /tmp/j.html
echo "== end of body =="; tail -c 120 /tmp/j.html
echo "== grep flash/alert =="; grep -o 'alert-danger[^<]*\|alert-success[^<]*\|account created\|already in use' /tmp/j.html | head -4
echo "== user =="; mysql -uroot -pinnovatechph innovatech_campus -N -e "SELECT id,email,role_id,institution_id,deleted_at FROM users WHERE email='juan.admin@innovatech.ph'" 2>/dev/null
echo "== apache err =="; tail -5 /var/log/apache2/error.log 2>/dev/null | grep -v "^$"