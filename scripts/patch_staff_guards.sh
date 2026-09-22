#!/bin/bash
# Add require_page() guards to staff pages
cd "/var/www/html/G7 4D THESIS/TITLE 1" || exit 1

patch() {
  local f=$1 key=$2
  if grep -q "require_page('$key');" "$f"; then
    echo "skip $f"
    return
  fi
  sed -i "0,/require_admin_staff();/s//require_admin_staff();\nrequire_page('$key');/" "$f"
  echo "patched $f -> $key"
}

patch admin/staff/ai-info.php    staff.aiinfo
patch admin/staff/ai-stitch.php  staff.aistitch
patch admin/staff/facilities.php staff.facilities
patch admin/staff/floor-plans.php staff.floorplans
patch admin/staff/uploads.php    staff.uploads

grep -rn "require_page" admin/staff/*.php