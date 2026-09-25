#!/usr/bin/bash
#SBATCH --job-name=MOOP_COPY_ALL
#SBATCH --cpus-per-task=1
#SBATCH --mem=4gb
#SBATCH --time=04:00:00
#SBATCH --output=slurm_logs/%x_%j.out

REPO=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/build_and_load_db/v2
SCRIPTS="$REPO/scripts"

## Copy every *active* organism/assembly/geneset (same detection as run_all_v2.sh):
## GENOMES/<organism>/<assembly>/<geneset>/metadata.yaml exists and contains "active: true".
## From $SCRIPTS, not $(dirname "${BASH_SOURCE[0]}"): SLURM runs a COPY of this file from
## its spool directory, so that dirname is the spool, paths.sh is not there, and every
## sbatch of this script failed at this line.
source "$SCRIPTS/paths.sh" || { echo "ERROR: cannot read $SCRIPTS/paths.sh" >&2; exit 1; }
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

while IFS=$'\t' read -r THIS_ORG ASSEMBLY GENE_SET <&3; do
  echo "--- $THIS_ORG [$ASSEMBLY/$GENE_SET]"
  ## REPO passed explicitly, as moop_process_genome_data_v2.sbatch does: left to derive it
  ## from its own location, copy2moop_v2.sh follows the scripts/ symlink into the git
  ## checkout, which has no data/ -- the build writes one tree and the copy reads another.
  REPO="$REPO" bash "$SCRIPTS/copy2moop_v2.sh" "$THIS_ORG" "$ASSEMBLY" "$GENE_SET"
done 3< "$GENESETS_FILE"
