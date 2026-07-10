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

- [ ] **#4 Duplicate access-gate function** — `require_access()`
      (`includes/access_control.php:266`, uses ConfigManager) vs `requireAccess()`
      (`lib/functions_access.php:457`, uses fragile `global $site`). One caller each:
      `tools/groups.php:112` uses the camelCase lib copy; `lib/functions_display.php:331` uses the
      documented snake_case one. **Fix:** delete `requireAccess()`, repoint `groups.php` to
      `require_access()`.

- [ ] **#5 JSON loading done two ways** — 65 raw `json_decode(file_get_contents(...))` calls vs 11
      uses of the `loadJsonFile()` helper. `tools/groups.php` uses **both**, 8 lines apart (helper
      at :56, raw at :64). Raw form has no missing/corrupt-file handling. **Fix:** migrate raw
      calls to `loadJsonFile()` (start with tools/, then admin/); do it incrementally, one file
      per commit.

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

- [ ] **#8 Assembly identified two ways** — bare accession `GCA_033964005.1` on organism +
      assembly pages vs friendly `Nvec200 (GCA_033964005.1)` on gene_set + parent. **Fix:** pick
      one convention (recommend `FriendlyName (Accession)`) and apply everywhere. Minor.

- [ ] **#9 Heading hierarchy disagrees between pages** — assembly/gene_set/parent use a real
      `<h1>`; organism uses `<h2>`; index and groups have no `<h1>` (groups' largest text is `<h5>`
      card titles). Affects accessibility and visual rhythm. **Fix:** each page gets exactly one
      `<h1>` as its title; demote/promote accordingly.

## E. Layout

- [ ] **#10 `parent.php` horizontal overflow on mobile** — +97px at 375px wide; the right-aligned
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

## G. Needs investigation (not yet confirmed a bug)

- [ ] **#12 `jbrowse2.php` throws real JS errors** — `no session model found` + a
      mobx-state-tree union mismatch on `LinearBasicDisplay`. Page returns 200; these are config
      errors, not the known tracks/CORS/network issue. Couldn't fully isolate from the headless
      env. **Next:** check whether the default session's display type is malformed for this
      assembly. (The failing google-analytics request is just the sandbox blocking GA — ignore.)

## H. Bonus findings during implementation (not in the original 12)

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

## Suggested order

1. #1, #2, #3 — exposure/correctness, small diffs.
2. #6, #11 — quick display/cleanup wins.
3. #4, #9, #10 — consistency + layout.
4. #5, #7, #8 — larger sweeps, scope each on its own.
5. #12 — investigate.
