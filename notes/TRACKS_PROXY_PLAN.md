# Tracks Reverse-Proxy Plan

Status: **designed + validated, NOT deployed** (2026-07-09). PHP foundation staged and dormant;
no nginx block live. Nothing in production behaves differently yet.

## Problem this solves

JBrowse fetches remote track files (bigWig/BAM/GFF) directly from the tracks server
(`https://tracks.stowers.org:8080`). Because that is a *different origin* than the MOOP page, the
browser enforces CORS: the tracks server's `Access-Control-Allow-Origin` must exactly match the
MOOP page's origin (scheme + host + port). This coupling caused the 2026-07-09 outage — the page
was `http://172.16.2.52` but the tracks allowlist was `https://172.16.2.52`, so every track fetch
was blocked. It also means every browser must be able to resolve and reach `tracks.stowers.org`.

A same-origin reverse proxy removes the whole class of problem: the browser fetches tracks from
MOOP's own origin (`/moop/tracks-proxy/...`), and MOOP forwards server-to-server to the tracks host.
No cross-origin request → no CORS, no preflight, no allowlist to keep in sync. Clients never resolve
the tracks host.

**External-launch payoff:** the forward hop (MOOP box → `172.16.2.31`) is internal and does not
depend on where users are. When MOOP gets a public IP, external users hit MOOP, JBrowse fetches
same-origin, MOOP forwards internally. The tracks server can stay fully private — no public NAT,
no internet exposure, no CORS change. The direct approach would need IT to reopen the tracks public
NAT *and* add the new public origin to the tracks CORS allowlist. The proxy needs neither.

## The nginx block

Add to BOTH the `:80` and `:443` server blocks in `/etc/nginx/nginx.conf` (both, so plain `http`
works and users don't have to remember `https`):

```nginx
    # Same-origin proxy to the tracks server. Browser fetches /moop/tracks-proxy/... on MOOP's
    # own origin; nginx forwards to the tracks host. No CORS, no client DNS dependence.
    # ^~ is REQUIRED: without it the `location ~ \.php$` regex captures tracks.php and hands it
    # to local PHP instead of proxying it.
    location ^~ /moop/tracks-proxy/ {
        proxy_pass            https://172.16.2.31:8080/moop/;   # trailing /moop/ maps the prefix
        proxy_set_header      Host tracks.stowers.org;          # match the *.stowers.org cert
        proxy_ssl_server_name on;
        proxy_ssl_name        tracks.stowers.org;
        proxy_ssl_verify      off;                              # tracks TLS chain is incomplete
        proxy_http_version    1.1;
        proxy_buffering       off;                              # stream byte-range responses
    }
```

## Proposed PHP edits (ALREADY STAGED in the working tree, dormant, uncommitted)

Inert while `tracks_server.proxy_path` is empty; verified byte-identical output when unset.

- `config/site_config.php` — documents the new `proxy_path` default (`''`).
- `config/config_editable.json` — where the live value goes (`/moop/tracks-proxy`) — **currently empty**.
- `api/jbrowse2/config.php` and `api/jbrowse2/config-optimized.php` — read `proxy_path`; when set,
  swap only the *browser-facing base* of each track URL. Trust checks still run against the real
  `tracks_server.url`, and the JWT is still minted and attached exactly as before.

Effect when enabled: track URIs change from
`https://tracks.stowers.org:8080/moop/api/jbrowse2/tracks.php?file=...&token=...`
to `/moop/tracks-proxy/api/jbrowse2/tracks.php?file=...&token=...`.

## Deploy order (this order matters — getting it wrong breaks the working site)

1. Add the nginx block to `:80` and `:443`. `sudo nginx -t && sudo systemctl reload nginx`.
   Site unchanged — track URLs still absolute; the new location just sits unused.
2. Confirm the proxy forwards: `curl` `/moop/tracks-proxy/api/jbrowse2/tracks.php?file=...&token=...`
   → expect 206. If it fails, fix nginx while the live site keeps working.
3. ONLY THEN set `"proxy_path": "/moop/tracks-proxy"` in `config/config_editable.json`.
4. Verify headless + in a real browser over plain `http://`.

(On 2026-07-09 step 3 was done before step 1 existed → every track URL 404'd → broke the working
https view. Reverting the one config line restored it. Do steps in order.)

## Validation performed 2026-07-09 (throwaway nginx on :8899, real tracks server, no prod changes)

A disposable nginx instance ran the exact block above, pointed at the live tracks server. Results:

| # | Test | Result |
|---|------|--------|
| 1 | Full request forwards to tracks | 206, bigWig magic `26fc8f88` |
| 2 | Byte-range request | 206 + `Accept-Ranges: bytes` + `Content-Range` |
| 3 | `^~` beats the `.php` regex trap | proxy wins (not local PHP) |
| 4 | Bad token through the proxy | 403 — auth NOT bypassed |
| 5 | Blocked `/data/tracks/` path through the proxy | 403 — direct-file block intact |
| 6 | **Real browser, plain http, same-origin fetch** | 206, valid data, **0 CORS errors** |
| 7 | 1 MB streamed range (`proxy_buffering off`) | exact bytes, no truncation, ~3.8 MB/s |
| 8 | 10 concurrent range requests | all 206 |

Test 6 is the decisive one: the exact browser fetch that fails today, done same-origin through the
proxy over plain `http`, succeeds with no CORS error.

## Security model — what the proxy does and does NOT change

**Access control is UNCHANGED and still enforced.** The JWT stays the protection mechanism:
- RS256-signed, scoped to a single organism + assembly, 1-hour expiry (`lib/jbrowse/track_token.php`).
- MOOP only mints a token for an organism/assembly the user is authorized to view — page access is
  gated by `auth_gateway.php` / `has_assembly_access()`, and `config.php` calls
  `generateTrackToken($organism, $assembly)` only for that scope.
- The tracks server re-checks scope on every file: `api/jbrowse2/tracks.php:91` returns 403 if the
  token's organism/assembly doesn't match the requested file's first two path segments (also blocks
  `..`/`//` traversal and enforces a realpath base-dir check). A user with access to a few organisms
  cannot use their token to pull files for organisms they can't see.
