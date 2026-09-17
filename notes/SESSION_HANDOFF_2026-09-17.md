# Session handoff — 2026-09-17

**⚠️ 3 commits are COMMITTED BUT NOT PUSHED** (`a3cce59`, `6b1a2a4`, `9db9b42`).
`git fetch` first — the remote had a commit from another machine once before.

Context for the whole list: the compute-side annotation pipeline is mid-change (waiting on a
colleague), after which **all MOOP data gets reloaded**. So this session deliberately skipped
data fixes and worked on code. Keep doing that until the reload lands.

---

## ⭐ START HERE — the gene page 500s for 17 organisms

**The single highest-impact open item, and it is pure code — unaffected by the reload.**

`tools/pages/parent.php:517` passes `$gene_model_legend` to `array_map()`. That variable is
only defined at line 240, **inside** `<?php if (!empty($gene_model)): ?>` (lines 197–259). The
help modal at 499 sits outside that block. `$gene_model` is null whenever the gene set has no
GFF, so the page fatals with an uncaught `TypeError`.

Measured 2026-09-17, one gene page per organism across all 85: **66 × 200, 18 × 500.**
A real tester at 172.16.30.216 hit it on Schmidtea_mediterranea on 2026-09-11, twice.

**13 of the 18 are planarians/flatworms** — Schmidtea (all four), Phagocata, Bipalium vagum,
Cura, Obama, Procerodes, Romankenkius, Spathula, Camerata, Diversibipalium — plus
Schizocardium, Turritopsis, Sinorhizobium, Bradyrhizobium.

⚠️ **Parastichopus is now 200 BY LUCK, not because anything was fixed** — its 2026-09-17
rsync brought a `genes.gff`, so `$gene_model` is non-null. The other 17 are untouched.

- **The fix is small**: hoist the legend definition above the `if`, or guard the modal.
- **Behind it is the real task**: 24 of 96 gene sets have **no `genes.gff` and no `genome.fa`**
  — they are transcriptome-only, which is a legitimate data shape the site does not model.
  Fixing the fatal stops the white screen; deciding how a transcriptome-only gene set should
  *present* (no model diagram, no JBrowse, no genomic sequence — said plainly rather than by
  omission) is the actual piece of work.

---

## Shipped this session

### Data-health check — a database that loaded NOTHING passed every check (`6966c0d`, `59787a4`, pushed)
See [[bug_health_checks_referential_not_existence]] in memory. Found 3 organisms scoring
10/10 while returning zero results. Now: emptiness checks in `validateDatabaseIntegrity()`,
a `data_issue_codes` array the UI branches on, a dashboard alert, specific Manage Organisms
badges, a fixed DB-icon tint, an "Empty / Unlinked DB" filter chip, `?filter=` deep links,
and severity graded by impact instead of by count (`Critical` used to be unreachable — it
fired for 0 of 85). 18 new hermetic tests; suite 138 → 156.

### ✅ Parastichopus_parvimensis — RESOLVED by the 2026-09-17 13:54 rsync
0 features → 164,629. `feature_annotation` 0 → 1,779,691. Annotation search for "kinase"
went from **0 rows to 2,500** (capped) in 0.09 s. Gene page 500 → 200. Cache refreshed with
`php scripts/warm_organism_cache.php --organism=Parastichopus_parvimensis` (0.09 s), and the
dashboard alert dropped 3 → 2. The check flagged a real problem and cleared itself.

⚠️ Still worth a look at reload time: **0 descriptions on every feature type** (gene, mRNA,
CDS, protein, transcript) and 0 of 41,257 in the protein FASTA — those agree, so it is NOT
the `updateFASTA.pl` blanking bug, there simply are none. Names are partial (88,596 of
164,629) but **0 of the genes have one**, so gene pages show bare `parpar1_gene-LOC…` ids.
Possibly expected for LiftOn output; decide at reload.

### Primer Maker + headings (`a3cce59`, `6b1a2a4`, `9db9b42` — NOT PUSHED)
- Overview `(i)` on the header — it was the only tool page with no help modal at all.
  Step 2 now says RT-PCR needs junctions and that you mark them yourself with `|` for a
  pasted sequence (the one case the preset blurb does not cover).
- `page_title()` on Primer Maker: it had **no `<h1>`**. Also on Download Selected Sequences,
  which started at `<h2>` and had no purpose sentence.
