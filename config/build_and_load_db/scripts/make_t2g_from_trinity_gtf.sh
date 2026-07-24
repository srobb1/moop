#!/usr/bin/bash
# Generate transcript2gene.txt and protein2gene.txt from a Trinity-style GTF.
# Gene IDs are derived by stripping the _i\d+ isoform suffix from transcript IDs.
#
# Usage: make_t2g_from_trinity_gtf.sh <gtf> <protein_fasta> [output_dir]
#   output_dir defaults to the current directory

set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <gtf> <protein_fasta> [output_dir]" >&2
  exit 1
fi

GTF=$1
PROTEIN_FA=$2
OUTDIR=${3:-.}

mkdir -p "$OUTDIR"

awk '$3=="transcript"' "$GTF" \
  | grep -oP 'transcript_id "[^"]+"' \
  | grep -oP '"[^"]+"' \
  | tr -d '"' \
  | perl -ne 'chomp; ($gene = $_) =~ s/_i\d+$//; print "$_\t$gene\n"' \
  > "$OUTDIR/transcript2gene.txt"

echo "Written: $OUTDIR/transcript2gene.txt ($(wc -l < "$OUTDIR/transcript2gene.txt") transcripts)"

grep '^>' "$PROTEIN_FA" \
  | perl -ne 'chomp; s/^>//; s/ .*//; $prot = $_; ($gene = $prot) =~ s/\.p\d+$//; $gene =~ s/_i\d+$//; print "$prot\t$gene\n"' \
  > "$OUTDIR/protein2gene.txt"

echo "Written: $OUTDIR/protein2gene.txt ($(wc -l < "$OUTDIR/protein2gene.txt") proteins)"
