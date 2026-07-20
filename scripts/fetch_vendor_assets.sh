#!/usr/bin/env bash
#
# fetch_vendor_assets.sh — download the third-party front-end libraries MOOP serves itself.
#
# WHAT THIS IS FOR
# ────────────────
# The vendor assets themselves are COMMITTED to the repo, so a clone already has everything and
# this script is NOT part of deployment. It exists to answer the questions a committed binary
# blob cannot answer on its own:
#
#   * Where did this file come from?   — the URL is pinned in the table below.
#   * Is it still the file we shipped? — `--check` verifies every checksum.
#   * How do I upgrade one?            — change the version in its URL, run it, commit the diff.
#
# (Until 2026-07-20 these paths were gitignored with nothing to fetch them, so a fresh clone had
# no jQuery, Bootstrap, DataTables or icons and every page was broken, while setup-check.php
# reported all-clear because it only checked Composer's PHP vendor/autoload.php. They are now
# committed; see the note in .gitignore.)
#
# MOOP self-hosts these rather than using a CDN: it runs on an internal network where the
# browser may have no route to the internet, its Content-Security-Policy is `script-src 'self'`,
# and a CDN <script> without SRI hands a third party code execution on an authenticated admin
# page. See js/README.md.
#
# USAGE
#   scripts/fetch_vendor_assets.sh --check    # verify committed files (this is the common one)
#   scripts/fetch_vendor_assets.sh            # re-download anything missing or corrupted
#   scripts/fetch_vendor_assets.sh --force    # re-download everything, e.g. after a version bump
#
# Run it from anywhere; paths are resolved relative to the repo root. Requires network access
# EXCEPT for --check, which is purely local.
#
# ADDING A LIBRARY
#   Append a line to the ASSETS table below: <path>|<url>|<sha256>|<post-process>
#   Get the checksum from the file you actually verified — never from the vendor's website.
#   Every checksum here was confirmed byte-identical against the deployed, working files.
#
# NOTE ON FONT AWESOME
#   Upstream all.css references ../webfonts/, because it expects to live at css/all.css with
#   webfonts/ as a sibling. MOOP puts it at css/fontawesome/all.css with webfonts/ INSIDE that
#   directory, so the ../ must be stripped or every icon 404s. That rewrite is the `fa_paths`
#   post-process step, and the pinned checksum is of the REWRITTEN file. A script that merely
#   downloaded upstream would silently ship broken icons.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT" || exit 1

MODE=install
case "${1:-}" in
    --check) MODE=check ;;
    --force) MODE=force ;;
    --help|-h) sed -n '2,36p' "$0"; exit 0 ;;
    "") ;;
    *) echo "unknown option: $1 (try --help)" >&2; exit 2 ;;
esac

