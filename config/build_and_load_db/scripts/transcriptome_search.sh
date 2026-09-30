#!/usr/bin/bash
#SBATCH --job-name=transcriptome_search
#SBATCH --cpus-per-task=16
#SBATCH --mem=64gb
#SBATCH --time=12:00:00
#SBATCH --output=transcriptome_search_%j.log
# transcriptome_search.sh -- a gene set's proteins searched against the SAME species' experimental
# transcriptome, for geneset_config.yaml transcript_hits: (gene naming reports a gene as expressed when
# a transcript matches it; nothing is named from it, and no match is never reported). Run by hand when a
# gene set has a transcriptome; ids need not agree -- the search pairs proteins and transcripts.
#
#   bash scripts/transcriptome_search.sh GENESET_PROTEINS.fa TRANSCRIPTOME.fa OUT_DIR LABEL [THREADS]
#     TRANSCRIPTOME  nucleotide transcripts (e.g. a Trinity assembly; searched translated, as tblastn)
#                    or their predicted ORFs (e.g. TransDecoder .pep; searched as proteins) -- detected
#                    from the sequence; plain or gzipped
#     LABEL          what the transcriptome is, as it will read in the statement, e.g.
#                    "Trinity, adult gill and mantle" -- say the tissues/stages: they are its limits
#     THREADS        default: the job's CPUs under sbatch, else 4
#
# Runs where it is started: `bash transcriptome_search.sh ...` in an interactive allocation, or
# `sbatch transcriptome_search.sh ...` (the #SBATCH lines apply only then).
#
# MMseqs2 easy-search (translated search against nucleotides), E <= 1e-10, >= 80% identity (a match of the
# same gene; naming asks >= 95% over >= 90% of the protein, allowing for assembly and sequencing
# differences), the 5 best transcripts per protein by bitscore. For a nucleotide transcriptome alnlen is in
# nucleotides; qstart/qend (what naming reads) are protein positions. Writes, for gene naming:
#   OUT_DIR/transcript_hits.tsv.gz   "#"-header, then qseqid tseqid pident alnlen mismatch gapopen qstart qend
#                                    tstart tend evalue bits qlen tlen (BLAST tabular order)
#   OUT_DIR/db_version.txt           "LABEL<TAB>md5 of the transcriptome fasta" (naming shows the LABEL)
#   OUT_DIR/mmseqs_version.txt, OUT_DIR/command.txt
# Then in geneset_config.yaml, under the gene set:  transcript_hits: OUT_DIR/transcript_hits.tsv.gz

set -euo pipefail
QUERY=${1:?gene set protein fasta}; TRANSCRIPTOME=${2:?transcriptome fasta}; OUT_DIR=${3:?out dir}; LABEL=${4:?label}
THREADS=${5:-${SLURM_CPUS_PER_TASK:-4}}
for input in "$QUERY" "$TRANSCRIPTOME"; do
  [ -s "$input" ] || { echo "ERROR: missing $input" >&2; exit 1; }
done
case "$LABEL" in *$'\t'*|*$'\n'*) echo "ERROR: LABEL may not contain a tab or newline" >&2; exit 1 ;; esac
mkdir -p "$OUT_DIR"
module load mmseqs2/14-7e284 2>/dev/null || true
command -v mmseqs >/dev/null || { echo "ERROR: mmseqs not in PATH (module load mmseqs2/14-7e284)" >&2; exit 1; }

## nucleotide or protein: the share of A/C/G/T/U/N in the first 100,000 sequence characters (pipefail off in
## here: head closing the pipe early kills cat/grep with SIGPIPE, which would stop the script silently)
KIND=$(set +o pipefail; (if [[ "$TRANSCRIPTOME" == *.gz ]]; then zcat "$TRANSCRIPTOME"; else cat "$TRANSCRIPTOME"; fi) 2>/dev/null \
  | grep -v '^>' | tr -d '\n\r' | head -c 100000 \
  | awk '{ total = length($0); n = gsub(/[ACGTUNacgtun]/, ""); print (total && n / total >= 0.9) ? "nucleotide" : "protein" }')
echo "transcriptome: $TRANSCRIPTOME ($KIND)"

COLUMNS=query,target,pident,alnlen,mismatch,gapopen,qstart,qend,tstart,tend,evalue,bits,qlen,tlen
OUT_FILE=$OUT_DIR/transcript_hits.tsv
TMP=$OUT_DIR/tmp.$$
mkdir -p "$TMP"
trap 'rm -rf "$TMP" "$OUT_FILE.body"' EXIT

## --search-type 2: translated (protein query vs nucleotide target); 1: amino acids
SEARCH_TYPE=1
[ "$KIND" = nucleotide ] && SEARCH_TYPE=2
mmseqs easy-search "$QUERY" "$TRANSCRIPTOME" "$OUT_FILE.body" "$TMP" \
  --search-type "$SEARCH_TYPE" -e 1e-10 --min-seq-id 0.8 \
  --format-output "$COLUMNS" --threads "$THREADS" -v 1

## the 5 best transcripts per protein, by bitscore -- kept here, not with --max-accept, which stops at the first
## 5 accepted in search order: 9 of 300 Congeria proteins lost their 100% match to a near-identical paralog
{ echo "# ${COLUMNS//,/$'\t'}"; sort -t $'\t' -k1,1 -k12,12gr "$OUT_FILE.body" | awk -F'\t' '++seen[$1] <= 5'; } > "$OUT_FILE"
gzip -f "$OUT_FILE"
printf '%s\t%s\n' "$LABEL" "md5:$( (if [[ "$TRANSCRIPTOME" == *.gz ]]; then zcat "$TRANSCRIPTOME"; else cat "$TRANSCRIPTOME"; fi) | md5sum | cut -d' ' -f1)" \
  > "$OUT_DIR/db_version.txt"
mmseqs version > "$OUT_DIR/mmseqs_version.txt"
{
  echo "date: $(date '+%Y-%m-%d %H:%M')"
  echo "command: $0 $*"
  echo "query: $QUERY (md5 $(md5sum "$QUERY" | cut -d' ' -f1))"
  echo "transcriptome: $TRANSCRIPTOME ($KIND)"
  echo "mmseqs easy-search --search-type $SEARCH_TYPE -e 1e-10 --min-seq-id 0.8 --format-output $COLUMNS; 5 best per protein by bits"
} > "$OUT_DIR/command.txt"
echo "Done: $OUT_FILE.gz ($(zcat "$OUT_FILE.gz" | grep -v '^#' | cut -f1 | sort -u | wc -l) proteins with a transcript match)"
