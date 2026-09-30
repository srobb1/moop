#!/usr/bin/bash
#SBATCH --job-name=diamond_all
#SBATCH --cpus-per-task=16
#SBATCH --mem=48gb
#SBATCH --time=24:00:00
#SBATCH --partition=compute
#SBATCH --output=/n/sci/SCI-004223-SBGENOMES/slurm_logs/diamond/%x_%A_%a.out
#SBATCH --error=/n/sci/SCI-004223-SBGENOMES/slurm_logs/diamond/%x_%A_%a.err
#
# One database of a run_diamond_all.sh array: DIAMOND blastp of a gene set's clean
# query fasta against $DB_BASE/<db>/current/peptide.dmnd, where <db> is line
# $SLURM_ARRAY_TASK_ID of $DB_LIST. Writes the annotation pipeline's layout and names:
#   $OUT_BASE/<db>/diamond_results.tsv.gz, db_version.txt, diamond_version.txt
#
# Columns 1-4 are the annotation pipeline's; 5-17 are the ones naming v2 uses for
# identity/coverage/bitscore filtering (notes/NAMING_V2_PLAN.md). Without them
# assign_gene_names_v2.pl falls back to E-value only.
#
# Everything arrives through --export from run_diamond_all.sh. Nothing is sourced
# relative to this file: SLURM runs a copy of it from its spool directory, so
# $(dirname "${BASH_SOURCE[0]}") is not scripts/ here.
#
# Don't sbatch this directly — run run_diamond_all.sh.

set -euo pipefail

: "${OUT_BASE:?}" "${QUERY_FASTA:?}" "${DB_BASE:?}" "${DB_LIST:?}"
[ -s "$QUERY_FASTA" ] || { echo "ERROR: missing $QUERY_FASTA"; exit 1; }

DB_NAME=$(sed -n "${SLURM_ARRAY_TASK_ID}p" "$DB_LIST")
[ -n "$DB_NAME" ] || { echo "ERROR: no database on line $SLURM_ARRAY_TASK_ID of $DB_LIST"; exit 1; }
DB_DIR=$DB_BASE/$DB_NAME/current
OUT_DIR=$OUT_BASE/$DB_NAME
OUT_FILE=$OUT_DIR/diamond_results.tsv
COLUMNS=(qseqid sseqid stitle evalue pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp)
mkdir -p "$OUT_DIR"
echo "DIAMOND: $QUERY_FASTA vs $DB_NAME"

## as the annotation pipeline runs it: 25 targets against human (enough genes to see the
## ortholog behind its paralogs, and a second gene for the paralog-tie rule), 5 against the
## rest. The pipeline searches CANONICAL human proteins (one per gene); this stand-in searches
## $DB_DIR/peptide.dmnd as it is, so with all isoforms 25 targets cover fewer genes.
MAX_TARGETS=5
[ "$DB_NAME" = ENS_homo_sapiens ] && MAX_TARGETS=25

module purge
module load diamond/2.1.6
TMP=/scratch/$USER/tmp/diamond/${SLURM_ARRAY_JOB_ID}_${SLURM_ARRAY_TASK_ID}
mkdir -p "$TMP"

diamond blastp --ultra-sensitive \
  --threads "$SLURM_CPUS_PER_TASK" \
  --evalue 1e-5 \
  --tmpdir "$TMP" \
  --query "$QUERY_FASTA" \
  --db "$DB_DIR/peptide" \
  --out "$OUT_FILE.body" \
  --max-target-seqs "$MAX_TARGETS" \
  --outfmt 6 "${COLUMNS[@]}"

## header line, as the annotation pipeline writes (naming v2 reads it for scovhsp)
{ (IFS=$'\t'; echo "${COLUMNS[*]}"); cat "$OUT_FILE.body"; } > "$OUT_FILE" && rm "$OUT_FILE.body"
gzip -f "$OUT_FILE"

## "<db>\t<version>", the annotation pipeline's format: the db's own db_version.txt
## when it has one (Ensembl release, RefSeq accession), else the dated directory
## that current points at (UNIPROT_sprot/20260720)
VERSION=$(head -1 "$DB_DIR/db_version.txt" 2>/dev/null || true)
[ -n "$VERSION" ] || VERSION=$(basename "$(readlink -f "$DB_DIR")")
printf '%s\t%s\n' "$DB_NAME" "$VERSION" > "$OUT_DIR/db_version.txt"
diamond --version > "$OUT_DIR/diamond_version.txt"
rm -rf "$TMP"

echo "Done: $OUT_FILE.gz"
