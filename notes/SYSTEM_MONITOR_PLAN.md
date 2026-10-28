# System Monitor — resources, traffic, who, and what to limit

Written 2026-10-28. Sofia: "easily monitor our system resources, know what is happening, what
pages and if possible by who, and the traffic" — and never have to remember to re-run an nginx
script or edit `moop-ratelimit-http.conf`.

Companion to `notes/BOT_PROTECTION_PLAN.md` (layer 5 there). Capacity context: the 2026-10-23
capacity test found RAM, not CPU, breaks first; this host has 8 CPUs, 15 GB RAM, php-fpm
`pm.max_children = 50`, `/var` and `/tmp` 4 GB each.

## Decisions (Sofia, 2026-10-28)

- **nginx sync is automatic.** The root timer applies changes to the exempt ranges and the heavy
  group itself (validated, `nginx -t`, restore on failure) and the dashboard says what it did.
- **Per-request log kept 30 days.** Daily summaries may be kept longer.
- **Alerts: dashboard only for now.** The host cannot send mail (no MTA). Revisit (IT relay or a
  webhook) once we know which thresholds matter.

## Three sources — none sees everything alone

| Source | Sees | Runs as |
|---|---|---|
| MOOP request log (shutdown function) | script, method, status, wall ms, **peak memory**, rate-limit class, **who** (username / access level), client address | php-fpm (apache) — appends one line per request to `{cache_path}/metrics/requests-YYYY-MM-DD.tsv` |
| Root timer `moop-monitor`, every minute | CPU (from `/proc/stat` deltas), RAM, swap, load, disk (`/`, `/var`, `/tmp`, cache, data), php-fpm status (busy/idle/queue/max-children-reached), nginx access-log increments (status codes, 429s, bytes, user agents), nginx error log (`limiting requests`) | root — nginx logs and php-fpm status are root/localhost-only. Writes JSON into `{cache_path}/monitor/`; MOOP only READS it (same pattern as the security-updates report) |
| php-fpm status page | worker saturation — the earliest warning: all 50 busy = requests queue, site feels hung while CPU idles | `pm.status_path` (currently commented out) + an nginx location allowed from 127.0.0.1 only |

**Root never executes MOOP code.** MOOP writes what it wants nginx to have —
`{cache_path}/monitor/nginx_wanted.json` (exempt ranges + heavy endpoints) — and the root timer
re-validates every value strictly (IPv4 addresses; endpoint names `^[a-z0-9_]+$` that exist as
files under `api/` or `tools/`) before generating the conf. A compromised admin account can at
most change which addresses are exempt or which endpoints count as heavy; it cannot inject nginx
configuration. (install_nginx_limits.sh ran `php -r` on MOOP's config as root; the timer must not.)

## The admin page (Admin → System Monitor)

- **Now** — CPU, RAM, swap, load, php-fpm busy/total, disk; each with a plain-language status.
- **Charts** — 24 h (per minute) and 7 days (per hour): CPU, RAM, requests, php-fpm busy, 429s.
- **Traffic** — requests by class (page/api/jbrowse/heavy/static), status codes, 429s; top
  addresses; user agents grouped as browser / search engine / AI crawler / script / unknown.
- **Heaviest pages** — per script: count, total time, mean and p95 time, mean and peak memory.
- **Who** — top users and addresses by total time and by peak memory.
- **Slowest requests** — last N over a threshold, with who and when.
- **Recommendations** — (1) heavy-group candidates: scripts whose measured p95 memory or time is
  high but which are not in the heavy group, each with an **Add to heavy group** button;
  (2) php-fpm saturation seen; (3) RAM headroom vs `max_children` × measured peak memory;
  (4) disk filling; (5) nginx limits out of sync / last sync result; (6) "site is public —
  enable Turnstile" when traffic from outside the exempt ranges appears.

Also: BLAST queue depth and wait, 5xx/502/504 counts, the php-fpm slow log (written today,
read by nobody).

## Pieces (each safe on its own)

1. **Request log** — `lib/request_metrics.php` (shutdown function, one append per request),
   housekeeping deletes files older than 30 days, aggregation function + smoke tests. No root.
2. **Root timer** — `scripts/install_monitor.sh`: `/usr/local/sbin/moop-monitor` + systemd
   timer (every minute), php-fpm status enabled, nginx localhost status location. Replaces
   running `install_nginx_limits.sh` by hand (it becomes the timer's sync step).
3. **Heavy group in MOOP config** — moves out of the nginx template; `nginx_wanted.json`
   written by MOOP; the timer's sync applies it.
4. **Admin page** — Now / charts / traffic / heaviest / who / slowest.
5. **Recommendations** + Add-to-heavy button; dashboard card linking to the page.

## Progress

- **Piece 1 DONE** 2026-10-28 (ea20ae8f): request log live.
- **Piece 2 DONE** 2026-10-28: collector installed by Sofia (`sudo scripts/install_monitor.sh`),
  sampling every minute; php-fpm status on (127.0.0.1 only); nginx sync in_sync on first run
  (rendered conf byte-identical to the hand-installed one, so no reload). The installer is also
  the single new-install step for the protect-and-watch layer: it ships the security headers +
  CSP (`docs/nginx/moop-headers.conf`) — which until now existed ONLY in this host's nginx.conf,
  so a new install would have had none — and is wired into README step 7, docs/INSTALL.md,
  setup-check.php ("Protection & System Monitor") and CLAUDE.md's deployment list.
- **Piece 4 DONE** 2026-10-28: Admin → Maintenance → System Monitor (`admin/system_monitor.php`).
  Right-now tiles (status = icon + word, never colour alone), five single-measure charts (inline
  SVG, crosshair + keyboard, gaps break the line, data table underneath), traffic by user-agent
  kind / request kind / status, busiest addresses (internal marked), heaviest pages, who, slowest
  requests, rate-limit sync. Checked logged in as the test admin: no JS errors, no CSP issues,
  no horizontal scroll at 1440 or 390 px.
- First finding from it: `api/jbrowse2/config.php` takes ~3.2 s and ~28 MB on every genome-browser
  open — the slowest public request. Worth investigating (cacheable?).
- **Pieces 3 + 5 DONE** 2026-10-28: heavy group = `rate_limit_heavy` (site_config.php default, overrides
  in config_editable.json), published in nginx_wanted.json, validated by the collector (shape +
  existing file) and rendered as ONE map line. Page: Recommendations (saturation, RAM/swap, workers vs
  RAM, CPU, heavy candidates with an Add button, 5xx per page, crawler exposure, disk, sync errors),
  Capacity (when to add RAM / CPUs / workers + how to change pm.max_children), Heavy group chips with
  Remove. Collector and install script render byte-identically (an `awk -v` escape bug turned `\.php`
  into `.php` — fixed by passing values through ENVIRON). Needs `sudo scripts/install_monitor.sh`
  re-run to take the new collector + template; its first sync rewrites the map (two heavy lines → one)
  and reloads nginx once.

## Privacy

Usernames and addresses are personal data: admin-only page, 30-day retention, never in the
site-data backup, never leaves the host.
