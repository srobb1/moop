#!/bin/bash
# Keep php-fpm (the whole website) running when ONE request runs out of memory.
#
# Found by the capacity test on 2026-10-23: eight tblastn searches against the largest genome
# (2.9 GB of RAM each) exhausted the 15 GB server. The kernel's OOM killer killed one BLAST --
# which is fine, it is the right victim -- but php-fpm runs with systemd's defaults:
#
#   OOMPolicy=stop   any OOM kill inside the service stops the WHOLE service
#   Restart=no       ...and nothing starts it again
#
# so a single oversized BLAST took the site down until someone ran `systemctl start php-fpm`
# by hand. This drop-in changes both:
#
#   OOMPolicy=continue   the killed BLAST fails (that user sees an error); php-fpm keeps serving
#   Restart=always       if php-fpm itself ever dies, systemd brings it back after 2 s
#                        (a deliberate `systemctl stop` still stops it)
#
# It does NOT stop memory exhaustion -- capping concurrent BLASTs does that (see
# notes/CAPACITY_TEST_2026-10-23.md). It turns "site down until a human notices" into "one
# search fails".
#
# Installs /etc/systemd/system/php-fpm.service.d/moop-resilience.conf. Leaves php-fpm's own
# unit file untouched, so OS updates to it still apply. Remove the file + daemon-reload to undo.
#
# Usage: sudo scripts/install_php_fpm_resilience.sh
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run with sudo." >&2; exit 1; }
UNIT=php-fpm.service
systemctl cat "$UNIT" >/dev/null 2>&1 || { echo "$UNIT not found" >&2; exit 1; }

mkdir -p "/etc/systemd/system/$UNIT.d"
cat > "/etc/systemd/system/$UNIT.d/moop-resilience.conf" <<'EOF'
# Installed by MOOP scripts/install_php_fpm_resilience.sh -- see that file for why.
[Service]
OOMPolicy=continue
Restart=always
RestartSec=2
EOF

systemctl daemon-reload
# Start it if it is down (e.g. after the very OOM stop this guards against); otherwise leave
# it running -- the new settings take effect without a restart.
systemctl is-active --quiet "$UNIT" || systemctl start "$UNIT"

echo "php-fpm: $(systemctl is-active $UNIT)"
systemctl show "$UNIT" -p OOMPolicy -p Restart -p RestartUSec
