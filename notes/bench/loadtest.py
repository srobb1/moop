#!/usr/bin/env python3
"""Realistic search load, with the counters IT's monitoring can actually see.

WHY THIS EXISTS. The obvious demonstration -- "look, we use all our RAM" -- proves
nothing: buff/cache is full on every healthy Linux box, because the kernel always
fills it. And a memory-used or swap threshold can never fire here, since page cache
is reclaimable and gets evicted silently instead of swapping.

The signal that IS diagnostic is the REFAULT: a file page that was evicted and then
had to be read back. Refaults mean the cache could not hold the working set. This
script snapshots the relevant counters, drives a realistic multi-organism search
load through the live site, and reports the deltas -- plus the exact wall-clock
window, so someone can line it up against a Prometheus graph.

  workingset_refault_file  pages evicted then needed again   <- the one that matters
  workingset_restore_file  ...that were still on the active list -- genuine thrash
  pgmajfault               faults that reached disk (already exported by node_exporter)
  pgscan/pgsteal_kswapd    how hard reclaim is working

usage:
  loadtest.py [--group Bats] [--passes 3] [--conc 5] [--term transposases]
  loadtest.py --all --passes 6          # every organism, several times

⚠️  THIS EVICTS THE PAGE CACHE AND THE SITE IS SLOW FOR HOURS AFTERWARDS.
    A previous cross-organism run took cache 12.2 GB -> 0.3 GB, and 2.5 hours later
    it had only recovered to 6.6 GB. Run it out of hours, and tell IT the window.
"""
import argparse, json, subprocess, sys, time
from concurrent.futures import ThreadPoolExecutor

BASE = "http://172.16.2.52/moop/tools/annotation_search_ajax.php"
GROUPS = "/var/www/html/moop/metadata/organism_assembly_groups.json"
COUNTERS = ("workingset_refault_file", "workingset_restore_file", "pgmajfault",
            "pgscan_kswapd", "pgsteal_kswapd", "pswpout")


def vmstat():
    out = {}
    with open("/proc/vmstat") as fh:
        for line in fh:
            k, _, v = line.partition(" ")
            if k in COUNTERS:
                out[k] = int(v)
    return out


def meminfo_gb(key):
    with open("/proc/meminfo") as fh:
        for line in fh:
            if line.startswith(key):
                return int(line.split()[1]) / 1024**2
    return 0.0


def sdb_mb():
    with open("/proc/diskstats") as fh:
        for line in fh:
            f = line.split()
            if len(f) > 5 and f[2] == "sdb":
                return int(f[5]) * 512 / 1024**2
    return 0.0


def organisms(group=None):
    entries = json.load(open(GROUPS))
    if group:
        return sorted({e["organism"] for e in entries if group in (e.get("groups") or [])})
    return sorted({e["organism"] for e in entries})


def one(org, term):
    t = time.perf_counter()
    subprocess.run(["curl", "-s", "-o", "/dev/null", "--max-time", "600",
                    f"{BASE}?search_keywords={term}&organism={org}"], check=False)
    return time.perf_counter() - t


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--group"); ap.add_argument("--all", action="store_true")
    ap.add_argument("--passes", type=int, default=3)
    ap.add_argument("--conc", type=int, default=5)
    ap.add_argument("--term", default="transposases")
    ap.add_argument("--force", action="store_true",
                    help="run even if the cache is not at steady state (timings only)")
    a = ap.parse_args()

    orgs = organisms(None if a.all else (a.group or "Bats"))

    # PRECONDITION -- refaults only happen when the cache is FULL and pages compete
    # for it. On a cache that was recently dropped (or a freshly booted host) the
    # reads land in free memory, nothing is evicted, and the counter reads ZERO --
    # which looks like evidence AGAINST needing more memory. It is not: it means the
    # test was run before the cache refilled. Measured 2026-07-30: a 578 MB load an
    # hour after `drop_caches` produced 0 refaults with 11 GB free.
    cached, avail = meminfo_gb("Cached:"), meminfo_gb("MemAvailable:")
    if cached < 0.75 * avail:
        print("=" * 74)
        print("REFUSING TO REPORT REFAULTS: the page cache is not at steady state.")
        print("  Cached %.1f GB against %.1f GB available -- there is free memory, so"
              % (cached, avail))
        print("  nothing needs evicting and the refault delta will read ~0 whatever")
        print("  load you apply. That reads as 'no memory pressure' and would argue")
        print("  AGAINST the case.")
        print()
        print("  Wait for the cache to refill under normal use (hours), or warm it")
        print("  first, then re-run. Override with --force if you want the timings")
        print("  anyway and will ignore the counters.")
        print("=" * 74)
        if not a.force:
            sys.exit(2)
        print("(--force: timings only, counters are NOT evidence)\n")

    print(f"{len(orgs)} organisms x {a.passes} passes, concurrency {a.conc}, term '{a.term}'")
    print(f"cache at start: {cached:.1f} GB of {avail:.1f} GB available")
    print(f"START  {time.strftime('%Y-%m-%d %H:%M:%S %Z')}\n")

    v0, c0, d0, t0 = vmstat(), meminfo_gb("Cached:"), sdb_mb(), time.time()
    times = []
    for p in range(a.passes):
        with ThreadPoolExecutor(max_workers=a.conc) as ex:
            got = list(ex.map(lambda o: one(o, a.term), orgs))
        times += got
        got.sort()
        print("  pass %d/%d: wall %.1fs  median %.2fs  slowest %.2fs"
              % (p + 1, a.passes, sum(got) / a.conc, got[len(got) // 2], got[-1]))
    v1, c1, d1, t1 = vmstat(), meminfo_gb("Cached:"), sdb_mb(), time.time()

    print(f"\nEND    {time.strftime('%Y-%m-%d %H:%M:%S %Z')}   ({t1 - t0:.0f}s elapsed)")
    print("\n--- give IT this window and these deltas ---")
    print("  %-26s %14s" % ("counter", "delta"))
    for k in COUNTERS:
        print("  %-26s %14s" % (k, f"{v1.get(k,0) - v0.get(k,0):,}"))
    print("  %-26s %14s" % ("MB read from sdb", f"{d1 - d0:,.0f}"))
    print("  %-26s %14s" % ("page cache GB", f"{c0:.1f} -> {c1:.1f}"))

    refault = v1.get("workingset_refault_file", 0) - v0.get("workingset_refault_file", 0)
    print("\n  %.2f GB of file pages were evicted and read back DURING this run."
          % (refault * 4096 / 1024**3))
    print("  With cache large enough to hold the working set, that number is ~0.")


if __name__ == "__main__":
    main()
