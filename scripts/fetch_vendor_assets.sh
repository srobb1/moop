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
#   * Where did this file come from?   — the URL is pinned in scripts/vendor_assets.txt.
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
#   Append a line to scripts/vendor_assets.txt: <path>|<url>|<sha256>|<post-process>|<ecosystem:package@version>
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

# The table lives in scripts/vendor_assets.txt (also read by the dashboard's library check).
ASSETS=()
while IFS= read -r line; do
    case "$line" in ''|'#'*) continue ;; esac
    ASSETS+=("$line")
done < "$REPO_ROOT/scripts/vendor_assets.txt"

sha_of() { sha256sum "$1" 2>/dev/null | cut -d' ' -f1; }

ok=0; fixed=0; failed=0; missing=0
tmpdir=$(mktemp -d) || exit 1
trap 'rm -rf "$tmpdir"' EXIT

for row in "${ASSETS[@]}"; do
    IFS='|' read -r path url want post _pkg <<< "$row"
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
