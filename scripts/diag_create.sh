#!/bin/bash
# cleanup stray test institutions (keep id 1 ICC + its surviving folder)
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM institutions WHERE id IN (2,3,4)" 2>/dev/null
echo "cleaned"

B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/ow.c
curl -s -c /tmp/ow.c -b /tmp/ow.c -d "form=login&email=owner@innovatech.ph&password=password" -o /dev/null "$B/admin/index"

echo "== create with assign=0 =="
curl -s -c /tmp/ow.c -b /tmp/ow.c \
  --data-urlencode "inst_action=create" \
  --data-urlencode "name=San Lorenzo University" \
  --data-urlencode "short_name=SLU" \
  --data-urlencode "institution_type=university" \
  --data-urlencode "city=Quezon City" \
  --data-urlencode "landing_mode=360_rotation" \
  --data-urlencode "assign_admin_id=0" \
  -D /tmp/h.txt -o /tmp/b.html "$B/admin/owner/institutions"
head -1 /tmp/h.txt
grep -io "create failed[^<]*\|institution created[^<]*" /tmp/b.html | head -2
echo "== orgs dir =="
ls "/var/www/html/G7 4D THESIS/TITLE 1/organizations/" 2>/dev/null