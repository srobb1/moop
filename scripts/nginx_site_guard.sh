#!/bin/bash
# Print the nginx no-exec guard for ONE MOOP site -- the file to deploy as
# /etc/nginx/default.d/<site>-security.conf.
#
#   scripts/nginx_site_guard.sh            # the site this checkout is (its folder name)
#   scripts/nginx_site_guard.sh cuttingclass
#   sudo bash -c 'scripts/nginx_site_guard.sh > /etc/nginx/default.d/cuttingclass-security.conf'
#
# The ONE definition of "this site's guard". fix_moop_selinux.sh and setup-check.php compare the
# deployed file against this script's output, so the rule cannot drift between the docs and the
# two checkers.
#
# For the site "moop" it prints docs/nginx/moop-security.conf unchanged. For any other site it
# re-keys every /moop/ rule to /<site>/ and LEAVES OUT the three server-wide rules (dotfiles,
# /users.json, */users.json): those protect the whole server, the first site's file already
# applies them, and a second `location = /users.json` is a duplicate-location error that fails
# `nginx -t`. Found 2026-11-05 installing cuttingclass beside simrbase -- the old instruction was
# `sed 's#/moop/#/<site>/#g'`, which produced exactly that error.
#
# Reads only the template; writes nothing. Safe to run as root (it runs no MOOP code).
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SITE="${1:-$(basename "$HERE")}"
TEMPLATE="$HERE/docs/nginx/moop-security.conf"

if [[ ! "$SITE" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "nginx_site_guard.sh: not a site name: $SITE" >&2
    exit 2
fi
if [[ ! -r "$TEMPLATE" ]]; then
    echo "nginx_site_guard.sh: cannot read $TEMPLATE" >&2
    exit 2
fi

if [[ "$SITE" == "moop" ]]; then
    cat "$TEMPLATE"
    exit 0
fi

printf '%s\n' \
    "# ${SITE}'s copy of docs/nginx/moop-security.conf: every /moop/ rule re-keyed to /${SITE}/." \
    "# The three SERVER-WIDE rules (dotfiles, /users.json, */users.json) are left out: they are not" \
    "# per site, simrbase's moop-security.conf already applies them to the whole server, and a second" \
    "# copy of \`location = /users.json\` is a duplicate-location error that fails nginx -t."
awk -v site="$SITE" '
    /^location ~ \/\\\.\(\?!well-known\) \{$/ { skip = 1; next }   # dotfiles: a block
    skip { if ($0 ~ /^\}$/) skip = 0; next }
    /^location = \/users\.json/              { next }
    /^location ~ \^\/\.\*\/users\\\.json/    { next }
    { gsub("/moop/", "/" site "/"); print }
' "$TEMPLATE"
