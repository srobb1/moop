#!/usr/bin/bash
#SBATCH --job-name=closest_diamond
#SBATCH --cpus-per-task=16
#SBATCH --mem=32gb
#SBATCH --time=12:00:00
#SBATCH --output=closest_diamond_%j.log
# closest_species_diamond.sh -- DIAMOND search of a gene set against ONE other species' proteome,
# for a closest_species entry of geneset_config.yaml (its diamond: path). Run by hand, now and
# then, when a gene set needs a closest species the annotation pipeline does not search.
#
#   bash scripts/closest_species_diamond.sh GENESET_PROTEINS.fa PARTNER_PROTEINS.fa OUT_DIR LABEL [THREADS]
#     LABEL   the partner, as it will read in evidence text (e.g. "RefSeq jaNemVect1")
#     THREADS default: the job's CPUs under sbatch, else 4
#
# Runs where it is started: `bash closest_species_*.sh ...` in an interactive allocation, or
# `sbatch closest_species_*.sh ...` (the #SBATCH lines above apply only then; nothing is sourced
# relative to this file, so SLURM's spool copy runs the same).
#
# Exactly as the annotation pipeline runs DIAMOND (run_diamond_all.sbatch): 2.1.6,
# --ultra-sensitive, E <= 1e-5, the 17 naming columns with a header line (query and subject
# coverage), except --max-target-seqs 5 (so a second partner gene is seen; paralog ties).
# Writes the pipeline's layout, which gene naming reads:
#   OUT_DIR/diamond_results.tsv.gz   the hits
#   OUT_DIR/db_version.txt           "LABEL<TAB>md5 of the partner fasta"
#   OUT_DIR/diamond_version.txt
#   OUT_DIR/command.txt              the command, the inputs' md5s, the date
# The partner database is built in OUT_DIR and removed afterwards.

set -euo pipefail
QUERY=${1:?query fasta}; PARTNER=${2:?partner fasta}; OUT_DIR=${3:?out dir}; LABEL=${4:?label}; THREADS=${5:-${SLURM_CPUS_PER_TASK:-4}}
for input in "$QUERY" "$PARTNER"; do
  [ -s "$input" ] || { echo "ERROR: missing $input" >&2; exit 1; }
done
mkdir -p "$OUT_DIR"
module load diamond/2.1.6 2>/dev/null || true
command -v diamond >/dev/null || { echo "ERROR: diamond not in PATH (module load diamond/2.1.6)" >&2; exit 1; }

COLUMNS=(qseqid sseqid stitle evalue pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp)
OUT_FILE=$OUT_DIR/diamond_results.tsv
TMP=$OUT_DIR/tmp.$$
mkdir -p "$TMP"
trap 'rm -rf "$TMP" "$OUT_DIR/partner.dmnd" "$OUT_FILE.body"' EXIT

diamond makedb --in "$PARTNER" --db "$OUT_DIR/partner" --threads "$THREADS" --quiet
diamond blastp --ultra-sensitive \
  --threads "$THREADS" \
  --evalue 1e-5 \
  --tmpdir "$TMP" \
  --query "$QUERY" \
  --db "$OUT_DIR/partner" \
  --out "$OUT_FILE.body" \
  --max-target-seqs 5 \
  --outfmt 6 "${COLUMNS[@]}"

{ (IFS=$'\t'; echo "${COLUMNS[*]}"); cat "$OUT_FILE.body"; } > "$OUT_FILE"
gzip -f "$OUT_FILE"
printf '%s\t%s\n' "$LABEL" "md5:$(md5sum "$PARTNER" | cut -d' ' -f1)" > "$OUT_DIR/db_version.txt"
diamond --version > "$OUT_DIR/diamond_version.txt"
{
  echo "date: $(date '+%Y-%m-%d %H:%M')"
  echo "command: $0 $*"
  echo "query: $QUERY (md5 $(md5sum "$QUERY" | cut -d' ' -f1))"
  echo "partner: $PARTNER (md5 $(md5sum "$PARTNER" | cut -d' ' -f1))"
  echo "diamond blastp --ultra-sensitive --evalue 1e-5 --max-target-seqs 5 --outfmt 6 ${COLUMNS[*]}"
} > "$OUT_DIR/command.txt"
echo "Done: $OUT_FILE.gz ($(zcat "$OUT_FILE.gz" | tail -n +2 | cut -f1 | sort -u | wc -l) query proteins with a hit)"
