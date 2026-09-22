#!/bin/bash
echo "== body of acc-create response =="
grep -o 'alert-danger[^<]*\|alert-success[^<]*' /tmp/bc.html | head -3
echo "== raw flash =="
grep -o '"msg":"[^"]*"\|"type":"[^"]*"' /tmp/bc.html | head -4
echo "== first status =="
head -8 /tmp/hc.txt
echo "== tail =="
tail -c 300 /tmp/bc.html