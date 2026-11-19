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
# Also writes $OUT_DIR/filtered/<ref>_unlinked.txt (set id reason seqid start end strand, no
# header): every sequence NOT kept, with the reason and its primary alignment
# (scripts/unlinked_status.py; per-set files in <ref>_unlinked/, redone when their inputs change).
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"
load_modules
need python3 bedtools

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

    # Why the rest did not map, and where they aligned (unlinked_status.py), one file per set,
    # then combined: set id reason seqid start end strand. build_crosswalk.sh makes the
    # crosswalk's 'unlinked' table from it (2026-11-19, for the web's "no gene" hover + link).
    U=$F/${ref}_unlinked; mkdir -p "$U"
    A=$OUT_DIR/alignments/$ref
    for set in "${QUERY_NAMES[@]}"; do
        mode=stranded; is_unstranded "$set" && mode=unstranded
        st=$U/$set.tsv; ov=$P/$set.$mode.overlaps.txt
        q=${QUERY_FASTA[$set]}; cds=$(query_cds "$set")
        gsgff=; [ -n "$cds" ] && [ -s "$(dirname "$q")/genes.gff" ] && gsgff=$(dirname "$q")/genes.gff
        [ -e "$st" ] && uptodate "$st" "$A/$set.sam" "$A/$set.sorted.gff" "$ov" "$OUT_DIR/refdb/$ref/exons.gtf" && continue
        bedtools intersect -u $([ $mode = stranded ] && echo -s) -a "$A/$set.sorted.gff" \
            -b "$OUT_DIR/refdb/$ref/exons.gtf" \
            | awk 'match($0, /Target=[^ ]+/) {print substr($0, RSTART + 7, RLENGTH - 7)}' | sort -u > "$st.exonhits"
        python3 "$RS_HOME/scripts/unlinked_status.py" --name "$set" --sam "$A/$set.sam" \
            --gff "$A/$set.sorted.gff" --overlaps "$ov" --exon-hits "$st.exonhits" \
            ${cds:+--cds "$cds"} ${gsgff:+--genes-gff "$gsgff"} \
            > "$st.tmp" || die "$ref $set: unlinked_status.py failed"
        rm -f "$st.exonhits"
        mv -f "$st.tmp" "$st"
    done
    for set in "${PROBE_NAMES[@]}"; do
        st=$U/$set.tsv; pbed=$OUT_DIR/probe_alignments/$ref/$set.bed; ov=$P/$set.probe.overlaps.txt
        [ -e "$st" ] && uptodate "$st" "$pbed" "$ov" "${PROBE_FASTA[$set]}" && continue
        python3 "$RS_HOME/scripts/unlinked_status.py" --name "$set" --probe-fasta "${PROBE_FASTA[$set]}" \
            --probe-bed "$pbed" --overlaps "$ov" > "$st.tmp" || die "$ref $set: unlinked_status.py failed"
        mv -f "$st.tmp" "$st"
    done
    ul=$F/${ref}_unlinked.txt
    for set in "${QUERY_NAMES[@]}" "${PROBE_NAMES[@]}"; do
        awk -F'\t' -v OFS='\t' -v s="$set" '{sub(/^(gene|transcript):/, "", $1); print s, $0}' "$U/$set.tsv"
    done > "$ul.tmp"
    mv -f "$ul.tmp" "$ul"
    log "$ref: $(wc -l < "$ul") sequences not mapped -> $ul"
done
log "step 04 done"