- Registered Primer Maker in the page finder (11 → 12 routes). It was in neither `$pages`
  nor `$meta` in `scripts/extract_page_purposes.php`, so it was never probed.
- Converted the 3 pages that wrote `page_title()`'s markup out by hand.

---

## Decisions made — do not re-litigate

- **Combined cross-organism search index: PARKED, wait for the faster disks.** The measured
  bottleneck is seek-bound (concurrency 5→15 = 1.12×; the step-1 win was contiguity, not
  volume), which is exactly what flash fixes. The index costs 3–4 days **plus a
  cross-organism access-control redesign** where one missing gene-set filter leaks, and its
  ~20 MB payoff is extrapolation, never prototyped. Asymmetry decides it: build it now and
  the disks make it moot → 4 days gone *and* a permanent access-control surface. Wait and it
  is still there to build. **Re-measure when the disks land** with `notes/bench/bench.py`,
  eviction verified by `mincore` — cross-organism `helicase` was 90.7 s.
- **`organism` / `assembly` / `gene_set` / `parent` headers stay as they are** (user, after
  looking at them). They are a different component on purpose — type-coloured bar, data name,
  type badge chip, details block — not drift. Their `letter-spacing: 0.512px` vs tool pages'
  `1.28px` is deliberate: tighter tracking reads better on accessions and IDs.
- **`about` / `access_denied` / `login` / `index` keep their own `<h1>`** — a 40px plain
  title, or index's 16.8px with 3px tracking. `page_title()` would shrink them to a 12.8px
  eyebrow. Tool pages are now **11 `page_title()`, 8 deliberate exceptions, 0 with no heading**.
- **Download Selected Sequences is NOT in the page finder** — the finder answers "which page
  do I want for this task", and nobody sets out to go there; it is a step in a flow that
  starts on a results table. 🔑 A heading fix *cannot* add a page to the finder anyway: the
  finder renders from an allowlist (`$pages`/`$meta`) and reads `data-page-purpose`, never
  the `<h1>`. Verified after the change — still 12 routes.

---

## Open, roughly in priority order

1. **The gene-page fatal + transcriptome-only presentation** — see the top of this file.
2. **Naming collision worth a decision:** "Retrieve Sequences" (in the finder, wants IDs
   pasted in) vs "Download Selected Sequences" (not in the finder, reached from a results
   table). Someone who ticked rows and goes looking will find the first and land somewhere
   that does not do what they want. Renaming is a product call.
3. **Use-case cards** — the user's own ask, groundwork done: the page finder is live, every
   page declares `page_purpose()`, and seed content is harvested in
   `notes/USE_CASES_AND_HELP_ROUTER_PLAN.md` §2b. One decision left: a second card list on
   the finder, or its own topic. The user's earlier framing points at the former.
   ⚠️ Several natural use cases would currently walk a reader into a 500 — do item 1 first.
4. **2 of 96 gene sets are public** (1 of 85 organisms, Nematostella). Unchanged since July.
   Testers auto-login IP_IN_RANGE and never exercise the public path, so "invite everyone"
   would flip on a configuration nobody has used. This is a data/policy call, not code.
5. **Medicago_truncatula and Turritopsis_dohrnii** still have `feature_annotation` empty —
   annotations loaded, join table empty, annotation search silently returns 0 with HTTP 200.
   Data side; the reload should fix it and the dashboard will now say so if it does not.
6. Low: `.feature-header` pages jump `<h1>` → `<h5>` → `<h4>`, so heading order is not
   monotonic. Cosmetic accessibility, no visible defect.

---

## Useful commands

```bash
php scripts/warm_organism_cache.php --organism=<Name>   # one organism, ~0.1s
php scripts/warm_organism_cache.php --force             # all 85, ~92s
php tests/smoke_tests.php ; echo $?                     # 156 assertions — CHECK THE EXIT CODE
node tests/js_smoke_tests.js ; echo $?
php scripts/extract_page_purposes.php --write           # rebuild the finder's routes
```

⚠️ Headless verification: puppeteer-core lives in `~/.moop-headless/node_modules/`, Chrome at
`~/.cache/puppeteer/chrome/linux-148.0.7778.97/chrome-linux64/chrome`. Always
`setCacheEnabled(false)` — a cached page compares equal to itself. Screenshot `clip` wants
`{x,y,width,height}` and **integers**; `{w,h}` or floats fail with a deserialize error.
