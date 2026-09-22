#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1" || exit 1
for f in $(find admin -name '*.php' | sort); do
  if grep -q 'REQUEST_METHOD.*POST\|$_POST' "$f"; then
    post_line=$(grep -n 'REQUEST_METHOD' "$f" | head -1 | cut -d: -f1)
    head_line=$(grep -n 'layout/header.php' "$f" | head -1 | cut -d: -f1)
    echo "$f  POST@$post_line  HEADER@$head_line"
  fi
done