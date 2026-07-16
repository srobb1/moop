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
# organisms/ IS in this list, deliberately — see the note below it.
RW_DIRS=(
    "$MOOP/logs"                    # error.log, login_attempts.json
    "$MOOP/config"                  # config_editable.json (admin UI)
    "$MOOP/metadata"                # jbrowse2-configs/{tracks,assemblies,sheets}, groups, taxonomy
    "$MOOP/data/genomes"            # annotations.gff3.gz + tabix indexes (regenerated on re-prep)
    # The whole images/ tree is web-written, so label it recursively rather than listing
    # subdirs. Top level: organism images uploaded from the edit-organism modal land HERE
    # (admin/api/handle_image_upload.php -> $config->getPath('absolute_images_path')).
    # Below it: banners/ (manage_site_config.php uploads), wikimedia/ + ncbi_taxonomy/
    # (php downloads remote images into these caches).
    # Safe despite being served: nginx denies .php under /moop/images/ (moop-security.conf).
    "$MOOP/images"
    "$MOOP/archived_gene_sets"      # gene-set archives
    /var/www/moop-site-data         # site-data backup (config, secrets, users.json)
    "$CACHE"                        # generated caches (organism scan, annotation counts, ...)
    "$MOOP/organisms"               # organism data + in-tree BLAST/.fai indexes the web builds
)
# WHY organisms/ is writable (settled 2026-07-16 — this is the intended end state, not a TODO):
# It was briefly read-only, which blocked in-app BLAST/.fai index building AND made SQLite log a
# { write } denial on every DB open (PDO opened read-write and fell back to read-only — ~740
# denials/min of audit spam). The webshell risk from a writable data tree is closed at the
# EXECUTION layer instead, by nginx: docs/nginx/moop-security.conf denies HTTP to /moop/organisms/
# and .php under /moop/data/, so a .php written into a data tree cannot run. Verified by direct
# test 2026-07-16 (a real .php in data/genomes/ 404s; the same file in the docroot executes).
# Do NOT "re-tighten" this to read-only without reading notes/MOOP_INDEXES_PLAN.md first — the
# index/-subdir plan that would have required it was evaluated and declined.

# ── 0. The guard MUST exist before we make anything writable ──────────────────
# This script's whole promise is "safe writable trees". The trees below are served
# over HTTP, so the ONLY thing making them safe to write is the nginx rule denying
# .php inside them: without it, one file-write bug becomes a persistent webshell.
# The two halves are coupled, so verify the protective half BEFORE applying the
# dangerous half — the same guard-first ordering the tracks proxy needed.
echo "== 0. Verify the nginx execution-layer guard =="
NGINX_GUARD=/etc/nginx/default.d/moop-security.conf
NGINX_CANON="$MOOP/docs/nginx/moop-security.conf"

if [[ "${SKIP_NGINX_CHECK:-0}" == "1" ]]; then
    echo "  SKIPPED (SKIP_NGINX_CHECK=1) — writable trees will NOT be protected from .php execution"
elif [[ ! -f "$NGINX_CANON" ]]; then
    echo "  WARN: canonical copy missing ($NGINX_CANON) — cannot verify; continuing"
elif [[ ! -f "$NGINX_GUARD" ]]; then
    cat >&2 <<MSG

  REFUSING TO RUN — the nginx no-exec guard is NOT deployed.

  This script makes data trees writable by the web server. Those trees are served
  over HTTP, so without the guard a single file-write bug becomes a webshell
  (persistent remote code execution). Deploy it FIRST, then re-run this script:

    sudo cp $NGINX_CANON $NGINX_GUARD
    sudo chmod 644 $NGINX_GUARD
    sudo nginx -t && sudo systemctl reload nginx

  Override with SKIP_NGINX_CHECK=1 only if you know exactly why.

MSG
    exit 1
elif ! diff -q "$NGINX_CANON" "$NGINX_GUARD" >/dev/null 2>&1; then
    cat >&2 <<MSG

  REFUSING TO RUN — the deployed nginx guard DIFFERS from the canonical copy.

    deployed:  $NGINX_GUARD
    canonical: $NGINX_CANON

  The deployed copy may predate a rule that closes a real hole — the .php deny was
  extended to images/, jbrowse2/ and archived_gene_sets/ on 2026-07-16, and before
  that images/ was writable AND executing PHP. Re-deploy, then re-run:

    sudo cp $NGINX_CANON $NGINX_GUARD && sudo nginx -t && sudo systemctl reload nginx

  Override with SKIP_NGINX_CHECK=1 only if you know exactly why.

MSG
    exit 1
else
    echo "  deployed, matches canonical: $NGINX_GUARD"
    # Deployed is not the same as IN FORCE — nginx only picks it up on reload.
    if nginx -T 2>/dev/null | grep -q '/moop/organisms/'; then
        echo "  in force (present in nginx's running config)"
    else
        echo "  WARN: on disk but NOT in nginx's running config — it has not been reloaded:"
        echo "        sudo nginx -t && sudo systemctl reload nginx"
    fi
fi

echo "== 1. Remove a historical typo'd rule ('configs' — no such directory) =="
semanage fcontext -d "$MOOP/configs(/.*)?" 2>/dev/null && echo "  removed" || echo "  not present (fine)"

echo "== 2. Create the cache directory if missing (apache-owned, SGID) =="
if [[ ! -d "$CACHE" ]]; then
    mkdir -p "$CACHE"
    echo "  created $CACHE"
fi
chown apache:apache "$CACHE"
chmod 2775 "$CACHE"   # SGID: new cache files inherit group apache (php-fpm + CLI both read/write)

echo "== 3. Persistent read-write SELinux rules (recursive) =="
for d in "${RW_DIRS[@]}"; do
    [[ -d "$d" ]] || { echo "  SKIP (missing): $d"; continue; }
    spec="${d}(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "$spec" 2>/dev/null \
      || semanage fcontext -m -t httpd_sys_rw_content_t "$spec"
    echo "  rw  $spec"
done

echo "== 4. Clean up the retired organism.json narrow rule (organisms/ is now writable) =="
# organisms/ is fully writable via RW_DIRS above. Remove the Phase-1.5 narrow rule if present.
semanage fcontext -d "$MOOP/organisms/[^/]+/organism\.json" 2>/dev/null && echo "  removed retired narrow rule" || echo "  not present (fine)"

echo "== 5. Apply all labels now =="
for d in "${RW_DIRS[@]}"; do
    [[ -d "$d" ]] && restorecon -R "$d"
done
echo "  relabelled"

echo "== 6. Allow php-fpm outbound connections (Google Sheets sync) =="
setsebool -P httpd_can_network_connect on
echo "  httpd_can_network_connect -> $(getsebool httpd_can_network_connect | awk '{print $3}')"

echo "== 7. Restore apache ownership under the JBrowse track configs =="
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
  ls -ldZ $MOOP/organisms                                # httpd_sys_rw_content_t (writable)
  getsebool httpd_can_network_connect                    # on

Also ensure the nginx execution-layer rules are deployed (they make the writable data
trees safe): docs/nginx/moop-security.conf -> /etc/nginx/default.d/moop-security.conf.
EOF
