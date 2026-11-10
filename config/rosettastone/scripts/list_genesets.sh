#!/bin/bash
# List the gene sets of a species in the genomes datastore, to choose references from.
#
#   list_genesets.sh /n/sci/SCI-004223-SBGENOMES/genomes/v2/Schmidtea_mediterranea
#   list_genesets.sh /n/sci/SCI-004223-SBGENOMES/genomes/v2      # every species
#
# Prints assembly/geneset, whether metadata.yaml says active: true, whether the genome is real
# or a placeholder made of the transcripts (those cannot be references), transcripts and genes.
# Use the assembly/geneset (or just the geneset) in REFERENCE_GENESETS / MAP_GENESETS.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
[ $# -eq 1 ] && [ -d "$1" ] || { sed -n '2,/^set -/p' "$0" | sed '$d; s/^# \{0,1\}//' >&2; exit 2; }
dir=${1%/}
if ls "$dir"/*/*/metadata.yaml >/dev/null 2>&1; then species=("$dir")
else species=(); for d in "$dir"/*/; do species+=("${d%/}"); done; fi
for sp in "${species[@]}"; do
    out=$(ds_genesets "$sp") || true
    [ -n "$out" ] || continue
    echo "$(basename "$sp")"
    SPECIES_DIR=$sp
    while IFS=$'\t' read -r gs active; do
        t=$(grep -c '>' "$sp/$gs/transcript.nt.fa" 2>/dev/null || echo 0)
        g=$(cut -f2 "$sp/$gs/transcript2gene.txt" 2>/dev/null | sort -u | wc -l)
        k=$(ds_genome_kind "$gs"); [ "$k" = genome ] && k="genome" || k="placeholder"
        printf '  %-40s %-8s %-11s %8s transcripts %8s genes\n' "$gs" "$([ "$active" = 1 ] && echo active || echo inactive)" "$k" "$t" "$g"
    done <<< "$out"
done
