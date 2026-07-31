#!/usr/bin/env python3
"""Does the search working set SURVIVE in page cache, or is it evicted between uses?

This is the evidence for the RAM ask, and it is a different question from the one
loadtest.py answers. loadtest.py evicts the cache to prove refaults happen. This
script must NEVER evict anything -- the entire measurement is whether the kernel
kept our pages on its own, under normal site use. Evicting would answer a question
nobody asked, and would also make the site slow for hours (see loadtest.py's warning).

THE METHOD. Run the SAME query, unchanged, at intervals over a day:

    run 1   right after a cold run   -> the warm floor. "this is the speed when cached"
    run 2   an hour later
    run 3   several hours later, after normal use by other people

If run 3 has crept back toward the cold time, the working set did not survive, and
more RAM is the fix. If it stays near the warm floor, it did survive, and RAM is NOT
the bottleneck -- that outcome is a real possibility and the point of measuring.

RULES, learned the hard way:
  - SAME TERM EVERY TIME. A different term touches different pages, so a slow result
    proves only that those pages were cold, which we already knew. Changing the term
    silently converts a survival test into a first-touch test.
  - Do not evict. Do not run loadtest.py in between; it destroys the thing being measured.
  - Record the counters, not just the stopwatch. Wall time alone cannot separate
    "read from disk" from "the query is just CPU-heavy". `kinase` is CPU-bound at
    ~288 ms warm and cannot discriminate at all.
  - Bytes read comes from /proc/diskstats for sdb (the data volume). A run that is
    slow with ~0 MB read was slow for some other reason -- say so rather than
    filing it as evidence for RAM.
  - MEASURE THE GAP, NOT JUST ITS LENGTH. "Survived 14 hours" means nothing if the
    machine was idle for all 14 -- nothing competed, so nothing had to be evicted.
    Survival is only evidence when something was actually pushing for that memory.
    Each run therefore records the ABSOLUTE counters as well as its own deltas, so
    the next run can subtract and report how much I/O happened in between.

usage:
  persistence.py --term piwi --group Bats        # run and append to persistence.log
  persistence.py --show                          # print every run so far, side by side
"""
import argparse, json, os, subprocess, time
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor

BASE = "http://172.16.2.52/moop/tools/annotation_search_ajax.php"
GROUPS = "/var/www/html/moop/metadata/organism_assembly_groups.json"
LOG = os.path.join(os.path.dirname(os.path.abspath(__file__)), "persistence.log")
COUNTERS = ("workingset_refault_file", "workingset_restore_file", "pgmajfault")


def vmstat():
    out = {}
    with open("/proc/vmstat") as fh:
        for line in fh:
            k, _, v = line.partition(" ")
            if k in COUNTERS:
                out[k] = int(v)
    return out


def sdb_mb():
    with open("/proc/diskstats") as fh:
        for line in fh:
            f = line.split()
            if len(f) > 5 and f[2] == "sdb":
                return int(f[5]) * 512 / 1024**2
    return 0.0


def meminfo_gb(key):
    with open("/proc/meminfo") as fh:
        for line in fh:
            if line.startswith(key + ":"):
                return int(line.split()[1]) / 1024**2
    return 0.0


def cache_gb():
    return meminfo_gb("Cached")


# Below this much free memory the kernel is actually having to choose what to keep,
# which is the only condition under which "did it survive?" is a real question.
NORMAL_FREE_GB = 4.0


def organisms(group):
    entries = json.load(open(GROUPS))
    if not group:
        return sorted({e["organism"] for e in entries})
    return sorted({e["organism"] for e in entries if group in (e.get("groups") or [])})


def one(org, term):
    subprocess.run(["curl", "-s", "-o", "/dev/null", "--max-time", "900",
                    f"{BASE}?search_keywords={term}&organism={org}"], check=False)


