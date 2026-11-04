# Renaming simrbase's folders from `moop` to `simrbase` — plan, NOT scheduled

Written 2026-11-04. **Decision that day: leave `moop` as `moop`.** Sofia: "in a perfect world
I'd change it, but make a plan in case I change my mind." This is that plan. Do not start it
without her asking.

## Why anyone would want it

A second site (cuttingclass) is going onto this host. With both, a new admin should be able to
read the server as "here is all of simrbase, here is all of cuttingclass". Today simrbase lives
in four places, all named `moop`, which reads as the software's name, not the site's:

| Today | Would become | Holds |
|---|---|---|
| `/var/www/html/moop` | `/var/www/html/simrbase` | code + organism data |
| `/var/www/moop-site-data` | `/var/www/simrbase-site-data` | settings backup (private git repo) |
| `/var/www/moop-private` | `/var/www/simrbase-private` | users.json |
| `/var/www/moop-cache` | `/var/www/simrbase-cache` | caches, BLAST queue, monitor data |

cuttingclass is installed with clean names from the start (`/var/www/html/cuttingclass`,
`cuttingclass-site-data`, `-private`, `-cache`), so only simrbase would ever need this.

## Prerequisite: addresses must not depend on the folder name

Until the **link prefix setting** exists (separating "the folder" from "the start of every
link"), renaming the folder renames every URL. Build that first (planned for cuttingclass
anyway). With it, simrbase keeps the prefix `/moop` after the rename, so visitors see no
change; nginx maps `/moop/` to the new folder.

## What points at the folders (inventory, 2026-11-04)

- **Settings:** `config/config_editable.json` (root_path / site / users_file / cache_path /
  site_data_path), `config/site_config.php` defaults derive from `site` -- check each.
- **SELinux** — `scripts/fix_moop_selinux.sh` and the persistent `semanage fcontext` rules it
  created. ⚠️ The silent one: a path that loses its `httpd_sys_rw_content_t` rule still LOOKS
  writable and fails quietly (banner and image uploads stayed broken for three days in July
  2026 this way). After the move: re-run the script, then `setup-check.php` and the dashboard's
  permission card must be clean.
- **nginx** — `docs/nginx/moop-security.conf` (deny rules keyed on `/moop/config/`, `/moop/logs/`
  ...), the rate-limit and header includes, `root`. Re-verify each deny with a real request
  (CLAUDE.md §11: a 404 never proves a deny rule works).
- **systemd** — `moop-monitor.service/.timer`, `moop-security-report.service/.timer` (they write
  into the cache folder). Unit names can stay; paths inside must change.
- **Compute pipeline** — `config/build_and_load_db/scripts/copy2moop_v2.sh`, `check_status.sh`:
  the copy target for new data. Coordinate with whoever runs the pipeline (web vs compute file
  ownership rule). Miss this and the next data delivery lands in a folder that no longer exists.
- **JBrowse** — `lib/jbrowse/PathResolver.php`, generated track configs, `data/genomes` symlinks,
  the tracks server's CORS origin (only if the domain changes at the same time).
- **Code with absolute paths** — `tools/blast.php`, `lib/functions_login_protection.php`,
  `lib/request_metrics.php`, `setup-check.php`, two help pages, `admin/pages/manage_site_config.php`,
  `scripts/check_sequence_id_match.sh`, `tests/` fixtures. Most are comments/examples; each needs
  reading, not a blind sed.
- **Docs** — CLAUDE.md, README.md, docs/ (19 files), notes/ (historical; leave notes as written).
- **The git clone itself** — moving the working tree is fine (`core.hooksPath .githooks` is
  relative), but the private site-data repo's remote/paths and any cron/shell aliases need checking.

## How it would be done

1. A script, `scripts/rename_site_folders.sh` (dry-run by default): stop php-fpm briefly, `mv`
   the four folders, rewrite the settings paths, re-run `fix_moop_selinux.sh` for the new paths,
   update nginx + systemd paths, reload, start php-fpm.
2. Rehearse it on a throwaway copy (the scratchpad clone pattern), not on the live site.
3. A check that nothing still names an old path (`grep` + `setup-check.php` + permission card +
   render comparison: every page byte-identical, since the prefix stays `/moop`).
4. Do it in the SAME maintenance window as the simrbase domain/HTTPS switch, so the server
   changes once.
5. Undo: the script records what it moved; the reverse `mv` + settings restore is the rollback.

## Cost

About one session for the script and rehearsal, plus the maintenance window. Low risk only if
the SELinux and pipeline steps are not skipped — those are the two that fail silently.
