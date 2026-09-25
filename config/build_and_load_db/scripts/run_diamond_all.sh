#!/usr/bin/bash
## Interactive launcher for run_diamond_all.sbatch: DIAMOND blastp of one genomes/v2
## gene set against every protein database in $BASE/db (every <db>/current/peptide.dmnd),
## one SLURM array task per database. A stand-in for the annotation pipeline's own
## DIAMOND step, for testing a gene set before that pipeline has run it.
##
## Prompts you to pick (or create) the ANNOTATIONS run directory to write into, builds
## a stop-codon-clean query fasta (make_clean_query_fasta.sh), then submits the array.
## Output is the annotation pipeline's layout, which process_one_geneset.sh reads:
##   <run>/<org>/<assembly>/<geneset>/diamond/<db>/diamond_results.tsv.gz
## To build from it, point the build at the run:  ANNOTATIONS=<run> sbatch ...
##
## Databases in SKIP_DBS are left out: UNIPROT_trembl (85 GB, days per gene set) and
## plasmid_db (a contamination screen, not homologs). Override with, e.g.,
##   SKIP_DBS="UNIPROT_trembl" bash run_diamond_all.sh ...
## or run a chosen few with  ONLY_DBS="UNIPROT_sprot ENS_homo_sapiens" bash run_diamond_all.sh ...
##
## Usage: bash run_diamond_all.sh <organism> <assembly> <geneset>

set -euo pipefail

ORG=${1:-}; ASSEMBLY=${2:-}; GENESET=${3:-}
[ -n "$GENESET" ] || { echo "Usage: $0 <organism> <assembly> <geneset>"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/paths.sh"

DB_BASE=$REF_DB
SKIP_DBS=${SKIP_DBS-UNIPROT_trembl plasmid_db}
ONLY_DBS=${ONLY_DBS:-}

DBS=()
for dmnd in "$DB_BASE"/*/current/peptide.dmnd; do
  db=$(basename "$(dirname "$(dirname "$dmnd")")")
  if [ -n "$ONLY_DBS" ]; then
    [[ " $ONLY_DBS " == *" $db "* ]] || continue
  else
    [[ " $SKIP_DBS " == *" $db "* ]] && continue
  fi
  DBS+=("$db")
done
[ ${#DBS[@]} -gt 0 ] || { echo "ERROR: no databases selected under $DB_BASE"; exit 1; }

echo "DIAMOND databases (${#DBS[@]}):" >&2
printf '  %s\n' "${DBS[@]}" >&2

ANNOTATIONS_DIR=$(bash "$SCRIPT_DIR/pick_annotations_run.sh")
QUERY_FASTA=$(bash "$SCRIPT_DIR/make_clean_query_fasta.sh" "$ORG" "$ASSEMBLY" "$GENESET")

OUT_BASE=$ANNOTATIONS_DIR/$ORG/$ASSEMBLY/$GENESET/diamond
mkdir -p "$OUT_BASE"
## the array task reads its database from line $SLURM_ARRAY_TASK_ID of this list
DB_LIST=$OUT_BASE/.db_list.txt
printf '%s\n' "${DBS[@]}" > "$DB_LIST"

sbatch --array=1-${#DBS[@]} \
  --export=ALL,OUT_BASE="$OUT_BASE",QUERY_FASTA="$QUERY_FASTA",DB_BASE="$DB_BASE",DB_LIST="$DB_LIST" \
  "$SCRIPT_DIR/run_diamond_all.sbatch"
