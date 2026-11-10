#!/bin/bash
# Step 04: combine the per-set overlaps into one mapping table per reference.
#
#   04_filter_mappings.sh -c rosettastone.conf
#
# Reads  $OUT_DIR/mappings/<ref>/*.overlaps.txt (step 03), for the sets now configured
#        (results for sets you have removed are left out)
# Writes $OUT_DIR/filtered/<ref>_mappings.txt
#          gene_id  target_id  transcriptome  max_overlap  exon_overlap
# Keeps an overlap if it is >= 100 bp or shares at least one exact exon. Uses the stranded
# overlaps, except for UNSTRANDED_SETS; probe overlaps are all kept. A "gene:" or
# "transcript:" prefix (Ensembl, WormBase) is dropped from gene IDs.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"

F=$OUT_DIR/filtered
mkdir -p "$F"
for ref in "${REF_NAMES[@]}"; do
    P=$OUT_DIR/mappings/$ref
    files=()
    for set in "${QUERY_NAMES[@]}"; do
        mode=stranded; is_unstranded "$set" && mode=unstranded
        f=$P/$set.$mode.overlaps.txt
        [ -e "$f" ] || die "$f missing; run step 03"
        files+=("$f")
    done
    for set in "${PROBE_NAMES[@]}"; do
        f=$P/$set.probe.overlaps.txt
        [ -e "$f" ] || die "$f missing; run step 03"
        files+=("$f")
    done
    out=$F/${ref}_mappings.txt
    { printf 'gene_id\ttarget_id\ttranscriptome\tmax_overlap\texon_overlap\n'
      awk -F'\t' -v OFS='\t' '
        $1 == "gene_id" { next }
        FILENAME !~ /\.probe\.overlaps\.txt$/ && !($4 >= 100 || $5 >= 1) { next }
        { sub(/^(gene|transcript):/, "", $1); print }' "${files[@]}"
    } > "$out.tmp"
    finish "$out.tmp" "$out"
    log "$ref: $(($(wc -l < "$out") - 1)) mappings -> $out"
done
log "step 04 done"
