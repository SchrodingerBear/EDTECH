#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1" || exit 1

patch() {
  local f=$1 key=$2
  if grep -q "require_page('$key');" "$f"; then
    echo "skip $f"
    return
  fi
  sed -i "0,/require_admin();/s//require_admin();\nrequire_page('$key');/" "$f"
  echo "patched $f -> $key"
}

patch admin/institution/locations.php  admin.locations
patch admin/institution/tours.php      admin.tours
patch admin/institution/floor-plans.php admin.floorplans
patch admin/institution/settings.php   admin.settings
patch admin/institution/ai.php         admin.ai
patch admin/institution/files.php      admin.files

grep -rn "require_page" admin/institution/*.php