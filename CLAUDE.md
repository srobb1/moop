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

- Web root: `/var/www/html/moop/` (symlinked from `/data/moop/`)
- Users file: `/data/users.json` (intentionally outside web root)
- Organism data: `/var/www/html/moop/organisms/{organism}/{assembly}/`
- SQLite databases: one per organism at `organisms/{organism}/organism.sqlite`

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
│   ├── functions_system.php    handleAdminAjax(), file permission helpers
│   ├── housekeeping.php        Maintenance tasks that run once per admin session
│   └── JBrowse/            JBrowse track management classes
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

`lib/housekeeping.php` runs lightweight maintenance tasks once per admin session
(called from `admin_init.php`). No cron jobs or external setup required.

**Adding a new housekeeping task:**
1. Write a function in `lib/housekeeping.php` (keep it fast — no network calls)
2. Add it to the `$tasks` array in `run_housekeeping()`

Current tasks:
- `housekeeping_clean_temp_files` — deletes stale BLAST/MAFFT temp files (>24h old)

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
`ORGANISM_DISPLAY_README.md`.

### Add a new tool to the tool menu
Edit `config/tools_config.php` — each entry defines which pages the tool
appears on, its URL path, and what context parameters it accepts.

### Regenerate BLAST indexes
Admin Dashboard → Organism Checklist → click "Build BLAST Index" per assembly.
Or call the API endpoint `admin/api/generate_blast_indexes.php` via POST.

### Change site title / logo / favicon
Admin Dashboard → Manage Site Configuration. Changes go to `config/config_editable.json`.

### Add a new user or change access
Admin Dashboard → Manage Users. User data is stored in `/data/users.json`.

---

## Repo Structure: App vs. Site Data

The moop git repo contains only application code — everything needed to set up
a new MOOP site from scratch. Site-specific data is versioned separately.

**App repo** (`/data/moop/`):
- PHP source, JS, CSS, templates, docs
- `.example` template files for config and metadata
- `composer.json` (but not `vendor/` or `composer.phar` — run `composer install`)

**Site-data repo** (`site_data_path` in `site_config.php`, default `/data/moop-site-data/`):
- `config/config_editable.json` — admin-edited settings
- `config/secrets.php` — API keys
- `metadata/*.json` — groups, annotations, taxonomy tree
- `users.json` — user accounts (bcrypt-hashed passwords)
- **Keep this repo private** — it contains credentials

**How snapshots work:**
- `lib/housekeeping.php` → `housekeeping_snapshot_site_data()` runs once per admin session
- Copies changed files to the site-data repo and auto-commits
- If the site-data directory doesn't exist, the admin dashboard shows setup instructions
- Setup commands use the detected web server user/group for correct ownership

**Setting up a new deployment:**
1. Clone the app repo
2. Copy `.example` files → remove `.example` suffix, edit values
3. Run `composer install`
4. Create the site-data repo (see admin dashboard prompt for commands)

---

## Known Issues / TODO (from March 2026 review)

See `notes/IMPROVEMENT_ROADMAP.md` for the full list. Top remaining items:

- **RESOLVED:** `page-setup.php` deleted — removed broken CSS URL bug and dual DataTables loading
- **Medium:** HTTP security headers (`X-Frame-Options`, `X-Content-Type-Options`,
  `Content-Security-Policy`) not set — add to nginx/Apache config
- **Medium:** JWT tokens passed as URL query parameter in JBrowse track requests
  (visible in server logs) — architectural constraint from JBrowse2
- **Medium:** BLAST temp files accumulate — add cron: `find /tmp -name 'blast_*' -mtime +1 -delete`
- **Low:** `getBlastDatabases()` in `blast_functions.php` uses `global $sequence_types` — pass as param
- **Low:** Backup files (`.bak`, `.backup`) in production — delete and rely on git
- **Low:** `test_input()` in `functions_validation.php` is deprecated — remove callers then delete
- **Low:** `validateOrganismParam()` hardcodes `/moop/index.php` — use ConfigManager for site name
- **Low:** No SRI hashes on CDN `<script>` tags
