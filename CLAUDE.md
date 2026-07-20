# CLAUDE.md — AI Development Guide for MOOP

This file gives Claude (and other AI assistants) the context needed to work
effectively on this codebase without re-discovering its architecture each session.

---

## What This Project Is

**MOOP** (Multi-Organism Omics Platform) is a PHP web application for displaying
genome assemblies, genes, mRNAs, and functional annotations across multiple
organisms. It integrates with **JBrowse2** (genome browser) and **Galaxy**
(bioinformatics workflows). Access to data is controlled: not all organisms or
assemblies are public.

- Web root: `/var/www/html/moop/`
- Users file: `/var/www/moop-private/users.json` — bcrypt-hashed passwords. **Moved OUTSIDE
  the served document root on 2026-07-17** (it previously sat at `/var/www/html/users.json`,
  inside `/var/www/html`). php-fpm (`httpd_t`) both reads and writes it (Manage Users), so it
  needs SELinux `httpd_sys_rw_content_t` + a persistent `semanage` rule — see
  `scripts/fix_moop_selinux.sh`. A plain `chmod` will NOT make it writable; under SELinux the
  label decides — a read-only `httpd_sys_content_t` label (what a `mv` from the docroot leaves
  behind) is exactly what 500'd Manage Users on 2026-07-17. The path is config-driven:
  `site_config.php` default, overridable via the `users_file` key in `config_editable.json`.
  The old docroot deny rules in `docs/nginx|apache/moop-security.conf` are kept as
  defense-in-depth against a stray copy ever landing back in the docroot.
- Organism data: `/var/www/html/moop/organisms/{organism}/{assembly}/`
- SQLite databases: one per organism at `organisms/{organism}/organism.sqlite`
  (opened **read-only** — `PDO::SQLITE_OPEN_READONLY`; the web workload is queries only)

---

## Directory Structure

```
moop/
├── admin/              Admin-only pages and controllers
│   ├── admin_init.php      Bootstrap for all admin pages (ONE include at top)
│   ├── admin_access_check.php  Verifies admin role, redirects otherwise
│   ├── api/            AJAX endpoints called from admin JS
│   └── pages/          HTML content files (no <html>/<body> tags)
├── api/                Public/authenticated API endpoints
│   └── jbrowse2/       JBrowse2 track serving + config endpoints
├── config/
│   ├── site_config.php     DEFAULT config values (do not edit directly)
│   ├── config_editable.json  Admin-editable overrides (managed via UI)
│   ├── tools_config.php    Tool registry (which tools appear on which pages)
│   └── secrets.php         API keys etc. — NOT in git
├── includes/
│   ├── access_control.php  Auth functions + CSRF functions (loaded everywhere)
│   ├── ConfigManager.php   Singleton config class
│   ├── config_init.php     Bootstraps ConfigManager
│   ├── layout.php          render_display_page() — main template function
│   ├── head-resources.php  CSS/JS <head> content (used by both template systems)
│   ├── navbar.php          Top navigation bar
├── lib/                Shared PHP function libraries
│   ├── moop_functions.php      Organism/assembly listing helpers
│   ├── blast_functions.php     BLAST execution and result parsing
│   ├── functions_access.php    Access helpers (getAccessibleAssemblies, etc.)
│   ├── functions_database.php  PDO/SQLite query helpers
│   ├── functions_validation.php Input validation helpers
│   ├── functions_login_protection.php  Brute-force login protection
│   ├── functions_system.php    handleAdminAjax(), getWebServerUser(), permission helpers
│   ├── permission_check.php    Impact-based filesystem/SELinux checks (dashboard + manager)
│   ├── cache_paths.php         Resolves cache_path (caches live outside organisms/)
│   ├── housekeeping.php        Maintenance tasks — see §9 (interval-throttled, not per-session)
│   └── jbrowse/            JBrowse track management classes (lowercase — case matters)
├── tools/              Public/authenticated user-facing pages (controllers)
│   ├── tool_init.php       Bootstrap for all tool pages (ONE include at top)
│   ├── display-template.php  Calls render_display_page() from layout.php
│   └── pages/          HTML content files for tools
├── js/
│   ├── modules/        Per-feature JavaScript modules
│   │   ├── csrf.js         CSRF token auto-attach for jQuery AJAX
│   │   └── ...
│   └── *.js            Page-specific scripts
├── organisms/          Organism data directories (NOT in git typically)
├── metadata/           JSON metadata (groups, annotation config, taxonomy tree)
├── logs/               Error log + login_attempts.json
├── docs/               Architecture documentation
└── notes/              Planning documents (not user-facing)
```

