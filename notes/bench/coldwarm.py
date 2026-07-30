#!/usr/bin/env python3
"""Cold vs warm timing for ONE database, with eviction VERIFIED via mincore.

Deliberately one database, not a sweep: a full-corpus benchmark flushes the page
cache for hours (see notes/STORAGE_AND_RAM_TESTING.md). This touches ~0.4 GB.
"""
import sqlite3, sys, time
sys.path.insert(0, '/var/www/html/moop/notes/bench')
import cache

DB = '/var/www/html/moop/organisms/Craseonycteris_thonglongyai/organism.sqlite'
TERM = '"transposases"*'

SQL = """
WITH pool AS (
  SELECT rowid AS rid, bm25(feature_annotation_search,10.0,5.0,2.0,3.0) AS rank
  FROM feature_annotation_search WHERE feature_annotation_search MATCH ?
  ORDER BY rank LIMIT 5000
)
SELECT COUNT(*) FROM pool
JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
JOIN feature f            ON f.feature_id             = fa.feature_id
JOIN annotation a         ON a.annotation_id          = fa.annotation_id
"""

def run():
    c = sqlite3.connect('file:' + DB + '?mode=ro', uri=True)
    t = time.perf_counter()
    n = c.execute(SQL, (TERM,)).fetchone()[0]
    dt = (time.perf_counter() - t) * 1000
    c.close()
    return dt, n

r, tot = cache.resident(DB)
print('before evict : %.1f%% resident' % (100.0 * r / tot))
cache.evict(DB)
r, tot = cache.resident(DB)
print('after  evict : %.1f%% resident   <- verified, not assumed' % (100.0 * r / tot))
if r * 100.0 / tot > 1.0:
    print('!! eviction failed; timing below would be meaningless'); sys.exit(1)

cold, n = run()
warm, _ = run()
r, tot = cache.resident(DB)
print()
print('COLD : %8.1f ms   (%d rows)' % (cold, n))
print('WARM : %8.1f ms' % warm)
print('ratio: %8.0fx' % (cold / warm))
print('after run    : %.1f%% resident' % (100.0 * r / tot))
