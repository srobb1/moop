#!/usr/bin/env python3
"""How fast does varied real usage fill the page cache?

One search's working set fitting in cache is not the same as the cache being big
enough. Different terms touch different pages. This evicts everything, then runs a
series of DISTINCT terms, reporting the CUMULATIVE resident bytes after each --
the saturation curve. If it flattens well below the cache size, more RAM buys
little; if it is still climbing when it hits the limit, eviction starts costing
real searches and more RAM buys headroom.
"""
import os, sys, time
sys.path.insert(0, ".")
from cache import evict, resident
from crossorg import is_clean, fan_out, ORGANISMS, PAGE

dbs = []
for name in sorted(os.listdir(ORGANISMS)):
    db = os.path.join(ORGANISMS, name, "organism.sqlite")
    if os.path.isfile(db) and is_clean(db):
        dbs.append(db)

def resident_gb():
    return sum(resident(d)[0] for d in dbs) * PAGE / 1024**3

with open("/proc/meminfo") as fh:
    cache_gb = next(int(l.split()[1]) for l in fh if l.startswith("Cached:")) / 1024**2

print(f"{len(dbs)} databases, "
      f"{sum(os.path.getsize(d) for d in dbs)/1024**3:.1f} GB on disk, "
      f"page cache {cache_gb:.1f} GB", flush=True)

for d in dbs:
    evict(d)
print(f"evicted -> {resident_gb():.2f} GB resident\n", flush=True)

print(f"{'term':<16}{'search s':>10}{'cumulative GB':>16}{'delta GB':>10}", flush=True)
print("-" * 52, flush=True)
prev = resident_gb()
for term in ["kinase", "binding", "transposase", "transcription", "membrane", "zinc"]:
    t0 = time.perf_counter()
    fan_out(dbs, term)
    secs = time.perf_counter() - t0
    now = resident_gb()
    print(f"{term:<16}{secs:>10.1f}{now:>16.2f}{now-prev:>10.2f}", flush=True)
    prev = now
print(f"\npage cache is {cache_gb:.1f} GB", flush=True)
