#!/bin/bash
# Step 02: align each query set to every reference genome (minimap2, spliced, guided by the
# reference junctions) and convert the alignments to sorted GFF.
#
#   02_align_transcripts.sh -c rosettastone.conf [set ...]
#
# Reads  every query set (datastore gene sets' transcript.nt.fa and cds.nt.fa, QUERY_DIR/<set>.nt),
#        $OUT_DIR/refdb/<ref>/ (step 01)
# Writes $OUT_DIR/alignments/<ref>/<set>.sam and <set>.sorted.gff (scripts/minimap2_sam2gff.pl,
#        identity cut ALIGN_MIN_IDENTITY, default 0 = none). Whole transcripts are aligned, so
#        their UTRs place each one on the right copy of a duplicated gene; for a set with a
#        cds.nt.fa the GFF is then trimmed to the CDS (clip_to_cds.py), so step 03 compares
#        coding sequence only and non-coding transcripts drop out (coding genes only).
# Outputs newer than their inputs are skipped, so adding one new set and rerunning only
# aligns that set. Name sets on the command line to do only those.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"
load_modules
need minimap2 python3

n=0
for set in "${QUERY_NAMES[@]}"; do
    wanted "$set" || continue
    q=${QUERY_FASTA[$set]}
    [ -s "$q" ] || die "$set: cannot read $q"
    cds=$(query_cds "$set"); gs_gff=
    [ -n "$cds" ] && [ -s "$(dirname "$q")/genes.gff" ] && gs_gff=$(dirname "$q")/genes.gff
    for ref in "${REF_NAMES[@]}"; do
        R=$OUT_DIR/refdb/$ref; A=$OUT_DIR/alignments/$ref
        mkdir -p "$A"
        sam=$A/$set.sam; gff=$A/$set.sorted.gff
        [ -s "$R/genome.mmi" ] || die "$R/genome.mmi missing; run step 01"
        # also redo it when the SAM was made from another file (its @PG line names the input):
        # a SAM from an earlier CDS-only run was once reused as a transcript alignment (2026-11-18)
        if ! uptodate "$sam" "$q" "$R/genome.mmi" \
           || ! grep -m1 '^@PG' "$sam" | grep -qF -- " $q"; then
            log "$ref $set: minimap2"
            minimap2 -t "$THREADS" -ax splice:hq --junc-bed "$R/junctions.bed" -uf \
                "$R/genome.mmi" "$q" > "$sam.tmp"
            finish "$sam.tmp" "$sam"
        fi
        if ! uptodate "$gff" "$sam" ${cds:+"$cds"} ${gs_gff:+"$gs_gff"}; then
            log "$ref $set: to GFF"
            rm -f "$A/$set.tmp.gff"
            # minimap2_sam2gff.pl: AGAT's converter without its fixed 90% identity cut, which counts
            # every indel base as a mismatch and dropped near-identical alignments (2026-11-18)
            perl "$RS_HOME/scripts/minimap2_sam2gff.pl" --min-identity "${ALIGN_MIN_IDENTITY:-0}" \
                -i "$sam" -o "$A/$set.tmp.gff"
            if [ -n "$cds" ]; then
                python3 "$RS_HOME/scripts/clip_to_cds.py" --transcripts "$q" --cds "$cds" \
                    ${gs_gff:+--gff "$gs_gff"} "$A/$set.tmp.gff" > "$A/$set.clip.gff" \
                    || die "$ref $set: clip_to_cds.py failed"
                mv -f "$A/$set.clip.gff" "$A/$set.tmp.gff"
            fi
            LC_ALL=C sort -k1,1 -k4,4n -k5,5n "$A/$set.tmp.gff" > "$gff.tmp"
            rm -f "$A/$set.tmp.gff"
            finish "$gff.tmp" "$gff"
        fi
    done
    n=$((n + 1))
done
[ $n -gt 0 ] || [ ${#SETS[@]} -gt 0 ] || die "no query sets to align"
log "step 02 done: $n set(s) x ${#REF_NAMES[@]} reference(s)"
