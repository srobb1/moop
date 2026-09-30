#!/usr/bin/env python3
"""Pfam domains of every reviewed human protein (UniProtKB/Swiss-Prot), by HGNC gene, for gene naming.

  python3 scripts/fetch_human_domains.py --out moop/uniprot/human_pfam.tsv.gz

Gene naming says in a name's provenance whether the gene here has the Pfam domains of the human gene
it is named after ("has all 3 of ALPHA's Pfam domains"; "lacks PF00017"). The human side comes from
UniProt: accession, HGNC ids and Pfam accessions of each reviewed human entry. Fetched page by page
(the single-request stream was cut off twice for this ~20,000-entry query) with retries, and checked
against the total UniProt reports; an incomplete download is an error, never a partial file.

Output (gzip, tab-separated, after "#" lines giving the UniProt release and date):
  accession  hgnc_ids (;-separated)  pfam_ids (;-separated)
"""

import argparse, datetime, gzip, os, re, sys, time, urllib.parse, urllib.request

QUERY = 'reviewed:true AND organism_id:9606'
URL = ('https://rest.uniprot.org/uniprotkb/search?query=' + urllib.parse.quote(QUERY)
       + '&fields=accession,xref_hgnc,xref_pfam&format=tsv&size=500')


def fetch(url, tries=5):
    for attempt in range(1, tries + 1):
        try:
            with urllib.request.urlopen(url, timeout=300) as response:
                return response.read().decode('utf-8'), response.headers
        except Exception as error:   # network errors, timeouts, 5xx
            if attempt == tries:
                raise
            print(f'retry {attempt} after: {error}', file=sys.stderr)
            time.sleep(10 * attempt)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--out', required=True)
    args = parser.parse_args()

    rows, url, total, release = [], URL, None, None
    while url:
        text, headers = fetch(url)
        total = total or int(headers.get('X-Total-Results', '0'))
        release = release or headers.get('X-UniProt-Release', '?')
        lines = text.rstrip('\n').split('\n')
        rows.extend(line for line in lines[1:] if line)
        link = headers.get('Link', '')
        match = re.search(r'<([^>]+)>;\s*rel="next"', link)
        url = match.group(1) if match else None
        print(f'{len(rows)} of {total}', file=sys.stderr)
    if not total or len(rows) != total:
        sys.exit(f'ERROR: {len(rows)} entries downloaded, UniProt reports {total}; nothing written')

    tmp = args.out + '.tmp'
    with gzip.open(tmp, 'wt') as out:
        out.write(f'# UniProt release {release}: reviewed human entries ({total}), accession, HGNC ids, Pfam ids; '
                  f'fetched {datetime.date.today().isoformat()} from rest.uniprot.org\n')
        out.write('accession\thgnc_ids\tpfam_ids\n')
        for row in sorted(rows):
            accession, hgnc, pfam = (row.split('\t') + ['', ''])[:3]
            out.write(f"{accession}\t{hgnc.strip(';')}\t{pfam.strip(';')}\n")
    os.replace(tmp, args.out)
    print(f'{total} entries written to {args.out} (UniProt {release})', file=sys.stderr)


if __name__ == '__main__':
    main()
