# Capacity test — how many people can use MOOP at once? (2026-10-23)

Target: 5–10 people in bursts, usually 1–2. Server: 8 cores (Xeon Gold 6342), 15 GB RAM,
VMware disk (1.1 GB/s sequential, 0.55 ms random). Driver: `notes/bench/capacity.py`
(stdlib Python; replays the real form submissions; samples CPU / run queue / memory /
page-cache refaults / BLAST processes once a second when run on the server).

## The answer, short

**For 5–10 people, CPU is fine and the everyday tools are fine. What breaks first is memory,
and when it breaks it takes the whole site down.** Three fixes are needed before real users,
none of them hardware; a RAM upgrade is the one hardware change worth making.

| Rank | Finding | Fix | Cost |
|---|---|---|---|
| 1 🔴 | One BLAST running out of memory **stops all of php-fpm** (systemd `OOMPolicy=stop`, `Restart=no`) — site down until someone restarts it by hand. Happened during this test. | `sudo scripts/install_php_fpm_resilience.sh` (drop-in: `OOMPolicy=continue`, `Restart=always`) | 1 command |
| 2 🔴 | Nothing limits concurrent BLASTs. Genome tblastn needs 1.2 GB (bat) – 2.9 GB (wallaby); **~5 wallaby-class searches at once exhaust RAM.** | A small BLAST queue: at most N run, the rest wait their turn with a "you're 2nd in line" message | Code, ~a day |
| 3 🟠 | **Unticking "filter low complexity"** on a transcript vs a big genome → 187k alignments, 30 s of BLAST, then PHP reads 146–268 MB into a 128 MB limit → error page after ~1 min, for ONE user. | Cap alignments per subject (`-max_hsps`) and refuse oversize output with a clear message | Code, small |
| 4 🟡 | nginx waits 60 s for PHP. Bat tblastn at 10 at once = 36 s; with a queue (fix 2) waits add up. | Raise `fastcgi_read_timeout` for blast.php (300 s) now; asynchronous BLAST (submit → poll) later if ever needed | Config |
| 5 🟢 | RAM: 15 GB vs 33 GB SQLite + 32 GB genome BLAST DBs. Cold search costs 2× warm; big BLASTs evict search data. | **32 GB** (a VMware setting, not a purchase). Allows ~8 wallaby BLASTs in a queue slot budget and doubles the page cache. | Ask IT |
| — | More CPUs | **Not needed for 5–10 people.** See "When do we need more CPUs" | — |

## What each tool feels like (measured, through the real website)

Wait time for the slowest person when N press the button at the same moment.

| Tool | 1 person | 4 | 6 | 8 | 10 | Notes |
|---|---|---|---|---|---|---|
| Gene page | 0.05 s | 0.05 | 0.05 | 0.07 | 0.08 | |
| Primer Maker | 0.03 s | — | — | — | 0.12 (burst) | primer3 is trivial |
| Primer BLAST | 1.1 s | 1.1 | 1.1 | 1.3 | 1.6 | |
| Search, one organism | 0.2 s | 1.0 | 1.0 | 1.0 | 1.1 | |
| Search, all 49 bats (warm) | 7.3 s | 5.3 | 5.5 | 6.0 | 6.6 | each person = 5 parallel requests |
| Search, all 49 bats (**cold cache**) | 13 s | 7.2 (5 ppl) | | | 9.0 | people warm the cache for each other |
| blastp vs gene set | 1.1 s | 1.1 | 1.1 | 1.2 | 1.3 | |
| tblastn vs Nematostella genome | 2.8 s | 2.9 | 3.8 | 4.8 | 5.8 | |
| blastn vs bat genome (filter on) | 1.9 s | 1.9 | 2.5 | 2.8 | 3.4 | |
| **tblastn vs bat genome** | 15 s | 15 | 22 | 29 | **36** | CPU-bound past 4 |
| **tblastn vs wallaby genome** | 25 s | 25 | — | **💥 OOM** | | 2.9 GB each; site went down |

The shape for BLAST is textbook: **flat until 4 at once** (4 × 2 threads = 8 cores), then every
extra person adds ~3.5 s for everyone (bat tblastn). Nothing else on the site slows down while
BLASTs run — searches and gene pages were unaffected in every mixed test.

### Realistic bursts (people arrive, each does: search bats → gene page → bat tblastn → blastp → Primer Maker → Primer BLAST, with think time)

| Scenario | heavy BLAST median / slowest | everything else | server |
|---|---|---|---|
| 8 people over 60 s, 2 sessions each, think 5–20 s | 14.7 / 14.9 s (= alone) | unchanged | CPU peak 68%, mean 27% |
| 10 people over 10 s, think 2–6 s (harsh) | 28.8 / 30.8 s (2×) | unchanged | CPU peak 100%, 10 BLASTs, 6.9 GB RAM free at worst |

**Your expected load (5–10 people, bursty) is comfortably inside what this box does — as long as
nobody's BLAST can exhaust memory.**

## When do we need more CPUs?

