#!/usr/bin/bash
#SBATCH --job-name=diamond_sprot
#SBATCH --cpus-per-task=32
#SBATCH --mem=48gb
#SBATCH --time=24:00:00
#SBATCH --partition=compute
#SBATCH --output=/n/sci/SCI-004223-SBGENOMES/slurm_logs/diamond/%x_%j.out
#SBATCH --error=/n/sci/SCI-004223-SBGENOMES/slurm_logs/diamond/%x_%j.err
#
# DIAMOND blastp vs UniProt Swiss-Prot for one genomes/v2 gene-set: a stand-in for
# the annotation pipeline's own DIAMOND step, for testing a gene set before that
# pipeline has run it. Writes the same layout and file names the annotation pipeline
# does, which is what process_one_geneset.sh reads:
#   <run>/<org>/<assembly>/<geneset>/diamond/UNIPROT_sprot/diamond_results.tsv.gz
# To build from it, point the build at the run:  ANNOTATIONS=<run> sbatch ...
#
# Columns 1-4 are the annotation pipeline's; 5-17 are the ones naming v2 uses for
# identity/coverage/bitscore filtering (notes/NAMING_V2_PLAN.md). Without them
# assign_gene_names_v2.pl falls back to E-value only.
#
# Don't sbatch this directly — run run_diamond_sprot.sh instead, which lets
# you pick which ANNOTATIONS run dir to write into, builds a stop-codon-clean
# query fasta, and submits this with both passed through.
#
# Usage: sbatch --export=ALL,ANNOTATIONS_DIR=<run_dir>,QUERY_FASTA=<clean_fasta> \
#          run_diamond_sprot.sbatch <organism> <assembly> <geneset>

set -euo pipefail

ORG=$1; ASSEMBLY=$2; GENESET=$3
[ -n "$GENESET" ] || { echo "Usage: $0 <organism> <assembly> <geneset>"; exit 1; }

# GENOMES and ANNOTATIONS from the single definition, so this writes where the build
# will later look for it. Writing to a stale tree is silent: the build reports
# MISS_DIAMOND and there is nothing to say the results went somewhere else.
# Read from SCRIPTS_DIR (exported by run_diamond_sprot.sh), not $(dirname "${BASH_SOURCE[0]}"):
# SLURM runs a copy of this file from its spool directory, where paths.sh does not exist.
source "${SCRIPTS_DIR:?run via run_diamond_sprot.sh}/paths.sh"

BASE=/n/sci/SCI-004223-SBGENOMES
QUERY=${QUERY_FASTA:-$GENOMES/$ORG/$ASSEMBLY/$GENESET/protein.aa.fa}
DB=$BASE/db/UNIPROT_sprot/current/peptide

[ -s "$QUERY" ] || { echo "ERROR: missing $QUERY"; exit 1; }

ANNOTATIONS_DIR=${ANNOTATIONS_DIR:-$ANNOTATIONS}
OUT_DIR=$ANNOTATIONS_DIR/$ORG/$ASSEMBLY/$GENESET/diamond/UNIPROT_sprot
mkdir -p "$OUT_DIR"
OUT_FILE=$OUT_DIR/diamond_results.tsv
COLUMNS=(qseqid sseqid stitle evalue pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp)

module purge
module load diamond/2.1.6
mkdir -p /scratch/"$USER"/tmp/diamond

diamond blastp --ultra-sensitive \
  --threads "$SLURM_CPUS_PER_TASK" \
  --evalue 1e-5 \
  --tmpdir /scratch/"$USER"/tmp \
  --query "$QUERY" \
  --db "$DB" \
  --out "$OUT_FILE.body" \
  --max-target-seqs 1 \
  --outfmt 6 "${COLUMNS[@]}"

## header line, as the annotation pipeline writes (naming v2 reads it for scovhsp)
{ (IFS=$'\t'; echo "${COLUMNS[*]}"); cat "$OUT_FILE.body"; } > "$OUT_FILE" && rm "$OUT_FILE.body"

diamond --version > "$OUT_DIR/diamond_version.txt"
printf 'UNIPROT_sprot\t%s\n' "$(basename "$(readlink -f "$BASE/db/UNIPROT_sprot/current")")" > "$OUT_DIR/db_version.txt"
gzip -f "$OUT_FILE"

echo "Done: $OUT_FILE.gz"
