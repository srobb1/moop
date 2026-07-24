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

while IFS=$'\t' read -r THIS_ORG ASSEMBLY GENE_SET <&3; do
  echo "--- $THIS_ORG [$ASSEMBLY/$GENE_SET]"
  bash "$SCRIPTS/copy2moop_v2.sh" "$THIS_ORG" "$ASSEMBLY" "$GENE_SET"
done 3< "$GENESETS_FILE"
