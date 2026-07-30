#!/usr/bin/env python3
"""How many bytes does ONE cold search actually fault in?

Everything about the RAM question turns on this. "The database is 688 MB and the
cache is 12 GB" is the wrong comparison -- what matters is the bytes a query
actually touches, which mincore can measure exactly: evict, count resident pages,
run the query, count again. The delta IS the working set, not an estimate.
"""
import os, sqlite3, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import evict, resident

DB = "/var/www/html/moop/organisms/Nematostella_vectensis/organism.sqlite"
PAGE = os.sysconf("SC_PAGESIZE")

SQL_ANNOT = """
SELECT f.feature_uniquename, a.annotation_description
FROM feature_annotation_search fas
JOIN feature_annotation fa ON fa.feature_annotation_id = fas.rowid
JOIN feature f ON f.feature_id = fa.feature_id
JOIN annotation a ON a.annotation_id = fa.annotation_id
WHERE feature_annotation_search MATCH ?
ORDER BY bm25(feature_annotation_search) LIMIT 100
"""
SQL_ID = """
SELECT feature_uniquename, feature_name FROM feature
WHERE feature_uniquename LIKE ? LIMIT 100
"""


def measure(label, sql, param):
    evict(DB)
    before, total = resident(DB)
    t0 = time.perf_counter()
    con = sqlite3.connect(f"file:{DB}?mode=ro", uri=True)
    n = len(con.execute(sql, (param,)).fetchall())
    con.close()
    ms = (time.perf_counter() - t0) * 1000
    after, _ = resident(DB)
    read_mb = (after - before) * PAGE / 1048576
    file_mb = total * PAGE / 1048576
    print(f"  {label:34s} {ms:8.1f} ms   read {read_mb:7.1f} MB "
          f"of {file_mb:.0f} MB ({100*read_mb/file_mb:4.1f}%)   rows={n}")
    return read_mb


if __name__ == "__main__":
    print("\n=== bytes faulted in by ONE cold query (Nematostella, 688 MB) ===")
    a = measure("annotation search 'kinase'", SQL_ANNOT, '"kinase"*')
    b = measure("annotation search 'binding'", SQL_ANNOT, '"binding"*')
    c = measure("annotation search 'transposase'", SQL_ANNOT, '"transposase"*')
    d = measure("gene ID search 'NV2g0193%'", SQL_ID, 'NV2g0193%')

    print("\n=== extrapolated across 85 organisms ===")
    for name, mb in [("one annotation term", a), ("one gene-ID search", d)]:
        print(f"  {name:24s} ~{mb*85/1024:6.2f} GB per cross-organism search")
