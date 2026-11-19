#!/usr/bin/env python3
"""Curated anatomy IDs (Planosphere/PAGE records) that are in NONE of the crosswalk's sequence files.

Each distinct ID in anatomy_expression.sqlite is put in one of three buckets:
  gene      -- the crosswalk `map` reaches a gene (directly, or for a GENE id through its transcripts,
               the same rule the site uses: lib/crosswalk.php moop_crosswalk_gene_links)
  unlinked  -- in our data, but reaches no gene; the `unlinked` table says why
               (for a gene id: its transcripts are in `unlinked`)
  missing   -- in neither: the sequence was never in any FASTA we gave the crosswalk
Only `missing` is written out, grouped by ID set and paper, so the source files can be found
and added to the crosswalk run.

    python3 -I scripts/curated_ids_missing_report.py [organism] > out.tsv     (summary on stderr)
Read-only; ~2 s on Schmidtea_mediterranea.
"""
import collections
import re
import sqlite3
import sys

ORGANISM = sys.argv[1] if len(sys.argv) > 1 else 'Schmidtea_mediterranea'
ROOT = f'/var/www/html/moop/organisms/{ORGANISM}/'

# First match wins; the last one catches everything left (gene names like "Smed-wnt11-1").
ID_SETS = [
    ('dd_Smed_v6 (PlanMine)',                          r'^dd_smed_v6_'),
    ('dd_Smed_v4 (PlanMine)',                          r'^dd_smed_v4_'),
    ('Contig (EST/assembly contig; source differs by paper)', r'^contig\d+\*?$'),
    ('SmedASXL (asexual transcriptome)',               r'^smedasxl_?\d+$'),
    ('OX_Smed (Kao 2013, Oxford)',                     r'^ox_smed_'),
    ('GCR (Smed gene/clone IDs)',                      r'^gcr\d+$'),
    ('microRNA (not in any mRNA FASTA; miRBase)',      r'^(sme-)?(mir|let|bantam|lin-?4)'),
    ('GenBank / ENA accession',                        r'^[a-z]{1,2}\d{5,8}(\.\d+)?$'),
    ('SMU (Smed UniGene-style)',                       r'^smu\d+$'),
    ('mk (lcl|mk... local IDs)',                       r'mk\d'),
    ('Clone ID (H./NB./D. library clones, PL plates)', r'^((h|nb|d)\.\d+\.\d+[a-z]?|pl\d{5}[a-z]\d[a-z]\d{2})$'),
    ('Old genome gene model (SMED_ / v31.)',           r'^(smed_\d+_\d+|v31\.\d+)$'),
    ('Data error: a PMID in the ID field',             r'^\d{6,8}$'),
    ('Gene NAME, not an ID (needs name lookup)',       r'.'),
]


def open_ro(name):
    return sqlite3.connect(f'file:{ROOT}{name}?mode=ro', uri=True)


def id_set(acc_lc):
    for name, rx in ID_SETS:
        if re.search(rx, acc_lc):
            return name
    return ID_SETS[-1][0]


def main():
    anatomy, crosswalk, organism = (open_ro(f) for f in
                                    ('anatomy_expression.sqlite', 'crosswalk.sqlite', 'organism.sqlite'))
    records = collections.defaultdict(list)          # accession_lc -> [(accession as written, pmid)]
    for acc, acc_lc, pmid in anatomy.execute('SELECT accession, accession_lc, pmid FROM curation'):
        records[acc_lc].append((acc, pmid))
    papers = {pmid: (authors or f'PMID {pmid}', year or '')
              for pmid, authors, year in anatomy.execute('SELECT pmid, authors, year FROM publication')}
    in_map = {r[0] for r in crosswalk.execute('SELECT DISTINCT accession_lc FROM map')}
    in_unlinked = {r[0] for r in crosswalk.execute('SELECT DISTINCT accession_lc FROM unlinked')}
    children = collections.defaultdict(list)         # gene uniquename (lc) -> its transcripts (lc)
    for gene, child in organism.execute(
            "SELECT p.feature_uniquename, c.feature_uniquename FROM feature p "
            "JOIN feature c ON c.parent_feature_id = p.feature_id WHERE p.feature_type = 'gene'"):
        children[gene.lower()].append(child.lower())

    buckets = collections.Counter()
    missing = []
    for acc_lc in records:
        kids = children.get(acc_lc, [])
        if acc_lc in in_map or any(k in in_map for k in kids):
            buckets['gene'] += 1
        elif acc_lc in in_unlinked or any(k in in_unlinked for k in kids):
            buckets['unlinked'] += 1
        else:
            buckets['missing'] += 1
            missing.append(acc_lc)

    def paper_label(pmid):
        authors, year = papers.get(pmid, (f'PMID {pmid}', ''))
        return f'{authors} {year} (PMID {pmid})'.replace('  ', ' ')

    rows = []
    for acc_lc in missing:
        pmids = sorted({pmid for _, pmid in records[acc_lc]})
        rows.append((id_set(acc_lc), records[acc_lc][0][0], len(records[acc_lc]),
                     '; '.join(paper_label(p) for p in pmids)))
    order = [name for name, _ in ID_SETS]
    rows.sort(key=lambda r: (order.index(r[0]), r[3], r[1].lower()))

    print('id_set\taccession\trecords\tpapers')
    for row in rows:
        print('\t'.join(map(str, row)))

    log = sys.stderr
    print(f'{ORGANISM}: {len(records)} distinct IDs -- gene {buckets["gene"]}, '
          f'unlinked {buckets["unlinked"]}, missing {buckets["missing"]} '
          f'({sum(r[2] for r in rows)} records)', file=log)
    for name in order:
        these = [r for r in rows if r[0] == name]
        if not these:
            continue
        per_paper = collections.Counter(p for r in these for p in r[3].split('; '))
        print(f'  {name}: {len(these)} IDs, {sum(r[2] for r in these)} records', file=log)
        for paper, n in per_paper.most_common():
            print(f'      {n:4d}  {paper}', file=log)


if __name__ == '__main__':
    main()
