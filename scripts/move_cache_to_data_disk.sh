#!/bin/bash
# Move a MOOP cache folder to another (bigger) disk, KEEPING ITS PATH: the files go to
# <target-folder>/<name>, bind-mounted back at the old path (and recorded in /etc/fstab).
# Nothing that names the cache path has to change -- MOOP's cache_path setting,
# /etc/moop-monitor.conf, the security-report timer.
#
#   sudo scripts/move_cache_to_data_disk.sh <cache-folder> <target-folder>
#   sudo scripts/move_cache_to_data_disk.sh /var/www/moop-cache /var/www/html/.site-caches
#
# Why: caches grow with use (gene image thumbnails, BLAST queue, ontology files, request
# metrics). On a host whose big disk is mounted at the web root, the default cache place
# (/var/www/<site>-cache) is on the small /var disk. setup-check.php and the dashboard warn
# (moop_cache_disk_problem). docs/INSTALL.md, "Cache on the big disk".
#
# If <target-folder> is INSIDE the web root, give it a name starting with a dot
# (.site-caches): nginx refuses dot-paths (docs/nginx/moop-security.conf), and the cache holds
# the per-request log with user names -- it must never be served. The script refuses otherwise.
#
# Safe to re-run (an already-mounted cache is skipped). The old copy is kept as
# <cache-folder>.old-on-<disk> until you delete it.
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Run with sudo" >&2; exit 1; }
[ $# -eq 2 ] || { sed -n '2,8p' "$0"; exit 1; }
old="${1%/}"; base="${2%/}"; new="$base/$(basename "$old")"
case "$base/" in /var/www/html/*) case "$base" in */.*) ;; *)
    echo "Refusing: $base is inside the web root but not a dot-folder (it could be served)." >&2; exit 1;; esac;; esac

if findmnt -n "$old" >/dev/null 2>&1; then echo "$old is already a mount -- nothing to do"; exit 0; fi
[ -d "$old" ] || { echo "$old does not exist" >&2; exit 1; }
owner=$(stat -c %U:%G "$old"); mode=$(stat -c %a "$old")

mkdir -p "$base" "$new"
[ "$base" != "/" ] && chmod 0711 "$base"                    # traverse only: nobody can list it
if command -v selinuxenabled >/dev/null && selinuxenabled; then
    rule="$(printf '%s' "$base" | sed 's/\./\\./g')(/.*)?"
    semanage fcontext -l | grep -qF "$rule" || semanage fcontext -a -t httpd_sys_rw_content_t "$rule"
fi
rsync -aAX "$old/" "$new/"                                  # contents, owners, ACLs, SELinux xattrs
chown "$owner" "$new"; chmod "$mode" "$new"
command -v restorecon >/dev/null && restorecon -R "$new"
mv "$old" "$old.old-on-var"
mkdir "$old"; chown "$owner" "$old"; chmod "$mode" "$old"
grep -qE "^$new[[:space:]]+$old[[:space:]]" /etc/fstab || echo "$new $old none bind 0 0" >> /etc/fstab
systemctl daemon-reload 2>/dev/null || true
mount "$old"
rsync -aAX --ignore-existing "$old.old-on-var/" "$old/"    # writes that landed during the move

webuser=$(stat -c %U "$old")
if sudo -u "$webuser" touch "$old/.write-probe" 2>/dev/null; then
    rm -f "$old/.write-probe"
    echo "OK: $old is now on $(df -h --output=source,avail "$old" | tail -1 | tr -s ' ') free"
    echo "When the site looks fine: sudo rm -rf $old.old-on-var"
else
    echo "!! $webuser cannot write $old -- check before deleting $old.old-on-var" >&2; exit 1
fi
