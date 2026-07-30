#!/usr/bin/env python3
"""Cold/warm timing of MOOP's real annotation-search query across FTS variants.

Every cold run VERIFIES eviction via mincore before timing. That is the part the
previous round skipped, which is why its cold numbers did not reproduce.
"""
import os, sqlite3, sys, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import evict, resident, pct

T = "/var/www/html/moop/organisms/.fts_test"

# MOOP's real query (lib/database_queries.php::searchFeaturesAndAnnotations),
# minus the optional scope/source filters that a default search does not add.
SQL = """
SELECT f.feature_uniquename, f.feature_name, f.feature_description,
       a.annotation_accession, a.annotation_description,
       fa.score, fa.date, ans.annotation_source_name,
       o.genus, o.species, f.feature_type,
       g.genome_accession, gs.gene_set_name,
       (f.feature_name LIKE ?) AS name_match
FROM feature_annotation_search fas
JOIN feature_annotation  fa  ON fa.feature_annotation_id = fas.rowid
JOIN feature             f   ON f.feature_id             = fa.feature_id
JOIN annotation          a   ON a.annotation_id          = fa.annotation_id
JOIN annotation_source   ans ON ans.annotation_source_id = a.annotation_source_id
JOIN organism            o   ON o.organism_id            = f.organism_id
JOIN gene_set            gs  ON gs.gene_set_id           = f.gene_set_id
JOIN genome              g   ON g.genome_id              = gs.genome_id
WHERE feature_annotation_search MATCH ?
ORDER BY name_match DESC,
         bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0),
         f.feature_uniquename
LIMIT 100
"""


def run(db, match, like):
    con = sqlite3.connect(f"file:{db}?mode=ro", uri=True)
    try:
        t0 = time.perf_counter()
        rows = con.execute(SQL, (like, match)).fetchall()
        return (time.perf_counter() - t0) * 1000.0, len(rows)
    finally:
        con.close()


def timed(db, match, like, label):
    # --- cold: evict, then PROVE it is evicted ---
    evict(db)
    r, t = resident(db)
    residency = pct(r, t)
    if residency > 2.0:
        note = f"  !! eviction incomplete ({residency:.1f}% still resident)"
    else:
        note = ""
    cold_ms, n = run(db, match, like)

    # --- warm: immediately again, same query ---
    warm_ms, _ = run(db, match, like)

    print(f"  {label:34s} cold {cold_ms:9.1f} ms   warm {warm_ms:7.1f} ms   "
          f"rows={n:3d}   (resident before cold: {residency:.1f}%){note}")
    return cold_ms, warm_ms


if __name__ == "__main__":
    term_like = "%kinase%"
    cases = [
        ("base.sqlite",    '"kinase"* AND "binding"*', "base (porter, as deployed)"),
        ("prefix.sqlite",  '"kinase"* AND "binding"*', "prefix='2 3 4'"),
        ("trigram.sqlite", '"kinase" AND "binding"',   "trigram (substring)"),
    ]
    print(f"\n=== annotation search: 'kinase binding' — {os.path.basename(T)} ===")
    for fn, match, label in cases:
        path = os.path.join(T, fn)
        if not os.path.exists(path):
            print(f"  {label}: MISSING")
            continue
        try:
            timed(path, match, term_like, label)
        except Exception as e:
            print(f"  {label:34s} ERROR: {e}")
