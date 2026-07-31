#!/usr/bin/env python3
"""Where does a search's I/O actually go: the FTS index, or fetching the rows?

The split prototype shrank the index 17.8x and made the query SLOWER. This decomposes
design A's cost to find out what it was really paying for -- the index lookup, or the
join that fetches the matched rows out of feature/annotation.

Each stage is measured after a verified eviction, so the stages are not warming each
other. Stages are cumulative: each does everything the previous one did, plus more.
"""
import os, sqlite3, sys, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fts_split import ORGDB, POOL, CAP, evict, read_bytes, resident_pages

STAGES = {
    "1. FTS match only (count)":
        f"""SELECT COUNT(*) FROM feature_annotation_search
            WHERE feature_annotation_search MATCH ?""",
    "2. + bm25 rank + LIMIT":
        f"""SELECT rowid, bm25(feature_annotation_search,10.0,5.0,2.0,3.0) AS r
            FROM feature_annotation_search WHERE feature_annotation_search MATCH ?
            ORDER BY r LIMIT {POOL}""",
    "3. + join feature_annotation":
        f"""WITH pool AS (SELECT rowid AS rid, bm25(feature_annotation_search,10.0,5.0,2.0,3.0) AS r
                          FROM feature_annotation_search WHERE feature_annotation_search MATCH ?
                          ORDER BY r LIMIT {POOL})
            SELECT COUNT(*) FROM pool
            JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid""",
    "4. + join feature+annotation":
        f"""WITH pool AS (SELECT rowid AS rid, bm25(feature_annotation_search,10.0,5.0,2.0,3.0) AS r
                          FROM feature_annotation_search WHERE feature_annotation_search MATCH ?
                          ORDER BY r LIMIT {POOL})
            SELECT COUNT(*) FROM pool
            JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
            JOIN feature f ON f.feature_id = fa.feature_id
            JOIN annotation a ON a.annotation_id = fa.annotation_id""",
    "5. + fetch all columns":
        f"""WITH pool AS (SELECT rowid AS rid, bm25(feature_annotation_search,10.0,5.0,2.0,3.0) AS r
                          FROM feature_annotation_search WHERE feature_annotation_search MATCH ?
                          ORDER BY r LIMIT {POOL})
            SELECT f.feature_uniquename, f.feature_name, f.feature_description,
                   a.annotation_accession, a.annotation_description, fa.score,
                   ans.annotation_source_name
            FROM pool
            JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
            JOIN feature f ON f.feature_id = fa.feature_id
            JOIN annotation a ON a.annotation_id = fa.annotation_id
            JOIN annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
            LIMIT {CAP}""",
}

term = sys.argv[1] if len(sys.argv) > 1 else "helicase"
print(f"\n  {ORGDB.split('/')[-2]}, term {term!r} — cumulative stages, each from cold\n")
prev = 0.0
for label, sql in STAGES.items():
    evict(ORGDB)
    r, n = resident_pages(ORGDB)
    b0, t0 = read_bytes(), time.perf_counter()
    c = sqlite3.connect(f"file:{ORGDB}?mode=ro", uri=True)
    c.execute(sql, (term,)).fetchall()
    c.close()
    el, mb = time.perf_counter() - t0, (read_bytes() - b0) / 1048576
    print(f"  {label:32} {el:6.2f} s  {mb:7.1f} MB   (+{mb-prev:6.1f} MB)   [{r}/{n} resident]")
    prev = mb
