#!/usr/bin/bash
## Generates a stop-codon-clean protein FASTA for one genomes_v2 gene-set, for
## use as the DIAMOND / InterProScan query. Both tools choke on raw Mender
## output, which can contain internal '.' stop-codon markers (e.g. see
## CCA3t005136001.1 in Chamaeleo_calyptratus/CCA3/MENDER_20260701 — DIAMOND
## died with "Invalid character (.) in sequence").
##
## Uses analysis_parsers/clean_protein_fasta.pl, same stop-codon handling
## already used for OMA input (see /home/smr/sciproj/SBOMA/make_clean_splice):
## sequences with an internal stop are dropped entirely, a single terminal
## stop is trimmed.
##
## Always regenerates (cheap, sub-second, and avoids silently reusing a clean
## fasta built from a stale protein.aa.fa during active gene-set dev).
##
## Prints the clean fasta's absolute path to stdout only; progress/counts go
## to stderr, so callers can do:  QUERY=$(bash make_clean_query_fasta.sh ...)
##
## Usage: bash make_clean_query_fasta.sh <organism> <assembly> <geneset>

set -euo pipefail

ORG=${1:-}; ASSEMBLY=${2:-}; GENESET=${3:-}
[ -n "$GENESET" ] || { echo "Usage: $0 <organism> <assembly> <geneset>" >&2; exit 1; }

BASE=/n/sci/SCI-004223-SBGENOMES
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

RAW_FASTA=$BASE/genomes_v2/$ORG/$ASSEMBLY/$GENESET/protein.aa.fa
[ -s "$RAW_FASTA" ] || { echo "ERROR: missing $RAW_FASTA" >&2; exit 1; }

CLEAN_DIR=/scratch/$USER/tmp/clean_fasta/$ORG/$ASSEMBLY/$GENESET
mkdir -p "$CLEAN_DIR"
CLEAN_FASTA=$CLEAN_DIR/protein.clean.fa

echo "Cleaning $RAW_FASTA -> $CLEAN_FASTA ..." >&2
perl "$SCRIPT_DIR/../analysis_parsers/clean_protein_fasta.pl" "$RAW_FASTA" > "$CLEAN_FASTA.tmp"
mv "$CLEAN_FASTA.tmp" "$CLEAN_FASTA"
N_RAW=$(grep -c '^>' "$RAW_FASTA")
N_CLEAN=$(grep -c '^>' "$CLEAN_FASTA")
echo "  $N_RAW -> $N_CLEAN sequences ($((N_RAW - N_CLEAN)) dropped for an internal stop)" >&2

echo "$CLEAN_FASTA"
