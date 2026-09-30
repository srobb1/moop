#!/usr/bin/env python3
"""Merge InterProScan chunk JSONs into one document, one chunk in memory at a time.

  python3 scripts/merge_interproscan_json.py --out interproscan_results.json.gz CHUNK.json [CHUNK.json ...]

run_interproscan_geneset.sh merges the 100 chunk JSONs of an array run by loading all of them at
once (~70 MB each, ~7 GB for a 44,000-protein gene set, many times that as Python objects). This
writes the same document -- the first chunk's top-level fields, then every chunk's "results" in
the order given -- but streams: each chunk is read, its results written out, and freed before the
next. The output is gzipped when its name ends in .gz. Every chunk must come from the same
InterProScan version; a chunk from another version (a stale temp dir) stops the merge.
"""

import argparse, gzip, json, os, sys


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--out', required=True)
    parser.add_argument('chunks', nargs='+')
    args = parser.parse_args()

    tmp = args.out + '.tmp'
    opener = gzip.open if args.out.endswith('.gz') else open
    version, total = None, 0
    with opener(tmp, 'wt') as out:
        for index, chunk in enumerate(args.chunks):
            with open(chunk) as handle:
                document = json.load(handle)
            chunk_version = document.get('interproscan-version')
            if version is None:
                version = chunk_version
                header = {key: value for key, value in document.items() if key != 'results'}
                # the top-level fields, then an open "results" list the chunks are written into
                head = json.dumps(header)
                out.write(head[:-1] + (', ' if header else '') + '"results": [')
            elif chunk_version != version:
                out.close()
                os.remove(tmp)
                sys.exit(f'ERROR: {chunk} is InterProScan {chunk_version}, the others {version}; nothing written')
            for result in document['results']:
                out.write((',' if total else '') + json.dumps(result))
                total += 1
            del document
            print(f'{index + 1}/{len(args.chunks)} chunks, {total} proteins', file=sys.stderr)
        out.write(']}')
    os.replace(tmp, args.out)
    print(f'{total} proteins from {len(args.chunks)} chunks (InterProScan {version}) written to {args.out}', file=sys.stderr)


if __name__ == '__main__':
    main()
