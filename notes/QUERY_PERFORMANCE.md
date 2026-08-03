# Query performance — what is fast, what is slow, and what actually decides it

Measured 2026-07-22 against the live databases on this host, **and re-measured 2026-07-31
after the reload and the FTS rebuild.** Every number below is real, not estimated.

**The short version: query shape matters far less than whether the data is already in the
OS page cache — and, it turns out, far less than whether the bytes are contiguous.** The
same `COUNT(*)` took **401 ms cold and 2.7 ms warm** (~150×). Optimising SQL is worth
doing, but it is second-order next to cache residency and disk layout.

---

## The structural fact everything follows from

| | 2026-07-22 | **2026-07-31** |
|---|---|---|
| Organism databases | 85, totalling 66.2 GB | 85, totalling **33 GB** |
| Largest single DB | 1,915 MB (`Myotis_myotis`) | — |
| Host RAM | 15 GB, ~12 GB page cache | unchanged |

**Roughly a third of the data can now be resident at once**, up from 18%. A cold read is
still the *normal* case for a cross-organism search, which touches databases nobody has
opened recently. This is not a misconfiguration; it is the shape of the deployment.

---

## Cold vs warm — the measurement that matters

Same query, same database, same row count, back to back:

```
COUNT(*) FROM annotation   (Procerodes_sp, 182,405 rows)

  2026-07-22   cold : 7051.8 ms     warm : 2.0 ms
  2026-07-31   cold :  400.6 ms     warm : 2.7 ms      <- 17.6x faster cold
```

**Nothing about the query changed. The bytes moved.** Two things did it, and neither was a
query optimisation:

- the 2026-07-30 **reload** — contentless FTS dropped the duplicate `_content` table and
  `date` moved off `feature_annotation` onto `annotation_source`, halving the corpus;
- the 2026-07-31 **FTS rebuild**, which ran `VACUUM` — defragmenting each file so the
  annotation table's pages are contiguous instead of scattered through it.

The same lesson arrived independently from the search fan-out the same day: throughput went
from 16 MB/s to 27 MB/s not by reading less, but by reading in rowid order instead of
scattered lookups. **On this disk, contiguity is worth more than volume.**

Seven seconds of "slow query" was almost entirely disk I/O, and most of that was seeking.
Before optimising any query, check whether you are measuring the query, the cache, or the
disk layout.

⚠️ **Methodology caveat:** dropping the page cache needs root, which this measurement did not
have. "Cold" here means *not yet touched in this session*, which is a fair proxy but not a
guarantee. Warm numbers are exact.

---

## Fast (all warm, so these are true query costs)

| Query | Time | Notes |
|---|---|---|
| `feature` by `feature_uniquename` | **0.9 ms** | unique index — the gene-page lookup |
| `COUNT(*)` by `feature_type` | **21.5 ms** | indexed |
| `COUNT(*) FROM annotation` | **2.8 ms** | warm; see the cold figure above |
| **FTS5 `MATCH`** | **3.4 ms** | 85,615 hits, Nematostella |
| Annotations for one feature (2 joins) | **27 ms** | the gene page's own query |

**FTS5 is ~11× faster than the `LIKE` it replaced** (3.4 ms vs 37.3 ms warm, same DB), and
the gap widens on bigger databases. That migration earned its keep.

## Slow

| Query | Time | Why |
|---|---|---|
| `COUNT(*) FROM annotation` **cold** | **7,051 ms** | full scan against disk |
| `COUNT(*) FROM feature` (1.9 GB DB, cold) | **2,661 ms** | same |
| `GROUP BY annotation_type` + join, cold | **3,989 ms** | full scan of the annotation table |
| `LIKE '%term%'` | **37 ms warm / 3,083 ms cold** | no index can serve a leading wildcard |

**`COUNT(*)` on a large table is the trap.** It looks trivial and reads the whole table.
Never put one on a page-load path — cache it, or count something indexed.

---

## Cross-organism fan-out — the launch-relevant number

Searching all 85 organisms, one FTS query each, from a largely cold cache:

```
  sequential total : 33,310 ms
  mean per DB      :    392 ms
  slowest DB       :  1,753 ms  (Procerodes_sp)
  at concurrency 5 :  ~6,662 ms   <- MOOP's actual fan-out
```

⚠️ The concurrency figure is `total / 5`, an idealisation — real parallel execution adds
scheduling and contention, and 85 concurrent SQLite opens contend for the same disk. Treat
**~6.7 s as a floor**, not a promise.

Two consequences worth planning around:

1. **The first cross-organism search after a quiet period is the slow one.** Subsequent
   searches are dramatically faster while the pages stay resident — but with 66 GB against
   12 GB of cache, they do not stay resident long.
2. **Raising concurrency past 5 will not scale linearly** once the bottleneck is disk rather
   than CPU. Measure before changing it; more parallelism against a cold cache can be worse.

---

## Why bulk data lives in flat files

Reading `feature_coords.tsv` **in its entirety** — 107,643 rows, 6 MB:

```
  scan for one feature (early hit) : 0.0 ms
  read the whole file             : 6.8 ms
```

Reading the entire file costs less than a single cold database page-fault. That is the
concrete argument behind the schema stance in CLAUDE.md §9: bulk, per-feature, regenerable
positional data is cheaper beside the gene set than inside a database whose size determines
how much of *everything else* stays cached.

