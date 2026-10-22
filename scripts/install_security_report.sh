#!/bin/bash
# Daily OS security-update report for MOOP's dashboard.
#
# MOOP's housekeeping cannot ask the package manager itself: under SELinux the web server
# (httpd_t) may not open the RPM database -- dnf fails with "rpmdb open failed: Operation not
# permitted" (tested 2026-10-22). Granting httpd that access would hand every web request a
# view of the whole package database, so instead root writes a small plain-text report once a
# day, into MOOP's cache directory, and housekeeping only READS it. MOOP never runs as root
# and never installs anything.
#
# Installs:
#   /usr/local/sbin/moop-security-report          writes the report (dnf or apt, cached metadata only)
#   /etc/systemd/system/moop-security-report.{service,timer}   daily, and 10 min after boot
# and writes the first report now.
#
# The report lands in MOOP's cache_path (read from the site config). Re-run this script if
# cache_path changes. Remove with: systemctl disable --now moop-security-report.timer
#
# Usage: sudo scripts/install_security_report.sh
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run with sudo." >&2; exit 1; }
SITE="$(cd "$(dirname "$0")/.." && pwd)"
PHP="$(command -v php)" || { echo "php not found" >&2; exit 1; }

# The cache directory as MOOP itself resolves it -- never a second copy of that rule.
CACHE="$("$PHP" -r 'require $argv[1] . "/includes/config_init.php"; echo moop_cache_root();' "$SITE")"
[ -d "$CACHE" ] || { echo "Cache directory '$CACHE' does not exist" >&2; exit 1; }
OUT="$CACHE/security_updates.txt"

cat > /usr/local/sbin/moop-security-report <<'EOF'
#!/bin/bash
# Writes MOOP's OS security-update report -- installed by MOOP's scripts/install_security_report.sh.
# Uses the package manager's CACHED metadata only (dnf -C / apt's existing lists): the OS's own
# timers keep that current, so this makes no network call of its own.
set -uo pipefail
OUT="$1"
TMP="$(mktemp "${OUT}.XXXXXX")"
{
  echo "# generated: $(date -Iseconds)"
  echo "# running_kernel: $(uname -r)"
  if [ -e /var/run/reboot-required ]; then echo "# reboot_required: yes"; fi
  if command -v dnf >/dev/null; then
    echo "# manager: dnf"
    echo "# newest_kernel: $(rpm -q --last kernel 2>/dev/null | head -1 | awk '{print $1}' | sed 's/^kernel-//')"
    dnf -q -C updateinfo list --security 2>&1
    echo "# exit: $?"
  elif command -v apt-get >/dev/null; then
    echo "# manager: apt"
    apt-get -s -o Debug::NoLocking=1 upgrade 2>&1
    echo "# exit: $?"
  else
    echo "# manager: none"
  fi
} > "$TMP"
chmod 644 "$TMP"
mv -f "$TMP" "$OUT"
command -v restorecon >/dev/null && restorecon "$OUT" 2>/dev/null || true
EOF
chmod 755 /usr/local/sbin/moop-security-report

cat > /etc/systemd/system/moop-security-report.service <<EOF
[Unit]
Description=MOOP OS security-update report (read by the admin dashboard)

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/moop-security-report $OUT
EOF

cat > /etc/systemd/system/moop-security-report.timer <<'EOF'
[Unit]
Description=Daily MOOP OS security-update report

[Timer]
OnBootSec=10min
OnCalendar=daily
RandomizedDelaySec=1h
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now moop-security-report.timer
/usr/local/sbin/moop-security-report "$OUT"

echo "Report written to $OUT:"
head -6 "$OUT"
echo "..."
echo "Timer: $(systemctl list-timers moop-security-report.timer --no-legend | head -1)"
