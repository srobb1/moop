#!/bin/bash
# Send http:// visitors to https:// (nginx). Run once HTTPS works at the site's NAME.
#
# Until this is in place, logins and session cookies can cross the network in cleartext: the
# session cookie is only marked Secure on an https request (includes/session_init.php), and
# http:// still serves every page. With it, every browser request ends up on https.
#
# Adds a `return 301 https://<hostname>$request_uri` to nginx's PORT-80 server block. Redirects
# go to the NAME, not to whatever Host was asked for: someone who typed the IP would otherwise
# land on https://<ip> and a certificate warning (the certificate covers names, not addresses).
#
# This machine itself stays on http (127.0.0.1, ::1 and its own addresses): the System Monitor's
# php-fpm status check, render_compare and the page audit call http://127.0.0.1/ directly and
# would otherwise get a 301 instead of the page.
#
# Prerequisites: https://<hostname>/ answers with a valid certificate (check first:
#   curl -sI https://<hostname>/ ), and the tracks server, if any, allows https://<hostname> (CORS).
# Safe to re-run: an existing MOOP redirect is replaced. Backs up nginx.conf, tests the config,
# reloads only if the test passes, and restores the backup if it fails.
#
# Usage: sudo scripts/install_https_redirect.sh <hostname>      e.g. simrbase-dev.stowers.org
# Undo:  restore the printed backup (or delete the lines between the MOOP markers), nginx -t, reload.
# Verify from this machine (its own addresses are exempt): use a second loopback address --
#   curl -sI --interface 127.0.0.2 -H 'Host: <hostname>' http://127.0.0.1/ | head -3     -> 301
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run with sudo." >&2; exit 1; }
HOST=${1:-}
[[ "$HOST" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] || { echo "Usage: sudo $0 <hostname>" >&2; exit 1; }
CONF=${NGINX_CONF:-/etc/nginx/nginx.conf}
[ -f "$CONF" ] || { echo "$CONF not found (nginx only; set NGINX_CONF for another path)." >&2; exit 1; }

# Exempt: loopback + every address this machine has
EXEMPT="127.0.0.1 ::1 $(hostname -I 2>/dev/null || true)"
BLOCK="        # >>> MOOP https redirect (scripts/install_https_redirect.sh) >>>
        # Browsers -> HTTPS. Requests from this machine itself stay on http (System Monitor
        # fpm-status, render_compare and the page audit call http://127.0.0.1 directly).
        set \$to_https 1;"
for ip in $EXEMPT; do
    BLOCK="$BLOCK
        if (\$remote_addr = $ip) { set \$to_https 0; }"
done
BLOCK="$BLOCK
        if (\$to_https) { return 301 https://$HOST\$request_uri; }
        # <<< MOOP https redirect <<<"

BAK="$CONF.bak.$(date +%Y%m%d-%H%M%S)"
cp -p "$CONF" "$BAK"
echo "backup: $BAK"

# Drop an earlier MOOP block (marked, or the unmarked first version), then insert after the
# first 'root ...;' line of the first server block that listens on port 80.
BLOCK="$BLOCK" awk '
  /# >>> MOOP https redirect/ { skip = 1 }
  skip { if (/# <<< MOOP https redirect/) skip = 0; next }
  /# Browsers -> HTTPS\. Requests from this machine itself/ { old = 1; next }
  old && /Monitor fpm-status|set \$to_https|\$remote_addr = .*to_https|if \(\$to_https\)/ { next }
  { old = 0 }
  /listen[ \t]+80;/ { in80 = 1 }
  { print }
  in80 && !done && /^[ \t]*root[ \t]+[^;]+;/ { print ENVIRON["BLOCK"]; done = 1 }
  END { if (!done) exit 3 }
' "$BAK" > "$CONF.new" || { rm -f "$CONF.new"; echo "No port-80 server block with a root line found -- nothing changed." >&2; exit 1; }

cat "$CONF.new" > "$CONF"   # keeps the file's owner, mode and SELinux label
rm -f "$CONF.new"

if nginx -t; then
    systemctl reload nginx
    echo "nginx reloaded: http:// now redirects to https://$HOST (exempt: $EXEMPT)"
    diff "$BAK" "$CONF" || true
else
    cp -p "$BAK" "$CONF"
    echo "nginx config test FAILED -- restored $BAK, nothing reloaded." >&2
    exit 1
fi
