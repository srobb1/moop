# Page-by-Page UI/Consistency Audit — Findings & Plan

Status: **findings verified against the live site, none fixed yet** (2026-07-10).

Method: headless Chrome walk of every public/tool page from `172.16.2.52` (IP_IN_RANGE
auto-login), plus a PHP harness for the access-control assertion. 15 pages at desktop +
375px mobile widths; checked console/JS errors, failed requests, leaked PHP notices,
horizontal overflow, help affordances, and how the *same* datum renders across pages.
Every item below except #12 is confirmed live. Scratch scripts: `crawl.js`, `ux.js`,
`tooltip.js`, `mobile.js`, `help.js` (session scratchpad, disposable).

Related: [SECURITY_AUDIT_CHECKLIST.md](SECURITY_AUDIT_CHECKLIST.md) (prior sprint, all closed),
CLAUDE.md access-control section (contradicted by #3).

---

## A. Security / information disclosure — do first

- [x] **#1 CLI preflight script served over HTTP** — DONE (`PHP_SAPI !== 'cli'` guard; now 403 over
      HTTP, still runs from CLI). `setup-check.php` returns HTTP 200 to any
      anonymous visitor and prints base dir (`/var/www/html/moop`), web-server user
      (`apache:apache`), PHP version, loaded extensions, and every missing/installed CLI tool.
      It's a `#!/usr/bin/env php` CLI script with no web guard.
      **Fix:** add `if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }` at the very top
      (and to `setup-check.php` / any sibling CLI scripts), or an nginx `location ~ setup.*\.php`
      deny. `setup.php` already self-blocks via the `config_editable.json` gate — mirror that intent.

- [x] **#2 `setup-admin.php` served over HTTP** — DONE (same `PHP_SAPI` guard; 403 over HTTP).
      same exposure; also confirms `users.json` exists
      and prints its menu (option 2 = "DELETES all other users"). Under PHP-FPM `stdin` is EOF so
      the delete can't actually complete (falls through to "Setup cancelled"), so this is
      disclosure + fragility, not live destruction — but it should not be reachable at all.
      **Fix:** same `PHP_SAPI` guard / nginx deny as #1.

- [x] **#2b Web-exposure sweep + nginx no-exec layer** — DONE 2026-07-14 (commits 023f768, f5678e2).
      Found `config/`, `logs/`, `metadata/`, and `.git/` served over HTTP (world-readable JSON/logs;
      `config_editable.json`, `error.log`, `organism_assembly_groups.json`, `.git/config` all
      reachable), and the data trees web-executable. Added `docs/nginx/moop-security.conf` (deployed
      to `/etc/nginx/default.d/`): denies `config/`, `logs/`, `metadata/`, dotfiles, and
      `/moop/organisms/` wholesale, plus `.php` under `/moop/data/`. No credentials leaked
      (`secrets.php`/`site_config.php` are `.php`, execute to empty).

