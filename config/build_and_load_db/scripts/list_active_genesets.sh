#!/usr/bin/bash
# list_active_genesets.sh — print every ACTIVE geneset, one per line, as
#
#     organism<TAB>assembly<TAB>geneset
#
# "Active" means GENOMES/<organism>/<assembly>/<geneset>/metadata.yaml exists and
# contains "active: true" (case-insensitive, whole line).
#
# THIS IS THE ONE DEFINITION. run_all_v2.sh and scripts/check_status.sh both call
# it. They used to carry their own identical copy of the loop below, which meant two
# definitions of "which genesets exist" that drift apart silently -- and a status run
# would then disagree with what was actually built, in a way nothing reports.
#
# Writes to stdout only. It never writes a file, so it cannot truncate one that a
# running job is reading; the caller decides where the list goes.
#
# Usage:
#   bash scripts/list_active_genesets.sh              # to stdout
#   bash scripts/list_active_genesets.sh > list.tsv
#   GENOMES=/some/other/tree bash scripts/list_active_genesets.sh

set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/paths.sh"

# This script owns the default location of the genomes tree, so callers that need the
# path for their own checks (check_status.sh does) can ask for it instead of keeping a
# second copy of the literal that would drift.
if [ "${1:-}" = "--genomes-dir" ]; then
  echo "$GENOMES"
  exit 0
fi

if [ ! -d "$GENOMES" ]; then
  echo "ERROR: genomes tree not found: $GENOMES" >&2
  echo "       Set GENOMES to override." >&2
  exit 1
fi

for META in "$GENOMES"/*/*/*/metadata.yaml; do
  [ -f "$META" ] || continue
  grep -qiE '^active:[[:space:]]*true[[:space:]]*$' "$META" || continue
  GENESET_DIR=$(dirname "$META")
  GENE_SET=$(basename "$GENESET_DIR")
  ASSEMBLY=$(basename "$(dirname "$GENESET_DIR")")
  THIS_ORG=$(basename "$(dirname "$(dirname "$GENESET_DIR")")")
  printf '%s\t%s\t%s\n' "$THIS_ORG" "$ASSEMBLY" "$GENE_SET"
done
