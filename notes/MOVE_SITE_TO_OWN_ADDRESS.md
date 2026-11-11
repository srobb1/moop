# Moving a MOOP site to its own address — checklist

Two moves may use this (2026-11-09: cuttingclass is fast-tracked; simrbase dropping `/moop` is LIKELY but NOT decided — keeping `/moop` costs nothing, the code works either way):

1. **simrbase → `simrbase-dev.stowers.org/`** (drop `/moop`). simrbase has no real users yet, so it
   is the rehearsal: the big site, ~300 stored JBrowse genome paths, every nginx rule.
2. **cuttingclass → `cuttingclass.stowers.org/`** (its own domain, replacing the old Drupal site).
   The fast-tracked one. Same recipe, plus the old-link redirects
   (`notes/CUTTINGCLASS_PLAN.md` §4).

Do each in ONE maintenance window, ideally together with the HTTPS cert, so the server changes once.

**2026-11-11 — decided:** simrbase drops `/moop` and becomes `simrbase.stowers.org` (today the OLD
Drupal/Tripal site on another server). Sofia will likely move **simrbase, cuttingclass and
planosphere to this server at the same time**, so each gets its OWN server block by host name
(`root` = the site folder, `link_prefix` `"/"`) -- the layout `nginx_site_guard.sh` and the
rate-limit template already handle -- not a shared-block rewrite. Build the blocks ahead under the
real names (DNS still points at the old servers, so only we reach them: test with
`curl --resolve` / a browser host-resolver rule); have each 301 `/<folder>/…` → `/…` so the
rehearsal works before `link_prefix` flips. Switch day: `link_prefix` "/", `install_https_redirect.sh`
per name, simrbase-dev + the bare IP 301 to the new names, IT repoints DNS and opens the server,
old-site link tables live. The switch waits on DATA (IT is ready). **Planosphere = a landing page**
(decided 2026-11-11): its block serves a small page pointing at simrbase's planarian tools
(Anatomical Expression Search, Rosetta Stone, Planarians group, BLAST), and old Planosphere
addresses redirect to the matching simrbase tool -- no third MOOP.

## Already done in code (2026-11-09) — the "link prefix"

- Setting `link_prefix` (default `/` + folder name → nothing changes until it is set).
  For a site at its domain's root it is **`"/"`**, NOT `""` — ConfigManager ignores an empty
  override and would quietly keep `/moop`.
- Every link is built with `moop_url_base()` / `moop_url()` (`includes/config_init.php`), never
  from `site`. `site` stays the FOLDER name (cookies, caches, site-data dirs) and does not change.
- JBrowse: stored configs keep `/moop/data/genomes/…` as a "this site" marker on purpose;
  `api/jbrowse2/config.php` matches the folder and emits the prefix. No config rewriting.
- Shown (read-only) in Site Configuration → "Where the site's paths come from".

## Left for move day — nginx and the tools that mirror it

Each of these is keyed on `/moop/` (or `/cuttingclass/`) INSIDE nginx or a script, not in
MOOP's PHP, so the code change could not do them. **The dangerous ones fail silently: a rule
that stops matching gives no error, it just stops protecting.**

- [ ] **Security rules** — generate them: `scripts/nginx_site_guard.sh <folder>` now reads the
      site's `link_prefix` (or take it as 2nd argument: `nginx_site_guard.sh cuttingclass /`); for a
      site at the root it keeps the server-wide rules too (own server block). Code DONE 2026-11-09.
      `docs/nginx/moop-security.conf` (deployed:
      `/etc/nginx/default.d/moop-security.conf`, `cuttingclass-security.conf`): 16 `location`
      lines denying `config/ logs/ metadata/ certs/ organisms/ data/genomes/` and blocking `.php`
      in the writable trees. Rewrite for the new address. **VERIFY each with a real file**
      (CLAUDE.md §11: a 404 proves nothing): a `.php` that would execute in a writable tree must
      NOT run; `config/config_editable.json` and `logs/…` must NOT download.
- [x] **Rate limits** — code DONE 2026-11-09: the template says `@PREFIX@`, filled from the
      link prefix by `install_nginx_limits.sh` and the collector (identical output for /moop).
      Move day: re-run `sudo scripts/install_monitor.sh` so root's copies are the new ones.
- [x] **System Monitor collector** — code DONE 2026-11-09: MOOP publishes `link_prefix` in
      `nginx_wanted.json`; the collector checks it and fills the rules. (`moop_monitor_class()`
      works on site-relative paths and needed no change.)
