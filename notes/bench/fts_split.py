#!/usr/bin/env python3
"""Prototype: does splitting the FTS index actually cut what a search READS?

TWO DESIGNS, same organism, same term, same result cap.

  A (today)  feature_annotation_search -- one FTS document per (feature, annotation)
             pair. 2,884,714 docs, 395 MB. Rank in the index, then join out.
  B (split)  annotation_search -- one FTS document per ANNOTATION (407,002 docs,
             22 MB) plus the existing feature_search for name hits. Rank in the
             small index, then expand to features through feature_annotation.

MEASURING HONESTLY -- three traps this avoids:

  1. WRONG DEVICE. /tmp is on rootvg (fast); the databases are on datavg (the slow
     volume the notes measure at ~32 ms random reads). A prototype in /tmp would beat
     the real index on device speed alone. The prototype is built on datavg.
  2. WARM CACHE. Building B reads the source tables, warming exactly what A needs.
     So every timed run starts by evicting its own files with posix_fadvise(DONTNEED)
     -- targeted, so the rest of the site keeps its cache.
  3. ASSUMED EVICTION. mincore() verifies the pages actually left. The notes already
     record one benchmark that called evict() and never checked, reporting a cache
     hit as disk latency. Every run prints residency after eviction.

Bytes come from /proc/self/io read_bytes, so the figure is this process's real reads
regardless of which device served them.
"""
import ctypes, mmap, os, sqlite3, sys, time

ORGDB = "/var/www/html/moop/organisms/Rhinolophus_ferrumequinum/organism.sqlite"
PROTO = "/var/www/html/moop/notes/bench/split_proto.sqlite"  # same volume as ORGDB, on purpose
CAP = 2500
POOL = CAP * 2

libc = ctypes.CDLL("libc.so.6", use_errno=True)


libc.mmap.restype = ctypes.c_void_p
libc.mmap.argtypes = [ctypes.c_void_p, ctypes.c_size_t, ctypes.c_int,
                      ctypes.c_int, ctypes.c_int, ctypes.c_long]
libc.munmap.argtypes = [ctypes.c_void_p, ctypes.c_size_t]
libc.mincore.argtypes = [ctypes.c_void_p, ctypes.c_size_t, ctypes.c_char_p]
PROT_READ, MAP_SHARED = 1, 1


def resident_pages(path):
    """How many of this file's pages are in page cache right now, via mincore().

    Maps through libc rather than Python's mmap module: mincore() needs the raw
    address, and a read-only Python mmap will not hand one out (ctypes refuses to
    take the address of a non-writable buffer).
    """
    fd = os.open(path, os.O_RDONLY)
    try:
        size = os.fstat(fd).st_size
        if size == 0:
            return 0, 0
        addr = libc.mmap(None, size, PROT_READ, MAP_SHARED, fd, 0)
        if addr in (None, ctypes.c_void_p(-1).value):
            return -1, 0
        try:
            npages = (size + mmap.PAGESIZE - 1) // mmap.PAGESIZE
            vec = ctypes.create_string_buffer(npages)
            if libc.mincore(ctypes.c_void_p(addr), ctypes.c_size_t(size), vec) != 0:
                return -1, npages
            return sum(b & 1 for b in vec.raw), npages
        finally:
            libc.munmap(ctypes.c_void_p(addr), size)
    finally:
        os.close(fd)


def evict(path):
    fd = os.open(path, os.O_RDONLY)
    try:
        os.posix_fadvise(fd, 0, 0, os.POSIX_FADV_DONTNEED)
    finally:
        os.close(fd)


def read_bytes():
    with open("/proc/self/io") as fh:
        for line in fh:
            if line.startswith("read_bytes:"):
                return int(line.split()[1])
    return 0


def build_proto():
    if os.path.exists(PROTO):
        os.remove(PROTO)
    # uri=True on the MAIN connection is what enables URI parsing for the ATTACH below;
    # without it SQLite treats "file:...?mode=ro" as a literal filename and cannot open it.
    c = sqlite3.connect(f"file:{PROTO}", uri=True)
    c.execute("PRAGMA journal_mode=OFF")
    c.execute(f"ATTACH 'file:{ORGDB}?mode=ro' AS o")
    c.execute("""CREATE VIRTUAL TABLE annotation_search USING fts5(
                     annotation_description, annotation_accession,
                     content='', tokenize='porter unicode61')""")
    c.execute("""INSERT INTO annotation_search(rowid, annotation_description, annotation_accession)
                 SELECT a.annotation_id, a.annotation_description, a.annotation_accession
                 FROM o.annotation a""")
    c.execute("INSERT INTO annotation_search(annotation_search) VALUES('optimize')")
    c.commit()
    c.close()
    print(f"  prototype built: {os.path.getsize(PROTO)/1048576:.0f} MB\n")


