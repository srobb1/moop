#!/bin/bash
# Restore MOOP's SELinux contexts after the 2026-07-13 hardening run.
#
# WHY semanage AND NOT chcon:
#   chcon sets a label now, but the next SCAP/hardening relabel (restorecon)
#   resets it to the policy default and the site breaks again. semanage writes
#   a PERSISTENT policy rule, so future hardening runs RE-APPLY these labels.
#
# WHY the (/.*)? suffix:
#   semanage fcontext takes a REGEX. A bare path matches only that directory's
#   own inode, NOT its contents. Rules added without the suffix (as happened on
#   2026-07-13) leave every subdirectory read-only. This is the bug we are fixing.
#
# Idempotent — safe to re-run.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "must run as root: sudo $0"; exit 1; }

MOOP=/var/www/html/moop

# Every directory the web server (php-fpm as apache) writes into.
# Derived empirically: `find . -user apache` — these all contain apache-created files.
DIRS=(
    "$MOOP/logs"                    # error.log, login_attempts.json
    "$MOOP/config"                  # config_editable.json (admin UI)
    "$MOOP/metadata"                # jbrowse2-configs/{tracks,assemblies,sheets}, groups, taxonomy
    "$MOOP/data/genomes"            # annotations.gff3.gz + tabix indexes (regenerated on re-prep)
    "$MOOP/images/wikimedia"        # cached Wikipedia images
    "$MOOP/images/ncbi_taxonomy"    # cached NCBI taxonomy images
    "$MOOP/archived_gene_sets"      # gene-set archives
    "$MOOP/organisms"               # scattered caches: chr_names_cache.json,
                                    # annotated_feature_types.json, .organism_cache.json
    /var/www/moop-site-data         # site-data backup (had NO rule at all)
)

echo "== 0. Remove a typo'd rule (2026-07-13: 'configs' — no such directory) =="
semanage fcontext -d "$MOOP/configs(/.*)?" 2>/dev/null && echo "  removed" || echo "  not present (fine)"

echo "== 1. Persistent SELinux rules (recursive) =="
for d in "${DIRS[@]}"; do
    [[ -d "$d" ]] || { echo "  SKIP (missing): $d"; continue; }
    spec="${d}(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "$spec" 2>/dev/null \
      || semanage fcontext -m -t httpd_sys_rw_content_t "$spec"
    echo "  $spec"
done

echo "== 2. Apply the labels now =="
for d in "${DIRS[@]}"; do
    [[ -d "$d" ]] && restorecon -R "$d"
done
echo "  relabelled ${#DIRS[@]} trees"

echo "== 3. Allow php-fpm outbound connections (Google Sheets sync) =="
setsebool -P httpd_can_network_connect on
echo "  httpd_can_network_connect -> $(getsebool httpd_can_network_connect | awk '{print $3}')"

echo "== 4. Restore apache ownership under the JBrowse track configs =="
# A manual edit on 2026-07-13 flipped some of these to smr, which would make
# apache unable to overwrite them on a re-prep.
TRACKS="$MOOP/metadata/jbrowse2-configs/tracks"
chown -R apache:apache "$TRACKS"
find "$TRACKS" -type d -exec chmod 2775 {} +   # setgid: new files keep group apache
find "$TRACKS" -type f -exec chmod 664 {} +
echo "  $TRACKS -> apache:apache, dirs 2775 / files 664"

cat <<'EOF'

DONE.

Next: Admin -> Manage JBrowse -> click "re-prep" on each gene set.
That regenerates the track JSON with the corrected LinearBasicDisplay type.
(6 assemblies still carry the bad type: Amphimedon, Chamaeleo, Congeria,
 Drosophila, Furcifer, Scolanthus. Nematostella is already fixed.)

Verify:
  grep -rl LinearGeneAnnotationsDisplay /var/www/html/moop/metadata   # expect no output
  getsebool httpd_can_network_connect                                 # expect: on
EOF
