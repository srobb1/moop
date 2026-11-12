#!/usr/bin/bash
# Run OrthoFinder for one run folder made by make_clade_set.py (called by its run_orthofinder.sbatch).
#
#   run_orthofinder.sh <run folder>             # full run: DIAMOND all-vs-all, groups, trees
#   RESUME=1 run_orthofinder.sh <run folder>    # reuse the DIAMOND results of the newest earlier run
#
# Works in /scratch, not in the run folder: on /n/sci OrthoFinder's last copy step can fail
# ([Errno 524] listing extended attributes; seen 2026-11-12 on cerebro102), which stops it before
# the statistics and other final files are written while it still reports "finished".
# Afterwards it checks the run really completed (statistics written, no Python traceback), packs
# the folders that hold one file per orthogroup (alignments, sequences, the working directory) into
# per_group_files.tar.gz, copies the result to <run folder>/results/Results_<date>_<job>, and builds
# MOOP's file from it: build_and_load_db/v2/data/.clades/<clade>/homologs.sqlite (copy2moop ships it).
# The scratch folder is removed on success and kept on failure.
set -euo pipefail
OUT=$(cd "${1:?usage: run_orthofinder.sh <run folder>}" && pwd)
CPUS=${SLURM_CPUS_PER_TASK:-${CPUS:-4}}
JOB=${SLURM_JOB_ID:-$$}
WORK=/scratch/$USER/orthofinder_v2/$(basename "$OUT")_$JOB
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ORTHOFINDER_CONF=${ORTHOFINDER_CONF:-$HERE/orthofinder.conf}
[ -r "$ORTHOFINDER_CONF" ] || { echo "ERROR: no settings file $ORTHOFINDER_CONF (copy orthofinder.conf.example)" >&2; exit 1; }
# shellcheck disable=SC1090
source "$ORTHOFINDER_CONF"            # OF_ROOT, MOOP_DATA, ORTHOFINDER_MODULE
MODULE=${ORTHOFINDER_MODULE:-orthofinder/3.1.2}
BUILDER=$HERE/../build_and_load_db/scripts/build_clade_homologs.py
TAXDIR=$OF_ROOT/taxonomy
log() { echo "[$(date '+%F %T')] $*"; }
[ -r "$BUILDER" ] || { echo "ERROR: clade builder not found: $BUILDER"; exit 1; }

[ -d "$OUT/input" ] || { echo "ERROR: $OUT/input missing"; exit 1; }
command -v module >/dev/null || source /etc/profile.d/modules.sh
module load "$MODULE"

mkdir -p "$WORK"
trap 'echo "ERROR: failed; scratch folder kept: $WORK"' ERR
cp -r "$OUT/input" "$WORK/"
TREE=()
if [ -f "$OUT/species_tree.nwk" ]; then cp "$OUT/species_tree.nwk" "$WORK/"; TREE=(-s "$WORK/species_tree.nwk"); fi
cd "$WORK"
touch .started

if [ "${RESUME:-0}" = 1 ]; then
    # -fg (from groups; not in OrthoFinder 3's -h) reuses the earlier run's orthogroups and redoes the gene
    # trees, orthologues, HOGs and statistics. (-b does NOT skip the searches in OrthoFinder 3: it reruns
    # the commands of an -op run's blast_commands.txt.)
    prev=$(ls -dt "$OUT"/results/Results_*/ 2>/dev/null | head -1)
    [ -n "$prev" ] || { echo "ERROR: RESUME=1 but no earlier run in $OUT/results"; exit 1; }
    mkdir -p "$WORK/prev"
    cp -r "${prev%/}" "$WORK/prev/"
    prevw="$WORK/prev/$(basename "$prev")"
    if [ ! -d "$prevw/WorkingDirectory" ]; then
        [ -f "$prevw/per_group_files.tar.gz" ] || { echo "ERROR: $prev has no WorkingDirectory"; exit 1; }
        tar -xzf "$prevw/per_group_files.tar.gz" -C "$prevw"
    fi
    log "resuming from the orthogroups of $prev"
    orthofinder -fg "$prevw" -t "$CPUS" -a $(( CPUS > 4 ? CPUS / 4 : 1 )) "${TREE[@]}" 2>&1 | tee orthofinder.out
else
    log "full run, $CPUS threads"
    orthofinder -f input -t "$CPUS" -a $(( CPUS > 4 ? CPUS / 4 : 1 )) "${TREE[@]}" -o results 2>&1 | tee orthofinder.out
fi

# OrthoFinder prints "finished" even after a crash in its last steps: check for itself
if grep -q '^Traceback\|File ".*orthofinder' orthofinder.out; then
    echo "ERROR: OrthoFinder hit an error (see $WORK/orthofinder.out)"; exit 1
fi
stats=$(find "$WORK" -path '*/Comparative_Genomics_Statistics/Statistics_Overall.tsv' -newer .started | head -1)
[ -n "$stats" ] || { echo "ERROR: no Statistics_Overall.tsv written: the run did not complete"; exit 1; }
R=$(dirname "$(dirname "$stats")")
log "completed: $R"

cd "$R"
packed=()
for d in MultipleSequenceAlignments Orthogroup_Sequences Single_Copy_Orthologue_Sequences Gene_Trees WorkingDirectory; do
    [ -d "$d" ] && packed+=("$d")
done
if [ ${#packed[@]} -gt 0 ]; then
    log "packing ${packed[*]}"
    tar -czf per_group_files.tar.gz "${packed[@]}"
    rm -rf "${packed[@]}"
fi
cp "$WORK/orthofinder.out" .

dest="$OUT/results/$(basename "$R")_$JOB"
mkdir -p "$OUT/results"
cp -r "$R" "$dest"
log "copied to $dest ($(find "$dest" -type f | wc -l) files, $(du -sh "$dest" | cut -f1))"
cd /
rm -rf "$WORK"

# The file MOOP reads: data/.clades/<clade>/homologs.sqlite in the MOOP build folder, which copy2moop ships
CLADE=$(basename "$OUT")
log "building $MOOP_DATA/.clades/$CLADE/homologs.sqlite"
python3 -I "$BUILDER" --run "$OUT" --results "$dest" --taxonomy "$TAXDIR" \
    --out "$MOOP_DATA/.clades/$CLADE/homologs.sqlite"
log "done; ship it with: copy2moop_v2.sh --clades (or --all)"