- [x] **`setup-check.php`** — code DONE 2026-11-09: uses the link prefix (`$sc_prefix`); its
      guard check calls `nginx_site_guard.sh`, which reads the prefix itself.
- [x] **`scripts/render_compare.php`** — code DONE 2026-11-09: addresses use the link prefix.
- [ ] **JBrowse auth gateway** — `cuttingclass-site.conf` rewrites
      `/cuttingclass/jbrowse2/index.html` → `auth_gateway.php`; same rule for the new address.
- [ ] **The server block** — serve the folder at `/` for that host name (or `root` = the folder).
      For simrbase-dev with cuttingclass still at `/cuttingclass/` on the same host: the
      `/cuttingclass/` locations must stay more specific than simrbase's root rules, and each
      site's security rules must not shadow the other's.
- [ ] **301 the old address** — `/moop/(.*)` → `/$1` (simrbase); for cuttingclass, the old
      `simrbase-dev…/cuttingclass/*` → `https://cuttingclass.stowers.org/*`, plus the Drupal map.
- [ ] **Old-site redirects (cuttingclass)** — answered by nginx alone, no PHP:
      `php scripts/build_legacy_redirects.php /var/www/html/cuttingclass <dir>` writes
      `legacy-map.conf` (→ `/etc/nginx/conf.d/`) and `legacy-server.conf` (`include` inside the
      cuttingclass server block, BEFORE its `location`s). Table: the site's
      `metadata/legacy_redirects.json`. Old pages → new pages (language kept), other old paths →
      home, Drupal attack paths and `/?q=` → 444. Tested 2026-11-09 on a private nginx (all 196
      crawled addresses, MOOP's own `/admin/` and `?q=` pages pass through). Re-run the test the
      same way after any table change: never block a name the new site uses.
- [ ] **Set `link_prefix` to `"/"`** in that site's `config/config_editable.json` — same moment
      as the nginx reload.
- [ ] **Tracks server CORS** — allow the new origin (`map $http_origin`), or JBrowse tracks fail.
- [ ] **robots.txt** — belongs to a HOST, and today every host gets moop's copy
      (`moop-ratelimit.conf`: `alias /var/www/html/moop/robots.txt`), written for `/moop/`.
      Generate the host's file: `php scripts/build_robots.php /var/www/html/cuttingclass >
      <file>` (several site folders for a host serving several sites), and point that host's
      `location = /robots.txt` at it. The repo's robots.txt stays the one source of the rules.
      Until then, `/cuttingclass/` on simrbase-dev has no robots rules at all (harmless while that
      name only resolves inside Stowers).
- [ ] **Turnstile human check — turn it ON for cuttingclass at launch** (built, off).
      Cloudflare dashboard → Turnstile → a NEW widget for cuttingclass (its own keys; simrbase
      keeps its own). Hostnames: `cuttingclass.stowers.org` (+ `simrbase-dev.stowers.org` only
      to test before the move; remove after). Turnstile checks the HOST NAME, never the path, and
      takes no IP addresses. Keys go in `cuttingclass/config/secrets.php` (`turnstile.site_key`,
      `turnstile.secret_key`; the file does not exist yet), then `enabled` in Site Configuration. Measured 2026-11-09: a link-following bot finds only 50 of ~77,000
      cuttingclass gene pages (all from the Primers page), so the Tripal-style crawl does not work;
      a bot that takes IDs from a Downloads FASTA is held to 2 pages/s per address by nginx, but a
      crawler spread over thousands of addresses (the pattern on the Tripal sites) is only stopped
      by Turnstile. notes/BOT_PROTECTION_PLAN.md.
- [ ] **CSP** — unchanged (all `'self'`), but re-check JBrowse tracks load (worker issues, CLAUDE.md).

## Verify after the switch

- [ ] `setup-check.php`: 0 FAIL. Dashboard permission card clean.
- [ ] Walk the pages: home, search, organism, gene page, BLAST run, Retrieve Sequences, Data
      Exporter export, Downloads, JBrowse (open a track, a genome, a gene), login/logout, admin.
- [ ] No link in any page still starts with the old prefix (capture pages, grep for `/moop/`).
- [ ] Old address 301s land on the right page.
- [ ] CLAUDE.md, README, `docs/INSTALL.md`: say how a site is served at its own address.
