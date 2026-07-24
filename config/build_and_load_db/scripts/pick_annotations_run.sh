#!/usr/bin/bash
## Interactively pick (or create) an ANNOTATIONS run directory for manually
## started analyses (DIAMOND, InterProScan, ...). Lists existing SBGENOMES_*
## run dirs newest-to-oldest, or lets you create a new one.
##
## This is only for the manual analysis-generation scripts. It does NOT
## touch moop_process_genome_data_v2.sbatch, which keeps using its own
## hardcoded ANNOTATIONS run dir for now (see run-dir memory note).
##
## Prompts go to stderr; the chosen absolute path is the only thing printed
## to stdout, so callers can do:  RUN_DIR=$(bash pick_annotations_run.sh)

set -euo pipefail

ANNOTATIONS_BASE=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/annotations

mapfile -t RUNS < <(ls -1dt "$ANNOTATIONS_BASE"/SBGENOMES_* 2>/dev/null)

{
  echo "Existing ANNOTATIONS run directories (newest to oldest):"
  n=1
  for r in "${RUNS[@]}"; do
    printf '  %d) %s\n' "$n" "$(basename "$r")"
    n=$((n + 1))
  done
  echo "  n) create a new run directory"
  printf 'Select a number, or "n" for new: '
} >&2

read -r CHOICE

if [[ "$CHOICE" == "n" || "$CHOICE" == "N" ]]; then
  DEFAULT_NAME="SBGENOMES_$(date +%Y-%m-%d)"
  printf 'New run directory name [%s]: ' "$DEFAULT_NAME" >&2
  read -r NEW_NAME
  NEW_NAME=${NEW_NAME:-$DEFAULT_NAME}
  RUN_DIR=$ANNOTATIONS_BASE/$NEW_NAME
  mkdir -p "$RUN_DIR"
else
  IDX=$((CHOICE - 1))
  if [[ $IDX -lt 0 || $IDX -ge ${#RUNS[@]} ]]; then
    echo "ERROR: invalid selection" >&2
    exit 1
  fi
  RUN_DIR=${RUNS[$IDX]}
fi

echo "$RUN_DIR"