---

## Core Architecture Patterns

### 1. Bootstrap Files — Always Use These

**For admin pages:**
```php
<?php include_once __DIR__ . '/admin_init.php'; ?>
```
This single line handles: session start, config load, admin auth check (redirects
non-admins), CSRF verification on POST, and common lib includes.

**For tool pages (non-admin):**
```php
<?php include_once __DIR__ . '/../tools/tool_init.php'; ?>
```
Handles: session start, config load, access_control load. Does NOT require login
— access control is done per-page based on what data is being shown.

### 2. The Template System — Use `render_display_page()`

The preferred (newer) pattern separates controller logic from display:

```
Controller (tools/blast.php or admin/manage_users.php)
  → loads data, checks access, prepares $data array
  → calls render_display_page() or include display-template.php

layout.php: render_display_page($content_file, $data, $title)
  → outputs complete HTML page with all libraries loaded
  → includes the content file with $data extracted as variables

Content file (tools/pages/blast.php or admin/pages/manage_users.php)
  → pure HTML/PHP display, no <html>/<head>/<body> tags
  → uses variables that were extracted from $data
```

**Adding a new admin page:**
1. Create `admin/manage_foo.php` — include `admin_init.php`, load data, call `render_display_page()`
2. Create `admin/pages/manage_foo.php` — HTML content only
3. Add `csrf_input_field()` to every `<form method="post">` in the content file
4. CSRF is verified automatically in `admin_init.php` — no extra code needed

**Adding a new tool page:**
1. Create `tools/foo.php` — include `tool_init.php`, check access, load data
2. Create `tools/pages/foo.php` — HTML content only
3. Register in `config/tools_config.php` if it should appear in tool menus

### 3. ConfigManager — All Configuration Through This

```php
$config = ConfigManager::getInstance();
$path   = $config->getPath('organism_data');   // filesystem paths
$str    = $config->getString('siteTitle');      // strings
$arr    = $config->getArray('sequence_types');  // arrays
$bool   = $config->getBoolean('jbrowse2.enabled'); // dot-notation for nested
```

Never read `site_config.php` directly. Never use raw array access on config.
Editable settings go through `config/config_editable.json` (managed via Admin UI),
which is merged over `site_config.php` defaults at startup.

### 4. Access Control Model

Levels (in order of privilege):
- `PUBLIC` — no login required; only public assemblies visible
- `IP_IN_RANGE` — auto-logged in based on IP; full data access, no admin panel
- `COLLABORATOR` — logged in; access to specific organisms/assemblies in `users.json`
- `ADMIN` — logged in; full access + admin panel

Key functions (from `includes/access_control.php`):
```php
is_logged_in()                         // bool
get_access_level()                     // 'PUBLIC' | 'COLLABORATOR' | 'ADMIN' | 'IP_IN_RANGE'
has_access('COLLABORATOR')             // bool — checks level hierarchy
has_assembly_access($organism, $asm)   // bool — checks groups + user access list
require_access('COLLABORATOR')         // exits/redirects if not met
is_public_assembly($organism, $asm)    // bool — checks organism_assembly_groups.json
```

Assembly visibility is controlled by `metadata/organism_assembly_groups.json`.
The special group name `PUBLIC` makes an assembly visible to unauthenticated users.

### 5. CSRF Protection

**Already centralized** — added in the security hardening sprint:
- Token is generated in `access_control.php` on every page load
- `admin_init.php` calls `csrf_protect()` on every POST automatically
- jQuery AJAX calls automatically include the token via `js/modules/csrf.js`
- The `<meta name="csrf-token">` tag is emitted by `head-resources.php`

**When adding new forms:**
- Add `<?= csrf_input_field() ?>` inside every `<form method="post">` tag
- That's it — verification is already handled centrally

**When adding new admin AJAX endpoints (`admin/api/*.php`):**
- Include `admin_init.php` — CSRF is verified automatically
- jQuery will send the token as `X-CSRF-Token` header automatically

### 6. JavaScript Conventions

- JS goes in `js/modules/` (per-feature) or `js/` (page-specific)
- Never embed JS logic in PHP files — use inline_scripts only for PHP→JS variable passing
- Pass PHP values to JS via `inline_scripts` in the `$data` array:
  ```php
  'inline_scripts' => [
      "const sitePath = '/moop';",
      "const organism = '" . addslashes($organism_name) . "';",
  ]
  ```
