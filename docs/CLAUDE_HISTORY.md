# CLAUDE.md history

Moved verbatim out of CLAUDE.md on 2026-11-11 to keep the per-session file short. The RULES stay in CLAUDE.md; this holds the measurements, incident stories and finished items behind them.

## Query timings (from §9)

**Measured, on this host (figures refreshed 2026-10-21):** 85 databases totalling **33 GB**
against ~12 GB of page cache, so most data is still cold most of the time. The data volume
now answers a random read in **0.55 ms** (`notes/bench/disk_random_read.py`, O_DIRECT; it was
32 ms in July — `lsblk` still reports it rotational because it is a VMware virtual disk, so
trust the latency, not the flag). `COUNT(*) FROM annotation` on Procerodes_sp takes **8–18 ms
cold and ~2 ms warm** (eviction verified with mincore). A cold cross-organism search over the
49 bats takes **3–6 s** through the real endpoint. Cold still costs a multiple of warm, and
every megabyte added to a database still competes for that cache — the lean-schema argument
above stands — but cold is no longer catastrophic. Before optimising SQL, measure cold AND
warm, and check you are not actually measuring the disk.

The search's **unfiltered fast path** (the per-type quota pool) is still worth 2–3× on a cold
cross-organism search over any filtered search (helicase 5.7 s vs 10.2 s, binding 2.9 vs
8.9 s). Anything that sends a filter by default throws that away — the main Search page did,
from June to 2026-10-21, because "every type ticked" was sent as a list of every type.

⚠️ **Earlier revisions of this file said 66 GB / 7,051 ms cold, then 401 ms cold.** Each was
true when written. The first drop came from the 2026-07-30 reload and the 2026-07-31
`VACUUM` (same SQL, same rows, 17.6× faster purely from where the bytes sit); the second from
the storage change (32 ms → 0.55 ms random reads, found 2026-10-21). Do not quote the old
multipliers. The `notes/` files below predate the storage change — their mechanisms hold,
their absolute timings do not.

Numbers, fast/slow query shapes and the cross-organism fan-out cost:
`notes/QUERY_PERFORMANCE.md`. The cold-search cost model, and why `bm25()` was removed from
the search fast path, is `notes/SEARCH_COST_MODEL_2026-07-31.md`.

## Results-table defects, 2026-07-23 (from §9b)

What prompted this: the results-table work on 2026-07-23. The table was hand-written three
times with **positional** column identity (`data-column-index="6"`, `columnDefs targets: 6`),
so adding two columns meant renumbering ~18 sites by hand. Reading it turned up four defects
nobody had reported, three of them silent:

- Turning on a hidden column via colvis produced a header with no cells under it, so every
  later heading sat above the wrong data (`.export-only` CSS fighting DataTables visibility).
  The user had noticed this one and assumed it was unfixable.
- One organism's row selection satisfied another organism's export guard → a headers-only
  CSV with no warning.
- FASTA read the assembly from the first ticked row **anywhere on the page**, so it could
  request one organism's features against another's assembly — wrong data, not a wrong-looking
  page.
- The gene page's `Organism` column, tagged `export-only` precisely so it would appear in
  downloads, was dropped from every export by a hardcoded `idx === 0`.

**The pattern behind all four: identity expressed positionally, and DOM reads done page-wide
in a UI that renders the same component many times per page.** Both are worth flagging on
sight. The fix in each case was to name things — a column registry keyed by `key`, export
rules keyed by CSS class, every selection read scoped to its own DataTables instance
(`$(dt.rows().nodes())`, never `$(...)` on the document).

Corollary: **a workaround comment is a lead, not a conclusion.** "For print, only include
visible columns so header and data stay aligned" documented a symptom of the colvis bug; once
the real cause was fixed the restriction was pointless. When a comment explains why something
is limited, check whether the reason still holds.

## Housekeeping run record (from §10)

**A run that stops finishing is loud, not silent (2026-10-30).** The lock is a `flock`, which
the kernel drops however the process ends (it was a PID file; a run that `die()`d left a zombie
and blocked every run for 1.5 h). Every run keeps a record in the status file
(`housekeeping_runs`: start, current task, finish, per-task result); a shutdown function writes
which task a run died in and the message. `housekeeping_health()` turns the Housekeeping card —
and a banner atop the dashboard — red when a run died, or a full run was *requested* (the
marker's mtime) over 15 min ago and none has finished since. Not "no run for 2 h": runs only
happen while admins visit, so that rule went red after every quiet night (fixed 2026-11-03).
Background-child output still goes to `/dev/null`, so the record is the only place a death
shows: keep it that way, don't add a log nobody reads.

## Done — do not re-open (from Known Issues)

**Done — do not re-open:**
- **HTTPS (2026-11-11).** `simrbase-dev.stowers.org` (internal DNS) serves the renewed
  `*.stowers.org` cert (to 2027-04-12, chain verifies); IT changed the tracks server's CORS to
  accept both origins. Port 80 now 301s browsers to `https://simrbase-dev.stowers.org$request_uri`
  — `scripts/install_https_redirect.sh <hostname>`; setup-check reports it. **This machine's own
  addresses stay on http** (127.0.0.1, ::1, 172.16.2.52): the System Monitor's fpm-status,
  `render_compare.php` and the page audit call `http://127.0.0.1/` — a 301 there would break them
  silently. To test the redirect from here, use another loopback source:
  `curl -sI --interface 127.0.0.2 -H 'Host: simrbase-dev.stowers.org' http://127.0.0.1/moop/`.
  The session cookie stays **conditional** on HTTPS (`includes/session_init.php`) on purpose:
  every browser request is now https, so it is Secure in practice, and making it unconditional
  would silently break login on a new install that has no TLS yet. Still to do: HSTS (after the
  redirect has run cleanly for a while — browsers cache it, hard to undo); Turnstile hostnames
  at launch.
- `page-setup.php` deleted (broken CSS URL + dual DataTables loading).
- HTTP security headers in nginx (2026-07-08): `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy` enforced. HSTS not yet — see the HTTPS entry above
  (the hostname and redirect exist since 2026-11-11; add HSTS once the redirect has run cleanly).
- Session cookie attributes (2026-07-21): `HttpOnly` + `SameSite=Lax` always, `Secure` when
  the request arrived over HTTPS. Set in **one** place — `includes/session_init.php`. Every
  entry point that starts a session must call `moop_session_start()`, never `session_start()`
  directly: `session_set_cookie_params()` has no effect after the session begins, so a direct
  call silently issues a cookie with none of these protections.
  **Cookie names are per site** (`moop_cookie_name()`, 2026-11-04): two MOOP sites on one host
  share cookies (a cookie goes to every path on its host), so one name meant ONE session for both
  -- a simrbase admin would have been an admin on cuttingclass. The name carries the site's
  FOLDER (`MOOPSESS_cuttingclass`, `moop_lang_cuttingclass`); the folder `moop` keeps the bare
  `MOOPSESS` / `moop_lang`. Any new cookie: name it through `moop_cookie_name()`.
- Admin API CSRF (2026-07-21): all `admin/api/*.php` endpoints bootstrap via `admin_init.php`
  (auth **+** CSRF). `admin_access_check.php` alone checks the role but not the token, which
  left seven JBrowse endpoints authenticated but forgeable. Note `js/modules/csrf.js` wraps
  `window.fetch` globally and covers jQuery via `ajaxSetup`, so callers need no change — the
  browser already sends the token; only the server has to check it.
- BLAST temp files — `housekeeping_clean_temp_files` handles this (§10); **no cron needed**.
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
