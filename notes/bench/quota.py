#!/usr/bin/env python3
"""Step 1 of the plan: can a per-type QUOTA pool replace bm25's pool selection?

WHAT WE KNOW (notes/SEARCH_COST_MODEL_2026-07-31.md):
  - finding matches costs 0.4-0.7 MB; ALL the cost is bm25() ranking the match set
  - bm25 is ORDER BY tier 5, so it barely affects order -- its real job is picking
    the 5,000-row pool, and what it buys there is source spread
  - it buys it badly: bm25 favours SHORT documents, which are ProtNLM's terse
    AI-generated names, so 74/100 of page one for `helicase` comes from the type
    metadata/annotation_config.json ranks 8th of 10
  - pooling by rowid instead is 2-3.5x cheaper but drops ProtNLM to ZERO and
    collapses 16 sources to 2 -- diversity by accident, in the other direction
  - a curated type tier applied as a HARD tier over-corrects to 100/100 one type

SO: fill the pool with an explicit quota per annotation type, taken in the curated
order, each slice ordered by rowid (free) rather than bm25 (expensive). Diversity
becomes intentional and tunable instead of a side effect of term statistics.

The prototype index is today's PAIR index plus one column, annotation_type, so the
pool can be filtered by type INSIDE the FTS index -- no docsize reads, and the
rowid still joins straight back to feature_annotation exactly as today.

Built on the same slow volume as the real databases (/tmp is the fast root disk and
would flatter the prototype). Eviction verified by mincore() every run.
"""
import json, os, sys, sqlite3, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fts_split import ORGDB, evict, read_bytes, resident_pages

PROTO = "/var/www/html/moop/notes/bench/quota_proto.sqlite"
CAP, POOL = 2500, 5000
CFG = "/var/www/html/moop/metadata/annotation_config.json"

CURATED = [k for k, _ in sorted(
    json.load(open(CFG))["annotation_types"].items(),
    key=lambda kv: kv[1].get("order", 99))]

# Index a CODE, never the type name.
#
# The obvious thing -- store the name with spaces as underscores -- is broken, and
# silently. unicode61 splits on '_' and porter stems the pieces, so "RBBH_Homolog"
# indexes the token "homolog" and so does "Homologs". Filtering on Homologs then
# returned 870,674 rows where only 69,398 qualify (69,398 + 801,276 RBBH = exactly
# that), so the Homologs quota was filled with RBBH rows and Homologs never appeared.
# A code has no shared stem with anything, and is verified exact below by
# --check against a SQL ground-truth count.
TYPE_CODE = {name: f"atype{i}z" for i, name in enumerate(CURATED)}
TYPE_CASE = ("CASE ans.annotation_type "
             + " ".join(f"WHEN '{k}' THEN '{v}'" for k, v in TYPE_CODE.items())
             + " ELSE 'atypexz' END")


def build():
    if os.path.exists(PROTO):
        os.remove(PROTO)
    c = sqlite3.connect(f"file:{PROTO}", uri=True)
    c.execute("PRAGMA journal_mode=OFF")
    c.execute("PRAGMA synchronous=OFF")
    c.execute(f"ATTACH 'file:{ORGDB}?mode=ro' AS o")
    # Today's index plus annotation_type. rowid stays feature_annotation_id so the
    # join back is byte-for-byte the same as the shipped query.
    c.execute("""CREATE VIRTUAL TABLE fas USING fts5(
                     feature_name, feature_description, annotation_description,
                     annotation_accession, annotation_type,
                     content='', tokenize='porter unicode61')""")
    t0 = time.perf_counter()
    c.execute("""INSERT INTO fas(rowid, feature_name, feature_description,
                                 annotation_description, annotation_accession, annotation_type)
                 SELECT fa.feature_annotation_id, f.feature_name, f.feature_description,
                        a.annotation_description, a.annotation_accession,
                        """ + TYPE_CASE + """
                 FROM o.feature_annotation fa
                 JOIN o.feature f ON f.feature_id = fa.feature_id
                 JOIN o.annotation a ON a.annotation_id = fa.annotation_id
                 JOIN o.annotation_source ans ON ans.annotation_source_id = a.annotation_source_id""")
    c.execute("INSERT INTO fas(fas) VALUES('optimize')")
    c.commit(); c.close()
    print(f"  prototype built in {time.perf_counter()-t0:.0f}s, "
          f"{os.path.getsize(PROTO)/1048576:.0f} MB\n")


def quota_pool(c, term, per_pass):
    """Fill the pool by taking per_pass rows per type, cycling in curated order.

    Higher-ranked types get first pick; types with nothing matching simply drop out
    and their capacity goes to whoever is left, so no slot is wasted on an absent
    type. Each slice is ORDER BY rowid -- the free ordering -- because which rows
    come from a type matters far less than that the type is represented at all.
    """
    got, seen, exhausted = [], set(), set()
    while len(got) < POOL and len(exhausted) < len(CURATED):
        for t in CURATED:
            if t in exhausted or len(got) >= POOL:
                continue
            rows = c.execute(
                "SELECT rowid FROM fas WHERE fas MATCH ? ORDER BY rowid LIMIT ? OFFSET ?",
                (f'{{annotation_type}} : {TYPE_CODE[t]} AND '
                 f'{{annotation_description annotation_accession feature_name '
                 f'feature_description}} : {term}*',
                 per_pass, sum(1 for r in got if r[1] == t))).fetchall()
            new = [(r[0], t) for r in rows if r[0] not in seen]
            if not new:
                exhausted.add(t)
                continue
            seen.update(r[0] for r in new)
            got.extend(new)
    return [r[0] for r in got[:POOL]]