- [ ] **#2c Remaining minor exposure** — found 2026-07-14, not yet blocked: `composer.json` → 200
      (leaks the dependency list) and `includes/config_init.php` → 200 (executes to empty, but
      shouldn't be a direct entry point). **Fix:** add to `moop-security.conf` —
      `location = /moop/composer.json { return 404; }` and `location ^~ /moop/includes/`,
      `location ^~ /moop/lib/ { return 404; }` (never fetched directly; include-only).

## B. Correctness vs. documented behavior

- [x] **#3 `has_access('ADMIN')` returns `true` for IP_IN_RANGE** — DONE. `has_access()` now returns
      `$required_level !== 'ADMIN'` for IP_IN_RANGE (full data access, never admin); added 3
      IP_IN_RANGE smoke-test cases (29 pass); live admin panel still denies IP_IN_RANGE, organism
      page still 200. Proven via PHP harness. This
      contradicts CLAUDE.md ("IP_IN_RANGE — full data access, **no admin panel**") and the
      function's own docblock. The admin panel is safe *only* because
      `admin/admin_access_check.php:38` bypasses the helper and checks
      `get_access_level() !== 'ADMIN'` directly. Risk: any new code following the documented
      `has_access('ADMIN')` pattern would grant admin to the whole 172.16.x.x subnet.
      **Fix:** in `has_access()` (`includes/access_control.php:229`), stop treating IP_IN_RANGE as
      a blanket allow for the `ADMIN` required level — IP_IN_RANGE should satisfy data access but
      not `ADMIN`. Then add an IP_IN_RANGE case to `tests/smoke_tests.php` (currently only
      PUBLIC/COLLABORATOR/ADMIN are tested).

## C. Code-consistency — one way to do each thing

- [x] **#4 Duplicate access-gate function** — DONE. Deleted `requireAccess()` from
      `lib/functions_access.php`; repointed `tools/groups.php:112` to the documented
      `require_access()`. No refs remain, both files lint, 29/29 smoke pass, groups page still 200.
      `require_access()`
      (`includes/access_control.php:266`, uses ConfigManager) vs `requireAccess()`
      (`lib/functions_access.php:457`, uses fragile `global $site`). One caller each:
      `tools/groups.php:112` uses the camelCase lib copy; `lib/functions_display.php:331` uses the
      documented snake_case one. **Fix:** delete `requireAccess()`, repoint `groups.php` to
      `require_access()`.

- [x] **#5 JSON loading done two ways** — ✅ **COMPLETE (2026-07-13)**. Started at **89** raw
      `json_decode(file_get_contents(...))` calls across ~50 files. **82 converted** to
      `loadJsonFile()` across 8 batches; **7 remain, all by deliberate decision** (see "final state"
      below) — there is no pending work left on this item. The raw form had no missing/corrupt-file
      handling (warns + returns null); every conversion verified `, true` (assoc) and preserved any
      null-vs-missing logic.

      **Final state — the 7 remaining raw calls are all intentional, do NOT "fix" them:**
      - `api/galaxy/mafft.php:39`, `api/galaxy_mafft_align.php:47`, `setup.php:198`,
        `admin/api/generate_registry.php:18` — **N/A**: these read `php://input` (the request body),
        not a file. `loadJsonFile()` is a file loader and does not apply.
      - `api/jbrowse2/archive/get-config.php:57`, `api/jbrowse2/archive/config.json.php:24` —
        **dead code**, skip (see #15).
      - `admin/manage_users.php:76` — **deliberately skipped**: already guards with `=== null` +
        `json_last_error()` + `die`, which is stricter than the helper.
      - [x] **`tools/` batch DONE** (11 sites: gene_set×2, assembly, organism, groups,
        pages/groups, blast, moopmart×2, search, get_annotation_sources_grouped). 0 raw left in
        tools/; all 8 affected pages render clean (200, no notices).
      - [x] **`admin/` batch DONE** (12 sites: admin.php, manage_blast_linkouts, manage_organisms×2,
        organism_checklist, get_organism_modal, toggle_group_featured, assign_organisms_to_group,
        jbrowse_tracks_server, fetch_group_wiki, refresh_organism_cache×2). All affected admin pages
        render clean as admin. **Deliberately skipped:** `manage_users.php:76` (already guards with
        `=== null` + json_last_error + die), `api/generate_registry.php:18` (reads `php://input`,
        not a file). **Deferred (need downstream review):** the jbrowse-track loaders
        (`manage_jbrowse` ×3, `jbrowse_text_index` ×2, `jbrowse_list_tracks`, `manage_blast_linkouts:117`),
        `sync_ncbi_taxonomy:29`, `pages/manage_site_config.php:677` (uses `: null` sentinel → convert
        with `loadJsonFile($p, null)`).
      - [x] **`lib/` batch DONE** (15 sites: functions_data ×6, housekeeping ×3, taxonomy_functions
        ×2, functions_access, functions_database, moopmart_functions, organism_cache). 0 raw left in
        those files; smoke + live pages (index, groups, organism, moopmart, admin dashboard/
        organisms/checklist) all clean. Confirmed the CLI warmer loads moop_functions → helper in
        scope for organism_cache.
      - [x] **ENABLER**: `functions_json.php` now loaded in `config_init.php` (commit d13e441), so
        `loadJsonFile()` is globally available — unblocked the jbrowse/api/login/core paths.
      - [x] **jbrowse subsystem batch DONE** (25 sites: lib/jbrowse gene_set_functions ×4,
        config_functions ×3, PluginLoader, TrackGenerator; admin manage_jbrowse ×3,
        jbrowse_text_index ×2, jbrowse_list_tracks, manage_blast_linkouts:117, sync_ncbi_taxonomy;
        api/jbrowse2 config.php ×3, config-optimized.php ×5). The real browser endpoint `config.php`
        still generates valid config (1 assembly, 1176 tracks); jbrowse2 + manage_jbrowse clean.
      - [x] **auth-core batch DONE** (7 sites: `includes/access_control.php` ×4 — all four are the
        same `$groups_data = ...; if (!$groups_data) return false;` shape, so `[]` is faithful;
        `includes/ConfigManager.php` ×2 — both already `is_array()`-guarded;
        `lib/functions_login_protection.php:54` — `@json_decode` → `loadJsonFile($f, [])`, keeps its
        `is_array()` guard). Safe because the ENABLER puts functions_json before ConfigManager in
        config_init, and access_control includes config_init at line 11. Verified: php -l clean on
        all 3; **29/29 smoke tests pass** (they exercise has_assembly_access / is_public_assembly
        directly); live `/`, `/login.php` render clean, no new PHP errors in logs.
        Note: `tools/groups.php` 302→index.php and `admin/admin.php` 302→login for an
        unauthenticated curl are **pre-existing/correct** (verified identical against stashed code).

      - [x] **installers batch DONE — the hard one** (4 sites). These run BEFORE config exists and
        include almost nothing (`setup-admin.php` included *nothing*), so each now requires the helper
        directly: `setup.php:80` → `require_once "$base/lib/functions_json.php"` (next to the existing
        distro_detect require; `$base = __DIR__`), `setup-admin.php:27` → `require_once __DIR__ .
        '/lib/functions_json.php'`. **Safe because `lib/functions_json.php` has zero include-time side
        effects** — its only top-level statement is a `function_exists`-guarded `array_is_list` polyfill;
        everything else is function defs, and `loadJsonFile()` itself touches no config/globals.
        Converted: `setup.php:415` (is_array-guarded → `[]`), `setup.php:521` (was already `?? []` →
        exactly `loadJsonFile($p, [])`), `setup-admin.php:111` (truthy-guarded → `[]`),
        **`setup-admin.php:179` → `loadJsonFile($f, null)`** — its `=== null` check is load-bearing:
        it distinguishes an unreadable/corrupt users.json from an empty one and aborts rather than
        overwriting. `setup.php:197` is **N/A** (reads `php://input`, not a file) — leave it.

        Verified (installers can't be run against the live site, so this was done in sandboxes):
        - **Behavioral-equivalence harness**: old expr vs new expr over 5 input shapes (valid / empty /
          invalid-JSON / missing / unreadable-chmod-000) — identical downstream decision at every site,
          including the safety-critical "corrupt → ABORT, don't clobber users.json".
        - `setup.php`: sandboxed copy + **stub config** (paths confined to sandbox — the real config's
          `users_file` points at `/data/users.json`, so never run its POST path with the real config),
          token gate passed, full wizard renders (15.8 KB), no fatals, nothing written outside sandbox.
          Note the live `setup.php` self-disables anyway because `config/config_editable.json` exists.
        - `setup-admin.php`: sandboxed run reaches Step 1 / Step 2 prompts ⇒ execution passed the new
          `require_once` with no fatal. Its POST/interactive tail can't be driven headlessly (see #16).

      - [x] **root-pages batch DONE** (5 sites; helper in scope via access_control → config_init).
        `index.php:37` taxonomy_tree_config (unguarded → `[]`), `index.php:166` (was `?: []` → exact),
        `login.php:18` users.json (unguarded → `[]`), `jbrowse2.php:93` (was `?: []` → exact).
        **`index.php:21-25` DELETED, not converted** — `$users`/`$usersFile` were assigned and never
        read again (not in the `$data` array, not referenced by `tools/pages/index.php`), i.e. the
        public homepage was reading `/data/users.json` (the bcrypt credential file) on *every* page
        load for nothing. Dead read removed.
        `login.php:18` safety argument: `loadJsonFile()` returns exactly what `json_decode()` returns
        unless that's `null`, in which case `[]`. Downstream is `isset($users[$u])`, and
        `isset(null[$u]) === isset([][$u]) === false` — so the login decision is identical in *every*
        case. Confirmed with a fixture harness (real users.json is `0600 apache:root`, deliberately
        unreadable to a CLI test): valid creds still LOGIN; missing/corrupt file still DENY.
        Verified: php -l ×3; **rendered HTML byte-identical to the committed code** for index / login /
        jbrowse2 (only the per-request CSRF token and the randomly-rotating banner image differ);
        29/29 smoke tests.

      - [x] **final batch DONE** (3 sites — closes #5).
        `admin/pages/manage_site_config.php:677` → `loadJsonFile($config_file, null)` (the `null`
        default is exact: the old code was `file_exists(...) ? json_decode(...) : null`; helper is in
        scope because the controller includes `admin_init.php`);
        `scripts/warm_organism_cache.php:60` and `scripts/generate_taxonomy_tree.php:45` → both decode
        the *same* `.organism_cache.json` behind the same `if ($cached && isset(...))` guard, so `[]`
        is faithful; both scripts `require` `config_init.php` directly, so the helper is in scope.
        **The two `api/galaxy*` sites turned out to be `php://input`, not files** — N/A, not
        convertible (an earlier note wrongly listed them as "check scope").
        Verified: php -l ×3; old-vs-new `===` identical on the *real* `.organism_cache.json` and the
        *real* `config_editable.json`, plus both failure modes (missing / corrupt) for the `null`
        sentinel; `scripts/warm_organism_cache.php` **run end-to-end** — read the cache, matched
        fingerprints across 85 organisms, reported "Cache is already up to date", exited 0, left the
        file byte-identical; `generate_taxonomy_tree.php`'s changed line proven to build a deep-`===`
        identical 85-organism list (the full script was **not** run — it rewrites the live
        `metadata/taxonomy_tree_config.json` that `index.php` reads); 29/29 smoke tests; live pages 200.

- [ ] **#16 (bonus, pre-existing) `setup-admin.php` password prompt infinite-loops on EOF** — found
  while sandbox-testing the #5 installer batch. `getPasswordInput()` does `fgets()` with no EOF/false
  guard, so on non-interactive stdin it spins forever printing "Error: Password must be at least 8
  characters" (also emits `stty: Inappropriate ioctl for device` each pass). Harmless interactively,
  but it makes the installer impossible to drive headlessly or test in CI. Fix: bail out if
  `fgets()` returns `false`, and cap retries. Unrelated to the loadJsonFile refactor. Noted 2026-07-13.

- [x] **#17 🔒 SECURITY — JBrowse track tokens scoped to org/assembly, not track/file. FIXED via
  PER-FILE-BOUND TOKENS and DEPLOYED + VERIFIED LIVE 2026-07-13.**
  **Live proof (run from cerebro against tracks.stowers.org:8080 — MOOP itself can't reach the tracks
  box; 172.16.2.31, ports firewalled):** legitimate token→its file = HTTP 206; same token replayed on
  a DIFFERENT file = HTTP 403 "Token does not authorize this file". Under the old code that replay
  would have been 206, so 403 also confirms the new tracks.php is the running code. Deploy was a
  one-time copy of `api/jbrowse2/tracks.php` + `lib/jbrowse/track_token.php` to
  `/var/www/privatehtml/moop/…`; NOTHING is sent to the tracks box on future track updates.

  **PIVOTED away from the manifest approach.** First attempt (committed b51bcf6) added a `level` claim
  + a per-assembly `access_manifest.json` that tracks.php read. It worked, but the manifest had to
  reach the tracks server on every track change, and **MOOP and tracks are two separate machines with
  no shared drive — the user copies files tracks-only, manually, as data is generated.** So a manifest
  is a per-update manual step, and because enforcement was fail-closed, forgetting it = all tracks on
  that assembly 403. Unacceptable failure point. Reverted all of it (deleted access_manifest.php, the
  `level` claim, `trackAccessLevelValue`, the two auto-regen hooks, and the 69 generated manifests).

  **Final design — the token IS the capability (no manifest, nothing to sync per update):**
  - `lib/jbrowse/track_token.php`: `generateTrackToken($org,$asm,$file)` binds each token to the ONE
    file it authorizes via a `file` claim (= the exact `?file=` path). No level, no manifest.
  - `api/jbrowse2/config.php` + `config-optimized.php`: `addTokenToAdapterUrls()` now mints a token
    **per file URI** (bound to that file's path) instead of stamping one per-track token on every URI.
    `addTokensToTrack()` no longer pre-mints. The 1348 tracks-server URIs get a bound token; the 136
    `/moop/data/genomes/…` gene-model URIs (served statically by MOOP, token decorative) get a
    path-bound token too, harmlessly.
  - `api/jbrowse2/tracks.php`: replaced the org/assembly scope check (and the whole manifest block)
    with one check — `token.file === requested file`, else 403. The tracks server needs no access
    list; the signed token carries the authorization. Missing/empty `file` claim ⇒ 403.
  - Verified: live config.php embeds **1339/1339 tokens each bound to its own file**; driving the real
    tracks.php — a PUBLIC file's token replayed on a COLLABORATOR file = **403 "Token does not
    authorize this file"**, replayed on a *different public* file = **403** too (a token is good for
    exactly one file), legitimate same-file request = passes auth. 29/29 smoke; live pages 200. Token
    ~623 chars (fine for URLs). Live site unaffected (remote tracks.php still old code, ignores claim).

  **REMAINS — remote deploy (the fix is INERT until done), and it's now a ONE-TIME code copy with
  ZERO per-update syncing ever after:** copy just two files to the tracks server —
  `api/jbrowse2/tracks.php` and `lib/jbrowse/track_token.php` — to
  `/var/www/privatehtml/moop/…` (the tracks box's moop root; NOT /var/www/html). Both are box-agnostic.
  vendor/ + certs/jwt_public_key.pem already there. After this, adding/changing tracks needs NO file
  sent to tracks (MOOP bakes the authorization into the URLs it generates). ⚠ Enable via php-fpm
  reload on the tracks box if opcache is sticky. See [[bug_tracks_server_8080_unreachable]].
  (NOTE 2026-07-13: user already scp'd the OLD manifest-era tracks.php + track_token.php to tracks;
  they must re-copy these FINAL versions. The manifest tarball was never sent — good, it's obsolete.)

  ---
  ORIGINAL FINDING (kept for context):
  The JWT minted by
  `generateTrackToken()` carries only `{organism, assembly, iat, exp}` — no track, no access level.
  `api/jbrowse2/tracks.php` authorizes a file request purely on `token.organism/assembly ===` the
  first two path segments (plus a `realpath` base-dir check). **Consequence:** a PUBLIC user, who
  legitimately receives a token for a PUBLIC assembly just by viewing any public track, can use that
  same token to fetch a COLLABORATOR/ADMIN track's *file* on the same assembly — the only thing
  stopping them is not knowing the file path (security-by-obscurity; paths follow the predictable
  `MOLNG-####.##.bam` pattern). Verified 2026-07-13 by replicating tracks.php's authz check: a
  PUBLIC-scoped token authorizes both a public and a COLLABORATOR file on Nvec.
  **Exposure today:** exactly 1 assembly — `Nematostella_vectensis/GCA_033964005.1` (PUBLIC) — carries
  44 restricted (COLLABORATOR) track files reachable this way. Bounded further by 1-hour token expiry.
  Pre-existing; present in `config.php` too; NOT introduced by the #15 work. That it's a real
  boundary (not just listing curation) is implied by the config carefully filtering restricted tracks
  out of listings AND the per-track 403 now enforced in `serveSingleTrackConfig()`.

  **Key enabling facts for a fix:** tokens are *already minted per-track* (config.php:347 in the
  `loadFilteredTracks` loop; config-optimized addTokensToTrack:478) — so scoping needs no change to
  *when* we sign, only *what's in the token* + *what tracks.php checks*. Current token = 514 chars in
  the URL (room to grow). `tracks.php` already reads from disk and has NO DB/session access by design
  (that constraint must be preserved). **Both** the MOOP box and the remote tracks server
  (`tracks.stowers.org:8080`) run their own copy of `tracks.php` + `jwt_public_key.pem`, so any token
  format / verify change must deploy to BOTH — see [[bug_tracks_server_8080_unreachable]] (do not scp
  canonical files onto the tracks box).

  **PROPOSAL — Option A (recommended): access-level claim + per-assembly file manifest.**
  1. `generateTrackToken($organism, $assembly, $user_level)` adds a numeric `level` claim = the
     bearer's granted access level (PUBLIC=1 … ADMIN=4). Minting stays per-track; the flow config.php
     uses is otherwise unchanged (this is the one spot that touches "how the normal config handles
     JWT" — additive only, one claim).
  2. At track registration, emit a static `access_manifest.json` per assembly mapping
     `relative/file/path → required level` (alongside how `feature_coords.tsv` is already generated).
  3. `tracks.php` reads that manifest (cacheable; keeps it DB-free), looks up the requested file's
     required level, and enforces `token.level >= file_level` in addition to the existing org/assembly
     check. Missing manifest entry ⇒ deny (fail closed).
  Handles multi-file tracks cleanly (e.g. the 4-bigWig MultiWiggle combo) because the token encodes
  the *user's* level, not a single file — so no per-URI re-minting and no extra RSA-sign cost.
  - **Rejected — Option B (per-file token):** token carries the exact file. Forces per-URI minting
    inside `addTokenToAdapterUrls`, multiplying RSA signs (config.php already burns ~1.4 s signing
    per-track on Nvec; per-file would be far worse) and changing the minting pattern the user wants
    left alone.
  - **Rejected — Option C (encode level in storage path):** requires re-laying-out track files on the
    remote box; large data migration.
  **Blast radius (Option A):** `lib/jbrowse/track_token.php` (`generateTrackToken` signature +
  payload), both config generators (pass user level at the existing call site), `tracks.php`
  (manifest read + level compare) on **both** hosts, and the registration pipeline (emit manifest).
  **Deploy (SIMPLIFIED — dev, no backward-compat required, confirmed 2026-07-13):** single atomic
  change, no phasing. Ship the new `generateTrackToken` (adds `level`), manifest generation +
  manifests on both hosts, and the enforcing `tracks.php` (missing `level` ⇒ deny) together; deploy
  to the MOOP box and the remote tracks server. Old in-flight tokens simply stop working, which is
  acceptable in dev. This removes the phased plan's only insecure window (the legacy-allow step) and
  roughly halves the effort. Still touches the sensitive tracks box — see
  [[bug_tracks_server_8080_unreachable]]; deploy tracks.php to it in place, don't scp canonical data.
  Approve before code.

- [ ] **#18 (bug, config-optimized only) `generateAssemblyList()` puts plugins in the wrong place.**
  `config.php` emits a top-level `plugins` key with `configuration: {}`; `config-optimized.php`
  instead nests the plugin array under `configuration` and emits **no top-level `plugins` key**. JSON
  is valid and tracks are all present, so it looks fine, but JBrowse2 would load **no plugins** on the
  optimized path. Confirmed against live responses 2026-07-13 (config.php: `plugins`=1 at top level;
  optimized: `plugins` ABSENT). Contained fix — config-optimized only, zero prod risk (that endpoint
  is unused). Blocks adoption of the optimized path. Fix when the optimized path is next touched.

- [ ] **#19 (maintainability) Four functions duplicated across `config.php` and `config-optimized.php`
  — this is exactly how the #15 drift happened.** `canUserAccessAssembly()` and `addTokensToTrack()`
  are byte-identical (logic); `generateAssemblyList()` diverges (the #18 bug); `addTokenToAdapterUrls()`
  diverges **meaningfully** — config-optimized has an internal-path→remote-tracks-server rewrite
  (from `dbfe578`) that config.php never got, and config.php's version is the one in production and
  is known-good (user: do NOT change how config.php handles JWT/token URLs). So this can't be a blind
  extract. Plan when the optimized path is adopted: first reconcile #18, then extract the 2 identical
  fns into `lib/jbrowse/config_functions.php`, and make a deliberate decision on `addTokenToAdapterUrls`
  (keep config.php's semantics as canonical) before merging it. Until then they stay duplicated.

- [x] **#15 `api/jbrowse2/config-optimized.php` — RESURRECTED, now at parity with `config.php`**
  (2026-07-13). **The original note was WRONG on both counts; corrected here.** It does *not* call
  `getDbConnection()` anywhere (that error came from a bare CLI harness that lacked the include
  chain, not from the file), and the "pre-existing HTTP 500" was almost certainly an **opcache
  artifact**: `git stash`/`pop` rewrites the file within the same second, so php-fpm served a stale
  or torn copy. A `touch` + 1s wait makes the 500 vanish. **Beware this trap when A/B-ing a live PHP
  file by stashing — always `touch` and settle before curling, or serve the old version from a
  different filename.**

  The file's *real* defects (now fixed) were **drift** — it never received two commits that
  `config.php` got:
  - `b31104e` "JBrowse config access fix": load the assembly JSON *first*, then authorize with
    `canUserAccessAssembly()` against its `defaultAccessLevel`. `getAccessibleAssemblies()` checks
    **gene-set** groups, not genome-browser access, and returns empty for PUBLIC users — so
    config-optimized was **403-ing every PUBLIC user** on PUBLIC assemblies (fail-closed, unusable).
  - `5a3fc5c` "sort Gene Models first": category ordering. Ported into `getTrackReferences()` (not the
    callers) so **both** the full-config and track-URI paths inherit it.

  Two further problems found while porting:
  - 🔒 **`serveSingleTrackConfig()` had NO per-track access check** — it computed
    `$track_access_level` and then had a literal `// ... access check logic ...` stub, serving the
    track unconditionally. The broken assembly gate was the *only* thing holding the door shut.
    **Fixing the assembly gate alone would have converted a fail-closed bug into a data leak**: any
    PUBLIC user could have pulled the 44 COLLABORATOR-only tracks (with working JWTs to the BAMs) off
    the PUBLIC Nvec assembly. Both had to be fixed together, and were.
  - `config-optimized.php` never enabled gzip (it even *documents* gzip in its header comment).
    Without it the endpoint sent 552 KB where `config.php` sent 66 KB, i.e. the "optimized" path
    looked ~8× *worse*. Ported the `ob_gzhandler` block.

  **Verified** (Nvec `GCA_033964005.1`, PUBLIC, 1176 tracks): both endpoints now return 200 with the
  identical track set **and order**, Gene Models first, identical `assemblies` block; optimized is
  **21 KB gzipped vs config.php's 66 KB** (and 552 KB vs 3.07 MB decoded — the actual win). As a
  PUBLIC user: 1132 tracks listed, **0 of the 44 COLLABORATOR tracks leaked**, and the single-track
  endpoint returns `403 Access denied to this track` for them while serving PUBLIC ones.
  ⚠️ **Testing note:** curling MOOP *from this box* auto-authenticates as **IP_IN_RANGE**
  (`logged_in: yes`), which outranks COLLABORATOR — so "anonymous" curl tests are NOT anonymous and
  will appear to leak restricted tracks. That is correct behavior, not a bug. Use a CLI include
  (no `REMOTE_ADDR`) to test as a true PUBLIC user.

  **Still open (not a bug, a design smell):** `canUserAccessAssembly()` and `addTokensToTrack()` are
  **duplicated** in both files — that duplication is exactly how this drift happened. If the
  optimized path is adopted, extract the shared functions into `lib/jbrowse/config_functions.php` so
  the two generators cannot diverge again.

## D. UX / display-consistency — same data shouldn't look different

- [x] **#6 Species name UPPERCASED on organism page only** — DONE. Dropped `text-uppercase` from the
      binomial span (now proper-case italic, matching every other page).
      `tools/pages/organism.php:32` wraps
      the binomial in `text-uppercase`, rendering `NEMATOSTELLA VECTENSIS`. Every other page
      (assembly, gene_set, parent, groups) shows proper-case italic `Nematostella vectensis`.
      Uppercasing a scientific name is both inconsistent and taxonomically wrong.
      **Fix:** drop `text-uppercase` from that span; keep the `<em>` italic.

- [ ] **#7 Three separate help mechanisms coexist** — (a) JS `info-icon`→modal on 7 pages
      (organism, assembly, gene_set, groups, multi_organism, search, moopmart — modal confirmed to
      open); (b) Bootstrap `popover` on search/blast/downloads/moopmart; (c) plain `title=`
      tooltips. `tools/pages/parent.php` — a heavily used page — uses none of the guided-help
      system. **Fix:** pick one primary pattern (the `info-icon`→modal system) and standardize;
      at minimum add guided help to parent.php. Larger cleanup — scope separately.

- [x] **#8 Assembly shown inconsistently — standardize on `Name (Accession)`** — DONE for the
      user-facing PHP display sites 2026-07-13. Data model: `genome` table has `genome_name`
      (`Nvec200`) + `genome_accession` (`GCA_033964005.1`), resolved by `getAssemblyInfo()`; the URL
      `$assembly` param IS the accession. **Single helper `assembly_label($name,$accession)` added to
      `lib/functions_access.php`** (next to getAssemblyInfo) — returns `"Name (Accession)"`, or bare
      accession when name is empty/equals accession. Routed through it:
      - `includes/source-list.php:105` — the shared source selector (all tool pages w/ selector, e.g.
        blast, retrieve_sequences) — was bare `genome_name`; now `Name (Accession)`. Highest-traffic fix.
      - `tools/pages/assembly.php:10` — heading card — was bare name; now `Name (Accession)`.
      - `tools/pages/parent.php:92` and `gene_set.php:82` — already the good form but with hand-rolled
        inline logic; consolidated onto the helper so they can't drift.
      Verified live: assembly heading, gene_set link, and blast selector all render `Name (Accession)`
      (e.g. `Nvec200 (GCA_033964005.1)`, `AmpQue_v1.1 (GCF_000090795.2)`); helper unit-tested;
      29/29 smoke. Non-standard cases (name==accession, or accession-less like `Bats`) correctly fall
      back to bare — not bugs.
      **Deliberately NOT changed (out of scope / lower priority):** `retrieve_sequences.php:161`
      (`$assembly_name` is an internal var passed to sequences_display, not a user label) and its
      hidden form input (:64); admin `manage_organisms.php` (no simple label display — comparisons
      only); the browser `<title>` tag in `tools/assembly.php:70` (tab title, conventionally the short
      name); JS sites (mostly use accession as a KEY or are admin chips like manage-users.js — no
      user-facing "Name (Accession)" label rendered in JS). If a JS label surface appears later, add a
      JS twin of `assembly_label()`. Organism-page instances still fold into the section J design pass.

- [x] **#9 Heading hierarchy disagrees between pages** — DONE. Every page now has exactly one
      semantic `<h1>` (verified live: index, blast, downloads, search, moopmart, groups, organism +
      the already-correct assembly/gene_set/parent). No visual change anywhere. Fresh audit: 7/10 pages had
      **zero** `<h1>` (index, groups, organism, search, blast, moopmart, downloads); only
      assembly/gene_set/parent are correct. organism starts at `<h2>`; groups + tool pages use a
      styled `<span>` eyebrow as the "title".
      **Chosen fix = A via C (real component):** built `page_title($text,$icon)` in
      `includes/page_header.php` (wired into `render_display_page()`), with styling centralized in
      `css/display.css .page-title-eyebrow`. Emits one semantic `<h1>` in the existing eyebrow style
      — no visual change. Consolidates the inline-styled `<span>` titles that were hand-rolled per
      page (root-cause fix). **Piloted on BLAST** (1 `<h1>`, look identical). Rollout pending user OK:
      downloads, search, moopmart next; index gets an `<h1>`; organism/groups adopt it in their
      design passes (section J).

## E. Layout

- [x] **#10 `parent.php` horizontal overflow on mobile** — DONE. Added `flex-wrap` to the Gene
      Structure and Annotations card headers; the button toolbars now wrap under the title on narrow
      screens. Verified 375px overflow 97px→0px, desktop 1440px unchanged (header still one line).
      +97px at 375px wide; the right-aligned
      action toolbar (`.ms-auto` group: Retrieve Sequences / BLAST / Downloads / View in Genome
      Browser) doesn't wrap. Every other page is clean at 375px. **Fix:** allow the toolbar to wrap
      (`flex-wrap`) or collapse to a menu on narrow widths.

## F. Dead code

- [x] **#11 `tools/get_annotation_sources.php` is unreferenced** — DONE. Deleted (recoverable from
      git). Re-verified: only mention was in the auto-generated `docs/function_registry.json`; all 3
      JS callers use `_grouped`; no dynamic URL, nginx rewrite, or tools-config entry. Its lib fn
      `getAnnotationSources()` is now dead → tracked in
      [UNUSED_FUNCTIONS_CLEANUP_PLAN.md](UNUSED_FUNCTIONS_CLEANUP_PLAN.md). Superseded
      by `get_annotation_sources_grouped.php` (4 refs).

- [ ] **`api/jbrowse2/archive/` is dead code** — found 2026-07-14. Zero references anywhere
      (`grep --include=*.php --include=*.js`); web-reachable and 500s when hit;
      `assembly-cached.php` does a `mkdir` into `data/`. Delete the directory (recoverable from git).

## G. Needs investigation (not yet confirmed a bug)

- [ ] **#12 `jbrowse2.php` throws real JS errors** — `no session model found` + a
      mobx-state-tree union mismatch on `LinearBasicDisplay`. Page returns 200; these are config
      errors, not the known tracks/CORS/network issue. Couldn't fully isolate from the headless
      env. **Next:** check whether the default session's display type is malformed for this
      assembly. (The failing google-analytics request is just the sandbox blocking GA — ignore.)

## I. Parent (gene) page — UI polish (iterated live with the user 2026-07-10)

- [x] Sidebar section-nav title "On this page" → **"Jump to"** (matches the mobile jump-bar).
- [x] Sidebar type chips now use the site feature color code (mRNA=teal #17a2b8, gene=purple,
      protein/polypeptide=green); removed forced uppercase so casing is correct (**mRNA**, not MRNA).
      CDS has no color in the code yet — add `.feature-color-cds` if wanted.
- [x] **Overview card redesign** — the short stable **ID** now sits in the colored bar; the
      descriptive **name** is a proper-case ~20px `<h1>` headline in the body (was a tiny 12.8px
      all-caps label). Fallback to feature_name, then "No description available" when blank. Exactly
      one `<h1>`. Parent-only (new `.feature-header-id` / `.feature-title` classes); the shared
      `.feature-header h1` rule still serves assembly/gene_set. Also added `gy-3` gap (#14) and
      `flex-wrap` headers (#10) earlier. Redundant ID badge removed from the body; the color-coded
      `gene` + `mRNA child` badges stay as a classification line. Headline kept in natural NCBI case
      (user preference — no `::first-letter` capitalization).
- [ ] **Gene Structure (gene diagram) section — user wants to review for a couple of tweaks.**
      Not yet specified; revisit. File: `tools/pages/parent.php` (~line 132+, `#gene-model-svg`)
      and the JS that draws it (`js/` gene-model script).

## J. Organism page — apply the same design updates as the gene page (user request 2026-07-10)

The organism page (`tools/pages/organism.php`) needs a design pass mirroring the parent-page work in
section I. Candidates (confirm specifics with the user when we get to it):
- [ ] **Heading level:** the page title is an `<h2 class="fw-bold mb-1">` ("Starlet Sea Anemone",
      line ~126) — should be the page's single `<h1>` (ties into audit #9 heading hierarchy).
- [ ] **Header/overview card treatment:** apply the readable header pattern from the gene page
      (clear title hierarchy; ID/short-identifier vs descriptive-name split where relevant) instead
      of the tiny uppercase eyebrow labels. (Binomial casing already fixed in #6.)
- [ ] **Shadow / card hierarchy & section styling:** keep it consistent with the gene page's
      two-tier shadow and section-card conventions.
- [ ] Review the `text-uppercase` eyebrow labels ("Search Gene IDs and Annotations", "Search
      Results", lines ~38/72) for the same readability considerations.

## M. "Working examples" validator/generator — to build (user idea 2026-07-10)

Problem: example values are **hardcoded** wherever we show them (index organism-search chips +
gene-search chips, BLAST "Sample Protein/Nucleotide", search page, moopmart "HDAC"/"GO:0006351"),
so they go stale as data changes and a dead example (e.g. `LOC100636551`, removed 2026-07-10) makes
the site look broken.

Proposed: an admin action (on Manage Site Configuration) that keeps examples honest, in two modes:
1. **Validate** — run each configured example against the live DBs and flag the broken ones.
2. **Suggest/generate** — pull a real, representative value per context straight from the DBs
   (a real gene ID, mRNA/accession, annotation term, assembly accession, organism/group name).

Root-cause fix so it can't drift: move examples out of hardcoded HTML into `config_editable.json`
(a small `examples` block), have index/BLAST/search/moopmart read from config, and let the admin
tool refresh/validate that block. Assessment: high value, low risk, directly prevents "example
returns no match" embarrassment. Reuses existing FTS/search + DB helpers.

## L. Index (home) page — quick fixes (done 2026-07-10)
- [x] Removed dead gene-id example `LOC100636551` from the index gene-search chips.

- [x] Site title `<p>` → `<h1>` (part of #9; same styling, no visual change).
- [x] Stop password managers autofilling the 5 search/filter boxes: added `autocomplete="off"`,
      `data-1p-ignore`, `data-lpignore="true"`, `data-form-type="other"`, and switched
      `type="text"` → `type="search"` (the lever Safari/iCloud Passwords actually respects). If
      iCloud still pops up on the user's machine, it's Safari heuristics largely outside site control.
- [x] Gene-search input icon `fa-fingerprint` → `fa-search` (matches the organism search).

## K. Feature breadcrumb — to discuss (user idea 2026-07-10)

Add a hierarchy breadcrumb at the top of the detail pages so users can see and navigate the
organism → assembly → gene set → feature chain:
- **Gene/feature page:** Organism → Assembly → Gene Set → Feature
- **Gene set page:** Organism → Assembly → Gene Set
- **Assembly page:** Organism → Assembly

The links already exist (the overview info-grid rows link to `organism.php` / `assembly.php` /
`gene_set.php`), so this is mostly a presentation/navigation layer. Points to discuss:
- Placement (above the overview card? in/near the "Jump to" bar?) and whether it complements or
  replaces the info-grid links.
- Styling (Bootstrap breadcrumb vs. custom), truncation of long names, mobile wrapping.
- Whether the last crumb (current page) is plain text vs. linked.
- Consistency across all three detail page types + reuse of one component.

## H. Bonus findings during implementation (not in the original 12)

- [x] **#14 Info box & Toolbox touch with no gap when stacked** — DONE. On the gene (parent) page the
      Overview `row` holds `col-lg-8` (info box) + `col-lg-4` (Toolbox); below the `lg` breakpoint
      (~<992px) they stack, and Bootstrap columns have no vertical gutter, so the two cards touched.
      Added `gy-3` to the row: 16px gap when stacked, unchanged side-by-side on desktop. Found while
      reviewing the #10 fix (pre-existing, unrelated to it). Ordering (overview then toolbox) kept —
      identity first, then actions.


- [x] **#13 Admin "Generate Registry" button fails** — DONE. `docs/function_registry.json` was
      `-rwxr-xr-x smr apache` (no group write), so the generator succeeds when run from the CLI
      (as `smr`) but `file_put_contents` fails when run by php-fpm (as `apache`) → generator prints
      "❌ Error writing JSON file" → the endpoint's `stripos($output,'error')` check reports
      "Failed to generate." Fix: `chmod 664` the file (now group-writable) + added
      `@chmod($jsonFile, 0664)` after write in both `generate_registry_json.php` and
      `generate_js_registry_json.php` so it self-heals regardless of which user regenerates.
      Secondary (not fixed): the endpoint infers success from `$output` being non-empty and a naive
      "error" substring scan rather than the generator's real exit code — fragile, worth hardening.

---

## K. JBrowse Google Sheets — per-track feature-type filtering (2026-07-13)

> **CORRECTION (2026-07-14).** An earlier draft of this section claimed
> `filterFeatureTypes` "already works for auto-generated gene tracks." **It does not, and
> it never did.** That property does not exist in the deployed JBrowse2 build (verified:
> 0 occurrences in `jbrowse2/static/js/`). JBrowse validates the config as a single unit,
> so an unrecognised display property — or an unrecognised display *type* — makes
> mobx-state-tree reject the ENTIRE config with "No matching type for union", taking down
> every track and the assembly with it. This is exactly what broke the browser between
> 2026-07-08 and 2026-07-14. **Do not add `filterFeatureTypes` to any track type.**

- [ ] **Add per-track feature-type filtering to the Google Sheets pipeline.** Lets you
      point two sheet rows at the same GFF file and show different feature types per track
      (e.g. genes vs. transcripts).
      Real-world case: `kc_nvec200_LOE_genes` and `kc_nvec200_LOE_transcripts` both point to
      the same LOE GFF but currently show all feature types identically.

      The supported mechanism is **`jexlFilters`** — a real `LinearBasicDisplay` property
      (39 occurrences in the deployed build). It takes an array of JEXL expression strings:

      ```php
      'displays' => [[
          'type'        => 'LinearBasicDisplay',
          'displayId'   => "$track_id-LinearBasicDisplay",
          'jexlFilters' => ["jexl:get(feature,'type') == 'gene'"],
      ]],
      ```

      Files to change:
      1. `lib/jbrowse/GoogleSheetsParser.php` — add the column to `cleanTrackData()`
      2. `lib/jbrowse/TrackTypes/GFFTrack.php` — translate it into `jexlFilters` in the display
      3. `lib/jbrowse/TrackTypes/GTFTrack.php` — same
      4. `scripts/generate_tracks_from_sheet.php` — document it in the "Optional Columns" list

      **Verify against the deployed build before shipping** — grep
      `jbrowse2/static/js/` for any display property before emitting it. A single unknown
      key is not ignored; it is fatal to the whole config.

---

## N. Admin dashboard — front-door health pointers (user idea 2026-07-14)

The dashboard should tell the admin *that* a sub-page has problems and *how many*, then
link to it — NOT reproduce the issues on the dashboard. A count/badge + "go look here", so
the dashboard stays a router, not a wall of detail. Mirrors the existing
`housekeeping_environment_check()` collapsible card.

- [ ] **File Permissions pointer** — "N permission issues → Permissions Manager", linking to
  `manage_filesystem_permissions.php`. Needs a light aggregate count (reuse
  `performPermissionCheck()` there for pass/fail totals). **PERF:** it `stat()`s many files —
  do NOT run it on every dashboard load. Compute once per admin session in
  `lib/housekeeping.php`, stash the count in the status file (same pattern as
  `housekeeping_environment_check`), dashboard reads the cached number. (As of Phase 1,
  2026-07-14, `organisms/` is writable again — see §O — so a normal permission check is accurate;
  only if Phase 2 re-tightens it to read-only, don't false-alarm on that.)

- [ ] **New Organism Checklist pointer** — "N organisms need setup steps" → `organism_checklist.php`.
  Overlaps existing `health_alerts` (`new_gene_sets`, `no_database`) — decide whether to fold
  those into one "needs attention" pointer rather than a separate card.

- [x] **Git status in the site-backup card** — DONE 2026-07-14 (commit 96fb61a). The backup card
  now shows the repo's real state: green "All committed & pushed — N commits · last X" when clean,
  amber "N files to commit, M commits to push" + the exact command when not. Computed once per
  admin session via `housekeeping_git_status()` (local read-only git; graceful fallback to the old
  "Git available" badge). Confirmed git runs fine under httpd_t (card shows live numbers).

- [ ] Other candidates, same badge+link pattern, priority order: **site-data backup status**
  (`$_SESSION['site_data_backup']` already populated — just surface failures/staleness);
  **error-log activity** ("N errors in 24h", see ERROR_LOG_IMPROVEMENTS_PLAN.md); **account
  lockouts** (`logs/login_attempts.json`); **cache/refresh health** (organism cache stale or
  last refresh errored); **disk space** on the genome volume (a fill-up is silent today).

---

## O. Read-only organisms/ vs in-app index building (2026-07-14) — RESOLVED (Phase 1)

**RESOLVED 2026-07-14 (Phase 1, commit f5678e2)** — chose a hybrid, not A/B/C as written below.
Reverted `organisms/` to **writable** (in-app index building AND SQLite both need it — read-only
also caused a ~740/min `{ write }` AVC flood because PDO opens DBs read-write, falls back to ro)
AND closed the webshell risk at the **execution layer** instead: nginx
(`docs/nginx/moop-security.conf`) denies HTTP to `/moop/organisms/` and `.php` under `/moop/data/`,
so a written `.php` can't execute. **Phase 2** (`notes/MOOP_INDEXES_PLAN.md`) adds FASTA *integrity*
back via an `index/` subdir (symlink FASTA in) + read-only DB connections (`mode=ro`). The
false-green fix below applies to Phase 2 only (organisms/ is writable now, so the checklist is
accurate). Original analysis kept for context:

After moving caches out and making `organisms/` read-only to the web server (see
docs/SELINUX_AND_HARDENING.md, [[project_cache_path_and_readonly_organisms]]), some
**web-triggered** admin operations that WRITE into the tree now fail. Key nuance: the
block is domain-based, not user-based. php-fpm runs as `apache` in `httpd_t`; commands it
exec's (makeblastdb, samtools) inherit `httpd_t` and are blocked. The SAME commands run
from the CLI / compute-server run in `unconfined_t` and write fine (verified). So:

- **BLAST index build** (`makeblastdb`, blast_functions.php:853, cd's into the assembly dir
  and writes `.phr/.psq/...` there) — breaks from the web ("Build BLAST Index" button); works
  from CLI/compute-server.
- **samtools faidx** on assembly registration (`jbrowse_register_assembly.php:97`, writes
  `genome.fa.fai` into organisms/) — same.
- **Generate organism JSON** for an organism with no json yet (creates the file, needs dir
  write) — same.
- GFF bgzip+tabix is NOT affected — it writes to `data/genomes/` (writable).

**False-green to fix regardless of the decision:** `organism_checklist.php:395` and
blast_functions.php `isAssemblyDirectoryWritable` use `is_writable()`, which checks DAC only
and returns TRUE for organisms/ (mode 2775 is intact; SELinux is what blocks the write). So
the checklist says "ready to build" and the web build then fails at runtime. Either detect
the httpd_t block (attempt-and-catch, or check the SELinux label) or reword to "build from
the compute server." Also the permission-manager "Organism Data Directories" entry still says
the tree must be writable — its DAC check still passes (2775), but the `why_write` text is now
misleading; reword to note it is intentionally read-only to httpd_t.

**Options (pick one):**
- **A. Build indexes CLI/compute-side, keep read-only** — fits the existing "ship data in"
  workflow; in-app build buttons become "run on the compute server" (or are removed). Cleanest,
  preserves the security win. RECOMMENDED if indexes are already built off-box.
- **B. Unlock-build-relock** — keep read-only for serving; provide a small root script that
  `chcon`s a specific assembly dir to rw, builds, then `restorecon`s. Preserves in-app builds
  as an occasional privileged action.
- **C. Revert read-only** — put organisms/ back to httpd_sys_rw_content_t. Simplest; loses the
  writable-surface reduction (security benefit was modest — the tree is non-executable).

---

## Suggested order

1. #1, #2, #3 — exposure/correctness, small diffs.
2. #6, #11 — quick display/cleanup wins.
3. #4, #9, #10 — consistency + layout.
4. #5, #7, #8 — larger sweeps, scope each on its own.
5. #12 — investigate.
