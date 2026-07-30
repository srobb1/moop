#!/usr/bin/env python3
"""Random 4K read latency per volume, page cache evicted first.

⚠️ PREFER disk_random_read.py. THIS SCRIPT PRODUCED A WRONG ANSWER AND WAS BELIEVED.

On 2026-07-28 it reported sdb at 0.276 ms and that was recorded as settled fact:
"the data volume is already flash, the storage ask is dead." Re-measured with
O_DIRECT on 2026-07-29, sdb does 32 ms median. It is not flash, and acting on the
wrong number nearly sent a false claim to IT.

The mechanism: evict() below is POSIX_FADV_DONTNEED, which is ADVISORY -- the kernel
drops clean pages when it feels like it, and there was NO CHECK that it had. bench.py
in this same directory reads base.sqlite heavily, so by the time this ran the file was
largely resident, the evict did not take, and 400 "disk reads" were cache hits. The
harness README's own first rule -- "ALWAYS verify eviction" -- was broken by one of its
own scripts.

Now it verifies residency with mincore before timing and REFUSES to print a latency it
cannot vouch for. But buffered reads still populate the cache as they go, so even a
verified-cold start drifts warm across 400 samples. disk_random_read.py uses O_DIRECT,
which bypasses the page cache in the kernel: nothing to verify, nothing to drift.

A real 7200rpm disk cannot do better than roughly 8-12 ms per random read. Flash is
well under 1 ms.
"""
import os, random, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import evict, resident, pct

MAX_RESIDENT_PCT = 2.0

N = 400
BS = 4096


def latency(path):
    size = os.path.getsize(path)
    evict(path)
    # VERIFY. This check is the whole reason the numbers below can be trusted at all;
    # without it a cache hit is indistinguishable from a fast disk.
    r, t = resident(path)
    if pct(r, t) > MAX_RESIDENT_PCT:
        raise RuntimeError(
            f"{path}: eviction did not take -- {pct(r, t):.1f}% still resident. "
            f"Any latency measured now would be page-cache hits, not disk. "
            f"Use disk_random_read.py (O_DIRECT) instead."
        )
    fd = os.open(path, os.O_RDONLY)
    try:
        rnd = random.Random(1234)          # same offsets on every volume
        offsets = [rnd.randrange(0, size - BS) // BS * BS for _ in range(N)]
        samples = []
        for off in offsets:
            t0 = time.perf_counter()
            os.pread(fd, BS, off)
            samples.append((time.perf_counter() - t0) * 1000.0)
        return samples
    finally:
        os.close(fd)


if __name__ == "__main__":
    for path, label in [
        ("/var/www/html/moop/organisms/.fts_test/base.sqlite", "ROTATIONAL? (sdb, data)"),
        ("/tmp/ssd_test.sqlite",                               "FLASH (sda, root)"),
    ]:
        if not os.path.exists(path):
            print(f"{label:26s} SKIP -- {path} does not exist")
            continue
        try:
            s = sorted(latency(path))
        except RuntimeError as exc:
            print(f"{label:26s} REFUSED -- {exc}")
            continue
        mean = sum(s) / len(s)
        print(f"{label:26s} mean {mean:7.3f} ms   median {s[len(s)//2]:7.3f} ms   "
              f"p95 {s[int(len(s)*0.95)]:7.3f} ms   max {s[-1]:7.3f} ms")
