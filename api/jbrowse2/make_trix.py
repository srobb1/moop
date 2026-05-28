#!/usr/bin/env python3
"""
make_trix.py — Generate JBrowse 2 trix name-index files (.ix + .ixx) from GFF3.

No external dependencies — Python 3.6+ standard library only.

Usage:
    python3 make_trix.py genes.gff3.gz
    python3 make_trix.py genes.gff3.gz --out /srv/tracks/genes.gff3.gz
    python3 make_trix.py genes.gff3.gz --track-id my_genes_track
    python3 make_trix.py genes.gff3.gz --attributes Name,ID,gene_name,Alias

Output:
    PREFIX.ix   — sorted text index (one word per line, tab-separated entries)
    PREFIX.ixx  — sparse seek-index into .ix for binary search

The output prefix defaults to the input filename (so genes.gff3.gz → genes.gff3.gz.ix).
Matches the format produced by `jbrowse text-index` from @jbrowse/cli.

Default indexed attributes: Name, ID
All values are lowercased as the lookup key; original case is preserved in entries.
GFF3 1-based start coordinates are converted to 0-based for JBrowse2 compatibility.
"""

import argparse
import gzip
import os
import sys
import urllib.parse
from collections import defaultdict

# Chunk size between ixx entries — matches jbrowse text-index (~64 KB)
_CHUNK_BYTES = 65536
# Characters of the word stored in each ixx line (rest is padded with spaces)
_IXX_PREFIX_LEN = 7


# ── GFF3 parsing ─────────────────────────────────────────────────────────────

def _open(path):
    if path.endswith('.gz'):
        return gzip.open(path, 'rt', encoding='utf-8', errors='replace')
    return open(path, 'r', encoding='utf-8', errors='replace')


def _parse_attrs(attr_str):
    """Return {key: [val, ...]} for a GFF3 attribute column."""
    out = {}
    if not attr_str or attr_str == '.':
        return out
    for field in attr_str.split(';'):
        field = field.strip()
        if not field:
            continue
        if '=' in field:
            k, _, v = field.partition('=')
            out[k] = [urllib.parse.unquote(x) for x in v.split(',')]
        else:
            out[field] = [field]
    return out


# ── Entry generation ──────────────────────────────────────────────────────────

def _loc(ref, gff_start, gff_end):
    """
    Build the locstring stored in the ix entry.
    JBrowse2 uses 0-based start (GFF3 start − 1), 1-based end, colon URL-encoded.
    """
    ref_safe = ref.replace('%', '%25').replace('|', '%7C')
    return f"{ref_safe}%3A{int(gff_start) - 1}..{gff_end}"


def _safe(s):
    """Escape characters that would break the pipe-delimited ix entry format."""
    return s.replace('%', '%25').replace('"', '%22').replace('|', '%7C')


def collect_entries(gff_path, attr_keys):
    """
    Read the GFF3 and yield (word, locstring, name, feature_id) for every
    attribute value to be indexed.  word is the lowercase search key.
    """
    with _open(gff_path) as fh:
        for line in fh:
            if not line or line[0] == '#':
                continue
            cols = line.rstrip('\n').split('\t')
            if len(cols) < 9:
                continue

            ref, _, ftype, start, end = cols[0], cols[1], cols[2], cols[3], cols[4]
            attrs = _parse_attrs(cols[8])

            # Canonical name and ID for this feature (used in every entry)
            names = attrs.get('Name', [])
            ids   = attrs.get('ID',   [])
            name  = names[0] if names else (ids[0] if ids else None)
            fid   = ids[0]   if ids   else (names[0] if names else None)
            if name is None and fid is None:
                continue

            try:
                locstring = _loc(ref, start, end)
            except (ValueError, IndexError):
                continue

            display_name = name or fid
            feature_id   = fid  or name

            seen = set()
            for key in attr_keys:
                for val in attrs.get(key, []):
                    if not val:
                        continue
                    word = val.lower()
                    if word not in seen:
                        seen.add(word)
                        yield word, locstring, display_name, feature_id


# ── Index building ────────────────────────────────────────────────────────────

