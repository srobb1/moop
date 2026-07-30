#!/usr/bin/env python3
"""Does pre-ranking change WHAT the user sees? Tested at the production cap (2500).

A faster query that returns different results is not an optimisation, it is a
regression. bm25 alone ranks literal matches poorly -- that is the whole reason
4c229f2 added the literal tier -- so a bm25-chosen pool may drop exactly the rows
that tier was meant to promote.
"""
import os, sqlite3, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import evict, resident

DB = "/var/www/html/moop/organisms/Nematostella_vectensis/organism.sqlite"
PAGE = os.sysconf("SC_PAGESIZE")
CAP = 2500

CURRENT = """
SELECT f.feature_uniquename, a.annotation_description
FROM feature_annotation_search fas
JOIN feature_annotation fa ON fa.feature_annotation_id = fas.rowid
JOIN feature f ON f.feature_id = fa.feature_id
JOIN annotation a ON a.annotation_id = fa.annotation_id
WHERE feature_annotation_search MATCH ?
ORDER BY (f.feature_name LIKE ?) DESC,
         (a.annotation_description LIKE ?) DESC,
         bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0),
         f.feature_uniquename
LIMIT {cap}
"""

PRERANK = """
WITH pool AS (
    SELECT rowid AS rid, bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0) AS rank
    FROM feature_annotation_search
    WHERE feature_annotation_search MATCH ?
    ORDER BY rank LIMIT ?
)
SELECT f.feature_uniquename, a.annotation_description
FROM pool
JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
JOIN feature f ON f.feature_id = fa.feature_id
JOIN annotation a ON a.annotation_id = fa.annotation_id
ORDER BY (f.feature_name LIKE ?) DESC,
         (a.annotation_description LIKE ?) DESC,
         pool.rank,
         f.feature_uniquename
LIMIT {cap}
"""


def run(sql, params):
    evict(DB)
    before, _ = resident(DB)
    t0 = time.perf_counter()
    con = sqlite3.connect(f"file:{DB}?mode=ro", uri=True)
    rows = con.execute(sql, params).fetchall()
    con.close()
    ms = (time.perf_counter() - t0) * 1000
    after, _ = resident(DB)
    return ms, (after - before) * PAGE / 1048576, rows


if __name__ == "__main__":
    for term in ("transpos", "binding", "kinase"):
        m, like = f'"{term}"*', f"%{term}%"
        print(f"\n=== {term} (cap {CAP}) ===")
        ms0, mb0, base = run(CURRENT.format(cap=CAP), (m, like, like))
        print(f"  current                    {ms0:8.1f} ms  {mb0:7.1f} MB  rows={len(base)}")
        base_top = [r[0] for r in base[:100]]
        base_set = set(map(tuple, base))

        for pool in (CAP, CAP * 2):
            ms1, mb1, got = run(PRERANK.format(cap=CAP), (m, pool, like, like))
            got_top = [r[0] for r in got[:100]]
            overlap = len(base_set & set(map(tuple, got))) / max(len(base_set), 1) * 100
            same_top = "IDENTICAL" if got_top == base_top else "DIFFERENT"
            print(f"  pre-rank pool={pool:<6}       {ms1:8.1f} ms  {mb1:7.1f} MB  rows={len(got)}"
                  f"   top100 {same_top}   overlap {overlap:5.1f}%")
