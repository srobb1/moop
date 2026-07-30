#!/usr/bin/env python3
"""A better disk benchmark: isolate I/O time, and measure what was actually read.

Three things the earlier runs got wrong, all of which flatter or distort the result:

  1. RAW COLD TIME MIXES CPU WITH DISK. "kinase" spends 288 ms warm, so its cold
     time is mostly CPU and the disk difference disappears into it. Report
     COLD - WARM: that is the I/O component.

  2. NO IDEA HOW MUCH WAS READ. Without bytes, a fast time can mean a fast disk
     OR a query that touched almost nothing. Reading /proc/diskstats around the
     query gives MB and therefore effective MB/s -- which is what separates a
     sequential scan (spinning disk keeps up) from scattered reads (it does not).

  3. ONE TERM IS NOT A WORKLOAD. Selectivity changes the access pattern
     completely: a common term scans a long posting list sequentially, a rare one
     hops. Both are real user behaviour.
"""
import sqlite3, sys, time
sys.path.insert(0, '/var/www/html/moop/notes/bench')
import cache

COPIES = [('sda flash', '/home/smr/organism.sqlite', 'sda'),
          ('sdb spin',  '/var/www/html/moop/testing/organism.sqlite', 'sdb')]
TERMS = ['"kinase"*', '"receptor"*', '"zinc finger"', '"transposases"*', '"maelstrom"*']

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

def sectors(dev):
    with open('/proc/diskstats') as fh:
        for line in fh:
            f = line.split()
            if len(f) > 5 and f[2] == dev:
                return int(f[5])
    return 0

def run(path, term, dev):
    c = sqlite3.connect('file:' + path + '?mode=ro', uri=True)
    s0 = sectors(dev); t = time.perf_counter()
    n = c.execute(SQL, (term,)).fetchone()[0]
    dt = (time.perf_counter() - t) * 1000
    mb = (sectors(dev) - s0) * 512 / 1024**2
    c.close()
    return dt, mb, n

print('%-15s %-11s %8s %8s %8s %8s %9s' %
      ('term', 'volume', 'cold', 'warm', 'I/O', 'MB read', 'MB/s'))
print('-' * 76)
for term in TERMS:
    io = {}
    for label, path, dev in COPIES:
        cache.evict(path)
        r, t = cache.resident(path)
        if r * 100.0 / t > 1.0:
            print('  eviction failed on %s' % label); continue
        cold, mb, n = run(path, term, dev)
        warm, _, _  = run(path, term, dev)
        iot = cold - warm
        io[label] = (iot, mb)
        print('%-15s %-11s %7.0fms %7.0fms %7.0fms %7.1f %9.0f'
              % (term.strip('"*'), label, cold, warm, iot, mb,
                 mb / (iot / 1000) if iot > 1 else 0))
    if 'sda flash' in io and 'sdb spin' in io and io['sda flash'][0] > 1:
        print('%-15s %-11s I/O ratio spin/flash = %.1fx'
              % ('', '', io['sdb spin'][0] / io['sda flash'][0]))
    print()
