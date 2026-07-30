# Storage and RAM: what was measured, and how to measure it again

Paused 2026-07-29, to be resumed. Everything here is measured on `simrbasenew`.

---

## 1. The headline correction

**A previous session recorded "sdb does 0.276 ms random reads, so the data volume is
already flash, the storage ask is dead." That was wrong by ~60×.**

Re-measured with `O_DIRECT` on settled files of comparable size:

| volume | mount | median | p95 |
|---|---|---|---|
| **sdb** (`datavg-datalv`) | `/var/www/html` | **32–34 ms** | 59–64 ms |
| sda (`rootvg-*`) | `/`, `/home`, `/tmp`, `/var` | **0.5 ms** | 0.8 ms |

Three independent methods agree on the direction: `O_DIRECT` 32–34 ms;
`/proc/diskstats` average since boot 26.3 ms over 11.5M reads; `fadvise`
with residency verified 13.5 ms. The spread is queue depth. Nothing puts sdb near flash.

It is VMware, so `ROTA` is the hypervisor's guess — but it guessed **right** for sdb
(`ROTA=1`) and the "virtualisation artifact" reasoning was backwards. Note `sda` reports
`ROTA=0` on the same host, which is the tell that these are two different datastores.

### Flash demonstrably fixes cold queries

Same database copied to sda, identical `COUNT(*) FROM annotation`, both verified 0.0%
resident:

```
sdb   248.3 ms cold    1.49 ms warm
sda    14.3 ms cold    1.40 ms warm      -> 17x on the cold path
```

Warm is identical, as it must be — once data is in RAM the disk is irrelevant. The whole
difference is the cold path, which is the problem we have.

---

## 2. THE THREE CACHE LAYERS — this is what makes measuring hard

Every wrong number in this file's history comes from not knowing which layer was being
measured.

| layer | cleared by | notes |
|---|---|---|
| SQLite page cache | new process | only 2 MB by default; rarely matters |
| **Linux page cache** | `sudo sh -c 'sync; echo 3 > /proc/sys/vm/drop_caches'` | reliable. `POSIX_FADV_DONTNEED` is **advisory** and often does nothing |
| **VMware host cache** | *nothing you can do from inside the guest* | **this is the floor.** Recently-read OR recently-written blocks stay fast |

The host layer is the one that ambushes you. It cannot be cleared, verified, or seen from
inside the VM. The only defence is to test data the host has not touched recently.

---

## 3. Four traps, all of which produced a wrong answer here

1. **Unverified eviction.** `disk_latency.py` called `cache.evict()` and never checked it
   — it imported `evict` but not `resident`. `bench.py` in the same directory hammers
   `base.sqlite`, so by the time it ran the file was resident, the evict did not take, and
   400 "disk reads" were cache hits. That is the origin of the bogus 0.276 ms. **Now
   guarded:** it verifies with mincore and refuses to print a number it cannot vouch for.

2. **A freshly copied file is NOT a cold file.** `cp` pushes blocks through the host's
   write path, so they sit in host cache. Measured directly:

   | file state | sdb median |
   |---|---|
   | just `cp`'d | **0.66 ms** |
   | read earlier the same day | 13.7 ms |
   | untouched for days | **33.9 ms** |

   A test that copies to *both* volumes flatters both sides and hides the effect entirely.
   For the sdb side, always query the **original in place**.

3. **Mismatched file sizes.** An early run compared a 327 MB database on sdb against a
   3 KB `.bashrc` on sda. A file smaller than one block makes every "random" offset resolve
   to block 0 — it re-read one cached block 60 times. `disk_random_read.py` now refuses
   files under 256 KB and reports why.

4. **A fixed random seed across runs.** `disk_latency.py` uses `random.Random(1234)`, so
   every run reads the *same* 400 offsets. Run it twice and the second run measures host
   cache. This is why it can report 13.5 ms then 0.3 ms back to back.

---

## 4. Tools

| script | use |
|---|---|
| **`disk_random_read.py`** | ⭐ **Use this.** `O_DIRECT` random reads — bypasses the guest page cache in the kernel, so there is nothing to verify and nothing to drift. Enforces comparable file sizes; prefers settled files over fresh ones. |
| `disk_latency.py` | Legacy, kept as a cautionary example. Now verifies residency and refuses untrustworthy numbers, but buffered reads still drift warm across samples. |
| `crossorg.py` | Cross-organism search, cold vs warm, over the *reloaded* databases only. Reports the working set via mincore delta. |
| `saturate.py` | Cumulative working set across N distinct terms — the cache-saturation curve. |
| `working_set.py`, `prerank.py`, `prerank_correct.py`, `bench.py` | Query-shape work; see `README.md`. |
| `cache.py` | `evict` / `resident` / `pct`. **Always use `resident` to verify `evict`.** |

### Doing it by hand

