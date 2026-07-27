# Request to IT: increase RAM on the MOOP/SIMRbase web VM

Draft to send. Two asks: **more RAM**, and **confirmation of what the data volume is
backed by**. Numbers below are measured on the host, not estimates.

---

## The ask

1. **Increase this VM's memory from 16 GB to 64 GB.**
2. **Confirm whether the datastore backing the data volume (`sdb`, 1 TB) is SSD/flash or
   spinning disk.** The guest reports it as rotational (`ROTA=1`). If that is accurate
   rather than an artifact of virtualisation, we would like the volume moved to
   flash-backed storage — or a small SSD cache placed in front of it.

Both are virtualisation-layer changes. No hardware purchase or software licensing is
involved, and no application downtime beyond a reboot.

---

## Why

The site serves a genomics dataset of **67 GB of databases** (85 SQLite files, one per
organism) from a VM with **16 GB of RAM**, of which roughly 12 GB is available as
filesystem cache.

Because the data is five times larger than the cache, most reads miss the cache and go to
disk. The measured effect on a single identical query:

| | Time |
|---|---|
| Cold (data not in cache) | **7,051 ms** |
| Warm (data in cache) | **2 ms** |

That is a **~3,500× difference**, and it is entirely determined by whether the bytes are in
memory. The query itself is unchanged.

The practical consequence is that the first person to search a given organism waits about
seven seconds; the second person waits milliseconds. With 85 organisms and a 12 GB cache,
most visits are the first kind.

## Why this matters now

The site is about to open to external users. Search is the primary entry point, so a
first-time visitor is exactly the user who hits cold data. A multi-second delay on the
first action reads as a broken site, and it is the single largest performance problem we
have.

## What we have already done to reduce the requirement

We did not want to ask for hardware before reducing our own footprint. Already completed:

- **Removed duplicated full-text search storage.** The search index kept a second full copy
  of text that already existed in the database. Measured across four representative
  databases the saving is **38–49%** (the larger the database, the larger the share),
  roughly **29 GB across the corpus**.
- **Removed a duplicate database index** that was maintained on every write for no benefit.
- Reviewed the schema for further redundancy; bulk regenerable data is already kept in flat
  files outside the databases by design.

After those reductions the working set is approximately **37 GB** (measured, not
estimated: 67 GB × the 45% average reduction). At 64 GB of RAM the entire dataset stays
cached and the cold-read penalty disappears rather than being reduced.

**Why 64 GB and not 32 GB.** 32 GB leaves roughly 26 GB of cache against 37 GB of data —
still short, so the thrashing continues and the benefit is partial. 64 GB clears it with
headroom for growth (see below). Note also that neither change works alone: 67 GB does
not fit in 64 GB of RAM, and 37 GB does not fit in the current 16 GB. The reduction and
the memory increase are only sufficient together.

## If only one is possible

RAM is the higher-value change. Faster storage reduces the cold penalty by perhaps 50–100×;
sufficient RAM removes it, because the data is only read from disk once.

---

## How much RAM per unit of data — and when we will need more

Measured across all 85 databases: **136,116,755 annotation records in 67 GB**, i.e. **522
bytes per record** today and **~287 bytes per record** after the reduction above. That is
the whole cost — the record, its text, its search-index entry and its indexes.

That gives a simple planning formula:

> **RAM required ≈ 0.29 KB × (annotation records)**
> **≈ 0.42 GB × (number of gene sets)**

A gene set averages ~1.5 million annotation records, so **each new gene set costs roughly
0.4 GB of RAM** (range 0.15–0.8 GB, driven by how heavily annotated it is, not by genome
size).

Current position and headroom:

| | gene sets | data | fits in |
|---|---|---|---|
| Today | 92 | 67 GB | needs ~78 GB — we have 12 GB of cache |
| After the reduction | 92 | 37 GB | needs ~37 GB |
| Ceiling at 64 GB RAM | **~130** | ~55 GB | ~55 GB usable cache |

So 64 GB accommodates roughly **40 more gene sets** beyond today's 92 — at our current
rate of adding organisms, comfortably several years. We would expect to return to this
conversation when the collection approaches ~130 gene sets, and the formula above lets us
predict that rather than discover it as a slowdown.

For context on how far behind we currently are: 12 GB of cache supports about 28 gene
sets. We have 92.

---

## Technical details, for reference

```
VM memory:      16 GB total, ~12 GB filesystem cache, ~2 GB in use
Data volume:    /dev/mapper/datavg-datalv (XFS) on sdb, 1 TB, reported ROTA=1 (rotational)
Dataset:        67 GB across 85 SQLite databases  (in a 267 GB data tree)
Access pattern: read-only, random reads across many files; a cross-organism search
                touches all 85 databases
Measurement:    identical COUNT(*) query, cold vs warm page cache
OS:             RHEL 9 (Linux 5.14)
```

Swap is present (3 GB) and correctly unused — swap does not help here, since the problem is
too little cache for file reads, not memory pressure from processes.
