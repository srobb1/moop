# Query performance — what is fast, what is slow, and what actually decides it

Measured 2026-07-22 against the live databases on this host. Every number below is real, not
estimated.

**The short version: query shape matters far less than whether the data is already in the
OS page cache.** The same `COUNT(*)` took **7,051 ms cold and 2 ms warm** — a ~3,500×
difference, on identical SQL. Optimising SQL is worth doing, but it is second-order next to
cache residency.

---

## The structural fact everything follows from

| | |
|---|---|
| Organism databases | **85**, totalling **66.2 GB** |
| Largest single DB | 1,915 MB (`Myotis_myotis`) |
| Host RAM | 15 GB, of which ~12 GB is page cache |

**Roughly 18% of the data can be resident at once.** So a cold read is the *normal* case, not
the exception — especially for a cross-organism search, which touches databases nobody has
opened recently. This is not a misconfiguration; it is the shape of the deployment.

---

## Cold vs warm — the measurement that matters

Same query, same database, back to back:

```
COUNT(*) FROM annotation   (Procerodes_sp, 182,405 rows)
  cold : 7051.8 ms      <- first touch, reading from disk
  warm :    2.0 ms      <- pages now cached
```

Ten seconds of "slow query" was almost entirely disk I/O. Before optimising any query, check
whether you are measuring the query or the disk.

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
