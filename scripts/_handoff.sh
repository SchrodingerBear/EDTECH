#!/bin/bash
cd "/var/www/html/G7 4D THESIS/TITLE 1"
echo "== 1) Files still using raw \$pdo (NOT refactored to crud) =="
grep -rln '\$pdo' admin/ database/ 2>/dev/null | sort
echo ""
echo "== 2) database/seed-demo.php headers =="
head -12 database/seed-demo.php
echo ""
echo "== 3) CodeMirror / JSON-CSS editors references =="
grep -rln -i 'codemirror' admin/ includes/ 2>/dev/null | sort
echo ""
echo "== 4) tools/ leftover diagnostics count =="
ls tools/*.sh | wc -l
ls tools/*.php 2>/dev/null | wc -l
echo ""
echo "== 5) node binary available? =="
which node || echo "NO NODE"
echo ""
echo "== 6) old/ or manager/ dirs present =="
ls -d old manager 2>/dev/null || echo "none/not at root"
echo ""
echo "== 7) icon() definition location (dup check) =="
grep -rn 'function icon' includes/ admin/assets/*.php 2>/dev/null | head