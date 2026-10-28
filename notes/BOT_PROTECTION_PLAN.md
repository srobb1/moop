# Bot protection: keep crawlers and AI scrapers from draining the site

Written 2026-10-28. Goal (Sofia): "do as much as we can to keep our site from too many hits, too
many db queries, too many downloads. Real ones are good. Bots and AI are a drain on our
resources." The Tripal/Drupal sites suffer from this today; MOOP is still VPN-only, so nothing
has hit it yet — this is the window to have it in place before launch.

What we are protecting is **capacity**, not secrecy. Restricted data is already protected by
access control (a restricted gene page answers "not found" whether the URL was guessed or not),
and public gene IDs are in the public downloads anyway, so URL enumeration itself is not the
threat. The threat is load: the capacity test (2026-10-23) showed RAM runs out before CPU, and
every gene page is several SQLite queries against a corpus that is mostly cold.

The modern problem is the AI-training crawler: it requests every URL it can find, often from
thousands of IPs with browser-like User-Agents, and ignores robots.txt. Per-IP limits alone do
not stop that; a challenge that costs a real browser nothing and a mass crawler a lot does.

## State found 2026-10-28

- No robots.txt, no nginx `limit_req`/`limit_conn`, no app-level throttling.
- Turnstile: site key + secret configured, `enabled: false`. The gate in
  `includes/access_control.php` (anonymous visitors, once per session) is live code, but:
  - 🔴 `verify-human.php` was deleted in ac0ce979 (2026-06-09) as presumed dead. Enabling
    Turnstile today would redirect every anonymous visitor to a 404 — the public site dies.
  - 🔴 The deleted page used `/turnstile/v1/` endpoints. They 404 (checked); the real ones are
    `v0`. It never could have verified anyone. It also read `$return` before assigning it, and
    accepted `//evil.com` as a same-origin return (open redirect).
  - 🟡 Saving Site Configuration copies the Turnstile **secret** into `config_editable.json`
    (664, snapshotted into the site-data backup), where it then OVERRIDES `secrets.php` — a
    rotated secret would be silently ignored.
- Cloudflare is reachable from this host (siteverify answers 200). Cloudflare's published test
  keys work on any hostname, including a bare IP — so the challenge CAN be built and tested
  end to end before launch, with the real key swapped in at launch.

## Layers — each one catches what the one before lets through

| # | Layer | Stops | Where | Cost to real users |
|---|---|---|---|---|
| 1 | `robots.txt` | polite crawlers (Google, Bing, honest AI bots) | static file | none |
| 2 | Turnstile gate, rebuilt | the mass AI crawler — never reaches PHP data code or SQLite | MOOP | ~1 s once per session, usually invisible; logged-in + internal IPs skip it |
| 3 | nginx per-IP rate limits | any single client hammering, verified or not | nginx (root) | none at human speed |
| 4 | Heavy-endpoint limits | bulk downloads / exports / BLAST from one client | nginx + app | a real user downloading many files at once may wait |
| 5 | Traffic report → dashboard | knowing it is happening before the site is slow | root timer + card | none |
| 6 | (later) nginx cache of anonymous gene pages | repeat hits costing PHP + SQLite | nginx | none |

### 1. robots.txt
Disallow the generated pages (gene, search, exporter, BLAST, downloads, api/); allow home,
organism and group pages so the site stays findable. Point to Downloads as the bulk-data route.

### 2. Turnstile, rebuilt properly
- New `verify-human.php`: `v0` endpoints, return URL validated as same-site path (no `//`,
  no scheme), real stylesheet, the site's look.
- Requests that are not page loads (AJAX, `api/`, downloads) get a **403/429 JSON**, not a
  302 to an HTML challenge a script cannot follow.
- Exempt: logged-in users (already), internal/VPN IP ranges, robots.txt, static assets.
- If Cloudflare cannot be reached: DECISION NEEDED (fail open = site stays up, unprotected
  for that moment; fail closed = site unusable for anonymous visitors while Cloudflare is out).
- CSP: allow `https://challenges.cloudflare.com` in script-src and frame-src, on the verify
  page only.
- Secret: only ever from `secrets.php`; stop writing it into `config_editable.json`; remove
  the copy already there.
- Tested now with Cloudflare's always-pass and always-fail test keys; real key at launch.
- Known gap: the gate runs after the PHP session starts, so a cookie-less bot creates a
  session file per request before being turned away. nginx limits (3) bound how many.

### 3. nginx per-IP limits (needs root — written here, installed by Sofia)
`limit_req` zone on PHP requests (~5/s per IP, burst ~20, `nodelay`), `limit_req_status 429`,
internal ranges exempt via a `geo` map. Static files unlimited.

### 4. Heavy endpoints
Stricter `limit_req` (~1/s, burst ~5) and `limit_conn` (2 concurrent per IP) on the Data
Exporter, FASTA/sequence downloads, genome_file.php and BLAST submit. BLAST already has its
queue (blast_queue.php); exports could get the same flock slot design if measurement shows
they need it (piece 1b measured them as NOT needing slots for real users).

### 5. Visibility
Root-run daily report (same pattern as the security-updates timer: root writes into
cache_path, the dashboard reads it) — requests per IP, top User-Agents, 429s served,
challenges passed/failed. nginx logs are root-only, so this is the only way MOOP can see them.

## Progress

**2026-10-28 — layer 2 built (uncommitted at time of writing), tested with Cloudflare test keys:**
`lib/human_check.php` (decision, safe return, siteverify v0), `verify-human.php` +
`tools/pages/verify_human.php` + `js/verify-human.js`. Live test from 127.0.0.1 (anonymous):
redirect → widget passes → back to the requested page, session stays verified; always-fail
secret → stays on the page, waits for "Try again" (no auto-resubmit loop); curl → JSON 403;
internal IP → no challenge; `//evil.com` return → home. Secret now only from secrets.php
(ConfigManager strips it from the editable block; the save no longer writes it; the stale
copy removed from config_editable.json).

**CSP crawl (every user page + JBrowse + a live search):** MOOP itself zero violations.
JBrowse loaded the MAF plugin from jbrowse.org (now self-hosted: js/vendor, pinned 1.6.1 in
vendor_assets.txt — byte-identical to what jbrowse.org served) and sent sessions to Google
Analytics + analytics.jbrowse.org (now `disableAnalytics: true`). After: JBrowse contacts no
outside host. Enforcement = nginx edit by root (enforce script, adds challenges.cloudflare.com
script+frame, upload.wikimedia.org img, form-action 'self').

**CSP ENFORCED 2026-10-28** (Sofia ran the script; backups /etc/nginx/nginx.conf.bak-20261028-*).
First enforcement broke every JBrowse track: its worker fetches a `data:` WebAssembly module,
and worker violations never reach the page console, so the report-only crawl had missed it.
`data:` added to connect-src; re-verified with CDP Audits issues (workers included): 24 pages
+ JBrowse, zero violations, gene track draws; Turnstile pass flow passes under enforcement.

**Found on the way:** `/var/www/html/info.php` was a live phpinfo() page — deleted 2026-10-28.

## Order
1 + 2 first (all MOOP code, testable now), then 3 + 4 (nginx, root), then 5. 6 only if
measurements after launch say so.
