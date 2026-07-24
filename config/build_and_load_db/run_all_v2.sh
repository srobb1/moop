#!/usr/bin/bash
## Submit all *active* organism/assembly/geneset jobs as a SLURM array.
## "Active" = GENOMES/<organism>/<assembly>/<geneset>/metadata.yaml exists
## and contains "active: true" (case-insensitive).
## %10 = run at most 10 tasks concurrently.
##
## Usage: bash run_all_v2.sh [--no-copy]
##   --no-copy   Build everything but skip the final rsync to the live moop app
##               (moop_process_genome_data_v2.sbatch still runs copy2moop_v2.sh
##               unless SKIP_COPY=1 is set, which this flag does).

SKIP_COPY=0
for arg in "$@"; do
  case "$arg" in
    --no-copy) SKIP_COPY=1 ;;
    *) echo "Unknown argument: $arg" >&2; exit 1 ;;
  esac
done

# Where this pipeline lives, derived from this script's own location rather than
# hardcoded, so a git checkout works wherever it is cloned. Everything below is
# relative to it (and the sbatch takes REPO=$SLURM_SUBMIT_DIR, which is this cd).
REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
cd "$REPO"

# slurm_logs/ is where "#SBATCH --output=slurm_logs/..." writes. Git does not track
# empty directories, so a fresh clone has none and every job would lose its output.
mkdir -p slurm_logs

# Site data, deliberately OUTSIDE the repo: genome trees and annotation releases are
# large, shared, and not version-controlled with the code.
GENOMES=/n/sci/SCI-004223-SBGENOMES/genomes_v2
GENESETS_FILE="$REPO/active_genesets.tsv"

> "$GENESETS_FILE"
for META in "$GENOMES"/*/*/*/metadata.yaml; do
  [ -f "$META" ] || continue
  grep -qiE '^active:[[:space:]]*true[[:space:]]*$' "$META" || continue
  GENESET_DIR=$(dirname "$META")
  GENE_SET=$(basename "$GENESET_DIR")
  ASSEMBLY=$(basename "$(dirname "$GENESET_DIR")")
  THIS_ORG=$(basename "$(dirname "$(dirname "$GENESET_DIR")")")
  printf '%s\t%s\t%s\n' "$THIS_ORG" "$ASSEMBLY" "$GENE_SET" >> "$GENESETS_FILE"
done

N=$(wc -l < "$GENESETS_FILE")
if [ "$N" -eq 0 ]; then
  echo "No active genesets found (active: true in metadata.yaml)."
  exit 1
fi

echo "Submitting $N active geneset(s):"
cat "$GENESETS_FILE"

[ "$SKIP_COPY" -eq 1 ] && echo "--no-copy set: skipping rsync to the live moop app for this run."

sbatch --array=0-$((N - 1))%10 --export=ALL,GENESETS_FILE="$GENESETS_FILE",SKIP_COPY="$SKIP_COPY" scripts/moop_process_genome_data_v2.sbatch
