#!/usr/bin/bash
## Interactive launcher for run_diamond_sprot.sbatch. Prompts you to pick (or
## create) the ANNOTATIONS run directory to write into, builds a stop-codon-
## clean query fasta (see make_clean_query_fasta.sh), then submits the job.
##
## Usage: bash run_diamond_sprot.sh <organism> <assembly> <geneset>

set -euo pipefail

ORG=${1:-}; ASSEMBLY=${2:-}; GENESET=${3:-}
[ -n "$GENESET" ] || { echo "Usage: $0 <organism> <assembly> <geneset>"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

ANNOTATIONS_DIR=$(bash "$SCRIPT_DIR/pick_annotations_run.sh")
QUERY_FASTA=$(bash "$SCRIPT_DIR/make_clean_query_fasta.sh" "$ORG" "$ASSEMBLY" "$GENESET")

sbatch --export=ALL,ANNOTATIONS_DIR="$ANNOTATIONS_DIR",QUERY_FASTA="$QUERY_FASTA" \
  "$SCRIPT_DIR/run_diamond_sprot.sbatch" "$ORG" "$ASSEMBLY" "$GENESET"
