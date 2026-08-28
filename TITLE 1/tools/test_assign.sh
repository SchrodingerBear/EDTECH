#!/bin/bash
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM institutions WHERE id>1" 2>/dev/null
mysql -uroot -pinnovatechph innovatech_campus -e "DELETE FROM users WHERE email='marco.rodriguez@innovatech.ph'" 2>/dev/null
chmod -R a+rwX "/var/www/html/G7 4D THESIS/TITLE 1/organizations" 2>/dev/null
for d in "/var/www/html/G7 4D THESIS/TITLE 1/organizations/"*; do
  [ "$d" = "/var/www/html/G7 4D THESIS/TITLE 1/organizations/immaculada-concepcion-college" ] && continue
  rm -rf "$d" 2>/dev/null
done
echo "cleaned fresh"

B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/ow.c
curl -s -c /tmp/ow.c -b /tmp/ow.c -d "form=login&email=owner@innovatech.ph&password=password" -o /dev/null "$B/admin/index"

# create an unassigned org admin account
curl -s -c /tmp/ow.c -b /tmp/ow.c -D /tmp/hc.txt -o /tmp/bc.html \
  --data-urlencode "acc_action=create" \
  --data-urlencode "role=admin" \
  --data-urlencode "institution_id=" \
  --data-urlencode "first_name=Marco" \
  --data-urlencode "last_name=Rodriguez" \
  --data-urlencode "email=marco.rodriguez@innovatech.ph" \
  --data-urlencode "password=password" \
  "$B/admin/owner/accounts"
echo "acc-create: $(head -1 /tmp/hc.txt)"

AID=$(mysql -uroot -pinnovatechph innovatech_campus -N -e "SELECT id FROM users WHERE email='marco.rodriguez@innovatech.ph'" 2>/dev/null)
echo "new admin id=$AID"

# create institution and assign that admin
curl -s -c /tmp/ow.c -b /tmp/ow.c -D /tmp/hi.txt -o /dev/null \
  --data-urlencode "inst_action=create" \
  --data-urlencode "name=San Lorenzo University" \
  --data-urlencode "short_name=SLU" \
  --data-urlencode "institution_type=university" \
  --data-urlencode "city=Quezon City" \
  --data-urlencode "landing_mode=360_rotation" \
  --data-urlencode "assign_admin_id=$AID" \
  "$B/admin/owner/institutions"
echo "inst-create: $(head -1 /tmp/hi.txt)"

echo "== db state =="
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT i.id,i.name,i.folder_path,u.email,r.slug AS role,u.institution_id FROM institutions i LEFT JOIN users u ON u.id=$AID LEFT JOIN roles r ON r.id=u.role_id WHERE i.id>1" 2>/dev/null
echo "== folders =="
ls "/var/www/html/G7 4D THESIS/TITLE 1/organizations/" 2>/dev/null