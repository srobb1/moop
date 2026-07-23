# Cold-cache search latency — prewarming, and what it can and cannot fix

Status: **idea recorded, not implemented.** Raised by the user 2026-07-23 after hitting a
slow search themselves. Escalated from "measure it" to "solve it" and added to the launch
priority list.

Background numbers: `notes/QUERY_PERFORMANCE.md`. Read that first — this document only
covers the *remedy* question.

---

## The proposal

> Prewarm the largest databases — the largest take the most time. Maybe largest plus
> featured.

The instinct is right (get the pages resident *before* a user asks) and the "featured"
half is right for a reason worth stating: featured organisms are what a first-time visitor
actually clicks, and **a first-time visitor's first search is by definition cold**. That is
precisely the launch case.

The "largest" half needs a correction, and there is a decomposition that makes the whole
idea much cheaper.

---

## Measured 2026-07-23

**85 databases, 67.8 GB total.** Sizes are fairly uniform — largest `Myotis_myotis`
1,916 MB, and the top twelve all sit between 1.4 and 1.9 GB. There is no small set of
outliers to target: "the largest DBs" is most of the corpus.

**The FTS index is ~26–28% of each database**, and it splits very unevenly:

| | Nematostella_vectensis (689 MB) | Myotis_myotis (1,916 MB) |
|---|---|---|
| `feature_search_data` (names / IDs) | **6 MB** | **7 MB** |
| `feature_annotation_search_data` | 170 MB | 532 MB |

Extrapolated across 85 organisms:

- **`feature_search` total ≈ 0.5 GB** — comfortably resident in ~12 GB of page cache.
- **`feature_annotation_search` total ≈ 18 GB** — *cannot* all be resident.
- Whole corpus 67.8 GB against ~12 GB cache = a **5.6 : 1** ratio.

---

## What this means

**1. You cannot prewarm your way out of a 5.6:1 ratio.** Warming is not free storage; it is
eviction. Reading 18 GB of annotation indexes on a 15 GB box evicts everything else,
including whatever the last user warmed, and can leave the site *slower* under real
concurrent load. Any prewarming task needs a hard byte budget and must be judged against
what it evicts, not just what it loads.

**2. "Largest first" is probably backwards for coverage.** The largest databases have the
largest annotation indexes, so warming them spends the most cache on the fewest organisms.
If the goal is that *most* searches feel fast, warming small-and-likely beats warming large.
Warming large only wins if the largest organisms are also the most searched — which is an
open question, and MOOP does not log searches today, so it cannot currently be answered.

**3. The cheap win nobody has taken: warm `feature_search` for ALL 85 organisms.** About
0.5 GB total makes every gene-ID and gene-name lookup — the single most common thing a
biologist does — fast site-wide, permanently. This is a much better first move than warming
any set of full databases.

**4. Featured/public is the right targeting signal for the annotation index**, because it is
small, already curated by the admin, and matches where first-time traffic actually lands.
Today that is 2 gene sets; the cost is bounded and known.

---

## ⚠️ The premise that is still unverified

**"The largest take the most time" is untested for *search*.** The headline 7,051 ms cold
figure came from a `COUNT(*)` — a full-table scan, which does scale with size. An FTS5
`MATCH` reads index pages proportional to how selective the term is, **not** to total
database size. The actual cold cross-organism search numbers show a much narrower spread:
mean 392 ms per DB, slowest 1.75 s — about 4.5×, not 3,500×.

So before building anything: **measure cold search time against database size across a
sample of organisms and see whether they correlate at all.** If they do not, targeting by
size is targeting by the wrong variable, and the whole "largest" strategy should be dropped
in favour of "most likely to be searched".

---

## Candidate approaches, roughly in order of cost

1. **Warm `feature_search` for all organisms** (~0.5 GB). Cheapest, broadest, safest.
   A housekeeping task reading those index pages on an interval.
2. **Warm `feature_annotation_search` for featured/public gene sets only.** Bounded,
   targeted at first-visit traffic.
3. **Progressive result streaming** — do not fight the floor, hide it. Return each
   organism's hits as they arrive so the first results appear in a few hundred ms instead
   of after the full fan-out. This is the only option that helps the genuinely cold
   85-organism case, and it improves perceived speed even when nothing is resident.
   (Related: the long-standing "progressive MOOPmart loading" item.)
4. **Shrink what a search must read** — narrower FTS row payloads, fewer stored columns.
   Directly reduces the 18 GB.
5. **One cross-organism search index instead of 85 files.** Biggest potential win and the
   biggest change; would need to respect per-gene-set access control, which is exactly why
   the per-organism split exists. Do not start here.

---

## Rules for whoever does this

- **Measure cold AND warm, back to back, in one run.** Investigating a query warms it;
  the fast reading is the reproducible one. This is why the problem went undiagnosed for
  so long — see `reference_query_perf_cold_cache` memory.
- **Concurrent multi-user load has never been measured** and is the case that decides
  whether launch feels fast. The 6.7 s cross-organism figure is arithmetic at concurrency
  5, not an observation.
- Any prewarm task belongs in `lib/housekeeping.php` (CLAUDE.md §10), must be interval
  throttled, and must not run inline in a request.