**Every megabyte added to `organism.sqlite` competes for the same 12 GB of page cache.** That
is the real cost of schema growth here — not disk space, but the eviction of data that a user
is about to need.

---

## Why this was hard to track down

Worth recording, because the difficulty is structural and will recur.

**Investigating the problem destroys it.** The first run of a query pulls its pages into
cache; every run after that is fast. So the sequence is always:

1. A user reports a page or search being slow.
2. You open a shell and run the query. **2 ms.** Nothing looks wrong.
3. You run it again to be sure. Faster still.
4. You conclude the query is fine and go looking somewhere else.

You were measuring your own warm-up. And it is worse than a plain Heisenbug, because the
*fast* reading is the reproducible one — you can demonstrate "it's fine" on demand, all day,
and never see the 7 seconds the user saw.

What made it visible here was **timing the same query on a database that had not been touched
yet in the session, then immediately again** — cold and warm, back to back, in one run. Either
measurement alone is misleading; only the pair shows the effect.

Two corollaries for anyone chasing a "slow query" report in future:

- **A single timing is worthless** unless you state whether the data was cold. Always report
  both, or say which one you measured.
- **Explanations that fit the fast reading are traps.** "It must be the network", "it must be
  PHP", "it must be the browser" all become attractive precisely because the query keeps
  measuring fast. The query really is fast — the *second* time.

## Practical rules

1. **Never `COUNT(*)` a large table on a page load.** Cache it via housekeeping, or count
   through an index.
2. **Prefer FTS5 `MATCH` over `LIKE`** for any text search. Already true site-wide.
3. **Filter on indexed columns.** `feature_uniquename`, `feature_type`, `gene_set_id`,
   `parent_feature_id`, `annotation_accession` and the FTS tables are indexed; nothing else
   is.
4. **Assume cold.** A query that is instant in your shell may be seconds for the first user
   to hit it. Test after touching a database you have not opened.
5. **Expensive aggregates belong in housekeeping**, precomputed to
   `logs/.housekeeping_status.json` — the pattern the dashboard already uses.
6. **Keep the databases small.** See CLAUDE.md §9. Size is not just storage; it is cache
   pressure on every other query.

---

## Not yet measured

- Real concurrent fan-out under load (the figure above is arithmetic, not observed).
- Whether the tail organisms are slow because of size or FTS index shape.
- Behaviour with several users searching at once — the launch case that actually matters.

Related: [CLAUDE.md §9](../CLAUDE.md), [FTS5 search plan](../notes/), Expression Explorer
precompute rationale.

---

## Working set by search path (measured 2026-07-27)

The two search paths have very different footprints, which matters because it decides how
much has to stay resident for the *front door* to be fast.

| organism | post-reload size | feature search | annotation search |
|---|---|---|---|
| Schmidtea_polychroa | 117 MB | 13 MB (11%) | 101 MB (86%) |
| Petromyzon_marinus | 174 MB | 44 MB (25%) | 133 MB (76%) |
| Nematostella_vectensis | 349 MB | 36 MB (10%) | 306 MB (88%) |

**Feature search** (`searchFeaturesByNameDescription`, the main search box) reads the
`feature_search` FTS index joined to `feature` by rowid, plus the tiny gene_set/genome/
organism lookups. It never touches `annotation` or `feature_annotation`. ~15% of a
database, so roughly **5.5 GB across all 85** — which fits in the current 12 GB of page
cache.

**Annotation search** (`searchFeaturesAndAnnotations`) reads the much larger
`feature_annotation_search` index plus `annotation` and `feature_annotation` to display
results. ~85% of a database — essentially the whole corpus, ~37 GB post-reload. This is
what drives the RAM requirement, not the front door.

Practical consequence: after the reload, keeping only the feature-search objects resident
(e.g. `vmtouch`) makes the primary search path fast with **no hardware change**. Deep
annotation search is the part that needs more memory or faster storage.

### Two rules for any prewarming task

Carried over from the 2026-07-23 cold-cache plan, which is otherwise superseded by this
document and by `SEARCH_COST_MODEL_2026-07-31.md`:

- **Warming is eviction, not free storage.** Reading the ~37 GB of annotation indexes on a
  15 GB box evicts everything else, including whatever the last user warmed, and can leave
  the site *slower* under concurrent load. Any prewarm task needs a hard byte budget and
  must be judged by what it evicts, not by what it loads. This is why the feature-search
  set (~5.5 GB, fits) is the only one worth warming wholesale.
- **"Warm the largest databases first" is backwards.** The largest databases have the
  largest annotation indexes, so warming them spends the most cache on the fewest
  organisms. If the goal is that *most* searches feel fast, warm small-and-likely over
  large. Warming large only wins if the largest organisms are also the most searched —
  and MOOP does not log searches, so that cannot currently be answered.

Any such task belongs in `lib/housekeeping.php` (CLAUDE.md §10), interval-throttled, never
inline in a request.

### PRAGMA mmap_size — tested, NOT adopted

Tested at 256 MB against the FTS hot path: **0.3 ms/query without, 0.4 ms/query with** —
no benefit. The theory (SQLite's page cache double-buffering against the OS page cache)
does not apply at MOOP's scale, because SQLite's default cache is only 2 MB per
connection, and the memcpy it avoids is invisible on small FTS lookups.

Not adopted, because mmap turns an I/O error into SIGBUS — a killed php-fpm worker rather
than a catchable exception. Real downside, unmeasurable upside. Revisit only if a workload
appears that does large sequential scans.
