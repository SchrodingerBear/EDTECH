#!/bin/bash
B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/ow.c /tmp/f.html
curl -s -c /tmp/ow.c -b /tmp/ow.c -d "form=login&email=owner@innovatech.ph&password=password" -o /dev/null "$B/admin/index"
# follow the redirect to see the flash
curl -s -c /tmp/ow.c -b /tmp/ow.c -L -o /tmp/f.html \
  --data-urlencode "acc_action=create" \
  --data-urlencode "role=admin" \
  --data-urlencode "institution_id=" \
  --data-urlencode "first_name=Juan" \
  --data-urlencode "last_name=Dela Cruz" \
  --data-urlencode "email=juan.admin@innovatech.ph" \
  --data-urlencode "password=password" \
  "$B/admin/owner/accounts"
echo "== flash =="; grep -o '"msg":"[^"]*"\|"type":"[^"]*"' /tmp/f.html | head -4
echo "== alert =="; grep -o 'alert-warning[^<]*\|alert-danger[^<]*\|alert-success[^<]*' /tmp/f.html | head -3
echo "== users =="; mysql -uroot -pinnovatechph innovatech_campus -e "SELECT id,email,role_id,institution_id,first_name,last_name,is_active FROM users WHERE email LIKE 'juan%' OR email LIKE '%dela cruz%'" 2>/dev/null
echo "== any users with null inst =="; mysql -uroot -pinnovatechph innovatech_campus -e "SELECT id,email,role_id,institution_id FROM users WHERE institution_id IS NULL AND role_id IN (2,3)" 2>/dev/null