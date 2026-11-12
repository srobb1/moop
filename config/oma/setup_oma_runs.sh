#!/usr/bin/bash
# Sets up new OMA runs (HOWTO_NEW_SPECIES.md steps 1-3) and prints the commands that submit them.
# For each gene set: make the run dir (mirrors the datastore path), copy the template in, make slurm/,
# run make_oma_db_files.pl (DB/<PREFIX>.fa, .splice, species tree), then check its summary.
# Nothing is submitted.
#
#   bash setup_oma_runs.sh <organism>/<genome>/<geneset> [PREFIX [LINEAGE]]
#   bash setup_oma_runs.sh -f LIST          LIST: one gene set per line, "<organism>/<genome>/<geneset> [PREFIX [LINEAGE]]"
#                                           (blank lines and # comments skipped)
#
# PREFIX   OMA genome code; default: first 3 letters of the genus + first 3 of the species, upper case
#          (Petromyzon_marinus -> PETMAR). Several gene sets of one species share it (separate run dirs).
# LINEAGE  a species_trees.tsv lineage, only to override the NCBI taxonomy lookup.
#
# A run dir that already has parameters.drw is left alone. Output also goes to
# $OMA_TEMPLATES/setup_oma_runs.<date>.log. Paths come from oma.conf (see oma.conf.example).
set -uo pipefail

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
OMA_CONF=${OMA_CONF:-$HERE/oma.conf}
[ -r "$OMA_CONF" ] || { echo "ERROR: no settings file $OMA_CONF (copy oma.conf.example to oma.conf)" >&2; exit 1; }
# shellcheck disable=SC1090
source "$OMA_CONF"
OMA_TEMPLATES=${OMA_TEMPLATES:-$OMA_V2/OMA_TEMPLATE_RUNS}
TEMPLATE=$OMA_TEMPLATES/$DEFAULT_TEMPLATE/OMA.2.7.0
[ -d "$TEMPLATE/DB" ] || { echo "ERROR: no template $TEMPLATE" >&2; exit 1; }
# the template's reference genomes: every DB/<CODE>.fa in it
REFERENCE_CODES=" $(ls "$TEMPLATE"/DB/*.fa | xargs -n1 basename | sed 's/\.fa$//' | tr '\n' ' ')"
LOG=$OMA_TEMPLATES/setup_oma_runs.$(date +%Y-%m-%d).log

usage() { sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 1; }

ENTRIES=()
if [ "${1:-}" = -f ]; then
  [ -r "${2:-}" ] || { echo "ERROR: list file '${2:-}' missing"; exit 1; }
  while IFS= read -r line; do
    line=${line%%#*}
    [[ $line =~ [^[:space:]] ]] && ENTRIES+=("$line")
  done < "$2"
  [ ${#ENTRIES[@]} -gt 0 ] || { echo "ERROR: no gene sets in '$2'"; exit 1; }
elif [ -n "${1:-}" ] && [ "${1:0:1}" != - ]; then
  ENTRIES+=("$*")
else
  usage
fi

SUBMIT=()
setup_one() {
  local geneset=$1 prefix=${2:-} lineage=${3:-}
  geneset=${geneset%/}
  local run=$OMA_V2/$geneset src=$GENOMES/$geneset
  local organism=${geneset%%/*}

  if [ "$(tr -cd / <<< "$geneset" | wc -c)" -ne 2 ]; then
    echo "$geneset: SKIPPED, must be <organism>/<genome>/<geneset>"; return
  fi
  if [ -z "$prefix" ]; then
    local genus=${organism%%_*} species=${organism#*_}
    prefix=$(tr '[:lower:]' '[:upper:]' <<< "${genus:0:3}${species:0:3}")
  fi
  if ! [[ $prefix =~ ^[A-Z0-9]{3,6}$ ]]; then
    echo "$geneset: SKIPPED, prefix '$prefix' is not 3-6 upper-case letters/digits"; return
  fi
  if [[ $REFERENCE_CODES == *" $prefix "* ]]; then
    echo "$geneset: SKIPPED, $prefix is a reference genome of the template"; return
  fi
  local f
  for f in protein.aa.fa protein2gene.txt metadata.yaml; do
    [ -s "$src/$f" ] || { echo "$geneset: SKIPPED, $src/$f missing or empty"; return; }
  done
  if [ -e "$run/parameters.drw" ]; then
    echo "$geneset: already set up, left alone"; return
  fi

  echo "$(date '+%H:%M') $geneset ($prefix): copying the template"
  mkdir -p "$run" && cp -r "$TEMPLATE"/* "$run/" && mkdir -p "$run/slurm" \
    || { echo "  COPY FAILED"; return; }

  ( cd "$run" && OMA_CONF=$OMA_CONF perl "$HERE/make_oma_db_files.pl" "$prefix" $lineage > make_oma_db_files.log 2>&1 )
  local rc=$?
  sed 's/^/  /' "$run/make_oma_db_files.log"
  if [ $rc -ne 0 ]; then
    echo "  make_oma_db_files.pl FAILED (exit $rc); the run dir is left for a look: $run"; return
  fi

  ## the HOWTO's check: proteins written = proteins in the gene set minus the ones removed
  local n_in n_out
  n_in=$(grep -c '^>' "$src/protein.aa.fa")
  n_out=$(grep -c '^>' "$run/DB/$prefix.fa")
  echo "  proteins: $n_in in protein.aa.fa, $n_out in DB/$prefix.fa"
  grep -q '^SpeciesTree :=' "$run/parameters.drw" || echo "  WARNING: no active SpeciesTree line in parameters.drw"

  local part2
  part2=$(cd "$run" && ls oma-part2*.sh 2>/dev/null | head -1)
  SUBMIT+=("cd $run && j1=\$(sbatch --parsable oma-part1.sh) && j2=\$(sbatch --parsable --dependency=afterok:\$j1 $part2) && sbatch --dependency=afterok:\$j2 oma-part3.sh")
}

{
  for entry in "${ENTRIES[@]}"; do
    read -r geneset prefix lineage <<< "$entry"
    setup_one "$geneset" "$prefix" "$lineage"
  done

  if [ ${#SUBMIT[@]} -gt 0 ]; then
    echo
    echo "Check the lineage and protein counts above. Then submit each run (part 1 -> 2 -> 3 chained):"
    printf '%s\n' "${SUBMIT[@]}"
    echo "Afterwards: cd <run>/mapGO && ./get_OMA_GO_terms.sh <PREFIX>   (HOWTO step 7)"
  fi
} 2>&1 | tee -a "$LOG"
