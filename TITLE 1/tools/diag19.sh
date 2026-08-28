#!/bin/bash
grep -o 'flash-data">.*' /tmp/f.html | head -c 500
echo
echo "== search msg =="
python3 - <<'PY' 2>/dev/null || echo "no python"
import re
h=open('/tmp/f.html').read()
m=re.search(r'flash-data">(.*)</script>',h,re.S)
print("FLASH JSON:", m.group(1) if m else "NONE")
PY
echo "== full body tail =="
tail -c 400 /tmp/f.html