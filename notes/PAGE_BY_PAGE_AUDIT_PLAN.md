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

- [x] **#4 Duplicate access-gate function** — DONE. Deleted `requireAccess()` from
      `lib/functions_access.php`; repointed `tools/groups.php:112` to the documented
      `require_access()`. No refs remain, both files lint, 29/29 smoke pass, groups page still 200.
      `require_access()`
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

- [ ] **#8 Assembly shown inconsistently — standardize on `Name (Accession)`** (user-confirmed
      convention, 2026-07-10). The assembly identifier appears **three** ways across the site: bare
      accession (`GCA_033964005.1`), bare name (`Nvec200`), and the good form
      `Nvec200 (GCA_033964005.1)`. **Fix:** always render `Name (Accession)`. Audit every place an
      assembly is displayed (organism, assembly, gene_set, parent, downloads, moopmart, search
      scope chips, dropdowns, breadcrumb-to-be) and route through a single helper so it can't drift
      again — e.g. `assembly_label($name, $accession)`. Organism-page instances overlap with the
      deferred design pass (section J).

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

## Suggested order

1. #1, #2, #3 — exposure/correctness, small diffs.
2. #6, #11 — quick display/cleanup wins.
3. #4, #9, #10 — consistency + layout.
4. #5, #7, #8 — larger sweeps, scope each on its own.
5. #12 — investigate.