CPU only matters for genome BLAST. The rule from the curve: **waits start growing once more than
4 genome BLASTs run at the same moment.** Not 4 users — 4 *simultaneous* genome tblastns, each
lasting 15–25 s. With 10 people clicking at random over a few minutes, you rarely get that.

Buy cores when, in normal use (not a test), you see genome BLAST waits regularly double — i.e.
the queue from fix 2 is regularly non-empty. 16 cores would move the knee from 4 to 8
simultaneous BLASTs. Until then cores buy nothing: searches, gene pages and primers used under
30% CPU even at 10 people.

## When do we need more RAM?

Now, modestly. Two separate reasons:
1. **BLAST working memory** — tblastn holds about 3.5× the genome DB's `.nsq` size.
   Of 73 genome DBs: **8 are wallaby-class** (≥600 MB nsq, ~2.9 GB per search),
   **47 bat-class** (400–600 MB, ~1.2 GB), 18 small. On 15 GB with ~3 GB for everything else,
   that is ~4 wallaby or ~9 bat searches at once before the OOM killer.
2. **Page cache** — 65 GB of databases (SQLite + BLAST) against ~9–12 GB of cache. Search is
   still fine cold (13 s worst, for a 49-organism search), because the disk is now fast. But
   the 10-person burst produced 18k refaults: big BLASTs push search data out of cache.

**32 GB** fixes (1) comfortably with a queue of 4–6 and roughly doubles the cache for (2). It is a
VM setting on VMware, so it is an IT request, not a purchase. 64 GB would hold all the SQLite.

## Order to do things

1. ✅ `sudo scripts/install_php_fpm_resilience.sh` — installed 2026-10-23.
2. ✅ BLAST queue (fix 2) — `lib/blast_queue.php`, 2026-10-23. Searches take memory "units"
   (256 MB each; budget 60% of RAM = 36 units here; wallaby tblastn = 11, bat ≈ 7–10, protein
   sets 1) and at most `cores/threads + 2` = 6 run at once. The rest wait in line, and the
   BLAST button shows their place ("Server busy: your search is 2nd in line"). Primer BLAST
   uses the same queue. **Cannot get stuck:** every piece of state is a `flock()` the kernel
   drops when the process dies; a waiter gives up after 120 s with "busy, try again"; a
   running BLAST is killed after 150 s (`timeout`); an unusable queue directory runs the
   search unqueued and logs it. Verified: `kill -9` of a running search and of the head of
   the line each freed the next search within 1 s; 17 smoke tests (red on the first draft,
   which really did get stuck when a slot file was not writable).
   Re-test: 6 wallaby tblastn at once → 3 ran, 3 waited, MemAvailable never below 6.3 GB,
   all 6 returned hits, slowest 51 s (this morning: OOM, site down).
3. ✅ Output-size guard (fix 3) — results over `blast_max_output_mb` (20 MB of XML) are refused
   with the reason and what to change, checked on the BLAST archive before formatting. The
   filter-off case now returns that message in 45 s instead of a crash page at 57 s; the
   remaining time is BLAST itself (344,582 alignments), which only the run-time cap bounds.
4. ⏳ `sudo scripts/install_nginx_timeouts.sh` (fix 4) — nginx `fastcgi_read_timeout` 300 s.
   **Needed now:** the queue can make a request wait up to 120 s + run 150 s; at nginx's
   default 60 s the last in a busy line gets a 504.
5. Ask IT for 32 GB RAM. (Queue budget follows RAM automatically.)
6. Re-run the ladders, including `tblastn_big_gen` up to 12 with `--allow-oom`, once fix 4 is in.

## Running it yourself (here or from cerebro)

Copy `notes/bench/capacity.py` and `notes/bench/capacity_queries.json` (same directory), then:

```bash
python3 capacity.py list
python3 capacity.py ladder search_bats --levels 1,2,4,6,8,10
python3 capacity.py ladder tblastn_bat_gen --levels 1,2,4          # guard: max 4 until fixes 1-2
python3 capacity.py burst --users 8 --ramp 60 --loops 2             # the realistic case
python3 capacity.py evict                                           # server only: cold cache
```

From cerebro, add nothing — `--base` defaults to `http://172.16.2.52/moop`. At start-up it
checks whether the site gives cerebro in-range access; if not, the bat/wallaby scenarios are
skipped and only public Nematostella data is tested. Off the server there is no CPU/memory
sampling — you get what a user feels, network included, which is the point of running there.
Results land in `./capacity_results/` (CSV per ladder, JSON per burst + server samples).

⚠️ The driver refuses heavy genome BLAST above 4 at once (and bursts above 8 people) unless
`--allow-oom` — because that is exactly what took the site down. Lift it only after fix 1.

## Method notes

- Requests were recorded from the real pages (headless browser) and replayed with the form's own
  defaults — an early replay without them is what surfaced fix 3 by accident.
- Each "N people" level starts all N on a barrier, so they truly press at the same instant —
  harsher than reality.
- Cold runs evicted all 3,237 database/index files with `fadvise(DONTNEED)` and verified 0 MB
  resident with `mincore` before each run.
- The driver runs on the server it measures; it mostly waits on sockets (<5% of one core).
