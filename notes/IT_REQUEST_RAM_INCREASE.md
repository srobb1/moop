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
  of text that already existed in the database. Eliminating it reduces each database by
  **17–45%** depending on content, roughly **20 GB across the corpus**.
- **Removed a duplicate database index** that was maintained on every write for no benefit.
- Reviewed the schema for further redundancy; bulk regenerable data is already kept in flat
  files outside the databases by design.

After those reductions the working set is approximately **47 GB**. At 64 GB of RAM the
entire dataset stays cached, and the cold-read penalty disappears rather than being
reduced. That is why 64 GB rather than 32 GB — 32 GB would still leave a meaningful
fraction of the corpus uncached, and the dataset grows as organisms are added.

## If only one is possible

RAM is the higher-value change. Faster storage reduces the cold penalty by perhaps 50–100×;
sufficient RAM removes it, because the data is only read from disk once.

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
