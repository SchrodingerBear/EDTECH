#!/bin/bash
echo "== re-test dbg2 structure via real accounts POST =="
bash "/var/www/html/G7 4D THESIS/TITLE 1/tools/test_assign.sh" 2>&1
echo "== dbg4 headers_sent sanity =="
B="http://localhost/G7%204D%20THESIS/TITLE%201"
cat > /tmp/hdr.php <<'PHPEOF'
<?php
require_once "/var/www/html/G7 4D THESIS/TITLE 1/includes/auth.php";
error_log("P5 wan0 ob=" . ob_get_level() . " hs=" . (headers_sent() ? 'T' : 'F'));
require_once "/var/www/html/G7 4D THESIS/TITLE 1/admin/layout/header.php";
error_log("P5 wan1 ob=" . ob_get_level() . " hs=" . (headers_sent() ? 'T' : 'F'));
header('Location: http://localhost/x');
exit;
PHPEOF
sudo -n cp /tmp/hdr.php "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_h.php" 2>/dev/null || cp /tmp/hdr.php "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_h.php"
curl -s -D /tmp/p5.h -o /dev/null -c /tmp/ow.c -b /tmp/ow.c "$B/admin/owner/_h.php"
echo "status: $(head -1 /tmp/p5.h)"
rm -f "/var/www/html/G7 4D THESIS/TITLE 1/admin/owner/_h.php"