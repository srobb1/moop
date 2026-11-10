#!/bin/bash
# Step 02: align each query set to every reference genome (minimap2, spliced, guided by the
# reference junctions) and convert the alignments to sorted GFF.
#
#   02_align_transcripts.sh -c rosettastone.conf [set ...]
#
# Reads  every query set (datastore gene sets' transcript.nt.fa, QUERY_DIR/<set>.nt),
#        $OUT_DIR/refdb/<ref>/ (step 01)
# Writes $OUT_DIR/alignments/<ref>/<set>.sam and <set>.sorted.gff
# Outputs newer than their inputs are skipped, so adding one new set and rerunning only
# aligns that set. Name sets on the command line to do only those.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"
load_modules
need minimap2 agat_convert_minimap2_bam2gff.pl

n=0
for set in "${QUERY_NAMES[@]}"; do
    wanted "$set" || continue
    q=${QUERY_FASTA[$set]}
    [ -s "$q" ] || die "$set: cannot read $q"
    for ref in "${REF_NAMES[@]}"; do
        R=$OUT_DIR/refdb/$ref; A=$OUT_DIR/alignments/$ref
        mkdir -p "$A"
        sam=$A/$set.sam; gff=$A/$set.sorted.gff
        [ -s "$R/genome.mmi" ] || die "$R/genome.mmi missing; run step 01"
        if ! uptodate "$sam" "$q" "$R/genome.mmi"; then
            log "$ref $set: minimap2"
            minimap2 -t "$THREADS" -ax splice:hq --junc-bed "$R/junctions.bed" -uf \
                "$R/genome.mmi" "$q" > "$sam.tmp"
            finish "$sam.tmp" "$sam"
        fi
        if ! uptodate "$gff" "$sam"; then
            log "$ref $set: to GFF"
            rm -f "$A/$set.tmp.gff"
            agat_convert_minimap2_bam2gff.pl -s -i "$sam" -o "$A/$set.tmp.gff" >/dev/null
            LC_ALL=C sort -k1,1 -k4,4n -k5,5n "$A/$set.tmp.gff" > "$gff.tmp"
            rm -f "$A/$set.tmp.gff"
            finish "$gff.tmp" "$gff"
        fi
    done
    n=$((n + 1))
done
[ $n -gt 0 ] || [ ${#SETS[@]} -gt 0 ] || die "no query sets to align"
log "step 02 done: $n set(s) x ${#REF_NAMES[@]} reference(s)"
