#!/usr/bin/env python3
"""Page-cache residency: report and evict, with VERIFICATION.

The point of this file is that eviction is *checked*, not assumed. The previous
round's cold numbers did not reproduce because dd oflag=nocache is advisory.

usage:  cache.py stat <file>...      -> % of file resident in page cache
        cache.py evict <file>...     -> fadvise(DONTNEED) then re-stat
"""
import ctypes, mmap, os, sys

libc = ctypes.CDLL("libc.so.6", use_errno=True)


def resident(path):
    """Return (resident_pages, total_pages) via mincore(2)."""
    fd = os.open(path, os.O_RDONLY)
    try:
        size = os.fstat(fd).st_size
        if size == 0:
            return (0, 0)
        # MAP_PRIVATE + PROT_WRITE: ctypes needs a writable buffer to take an address.
        # Private means nothing is ever written back to the file; we only read it, and
        # mincore still reports the file's page-cache residency.
        m = mmap.mmap(fd, size, flags=mmap.MAP_PRIVATE,
                      prot=mmap.PROT_READ | mmap.PROT_WRITE)
        try:
            pagesize = os.sysconf("SC_PAGESIZE")
            npages = (size + pagesize - 1) // pagesize
            vec = (ctypes.c_ubyte * npages)()
            addr = ctypes.addressof((ctypes.c_char * size).from_buffer(m))
            if libc.mincore(ctypes.c_void_p(addr), ctypes.c_size_t(size), vec) != 0:
                raise OSError(ctypes.get_errno(), "mincore failed")
            return (sum(1 for b in vec if b & 1), npages)
        finally:
            m.close()
    finally:
        os.close(fd)


def evict(path):
    """Drop clean pages for one file. Needs no privileges."""
    fd = os.open(path, os.O_RDONLY)
    try:
        os.fsync(fd)
        os.posix_fadvise(fd, 0, 0, os.POSIX_FADV_DONTNEED)
    finally:
        os.close(fd)


def pct(r, t):
    return 0.0 if t == 0 else 100.0 * r / t


if __name__ == "__main__":
    mode, files = sys.argv[1], sys.argv[2:]
    for f in files:
        if mode == "evict":
            before = resident(f)
            evict(f)
            after = resident(f)
            print(f"{os.path.basename(f):28s} {pct(*before):6.1f}% -> {pct(*after):6.1f}% "
                  f"({after[0]}/{after[1]} pages resident)")
        else:
            r = resident(f)
            print(f"{os.path.basename(f):28s} {pct(*r):6.1f}% ({r[0]}/{r[1]} pages)")
