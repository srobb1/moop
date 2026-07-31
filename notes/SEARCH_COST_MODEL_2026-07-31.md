# Cold search: the measured cost model, and what to do about it

**Date:** 2026-07-31. Everything here is measured on this host unless marked *estimated*.
Harness: `testing/fts_split/` (`bench.py`, `decompose.py`, `nobm25.py`, `ladder.py`) and
`notes/bench/persistence.py`. All uncommitted at time of writing.

Measurement discipline used throughout: eviction via `posix_fadvise(DONTNEED)` on the
specific files only (never `drop_caches`, which would make the site slow for hours),
**verified with `mincore()`** every run, bytes from `/proc/self/io read_bytes`, and the
prototype built on the same slow volume as the real databases (`/tmp` is on the fast root
disk — a prototype there wins on device speed alone).

---

## 1. The cost model

`Rhinolophus_ferrumequinum`, cold, cap 2500, pool 5000:

| term | matching docs | FTS match | **bm25 rank** | row fetch | total |
|---|---|---|---|---|---|
| piwi | 304 | 0.4 MB | 1.8 | 2.2 | 4.4 MB |
| pax | 6,001 | 0.5 | 4.9 | 5.0 | 10.4 MB |
| helicase | 18,839 | 0.7 | **24.7** | 14.7 | 40.1 MB |
| ubiquitin | 75,512 | 0.7 | **42.2** | 27.5 | 70.4 MB |
| kinase | 219,663 | 0.7 | **47.1** | ~7 | 54.2 MB |
| binding | 322,361 | 0.7 | **51.1** | 21.7 | 73.5 MB |

**Finding the matches costs 0.4–0.7 MB regardless of term or index size.** All of the cost
is `bm25()`, which reads a scattered `_docsize` entry per matching document and saturates
once it has touched the whole 36.7 MB docsize table. Cost scales with **matching document
count**, not database size and not index size.

Cross-check against live 49-organism group searches:

| term | model (per-org × 49) | measured live |
|---|---|---|
| helicase | 1,965 MB | 2,517 MB / 156.9 s |
| pax | 510 MB | 299 MB / "pretty fast" |

---

## 2. Ruled out by measurement — do not re-propose

- **A smaller FTS index does not speed queries.** Built a per-annotation index 17.8×
  smaller (395 MB → 22 MB). The query got **slower**. The index is never read in bulk.
  Size matters for disk and cache residency, not for query time.
- **Concurrency.** 5 → 15, on disjoint cold halves of the same term: **1.12×**. The disk is
  seek-saturated, not starved of request slots.
- **Restoring the pre-FTS ranking ladder verbatim** (from `d691848^`). Worse: `helicase`
  returned 60/100 — 40% of page one did not contain the search term. It graded
  `feature_description` across three tiers but `annotation_description` across one, so
  annotation substring matches fell into `ELSE 7` with the stem noise and were then ordered
  alphabetically. Correct for a LIKE-based search over a small corpus; backwards for an
  annotation search.
- **Dropping bm25 naively** (pool by `rowid`): 2–3.5× cheaper and precision stays 100/100,
  but ProtNLM drops to **0 rows** on every heavy term and sources collapse 16 → 2.

---

## 3. What bm25 is actually doing

It is the **fifth** tier in the `ORDER BY` (`lib/database_queries.php:733`), below
`name_match`, the literal annotation LIKE, the stem tier and the has-a-name tie-break. It
barely affects the final order. Its expensive job is **selecting the 5,000-row pool**.

What it buys there is source spread — and it buys it badly. bm25's document-length
normalization favours short documents, and the shortest documents are ProtNLM's terse
AI-generated protein names. Page-one composition against the curated order in
`metadata/annotation_config.json`:

| term | Orthologs (1) | Domains (4) | Gene Families (7) | **AI Annotations (8)** |
|---|---|---|---|---|
| helicase | 2 | 0 | 16 | **74** |
| kinase | 23 | 2 | 30 | **45** |
| binding | 5 | 2 | 8 | **37** |
| pax | 0 | 36 | 15 | **25** |

**bm25 ranks by brevity, and brevity correlates with the annotation type ranked 8th of 10**
— the one the config annotates "So far Sofia doesn't love these". Meanwhile Orthologs, rank
1, gets 2 rows on `helicase` and 0 on `pax`.

