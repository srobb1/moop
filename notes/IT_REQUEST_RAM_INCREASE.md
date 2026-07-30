# Request to IT: faster storage and more RAM for the MOOP/SIMRbase VM

**Status 2026-07-30: numbers current, ready to send.** Rewritten after the pipeline reload
completed — every figure below was re-measured on that date. Two earlier drafts contained
wrong numbers (§5 records both, and why), so nothing here is carried over on trust.

**RAM leads. Storage is the smaller, cheaper complement.** An earlier version of this file
put storage first on the strength of a 60× device-latency gap. That gap is real but it is a
*device* characteristic, not a *query* one — see §5, trap 4. Measured on real searches,
flash buys **~2×** and sufficient RAM buys **~13×**.

---

## PART 1 — The message to send

### The asks

1. **Move the data volume (`/dev/sdb`) to the same storage tier `rootvg` is already on.**
   Only the databases need it: **33 GB**, not the whole 900 GB volume.
2. **Increase VM memory from 16 GB to 64 GB** (32 GB as a reversible first step).

Both are virtualisation-layer changes. No hardware purchase, no licensing, no downtime
beyond a reboot.

### What the site does

SIMRbase serves a genomics dataset — **85 SQLite databases totalling 32.7 GB**, one per
organism, inside a 233 GB data tree — from a VM with **16 GB of RAM**, of which roughly
12 GB is usable as filesystem cache. Access is read-only and random: a single search reads
scattered pages from a database, and a cross-organism search touches all 85.

### Ask 1 — storage

The VM has two volumes, **on different tiers**:

| device | LVM group | mounted at | `ROTA` |
|---|---|---|---|
| `sda` (40 G) | `rootvg` | `/`, `/home`, `/tmp`, `/var` | **0 — flash** |
| `sdb` (1 T) | `datavg` | **`/var/www/html`** — all genomes and databases | **1 — rotational** |

Random 4 K read latency, measured with `O_DIRECT` (bypasses the page cache in the kernel, so
a cache hit cannot masquerade as a disk read):

| volume | median | p95 |
|---|---|---|
| **sdb (data)** | **32–34 ms** | 59–64 ms |
| sda (root) | **0.5 ms** | 0.8 ms |

**~60× apart on the same VM** — *as a device*. That is the honest per-read figure and it is
why the volume is worth moving, but **it does not translate into 60× on query time**: the
kernel reads ahead in 128 KB chunks, which amortises seeks that a 4 K random benchmark
deliberately defeats.

What it is worth in practice: the same 853 MB database copied to each volume, real search
terms, eviction verified at 0.0 % resident before every cold run. **I/O time is reported as
cold − warm**, so CPU is excluded, and bytes read come from `/proc/diskstats`:

| search term | MB read | flash I/O | spinning I/O | flash MB/s | spin MB/s | ratio |
|---|---|---|---|---|---|---|
| `kinase` | 59.8 | 696 ms | 710 ms | 86 | 84 | **1.0×** |
| `receptor` | 95.9 | 491 ms | 961 ms | 195 | 101 | 2.0× |
| `"zinc finger"` | 65.6 | 307 ms | 843 ms | 213 | 78 | **2.7×** |
| `transposases` | 2.1 | 105 ms | 123 ms | 20 | 17 | 1.2× |

> **Faster storage is worth ~2× on real queries**, not 17× and not 60×.

**Why the device gap does not carry through.** These queries read 2–96 MB in largely
*sequential* runs — SQLite plus kernel readahead see to that — and a spinning disk streams
at 78–101 MB/s. The 32 ms figure is random-access latency, which readahead mostly avoids
paying. `kinase` is the clearest case: both volumes deliver ~85 MB/s, so the disk is not the
constraint at all and flash buys literally nothing.

### ⚠️ A single-database test cannot make the RAM case — only the storage one

Myotis is 853 MB. It fits in 12 GB of cache with room to spare, so copying one database
between volumes measures **device speed** and nothing else. That is why every such test —
ours and IT's — lands on 2–3×.

The memory problem is **32.7 GB not fitting in 12 GB**, and it only appears across the
corpus: a cross-organism search, or a second visitor arriving after the first has already
evicted what they need. That is what the refault delta measures, and it cannot be reproduced
on a single copied file. **Do not let the storage test stand in for the memory case.**

