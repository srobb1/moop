#!/usr/bin/env python3
"""check_crosswalk.py -- do a crosswalk's names line up with MOOP's gene sets?

Usage: check_crosswalk.py CROSSWALK.sqlite ORGANISM.sqlite [--own 0.9] [--partial 0.1]

MOOP's gene set names (organism.sqlite gene_set.gene_set_name) are the names everything else
must use. For every hub (by its ref_ids) and every source (by its accessions) this finds the MOOP
gene set holding most of its IDs:

  FAIL     >= --own of the IDs are features of gene set GS, but the name is not GS (a hub) or
           GS / GS_transcripts (a source). The site would label or link it wrongly.
  WARN     between --partial and --own of the IDs are in GS: a set that only partly matches a
           MOOP gene set (e.g. an older release of it). Linking it to GS gives dead links.
  WARN     two sources are both owned by the same gene set (duplicate columns).
  OK       named after the gene set that owns it, or no MOOP gene set holds its IDs (an outside set).
  INFO     MOOP gene sets that no hub or source covers.

Read-only. Exit status 1 if anything FAILs, else 0.
"""
import argparse
import sqlite3
import sys


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("crosswalk")
    ap.add_argument("organism_db")
    ap.add_argument("--own", type=float, default=0.9)
    ap.add_argument("--partial", type=float, default=0.1)
    a = ap.parse_args()

    db = sqlite3.connect(f"file:{a.crosswalk}?mode=ro", uri=True)
    db.execute(f"ATTACH 'file:{a.organism_db}?mode=ro' AS o")
    fmt = db.execute("SELECT value FROM meta WHERE key='format'").fetchone()
    print(f"crosswalk format: {fmt[0] if fmt else 'MISSING'}")

    db.executescript("""
        CREATE TEMP TABLE f AS
          SELECT DISTINCT f.feature_uniquename AS id, gs.gene_set_name AS gs
            FROM o.feature f JOIN o.gene_set gs USING (gene_set_id)
           WHERE f.feature_type IN ('gene', 'mRNA', 'transcript');
        CREATE INDEX temp.f_id ON f (id);
        CREATE TEMP TABLE items AS
          SELECT DISTINCT 'hub' AS kind, hub AS name, ref_id AS id FROM main.map
          UNION
          SELECT DISTINCT 'source', source, accession FROM main.map;
        CREATE INDEX temp.items_id ON items (id);
    """)
    totals = {(k, n): c for k, n, c in db.execute("SELECT kind, name, COUNT(*) FROM items GROUP BY 1, 2")}
    hits = {}
    for k, n, gs, c in db.execute("""SELECT i.kind, i.name, f.gs, COUNT(DISTINCT i.id)
                                       FROM items i JOIN f ON f.id = i.id GROUP BY 1, 2, 3"""):
        hits.setdefault((k, n), []).append((c, gs))
    moop_sets = [r[0] for r in db.execute("SELECT gene_set_name FROM o.gene_set ORDER BY 1")]

    fails = 0
    owners = {}
    rows = []
    for (kind, name), total in sorted(totals.items()):
        best_c, best_gs = max(hits.get((kind, name), [(0, None)]))
        frac = best_c / total
        ok_names = {best_gs} if kind == "hub" else {best_gs, f"{best_gs}_transcripts"}
        if best_gs and frac >= a.own:
            owners.setdefault(best_gs, []).append(f"{kind} {name}")
            if name in ok_names:
                status, note = "OK", f"MOOP gene set {best_gs}"
            else:
                status, note = "FAIL", f"IDs are MOOP gene set {best_gs}: name it " + " or ".join(sorted(ok_names))
                fails += 1
        elif best_gs and frac >= a.partial:
            status, note = "WARN", f"only partly MOOP gene set {best_gs} (dead links if linked to it)"
        else:
            status, note = "OK", "not a MOOP gene set (outside set)"
        rows.append((status, kind, name, total, best_c, frac, note))

    print(f"\n{'STATUS':6} {'KIND':6} {'NAME':34} {'IDS':>9} {'IN_MOOP':>9} {'FRAC':>6}  NOTE")
    for status, kind, name, total, c, frac, note in sorted(rows, key=lambda r: ("FAIL WARN OK".split().index(r[0]), r[1], r[2])):
        print(f"{status:6} {kind:6} {name:34} {total:9d} {c:9d} {frac:6.1%}  {note}")

    print()
    for gs, who in sorted(owners.items()):
        srcs = [w for w in who if w.startswith("source ")]
        if len(srcs) > 1 and not all(s in (f"source {gs}", f"source {gs}_transcripts") for s in srcs):
            print(f"WARN   gene set {gs} is covered by several sources: {', '.join(srcs)}")
    for gs in moop_sets:
        if gs not in owners:
            print(f"INFO   MOOP gene set {gs}: no hub or source in the crosswalk")
    print(f"\n{fails} naming failure(s)")
    return 1 if fails else 0


if __name__ == "__main__":
    sys.exit(main())
