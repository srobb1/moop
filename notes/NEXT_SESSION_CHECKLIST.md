# Next Session Checklist

Remaining cleanup and fixes identified during March 2026 audit sessions.

---

## Admin Page Verification

- [x] **Check organism checklist for `data/genomes/` and `data/tracks/`** — Added both
  directories to the Step 1 directory structure diagram in organism_checklist.php.

## Environment Validation on Admin Login

- [ ] **Show warnings if requirements degrade** — Beyond the setup script, the admin
  dashboard could show warnings if requirements degrade over time (e.g., an extension
  gets disabled after a PHP upgrade, permissions drift, JWT keys deleted). The
  filesystem permissions page already does some of this — extend it or link to it
  prominently.

## Help Pages — New Pages Needed

- [ ] **JBrowse2 track setup** — How to add track files to `data/tracks/`, configure
  assemblies/genomes, JWT auth flow, JBrowse CLI usage, linking tracks to
  organisms/assemblies, troubleshooting 403/token/htaccess issues.

- [ ] **Site-data backup & restore** — What the site-data repo is, setting it up,
  what's included (config_editable.json, secrets.php, metadata, users.json), how
  housekeeping auto-snapshots work, restoring from snapshot, manual backup for
  organism data.

- [ ] **User management** — Adding users via Manage Users, setting per-organism/assembly
  access, admin vs collaborator roles, password resets, IP-based auto-login config,
  relationship to users.json and organism_assembly_groups.json.

- [ ] **Upgrading MOOP** — Pulling new versions, composer install, checking for new
  .example files, running setup-check.php, new PHP extension requirements, when to
  re-run npm install / JBrowse CLI updates.

## Help Pages — Existing Page Fixes

- [x] **USER_GUIDE.md** (`docs/current/user/USER_GUIDE.md`):
  - Replaced "SIMRbase" with "MOOP" throughout
  - Removed reference to non-existent `SECURITY_IMPLEMENTATION.md`
  - Fixed "All Access IP Range" → "IP_IN_RANGE" terminology
  - Fixed broken help links (relative .php → `help.php?topic=` paths)
  - Rewrote Security & Privacy section (removed false "Activity logging" claim,
    added CSRF, session security, correct access level names)

---

## Already Completed (for reference)

- [x] Security audit — 20 items, all done (commits `e4440ab`, `c7dd00e`)
- [x] Housekeeping system — `lib/housekeeping.php` (temp cleanup + site-data snapshots)
- [x] SRI hashes on CDN resources — `head-resources.php`, `layout.php`
- [x] Repo cleanup — `.gitignore`, `.example` templates, site-data backup repo
- [x] README rewrite — complete setup guide with JBrowse2, JWT keys, Node.js
- [x] Permissions page — removed hardcoded `www-data`, added genomes/tracks/certs checks
- [x] Deleted `includes/page-setup.php` — dead code, removed dual DataTables loading
- [x] README `www-data` — added note about web server user varying by distro
- [x] `validateOrganismParam()` — already used ConfigManager (was fixed earlier)
- [x] `getBlastDatabases()` — already used ConfigManager (was fixed earlier)
- [x] Organism checklist `www-data` — now uses `getWebServerUser()` dynamically
- [x] setup.php — interactive web-based installer (self-disabling)
- [x] setup-check.php — CLI preflight validation script
- [x] Help files audit — fixed permission-management.php, system-requirements.php
- [x] Docs accuracy — rewrote CONFIG_GUIDE.md, SECURITY_GUIDE.md, fixed PERMISSIONS_GUIDE.md
- [x] Help gaps assessed — identified 4 new pages needed + USER_GUIDE.md fixes