- Load page-specific JS via `page_script` in the `$data` array
- All pages get: jQuery, Bootstrap, DataTables, and `js/modules/csrf.js` automatically

### 7. Shell Command Safety

All shell commands use `escapeshellarg()` on every argument. Examples:
```php
$cmd = $blast_path . ' -db ' . escapeshellarg($db) . ' -evalue ' . escapeshellarg($evalue);
exec($cmd, $output, $return_code);
```
Never interpolate user input directly into shell strings.

### 8. Database Queries — Always Use Prepared Statements

```php
$dbh  = new PDO('sqlite:' . $db_file);
$stmt = $dbh->prepare('SELECT * FROM feature WHERE feature_id = ?');
$stmt->execute([$feature_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```
Never use `$dbh->query()` with user-supplied values interpolated in.

### 9. Housekeeping — Automatic Maintenance Tasks

`lib/housekeeping.php` runs maintenance tasks from `admin_init.php`. No cron jobs or
external setup required.

**Throttled by INTERVAL, not by session.** `HOUSEKEEPING_MIN_INTERVAL` is 4 hours; results
persist to `logs/.housekeeping_status.json` and are hydrated cheaply into `$_SESSION` on
every admin page load. A long-lived session would otherwise outlive the interval and never
re-run.

**This means dashboard health cards can be up to 4 hours stale.** That is by design —
expensive sweeps (the permission scan, the organism-tree walk) must never run on every
dashboard load. The dashboard is a router ("N issues → go look"), and the linked manager
page re-checks live. Say when a cached figure was taken; the permission card does.

**Adding a new housekeeping task:**
1. Write a function in `lib/housekeeping.php` (keep it fast — no network calls)
2. Add it to the `$tasks` array in `run_housekeeping()`

Current tasks (`run_housekeeping()`):
- `clean_temp_files` — deletes stale BLAST/MAFFT temp files (>24h old)
- `ensure_cache_dir` — creates/repairs the `cache_path` directory
- `snapshot_site_data` — copies changed site data to the backup directory
- `environment_check` — PHP extensions, JWT keys, CLI tools, composer deps
- `permission_check` — filesystem/SELinux impact scan → the dashboard pointer
- `refresh_annotation_caches`
- `refresh_organism_cache` — only if stale
- `ncbi_taxonomy_update`

### 10. Filesystem, SELinux, and the No-Exec Guard — read before touching permissions

**MOOP writes into directories it also serves over HTTP** (caches, generated BLAST/`.fai`
indexes, uploaded images). That is safe only because the web server **refuses to execute
`.php` inside them** — `docs/nginx/moop-security.conf`, or `docs/apache/moop-security.conf`
for Apache. Without that guard, one file-write bug is a persistent webshell. Deploy it on
any new host; `setup-check.php` verifies it.

- **`organisms/` is writable by design.** The web builds indexes in place. Making it
  read-only was tried, broke that, and the `index/`-subdir plan to work around it was
  **evaluated and declined** — see `notes/MOOP_INDEXES_PLAN.md` before re-litigating.
- **`jbrowse2/` must stay read-only.** It is the browser app's own JavaScript, which every
  user's browser runs — and the guard blocks `.php`, not `.js`. Update it from the CLI
  (`npx @jbrowse/cli upgrade`), never via the web server. This is the one tree where the
  filesystem is the real defense.
- **Under SELinux the label — not the Unix mode — decides.** A directory can look perfectly
  writable at `2775` and still be unwritable. `chmod` will not fix it; run
  `sudo scripts/fix_moop_selinux.sh` (RHEL family only; Debian/Ubuntu use AppArmor and do
  not need it).
- **Judge permissions by IMPACT, not an exact mode.** `640`/`660` pass. `644` is
  world-readable — never required, and wrong for restricted data. Never world-writable;
  files never executable (directories need the traverse bit). `lib/permission_check.php`
  implements this.
- **The writable allowlist is hand-maintained** (`moop_permission_check_mode()`), and the
  SELinux label check runs **only** for `writable` paths. A missing entry means no check,
  and the feature dies **silently** — that is exactly how banner upload and organism image
  upload stayed broken for three days in July 2026. If you add code that writes somewhere
  new, add the path to that allowlist and to `scripts/fix_moop_selinux.sh`.

