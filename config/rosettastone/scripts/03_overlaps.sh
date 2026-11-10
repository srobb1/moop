#!/bin/bash
# Step 03: find which reference genes each aligned sequence overlaps, and how well.
#
#   03_overlaps.sh -c rosettastone.conf [set ...]
#
# Reads  $OUT_DIR/refdb/<ref>/exons.gtf (step 01), alignments (steps 02, 02a)
# Writes $OUT_DIR/overlaps/<ref>/{stranded,unstranded,probe}/<set>.overlap.bed
#        $OUT_DIR/mappings/<ref>/<set>.{stranded,unstranded,probe}.overlaps.txt
#          gene_id  target_id  transcriptome  max_overlap  exon_overlap
#          max_overlap  = longest single overlap (bp) between an exon and an aligned block
#          exon_overlap = number of aligned blocks whose ends match an exon's exactly
#          (probes: 1 and 1)
# Outputs newer than their inputs are skipped.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"
load_modules
need bedtools python3

for ref in "${REF_NAMES[@]}"; do
    exons=$OUT_DIR/refdb/$ref/exons.gtf
    [ -s "$exons" ] || die "$exons missing; run step 01"
    O=$OUT_DIR/overlaps/$ref; P=$OUT_DIR/mappings/$ref
    mkdir -p "$O/stranded" "$O/unstranded" "$O/probe" "$P"

    for set in "${QUERY_NAMES[@]}"; do
        wanted "$set" || continue
        gff=$OUT_DIR/alignments/$ref/$set.sorted.gff
        [ -s "$gff" ] || die "$gff missing; run step 02"
        for mode in stranded unstranded; do
            bed=$O/$mode/$set.overlap.bed
            txt=$P/$set.$mode.overlaps.txt
            if ! uptodate "$bed" "$gff" "$exons"; then
                log "$ref $set: $mode overlaps"
                flag=; [ $mode = stranded ] && flag=-s
                bedtools intersect $flag -wo -a "$exons" -b "$gff" > "$bed.tmp"
                finish "$bed.tmp" "$bed"
            fi
            if ! uptodate "$txt" "$bed"; then
                python3 "$RS_HOME/scripts/count_overlaps.py" --name "$set" "$bed" > "$txt.tmp"
                finish "$txt.tmp" "$txt"
            fi
        done
    done

    for set in "${PROBE_NAMES[@]}"; do
        wanted "$set" || continue
        pbed=$OUT_DIR/probe_alignments/$ref/$set.bed
        [ -s "$pbed" ] || die "$pbed missing; run step 02a"
        bed=$O/probe/$set.overlap.bed
        txt=$P/$set.probe.overlaps.txt
        if ! uptodate "$bed" "$pbed" "$exons"; then
            log "$ref $set: probe overlaps"
            bedtools intersect -wo -a "$exons" -b "$pbed" > "$bed.tmp"
            finish "$bed.tmp" "$bed"
        fi
        if ! uptodate "$txt" "$bed"; then
            # gene from the GTF attributes ($9), probe name from the BED name column ($13)
            awk -F'\t' -v OFS='\t' -v name="$set" '
                match($9, /gene_id "[^"]+"/) {
                    g = substr($9, RSTART + 9, RLENGTH - 10); print g, $13, name, 1, 1 }' \
                "$bed" > "$txt.tmp"
            finish "$txt.tmp" "$txt"
        fi
    done
done
log "step 03 done"
