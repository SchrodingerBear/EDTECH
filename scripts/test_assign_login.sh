#!/bin/bash
B="http://localhost/G7%204D%20THESIS/TITLE%201"
rm -f /tmp/mr.c
# login as the assigned San Lorenzo admin
curl -s -c /tmp/mr.c -b /tmp/mr.c -D /tmp/mr.h -o /tmp/mr.html \
  -d "form=login&email=marco.rodriguez@innovatech.ph&password=password" "$B/admin/index"
echo "== login status =="; head -1 /tmp/mr.h; grep -i "^location" /tmp/mr.h
# fetch dashboard (follow redirect)
curl -s -c /tmp/mr.c -b /tmp/mr.c -o /tmp/mr2.html "$B/admin/institution/dashboard"
echo "== dashboard title =="; grep -o '<title>[^<]*' /tmp/mr2.html
echo "== institution context =="; grep -o 'San Lorenzo University\|Immaculada Concepcion College\|LIVE' /tmp/mr2.html | sort -u | head -5
echo "== role badge =="; grep -o 'Admin[^<]*workspace\|>Admin<' /tmp/mr2.html | head -2