#!/bin/bash
# MOOP System Monitor — the root collector, run once a minute by a systemd timer.
# Plan: notes/SYSTEM_MONITOR_PLAN.md. Collector: scripts/moop_monitor.py (read its header).
#
# Installs:
#   /usr/local/sbin/moop-monitor                     the collector (copy of scripts/moop_monitor.py)
#   /usr/local/share/moop-monitor/*.conf             root-owned copies of the nginx rate-limit
#                                                    templates (never read from the repo at run
#                                                    time: the web group can write docs/)
#   /etc/moop-monitor.conf                           CACHE= and SITE= for the collector
#   /etc/systemd/system/moop-monitor.{service,timer} every minute
#   /var/lib/moop-monitor/                           the collector's state (root only)
#   php-fpm status page: pm.status_path = /moop-fpm-status in /etc/php-fpm.d/www.conf, and
#   /etc/nginx/default.d/moop-monitor.conf exposing it to 127.0.0.1 ONLY
#   /etc/nginx/default.d/moop-headers.conf           security headers + the enforced CSP, from
#                                                    docs/nginx/moop-headers.conf -- ONLY if nginx
#                                                    does not already set them (hosts set up before
#                                                    2026-10-28 have them in nginx.conf)
#   the rate limits + robots.txt (install_nginx_limits.sh), if not installed yet
#
# This is the ONE command for the whole "protect and watch the server" layer on a new install
# (README Quick Start step 7). Safe to re-run.
# Each config change is tested (php-fpm -t, nginx -t) and put back if the test fails.
#
# After this, the nginx rate-limit exemptions follow MOOP's auto-login IP ranges by themselves
# (MOOP publishes nginx_wanted.json; the collector applies it) — install_nginx_limits.sh is only
# needed for the very first install of the limits, which this script also does if missing.
#
# Re-run after pulling a new version of moop_monitor.py or the nginx templates.
# Remove: sudo scripts/install_monitor.sh --remove   (leaves the rate limits in place)
#
# Usage: sudo scripts/install_monitor.sh [--remove]
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Run as root: sudo $0" >&2; exit 1; }

SITE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="$(command -v php)"
STAMP=$(date +%Y%m%d-%H%M%S)
FPM_CONF=/etc/php-fpm.d/www.conf
NGX_STATUS=/etc/nginx/default.d/moop-monitor.conf
NGX_HEADERS=/etc/nginx/default.d/moop-headers.conf

if [ "${1:-}" = "--remove" ]; then
    systemctl disable --now moop-monitor.timer 2>/dev/null || true
    rm -f /etc/systemd/system/moop-monitor.service /etc/systemd/system/moop-monitor.timer
    systemctl daemon-reload
    rm -f /usr/local/sbin/moop-monitor /etc/moop-monitor.conf "$NGX_STATUS"   # headers stay
    rm -rf /usr/local/share/moop-monitor /var/lib/moop-monitor
    nginx -t && systemctl reload nginx
    echo "System Monitor collector removed. (php-fpm status_path left set; rate limits left in place.)"
    exit 0
fi

command -v python3 >/dev/null || { echo "python3 is required" >&2; exit 1; }

# The cache directory as MOOP itself resolves it (same as install_security_report.sh).
CACHE="$("$PHP" -r 'require $argv[1] . "/includes/config_init.php"; echo moop_cache_root();' "$SITE")"
[ -d "$CACHE" ] || { echo "Cache directory '$CACHE' does not exist" >&2; exit 1; }

echo "== collector"
install -D -m 755 -o root -g root "$SITE/scripts/moop_monitor.py" /usr/local/sbin/moop-monitor
install -d -m 755 /usr/local/share/moop-monitor
install -m 644 -o root -g root "$SITE/docs/nginx/moop-ratelimit-http.conf" /usr/local/share/moop-monitor/
install -m 644 -o root -g root "$SITE/docs/nginx/moop-ratelimit.conf" /usr/local/share/moop-monitor/
install -d -m 700 /var/lib/moop-monitor
printf 'CACHE=%s\nSITE=%s\n' "$CACHE" "$SITE" > /etc/moop-monitor.conf
chmod 644 /etc/moop-monitor.conf

echo "== php-fpm status page"
if grep -qE '^pm\.status_path\s*=\s*/moop-fpm-status\s*$' "$FPM_CONF"; then
    echo "already enabled"
elif grep -qE '^pm\.status_path\s*=' "$FPM_CONF"; then
    echo "pm.status_path is already set to something else in $FPM_CONF — leaving it; fix by hand:" >&2
    grep -nE '^pm\.status_path' "$FPM_CONF" >&2
    exit 1
else
    cp -p "$FPM_CONF" "$FPM_CONF.bak-$STAMP"
    sed -i -E 's|^;pm\.status_path = /status$|pm.status_path = /moop-fpm-status|' "$FPM_CONF"
    grep -qE '^pm\.status_path = /moop-fpm-status$' "$FPM_CONF" || echo 'pm.status_path = /moop-fpm-status' >> "$FPM_CONF"
    if php-fpm -t; then
        systemctl reload php-fpm
    else
        cp -p "$FPM_CONF.bak-$STAMP" "$FPM_CONF"; echo "php-fpm -t failed — restored" >&2; exit 1
    fi
