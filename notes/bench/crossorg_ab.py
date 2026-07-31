#!/usr/bin/env python3
"""Cross-organism search, cold AND warm, over a NAMED set of organisms.

For A/B-ing the FTS rebuild: measure a set of organisms, rebuild them, measure again.
Named organisms rather than a group because a rebuild lands a few at a time, and
comparing a rebuilt subset against a whole group would compare two different questions.

Goes through the real HTTP endpoint at the real fan-out concurrency, so what is measured
is what a user gets -- not a library call. Bytes come from /proc/diskstats for sdb, which
is global, so nothing else should be hitting the disk while this runs.

Cold is the number that matters; warm is reported alongside because a large cold/warm gap
is what says the cost is I/O and not CPU.

usage:
  crossorg_ab.py --term helicase --orgs A,B,C
  crossorg_ab.py --term helicase --orgs-file /tmp/orgs.txt --label after
"""
import argparse, json, os, subprocess, sys, time
from concurrent.futures import ThreadPoolExecutor

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from quota import hard_evict

BASE = "http://172.16.2.52/moop/tools/annotation_search_ajax.php"
ORGDIR = "/var/www/html/moop/organisms"


def sdb_mb():
    for line in open("/proc/diskstats"):
        f = line.split()
        if len(f) > 5 and f[2] == "sdb":
            return int(f[5]) * 512 / 1024**2
    return 0.0


def one(org, term):
    out = subprocess.run(
        ["curl", "-s", "--max-time", "900", f"{BASE}?search_keywords={term}&organism={org}"],
        capture_output=True, text=True)
    try:
        return len(json.loads(out.stdout).get("results", []))
    except Exception:
        return -1


def sweep(orgs, term, conc):
    t0, d0 = time.perf_counter(), sdb_mb()
    with ThreadPoolExecutor(max_workers=conc) as ex:
        counts = list(ex.map(lambda o: one(o, term), orgs))
    return time.perf_counter() - t0, sdb_mb() - d0, counts


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--term", default="helicase")
    ap.add_argument("--orgs")
    ap.add_argument("--orgs-file")
    ap.add_argument("--conc", type=int, default=5)
    ap.add_argument("--label", default="")
    # Measure the machine AS IT STANDS, without evicting. Answers "what does a user get
    # right now", which is a different question from "what does a first visitor get" --
    # and it is the only way to see whether the working set is actually staying resident.
    ap.add_argument("--no-evict", action="store_true")
    args = ap.parse_args()

    if args.orgs_file:
        orgs = [l.strip() for l in open(args.orgs_file) if l.strip()]
    else:
        orgs = [o.strip() for o in (args.orgs or "").split(",") if o.strip()]
    if not orgs:
        sys.exit("no organisms given")

    # Evict every database in the set and VERIFY. An unverified evict reports a cache hit
    # as a cold read, which is how a benchmark in this very directory once measured page
    # cache and called it disk latency.
    still = []
    for o in orgs:
        db = os.path.join(ORGDIR, o, "organism.sqlite")
        if not os.path.exists(db):
            sys.exit(f"missing {db}")
        if args.no_evict:
            continue
        r, _ = hard_evict(db)
        if r:
            still.append(f"{o}:{r}")
    if still:
        sys.exit("eviction failed for " + ", ".join(still))

    cold_s, cold_mb, counts = sweep(orgs, args.term, args.conc)
    warm_s, warm_mb, _ = sweep(orgs, args.term, args.conc)

    tag = f" [{args.label}]" if args.label else ""
    print(f"\n  {len(orgs)} organisms, term {args.term!r}, concurrency {args.conc}{tag}")
    print(f"    COLD  {cold_s:7.1f} s   {cold_mb:8.1f} MB")
    print(f"    WARM  {warm_s:7.1f} s   {warm_mb:8.1f} MB")
    if warm_s > 0:
        print(f"    ratio {cold_s / warm_s:7.1f}x")
    bad = [o for o, c in zip(orgs, counts) if c < 0]
    print(f"    rows returned: {sum(c for c in counts if c > 0):,}"
          + (f"   ⚠ {len(bad)} organism(s) returned no parseable JSON: {bad[:3]}" if bad else ""))


if __name__ == "__main__":
    main()