def build_sorted_index(entries):
    """Group by word, return sorted list of (word, [(locstring, name, fid), ...])."""
    by_word = defaultdict(list)
    for word, locstring, name, fid in entries:
        by_word[word].append((locstring, name, fid))
    return sorted(by_word.items())


# ── Writing ───────────────────────────────────────────────────────────────────

def write_ix_ixx(sorted_index, track_id, ix_path, ixx_path):
    """
    Write the .ix file and collect ixx seek points, then write the .ixx file.

    ix line format (one word per line):
        word<TAB>["loc"|"trackId"|"Name"|"ID"],1 ["loc"|"trackId"|"Name"|"ID"],2 ...\n

    ixx line format (one entry per ~64 KB chunk):
        <7-char word prefix, space-padded><10-char uppercase hex byte offset>\n
    """
    ixx_points = []          # (word, byte_offset_in_ix)
    next_chunk_threshold = 0

    with open(ix_path, 'w', encoding='utf-8') as ix:
        byte_offset = 0
        for word, locs in sorted_index:
            # Record a seek point at the start of each chunk
            if byte_offset >= next_chunk_threshold:
                ixx_points.append((word, byte_offset))
                next_chunk_threshold = byte_offset + _CHUNK_BYTES

            entries = []
            for i, (locstring, name, fid) in enumerate(locs, 1):
                entries.append(
                    f'["{locstring}"|"{track_id}"|"{_safe(name)}"|"{_safe(fid)}"],{i}'
                )

            line = f"{word}\t{' '.join(entries)}\n"
            ix.write(line)
            byte_offset += len(line.encode('utf-8'))

    with open(ixx_path, 'w', encoding='utf-8') as ixx:
        for word, offset in ixx_points:
            prefix = word[:_IXX_PREFIX_LEN].ljust(_IXX_PREFIX_LEN)
            ixx.write(f"{prefix}{offset:010X}\n")


# ── CLI ───────────────────────────────────────────────────────────────────────

def main():
    ap = argparse.ArgumentParser(
        description='Generate JBrowse 2 trix name-index files from a GFF3 file.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__.split('\n\n', 1)[1],
    )
    ap.add_argument('gff', help='Input GFF3 file (plain or .gz)')
    ap.add_argument(
        '--out',
        help='Output prefix for .ix / .ixx files (default: same as input filename)',
    )
    ap.add_argument(
        '--track-id',
        help='Track ID embedded in each index entry (default: input filename)',
    )
    ap.add_argument(
        '--attributes',
        default='Name,ID',
        help='Comma-separated GFF3 attributes to index (default: Name,ID). '
             'Example: --attributes Name,ID,Alias,gene_name',
    )
    args = ap.parse_args()

    out_prefix = args.out or os.path.basename(args.gff)
    track_id   = args.track_id or os.path.basename(args.gff)
    attr_keys  = [a.strip() for a in args.attributes.split(',') if a.strip()]

    ix_path  = out_prefix + '.ix'
    ixx_path = out_prefix + '.ixx'

    print(f"Input:      {args.gff}", file=sys.stderr)
    print(f"Attributes: {', '.join(attr_keys)}", file=sys.stderr)
    print(f"Track ID:   {track_id}", file=sys.stderr)
    print(f"Output:     {ix_path}  {ixx_path}", file=sys.stderr)
    print('Reading GFF3 ...', file=sys.stderr)

    raw = list(collect_entries(args.gff, attr_keys))
    print(f"  {len(raw)} raw entries", file=sys.stderr)

    sorted_index = build_sorted_index(raw)
    print(f"  {len(sorted_index)} unique search terms", file=sys.stderr)

    print('Writing index ...', file=sys.stderr)
    write_ix_ixx(sorted_index, track_id, ix_path, ixx_path)

    ix_kb  = os.path.getsize(ix_path)  // 1024
    ixx_b  = os.path.getsize(ixx_path)
    print(f"Done.  {ix_path} ({ix_kb} KB)  {ixx_path} ({ixx_b} B)", file=sys.stderr)


if __name__ == '__main__':
    main()