fi

# Every nginx file this run writes is backed up first, and all are put back together if
# `nginx -t` fails at the end.
NGX_TOUCHED=()
ngx_backup() { [ -f "$1" ] && cp -p "$1" "$1.bak-$STAMP"; NGX_TOUCHED+=("$1"); }
ngx_restore() {
    for f in "${NGX_TOUCHED[@]}"; do
        if [ -f "$f.bak-$STAMP" ]; then mv -f "$f.bak-$STAMP" "$f"; else rm -f "$f"; fi
    done
}

ngx_backup "$NGX_STATUS"
cat > "$NGX_STATUS" <<'EOF'
# MOOP System Monitor: php-fpm's status page, for the root collector ONLY (127.0.0.1).
# Installed by scripts/install_monitor.sh. Not .php, so the rate limits do not count it.
location = /moop-fpm-status {
    allow 127.0.0.1;
    allow ::1;
    deny all;
    include fastcgi_params;
    fastcgi_param SCRIPT_NAME     /moop-fpm-status;
    fastcgi_param SCRIPT_FILENAME /moop-fpm-status;
    fastcgi_pass php-fpm;
}
EOF
restorecon "$NGX_STATUS" 2>/dev/null || true

echo "== security headers + CSP"
if grep -rqs --exclude=moop-headers.conf --exclude='*.bak-*' 'Content-Security-Policy' \
        /etc/nginx/nginx.conf /etc/nginx/conf.d /etc/nginx/default.d; then
    echo "already set elsewhere in nginx (nginx.conf on hosts set up before 2026-10-28) -- left alone"
else
    # The remote JBrowse tracks server, if configured: scheme://host[:port], nothing else.
    TRACKS_ORIGIN="$("$PHP" -r 'require $argv[1] . "/includes/config_init.php";
        $t = ConfigManager::getInstance()->getArray("tracks_server", []);
        $u = !empty($t["enabled"]) ? parse_url((string) ($t["url"] ?? "")) : false;
        if ($u && in_array($u["scheme"] ?? "", ["http", "https"], true) && preg_match("/^[A-Za-z0-9.-]+$/", $u["host"] ?? "")) {
            echo $u["scheme"] . "://" . $u["host"] . (isset($u["port"]) ? ":" . (int) $u["port"] : "");
        }' "$SITE")"
    ngx_backup "$NGX_HEADERS"
    sed "s|@TRACKS_ORIGIN@|$TRACKS_ORIGIN|" "$SITE/docs/nginx/moop-headers.conf" > "$NGX_HEADERS"
    chmod 644 "$NGX_HEADERS"; restorecon "$NGX_HEADERS" 2>/dev/null || true
    echo "installed (tracks server allowed for JBrowse: ${TRACKS_ORIGIN:-none configured})"
fi

# Rate limits never installed? Install them now (the collector only keeps them in step).
if [ ! -f /etc/nginx/conf.d/moop-ratelimit.conf ]; then
    echo "== rate limits (first install)"
    "$SITE/scripts/install_nginx_limits.sh"
fi

if nginx -t; then
    systemctl reload nginx
else
    ngx_restore
    echo "nginx -t failed -- this run's nginx changes were put back" >&2; exit 1
fi
sleep 1
printf 'php-fpm status from 127.0.0.1: '; curl -s -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1/moop-fpm-status?json'

echo "== MOOP publishes its wanted nginx settings (as the web user, as it will from now on)"
WEBUSER=$(stat -c %U "$CACHE")
runuser -u "$WEBUSER" -- "$PHP" -r 'require $argv[1] . "/includes/config_init.php"; require $argv[1] . "/lib/system_monitor.php";
    echo moop_monitor_publish_wanted() ? "published\n" : "FAILED to write nginx_wanted.json\n";' "$SITE"

echo "== timer"
cat > /etc/systemd/system/moop-monitor.service <<'EOF'
[Unit]
Description=MOOP System Monitor collector (one sample)

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/moop-monitor
Nice=10
IOSchedulingClass=idle
EOF
cat > /etc/systemd/system/moop-monitor.timer <<'EOF'
[Unit]
Description=MOOP System Monitor collector, every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
AccuracySec=5s

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now moop-monitor.timer

# Two samples so the first CPU figure (a difference between two readings) exists right away.
/usr/local/sbin/moop-monitor; sleep 2; /usr/local/sbin/moop-monitor
echo "== first sample"
python3 - "$CACHE/monitor_now.json" <<'PY'
import json, sys
d = json.load(open(sys.argv[1])); n = d['now']; s = d['nginx_sync']
print(f"CPU {n.get('cpu_pct')}%  RAM {n['mem_used_pct']}% used ({n['mem_avail_mb']} MB free)  load {n['load1']}")
print(f"php-fpm: {n.get('fpm_active')} busy / {n.get('fpm_total')} workers, queue {n.get('fpm_queue')}" if 'fpm_active' in n else f"php-fpm status: {n.get('fpm_error')}")
print(f"nginx sync: {s.get('state')} — {s.get('message')}")
PY
echo "Installed. Admin -> System Monitor reads $CACHE/monitor_{now,24h,7d,30d}.json."
