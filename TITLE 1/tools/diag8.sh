#!/bin/bash
export ACCT_DEBUG=1
B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/j.html /tmp/j.h
curl -s -c /tmp/ow.c -b /tmp/ow.c -D /tmp/j.h -o /tmp/j.html \
  --data-urlencode "acc_action=create" \
  --data-urlencode "role=admin" \
  --data-urlencode "institution_id=" \
  --data-urlencode "first_name=Juan" \
  --data-urlencode "last_name=Dela Cruz" \
  --data-urlencode "email=juan.admin@innovatech.ph" \
  --data-urlencode "password=password" \
  "$B/admin/owner/accounts"
head -1 /tmp/j.h
echo "== errlog =="
tail -8 /var/log/apache2/error.log 2>/dev/null | grep "ACCT_DEBUG\|Warning\|Fatal" | tail -6