TIERS = ("ORDER BY (f.feature_name LIKE :nm) DESC, "
         "(a.annotation_description LIKE :nm) DESC, "
         "(COALESCE(f.feature_name,'') <> '') DESC, f.feature_uniquename")


def hard_evict(path, tries=5):
    """Evict, and MAKE SURE. posix_fadvise(DONTNEED) silently skips DIRTY pages, so a
    file written moments ago stays partly resident and the next run reads it from RAM.
    That is exactly the failure disk_latency.py shipped -- a cache hit measured as disk.
    sync() first, then verify with mincore and retry rather than trust one call."""
    for _ in range(tries):
        os.sync()
        evict(path)
        resident, total = resident_pages(path)
        if resident == 0:
            return resident, total
        time.sleep(0.3)
    return resident, total


def run(term, per_pass=250):
    r1, n1 = hard_evict(ORGDB); r2, n2 = hard_evict(PROTO)
    b0, t0 = read_bytes(), time.perf_counter()
    c = sqlite3.connect(f"file:{PROTO}?mode=ro", uri=True)
    c.execute(f"ATTACH 'file:{ORGDB}?mode=ro' AS o")
    rids = quota_pool(c, term, per_pass)
    if not rids:
        c.close(); return [], 0, 0
    c.execute("CREATE TEMP TABLE pool(rid INTEGER PRIMARY KEY)")
    c.executemany("INSERT OR IGNORE INTO pool VALUES (?)", [(x,) for x in rids])
    rows = c.execute(f"""
        SELECT f.feature_uniquename, f.feature_name, a.annotation_description,
               ans.annotation_type
        FROM pool
        JOIN o.feature_annotation fa ON fa.feature_annotation_id = pool.rid
        JOIN o.feature f ON f.feature_id = fa.feature_id
        JOIN o.annotation a ON a.annotation_id = fa.annotation_id
        JOIN o.annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
        {TIERS} LIMIT {CAP}""", {"nm": f"%{term}%"}).fetchall()
    c.close()
    el, mb = time.perf_counter() - t0, (read_bytes() - b0) / 1048576
    assert r1 == 0 and r2 == 0, f"eviction failed: {r1}/{n1}, {r2}/{n2}"
    return rows, mb, el


def check():
    """The type filter must select EXACTLY its type. Verified against a SQL count.

    This exists because the first version did not, and failed silently: the Homologs
    filter returned 870,674 rows of which only 69,398 qualified, so that quota was
    filled with RBBH Homolog rows and Homologs never reached page one. Nothing in the
    output looked wrong -- the diversity table simply reported a zero as if it were data.
    """
    c = sqlite3.connect(f"file:{PROTO}?mode=ro", uri=True)
    c.execute(f"ATTACH 'file:{ORGDB}?mode=ro' AS o")
    truth = dict(c.execute("""SELECT ans.annotation_type, COUNT(*)
        FROM o.feature_annotation fa
        JOIN o.annotation a ON a.annotation_id = fa.annotation_id
        JOIN o.annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
        GROUP BY 1""").fetchall())
    bad = 0
    print(f"\n  {'type':22} {'true':>12} {'filter':>12}  verdict")
    for t in CURATED:
        want = truth.get(t, 0)
        got = c.execute("SELECT COUNT(*) FROM fas WHERE fas MATCH ?",
                        (f'{{annotation_type}} : {TYPE_CODE[t]}',)).fetchone()[0]
        ok = want == got
        bad += not ok
        print(f"  {t:22} {want:12,} {got:12,}  "
              f"{'OK' if ok else '<-- WRONG'}{'' if want or got else ' (absent)'}")
    c.close()
    print(f"\n  {'ALL TYPE FILTERS EXACT' if not bad else str(bad) + ' TYPE FILTER(S) WRONG'}\n")
    return bad


if __name__ == "__main__":
    if not os.path.exists(PROTO):
        build()
    if "--check" in sys.argv:
        sys.exit(1 if check() else 0)
    import collections
    terms = [a for a in sys.argv[1:] if not a.startswith("-")] or \
            ["piwi", "pax", "helicase", "ubiquitin", "kinase", "binding"]
    print(f"  {'term':11} {'rows':>6} {'subst':>6} {'types':>6} {'top type':>22} {'MB':>7} {'s':>6}")
    for t in terms:
        rows, mb, el = run(t)
        top = rows[:100]
        sub = sum(1 for r in top if t.lower() in ((r[1] or '') + (r[2] or '')).lower())
        cnt = collections.Counter(r[3] for r in top)
        best = cnt.most_common(1)[0] if cnt else ('-', 0)
        print(f"  {t:11} {len(rows):6d} {sub:6d} {len(cnt):6d} "
              f"{best[0][:18] + ' ' + str(best[1]):>22} {mb:7.1f} {el:6.2f}")