`annotation_source.annotation_type` is already in the database on a tiny table the query
already joins, so a curated tier costs no extra reads. Applied as a **hard** tier it
over-corrects to 100/100 a single type. The right shape is a **per-type quota pool** in
curated order — diversity by intention rather than by accident. **Untested.**

Measured with the curated tier and a cheap pool (precision 100/100 throughout):

| term | today | curated tier + cheap pool |
|---|---|---|
| binding | 84.4 MB | **24.1 MB** |
| kinase | 60.4 MB | **25.5 MB** |
| pax | 17.4 MB | **5.6 MB** |
| helicase | 48.5 MB | 44.1 MB |

---

## 4. The plan

| step | effect | cost | state |
|---|---|---|---|
| 1. `annotation_type` as an FTS column + per-type quota pool | 2–3.5× less I/O, results aligned to curation | re-index + query rewrite | **next** |
| 2. Combined cross-organism index (rank once, not 49×) | *estimated* 2,517 MB → ~20 MB | 3–4 days + access-control redesign | estimated only |
| 3. RAM to 32 GB | hides remaining cost on repeat searches only | blocked on IT | — |

**Step 1 is a re-index, not a reload.** `config/build_and_load_db/data_loaders/build_fts_index.sql`
line 10: *"Safe to re-run any time: it drops and rebuilds from the current tables"*, atomic
in one transaction, rolls back cleanly if killed. ~20–40 min for 85 databases, then
`VACUUM`. No pipeline risk, no data risk.

**Step 2 is where the order of magnitude is.** The dominant multiplier is that each of 49
organisms runs its own bm25 pass over its own docsize table. One index means one pass.
Supporting measurement — cross-database duplication over 79 organisms:

```
total annotation rows across sample     25,699,753
sum of per-database distinct            24,805,355
ACTUAL distinct across all of them       1,388,837   -> 17.86x
union.sqlite on disk                          139 MB
```

Saturates hard: organism 1 contributed 370,778 unique (accession, description) pairs;
organism 50 contributed about 5,000. The entire annotation vocabulary of the site is 139 MB.

Risks for step 2: **cross-organism access control** (today privacy is enforced by not
opening the database — one missing gene-set filter leaks), a staleness fingerprint, global
vs per-organism result caps, and loss of per-organism streaming.

---

## 5. Two things about testing

**Both relevance metrics in use are flawed.**

- **100/100** (top-100 rows literally containing the term) is blind to quality. It tests the
  same substring the LIKE tiers test, so it agrees with whatever it measures. It scored a
  clean 100/100 on a pool that had silently dropped every ProtNLM row.
- **whole-word (`\bterm\b`)** actively penalizes correct results: it scores PAX3 as a miss
  for "pax", when PAX3 is exactly what the user wants.

Neither can adjudicate a ranking change. **A test that can see type/source diversity is
needed** — something like "page one draws from at least N annotation types, and no single
type exceeds M rows". That gap is real independent of any fix here.

**Perceived speed is already solved; duration is not.** The fan-out in
`js/modules/annotation-search.js` runs one request per organism at concurrency 5 and renders
each as it lands. Measured on `ubiquitin` (54.5 s total, 1,172 MB): first result at 4.4 s,
**longest stall 3.4 s**. No UI work can rescue a multi-minute search — the fix has to be
duration. Note that a combined index returns everything in one query and would *lose* the
streaming, which is only acceptable if it is genuinely fast.

---

## 6. Related correction

`notes/IT_REQUEST_SEND.md` (commit `b846585`) argues the working set "did not last the
afternoon". On 2026-07-31 the same piwi/Bats search survived **13.8 hours** — 1.31 s, 0 MB,
0 refaults. Elapsed time evicts nothing; competing reads do (in the original 2.5 h window
`Cached` went 2.1 → 11.8 GB, ~10 GB of other reading). **That document is refutable as
written and should be rewritten** around the per-term cost spread instead.

`notes/bench/persistence.py --show` has been fixed to record absolute counters and report
what happened *during* the gap, so it now calls an idle gap not-evidence rather than
"SURVIVED".
