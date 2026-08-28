#!/bin/bash
B="http://localhost/G7%204D%20THESIS/TITLE%201"
PASS=0; FAIL=0; FAILED=""
note() { if [ "$1" = "ok" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); FAILED="$FAILED\n  - $2"; fi; }

# 1. login as each role
login() { # email -> returns cookie code
  local c=$(mktemp); local code=$(curl -s -o /dev/null -w "%{http_code}" -c "$c" "$B/admin/index" -d "form=login&email=$1&password=password")
  echo "$code $c"
}
for pair in "owner@innovatech.ph:owner/dashboard" "system.admin@innovatech.ph:system/dashboard" "admin@innovatech.ph:institution/dashboard" "staff@innovatech.ph:staff/dashboard"; do
  email="${pair%%:*}"; dest="${pair##*:}"
  out=$(login "$email"); code="${out%% *}"; c="${out#* }"
  [ "$code" = "302" ] && note ok "login $email" || note fail "login $email got $code"
  code2=$(curl -s -o /dev/null -w "%{http_code}" -b "$c" "$B/admin/$dest")
  [ "$code2" = "200" ] && note ok "dashboard $email" || note fail "dashboard $email got $code2"
  rm -f "$c"
done

# 2. owner pages reachable
c=$(mktemp); curl -s -o /dev/null -c "$c" -d "form=login&email=owner@innovatech.ph&password=password" "$B/admin/index"
for p in owner/accounts owner/institutions owner/emails owner/settings owner/website-settings owner/landing; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -b "$c" "$B/admin/$p")
  [ "$code" = "200" ] && note ok "page $p" || note fail "page $p got $code"
done
code=$(curl -s -o /dev/null -w "%{http_code}" -b "$c" "$B/admin/help/")
[ "$code" = "200" ] && note ok "page admin/help/" || note fail "page admin/help/ got $code"

# profile shared by all roles (login + eye toggles must render)
for email in "owner@innovatech.ph" "admin@innovatech.ph" "staff@innovatech.ph"; do
  ck=$(mktemp); curl -s -o /dev/null -c "$ck" -d "form=login&email=$email&password=password" "$B/admin/index"
  code=$(curl -s -o /dev/null -w "%{http_code}" -b "$ck" "$B/admin/profile")
  [ "$code" = "200" ] && note ok "profile $email" || note fail "profile $email got $code"
  eyes=$(curl -s -b "$ck" "$B/admin/profile" | grep -c 'data-pw=')
  [ "$eyes" = "3" ] && note ok "profile eyes $email" || note fail "profile eyes $email count=$eyes"
  rm -f "$ck"
done

# 3. sidebar links use project-relative base
nav=$(curl -s -b "$c" "$B/admin/owner/dashboard")
echo "$nav" | grep -q 'href="http://localhost/G7 4D THESIS/TITLE 1/admin/owner/accounts"' && note ok "sidebar prefixed" || note fail "sidebar not prefixed"
echo "$nav" | grep -q 'href="/admin/' && note fail "raw /admin link" || note ok "no raw /admin links"
rm -f "$c"

# 4. public landing + root
code=$(curl -s -o /dev/null -w "%{http_code}" "$B/organizations/immaculada-concepcion-college/index.html")
[ "$code" = "200" ] && note ok "public landing" || note fail "public landing got $code"
code=$(curl -s -o /dev/null -w "%{http_code}" "$B/index.php")
[ "$code" = "200" ] && note ok "public root" || note fail "public root got $code"

echo ""
echo "PASS=$PASS FAIL=$FAIL"
[ -n "$FAILED" ] && echo -e "FAILED:$FAILED"
exit $FAIL