def gap_io(prev, cur):
    """MB read from sdb, and refaults, BETWEEN the end of `prev` and the start of `cur`.

    Returns (mb, refaults) or None when either run predates the absolute counters.
    Old log lines only ever recorded per-run deltas, which cannot answer this.
    """
    if "sdb_after_mb" not in prev or "sdb_before_mb" not in cur:
        return None
    return (cur["sdb_before_mb"] - prev["sdb_after_mb"],
            cur["refault_before"] - prev["refault_after"])


def show():
    if not os.path.exists(LOG):
        print("no runs recorded yet")
        return
    rows = [json.loads(l) for l in open(LOG) if l.strip()]
    print(f"{'when':20} {'term':12} {'group':8} {'orgs':>5} {'seconds':>9} "
          f"{'MB read':>9} {'refaults':>10} {'cache GB':>9} {'free GB':>8}")
    for r in rows:
        print(f"{r['when']:20} {r['term']:12} {str(r['group']):8} {r['orgs']:5d} "
              f"{r['seconds']:9.1f} {r['mb_read']:9.1f} {r['refaults']:10d} "
              f"{r['cache_gb']:9.1f} {r.get('free_gb', float('nan')):8.1f}")
    if len(rows) < 2:
        return

    # Compare the LAST TWO runs, not first-vs-last, and weigh the GAP between them.
    #
    # Survival is a question about elapsed time. A fast run ten minutes after a slow one
    # only shows that the slow one warmed the cache -- which is guaranteed, not evidence.
    # An earlier version compared rows[0] to rows[-1] and announced "the working set
    # SURVIVED" for exactly that case. A benchmark that draws a flattering conclusion from
    # a meaningless pair is worse than no benchmark.
    # The log holds one series PER TERM -- a second term interleaved with the first is
    # normal and useful. So compare the latest run against the previous run with the SAME
    # settings, not against whatever row happens to sit above it.
    b = rows[-1]
    a = next((r for r in reversed(rows[:-1])
              if r["term"] == b["term"] and r["group"] == b["group"]), None)
    if a is None:
        print(f"\n  only one run so far for term {b['term']!r} — re-run it later to test survival.")
        return

    fmt = "%Y-%m-%d %H:%M:%S"
    gap_h = (datetime.strptime(b["when"], fmt) - datetime.strptime(a["when"], fmt)).total_seconds() / 3600.0
    same = [r for r in rows if r["term"] == b["term"] and r["group"] == b["group"]]
    warm = min((r["seconds"] for r in same if r["mb_read"] < 20), default=None)

    print(f"\n  latest run: {b['seconds']:.1f}s, {b['mb_read']:.0f} MB read, "
          f"{b['refaults']:,} refaults — {gap_h:.1f} h after the previous {b['term']!r} run")
    if warm is not None:
        print(f"  warm floor for {b['term']!r}: {warm:.1f}s")

    # How hard was the cache pushed while we were not looking? Without this, an idle gap
    # and a punishing one are indistinguishable, and the idle one reads as good news.
    gap = gap_io(a, b)
    if gap is None:
        gap_gb = None
        print("  (gap I/O unknown: one of these runs predates the absolute counters)")
    else:
        gap_gb, gap_refaults = gap[0] / 1024.0, gap[1]
        print(f"  during the gap the machine read {gap_gb:.1f} GB from sdb "
              f"and took {gap_refaults:,} refaults")

    if b["mb_read"] < 20:
        # To evict our pages the kernel must have needed the space, which takes roughly a
        # cache-sized volume of other reads. Less than that and survival proves nothing.
        turnover = gap_gb is not None and gap_gb >= b["cache_gb"]
        if gap_h < 1.0:
            print(f"  INCONCLUSIVE: this is a warm re-run only {gap_h*60:.0f} minutes after the")
            print( "  previous one, which had already loaded these pages. It says nothing about")
            print( "  survival. Leave hours of NORMAL USE between runs, then repeat.")
        elif turnover:
            print(f"  SURVIVED {gap_h:.1f} h AND {gap_gb:.1f} GB of competing reads — more than the")
            print(f"  {b['cache_gb']:.1f} GB cache holds, so these pages were kept in preference to")
            print( "  others. That is real evidence that RAM is not the bottleneck.")
        else:
            print(f"  SURVIVED {gap_h:.1f} h, but the gap was QUIET", end="")
            print(f" ({gap_gb:.1f} GB read)." if gap_gb is not None else ".")
            print( "  Nothing competed for the memory, so nothing had to be evicted — this is not")
            print( "  yet evidence that the working set holds up under load. Repeat across a gap")
            print( "  with real traffic, or with other organisms being searched.")
    else:
        refault_mb = b["refaults"] * 4096 / 1048576
        share = (refault_mb / b["mb_read"] * 100) if b["mb_read"] else 0
        print(f"  EVICTED within {gap_h:.1f} h: it went back to disk for {b['mb_read']:.0f} MB.")
        print(f"  {b['refaults']:,} refaults x 4 KB = {refault_mb:.0f} MB, i.e. {share:.0f}% of that I/O")
        print( "  was pages the machine had ALREADY held and discarded — the same data, twice.")
        if b.get("free_gb") is not None and b["free_gb"] > NORMAL_FREE_GB:
            print(f"  (but {b['free_gb']:.1f} GB was free, so treat this cautiously)")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--term", default="piwi")
    ap.add_argument("--group", default="Bats")
    ap.add_argument("--conc", type=int, default=5)
    ap.add_argument("--show", action="store_true")
    args = ap.parse_args()

    if args.show:
        return show()

    orgs = organisms(args.group)
    if not orgs:
        print(f"no organisms in group {args.group!r}")
        return

    v0, d0, t0 = vmstat(), sdb_mb(), time.perf_counter()
    with ThreadPoolExecutor(max_workers=args.conc) as ex:
        list(ex.map(lambda o: one(o, args.term), orgs))
    elapsed, v1, d1 = time.perf_counter() - t0, vmstat(), sdb_mb()
    mb = d1 - d0

    rec = {
        "when": time.strftime("%Y-%m-%d %H:%M:%S"),
        "term": args.term, "group": args.group, "orgs": len(orgs), "conc": args.conc,
        "seconds": round(elapsed, 2), "mb_read": round(mb, 1),
        "refaults": v1["workingset_refault_file"] - v0["workingset_refault_file"],
        "majfaults": v1["pgmajfault"] - v0["pgmajfault"],
        "cache_gb": round(cache_gb(), 2),
        "free_gb": round(meminfo_gb("MemFree"), 2),
        # Absolute, since boot. The deltas above describe THIS run; these let the next
        # run measure what happened in the gap between them. Both are needed.
        "sdb_before_mb": round(d0, 1), "sdb_after_mb": round(d1, 1),
        "refault_before": v0["workingset_refault_file"],
        "refault_after": v1["workingset_refault_file"],
    }
    with open(LOG, "a") as fh:
        fh.write(json.dumps(rec) + "\n")

    print(f"  {len(orgs)} organisms, term {args.term!r}, concurrency {args.conc}")
    print(f"  {elapsed:.1f} s   {mb:.1f} MB read from sdb   "
          f"{rec['refaults']} refaults   cache {rec['cache_gb']:.1f} GB")
    if mb < 20:
        print("  -> read essentially nothing: this run was served from RAM (warm).")
    else:
        print("  -> read from disk: these pages were not resident when the run started.")

    if rec["free_gb"] > NORMAL_FREE_GB:
        print(f"\n  !! {rec['free_gb']:.1f} GB of memory is still FREE, so the kernel has not had to")
        print( "     evict anything. A fast result here is NOT evidence that the working set")
        print( "     survives under real conditions -- it survived because nothing competed.")
        print( "     Wait until the page cache has refilled (free_gb below "
              f"{NORMAL_FREE_GB:.0f}) before treating a run as evidence.")

    print(f"\n  logged to {LOG}. Re-run LATER WITH THE SAME --term to test survival.")


if __name__ == "__main__":
    main()