**Gotchas that will cost you an afternoon:**
- **Edited PHP files save as `640 smr:smr`, which php-fpm cannot read → site-wide 500.**
  `chmod 644` any web-served file you edit. The real error is in
  `/var/log/php-fpm/www-error.log` (root-only) — it is not an opcache problem.
- **php-fpm has `PrivateTmp`.** Anything exec'd from a web request gets its own `/tmp`,
  invisible from your shell. "It works in my terminal" proves nothing; pass an explicit
  temp/cache dir.
- **A 404 never proves a deny rule works.** Files unreadable to the web user 404/403 by
  accident. Test with a real `.php` that *would* execute, plus a control copy in a normal
  location.
- **`is_writable()` is owner-biased.** From a CLI shell it answers for *you*, not for
  `apache` — wrong in both directions. Predict the web user's view from mode bits + numeric
  ids (`setup-check.php::webCanWrite()`), or read the housekeeping-persisted result.

Full detail: `docs/SELINUX_AND_HARDENING.md`.

---

## Security Notes (Recent Sprint — March 2026)

The following were added/fixed; keep these patterns:

| Item | Where | Notes |
|------|--------|-------|
| CSRF tokens | `access_control.php`, `admin_init.php`, `js/modules/csrf.js` | Central; just add `csrf_input_field()` to new forms |
| Session fixation fix | `login.php` | `session_regenerate_id(true)` after login |
| Brute-force protection | `lib/functions_login_protection.php` | 5 fails→delay, 10 fails→lockout; state in `logs/login_attempts.json` |
| Path traversal fix | `lib/fasta_download_handler.php` | `realpath()` + base-dir check |
| Admin API auth | `admin/api/generate_registry.php` | Must use `admin_init.php` |
| Trusted server URL fix | `includes/ConfigManager.php::isTrustedTracksServer()` | Prevents subdomain bypass |
| Proxy/IP warning | `includes/access_control.php` | Logs warning if X-Forwarded-For seen with IP ranges active |

---

## Common Admin Tasks

### Add a new organism
Use the Admin Dashboard → Manage Organisms, or place data in
`organisms/{OrganismName}/{AccessionID}/` following the structure in
`organisms/ORGANISM_DISPLAY_README.md` (it lives in the data tree, not the repo root).
For the JBrowse2 side, see `docs/JBrowse2/SETUP_NEW_ORGANISM.md`.

### Add a new tool to the tool menu
Edit `config/tools_config.php` — each entry defines which pages the tool
appears on, its URL path, and what context parameters it accepts.

### Regenerate BLAST indexes
Admin Dashboard → Organism Checklist → click "Build BLAST Index" per assembly.
Or call the API endpoint `admin/api/generate_blast_indexes.php` via POST.

### Change site title / logo / favicon
Admin Dashboard → Manage Site Configuration. Changes go to `config/config_editable.json`.

### Add a new user or change access
Admin Dashboard → Manage Users. User data is stored in `/var/www/moop-private/users.json`
(see the note at the top — it lives OUTSIDE the document root; the path is config-driven via
the `users_file` key, `site_config.php` default overridable in `config_editable.json`).

---

## Repo Structure: App vs. Site Data

The moop git repo contains only application code — everything needed to set up
a new MOOP site from scratch. Site-specific data is versioned separately.

**App repo** (`/var/www/html/moop/`):
- PHP source, JS, CSS, templates, docs
- `.example` template files for config and metadata
- `composer.json` (but not `vendor/` or `composer.phar` — run `composer install`)

**Site-data backup directory** (`site_data_path`; `site_config.php` ships
`/var/www/html/moop-site-data`, and this deployment overrides it to `/var/www/moop-site-data`
via `config_editable.json` — read it through ConfigManager, never from `site_config.php`):
- `config/config_editable.json` — admin-edited settings
- `config/secrets.php` — API keys
- `metadata/*.json` — groups, annotations, taxonomy tree
- `users.json` — user accounts (bcrypt-hashed passwords)
- **Keep this directory private** — it contains credentials

**How snapshots work:**
- `lib/housekeeping.php` → `housekeeping_snapshot_site_data()` runs on the housekeeping
  interval (§9 — at most once every 4h, not once per session)
- Auto-creates the backup directory if it doesn't exist (and writes a README into it)
- Copies changed files to the backup directory
- Git is NOT required, and **MOOP never commits**. If the directory is a git repo, MOOP
  only *reads* its state (`housekeeping_git_status()` — `status --porcelain`, ahead count)
  to render a badge on the dashboard. Committing and pushing are manual, by design; the
  README it writes into the backup directory says so.
