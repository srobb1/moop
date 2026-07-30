#!/usr/bin/env python3
"""Random 4K read latency per volume, measured with O_DIRECT.

WHY O_DIRECT AND NOT fadvise. The other script here, disk_latency.py, calls
cache.evict() and never checks that it worked -- it imports evict but not resident.
POSIX_FADV_DONTNEED is ADVISORY: it drops clean pages when the kernel feels like it.
So a run can silently read from the page cache and report flash-like numbers for a
spinning disk. That is how "sdb does 0.276 ms random reads, so it is already flash"
got recorded as settled fact, and it was wrong by two orders of magnitude.

O_DIRECT removes the question. It bypasses the page cache in the kernel, so there is
no eviction to verify and no way for a cache hit to sneak in.

FAIRNESS. Compare files of SIMILAR SIZE on each volume. An earlier ad-hoc run compared
a 327 MB database on sdb against a 3 KB .bashrc on sda -- with a file smaller than one
block every "random" offset resolved to block 0, so it re-read one block 60 times and
made sda look better than it is. If a volume has no large file to test, this creates
one rather than quietly testing a small one.

usage:  disk_random_read.py [--reads N] [--size-mb N] [PATH ...]

With no PATH it tests one file per mounted volume it can find a suitable file on.
"""
import os, random, statistics, sys, time

BLOCK = 4096
DEFAULT_READS = 200
DEFAULT_SIZE_MB = 256


def find_or_make(path_hint, size_mb):
    """Return a file of at least size_mb on the same filesystem as path_hint.

    Prefers an existing large file so we are not measuring freshly-written extents,
    which on some storage sit in a faster tier than long-settled data.
    """
    if os.path.isfile(path_hint) and os.path.getsize(path_hint) >= size_mb * 1024**2:
        return path_hint, False

    root = path_hint if os.path.isdir(path_hint) else os.path.dirname(path_hint)
    best, best_size = None, 0
    for dirpath, dirnames, filenames in os.walk(root):
        # Do not descend into other filesystems -- that would defeat the whole point.
        dirnames[:] = [d for d in dirnames
                       if not os.path.ismount(os.path.join(dirpath, d))]
        for name in filenames:
            fp = os.path.join(dirpath, name)
            try:
                if os.path.islink(fp):
                    continue
                sz = os.path.getsize(fp)
            except OSError:
                continue
            if sz > best_size:
                best, best_size = fp, sz
        if best_size >= size_mb * 1024**2:
            break

    if best_size >= size_mb * 1024**2:
        return best, False

    made = os.path.join(root, f".disk_random_read_scratch_{os.getpid()}")
    with open(made, "wb") as fh:
        chunk = os.urandom(1024 * 1024)
        for _ in range(size_mb):
            fh.write(chunk)
        fh.flush()
        os.fsync(fh.fileno())
    return made, True


def measure(path, reads):
    """Median/p95 latency of `reads` random 4K reads, cache bypassed."""
    size = os.path.getsize(path)
    if size < BLOCK * 64:
        return None, f"too small to sample randomly ({size} bytes)"

    try:
        fd = os.open(path, os.O_RDONLY | os.O_DIRECT)
    except (AttributeError, OSError) as exc:
        return None, f"O_DIRECT unavailable ({exc}) -- number would be untrustworthy"

    try:
        # O_DIRECT requires a page-aligned buffer; an anonymous mmap gives one.
        import mmap
        buf = mmap.mmap(-1, BLOCK)
        highest = (size - BLOCK) & ~(BLOCK - 1)
        latencies = []
        for _ in range(reads):
            offset = random.randrange(0, highest // BLOCK + 1) * BLOCK
            t0 = time.perf_counter()
            os.preadv(fd, [buf], offset)
            latencies.append((time.perf_counter() - t0) * 1000.0)
        latencies.sort()
        return {
            "n": len(latencies),
            "median": statistics.median(latencies),
            "mean": statistics.fmean(latencies),
            "p95": latencies[int(len(latencies) * 0.95) - 1],
            "max": latencies[-1],
            "size_mb": size / 1024**2,
        }, None
    finally:
        os.close(fd)


def main():
    args = sys.argv[1:]
    reads = DEFAULT_READS
    size_mb = DEFAULT_SIZE_MB
    if "--reads" in args:
        i = args.index("--reads"); reads = int(args[i + 1]); del args[i:i + 2]
    if "--size-mb" in args:
        i = args.index("--size-mb"); size_mb = int(args[i + 1]); del args[i:i + 2]

    targets = args or ["/var/www/html/moop/organisms", "/home", "/tmp"]

    print(f"\nRandom {BLOCK}-byte reads, {reads} per volume, O_DIRECT (page cache bypassed)")
    print(f"{'path':<46}{'MB':>7}{'median':>10}{'mean':>9}{'p95':>9}{'max':>9}")
    print("-" * 90)

    scratch = []
    try:
        for hint in targets:
            if not os.path.exists(hint):
                print(f"{hint:<46}  (absent)")
                continue
            path, made = find_or_make(hint, size_mb)
            if made:
                scratch.append(path)
            stats, err = measure(path, reads)
            label = path if len(path) <= 45 else "..." + path[-42:]
            if err:
                print(f"{label:<46}  {err}")
                continue
            print(f"{label:<46}{stats['size_mb']:>7.0f}"
                  f"{stats['median']:>9.3f}m{stats['mean']:>8.3f}m"
                  f"{stats['p95']:>8.3f}m{stats['max']:>8.3f}m")
    finally:
        for f in scratch:
            try:
                os.unlink(f)
            except OSError:
                pass

    print("\nRule of thumb: flash is well under 1 ms. A 7200rpm spindle cannot beat")
    print("~8-12 ms. Anything above that is a spindle or contended shared storage.")


if __name__ == "__main__":
    main()
