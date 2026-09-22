#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1" || exit 1
for f in admin/institution/ai.php admin/institution/tours.php admin/institution/floor-plans.php admin/institution/settings.php admin/institution/locations.php admin/institution/files.php; do
  sed -i 's/current_institution()/resolve_active_institution()/g' "$f"
done
grep -rn 'current_institution\|resolve_active_institution' admin/institution/*.php