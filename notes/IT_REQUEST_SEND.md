# Request: additional memory and faster storage for the SIMRbase VM

**Host:** simrbasenew · RHEL 9 · currently 16 GB RAM, 1 TB data volume

---

## The asks

1. **Increase VM memory from 16 GB to 64 GB.** (32 GB as a reversible first step, if
   preferred — see *How much memory* below for what each buys.)
2. **Move the data volume `/dev/sdb` to the same storage tier `rootvg` already uses.** Only
   the databases need it: **33 GB**, not the whole 900 GB volume.

Both are virtualisation-layer changes — no hardware purchase, no licensing, no downtime
beyond a reboot. RAM is the larger effect by roughly 6×; the two compound.

---

## What the application does

SIMRbase serves a genomics dataset — **85 SQLite databases totalling 32.7 GB**, one per
organism, within a 233 GB data tree — to researchers inside and outside the institute.
Access is read-only. A search reads scattered pages from a database; a cross-organism search
touches all 85.

The site is opening to external users shortly, and search is the primary entry point.

---

## Ask 1 — memory

### The measurement

The databases are read through the **filesystem cache**. The requirement is therefore cache
capacity, not process memory. Measured with `mincore(2)` across all 85 files:

> **32.7 GB of databases, of which 3.33 GB is resident — 10.2 %.**

Nine reads in ten reach the disk.

### What that costs a user

Searching one of our organism groups — **"Bats", 49 organisms** — is an ordinary action a
visitor performs from the front page. The browser issues one request per organism, five at a
time. Measured end to end through the live site:

| | wall clock | disk read |
|---|---|---|
| **As the site behaves today** (data not cached) | **24.6 s** | 658 MB |
| **The identical search, repeated immediately** (data cached) | **0.7 s** | 0 MB |

**A 63× difference, and nothing changed but what was in memory.** The slowest single
organism took 14.8 s; the median took 9.0 s.

Ninety seconds is not a slow search — it is a search a user abandons. The second run
finishing in 1.4 s is what the application is capable of when the data is resident, and it
is what every visitor would get with enough cache to keep it there.

Note the size: this group's entire working set is **658 MB**. It is not too large to cache —
it simply does not survive, because 12 GB of cache is shared across 32.7 GB of databases and
is reclaimed continuously (see the counters below).

### That it does not survive is now measured directly, not inferred

The same search was run twice on 2026-07-30, with **nothing changed but the passage of
time** — same term, same 49 organisms, same concurrency:

| | 15:57 | 18:26 |
|---|---|---|
| wall clock | **1.3 s** | **70.5 s** |
| read from disk | 0.0 MB | **536.5 MB** |
| **refaults** (pages evicted, then needed again) | 0 | **137,312** |
| free memory at the time | — | 0.6 GB |

**137,312 refaults x 4 KB = 536.4 MB, against 536.5 MB actually read.** To the nearest
megabyte, *every byte this search read from disk was a page the machine had already held in
memory and discarded.* Not new data being loaded — the same data, fetched twice.

That is the whole request in one line: the working set is **536 MB on a 15 GB machine**, it
was resident, and it did not last the afternoon. The problem is not that the data is too
large to cache. It is that there is not enough cache for it to stay cached.

### Why the usual memory metrics will not show this

**A "memory used %" or "swap %" threshold cannot detect this workload.** Page cache is
*reclaimable*: when it is too small, the kernel silently evicts and re-reads rather than
swapping. Live, while everything above is true:

```
              total   used   free   buff/cache   available
Mem:            15 G    2 G    0 G       12 G        12 G
Swap:            4 G  339 M    4 G
```

That reads as a healthy, under-utilised VM on any dashboard.

The counters that do show it, from `/proc/vmstat` over **50 days** of uptime:

