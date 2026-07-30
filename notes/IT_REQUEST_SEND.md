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

Nine reads in ten reach the disk. The user-visible effect, on a real search against one
organism, with the cache verified empty before the cold run:

| | time |
|---|---|
| **Cold** — data not in cache | **8,390 ms** |
| **Warm** — data in cache | **4.6 ms** |

The query is identical in both cases. The only variable is whether the bytes are already in
memory. In practice the first person to touch an organism waits several seconds and the next
waits milliseconds — and with 85 organisms against 12 GB of usable cache, most visits are the
first kind.

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

**Faster storage is worth roughly 2× on real queries** — worthwhile, but smaller than the
device latency alone would suggest, because these queries read in largely sequential runs
that readahead handles well. It is the second ask for that reason, not the first.

---

## What we have already done

We reduced our own footprint before asking. The corpus was **67 GB** and is now **32.7 GB**,
a 51 % reduction:

- **Removed duplicated full-text search storage** — the search index held a second full copy
  of text already present in the database (38–49 % of each file).
- **Removed a redundant database index** that was maintained on every write for no benefit.
- **Moved search ranking inside the index**, cutting the working set of a cross-organism
  search from 13.4 GB to 3.6 GB.
- Reviewed the schema for further redundancy; bulk regenerable data already lives in flat
  files outside the databases by design.

Neither change is sufficient alone — 67 GB was never going to fit in 64 GB of RAM, and
32.7 GB does not fit in 16 GB.

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
