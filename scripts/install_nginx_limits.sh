#!/bin/bash
# Per-address rate limits for MOOP, and robots.txt at the site root.
#
# Crawlers and AI scrapers request every URL they can find; each gene page is several SQLite
# queries and each export far more. These nginx limits cap what one address can take, by kind
# of request (pages, AJAX, genome browser, exports/downloads). Internal/VPN/institute addresses —
# MOOP's auto_login_ip_ranges — are never limited. Numbers and reasoning:
# docs/nginx/moop-ratelimit.conf. Plan: notes/BOT_PROTECTION_PLAN.md.
#
# Installs:
#   /etc/nginx/conf.d/moop-ratelimit.conf     zones, request classes, exempt ranges (http context),
#                                             generated from docs/nginx/moop-ratelimit-http.conf
#                                             + the site config's auto_login_ip_ranges
#   /etc/nginx/default.d/moop-ratelimit.conf  the limits + /robots.txt (server context, both
#                                             the http and https server blocks include default.d)
# then `nginx -t`; reloads only if that passes, otherwise puts the previous files back.
#
# Re-run after changing the IP ranges in Site Configuration — the exempt list is copied here,
# not read live. Remove with: sudo scripts/install_nginx_limits.sh --remove
#
# Usage: sudo scripts/install_nginx_limits.sh [--remove]
set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "Run as root: sudo $0" >&2; exit 1; }

MOOP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTTP_DST=/etc/nginx/conf.d/moop-ratelimit.conf
SRV_DST=/etc/nginx/default.d/moop-ratelimit.conf
STAMP=$(date +%Y%m%d-%H%M%S)

backup() { [ -f "$1" ] && cp -p "$1" "$1.bak-$STAMP" || true; }
restore() {   # put back what was there before this run (or remove what this run added)
    for f in "$HTTP_DST" "$SRV_DST"; do
        if [ -f "$f.bak-$STAMP" ]; then mv -f "$f.bak-$STAMP" "$f"; else rm -f "$f"; fi
    done
}
reload_or_restore() {
    if nginx -t; then
        systemctl reload nginx
    else
        echo "nginx -t FAILED — previous configuration restored, nothing reloaded." >&2
        restore
        nginx -t >/dev/null 2>&1 && systemctl reload nginx
        exit 1
    fi
}

if [ "${1:-}" = "--remove" ]; then
    backup "$HTTP_DST"; backup "$SRV_DST"
    rm -f "$HTTP_DST" "$SRV_DST"
    reload_or_restore
    echo "MOOP rate limits removed (backups: *.bak-$STAMP)."
    exit 0
fi

# Exempt ranges, from the site config — the same addresses MOOP auto-logs-in.
RANGES=$(php -r '
    require $argv[1] . "/includes/config_init.php";
    foreach (ConfigManager::getInstance()->getArray("auto_login_ip_ranges", []) as $r) {
        $s = $r["start"] ?? ""; $e = $r["end"] ?? "";
        if (filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($e, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ip2long($s) <= ip2long($e)) {
            echo "    $s-$e 1;\n";
        } else {
            fwrite(STDERR, "skipping invalid range: " . json_encode($r) . "\n");
        }
    }
' "$MOOP")
[ -n "$RANGES" ] || echo "Note: no auto_login_ip_ranges configured — every address is limited."

TMP=$(mktemp)
trap 'rm -f "$TMP"' EXIT
# Replace the placeholder line with the ranges (awk, so dots and dashes need no escaping).
awk -v ranges="$RANGES" '/^[ \t]*@EXEMPT_RANGES@[ \t]*$/ { if (ranges != "") print ranges; next } { print }' \
    "$MOOP/docs/nginx/moop-ratelimit-http.conf" > "$TMP"

backup "$HTTP_DST"; backup "$SRV_DST"
install -m 644 "$TMP" "$HTTP_DST"
install -m 644 "$MOOP/docs/nginx/moop-ratelimit.conf" "$SRV_DST"
command -v restorecon >/dev/null && restorecon "$HTTP_DST" "$SRV_DST" || true

reload_or_restore

echo "MOOP rate limits installed."
echo "Exempt (never limited):"; echo "${RANGES:-    (none)}"
[ -f "$HTTP_DST.bak-$STAMP" ] && echo "Previous files kept as *.bak-$STAMP"
sleep 1
printf 'robots.txt: '; curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/robots.txt