| counter | value | meaning |
|---|---|---|
| `workingset_refault_file` | **59,069,789** | file pages evicted, then needed again — **~1.2 M/day** |
| `workingset_restore_file` | 4,714,548 | of those, still on the active list — genuine thrashing |
| `pgmajfault` | 446,162 | page faults that had to reach disk |
| `pgscan_kswapd` / `pgsteal_kswapd` | 239.7 M / 231.9 M | **97 % steal ratio** — reclaim is continuous |

**Most of this is already in your Prometheus.** `node_exporter` is running on the host and
exporting `node_vmstat_pgmajfault` and `node_memory_Cached_bytes` today.
`workingset_refault_file` is the one useful counter not exported by default — node_exporter
ships a field allowlist (`^(oom_kill|pgpg|pswp|pg.*fault).*`), so it needs
`--collector.vmstat.fields` widened. One flag.

`/proc/pressure/` (PSI) is not available on this kernel; it needs `psi=1` as a boot
parameter. We are happy to take that if a pressure metric would be more useful to you.

### How much memory

**92 gene sets in 32.7 GB → 0.36 GB per gene set**, which gives a planning rule:

> **RAM required ≈ 0.36 GB × (number of gene sets)**

| | usable cache | corpus | outcome |
|---|---|---|---|
| Today, 16 GB | ~12 GB | 32.7 GB | 10 % resident in practice |
| At 32 GB | ~28 GB | 32.7 GB | still short, no headroom — but would demonstrate the effect |
| **At 64 GB** | ~60 GB | 32.7 GB | **fits, with room for ~70 more gene sets** |

At our current rate of adding organisms, 64 GB is comfortably several years, and the rule
above lets us forecast the next request rather than discover it as a slowdown.

---

## Ask 2 — storage

The VM has two volumes on different tiers:

| device | LVM group | mounted at | `ROTA` |
|---|---|---|---|
| `sda` (40 G) | `rootvg` | `/`, `/home`, `/tmp`, `/var` | **0 — flash** |
| `sdb` (1 T) | `datavg` | **`/var/www/html`** — all genomes and databases | **1 — rotational** |

### Your own monitoring already measures this

Read latency per device, computed from node_exporter counters as
`node_disk_read_time_seconds_total / node_disk_reads_completed_total`, over 50 days of
production:

| device | volume | reads | avg latency |
|---|---|---|---|
| **dm-2** | **`datavg-datalv` → `/var/www/html`** | 11,206,436 | **20.77 ms** |
| dm-0 | `rootvg-rootlv` → `/` | 534,687 | 1.55 ms |
| dm-7 | `rootvg-varlv` → `/var` | 112,128 | 0.98 ms |
| dm-3 | `rootvg-tmplv` → `/tmp` | 38,557 | **0.43 ms** |

**The data volume is 13–48× slower than every other volume on the same VM**, across eleven
million real reads. Direct measurement with `O_DIRECT` agrees: 32–34 ms median for a random
4 K read on `sdb` against 0.5 ms on `sda`.

### What it is worth

We measured this rather than assuming it. The same 853 MB database was copied to each
volume and queried with real search terms, cache verified empty before each cold run, I/O
isolated as *cold − warm* so CPU is excluded, and bytes read taken from `/proc/diskstats`:

| search term | MB read | flash | spinning | ratio |
|---|---|---|---|---|
| `kinase` | 59.8 | 696 ms | 710 ms | 1.0× |
| `receptor` | 95.9 | 491 ms | 961 ms | 2.0× |
| `"zinc finger"` | 65.6 | 307 ms | 843 ms | 2.7× |

**Taken alone, a single query gains roughly 2× from flash** — smaller than the device latency
suggests, because one query reads in largely sequential runs that readahead handles well.

**Under real concurrency it is worse than that comparison implies.** During the 49-organism
group search above, the volume delivers **658 MB in 24.6 s — about 27 MB/s**, against the
78–101 MB/s it sustains for a single sequential reader. Five concurrent readers on five
different files turn sequential access into seek contention, which is precisely the case a
rotational disk handles worst and flash does not pay for at all.

