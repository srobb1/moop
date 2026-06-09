#!/usr/bin/env python3
"""
generate_scp_commands.py — match missing GFF files to their genomes-server
paths and output scp commands to run from cerebro.

Usage:
  # 1. On tracks server, get genomes file listing:
  #    ssh genomes find /var/other_data -name "*.gff" -o -name "*.gff3" -o -name "*.gtf" 2>/dev/null | sort > genomes_gffs.txt
  #
  # 2. Run this script:
  python3 scripts/generate_scp_commands.py \
      --missing missing_gffs_report.txt \
      --genomes-find genomes_gffs.txt \
      [--dest-base /var/www/privatehtml/moop/data/tracks] \
      [--out scp_commands.sh]

Output:
  scp_commands.sh  — one scp command per file, ready to run from cerebro
  stderr           — ambiguous matches and unresolved files
"""

import argparse
import os
import re
import sys
from collections import defaultdict


DEST_BASE = '/var/www/privatehtml/moop/data/tracks'


def parse_missing_report(path):
    """
    Parse missing_gffs_report.txt.
    Returns list of (org_dir, dest_dir, rel_filename) tuples.
    dest_dir is None when [UNMAPPED] or [MISSING ACCESSION].
    """
    entries = []
    current_dest = None
    current_org = None

    dest_re = re.compile(r'^## (\S+)\s+→\s+(.+)/gff/$')
    # dest path looks like /var/www/privatehtml/moop/data/tracks/Org/Acc
    # or [UNMAPPED] xxx  or [MISSING ACCESSION] xxx

    with open(path) as fh:
        for line in fh:
            line = line.rstrip('\n')
            m = dest_re.match(line)
            if m:
                current_org = m.group(1)
                dest_raw = m.group(2)
                if dest_raw.startswith('['):
                    current_dest = None  # unmapped / missing accession
                else:
                    current_dest = dest_raw + '/gff'
                continue
            if line.startswith('  ') and current_org:
                fn = line.strip()
                if fn and not fn.startswith('=') and not fn.startswith('Total') and not fn.startswith('Unmapped'):
                    entries.append((current_org, current_dest, fn))

    return entries


def build_genomes_index(path):
    """
    Read the find output from genomes and index by basename.
    Returns dict: basename -> list of full paths
    """
    idx = defaultdict(list)
    with open(path) as fh:
        for line in fh:
            line = line.strip()
            if line:
                idx[os.path.basename(line)].append(line)
    return idx


