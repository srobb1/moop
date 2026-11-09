#!/bin/bash
# Print the nginx no-exec guard for ONE MOOP site -- the file to deploy as
# /etc/nginx/default.d/<site>-security.conf.
#
#   scripts/nginx_site_guard.sh            # the site this checkout is (its folder name)
#   scripts/nginx_site_guard.sh cuttingclass
#   sudo bash -c 'scripts/nginx_site_guard.sh > /etc/nginx/default.d/cuttingclass-security.conf'
#   scripts/nginx_site_guard.sh cuttingclass /      # explicit link prefix (else read from the site)
#
# The rules are keyed on the site's LINK PREFIX (where its pages are served: "/moop/…"), not its
# folder name. The prefix is the second argument, else the site's own config_editable.json
# 'link_prefix', else "/" + the folder (the default, which is today's behaviour). A site at its
# domain's root ("/") gets every rule INCLUDING the server-wide ones: it has its own server block,
# so no other site's file supplies them there (notes/MOVE_SITE_TO_OWN_ADDRESS.md).
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

# The link prefix: argument 2, else the site's config_editable.json, else "/" + folder.
PREFIX="${2-}"
if [[ $# -lt 2 ]]; then
    SITE_DIR="$(dirname "$HERE")/$SITE"
    CFG="$SITE_DIR/config/config_editable.json"
    PREFIX=""
    if [[ -r "$CFG" ]]; then
        # json_decode only -- no MOOP code runs.
        PREFIX="$(php -r '$c = json_decode((string) file_get_contents($argv[1]), true); echo is_array($c) ? trim((string) ($c["link_prefix"] ?? "")) : "";' "$CFG" 2>/dev/null || true)"
    fi
    [[ -z "$PREFIX" ]] && PREFIX="/$SITE"
fi
# Normalise like moop_url_base(): "/" means the root (""), "moop" or "/moop/" mean "/moop".
if [[ "$PREFIX" == "/" ]]; then PREFIX=""; else PREFIX="/${PREFIX#/}"; PREFIX="${PREFIX%/}"; fi
if [[ -n "$PREFIX" && ! "$PREFIX" =~ ^(/[A-Za-z0-9._~-]+)+$ ]]; then
    echo "nginx_site_guard.sh: not a usable link prefix: $PREFIX" >&2
    exit 2
fi

if [[ "$PREFIX" == "/moop" ]]; then
    cat "$TEMPLATE"
    exit 0
fi

if [[ -z "$PREFIX" ]]; then
    printf '%s\n' \
        "# ${SITE}'s copy of docs/nginx/moop-security.conf for a site served at its domain's ROOT:" \
        "# every /moop/ rule re-keyed to /. The server-wide rules are KEPT -- this site has its own" \
        "# server block, so nothing else applies them there."
    sed 's#/moop/#/#g' "$TEMPLATE"
    exit 0
fi

printf '%s\n' \
    "# ${SITE}'s copy of docs/nginx/moop-security.conf: every /moop/ rule re-keyed to ${PREFIX}/." \
    "# The three SERVER-WIDE rules (dotfiles, /users.json, */users.json) are left out: they are not" \
    "# per site, simrbase's moop-security.conf already applies them to the whole server, and a second" \
    "# copy of \`location = /users.json\` is a duplicate-location error that fails nginx -t."
awk -v prefix="$PREFIX" '
    /^location ~ \/\\\.\(\?!well-known\) \{$/ { skip = 1; next }   # dotfiles: a block
    skip { if ($0 ~ /^\}$/) skip = 0; next }
    /^location = \/users\.json/              { next }
    /^location ~ \^\/\.\*\/users\\\.json/    { next }
    { gsub("/moop/", prefix "/"); print }
' "$TEMPLATE"