⚠️ **This figure was 7 MB/s in earlier drafts, and we corrected it ourselves.** Roughly
three-quarters of that gap turned out to be the *shape* of our own reads, not the disk: our
search was performing a scattered index lookup per matched record, and we changed it to read
in physical order (2026-07-31). We are reporting the improved number because it is the one
you would reproduce. The remaining gap — 27 MB/s against 78–101 — is seek contention we
cannot remove in software.

We have not measured the group search on flash — `/home` has only 1.3 GB free, so the corpus
cannot be staged there. We therefore state the measured single-query figure (2×) rather than
an estimate, and note that the concurrent case is where the 20.77 ms average latency in your
own monitoring is actually being paid.

---

## What we have already done

We reduced our own footprint before asking. The corpus was **67 GB** and is now **32.7 GB**,
a 51 % reduction:

- **Removed duplicated full-text search storage** — the search index held a second full copy
  of text already present in the database (38–49 % of each file).
- **Removed a redundant database index** that was maintained on every write for no benefit.
- **Moved search ranking inside the index**, cutting the working set of a cross-organism
  search from 13.4 GB to 3.6 GB.
- **Changed how search reads the disk (2026-07-31).** Ranking every matched record required
  a scattered lookup per match; the pool is now assembled in physical order instead. A
  49-organism group search went from **89 s to 25 s**, and read throughput from **7 MB/s to
  27 MB/s** — on the same hardware, with no configuration change. We also rebuilt and
  compacted all 85 databases, which made a representative full-table query **17.6× faster
  cold** (7,051 ms → 401 ms) purely by making its pages contiguous.
- Reviewed the schema for further redundancy; bulk regenerable data already lives in flat
  files outside the databases by design.

**We have deliberately re-measured everything in this document after those changes**, and
revised several figures downward. The remaining request is smaller than our first draft, and
it is what is left after the software fixes we could make ourselves:

| | first draft | after our own fixes |
|---|---|---|
| Bats group search, uncached | 89.3 s | **24.6 s** |
| read throughput under fan-out | 7 MB/s | **27 MB/s** |
| corpus | 67 GB | **32 GB** |

What software cannot fix: **the same search is 0.7 s when the data is cached and 24.6 s when
it is not**, a 35× cliff that depends entirely on whether 32 GB of databases fit in ~12 GB of
cache. And our heaviest common search term still takes **90 s uncached**. Both are capacity
problems, not code problems — 32 GB does not fit in 16 GB.

### What the memory actually buys, stated precisely

Memory does not make a slow read fast. It makes the read **not happen a second time**. The
first visitor to search a given term still waits; everyone after them does not — *provided
the pages survive*, which today they do not. We measured that directly: an identical search
run 2.5 hours apart went 1.3 s → 70.5 s, and 100 % of the second run's disk reads were pages
the machine had already held and discarded.

With the corpus fully resident there is a further option not available to us today: **warm
the cache deliberately.** Running the common searches once after a reload or reboot would
leave them resident permanently, so no user pays the first-touch cost either. There is no
point doing that now — the pages are evicted within hours — but at 64 GB it converts the
worst case from "90 s for whoever searches first" to "90 s once, at a time we choose".

---

## Reference

```
VM memory:      16 GB total, ~12 GB filesystem cache, ~2 GB in use
Swap:           4 GB, 339 MB used — correctly idle; swap does not address a page-cache shortage
Data volume:    /dev/mapper/datavg-datalv (XFS) on sdb, 1 TB, ROTA=1, 251 GB used of 900 GB
Root volume:    rootvg on sda, ROTA=0
Dataset:        32.7 GB across 85 SQLite databases, 92 gene sets, in a 233 GB data tree
Access pattern: read-only, random reads across many files
OS:             RHEL 9 (Linux 5.14)
```

Happy to walk through any of these measurements, or to re-run them with someone watching.
