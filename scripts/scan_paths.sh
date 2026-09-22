#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1"
grep -rnoE '(href|src|action)="/[a-zA-Z]' admin/ includes/ 2>/dev/null | grep -v "cdn.jsdelivr\|fonts.googleapis\|cdnjs.cloudflare" | head -60
echo "--- url( calls with leading / ---"
grep -rn 'url(' admin/ includes/ 2>/dev/null | grep -v "org_url" | head -10
echo "--- BASE_URL defined? ---"
grep -rn "BASE_URL" includes/config.php | head -5