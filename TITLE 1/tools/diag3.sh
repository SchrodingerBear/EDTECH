#!/bin/bash
head -12 /tmp/h.txt
echo "--- flash ---"
grep -o 'flash-data">[^<]*' /tmp/b.html | head -1
echo "--- title ---"
grep -o '<h1[^>]*>[^<]*' /tmp/b.html | head -1
echo "--- location headers ---"
grep -i location /tmp/h.txt