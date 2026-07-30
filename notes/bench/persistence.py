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

usage:
  persistence.py --term piwi --group Bats        # run and append to persistence.log
  persistence.py --show                          # print every run so far, side by side
"""
import argparse, json, os, subprocess, time
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
    if len(rows) > 1:
        a, b = rows[0], rows[-1]
        if a["term"] != b["term"] or a["group"] != b["group"]:
            print("\n  !! first and last runs used different query settings -- not comparable.")
            return
        print(f"\n  first run {a['seconds']:.1f}s ({a['mb_read']:.0f} MB read)"
              f"  ->  latest {b['seconds']:.1f}s ({b['mb_read']:.0f} MB read)")
        if b["mb_read"] < 20:
            print("  latest run read almost nothing from disk: the working set SURVIVED.")
        elif b["seconds"] > a["seconds"] * 2:
            print("  latest run is much slower AND read from disk: the working set was EVICTED.")


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
    elapsed, mb, v1 = time.perf_counter() - t0, sdb_mb() - d0, vmstat()

    rec = {
        "when": time.strftime("%Y-%m-%d %H:%M:%S"),
        "term": args.term, "group": args.group, "orgs": len(orgs), "conc": args.conc,
        "seconds": round(elapsed, 2), "mb_read": round(mb, 1),
        "refaults": v1["workingset_refault_file"] - v0["workingset_refault_file"],
        "majfaults": v1["pgmajfault"] - v0["pgmajfault"],
        "cache_gb": round(cache_gb(), 2),
        "free_gb": round(meminfo_gb("MemFree"), 2),
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
