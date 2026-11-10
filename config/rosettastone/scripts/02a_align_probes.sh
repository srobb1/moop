#!/bin/bash
# Step 02a: align probe sets (e.g. microarray probes: short, unspliced) to every reference
# genome with pblat at >=95% identity.
#
#   02a_align_probes.sh -c rosettastone.conf [set ...]
#
# Reads  $PROBE_DIR/<set>.nt, $OUT_DIR/refdb/<ref>/genome.fa (step 01)
# Writes $OUT_DIR/probe_alignments/<ref>/<set>.psl and <set>.bed
# Does nothing when PROBE_DIR is empty.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"
[ ${#PROBE_NAMES[@]} -gt 0 ] || { log "no probe sets; skipping step 02a"; exit 0; }
load_modules
need pblat psl2bed

for set in "${PROBE_NAMES[@]}"; do
    wanted "$set" || continue
    q=${PROBE_FASTA[$set]}
    for ref in "${REF_NAMES[@]}"; do
        R=$OUT_DIR/refdb/$ref; M=$OUT_DIR/probe_alignments/$ref
        mkdir -p "$M"
        psl=$M/$set.psl; bed=$M/$set.bed
        if ! uptodate "$psl" "$q" "$R/genome.fa"; then
            log "$ref $set: pblat"
            pblat -threads="$THREADS" -minScore=30 -minIdentity=95 "$R/genome.fa" "$q" "$psl.tmp"
            finish "$psl.tmp" "$psl"
        fi
        if ! uptodate "$bed" "$psl"; then
            psl2bed < "$psl" > "$bed.tmp"; finish "$bed.tmp" "$bed"
        fi
    done
done
log "step 02a done"