**If you benchmark this yourself:** use `"zinc finger"` (66 MB read, 141 ms warm — I/O
dominates, clean signal). Avoid `kinase`, which is CPU-capped and cannot discriminate, and
avoid `transposases`/`maelstrom`, which read ~2 MB and show nothing. Always report
`cold − warm` and MB read: raw cold time hides whether you measured disk or CPU, and without
bytes a fast result may just mean the query touched almost nothing.

**Why the latency itself settles the question.** A solid-state device answers a random read
in roughly **0.1–1 ms** — nothing has to move. **20–35 ms is seek time plus rotational
latency**, which is what a spinning disk costs. (The one way flash reaches those numbers is a
badly contended or throttled datastore — which would need attention too, so either answer is
actionable.)

### You are already collecting the proof

`node_exporter` is running on this VM and Prometheus has been scraping it. Read latency per
device, computed from your own counters as
`node_disk_read_time_seconds_total / node_disk_reads_completed_total`, accumulated over
**50 days of production**:

| device | volume | reads | avg latency |
|---|---|---|---|
| **dm-2** | **`datavg-datalv` → `/var/www/html` (the data)** | 11,206,436 | **20.77 ms** |
| dm-0 | `rootvg-rootlv` → `/` | 534,687 | 1.55 ms |
| dm-7 | `rootvg-varlv` → `/var` | 112,128 | 0.98 ms |
| dm-1 | `rootvg-swap` | 247,800 | 0.61 ms |
| dm-3 | `rootvg-tmplv` → `/tmp` | 38,557 | **0.43 ms** |

**The data volume is 13–48× slower than every other volume on the same VM**, over eleven
million real reads. This is not a synthetic benchmark and needs no new instrumentation —
it is already in Prometheus. (The average is 20.77 ms against my 32 ms median because it
includes sequential and cache-friendly reads; the direction and the magnitude agree.)

### Ask 2 — memory

The databases are read through the **filesystem cache**, so the requirement is cache, not
process memory. Measured with `mincore(2)` across all 85 files:

> **32.7 GB of databases, of which 3.33 GB is resident — 10.2 %.**
> Nine reads in ten go to the 32 ms disk.

The user-visible effect, on a real search (`transposases`, one organism), eviction verified
at 0.0 % resident before the cold run:

| | time |
|---|---|
| **Cold** (not in cache) | **8,390 ms** |
| **Warm** (in cache) | **4.6 ms** |
| ratio | **~1,800×** |

The query itself is unchanged; the only variable is whether the bytes are in memory. The
first person to touch an organism waits eight seconds, the next waits milliseconds. With 85
organisms and 12 GB of cache, most visits are the first kind.

**Why this matters now:** the site is about to open to external users, and search is the
primary entry point — so a first-time visitor is exactly the user who hits cold data.

### ⚠️ Why the usual memory test will not show this

**A "memory used %" or "swap %" threshold cannot fire for this workload.** Page cache is
*reclaimable*: when it is too small the kernel silently evicts and re-reads rather than
swapping. Live, right now, while the above is true:

```
              total   used   free   buff/cache   available
Mem:            15 G    2 G    0 G       12 G        12 G
Swap:            4 G  339 M    4 G
```

Any dashboard reads that as a healthy, under-utilised VM.

The counters that **do** show it, from `/proc/vmstat` over **50 days** of uptime:

| counter | value | meaning |
|---|---|---|
| `workingset_refault_file` | **59,069,789** | file pages evicted, then needed again — **~1.2 M/day** |
| `workingset_restore_file` | 4,714,548 | of those, still on the active list — genuine thrash |
| `pgmajfault` | 445,854 | faults that had to hit disk |
| `pgscan_kswapd` / `pgsteal_kswapd` | 239.7 M / 231.9 M | **97 % steal ratio** — kswapd reclaims essentially everything it scans |

