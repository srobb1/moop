# Next Session Checklist

Remaining cleanup and fixes identified during March 2026 audit sessions.

---

## Admin Page Verification

- [x] **Check organism checklist for `data/genomes/` and `data/tracks/`** — Added both
  directories to the Step 1 directory structure diagram in organism_checklist.php.

## Environment Validation on Admin Login

- [x] **Show warnings if requirements degrade** — Added `housekeeping_environment_check()`
  to `lib/housekeeping.php`. Runs once per admin session and checks: PHP extensions,
  JWT keys, directory writability, CLI tools (blastn, samtools, makeblastdb), composer
  deps, tracks .htaccess. Warnings display as a collapsible card on the admin dashboard.

## Help Pages — New Pages Needed

- [ ] **JBrowse2 track setup** — How to add track files to `data/tracks/`, configure
  assemblies/genomes, JWT auth flow, JBrowse CLI usage, linking tracks to
  organisms/assemblies, troubleshooting 403/token/htaccess issues.

- [ ] **Site-data backup & restore** — What the site-data repo is, setting it up,
  what's included (config_editable.json, secrets.php, metadata, users.json), how
  housekeeping auto-snapshots work, restoring from snapshot, manual backup for
  organism data.

- [ ] **User management** — Adding users via Manage Users, setting per-organism/assembly
  access, admin vs collaborator roles, password resets, IP-based auto-login config,
  relationship to users.json and organism_assembly_groups.json.

- [ ] **Upgrading MOOP** — Pulling new versions, composer install, checking for new
  .example files, running setup-check.php, new PHP extension requirements, when to
  re-run npm install / JBrowse CLI updates.

## Help Pages — Existing Page Fixes

- [x] **USER_GUIDE.md** (`docs/current/user/USER_GUIDE.md`):
  - Replaced "SIMRbase" with "MOOP" throughout
  - Removed reference to non-existent `SECURITY_IMPLEMENTATION.md`
  - Fixed "All Access IP Range" → "IP_IN_RANGE" terminology
  - Fixed broken help links (relative .php → `help.php?topic=` paths)
  - Rewrote Security & Privacy section (removed false "Activity logging" claim,
    added CSRF, session security, correct access level names)

---

## Already Completed (for reference)

- [x] Security audit — 20 items, all done (commits `e4440ab`, `c7dd00e`)
- [x] Housekeeping system — `lib/housekeeping.php` (temp cleanup + site-data snapshots)
- [x] SRI hashes on CDN resources — `head-resources.php`, `layout.php`
- [x] Repo cleanup — `.gitignore`, `.example` templates, site-data backup repo
- [x] README rewrite — complete setup guide with JBrowse2, JWT keys, Node.js
- [x] Permissions page — removed hardcoded `www-data`, added genomes/tracks/certs checks
- [x] Deleted `includes/page-setup.php` — dead code, removed dual DataTables loading
- [x] README `www-data` — added note about web server user varying by distro
- [x] `validateOrganismParam()` — already used ConfigManager (was fixed earlier)
- [x] `getBlastDatabases()` — already used ConfigManager (was fixed earlier)
- [x] Organism checklist `www-data` — now uses `getWebServerUser()` dynamically
- [x] setup.php — interactive web-based installer (self-disabling)
- [x] setup-check.php — CLI preflight validation script
- [x] Help files audit — fixed permission-management.php, system-requirements.php
- [x] Docs accuracy — rewrote CONFIG_GUIDE.md, SECURITY_GUIDE.md, fixed PERMISSIONS_GUIDE.md
- [x] Help gaps assessed — identified 4 new pages needed + USER_GUIDE.md fixes

---

## Paused 2026-07-27 — decisions still open

### 1. Connection reuse — keep or revert? (commit `ddc5bfe`)

`getDbConnection()` now caches one PDO handle per database file per request, instead of
opening a new one on every `fetchData()` call.

**It is not a performance fix, and it was originally presented as one.** Measured:

| | 11 queries |
|---|---|
| warm, fresh connections | 2.3 ms |
| warm, reused | 0.5 ms |
| cold, fresh (first run) | 75.6 ms |
| cold, reused | 2.8 ms |
| cold, fresh (second run) | **4.7 ms** — does not reproduce |

The warm saving is real and consistent: ~1.8 ms on a ~450 ms gene page. Invisible.

The cold figure was the justification and it does not hold. `dd oflag=nocache` eviction is
advisory and not reliable enough to trust either number, and the mechanism is weaker than
claimed anyway: the FIRST open pays the seek for the header and schema pages, after which
they are cached, so opens 2-11 were never going to be expensive.

Correct, free, strictly less work — but tidiness, not speed. **Decide: keep or revert.**

Lesson worth keeping either way: this was reasoning about the cold path instead of
measuring it, which is the exact failure CLAUDE.md §9 warns about.

### 2. `generateTreeHTML()` computes the gene-page hierarchy a second time

