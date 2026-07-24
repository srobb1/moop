#!/usr/bin/bash
#SBATCH --job-name=diamond_sprot
#SBATCH --cpus-per-task=32
#SBATCH --mem=48gb
#SBATCH --time=24:00:00
#SBATCH --partition=compute
#SBATCH --output=/n/sci/SCI-004223-SBGENOMES/slurm_logs/diamond/%x_%j.out
#SBATCH --error=/n/sci/SCI-004223-SBGENOMES/slurm_logs/diamond/%x_%j.err
#
# DIAMOND blastp vs UniProt Swiss-Prot for one genomes_v2 gene-set. Writes
# straight into the ANNOTATIONS/<run>/<org>/<assembly>/<geneset>/ layout that
# moop_process_genome_data_v2.sbatch expects (diamond_blast/UNIPROT_sprot/tophit.tsv.gz),
# instead of the old species-keyed genomes/<species>/current/ layout that
# scripts/sbatch/diamond.sbatch in the pipeline repo hardcodes.
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

BASE=/n/sci/SCI-004223-SBGENOMES
QUERY=${QUERY_FASTA:-$BASE/genomes_v2/$ORG/$ASSEMBLY/$GENESET/protein.aa.fa}
DB=$BASE/db/UNIPROT_sprot/current/peptide

[ -s "$QUERY" ] || { echo "ERROR: missing $QUERY"; exit 1; }

ANNOTATIONS_DIR=${ANNOTATIONS_DIR:-$BASE/dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21}
OUT_DIR=$ANNOTATIONS_DIR/$ORG/$ASSEMBLY/$GENESET/diamond_blast/UNIPROT_sprot
mkdir -p "$OUT_DIR"
OUT_FILE=$OUT_DIR/tophit.tsv

module purge
module load diamond/2.1.6
mkdir -p /scratch/"$USER"/tmp/diamond

diamond blastp --ultra-sensitive \
  --threads "$SLURM_CPUS_PER_TASK" \
  --evalue 1e-5 \
  --tmpdir /scratch/"$USER"/tmp \
  --query "$QUERY" \
  --db "$DB" \
  --out "$OUT_FILE" \
  --max-target-seqs 1 \
  --outfmt 6 qseqid sseqid stitle evalue

diamond --version > "$OUT_DIR/diamond_version.txt"
basename "$(readlink -f "$BASE/db/UNIPROT_sprot/current")" > "$OUT_DIR/db_version.txt"
gzip -f "$OUT_FILE"

echo "Done: $OUT_DIR/tophit.tsv.gz"
