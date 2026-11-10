#!/bin/bash
#SBATCH --job-name=rosettastone
#SBATCH --nodes=1
#SBATCH --ntasks=1
#SBATCH --cpus-per-task=32
#SBATCH --mem=100gb
#SBATCH --time=2-00:00:00
# Run the RosettaStone mapping pipeline for one species, then build its MOOP crosswalk.
#
#   run_pipeline.sh -c rosettastone.conf --plan          show what would be mapped, and exit
#   run_pipeline.sh -c rosettastone.conf [-s STEPS] [set ...]
#   submit_slurm.sh -c rosettastone.conf                 the same on SLURM, one job per set
#
#   -s STEPS   comma-separated steps to run (default: all, in order):
#              01  prepare references        02  align query sets        02a align probe sets
#              03  overlaps                  04  filter mappings         05  aliases
#              crosswalk  build crosswalk.sqlite
#   set ...    limit steps 02-03 to these sets
#
# Every step skips work whose outputs are newer than their inputs, so after adding a gene set
# to the datastore (or a <set>.nt to QUERY_DIR), rerunning only aligns and overlaps that set
# and then rebuilds the combined tables and the crosswalk.
set -euo pipefail
# "sbatch run_pipeline.sh" runs a copy from the spool directory: then use SLURM_SUBMIT_DIR
PIPELINE_DIR=$(cd "$(dirname "$0")" && pwd)
if [ ! -f "$PIPELINE_DIR/scripts/lib.sh" ] && [ -f "${SLURM_SUBMIT_DIR:-}/scripts/lib.sh" ]; then
    PIPELINE_DIR=$SLURM_SUBMIT_DIR
fi
source "$PIPELINE_DIR/scripts/lib.sh"

CONF=; STEPS=01,02,02a,03,04,05,crosswalk; SETS=(); PLAN=
while [ $# -gt 0 ]; do
    case $1 in
        -c) CONF=$2; shift 2 ;;
        -s) STEPS=$2; shift 2 ;;
        --plan) PLAN=1; shift ;;
        -h|--help) sed -n '8,/^set -/p' "$0" | sed '$d; s/^# \{0,1\}//' >&2; exit 0 ;;
        -*) die "unknown option $1" ;;
        *) SETS+=("$1"); shift ;;
    esac
done
[ -n "$CONF" ] || die "usage: run_pipeline.sh -c rosettastone.conf [--plan] [-s STEPS] [set ...]"
CONF=$(cd "$(dirname "$CONF")" && pwd)/$(basename "$CONF")
load_config "$CONF"
if [ -n "$PLAN" ]; then print_plan; exit 0; fi
for s in "${SETS[@]}"; do
    [ -n "${QUERY_FASTA[$s]+x}${PROBE_FASTA[$s]+x}" ] || die "'$s' is not one of the configured sets (see --plan)"
done
mkdir -p "$OUT_DIR"
{ echo "# $(date '+%F %T')  config: $CONF"; print_plan; } > "$OUT_DIR/rosettastone.plan.txt.$$"
finish "$OUT_DIR/rosettastone.plan.txt.$$" "$OUT_DIR/rosettastone.plan.txt"

run() {
    local step=$1 script=$2; shift 2
    [[ ",$STEPS," == *",$step,"* ]] || return 0
    log "=== step $step: $script"
    bash "$PIPELINE_DIR/$script" -c "$CONF" "$@"
}
run 01        scripts/01_prepare_reference.sh
run 02        scripts/02_align_transcripts.sh "${SETS[@]}"
run 02a       scripts/02a_align_probes.sh "${SETS[@]}"
run 03        scripts/03_overlaps.sh "${SETS[@]}"
run 04        scripts/04_filter_mappings.sh
run 05        scripts/05_make_aliases.sh
run crosswalk crosswalk/build_crosswalk.sh
log "pipeline done: $OUT_DIR"
