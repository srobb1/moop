#!/usr/bin/env python3
"""Is bm25 earning its 62% of the I/O, or would low-tech ranking do?

bm25 has TWO jobs in searchFeaturesAndAnnotations(), and they are worth separating:

  SELECTING the pool  -- narrow N matching documents down to 5,000 candidates, using
                         only the index. This is the expensive half (24.7 MB of
                         helicase's 40.1 MB) because it reads a scattered docsize entry
                         per matched document.
  ORDERING the result -- it is the FIFTH tier in the ORDER BY, below name_match, the
                         literal LIKE, the stem LIKE and the has-a-name tie-break. It
                         only separates rows those four could not.

The cheap alternative for selection is ORDER BY rowid, which costs 0.7 MB instead of
24.7 MB -- a 35x saving -- but picks an ARBITRARY 5,000 (rowid is insertion order).
The question is whether the four real tiers, applied afterwards, put the same rows on
the first page anyway.

So: run both, hold the tiers identical, and compare the TOP 100. Agreement means bm25
is buying nothing the tiers do not already deliver. Disagreement means it is load-bearing
and the cost is the price of correctness. Either answer is useful; guessing is not.
"""
import os, sys, sqlite3, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fts_split import ORGDB, POOL, evict, read_bytes

F = "feature_annotation_search"
CAP = 2500

TIERS = """ORDER BY (f.feature_name LIKE :nm) DESC,
                    (a.annotation_description LIKE :nm) DESC,
                    (COALESCE(f.feature_name,'') <> '') DESC,
                    {rank}
                    f.feature_uniquename"""

BODY = """SELECT f.feature_uniquename, a.annotation_accession, f.feature_name,
                 a.annotation_description
          FROM pool
          JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
          JOIN feature f ON f.feature_id = fa.feature_id
          JOIN annotation a ON a.annotation_id = fa.annotation_id
          {tiers}
          LIMIT {cap}"""


def run(term, mode, pool=POOL):
    """mode 'bm25' = today's pool selection; mode 'rowid' = the cheap one."""
    if mode == "bm25":
        cte = (f"WITH pool AS (SELECT rowid AS rid, bm25({F},10.0,5.0,2.0,3.0) AS r "
               f"FROM {F} WHERE {F} MATCH :t ORDER BY r LIMIT {pool})")
        rank = "pool.r,"
    else:
        cte = (f"WITH pool AS (SELECT rowid AS rid, 0 AS r "
               f"FROM {F} WHERE {F} MATCH :t ORDER BY rowid LIMIT {pool})")
        rank = ""
    sql = cte + BODY.format(tiers=TIERS.format(rank=rank), cap=CAP)
    evict(ORGDB)
    b0, t0 = read_bytes(), time.perf_counter()
    c = sqlite3.connect(f"file:{ORGDB}?mode=ro", uri=True)
    rows = c.execute(sql, {"t": term, "nm": f"%{term}%"}).fetchall()
    c.close()
    return rows, time.perf_counter() - t0, (read_bytes() - b0) / 1048576


if __name__ == "__main__":
    terms = sys.argv[1:] or ["piwi", "pax", "helicase", "ubiquitin", "kinase", "binding"]
    print(f"\n  {'term':11} {'bm25 pool':>19}   {'rowid pool':>19}   {'top-100':>8} {'top-2500':>9}")
    print(f"  {'':11} {'MB':>8} {'s':>9}   {'MB':>8} {'s':>9}   {'agree':>8} {'agree':>9}\n")
    for term in terms:
        a, at, amb = run(term, "bm25")
        b, bt, bmb = run(term, "rowid")
        ka, kb = [tuple(r[:2]) for r in a], [tuple(r[:2]) for r in b]
        top100 = len(set(ka[:100]) & set(kb[:100]))
        overall = len(set(ka) & set(kb))
        denom = max(1, min(len(ka), len(kb)))
        print(f"  {term:11} {amb:8.1f} {at:9.2f}   {bmb:8.1f} {bt:9.2f}   "
              f"{top100:6d}% {overall*100//denom:8d}%")
        if top100 < 100:
            print(f"      first divergence at rank "
                  f"{next((i+1 for i,(x,y) in enumerate(zip(ka,kb)) if x != y), '-')}"
                  f"   (bm25 returned {len(ka)} rows, rowid {len(kb)})")