COLS = """f.feature_uniquename, f.feature_name, f.feature_description,
          a.annotation_accession, a.annotation_description, fa.score,
          ans.annotation_source_name, o2.genus, o2.species, f.feature_type"""


def joins(p):
    return f"""JOIN {p}feature f ON f.feature_id = fa.feature_id
               JOIN {p}annotation a ON a.annotation_id = fa.annotation_id
               JOIN {p}annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
               JOIN {p}organism o2 ON o2.organism_id = f.organism_id"""


def design_a(term):
    c = sqlite3.connect(f"file:{ORGDB}?mode=ro", uri=True)
    sql = f"""WITH pool AS (
                SELECT rowid AS rid, bm25(feature_annotation_search,10.0,5.0,2.0,3.0) AS rank
                FROM feature_annotation_search WHERE feature_annotation_search MATCH ?
                ORDER BY rank LIMIT {POOL})
              SELECT {COLS} FROM pool
              JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
              {joins('')}
              LIMIT {CAP}"""
    n = len(c.execute(sql, (term,)).fetchall())
    c.close()
    return n


def design_b(term):
    c = sqlite3.connect(f"file:{PROTO}?mode=ro", uri=True)
    c.execute(f"ATTACH 'file:{ORGDB}?mode=ro' AS o")
    sql = f"""WITH pool AS (
                SELECT rowid AS rid, bm25(annotation_search,2.0,3.0) AS rank
                FROM annotation_search WHERE annotation_search MATCH ?
                ORDER BY rank LIMIT {POOL})
              SELECT {COLS} FROM pool
              JOIN o.feature_annotation fa ON fa.annotation_id = pool.rid
              {joins('o.')}
              ORDER BY pool.rank LIMIT {CAP}"""
    rows = c.execute(sql, (term,)).fetchall()
    # Name hits come from the existing per-feature index, which design A folds into its
    # one big index. Counted here so B is charged for the work A does inside its query.
    # NB: FTS5's MATCH takes the BARE table name on the left -- not schema-qualified
    # ("o.feature_search MATCH ?") and not an alias ("fs MATCH ?"). Both are errors.
    # The FROM side still needs the schema prefix.
    c.execute("SELECT COUNT(*) FROM o.feature_search WHERE feature_search MATCH ?", (term,)).fetchone()
    c.close()
    return len(rows)


def run(label, fn, term, files):
    for f in files:
        evict(f)
    res = [(os.path.basename(f), *resident_pages(f)) for f in files]
    b0, t0 = read_bytes(), time.perf_counter()
    n = fn(term)
    el, mb = time.perf_counter() - t0, (read_bytes() - b0) / 1048576
    print(f"  {label:24} {el:7.2f} s   {mb:8.1f} MB read   {n:6d} rows")
    print("      after evict: " + ", ".join(f"{b} {r}/{t} pages resident" for b, r, t in res))
    return el, mb


if __name__ == "__main__":
    term = sys.argv[1] if len(sys.argv) > 1 else "helicase"
    # Check the TABLE, not the file. A failed build leaves a zero-table database behind,
    # and "the file exists" then skips the rebuild and fails much later with a confusing
    # "no such table" in the middle of a timed run.
    built = False
    if os.path.exists(PROTO):
        chk = sqlite3.connect(f"file:{PROTO}?mode=ro", uri=True)
        built = bool(chk.execute(
            "SELECT 1 FROM sqlite_master WHERE name='annotation_search'").fetchone())
        chk.close()
    if not built:
        build_proto()
    print(f"\n  Rhinolophus_ferrumequinum, term {term!r}, cap {CAP}, pool {POOL}\n")

    # ALTERNATE and REPEAT. Two runs of design A with identical byte counts came back
    # 9.21 s and 0.90 s, because wall time here tracks how scattered the reads are, not
    # how many bytes they total. One run of each would be a coin flip; report the median.
    reps = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    res = {"A": [], "B": []}
    for i in range(reps):
        print(f"  --- round {i+1} ---")
        res["A"].append(run("A today (pair index)", design_a, term, [ORGDB]))
        res["B"].append(run("B split (annot index)", design_b, term, [ORGDB, PROTO]))

    def med(vals):
        s = sorted(vals)
        return s[len(s) // 2]

    at, ab = med([r[0] for r in res["A"]]), med([r[1] for r in res["A"]])
    bt, bb = med([r[0] for r in res["B"]]), med([r[1] for r in res["B"]])
    print(f"\n  MEDIAN of {reps}")
    print(f"  bytes read  {ab:8.1f} MB -> {bb:8.1f} MB   ({ab/bb:.1f}x less)" if bb else "")
    print(f"  wall clock  {at:8.2f} s  -> {bt:8.2f} s    ({at/bt:.1f}x faster)" if bt else "")
