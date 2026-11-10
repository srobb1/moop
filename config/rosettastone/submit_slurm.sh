#!/bin/bash
# Submit the RosettaStone pipeline for one species to SLURM, one array task per set to map.
#
#   submit_slurm.sh -c rosettastone.conf [options] [set ...]
#
#   1. step 01                      one job (prepares every reference)
#   2. steps 02, 02a, 03            an array: one task per query/probe set, all references
#   3. steps 04, 05 and crosswalk   one job, after every task in 2 has succeeded
# Only the named sets are submitted in 2 if any are given; step 3 always combines all sets.
# Tasks whose outputs are already up to date finish in seconds, so resubmitting after adding
# a gene set only does real work for the new set.
#
#   --cpus N         CPUs per job (THREADS)          [16]
#   --mem SIZE       memory per job                  [64gb]
#   --time T         time limit per job              [1-00:00:00]
#   --partition P    SLURM partition                 [cluster default]
#   --max-parallel N run at most N array tasks at once
#   --dry-run        print the sbatch commands instead of submitting (writes nothing)
#
# Logs and the task list go to $OUT_DIR/runs/<timestamp>/. Environment overrides
# (e.g. SPECIES_DIR=... OUT_ROOT=...) are passed on to the jobs.
set -euo pipefail
PIPELINE_DIR=$(cd "$(dirname "$0")" && pwd)
source "$PIPELINE_DIR/scripts/lib.sh"

CONF=; CPUS=16; MEM=64gb; TIME=1-00:00:00; PART=; MAXPAR=; DRY=; SETS=()
while [ $# -gt 0 ]; do
    case $1 in
        -c) CONF=$2; shift 2 ;;
        --cpus) CPUS=$2; shift 2 ;;
        --mem) MEM=$2; shift 2 ;;
        --time) TIME=$2; shift 2 ;;
        --partition) PART=$2; shift 2 ;;
        --max-parallel) MAXPAR=$2; shift 2 ;;
        --dry-run) DRY=1; shift ;;
        -h|--help) sed -n '2,/^set -/p' "$0" | sed '$d; s/^# \{0,1\}//' >&2; exit 0 ;;
        -*) die "unknown option $1" ;;
        *) SETS+=("$1"); shift ;;
    esac
done
[ -n "$CONF" ] || die "usage: submit_slurm.sh -c rosettastone.conf [options] [set ...]"
CONF=$(cd "$(dirname "$CONF")" && pwd)/$(basename "$CONF")
load_config "$CONF"
[ -n "$DRY" ] || need sbatch

if [ ${#SETS[@]} -eq 0 ]; then SETS=("${QUERY_NAMES[@]}" "${PROBE_NAMES[@]}"); fi
for s in "${SETS[@]}"; do
    [ -n "${QUERY_FASTA[$s]+x}${PROBE_FASTA[$s]+x}" ] || die "'$s' is not one of the configured sets (see run_pipeline.sh --plan)"
done

RUN=$OUT_DIR/runs/$(date +%Y%m%d-%H%M%S)
if [ -z "$DRY" ]; then     # --dry-run writes nothing
    mkdir -p "$RUN"
    printf '%s\n' "${SETS[@]}" > "$RUN/sets.txt"
    { echo "# $(date '+%F %T')  config: $CONF"; print_plan; } > "$RUN/plan.txt"
fi
export THREADS=$CPUS

common=(--parsable --nodes=1 --ntasks=1 --cpus-per-task="$CPUS" --mem="$MEM" --time="$TIME" --export=ALL)
[ -n "$PART" ] && common+=(--partition="$PART")
RP="bash $(printf %q "$PIPELINE_DIR/run_pipeline.sh") -c $(printf %q "$CONF")"
sub() {   # sub NAME [sbatch options...] -- COMMAND
    local name=$1; shift
    local -a opts=()
    while [ "$1" != -- ]; do opts+=("$1"); shift; done; shift
    if [ -n "$DRY" ]; then
        echo "sbatch -J $name ${common[*]} ${opts[*]} --wrap \"$*\"" >&2; echo "DRY_$name"
    else
        sbatch -J "$name" "${common[@]}" "${opts[@]}" --wrap "$*"
    fi
}
sp=$(basename "${SPECIES_DIR:-$OUT_DIR}")
n=${#SETS[@]}
j1=$(sub "rs01_$sp" -o "$RUN/01_%j.out" -- "$RP -s 01")
arr="1-$n"; [ -n "$MAXPAR" ] && arr="$arr%$MAXPAR"
j2=$(sub "rs02_$sp" --array="$arr" --dependency="afterok:$j1" -o "$RUN/02_%A_%a.out" -- \
     "set=\$(sed -n \"\${SLURM_ARRAY_TASK_ID}p\" $(printf %q "$RUN/sets.txt")); $RP -s 02,02a,03 \"\$set\"")
j3=$(sub "rs04_$sp" --dependency="afterok:$j2" -o "$RUN/04_%j.out" -- "$RP -s 04,05,crosswalk")
log "submitted $sp: prepare $j1 -> $n set task(s) $j2 -> combine + crosswalk $j3"
log "logs: $RUN"
