#!/usr/bin/env python3
"""Cross-organism annotation search: cold vs warm, over the RELOADED databases only.

This is the measurement the whole reload was for. Earlier runs mixed reloaded and
stale databases, so the numbers described a corpus that no longer exists -- the
stale ones are ~2x larger, still carry an FTS _content table, and are about to be
rebuilt anyway. Including them makes the working set look worse than what a user
will actually hit.

"Clean" is defined the same way as everywhere else today: both FTS tables present,
no _content shadow table, no string-'NULL' parent IDs. A database failing any of
those is excluded and named, not silently skipped.

Eviction is VERIFIED with mincore before every cold timing -- that is the rule the
previous round broke, which is why its cold numbers did not reproduce.

usage:  crossorg.py [term ...]
"""
import os, sqlite3, sys, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import evict, resident, pct

ORGANISMS = "/var/www/html/moop/organisms"
PAGE = os.sysconf("SC_PAGESIZE")
CAP = 2500              # moop_search_results_limit()
POOL = CAP * 2          # what lib/database_queries.php actually ships

# The deployed fast path: rank inside the FTS index, then join only the survivors.
SQL = f"""
WITH pool AS (
    SELECT rowid AS rid,
           bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0) AS rank
    FROM feature_annotation_search
    WHERE feature_annotation_search MATCH ?
    ORDER BY rank
    LIMIT {POOL}
)
SELECT f.feature_uniquename, f.feature_name, a.annotation_description,
       (f.feature_name LIKE ?) AS name_match
FROM pool
JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
JOIN feature            f  ON f.feature_id             = fa.feature_id
JOIN annotation         a  ON a.annotation_id          = fa.annotation_id
ORDER BY name_match DESC,
         (a.annotation_description LIKE ?) DESC,
         pool.rank,
         f.feature_uniquename
LIMIT {CAP}
"""


def is_clean(db):
    try:
        con = sqlite3.connect(f"file:{db}?mode=ro", uri=True)
    except sqlite3.Error:
        return False
    try:
        fts = con.execute(
            "SELECT count(*) FROM sqlite_master WHERE name IN "
            "('feature_search','feature_annotation_search')").fetchone()[0]
        content = con.execute(
            "SELECT count(*) FROM sqlite_master WHERE name LIKE '%_search_content'").fetchone()[0]
        bad = con.execute(
            "SELECT count(*) FROM feature INDEXED BY feature_parent_feature_id_idx "
            "WHERE parent_feature_id IN ('NULL','')").fetchone()[0]
        return fts == 2 and content == 0 and bad == 0
    except sqlite3.Error:
        return False
    finally:
        con.close()


def say(*a):
    """Unbuffered. Redirected stdout is block-buffered, so a long run with prints
    at the end looks identical to a hung process -- which is exactly how the first
    attempt at this read."""
    print(*a, flush=True)


def fan_out(dbs, term, label=""):
    """One search across every database, as the site does it. Returns (ms, rows)."""
    match, like = f'"{term}"*', f"%{term}%"
    t0 = time.perf_counter()
    rows = 0
    for i, db in enumerate(dbs, 1):
        con = sqlite3.connect(f"file:{db}?mode=ro", uri=True)
        try:
            rows += len(con.execute(SQL, (match, like, like)).fetchall())
        finally:
            con.close()
        if label and (i % 10 == 0 or i == len(dbs)):
            say(f"      {label} {i}/{len(dbs)} dbs  "
                f"{(time.perf_counter() - t0):.0f}s elapsed")
    return (time.perf_counter() - t0) * 1000.0, rows


def resident_total(dbs):
    pages = total = 0
    for db in dbs:
        r, t = resident(db)
        pages += r
        total += t
    return pages, total


def main():
    # ONE term by default. Each term costs a full eviction plus a cold pass over
    # every database, and cold is the whole point -- so this is minutes, not
    # seconds, and three terms is a ten-minute run.
    args = sys.argv[1:]
    # --limit N: measure N databases instead of all of them. A fully-evicted pass
    # over every database is disk-bound and takes minutes; a subset gives the same
    # per-database cost in a fraction of the time and scales honestly.
    limit = None
    if "--limit" in args:
        i = args.index("--limit")
        limit = int(args[i + 1])
        del args[i:i + 2]
    terms = args or ["kinase"]

    clean, dirty = [], []
    for name in sorted(os.listdir(ORGANISMS)):
        db = os.path.join(ORGANISMS, name, "organism.sqlite")
        if not os.path.isfile(db):
            continue
        (clean if is_clean(db) else dirty).append((name, db))

    dbs = [db for _, db in clean]
    if limit:
        dbs = dbs[:limit]
    size_gb = sum(os.path.getsize(db) for db in dbs) / 1024**3
    print(f"\nreloaded/clean : {len(clean)} databases, {size_gb:.1f} GB")
    print(f"excluded       : {len(dirty)}  ({', '.join(n for n, _ in dirty)})")

    with open("/proc/meminfo") as fh:
        cached = next(int(l.split()[1]) for l in fh if l.startswith("Cached:")) / 1024**2
    print(f"page cache now : {cached:.1f} GB\n")

    print(f"{'term':<14}{'COLD ms':>10}{'WARM ms':>10}{'speedup':>9}"
          f"{'read MB':>10}{'rows':>8}   evicted to")
    print("-" * 78)

    for term in terms:
        for db in dbs:
            evict(db)
        before, total_pages = resident_total(dbs)
        residency = pct(before, total_pages)

        cold_ms, rows = fan_out(dbs, term)
        after, _ = resident_total(dbs)
        read_mb = (after - before) * PAGE / 1024**2

        warm_ms, _ = fan_out(dbs, term)

        speed = cold_ms / warm_ms if warm_ms else 0
        flag = "  !! NOT COLD" if residency > 2.0 else ""
        print(f"{term:<14}{cold_ms:>10.0f}{warm_ms:>10.0f}{speed:>8.0f}x"
              f"{read_mb:>10.0f}{rows:>8}   {residency:.1f}%{flag}")

    print(f"\nread MB is the cross-organism working set: what one search faults in")
    print(f"across all {len(dbs)} databases. It fits in cache if it is below "
          f"{cached:.1f} GB.")


if __name__ == "__main__":
    main()
