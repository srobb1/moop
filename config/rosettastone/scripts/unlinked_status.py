#!/usr/bin/env python3
"""Why each sequence of one set did not map to a reference gene, and where it aligned.

  unlinked_status.py --name SET --sam SET.sam --gff SET.sorted.gff --overlaps SET.<mode>.overlaps.txt
                     --exon-hits IDS [--cds cds.nt.fa [--genes-gff genes.gff]] > SET.unlinked.tsv
  unlinked_status.py --name SET --probe-fasta SET.nt --probe-bed SET.bed > SET.unlinked.tsv

Writes  id  reason  seqid  start  end  strand  (no header; 1-based inclusive coordinates of the
primary alignment, empty when it did not align) for every sequence that step 04 does NOT keep
(kept: >= 100 bp of CDS overlap or one exact CDS exon; probes: any exon overlap).
The crosswalk build drops IDs that reach a gene some other way (another reference, an alias).

reason:
  did_not_align      no alignment to the reference genome
  not_coding         the transcript has no CDS (sets with a cds.nt.fa only): coding genes only
  cds_not_aligned    aligned, but not over its CDS (only UTR aligned)
  no_gene_at_locus   aligned where the reference has no gene (no exon overlap at all)
  no_cds             aligned over a gene's exons, but not its CDS (UTR, or a non-coding gene)
  cds_overlap_short  overlaps a gene's CDS, by less than 100 bp and no exact CDS exon
"""
import argparse
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from clip_to_cds import cds_parents  # noqa: E402

CIGAR_REF = re.compile(r'(\d+)([MDN=X])')
TARGET = re.compile(r'Target=(\S+)')


def coding_ids(cds_fasta, genes_gff):
    """transcript IDs that have a CDS, named as clip_to_cds.py names them"""
    parent = cds_parents(genes_gff) if genes_gff else {}
    ids = set()
    with open(cds_fasta) as fh:
        for line in fh:
            if line.startswith('>'):
                cid = line[1:].split()[0]
                ids.add(cid[:-4] if cid.endswith(':cds') else parent.get(cid, cid))
    return ids


def sam_primary(sam):
    """id -> (seqid, start, end, strand) of the primary alignment, or None if unaligned"""
    loc = {}
    with open(sam) as fh:
        for line in fh:
            if line.startswith('@'):
                continue
            f = line.split('\t', 6)
            flag = int(f[1])
            if flag & 0x900:              # secondary / supplementary
                continue
            if flag & 4:
                loc.setdefault(f[0], None)
                continue
            start = int(f[3])
            span = sum(int(n) for n, _ in CIGAR_REF.findall(f[5]))
            loc[f[0]] = (f[2], start, start + span - 1, '-' if flag & 16 else '+')
    return loc


def overlaps(path):
    """id -> (max_overlap, exon_overlap) over all genes; and the ids step 04 keeps"""
    best, kept = {}, set()
    with open(path) as fh:
        for line in fh:
            f = line.rstrip('\n').split('\t')
            if f[0] == 'gene_id' or len(f) < 5:
                continue
            tid = re.sub(r'^(gene|transcript):', '', f[1])
            mo, eo = int(f[3]), int(f[4])
            if mo >= 100 or eo >= 1:
                kept.add(tid)
            best[tid] = max(best.get(tid, (0, 0)), (mo, eo))
    return best, kept


def read_ids(path):
    with open(path) as fh:
        return {line.strip() for line in fh if line.strip()}


def gff_ids(gff):
    ids = set()
    with open(gff) as fh:
        for line in fh:
            m = TARGET.search(line)
            if m:
                ids.add(m.group(1))
    return ids


def fasta_ids(path):
    with open(path) as fh:
        return [line[1:].split()[0] for line in fh if line.startswith('>')]


def write(out, tid, reason, loc):
    if loc:
        out.write(f'{tid}\t{reason}\t{loc[0]}\t{loc[1]}\t{loc[2]}\t{loc[3]}\n')
    else:
        out.write(f'{tid}\t{reason}\t\t\t\t\n')


def probes(args, out):
    best = {}                                   # probe -> (score, location)
    with open(args.probe_bed) as fh:
        for line in fh:
            f = line.rstrip('\n').split('\t')
            score = float(f[4]) if len(f) > 4 and f[4] not in ('', '.') else 0
            loc = (f[0], int(f[1]) + 1, int(f[2]), f[5] if len(f) > 5 else '')
            if f[3] not in best or score > best[f[3]][0]:
                best[f[3]] = (score, loc)
    kept = set()
    if args.overlaps:
        with open(args.overlaps) as fh:
            kept = {line.split('\t')[1] for line in fh if '\t' in line}
    n = 0
    for pid in fasta_ids(args.probe_fasta):
        if pid in kept:
            continue
        hit = best.get(pid)
        write(out, pid, 'no_gene_at_locus' if hit else 'did_not_align', hit[1] if hit else None)
        n += 1
    return n


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--name', required=True)
    ap.add_argument('--sam')
    ap.add_argument('--gff')
    ap.add_argument('--overlaps')
    ap.add_argument('--exon-hits', help='IDs with a block over a reference exon (bedtools intersect -u, '
                    'run by step 04: python3 may run in a container without bedtools)')
    ap.add_argument('--cds', help="the set's cds.nt.fa when its GFF was clipped to the CDS")
    ap.add_argument('--genes-gff')
    ap.add_argument('--probe-fasta')
    ap.add_argument('--probe-bed')
    args = ap.parse_args()
    out = sys.stdout

    if args.probe_fasta:
        n = probes(args, out)
        sys.stderr.write(f'unlinked_status {args.name}: {n} probes not kept\n')
        return

    for need in ('sam', 'gff', 'overlaps', 'exon_hits'):
        if not getattr(args, need):
            ap.error(f'--{need.replace("_", "-")} is required for a transcript set')
    loc = sam_primary(args.sam)
    best, kept = overlaps(args.overlaps)
    ehits = read_ids(args.exon_hits)
    coding = coding_ids(args.cds, args.genes_gff) if args.cds else None
    in_gff = gff_ids(args.gff) if coding is not None else None

    counts = {}
    for tid, where in loc.items():
        if tid in kept:
            continue
        if where is None:
            reason = 'did_not_align'
        elif tid in best:
            reason = 'cds_overlap_short'
        elif coding is not None and tid not in coding:
            reason = 'not_coding'
        elif in_gff is not None and tid not in in_gff:
            reason = 'cds_not_aligned'
        elif tid in ehits:
            reason = 'no_cds'
        else:
            reason = 'no_gene_at_locus'
        counts[reason] = counts.get(reason, 0) + 1
        write(out, tid, reason, where)
    sys.stderr.write(f'unlinked_status {args.name}: {len(loc)} sequences, {len(kept)} kept; '
                     + ', '.join(f'{k} {v}' for k, v in sorted(counts.items())) + '\n')


if __name__ == '__main__':
    main()
