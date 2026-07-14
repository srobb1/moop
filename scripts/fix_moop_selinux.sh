#!/bin/bash
# Apply MOOP's required SELinux contexts, booleans, and the cache directory on a
# hardened RHEL host. This is the canonical, reproducible SELinux setup for a
# MOOP deployment — run it on a fresh install, or to recover after a SCAP/
# OpenSCAP hardening run resets labels.
#
# WHY semanage AND NOT chcon:
#   chcon sets a label now, but the next hardening relabel (restorecon) resets it
#   to the policy default and the site breaks again. semanage writes a PERSISTENT
#   policy rule, so future hardening runs RE-APPLY these labels automatically.
#   You do NOT need IT to redo these after a hardening run — that is the point.
#
# WHY the (/.*)? suffix:
#   semanage fcontext takes a REGEX. A bare path matches only that directory's own
#   inode, NOT its contents. A rule without the suffix leaves every subdirectory
#   at the policy default (read-only).
#
# Idempotent — safe to re-run.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "must run as root: sudo $0"; exit 1; }

MOOP=/var/www/html/moop
CACHE=/var/www/moop-cache

# Directories the web server (php-fpm as apache) must be able to WRITE.
# NOTE: organisms/ is deliberately NOT here — it is read-only except organism.json
# (handled separately below). Its regenerable caches live in $CACHE instead.
RW_DIRS=(
    "$MOOP/logs"                    # error.log, login_attempts.json
    "$MOOP/config"                  # config_editable.json (admin UI)
    "$MOOP/metadata"                # jbrowse2-configs/{tracks,assemblies,sheets}, groups, taxonomy
    "$MOOP/data/genomes"            # annotations.gff3.gz + tabix indexes (regenerated on re-prep)
    "$MOOP/images/wikimedia"        # cached Wikipedia images
    "$MOOP/images/ncbi_taxonomy"    # cached NCBI taxonomy images
    "$MOOP/archived_gene_sets"      # gene-set archives
    /var/www/moop-site-data         # site-data backup (config, secrets, users.json)
    "$CACHE"                        # generated caches (organism scan, annotation counts, ...)
)

echo "== 0. Remove a historical typo'd rule ('configs' — no such directory) =="
semanage fcontext -d "$MOOP/configs(/.*)?" 2>/dev/null && echo "  removed" || echo "  not present (fine)"

echo "== 1. Create the cache directory if missing (apache-owned, SGID) =="
if [[ ! -d "$CACHE" ]]; then
    mkdir -p "$CACHE"
    echo "  created $CACHE"
fi
chown apache:apache "$CACHE"
chmod 2775 "$CACHE"   # SGID: new cache files inherit group apache (php-fpm + CLI both read/write)

echo "== 2. Persistent read-write SELinux rules (recursive) =="
for d in "${RW_DIRS[@]}"; do
    [[ -d "$d" ]] || { echo "  SKIP (missing): $d"; continue; }
    spec="${d}(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "$spec" 2>/dev/null \
      || semanage fcontext -m -t httpd_sys_rw_content_t "$spec"
    echo "  rw  $spec"
done

echo "== 3. organisms/ is READ-ONLY except organism.json =="
# Drop any old recursive rw rule, then allow ONLY the per-organism organism.json
# (edited in place by the admin UI). Everything else — genomes, SQLite DBs, FASTA,
# BLAST indexes — stays read-only, so a compromised php-fpm cannot write the data tree.
semanage fcontext -d "$MOOP/organisms(/.*)?" 2>/dev/null && echo "  dropped old recursive rw rule" || echo "  no recursive rw rule (fine)"
semanage fcontext -a -t httpd_sys_rw_content_t "$MOOP/organisms/[^/]+/organism\.json" 2>/dev/null \
  || semanage fcontext -m -t httpd_sys_rw_content_t "$MOOP/organisms/[^/]+/organism\.json"
echo "  rw  $MOOP/organisms/[^/]+/organism.json  (tree otherwise read-only)"

echo "== 4. Apply all labels now =="
for d in "${RW_DIRS[@]}" "$MOOP/organisms"; do
    [[ -d "$d" ]] && restorecon -R "$d"
done
echo "  relabelled"

echo "== 5. Allow php-fpm outbound connections (Google Sheets sync) =="
setsebool -P httpd_can_network_connect on
echo "  httpd_can_network_connect -> $(getsebool httpd_can_network_connect | awk '{print $3}')"

echo "== 6. Restore apache ownership under the JBrowse track configs =="
TRACKS="$MOOP/metadata/jbrowse2-configs/tracks"
if [[ -d "$TRACKS" ]]; then
    chown -R apache:apache "$TRACKS"
    find "$TRACKS" -type d -exec chmod 2775 {} +
    find "$TRACKS" -type f -exec chmod 664 {} +
    echo "  $TRACKS -> apache:apache, dirs 2775 / files 664"
fi

cat <<EOF

DONE. Verify:
  ls -ldZ $CACHE                                         # httpd_sys_rw_content_t
  ls -ldZ $MOOP/organisms                                # httpd_sys_content_t (read-only)
  ls -lZ  $MOOP/organisms/*/organism.json | head -1      # httpd_sys_rw_content_t
  getsebool httpd_can_network_connect                    # on
EOF