def best_match(org_dir, rel_fn, candidates):
    """
    Given a list of candidate paths on genomes for a filename, try to pick
    the best one using the org_dir name as a hint.  Returns (path, note).
    note is '' if unambiguous, 'AMBIGUOUS' if multiple equally good matches.
    """
    if len(candidates) == 1:
        return candidates[0], ''

    # Score candidates: prefer paths that contain parts of org_dir
    # e.g. org_dir='Nfur_v10' → prefer paths containing 'Nfur' or 'killifish'
    org_tokens = re.split(r'[_v\d]+', org_dir.lower())
    org_tokens = [t for t in org_tokens if len(t) >= 3]

    def score(p):
        pl = p.lower()
        return sum(1 for t in org_tokens if t in pl)

    scored = sorted(candidates, key=score, reverse=True)
    best_score = score(scored[0])
    top = [p for p in scored if score(p) == best_score]
    if len(top) == 1:
        return top[0], ''
    return top[0], 'AMBIGUOUS(%d candidates)' % len(candidates)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--missing',       default='missing_gffs_report.txt')
    ap.add_argument('--genomes-find',  default='genomes_gffs.txt')
    ap.add_argument('--dest-base',     default=DEST_BASE)
    ap.add_argument('--out',           default='scp_commands.sh')
    ap.add_argument('--only-mapped',   action='store_true',
                    help='Skip UNMAPPED and MISSING ACCESSION organisms')
    args = ap.parse_args()

    entries = parse_missing_report(args.missing)
    print(f'Loaded {len(entries)} missing GFF entries', file=sys.stderr)

    genomes_idx = build_genomes_index(args.genomes_find)
    print(f'Loaded {sum(len(v) for v in genomes_idx.values())} genomes GFF paths '
          f'({len(genomes_idx)} unique basenames)', file=sys.stderr)

    # Group entries by (org_dir, dest_dir)
    by_org = defaultdict(list)
    for org_dir, dest_dir, rel_fn in entries:
        by_org[(org_dir, dest_dir)].append(rel_fn)

    commands = []
    unresolved = []
    ambiguous = []
    skipped_unmapped = 0

    for (org_dir, dest_dir), rel_fns in sorted(by_org.items()):
        if dest_dir is None:
            if args.only_mapped:
                skipped_unmapped += len(rel_fns)
                continue
            # Still try to resolve, but note in output
            dest_placeholder = f'TRACKS_DEST/{org_dir}/gff'
        else:
            dest_placeholder = dest_dir

        commands.append(f'\n# {org_dir} → {dest_placeholder}')

        for rel_fn in sorted(rel_fns):
            basename = os.path.basename(rel_fn)
            subdir   = os.path.dirname(rel_fn)  # e.g. 'ISO_Seq' or ''

            if basename not in genomes_idx:
                unresolved.append((org_dir, rel_fn))
                commands.append(f'# UNRESOLVED: {rel_fn}')
                continue

            candidates = genomes_idx[basename]
            src_path, note = best_match(org_dir, rel_fn, candidates)

            # dest: preserve subdirectory structure from rel_fn
            if subdir:
                dest_path = f'{dest_placeholder}/{subdir}/{basename}'
            else:
                dest_path = f'{dest_placeholder}/{basename}'

            if dest_dir is None:
                cmd = f'# UNMAPPED_ORG: scp genomes:{src_path} tracks:{dest_path}'
            else:
                cmd = f'scp genomes:{src_path} tracks:{dest_path}'

            if note:
                cmd += f'  # {note}'
                ambiguous.append((org_dir, rel_fn, candidates))

            commands.append(cmd)

    # Write output script
    with open(args.out, 'w') as fh:
        fh.write('#!/usr/bin/env bash\n')
        fh.write('# SCP commands to copy GFF files from genomes to tracks\n')
        fh.write('# Run from cerebro. Set up SSH ControlMaster first:\n')
        fh.write('#   ssh -MNf genomes && ssh -MNf tracks\n')
        fh.write('#\n')
        fh.write('# Dry-run to check paths before copying:\n')
        fh.write('#   bash scp_commands.sh --dry-run   (or grep "^scp" and review)\n')
        fh.write('\nset -euo pipefail\n')

        # mkdir commands per dest dir
        dest_dirs = sorted({
            os.path.dirname(line.split('tracks:')[1].rstrip())
            for line in commands
            if line.startswith('scp ') and 'tracks:' in line
        })
        if dest_dirs:
            fh.write('\n# Create destination directories\n')
            for d in dest_dirs:
                fh.write(f'ssh tracks mkdir -p {d}\n')

        for cmd in commands:
            fh.write(cmd + '\n')

    print(f'\nWrote {args.out}', file=sys.stderr)
    print(f'  scp commands:  {sum(1 for c in commands if c.startswith("scp "))}', file=sys.stderr)
    print(f'  unresolved:    {len(unresolved)}', file=sys.stderr)
    print(f'  ambiguous:     {len(ambiguous)}', file=sys.stderr)
    if skipped_unmapped:
        print(f'  skipped (unmapped orgs): {skipped_unmapped}', file=sys.stderr)

    if unresolved:
        print('\nUNRESOLVED (not found in genomes find output):', file=sys.stderr)
        for org, fn in unresolved[:30]:
            print(f'  {org}: {fn}', file=sys.stderr)
        if len(unresolved) > 30:
            print(f'  ... and {len(unresolved)-30} more', file=sys.stderr)

    if ambiguous:
        print('\nAMBIGUOUS (multiple paths matched — review these):', file=sys.stderr)
        for org, fn, cands in ambiguous[:20]:
            print(f'  {org}: {fn}', file=sys.stderr)
            for c in cands:
                print(f'    {c}', file=sys.stderr)


if __name__ == '__main__':
    main()
