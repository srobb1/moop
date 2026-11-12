#!/usr/bin/bash
# Submits OMA runs set up by setup_oma_runs.sh: part 1, then part 2 (afterok part 1), then part 3
# (afterok part 2), per run. Takes the same list file (or paths on the command line).
#
#   bash submit_oma_runs.sh -f LIST
#   bash submit_oma_runs.sh <organism>/<genome>/<geneset> [...]
#
# A run is skipped when it is not set up (no DB/*.fa beside parameters.drw), when parameters.drw still
# has no species tree, or when it was submitted before (slurm/submitted.txt, written here, or any
# slurm/oma1-* log). Output also goes to $OMA_TEMPLATES/submit_oma_runs.<date>.log. Paths: oma.conf.
set -uo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
OMA_CONF=${OMA_CONF:-$HERE/oma.conf}
[ -r "$OMA_CONF" ] || { echo "ERROR: no settings file $OMA_CONF (copy oma.conf.example to oma.conf)" >&2; exit 1; }
# shellcheck disable=SC1090
source "$OMA_CONF"
OMA_TEMPLATES=${OMA_TEMPLATES:-$OMA_V2/OMA_TEMPLATE_RUNS}
LOG=$OMA_TEMPLATES/submit_oma_runs.$(date +%Y-%m-%d).log

RUNS=()
if [ "${1:-}" = -f ]; then
  [ -r "${2:-}" ] || { echo "ERROR: list file '${2:-}' missing"; exit 1; }
  while IFS= read -r line; do
    line=${line%%#*}
    read -r geneset _ <<< "$line"
    [ -n "${geneset:-}" ] && RUNS+=("${geneset%/}")
  done < "$2"
elif [ -n "${1:-}" ] && [ "${1:0:1}" != - ]; then
  for arg in "$@"; do RUNS+=("${arg%/}"); done
else
  sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 1
fi
[ ${#RUNS[@]} -gt 0 ] || { echo "ERROR: no runs given"; exit 1; }

{
  for geneset in "${RUNS[@]}"; do
    run=$OMA_V2/$geneset
    if [ ! -e "$run/parameters.drw" ] || ! ls "$run"/DB/*.fa > /dev/null 2>&1; then
      echo "$geneset: SKIPPED, not set up (run setup_oma_runs.sh first)"; continue
    fi
    if ! grep -q "^SpeciesTree := '(" "$run/parameters.drw"; then
      echo "$geneset: SKIPPED, no species tree in parameters.drw (make_oma_db_files.pl not run?)"; continue
    fi
    if [ -e "$run/slurm/submitted.txt" ] || ls "$run"/slurm/oma1-* > /dev/null 2>&1; then
      echo "$geneset: SKIPPED, submitted before (see $run/slurm/)"; continue
    fi
    part2=$(cd "$run" && ls oma-part2*.sh 2>/dev/null | head -1)
    [ -n "$part2" ] || { echo "$geneset: SKIPPED, no oma-part2*.sh"; continue; }

    cd "$run" || continue
    j1=$(sbatch --parsable oma-part1.sh) \
      && j2=$(sbatch --parsable --dependency=afterok:"$j1" "$part2") \
      && j3=$(sbatch --parsable --dependency=afterok:"$j2" oma-part3.sh) \
      || { echo "$geneset: SUBMIT FAILED (part1=${j1:-} part2=${j2:-}); check squeue before retrying"; continue; }
    echo "$(date '+%F %T') part1 $j1 part2 $j2 part3 $j3" >> slurm/submitted.txt
    echo "$geneset: part1 $j1  part2 $j2  part3 $j3"
  done
} 2>&1 | tee -a "$LOG"