# path | url | sha256 | post-process (none|fa_paths)
ASSETS=(
"js/vendor/jquery.min.js|https://code.jquery.com/jquery-3.6.0.min.js|ff1523fb7389539c84c65aba19260648793bb4f5e29329d2ee8804bc37a3fe6e|none"
"js/vendor/jquery-ui.min.js|https://code.jquery.com/ui/1.14.0/jquery-ui.min.js|15bd333f88c4dc91eabbe20107d624b4b7128c8d5973a2766fa8138c1d0ba683|none"
"js/vendor/bootstrap.bundle.min.js|https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js|82f64f62bb03c1bc1824b0f9c9e05f70dba33e146818e63cdf5c306c8cf3dedd|none"
"css/bootstrap.min.css|https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css|3017df4a76db5f01c2b99b603d88b03106df13bcfe18e67b7c13c2341d3a67df|none"
"js/vendor/jquery.dataTables.min.js|https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js|552bbd0c3eaf26eaeb697823c5026ff41bb379d19f266ed71203d041e84a065c|none"
"js/vendor/dataTables.bootstrap5.min.js|https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js|079a1739cd9385bd77f12f4c7e42c70ece95eec295425e15f84bba1bbcc70d41|none"
"css/datatables/dataTables.bootstrap5.min.css|https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css|18fd969de4b138549b71ff1826a9dc2d4d52f5532a89f11042183a507c8154ff|none"
"js/vendor/dataTables.buttons.min.js|https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js|749896e15fee3ce201c59530d93c13c70d5e482ab0cd40d9228da30c5c8a04bc|none"
"js/vendor/buttons.bootstrap5.min.js|https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js|ea0b6a6cedca0ecf6a7dce0fe57aab199cea6d355f299f6b66aba0eea74ce2fb|none"
"js/vendor/buttons.html5.min.js|https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js|3afbfbcff9a8cea4fc9787c9494512082f27ddeee20179565c78fc14bba81b9f|none"
"js/vendor/buttons.print.min.js|https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js|c1f746892c5a352a895d7070c2d7c59341607e42da77ea74c946b673c520d3d9|none"
"js/vendor/buttons.colVis.min.js|https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js|519ef1a30e3a9a9c34af125e8fc94466e0dd2b309ee8228875927ac8cc9dfaab|none"
"css/datatables/buttons.bootstrap5.min.css|https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css|5ac0e5193d42ca6713646b5185f1e0b6028221cc2ac72361e819ceabb3de3892|none"
"js/vendor/dataTables.colReorder.min.js|https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js|ad621b1936cc8c60ce10718faf5429bb45f6ec177a719a464886e60c155ab99e|none"
"css/datatables/colReorder.dataTables.min.css|https://cdn.datatables.net/colreorder/1.6.2/css/colReorder.dataTables.min.css|c00fb83a1f191a964ad70790f3aa1cc41360471a80fbd4b77e6de36635bd83a9|none"
"js/vendor/jszip.min.js|https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js|acc7e41455a80765b5fd9c7ee1b8078a6d160bbbca455aeae854de65c947d59e|none"
"css/fontawesome/all.css|https://use.fontawesome.com/releases/v5.7.0/css/all.css|edeccf624d15b02e0cdf16ada5bd8e805e04f35e07345c56f3b8b733c41ca34e|fa_paths"
"css/fontawesome/webfonts/fa-solid-900.woff2|https://use.fontawesome.com/releases/v5.7.0/webfonts/fa-solid-900.woff2|658cf43db24e9d4c57890e958aa74656a13139754de24f19e706f0a355279e4d|none"
"css/fontawesome/webfonts/fa-regular-400.woff2|https://use.fontawesome.com/releases/v5.7.0/webfonts/fa-regular-400.woff2|79569bbf98e046743427673c2f59a9649ee833f2a9089b2e6497d435b5fe1b09|none"
"css/fontawesome/webfonts/fa-brands-400.woff2|https://use.fontawesome.com/releases/v5.7.0/webfonts/fa-brands-400.woff2|ed7514b6c3a5fdc386bff4dcccaee5e0c72e83cf31f90ff5ac4fb70e33fb6857|none"
)

sha_of() { sha256sum "$1" 2>/dev/null | cut -d' ' -f1; }

ok=0; fixed=0; failed=0; missing=0
tmpdir=$(mktemp -d) || exit 1
trap 'rm -rf "$tmpdir"' EXIT

for row in "${ASSETS[@]}"; do
    IFS='|' read -r path url want post <<< "$row"
    name=$(basename "$path")

    if [ "$MODE" != "force" ] && [ -f "$path" ] && [ "$(sha_of "$path")" = "$want" ]; then
        printf '  ok       %s\n' "$name"
        ok=$((ok+1))
        continue
    fi

    if [ "$MODE" = "check" ]; then
        if [ -f "$path" ]; then
            printf '  WRONG    %s (checksum does not match the pinned value)\n' "$name"
            failed=$((failed+1))
        else
            printf '  MISSING  %s\n' "$name"
            missing=$((missing+1))
        fi
        continue
    fi

    tmp="$tmpdir/$name"
    if ! curl -fsSL --max-time 60 "$url" -o "$tmp"; then
        printf '  FAILED   %s (download failed: %s)\n' "$name" "$url"
        failed=$((failed+1))
        continue
    fi

    # Post-processing must happen BEFORE verification: the pinned checksum is of the file as
    # MOOP serves it, not as upstream ships it.
    case "$post" in
        fa_paths) sed -i 's#url(\.\./webfonts/#url(webfonts/#g' "$tmp" ;;
    esac

    got=$(sha_of "$tmp")
    if [ "$got" != "$want" ]; then
        printf '  FAILED   %s (checksum mismatch — upstream changed, or the URL now serves a different build)\n' "$name"
        printf '             expected %s\n             got      %s\n' "$want" "$got"
        failed=$((failed+1))
        continue
    fi

    mkdir -p "$(dirname "$path")"
    install -m 644 "$tmp" "$path" || { printf '  FAILED   %s (could not write)\n' "$name"; failed=$((failed+1)); continue; }
    printf '  fetched  %s\n' "$name"
    fixed=$((fixed+1))
done

echo
if [ "$MODE" = "check" ]; then
    printf 'vendor assets: %d ok, %d missing, %d wrong\n' "$ok" "$missing" "$failed"
    [ $((missing+failed)) -eq 0 ] || { echo "run scripts/fetch_vendor_assets.sh to fix"; exit 1; }
    echo "all vendor assets present and verified"
else
    printf 'vendor assets: %d already ok, %d fetched, %d failed\n' "$ok" "$fixed" "$failed"
    [ "$failed" -eq 0 ] || exit 1
    echo "all vendor assets present and verified"
fi