> **What to graph instead of swap/memory ceilings — most of it is already in Prometheus:**
>
> | metric | status |
> |---|---|
> | `node_vmstat_pgmajfault` | **already exported** (446,162 and climbing) |
> | `node_disk_read_time_seconds_total{device="dm-2"}` | **already exported** — see the storage table |
> | `node_memory_Cached_bytes` | already exported — expect it pinned near full, which is normal and is *not* the signal |
> | `workingset_refault_file` | **not exported by default.** node_exporter's vmstat collector ships a field allowlist (`^(oom_kill\|pgpg\|pswp\|pg.*fault).*`); adding it needs `--collector.vmstat.fields` widened. One flag. |
>
> `/proc/pressure/` (PSI) is **not available on this kernel** — it needs `psi=1` as a boot
> parameter, which we are happy to take if you would rather have a pressure metric.

### How much memory, and when we will need more again

**92 gene sets in 32.7 GB → 0.36 GB per gene set.** That gives a planning rule:

> **RAM required ≈ 0.36 GB × (number of gene sets)**

| | cache available | corpus | verdict |
|---|---|---|---|
| Today, 16 GB | ~12 GB | 32.7 GB | **37 % at best; 10 % in practice** |
| At 32 GB | ~28 GB | 32.7 GB | still short — no headroom, but would demonstrate the effect |
| At 64 GB | ~60 GB | 32.7 GB | fits, plus room for **~70 more gene sets** |

At our rate of adding organisms, 64 GB is comfortably several years, and the rule above lets
us predict the next conversation rather than discover it as a slowdown.

### What we did before asking

We reduced our own footprint first. The corpus was **67 GB** and is now **32.7 GB** — a
51 % reduction, which beat our own 37 GB estimate:

- **Removed duplicated full-text search storage** — the search index held a second full copy
  of text already in the database (38–49 % of each file).
- **Removed a duplicate database index** maintained on every write for no benefit.
- **Re-ranked searches inside the index** rather than after the join, cutting the working set
  of a cross-organism search from 13.4 GB to 3.6 GB.
- Reviewed the schema for further redundancy; bulk regenerable data already lives in flat
  files outside the databases by design.

Neither change is sufficient alone: 67 GB never fit in 64 GB of RAM, and 32.7 GB does not fit
in 16 GB.

### Reference

```
VM memory:      16 GB total, ~12 GB filesystem cache, ~2 GB in use
Swap:           4 GB, 339 MB used — correctly idle; swap does not help a page-cache shortage
Data volume:    /dev/mapper/datavg-datalv (XFS) on sdb, 1 TB, ROTA=1, 251 GB used of 900 GB
Root volume:    rootvg on sda, ROTA=0
Dataset:        32.7 GB across 85 SQLite databases, 92 gene sets, in a 233 GB data tree
Access pattern: read-only, random reads across many files
OS:             RHEL 9 (Linux 5.14)
```

---

## PART 2 — Answers to IT's clarifying questions (2026-07-30)

**"Source?"** — autocorrect. The ask is **resources**.

**"I believe your VMs are already flash."** Half right, and the half that matters is wrong:
`sda` is flash, `sdb` — which holds every byte of data — is not. See Ask 1. This is why "my
home dir is fast and my data drive is slow" was an accurate observation, not a misreading.

**"I'd need to see the server consistently hitting upper bounds for both swap and memory."**
It never will, and that is the finding rather than a weak case — see *Why the usual memory
test will not show this*. Agreeing to that test is agreeing to lose.

**"Just run a lot of queries to show we use the RAM."** (Our own idea — wrong scoreboard.)
"buff/cache is full" is true on every healthy Linux box and will rightly be dismissed. The
demonstration that lands is the **refault delta**: snapshot `workingset_refault_file`, run a
realistic multi-organism load, snapshot again. Pages re-read are pages the cache could not
hold. Pair it with cold/warm latency.

⚠️ **Schedule that off-hours.** `crossorg.py` took page cache 12.2 GB → 0.3 GB, and 2.5 hours
later it was only back to 6.6 GB. The site is slow for hours afterwards.

---

## PART 3 — Measurement traps (why two earlier drafts were wrong)

Both errors pointed the same way — *making the problem look smaller than it is* — so they are
recorded rather than quietly fixed.

1. **"sdb is already flash, 0.276 ms."** Came from `notes/bench/disk_latency.py`, which calls
   `evict()` and never verifies it. `POSIX_FADV_DONTNEED` is **advisory**: the run read from
   page cache and reported a cache hit as disk latency — **wrong by ~60×**. It also compared
   a 327 MB database on sdb against a 3 KB `.bashrc` on sda, so every "random" offset in the
   small file resolved to block 0. Use **`disk_random_read.py`** (O_DIRECT, similar file
   sizes). Eric doubted flash would help and this bad number appeared to confirm him — worth
   saying plainly when re-opening.
