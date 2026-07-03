#!/usr/bin/env python3
"""
generate_gff_mv_commands.py — generate shell commands to move bare assembly/gff/
directories into assembly/{geneset}/gff/ on the tracks server.

Reads tracks_files.txt (3-level directory listing) to find assemblies that have
both a bare gff/ directory AND a sibling geneset directory, then outputs an
SSH script to move the gff contents into the geneset directory.

Usage:
  python3 scripts/generate_gff_mv_commands.py [--tracks-listing tracks_files.txt] [--out move_gff_to_geneset.sh]

Run the generated script from cerebro:
  bash move_gff_to_geneset.sh
"""

import argparse
import sys
from collections import defaultdict
from pathlib import Path

TRACKS_BASE = '/var/www/privatehtml/moop/data/tracks'

# Directory names that are NOT gene sets — skip when looking for a geneset sibling
DATA_TYPE_DIRS = {
    'RNAseq', 'RNASEQ', 'RNA_Seq', 'rna_seq',
    'whole_genome_alignment', 'chipSeq', 'atac-seq',
    'piRNA_cluster', 'DNA_Seq', 'ISO_Seq', 'RAD_Seq',
    'methylation', 'ATAC', 'ChIP',
    'gff',  # the directory we're moving
}


def looks_like_geneset(name):
    """Heuristic: is this directory name a gene set rather than a data type?"""
    if name in DATA_TYPE_DIRS:
        return False
    # Gene set patterns: SIMR_YYYY-MM-DD, RS_NNN, RS_YYYY_MM, Release-NNN, v1, etc.
    import re
    patterns = [
        r'^SIMR_\d{4}',
        r'^RS_\d',
        r'^Release-\d',
        r'^Release_\d',
        r'_geneset$',
        r'^v\d+$',
        r'^\d{4}$',  # bare year
    ]
    for p in patterns:
        if re.search(p, name):
            return True
    return False


def parse_tracks_listing(path):
    """
    Parse a flat file listing of directory paths (one per line).
    Returns: dict  org -> assembly -> set of level-3 directory names
    """
    tree = defaultdict(lambda: defaultdict(set))

    with open(path) as fh:
        for line in fh:
            line = line.strip()
            if not line:
                continue
            # Expect: /var/www/privatehtml/moop/data/tracks/{org}/{asm}/{subdir}
            if not line.startswith(TRACKS_BASE + '/'):
                continue
            rel = line[len(TRACKS_BASE) + 1:]
            parts = rel.split('/')
            if len(parts) < 3:
                continue
            org, asm, subdir = parts[0], parts[1], parts[2]
            tree[org][asm].add(subdir)

    return tree


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--tracks-listing', default='tracks_files.txt')
    ap.add_argument('--out', default='move_gff_to_geneset.sh')
    args = ap.parse_args()

    tree = parse_tracks_listing(args.tracks_listing)

    moves = []         # (org, asm, geneset, src_gff_dir, dst_gff_dir)
    no_geneset = []    # (org, asm) — has bare gff but no sibling geneset
    warnings = []

    for org in sorted(tree):
        for asm in sorted(tree[org]):
            subdirs = tree[org][asm]
            if 'gff' not in subdirs:
                continue  # no bare gff dir to move

            geneset_dirs = [d for d in subdirs if looks_like_geneset(d)]

            if not geneset_dirs:
                no_geneset.append((org, asm))
                continue

            if len(geneset_dirs) > 1:
                warnings.append(f'# WARN: multiple geneset dirs for {org}/{asm}: {geneset_dirs} — using first')
                geneset_dirs = sorted(geneset_dirs)[:1]

            geneset = geneset_dirs[0]
            src = f'{TRACKS_BASE}/{org}/{asm}/gff'
            dst = f'{TRACKS_BASE}/{org}/{asm}/{geneset}/gff'
            moves.append((org, asm, geneset, src, dst))

    with open(args.out, 'w') as fh:
        fh.write('#!/usr/bin/env bash\n')
        fh.write('# Move bare assembly/gff/ directories into assembly/{geneset}/gff/ on tracks.\n')
        fh.write('#\n')
        fh.write('# The {geneset}/gff/ directory may already exist (created by scp_evidence.sh).\n')
        fh.write('# Contents of the bare gff/ (old GFF files) are moved alongside the evidence/ subdir.\n')
        fh.write('#\n')
        fh.write('# Run from cerebro: bash move_gff_to_geneset.sh\n')
        fh.write('# Requires: ssh ControlMaster open for "tracks" host alias.\n')
        fh.write('#   ssh -MNf tracks\n')
        fh.write('\nset -euo pipefail\n\n')

        if warnings:
            for w in warnings:
                fh.write(w + '\n')
            fh.write('\n')

        for org, asm, geneset, src, dst in moves:
            fh.write(f'# {org}/{asm}  →  {geneset}/gff/\n')
            # dst may already exist (from evidence mkdir). Use mv contents + rmdir.
            # shopt -s nullglob guards against empty gff dir.
            fh.write(
                f'ssh tracks bash -c \''
                f'shopt -s nullglob; '
                f'mkdir -p {dst}; '
                f'mv {src}/* {dst}/; '
                f'rmdir {src}\'\n'
            )
            fh.write('\n')

        if no_geneset:
            fh.write('\n# ── Assemblies with bare gff/ but NO sibling geneset dir ─────────────────\n')
            fh.write('# These cannot be moved automatically. Investigate and handle manually.\n')
            for org, asm in no_geneset:
                fh.write(f'# {org}/{asm}\n')

    print(f'Wrote {args.out}', file=sys.stderr)
    print(f'  move commands : {len(moves)}', file=sys.stderr)
    if no_geneset:
        print(f'\nAssemblies with bare gff/ but NO geneset sibling ({len(no_geneset)}):',
              file=sys.stderr)
        for org, asm in no_geneset:
            print(f'  {org}/{asm}', file=sys.stderr)


if __name__ == '__main__':
    main()
