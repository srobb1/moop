#!/bin/bash
# Bring every MOOP site on this host to the SAME commit of the shared GitHub repo.
#
#   scripts/update_sites.sh            # every MOOP checkout under /var/www/html
#   scripts/update_sites.sh --check    # only report each site's commit and local changes
#
# Several MOOP sites can share one host (simrbase at /var/www/html/moop, cuttingclass at
# /var/www/html/cuttingclass): one repo, one checkout per site. The rule that keeps them aligned:
# NEITHER checkout is edited by hand. Changes are made in one tree, committed and pushed; every
# site gets them by pulling. This script is that pull, for all sites at once.
#
# Per site, in order (the smaller site first, so a bad update shows up there before simrbase):
#   1. refuse if the checkout has local changes to tracked files -- a hand edit would otherwise be
#      silently kept (or block the pull) and the sites would drift; commit it or discard it first
#   2. git pull --ff-only (never a merge on a live site)
#   3. restore web-readable permissions (.githooks/moop-fix-web-perms; the hook also runs itself)
#   4. php setup-check.php, reporting its FAIL count
# Stops at the first problem. Run as the checkouts' owner (not root): git and the hook need it.
set -uo pipefail

CHECK_ONLY=0
[[ "${1:-}" == "--check" ]] && CHECK_ONLY=1

# Every MOOP checkout under the document root, simrbase ("moop") LAST.
mapfile -t SITES < <(for d in /var/www/html/*/; do
    d="${d%/}"
    [[ -d "$d/.git" && -f "$d/setup-check.php" && -f "$d/includes/ConfigManager.php" ]] && echo "$d"
done | awk '{ print ($0 ~ /\/moop$/ ? "1 " : "0 ") $0 }' | sort | cut -d' ' -f2-)

if [[ ${#SITES[@]} -eq 0 ]]; then echo "No MOOP checkouts found under /var/www/html"; exit 1; fi

status=0
for site in "${SITES[@]}"; do
    name="$(basename "$site")"
    echo "== $name ($site)"
    dirty="$(git -C "$site" status --porcelain --untracked-files=no)"
    if [[ -n "$dirty" ]]; then
        echo "   LOCAL CHANGES -- not updating. Commit them in the shared repo, or discard them:"
        echo "$dirty" | sed 's/^/     /'
        status=1; break
    fi
    if [[ $CHECK_ONLY -eq 0 ]]; then
        if ! git -C "$site" pull -q --ff-only; then
            echo "   git pull failed -- stopping"; status=1; break
        fi
        (cd "$site" && sh .githooks/moop-fix-web-perms)
        fails="$(cd "$site" && php setup-check.php 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -c '\[FAIL\]')"
        echo "   setup-check: $fails FAIL"
        [[ "$fails" -gt 0 ]] && { echo "   run: cd $site && php setup-check.php"; status=1; }
    fi
    echo "   at $(git -C "$site" log --oneline -1)"
done

# Aligned means every site at one commit.
if [[ $status -eq 0 ]]; then
    n="$(for s in "${SITES[@]}"; do git -C "$s" rev-parse HEAD; done | sort -u | wc -l)"
    [[ "$n" -eq 1 ]] && echo "All ${#SITES[@]} sites at the same commit." || { echo "Sites are at $n different commits."; status=1; }
fi
exit $status
