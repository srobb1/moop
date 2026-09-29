#!/usr/bin/bash
#SBATCH --job-name=closest_rbh
#SBATCH --cpus-per-task=16
#SBATCH --mem=32gb
#SBATCH --time=12:00:00
#SBATCH --output=closest_rbh_%j.log
# closest_species_rbh.sh -- MMseqs2 reciprocal best hits between a gene set and ONE other
# species' proteome, for a closest_species entry of geneset_config.yaml (its rbh: path). Run by
# hand, now and then, when a gene set needs a closest species the annotation pipeline does not
# search.
#
#   bash scripts/closest_species_rbh.sh GENESET_PROTEINS.fa PARTNER_PROTEINS.fa OUT_DIR LABEL [THREADS]
#     LABEL   the partner, as it will read in evidence text (e.g. "RefSeq jaNemVect1")
#     THREADS default: the job's CPUs under sbatch, else 4
#
# Runs where it is started: `bash closest_species_*.sh ...` in an interactive allocation, or
# `sbatch closest_species_*.sh ...` (the #SBATCH lines above apply only then; nothing is sourced
# relative to this file, so SLURM's spool copy runs the same).
#
# As the annotation pipeline runs it (mmseqs easy-rbh, MMseqs2 14-7e284, default settings), with
# the same 12 columns first -- so parse_MMSEQS_RBH_to_MOOP_TSV.pl reads it unchanged -- and four
# more at the end: qlen tlen qcov tcov (coverage as fractions), so gene naming can apply its
# full-length rule without the partner fasta. Writes:
#   OUT_DIR/rbh_mmseq_results.tsv   header + one row per reciprocal best hit
#   OUT_DIR/db_version.txt          "LABEL<TAB>md5 of the partner fasta"
#   OUT_DIR/rbh_mmseq_version.txt
#   OUT_DIR/command.txt             the command, the inputs' md5s, the date

set -euo pipefail
QUERY=${1:?query fasta}; PARTNER=${2:?partner fasta}; OUT_DIR=${3:?out dir}; LABEL=${4:?label}; THREADS=${5:-${SLURM_CPUS_PER_TASK:-4}}
for input in "$QUERY" "$PARTNER"; do
  [ -s "$input" ] || { echo "ERROR: missing $input" >&2; exit 1; }
done
mkdir -p "$OUT_DIR"
module load mmseqs2/14-7e284 2>/dev/null || true
command -v mmseqs >/dev/null || { echo "ERROR: mmseqs not in PATH (module load mmseqs2/14-7e284)" >&2; exit 1; }

# easy-rbh's default third column is fident (identity as a fraction, 0.586) although the
# pipeline's header calls it pident; ask for fident and keep the pipeline's header, so the file
# is column-for-column the pipeline's (--format-output pident would give a percentage)
FORMAT=query,target,fident,alnlen,mismatch,gapopen,qstart,qend,tstart,tend,evalue,bits,qlen,tlen,qcov,tcov
HEADER=query,target,pident,alnlen,mismatch,gapopen,qstart,qend,tstart,tend,evalue,bits,qlen,tlen,qcov,tcov
OUT_FILE=$OUT_DIR/rbh_mmseq_results.tsv
TMP=$OUT_DIR/tmp.$$
trap 'rm -rf "$TMP" "$OUT_FILE.body"' EXIT

mmseqs easy-rbh "$QUERY" "$PARTNER" "$OUT_FILE.body" "$TMP" --threads "$THREADS" --format-output "$FORMAT" > "$OUT_DIR/mmseqs.log" 2>&1
{ echo "$HEADER" | tr ',' '\t'; cat "$OUT_FILE.body"; } > "$OUT_FILE"
printf '%s\t%s\n' "$LABEL" "md5:$(md5sum "$PARTNER" | cut -d' ' -f1)" > "$OUT_DIR/db_version.txt"
echo "mmseqs $(mmseqs version)" > "$OUT_DIR/rbh_mmseq_version.txt"
{
  echo "date: $(date '+%Y-%m-%d %H:%M')"
  echo "command: $0 $*"
  echo "query: $QUERY (md5 $(md5sum "$QUERY" | cut -d' ' -f1))"
  echo "partner: $PARTNER (md5 $(md5sum "$PARTNER" | cut -d' ' -f1))"
  echo "mmseqs easy-rbh QUERY PARTNER OUT TMP --threads $THREADS --format-output $FORMAT"
} > "$OUT_DIR/command.txt"
echo "Done: $OUT_FILE ($(tail -n +2 "$OUT_FILE" | wc -l) reciprocal best hits)"
