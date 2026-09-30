#!/usr/bin/env python3
"""Model coverage of every InterProScan match, from the JSON: how much of each signature's model the protein aligns to.

  python3 scripts/interproscan_model_coverage.py --json interproscan_results.json[.gz] \\
      --panther-hmm-lengths moop/panther/hmm_lengths.tsv --out model_coverage.tsv

The TSV gives only protein coordinates, so gene naming could only count protein residues against a
model's length. With insertions that overestimates (Congeria, 2026-09-30: PTHR10133 100% by residues,
57% of the model; 320 of 1,935 PANTHER matches at >= 80% by residues are below 80% of the model).
The JSON gives the model coordinates (hmmStart/hmmEnd) of every location; this writes, per protein,
analysis and signature, the model positions covered by all its locations together.

Written for the analyses whose model coordinates are real:
  Pfam, Gene3D, FunFam, NCBIfam, PIRSF, SFLD -- hmmLength from the JSON
  PANTHER -- the JSON's hmmLength is 0; the family model's length comes from --panther-hmm-lengths
            (the coordinates are on the family model: none of 2,755 checked locations passes its end)
Left out: SMART (every location reports the whole model, 1 to its length), PIRSR (site rules, not in
the TSV), and analyses with no
model coordinates (CDD, PROSITE profiles and patterns, PRINTS, HAMAP, SUPERFAMILY, Coils, MobiDB).
A match missing from the output has no model coverage to check.

Output (tab-separated, after "#" provenance lines):
  protein  analysis  signature  model_length  model_coverage_pct
analysis and signature are spelled as in the InterProScan TSV (signature without a version suffix).
"""

import argparse, datetime, os, sys

from interproscan_json import read_results

# JSON library -> the TSV's analysis name, for the analyses with real model coordinates
ANALYSIS = {
    'PFAM': 'Pfam', 'PANTHER': 'PANTHER', 'GENE3D': 'Gene3D', 'FUNFAM': 'FunFam', 'NCBIFAM': 'NCBIfam',
    'PIRSF': 'PIRSF', 'SFLD': 'SFLD',
}


def union_length(intervals):
    """positions covered by a list of (start, end) intervals, counting overlaps once"""
    total, reached = 0, 0
    for start, end in sorted(intervals):
        if end > reached:
            total += end - max(start, reached + 1) + 1
            reached = end
    return total


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--json', required=True)
    parser.add_argument('--panther-hmm-lengths', required=True)
    parser.add_argument('--out', required=True)
    args = parser.parse_args()

    panther_length = {}
    with open(args.panther_hmm_lengths) as handle:
        for line in handle:
            fields = line.rstrip('\n').split('\t')
            if len(fields) > 1 and fields[1].isdigit():
                panther_length[fields[0]] = int(fields[1])

    # the JSON is read one protein result at a time (a gene set's is gigabytes)
    version, results = read_results(args.json)
    covered = {}   # (protein, analysis, signature) -> [model length, [(model start, model end), ...]]
    skipped = {}
    for result in results:
        proteins = [xref['id'] for xref in result.get('xref', [])]
        for match in result['matches']:
            library = match['signature']['signatureLibraryRelease']['library']
            analysis = ANALYSIS.get(library)
            if analysis is None:
                skipped[library] = skipped.get(library, 0) + 1
                continue
            signature = match['signature']['accession'].split('.')[0] if library == 'PFAM' else match['signature']['accession']
            for location in match.get('locations', []):
                start, end = location.get('hmmStart'), location.get('hmmEnd')
                length = panther_length.get(signature) if library == 'PANTHER' else location.get('hmmLength')
                if not start or not end or not length:
                    continue
                for protein in proteins:
                    entry = covered.setdefault((protein, analysis, signature), [length, []])
                    entry[1].append((start, end))

    with open(args.out, 'w') as out:
        out.write(f"# InterProScan model coverage from {os.path.abspath(args.json)} "
                  f"(InterProScan {version}); PANTHER model lengths {os.path.abspath(args.panther_hmm_lengths)}; "
                  f"{datetime.date.today().isoformat()}\n")
        out.write('# not written (no real model coordinates): '
                  + ', '.join(f'{library} {count}' for library, count in sorted(skipped.items())) + '\n')
        out.write('protein\tanalysis\tsignature\tmodel_length\tmodel_coverage_pct\n')
        for (protein, analysis, signature) in sorted(covered):
            length, intervals = covered[(protein, analysis, signature)]
            percent = min(100, round(100 * union_length(intervals) / length))
            out.write(f'{protein}\t{analysis}\t{signature}\t{length}\t{percent}\n')
    print(f'{len(covered)} matches with model coverage written to {args.out}', file=sys.stderr)


if __name__ == '__main__':
    main()