`tools/parent.php:355` builds it with ONE access-filtered CTE
(`getChildrenHierarchical()`); `tools/pages/parent.php:222` then builds the same tree
again with one query per node — and passes only 4 arguments, so `$gene_set_ids` defaults
to `[]` and that path is NOT access-filtered.

The filter gap looks harmless in practice (children never cross gene sets, and the parent
was already access-checked), but it is the same data fetched two ways with two different
security postures. Fix: render from `$children_hierarchical`, already in `$data`. That
would also retire the cycle guard added to it in `8ef74d4`.

**This is the last thing found that is actually wrong rather than untidy.**

### 3. Offered, not done: move `feature_annotation.date` to `annotation_source`

440,610 rows storing **2 distinct values** = 4.2 MB per organism (~2.2%), ~1.5 GB across
the deployment. It is per-source data (from the `## Annotation Creation Date` header), not
per-row. `annotation_source` is ALREADY joined in every query that displays a date, so the
move costs no new join. Cheapest done during the reload, since the schema is being
recreated anyway. Also worth deciding `PRAGMA page_size` at the same time (currently the
4096 default; larger pages may suit a rotational volume — untested).

### 4. Three unused-but-not-broken query helpers

`getParentFeature`, `getFeaturesByType`, `searchFeaturesByUniquename` in
`lib/database_queries.php`. Not defects; they belong to
`notes/UNUSED_FUNCTIONS_CLEANUP_PLAN.md`.

### 5. The finding most worth acting on

**The main search box touches only ~15% of a database** (~5.5 GB across all 85), which
fits in the RAM already on the box. Deep annotation search touches ~85%. So the front door
could be fast after the reload with NO hardware change — see `notes/QUERY_PERFORMANCE.md`.

---

## Search performance — where the discussion with Eric landed (2026-07-27)

**He independently reached the same conclusion the measurements did.** "It's slow initially
because you need the whole table and not just the index." That is exactly the working-set
split measured in `notes/QUERY_PERFORMANCE.md`: feature/ID search touches ~15% of a
database (index + rowid lookups), annotation search touches ~85%, because after the FTS
index says *which* rows match, those rows must be fetched from wherever they live in the
file. Two independent routes to the same answer.

That matters for the IT ask: the cold cost is **fetching matched rows**, not finding them.

### Where he was right, and we were wrong

- **BLAST.** The earlier "38 GB of BLAST indexes" conflated index and sequence files.
  Actual split: `.pin`/`.nin` index = **0.3 GB**; `.psq`/`.nsq` sequence = 36 GB;
  `.phr`/`.nhr` = 4.1 GB. BLAST *indices* would fit in RAM trivially. This invalidated the
  "37 + 38 > 64 GB so BLAST can't fit" argument used to favour flash.
- **"Ask for one thing."** Asking for RAM *and* flash likely yields neither. His framing —
  request 32 GB as a reversible performance test, roll back if benchmarks don't move — is
  the better approach.
- **Connection count doesn't explain cold vs warm.** Confirmed independently; see item 1
  above.
- **There is still a LIKE.** `searchFeaturesByUniquenameForSearch()`, called from
  `tools/annotation_search_ajax.php:111`, runs `LIKE '%term%'` on `feature_uniquename`.
  Plan: `SCAN TABLE feature USING COVERING INDEX feature_unqiuename_idx`. It is a covering
  scan (3.5 MB index, never touches the table) and substring matching is genuinely wanted
  on IDs, but the claim "no more LIKE" was overstated.

### Real gap he surfaced: no `prefix=` on the FTS5 tables

Deployed DDL is `fts5(..., tokenize = 'porter unicode61')` with no `prefix` option, while
every search MOOP issues is a prefix query (`"kinase"*`). Without a prefix index, FTS5
range-scans the term list to resolve those.

**NOT YET TESTED.** Add `prefix='2 3 4'` to `build_fts_index.sql`, rebuild ONE organism,
re-time a cold annotation search. This is the experiment that settles the argument:

- if cold time drops materially → it was partly an indexing problem, fixed for free
- if it does not move → hard evidence the cost is row fetching, which is the strongest
  possible support for the RAM request

Do this BEFORE asking IT for anything.

### Open question that is a product decision, not a performance one

Porter/FTS5 tokenise on word boundaries, so prefix works (`transpos` → `transposase`) but
**infix does not** (`posase` will not find `transposase`). Eric is correct, and this is
true of Postgres GIN as well — infix needs trigram indexing (`pg_trgm`), which is
expensive. Question for the user: **do MOOP users need mid-word annotation search?** If
yes it is a functional gap, not just a tuning one. If no, this is a non-issue and should
be said plainly.

### Eric's larger argument, still open

"The best solution is probably a proper DB server." Worth taking seriously for the
cross-organism search: one buffer pool beats 85 separate files, and he is right that
touching fewer files is better. But note it does not change the physics of a cold random
read — Postgres does index lookup then heap fetch, same as SQLite. See the SQLite-vs-
Postgres assessment earlier in this file's history: the web code has ONE connection point,
so a driver swap is small; the real work is FTS5 → tsvector/GIN, the ATTACH fan-out, the
loader, and deployment.
