#!/usr/bin/env python3
"""Trim full-transcript alignments (step 02 GFF) to each transcript's coding sequence.

  clip_to_cds.py --transcripts transcript.nt.fa --cds cds.nt.fa [--gff genes.gff] in.gff > out.gff

Step 02 aligns whole transcripts, so their UTRs anchor each one to the right copy of a gene
that has near-identical copies. Overlaps must then be counted on the coding part only: a
long UTR running into a neighbour's gene linked unrelated genes (Nematostella NV2 sox-3 to
NOXRED1, 2026-11-18). This keeps, from every cDNA_match_part block, the part that falls in
the transcript's CDS (Target= gives the block's transcript coordinates), moved to the
matching genome coordinates, and drops the rest.

Tested 2026-11-18 against aligning the CDS alone: CDS alone placed lamprey RefSeq transcripts
on the wrong copy of duplicated genes (1,693 mapped only to another gene, vs 857 here and 858
with the old whole-transcript method), with the same precision on NV2.

The CDS is found by locating the cds.nt.fa sequence (or its reverse complement, for
unstranded assemblies) inside its transcript. CDS IDs are
"<transcript>:cds", or are mapped to their transcript through the CDS lines' Parent= in
--gff (RefSeq cds-XP_... -> XM_..., Ensembl CDS:ENSDARP... -> ENSDART...). Transcripts with no
CDS found (non-coding ones) are dropped: the crosswalk holds coding genes only.

Blocks are colinear runs (indels of < 10 bp merged by minimap2_sam2gff.pl), so the trimmed
ends can be off by the size of such an indel. cDNA_match parent lines are not written; step
03 reads only the parts.
"""
import argparse
import sys


COMP = str.maketrans('ACGTN', 'TGCAN')


def read_fasta(path):
    seqs, name, buf = {}, None, []
    with open(path) as fh:
        for line in fh:
            if line.startswith('>'):
                if name is not None:
                    seqs[name] = ''.join(buf).upper()
                name, buf = line[1:].split()[0], []
            else:
                buf.append(line.strip())
    if name is not None:
        seqs[name] = ''.join(buf).upper()
    return seqs


def locate(t, seq, k=30):
    """(start, end, approximate?) of seq in t, 1-based, or None. Exact match first. A CDS cut
    from the genome can differ from a curated mRNA by a few bases (Danio RefSeq: 5,806 of
    94,668), so then by its first and last k bases, or by one end and its length."""
    i = t.find(seq)
    if i >= 0:
        return i + 1, i + len(seq), 0
    if len(seq) < 2 * k:
        return None
    h, e = t.find(seq[:k]), t.rfind(seq[-k:])
    if h >= 0 and e >= 0 and e + k > h:
        return h + 1, e + k, 1
    if h >= 0:
        return h + 1, min(len(t), h + len(seq)), 1
    if e >= 0:
        return max(1, e + k - len(seq) + 1), e + k, 1
    return None


def cds_parents(gff):
    parent = {}
    with open(gff) as fh:
        for line in fh:
            f = line.rstrip('\n').split('\t')
            if len(f) < 9 or f[2] != 'CDS':
                continue
            attrs = dict(a.split('=', 1) for a in f[8].split(';') if '=' in a)
            if 'ID' in attrs and 'Parent' in attrs:
                parent[attrs['ID']] = attrs['Parent'].split(',')[0]
    return parent


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--transcripts', required=True)
    ap.add_argument('--cds', required=True)
    ap.add_argument('--gff', help="gene set's genes.gff: CDS ID -> transcript via Parent=")
    ap.add_argument('alignments')
    args = ap.parse_args()

    tx = read_fasta(args.transcripts)
    parent = cds_parents(args.gff) if args.gff else {}
    span, missing, approx = {}, 0, 0       # transcript -> (CDS start, end), 1-based transcript coordinates
    for cid, seq in read_fasta(args.cds).items():
        tid = cid[:-4] if cid.endswith(':cds') else parent.get(cid, cid)
        t = tx.get(tid, '')
        hit = locate(t, seq) if seq else None
        if hit is None and seq:  # unstranded assemblies (Trinity): CDS on the reverse strand
            hit = locate(t, seq[::-1].translate(COMP))
        if hit is None:
            missing += 1
            continue
        approx += hit[2]
        span[tid] = hit[:2]

    kept = clipped = 0
    out = sys.stdout
    out.write('##gff-version 3\n')
    with open(args.alignments) as fh:
        for line in fh:
            f = line.rstrip('\n').split('\t')
            if len(f) < 9 or f[2] != 'cDNA_match_part' or 'Target=' not in f[8]:
                continue
            head, target = f[8].split('Target=', 1)
            tid, ts, te = target.split()[:3]
            ts, te = int(ts), int(te)
            if tid not in span:
                continue
            cs, ce = span[tid]
            lo, hi = max(ts, cs), min(te, ce)
            if lo > hi:
                continue
            gs, ge = int(f[3]), int(f[4])
            if f[6] == '-':            # transcript runs right to left on the genome
                gs2, ge2 = gs + (te - hi), ge - (lo - ts)
            else:
                gs2, ge2 = gs + (lo - ts), ge - (te - hi)
            if gs2 > ge2:
                continue
            clipped += (gs2, ge2) != (gs, ge)
            kept += 1
            f[3], f[4], f[8] = str(gs2), str(ge2), f'{head}Target={tid} {lo} {hi}'
            out.write('\t'.join(f) + '\n')
    sys.stderr.write(f'clip_to_cds: CDS found in {len(span)} transcripts ({missing} CDS not found in '
                     f'their transcript, {approx} placed by their ends); {kept} blocks kept, {clipped} of them trimmed\n')


if __name__ == '__main__':
    main()