- The proxy forwards the token unchanged, so all of this still applies through it (test 4 confirms a
  bad token is still rejected; test 5 confirms the direct-path block still holds).

**What the proxy fixes:** CORS, client DNS dependence, external-IP robustness.

**What the nginx-proxy variant (everything above = PHASE 1) does NOT fix — token exposure.** The
token is still in the URL (`?token=<JWT>`), so it still appears in the browser URL/history and now
in MOOP's own access logs (instead of the tracks server's). **Phase 1 does not stop advertising
tokens.** Removing the token from the URL is Phase 2, below.

---

# PHASE 2 — remove the token from the URL (nginx `auth_request` + server-side injection)

Do this AFTER Phase 1 is deployed and stable, so the CORS/DNS win isn't held hostage to the
fiddlier nginx wiring here. Prereqs confirmed present on the MOOP box (2026-07-09):
`nginx -V` shows `--with-http_auth_request_module`; `tracks.php:39` reads the token from
`$_GET['token']`.

## Design — the token never reaches the browser
The browser asks MOOP for a track by **path only** (no token). nginx runs an auth subrequest to a
small MOOP PHP endpoint that checks the session and mints the JWT; nginx then attaches that token on
the **server-to-server** hop to the tracks server. `tracks.php` is UNCHANGED — the token still
arrives as `?token=`, just injected internally rather than baked into the browser URL.

```
Browser ─GET /moop/tracks-proxy/<org>/<asm>/<type>/<file>  (session cookie, NO token)─► MOOP nginx
                                                                                          │
        auth_request ─► track_auth.php (check is_public_assembly()/has_assembly_access();
                                        deny→403; allow→mint JWT, return in X-Track-Token header)
                                                                                          │
   MOOP nginx ─proxy_pass https://tracks/.../tracks.php?file=<...>&token=<minted>─► tracks server
                          (token added HERE, server-side — browser never saw it)
```
Why this shape: nginx does the heavy byte-streaming (efficient for large bigWigs); PHP does only the
lightweight per-request auth+mint; `tracks.php` needs no change.

## Pieces
1. **NEW `api/jbrowse2/track_auth.php`** (small): parse `<org>/<asm>` from the request URI; check
   `is_public_assembly($org,$asm)` OR session `has_assembly_access($org,$asm)`; deny → `403`; allow →
   `generateTrackToken($org,$asm)` and return it in an `X-Track-Token:` response header (empty body,
   `200`). This is the entire access decision, made once per file request, tied to the live session.
2. **nginx** — in the `location ^~ /moop/tracks-proxy/` block add:
   `auth_request /moop/track-auth;` · `auth_request_set $tok $upstream_http_x_track_token;` · a
   capture of the path after the prefix · `proxy_pass https://172.16.2.31:8080/moop/api/jbrowse2/tracks.php?file=$captured&token=$tok;`
   Plus an internal `location = /moop/track-auth { internal; fastcgi_pass ...track_auth.php; }`.
3. **`config.php` / `config-optimized.php`** — emit token-less URLs
   `/moop/tracks-proxy/<org>/<asm>/<type>/<file>` (drop the `?file=...&token=...` form). Same rewrite
   block already touched for Phase 1.
4. **`tracks.php`** — unchanged.

## Effort / risk
~1 day. Risk is concentrated in ONE place: wiring `auth_request` + capturing `$upstream_http_x_track_token`
into the upstream `proxy_pass` URL, and reconstructing the `file=` path from the request URI.
Prototype it on the throwaway-nginx harness (as Phase 1 was) BEFORE it touches the box. The PHP
access check is trivial (functions exist); streaming is already proven (Phase 1 tests 6–8). No
JBrowse plugin, no `ExternalTokenInternetAccount`, no build pipeline.

## Security delta (all improvements)
- Token never in the browser URL/history, never in any access log.
- Access is re-checked against the **live MOOP session** at fetch time (not a token baked in at page
  load), so shared JBrowse links stop carrying live tokens.
- The JWT itself is unchanged — only where it's minted and that it never leaves the server.

## Alternative considered (not recommended): `ExternalTokenInternetAccount` header
Verified against the JBrowse 4.1.3 source: no token-fetch URL exists, but MOOP can pre-seed the token
silently via `sessionStorage['moop-tracks-auth-token']` or per-track `internetAccountPreAuthorization`
(no custom plugin). Works, but the token still lives in browser storage / the config response — less
thoroughly hidden than the auth_request design, and it doesn't also solve CORS. See
[[plan_jbrowse_auth_headers]] / JBROWSE_AUTH_HEADERS. Prefer the auth_request design since Phase 1
already establishes the proxy.

## Caveats

- `proxy_ssl_verify off` — the tracks server's TLS chain is missing its intermediate, so the
  MOOP→tracks hop is not cert-verified. Acceptable on the internal network; flip to `on` if IT ever
  installs the full chain.
- All track bytes flow through the MOOP box. Fine for interactive/internal use; watch at scale.
- After enabling, tighten the CSP `connect-src` (currently allows `https://tracks.stowers.org:8080`;
  with the proxy the browser only connects to `'self'`).
```