```sh
# Pick a database NOT queried in days. Query the ORIGINAL for the sdb side.
DB=/var/www/html/moop/organisms/Schizocardium_californicum/organism.sqlite
cp "$DB" ~/flash_test.sqlite

sudo sh -c 'sync; echo 3 > /proc/sys/vm/drop_caches'
time sqlite3 "$DB" "SELECT COUNT(*) FROM annotation"                 # sdb COLD
time sqlite3 "$DB" "SELECT COUNT(*) FROM annotation"                 # sdb warm

sudo sh -c 'sync; echo 3 > /proc/sys/vm/drop_caches'
time sqlite3 ~/flash_test.sqlite "SELECT COUNT(*) FROM annotation"   # sda COLD
time sqlite3 ~/flash_test.sqlite "SELECT COUNT(*) FROM annotation"   # sda warm
rm ~/flash_test.sqlite
```

If the sdb cold number comes back fast (< 50 ms), the file was already cached despite
`drop_caches` — that is host cache. Try a different organism rather than concluding the
disk is fine. Good candidates are the databases `crossorg.py` **excludes** (the 10 unclean
ones), because no sweep has full-scanned them.

---

## 5. Corpus and cache, as of 2026-07-29

```
databases        85 files, 32.7 GB   (was 66 GB before the reload)
RAM              15 GB total, ~12 GB usable as page cache
=> at most ~36% of the data can be resident at once
```

Working set for **one** cross-organism annotation search: **4.3 GB** (was 13.4 GB before
the index-first ranking fix). That now fits. But different terms touch different pages:

| distinct terms searched | cumulative resident |
|---|---|
| 1 (`kinase`) | 4.22 GB |
| 2 | 6.26 GB |
| 4 | 7.68 GB |
| **6** | **8.80 GB — 73% of a 12 GB cache** |

The curve decelerates (terms share pages) but never flattens. Six searches fill three
quarters of the cache; beyond that, users evict each other.

---

## 6. ⏭️ THE RAM BEFORE/AFTER TEST — design this carefully or it proves nothing

**If the test working set fits in current RAM, adding RAM will show no difference.**

| test working set | before (12 GB cache) | after (~28 GB cache) | visible difference |
|---|---|---|---|
| under 12 GB | fits → warm | fits → warm | **none** |
| **12–28 GB** | thrashes | fits → warm | **large — aim here** |
| over 28 GB | thrashes | still thrashes | little |

Six terms across 75 databases = 8.8 GB, which is *under* the line. Roughly 10–15 distinct
terms are needed to land in the useful band.

### The design that shows it

Do not time a single query. Measure **whether the second pass is warm**:

1. `drop_caches` once.
2. Loop all databases × N terms. Time it. (Cold; slow before *and* after.)
3. Immediately run the identical loop again. Time it.

**Pass 2 is the measurement.** The metric is `pass1 / pass2`:

- **Before RAM:** ~1.2× — the end of the loop evicted the start, proving the cache is too small.
- **After RAM:** closer to 10× — it fits.

Record `free -g` alongside each run. `buff/cache` pinned at its ceiling during pass 2 is
the thrashing visible directly, independent of any timing.

---

## 7. Where the ask stands

Both levers are real and they **compose** rather than compete:

- **RAM** reduces how *often* you read cold.
- **Flash** reduces what a cold read *costs*.

With 33 GB of data against 12 GB of cache, cold reads never reach zero — so cheap cold
reads have permanent value, while RAM only helps until the working set fits.

**Lead with storage.** It is the larger measured win (17× on a real cold query), it needs
no hardware purchase since `rootvg` already sits on 0.5 ms storage on this same host, and
it helps regardless of cache size. It is a **datastore placement** request.

⚠️ `sda` is only 40 GB provisioned, `/home` has ~4.7 GB free — so "move the databases to
/home" is not an option. The ask is a **new volume for `datavg` from the same datastore
class as `rootvg`**, ~50 GB for headroom.

⚠️ Not measured: whether that datastore has the capacity, whether it stays fast with
330 GB of hot data on it, and whether **writes** behave as well (the pipeline copies ~33 GB
in periodically — a different profile from random reads). Those are questions for whoever
runs the VMware cluster.

Eric doubted flash would help and the bad measurement appeared to confirm him. Worth
saying that plainly when re-opening it, with the `O_DIRECT` numbers and the reason the
first measurement lied.

---

## 8. Also running

`residency_watch.py` was left sampling page-cache residency every 15 minutes for 20 hours
into `residency.log`, to answer whether anything reclaims the cache between uses. Nothing
scheduled on this host reads enough to do so — `raid-check` is a no-op (no md arrays),
`logrotate` handles 33 MB, `updatedb` only stats. The one unresolved candidate is the
**Rapid7 Insight Agent**, whose `/proc/<pid>/io` is root-owned and unreadable from here.

If the cache holds steady overnight, a cache-prewarming housekeeping task is low value:
the first search warms it and it stays warm. If it collapses, prewarming is worth building
— but only above ~32 GB of RAM, because reading 33 GB into a 12 GB cache just evicts as it
goes and would be worse than doing nothing.