- Status is stored in `$_SESSION['site_data_backup']` for the admin dashboard

**Setting up a new deployment:**
1. Clone the app repo
2. Copy `.example` files → remove `.example` suffix, edit values
3. Run `composer install`
4. Site-data backup directory is created automatically on first admin login
5. **Cache directory** — the `cache_path` setting (Admin → Site Configuration) names where
   the app writes regenerable caches. Leave it empty to keep caches inside `organisms/`
   (works out of the box). Better: point it at a directory **outside the document root**
   (e.g. `/var/www/moop-cache`), so `organisms/` holds shipped-in data only — regenerable
   caches do not belong in the data tree. On a hardened (SELinux) host that directory needs
   `apache:apache`, mode `2775`, and an `httpd_sys_rw_content_t` rule;
   `scripts/fix_moop_selinux.sh` creates and labels it. See `docs/SELINUX_AND_HARDENING.md`.
   (This is about **separation**, not locking down `organisms/` — that tree is writable by
   design; see §10.)
6. **Web-server security config** — deploy `docs/nginx/moop-security.conf` or
   `docs/apache/moop-security.conf`. Not optional; see §10.

---

## Known Issues / TODO

Planning docs live in `notes/`. Verified against the tree on 2026-07-16 — several
long-standing entries here had already been fixed, and one contradicted §9 of this file.

**Open:**
- **Medium:** JWT tokens passed as URL query parameter in JBrowse track requests
  (visible in server logs) — architectural constraint from JBrowse2. Two routes exist:
  `notes/TRACKS_PROXY_PLAN.md` (simpler) and an `Authorization`-header variant.
- **Medium:** `Content-Security-Policy` is **Report-Only** (with `'unsafe-inline'`), pending
  a refactor of ~154 inline event handlers (`onclick`/`onchange`/...) to `addEventListener`
  + per-request nonces before it can be enforced.
- **Low:** The Apache no-exec guard (`docs/apache/moop-security.conf`) ships **unverified** —
  written against a working nginx deployment, never run on Apache. Its VERIFY block has the
  exec test; settle it on the first Apache host.
- **Done (2026-07-17):** `users.json` moved OUT of the document root to
  `/var/www/moop-private/` — config-driven (`users_file`), SELinux `httpd_sys_rw_content_t`.
  See the top-of-file note. Docroot deny rules kept as defense-in-depth. Do not re-open.
- **Low:** Two sources of truth decide "does the web write here" — a rule's `why_write` and
  the allowlist in `moop_permission_check_mode()`. They drift, and drift fails silently
  (§10). Deriving `check_mode` from `why_write` would make that class impossible, but
  `why_write` is used inconsistently today (some read-only rules use it to explain *reads*),
  so it needs a cleanup pass first.

**Done — do not re-open:**
- `page-setup.php` deleted (broken CSS URL + dual DataTables loading).
- HTTP security headers in nginx (2026-07-08): `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy` enforced. HSTS is N/A while MOOP is HTTP-only.
- BLAST temp files — `housekeeping_clean_temp_files` handles this (§9); **no cron needed**.
  (This file previously listed both the task and a TODO to add a cron for it.)
- `getBlastDatabases()` no longer uses `global $sequence_types`.
- No `.bak`/`.backup` files remain in the tree.
- SRI hashes — **moot for the served app, but the old claim here was wrong.** This file used to
  state flatly that there were no CDN `<script>` tags. `admin/manage_annotations.php` was
  loading jQuery UI (JS **and** CSS) from `code.jquery.com` with no `integrity=`, which is also
  a `script-src 'self'` CSP violation — so enforcing the CSP would have silently killed that
  page's reorder UI. Vendored to `js/vendor/jquery-ui.min.js` on 2026-07-20 (checksum verified
  against upstream); its stylesheet was replaced by the one rule the page actually used
  (`css/manage-annotations.css`). **Now true:** every third-party library in the served app is
  self-hosted under `js/vendor/` and `css/`, so there is nothing to hash.
  One deliberate exception: `setup.php` pulls Bootstrap CSS from jsDelivr. It self-disables
  (403) once `config/config_editable.json` exists, so it is unreachable on a configured
  deployment — but on an air-gapped host the installer will render unstyled.
