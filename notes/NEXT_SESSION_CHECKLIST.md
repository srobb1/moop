# Next Session Checklist

Remaining cleanup and small fixes identified during March 2026 audit sessions.

---

## Dead Code Removal

- [ ] **Delete `includes/page-setup.php`** — No PHP files include it anymore (confirmed by search).
  Removes the duplicate DataTables loading issue and the broken `custom_css_path` CSS URL bug.
  Before deleting, do one final `grep -r 'page-setup' --include='*.php'` to be safe.

## Small Fixes

- [ ] **README hardcodes `www-data`** — Steps 7-8 in the setup guide use `www-data` in
  `chown`/`chmod` examples. Add a note that the user/group depends on the web server
  (e.g., `www-data` for Apache on Debian, `nginx` for Nginx, `apache` for CentOS).

- [ ] **`validateOrganismParam()` hardcodes `/moop/index.php`** — In `lib/functions_validation.php`.
  Should use `ConfigManager` to get the site name. Quick one-line fix.

- [ ] **`getBlastDatabases()` uses `global $sequence_types`** — In `lib/blast_functions.php`.
  Should accept it as a function parameter instead of using `global`.

## Admin Page Verification

- [ ] **Check organism checklist for `data/genomes/` and `data/tracks/`** — We verified the
  filesystem permissions page covers these directories (commit `909fdda`), but the organism
  checklist (`admin/pages/organism_checklist.php`) may also need to reference them during
  organism setup steps.

## New Site Setup — Streamline & Automate

Goal: A new MOOP deployment should be easy to stand up and hard to get wrong. The
README has 11 manual steps with shell commands, permission changes, key generation,
config file copies, and directory creation. That's too many places for things to break
silently (wrong PHP version, missing extension, bad permissions, forgotten .example copy).

### Ideas to explore

- [ ] **`setup.php` (or `install.php`) — interactive web-based installer**
  First page a new user hits if `config/config_editable.json` doesn't exist yet.
  Walks through setup in the browser: checks PHP version & extensions, verifies
  required CLI tools (BLAST+, samtools, etc.), creates directories with correct
  permissions, copies `.example` files, generates JWT keys, creates admin account.
  Disables itself after setup is complete. Similar to how WordPress/MediaWiki
  installers work. This is the highest-value item — it replaces most README steps.

- [ ] **`setup-check.php` — preflight validation script (CLI)**
  A PHP script that can run from the command line (`php setup-check.php`) and
  reports pass/fail for every requirement: PHP version, each extension (sqlite3,
  json, posix, openssl, curl), CLI tools (blastn, samtools, tabix, bgzip, jq),
  directory existence & permissions, JWT key presence, Apache modules (mod_rewrite,
  mod_headers), Composer dependencies installed, `.htaccess` active (test 403 on
  data/tracks/). Outputs a clear report with fix commands for anything that fails.
  Useful both during initial setup and after upgrades.

- [ ] **Consolidate directory creation** — The README lists `mkdir -p` for 6+
  directories. The setup script should handle all of these, using `getWebServerUser()`
  to set correct ownership/group automatically. No more copy-pasting `chown` commands.

- [ ] **`.example` file auto-copy** — If a required config/metadata JSON file is
  missing but its `.example` exists, either the setup script or the app itself
  (on first run) should copy it automatically. Reduces a whole README step to zero.

- [ ] **JWT key auto-generation** — If `certs/jwt_private_key.pem` doesn't exist,
  the setup script (or first-run logic) should generate the keypair automatically
  using PHP's `openssl_pkey_new()` — no need for the user to run `openssl` commands.

- [ ] **Environment validation on admin login** — Beyond the setup script, the admin
  dashboard could show warnings if requirements degrade over time (e.g., an extension
  gets disabled after a PHP upgrade, permissions drift, JWT keys deleted). The
  filesystem permissions page already does some of this — extend it or link to it
  prominently.

## Help Files Review

- [ ] **Audit existing help files** — Review all files under `tools/pages/help/` for
  accuracy. The security audit and repo cleanup changed auth flows, directory structure,
  permissions, and setup steps. Help pages may reference outdated paths, old permission
  schemes, or missing steps.

- [ ] **Check docs/ for accuracy** — Review `docs/current/admin/` guides (CONFIG_GUIDE,
  PERMISSIONS_GUIDE, SECURITY_GUIDE) and `docs/current/user/USER_GUIDE` against the
  current codebase. These may have drifted during the March 2026 changes.

- [ ] **Assess gaps** — Are there help topics users would need that don't have pages yet?
  Candidates: JBrowse2 track setup, site-data backup/restore, troubleshooting common
  permission errors, upgrading MOOP between versions.

---

### Design principles
- **Fail loud, fail early** — every check should produce a clear error with a fix command
- **Idempotent** — safe to re-run; skips steps already completed
- **No new dependencies** — pure PHP, no extra packages needed to run the installer
- **Works on fresh clone** — `git clone` + `php setup.php` should be enough to get started
- **Self-disabling** — installer locks itself out after first successful setup

---

## Already Completed (for reference)

- [x] Security audit — 20 items, all done (commits `e4440ab`, `c7dd00e`)
- [x] Housekeeping system — `lib/housekeeping.php` (temp cleanup + site-data snapshots)
- [x] SRI hashes on CDN resources — `head-resources.php`, `layout.php`
- [x] Repo cleanup — `.gitignore`, `.example` templates, site-data backup repo
- [x] README rewrite — complete setup guide with JBrowse2, JWT keys, Node.js
- [x] Permissions page — removed hardcoded `www-data`, added genomes/tracks/certs checks
