#!/bin/bash
# Let nginx wait long enough for a BLAST search that queued, then ran.
#
# nginx gives up on PHP after 60 s by default (fastcgi_read_timeout) and shows the user a 504
# -- while the search carries on in php-fpm for nobody. Since 2026-10-23 a BLAST may wait its
# turn in the queue (lib/blast_queue.php: blast_queue_max_wait, default 120 s) and then run
# (blast_max_runtime, default 150 s), so 60 s is not enough. Measured the same day: six wallaby
# tblastns at once finished in 51 s for the last in line, and a low-complexity transcript vs a
# bat genome took 45 s just to be refused. 300 s covers wait + run with room to spare.
#
# Raised for every PHP page, not just BLAST: one place, and it also covers large Data Exporter
# downloads. It does not let a page run longer -- PHP's own limits still apply -- it only stops
# nginx abandoning a request that is legitimately still working.
#
# Installs /etc/nginx/default.d/moop-timeouts.conf (included by both the http and https server
# blocks), tests the config, and reloads nginx only if the test passes. Remove the file and
# reload to undo.
#
# Usage: sudo scripts/install_nginx_timeouts.sh
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run with sudo." >&2; exit 1; }
CONF=/etc/nginx/default.d/moop-timeouts.conf

cat > "$CONF" <<'EOF'
# Installed by MOOP scripts/install_nginx_timeouts.sh -- see that file for why.
# Must exceed blast_queue_max_wait + blast_max_runtime (default 120 + 150 s).
fastcgi_read_timeout 300s;
EOF

if nginx -t; then
    systemctl reload nginx
    echo "nginx reloaded: fastcgi_read_timeout 300s"
else
    rm -f "$CONF"
    echo "nginx config test FAILED -- removed $CONF, nothing changed." >&2
    exit 1
fi
