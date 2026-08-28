#!/bin/bash
B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/ow.c
curl -s -c /tmp/ow.c -b /tmp/ow.c -d "form=login&email=owner@innovatech.ph&password=password" -o /dev/null "$B/admin/index"
echo "== POST settings (SMTP save) =="
curl -s -c /tmp/ow.c -b /tmp/ow.c \
  --data-urlencode "smtp_host=smtp.test.com" \
  --data-urlencode "smtp_port=587" \
  --data-urlencode "smtp_username=u" \
  --data-urlencode "smtp_password=" \
  --data-urlencode "smtp_from_name=Innovatech" \
  --data-urlencode "smtp_from_email=hello@innovatech.ph" \
  -D /tmp/h2.txt -o /tmp/b2.html -w "%{http_code}\n" "$B/admin/owner/settings"
head -1 /tmp/h2.txt
echo "== db smtp_host =="
mysql -uroot -pinnovatechph innovatech_campus -e "SELECT smtp_host FROM platform_settings WHERE id=1" 2>/dev/null