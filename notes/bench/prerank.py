#!/usr/bin/env python3
"""Does ranking inside the FTS index first, then joining only the survivors,
collapse the working set?

Current shape joins every matched row and sorts the lot, so "binding" fetches
121,780 rows to display 100. bm25() needs only the index, so the top N can be
chosen BEFORE touching feature/annotation. If that holds, cold search gets fast
with no hardware.
"""
import os, sqlite3, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import evict, resident

DB = "/var/www/html/moop/organisms/Nematostella_vectensis/organism.sqlite"
PAGE = os.sysconf("SC_PAGESIZE")

CURRENT = """
SELECT f.feature_uniquename, f.feature_name, a.annotation_description,
       (f.feature_name LIKE ?) AS name_match
FROM feature_annotation_search fas
JOIN feature_annotation fa ON fa.feature_annotation_id = fas.rowid
JOIN feature f ON f.feature_id = fa.feature_id
JOIN annotation a ON a.annotation_id = fa.annotation_id
WHERE feature_annotation_search MATCH ?
ORDER BY name_match DESC,
         (a.annotation_description LIKE ?) DESC,
         bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0),
         f.feature_uniquename
LIMIT 100
"""

# Rank in the index, take a generous pool, THEN join and re-rank precisely.
PRERANK = """
WITH pool AS (
    SELECT rowid AS rid, bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0) AS rank
    FROM feature_annotation_search
    WHERE feature_annotation_search MATCH ?
    ORDER BY rank
    LIMIT ?
)
SELECT f.feature_uniquename, f.feature_name, a.annotation_description,
       (f.feature_name LIKE ?) AS name_match
FROM pool
JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
JOIN feature f ON f.feature_id = fa.feature_id
JOIN annotation a ON a.annotation_id = fa.annotation_id
ORDER BY name_match DESC,
         (a.annotation_description LIKE ?) DESC,
         pool.rank,
         f.feature_uniquename
LIMIT 100
"""


def run(label, sql, params):
    evict(DB)
    before, total = resident(DB)
    t0 = time.perf_counter()
    con = sqlite3.connect(f"file:{DB}?mode=ro", uri=True)
    rows = con.execute(sql, params).fetchall()
    con.close()
    ms = (time.perf_counter() - t0) * 1000
    after, _ = resident(DB)
    mb = (after - before) * PAGE / 1048576
    print(f"  {label:38s} {ms:8.1f} ms   read {mb:7.1f} MB   rows={len(rows)}")
    return ms, mb, rows


if __name__ == "__main__":
    for term, like in [("binding", "%binding%"), ("kinase", "%kinase%")]:
        m = f'"{term}"*'
        print(f"\n=== term: {term} ===")
        run("current (join everything, then sort)", CURRENT, (like, m, like))
        for pool in (500, 2000):
            run(f"pre-rank in index, pool={pool}", PRERANK, (m, pool, like, like))