2. **"6.4 GB resident."** Misread from `notes/bench/residency.log`, whose per-organism columns
   are **percentages, not GB**. True figure is 3.33 GB (10.2 %), via `cache.resident()`, which
   calls `mincore`.
3. **Stale corpus size.** Drafts written before the reload quoted 67 GB and a 7,051 ms cold
   `COUNT(*)`. Both are superseded: 32.7 GB, and 8,390 ms on a real search.
4. **"Flash fixes cold queries, 17×" — a device number sold as a query number.** The 60×
   O_DIRECT gap is a correct measurement of the *device* under 4 K random reads. It does not
   survive contact with real queries, which read sequentially enough that readahead hides
   most of the seek cost: measured, flash is worth **~2×**, and on `kinase` **nothing at
   all**. This inverted the recommendation — an earlier version of this file told the reader
   to take storage over RAM if only one ask could land, which was backwards.
   **The lesson: a microbenchmark characterises hardware, not a workload.** Before quoting a
   ratio, check it against the query users actually run, and isolate I/O with `cold − warm`
   so CPU cannot masquerade as disk.

**The standing rule: never report a cold measurement without verifying eviction in the same
run.** `coldwarm.py` prints residency before and after and refuses to time anything if
eviction did not take.

---

## PART 4 — Internal notes, not for sending

- Search specifically is much better than when this was first drafted (`e1dc9c5` put the
  cross-organism working set inside the cache), so **do not claim search is broken**. The
  cold penalty is now sharpest on first touch of an organism, gene pages and bulk export.
  The 8,390 ms figure above is honest and current, but it is a *cold* first touch.
- **If only one ask can land, take RAM** — it is worth ~6× what flash is (12.8× vs 2.0×
  median on real searches), because it removes the read rather than speeding it up. Storage
  is still worth asking for in the same breath: it is a placement change, their own
  monitoring already evidences it, and the two compound.
- **`kinase` was 1.0× — flash bought nothing.** Its warm time is 288 ms, so that query is
  CPU-bound, not I/O-bound: neither ask fixes it. Common high-frequency terms across an
  85-organism fan-out are a *query cost* problem and belong with the search work, not this
  request.

### 🔍 Something evicts the database cache overnight — unidentified

`notes/bench/residency.log`, 07-29 13:57 → 07-30 09:42. Between **04:57 and 05:12** the
tracked organisms' residency collapsed while **`sdb_MB_read_since_last` was 0**:

| organism | 04:57 | 05:12 |
|---|---|---|
| Nematostella_vectensis | 28.2 % | **6.4 %** |
| Myotis_myotis | 36.1 % | **16.5 %** |
| Antrozous_pallidus | 20.6 % | **6.2 %** |
| Bipalium_kewense | 9.1 % | **0.0 %** |

Total cache simultaneously grew 10.4 → 12.2 GB. So ~1.8 GB was read from **somewhere other
than the data volume** and evicted database pages in the process.

**Why it matters:** the cache is cold every morning, so the first users of the day pay the
8.4 s penalty even for organisms that were warm the previous evening. That is a launch-day
experience problem independent of the RAM ask, and possibly cheaper to fix.

**Not yet identified.** `/etc/cron.daily` is empty; `mlocate-updatedb` and `logrotate` run at
00:00, not 05:00; root's crontab is unreadable to us. Candidates, all present and running:
**SentinelOne**, **Rapid7 Insight Agent**, and **`aide`** (file-integrity checking reads and
checksums every file — textbook cache destroyer). Ask IT what these are scheduled to do at
05:00, and whether a scan can use `posix_fadvise(DONTNEED)` or be excluded from
`/var/www/html`.

⚠️ The watcher is **not currently running** (it stopped at 09:42 on 07-30). Restart it before
drawing conclusions, and let it cover several nights — one night is one data point.
- Full method, the four measurement traps, and the RAM before/after test design:
  **`notes/STORAGE_AND_RAM_TESTING.md`**.
