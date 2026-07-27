#!/usr/bin/bash
## Submit all *active* organism/assembly/geneset jobs as a SLURM array.
## "Active" = GENOMES/<organism>/<assembly>/<geneset>/metadata.yaml exists
## and contains "active: true" (case-insensitive).
## %10 = run at most 10 tasks concurrently.
##
## Usage: bash run_all_v2.sh [--no-copy] [--reload]
##   --no-copy   Build everything but skip the final rsync to the live moop app
##               (moop_process_genome_data_v2.sbatch still runs copy2moop_v2.sh
##               unless SKIP_COPY=1 is set, which this flag does).
##   --reload    Rebuild and reload everything, ignoring the "already built" checks.
##               See the block below -- without it a re-run is very nearly a no-op.
##               (--force is accepted as an alias.)

SKIP_COPY=0
MOOP_RELOAD=0
for arg in "$@"; do
  case "$arg" in
    --no-copy)         SKIP_COPY=1 ;;
    --reload|--force)  MOOP_RELOAD=1 ;;
    *) echo "Unknown argument: $arg" >&2
       echo "Usage: bash run_all_v2.sh [--no-copy] [--reload]" >&2
       exit 1 ;;
  esac
done

## Why --reload exists
##
## Every build step is gated on its own output already existing:
##
##     has_data isoforms.tsv || REBUILD=true
##     has_data geneNames.tsv || REBUILD=true
##     has_data features.tsv  || REBUILD=true
##
## That makes a re-run cheap, which is right for adding a new geneset to a tree that
## is otherwise current. But it means that on a tree where everything was already
## built, plain "bash run_all_v2.sh" rebuilds NOTHING and reloads NOTHING -- it just
## re-copies. So it cannot be used to pick up a fix to a parser or a loader, which is
## exactly what a reload is for.
##
## --reload invalidates EXACTLY what the run covers, and nothing else. Here that is
## every organism, so every organism.sqlite is dropped and recreated from
## create_schema_sqlite.sql. Dropping matters on its own: constraints added to the
## schema (UNIQUE, NOT NULL, a corrected FOREIGN KEY) cannot be applied to an existing
## SQLite file, so a load into a surviving database silently keeps the OLD schema.
##
## To reload part of an organism, invoke the job directly -- it narrows, and the
## deletion narrows with it, so an organism's other gene sets are untouched:
##
##     MOOP_RELOAD=1 sbatch scripts/moop_process_genome_data_v2.sbatch Org Asm GeneSet
##
## NOT touched by --reload, deliberately: genome.json, geneset.json, and the
## "date_added" they carry. Those are site metadata, not derived data. organism.json
## IS regenerated, because the rebuild path has always done that.

# Where this pipeline lives, derived from this script's own location rather than
# hardcoded, so a git checkout works wherever it is cloned. Everything below is
# relative to it (and the sbatch takes REPO=$SLURM_SUBMIT_DIR, which is this cd).
REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
cd "$REPO"

# slurm_logs/ is where "#SBATCH --output=slurm_logs/..." writes. Git does not track
# empty directories, so a fresh clone has none and every job would lose its output.
mkdir -p slurm_logs

# The array task list is a PER-RUN SNAPSHOT, and nothing else ever writes it.
#
# A SLURM array task finds its work by line number:
#
#     LINE=$(sed -n "$((SLURM_ARRAY_TASK_ID + 1))p" "$GENESETS_FILE")
#
# so the file has to stay byte-identical for the whole life of the array -- which can
# be hours. It previously pointed at $REPO/active_genesets.tsv, a fixed path that
# check_status.sh ALSO truncated and rewrote every time it ran. Checking on a run's
# progress is the most natural thing in the world to do while it is running, and doing
# it would renumber the list underneath the still-queued tasks: if the active set had
# changed at all, tasks would silently process the wrong geneset. Two concurrent
# run_all_v2.sh invocations collided the same way.
#
# So: snapshot to a unique file per submission, hand THAT to the array, and never
# write it again. active_genesets.tsv is still refreshed as the human-readable
# "what is active right now" list the README describes, but nothing reads it.
mkdir -p runs
RUN_ID="$(date +%Y%m%dT%H%M%S)-$$"
GENESETS_FILE="$REPO/runs/genesets.$RUN_ID.tsv"
ORGANISMS_FILE="$REPO/runs/organisms.$RUN_ID.tsv"

bash "$REPO/scripts/list_active_genesets.sh" > "$GENESETS_FILE" || exit 1
cut -f1 "$GENESETS_FILE" | sort -u > "$ORGANISMS_FILE"
cp "$GENESETS_FILE" "$REPO/active_genesets.tsv"

NGS=$(wc -l < "$GENESETS_FILE")
N=$(wc -l < "$ORGANISMS_FILE")
if [ "$N" -eq 0 ]; then
  echo "No active genesets found (active: true in metadata.yaml)."
  rm -f "$GENESETS_FILE" "$ORGANISMS_FILE"
  exit 1
fi

## The ARRAY indexes organisms, not gene sets -- one job owns one organism, because
## organism.sqlite is per organism (see the header of the sbatch). Each job then reads
## its own gene sets out of the frozen $GENESETS_FILE.
echo "Submitting $N organism(s), $NGS active geneset(s):"
cat "$GENESETS_FILE"
echo "Task lists (frozen for this run):"
echo "  organisms: $ORGANISMS_FILE"
echo "  genesets : $GENESETS_FILE"

[ "$SKIP_COPY" -eq 1 ] && echo "--no-copy set: skipping rsync to the live moop app for this run."
if [ "$MOOP_RELOAD" -eq 1 ]; then
  echo ""
  echo "--reload set: rebuilding intermediates AND dropping all $N organism.sqlite"
  echo "              databases, so each is recreated from the current schema."
  echo ""
fi

sbatch --array=0-$((N - 1))%10 \
  --export=ALL,GENESETS_FILE="$GENESETS_FILE",ORGANISMS_FILE="$ORGANISMS_FILE",SKIP_COPY="$SKIP_COPY",MOOP_RELOAD="$MOOP_RELOAD" \
  scripts/moop_process_genome_data_v2.sbatch
