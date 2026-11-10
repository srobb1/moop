#!/bin/bash
# Step 01: prepare each reference genome in REFERENCES.
#
#   01_prepare_reference.sh -c rosettastone.conf
#
# Writes, per reference, $OUT_DIR/refdb/<ref>/
#   genome.fa       genome, uncompressed (for pblat); a symlink when the input is not gzipped
#   genome.mmi      minimap2 index
#   anno.gtf        gene models as GTF (gffread; GFF3 or GTF input, gzipped or not)
#   junctions.bed   splice junctions for minimap2 --junc-bed
#   exons.gtf       exons only, gene_id only, sorted: the overlap target for step 03
#   t2g.tsv         id<TAB>gene for every transcript and gene of the reference (a gene maps
#                   to itself): the reference's transcript2gene file if it has one (the
#                   datastore's, i.e. MOOP's IDs), else from the gene models' hierarchy.
# With a transcript2gene file, exons.gtf uses its gene IDs too (see below).
# Each file is skipped when it is newer than its input.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"
load_modules
need minimap2 gffread paftools.js

for ref in "${REF_NAMES[@]}"; do
    genome=${REF_GENOME[$ref]}; annot=${REF_ANNOT[$ref]}
    [ -r "$genome" ] || die "$ref: cannot read genome $genome"
    [ -r "$annot" ] || die "$ref: cannot read gene models $annot"
    R=$OUT_DIR/refdb/$ref
    mkdir -p "$R"

    if [[ "$genome" == *.gz ]]; then
        if ! uptodate "$R/genome.fa" "$genome"; then
            log "$ref: unpacking genome"
            rm -f "$R/genome.fa"
            zcat "$genome" > "$R/genome.fa.tmp"; finish "$R/genome.fa.tmp" "$R/genome.fa"
        fi
    elif [ "$(readlink "$R/genome.fa" 2>/dev/null)" != "$genome" ]; then
        ln -sfn "$genome" "$R/genome.fa"
    fi
    if ! uptodate "$R/genome.mmi" "$genome"; then
        log "$ref: minimap2 index"
        minimap2 -t "$THREADS" -d "$R/genome.mmi.tmp" "$R/genome.fa"
        finish "$R/genome.mmi.tmp" "$R/genome.mmi"
    fi
    if ! uptodate "$R/anno.gtf" "$annot"; then
        log "$ref: gene models to GTF"
        zcat -f "$annot" | gffread -T > "$R/anno.gtf.tmp"; finish "$R/anno.gtf.tmp" "$R/anno.gtf"
    fi
    if ! uptodate "$R/junctions.bed" "$R/anno.gtf"; then
        paftools.js gff2bed "$R/anno.gtf" > "$R/junctions.bed.tmp"; finish "$R/junctions.bed.tmp" "$R/junctions.bed"
    fi
    t2gfile=${REF_T2G[$ref]:-}; [ -r "$t2gfile" ] || t2gfile=
    if ! uptodate "$R/exons.gtf" "$R/anno.gtf" ${t2gfile:+"$t2gfile"}; then
        # exon lines, transcript_id dropped, one line per distinct exon of each gene.
        # De-duplicate whole lines: RosettaStone v2 used "sort -u -k1,1 -k4,4n -k5,5n", which
        # keeps one line per coordinate, so a gene sharing an exon with another gene lost it.
        # With a transcript2gene file (the datastore's, = MOOP's feature IDs), gene IDs are
        # renamed to that file's: gene models often use other IDs (RefSeq "gene-X"/"rna-X",
        # Ensembl "gene:X" without the version). Matched via the transcript, ignoring such
        # prefixes and a ".N" version; genes that are not in the file (so not in MOOP) are dropped.
        awk -F'\t' -v OFS='\t' -v t2g="$t2gfile" -v rep="$R/exons.rename.log" '
            function key(x) { sub(/^(transcript|gene|mRNA|rna|cds)[:-]/, "", x); sub(/\.[0-9]+$/, "", x); return x }
            function val(s, k,   re) {
                re = k " \"[^\"]+\""
                if (!match(s, re)) return ""
                s = substr(s, RSTART, RLENGTH); sub(/^[^"]*"/, "", s); sub(/"$/, "", s); return s }
            BEGIN { if (t2g != "") while ((getline line < t2g) > 0) {
                        split(line, f, "\t"); if (f[2] == "") continue
                        tg[key(f[1])] = f[2]; gg[key(f[2])] = f[2] } }
            $3 != "exon" { next }
            t2g == "" { sub(/transcript_id [^;]*; /, "", $9); print; next }
            {   t = val($9, "transcript_id"); g = val($9, "gene_id")
                if (key(t) in tg) new = tg[key(t)]
                else if (key(g) in gg) new = gg[key(g)]
                else { dropped[g] = 1; next }
                kept[new] = 1
                print $1, $2, $3, $4, $5, $6, $7, $8, "gene_id \"" new "\";" }
            END { if (t2g != "") {
                    for (g in kept) nk++; for (g in dropped) nd++
                    printf "gene IDs from %s: %d genes kept; %d gene-model genes not in it, dropped\n", t2g, nk, nd > rep } }' \
            "$R/anno.gtf" | LC_ALL=C sort -k1,1 -k4,4n -k5,5n | uniq > "$R/exons.gtf.tmp"
        finish "$R/exons.gtf.tmp" "$R/exons.gtf"
        [ -s "$R/exons.rename.log" ] && log "$ref: $(cat "$R/exons.rename.log")"
    fi
    if ! uptodate "$R/t2g.tsv" "$annot" ${t2gfile:+"$t2gfile"}; then
        if [ -n "$t2gfile" ]; then
            # the gene set's own transcript2gene: the IDs MOOP uses
            awk -F'\t' -v OFS='\t' 'NF >= 2 && $2 != "" {print $1, $2; print $2, $2}' "$t2gfile" \
                | LC_ALL=C sort -u > "$R/t2g.tsv.tmp"
        else
        # From the original gene models, not anno.gtf: gffread drops some transcript types
        # (e.g. WormBase non-coding "nontranslating_CDS"). GFF3: a feature whose Parent is a
        # top-level feature (one with no Parent) is a transcript of that gene. GTF:
        # transcript_id -> gene_id.
        zcat -f "$annot" | awk -F'\t' -v OFS='\t' '
            function attr(s, k,   re) {
                re = "(^|;)[ ]*" k "=[^;]*"
                if (!match(s, re)) return ""
                s = substr(s, RSTART, RLENGTH); sub(/^;?[ ]*[^=]*=/, "", s); return s }
            function gtf(s, k,   re) {
                re = k " \"[^\"]+\""
                if (!match(s, re)) return ""
                s = substr(s, RSTART, RLENGTH); sub(/^[^"]*"/, "", s); sub(/"$/, "", s); return s }
            function clean(x) { sub(/^(transcript|gene):/, "", x); return x }
            /^#/ || NF < 9 { next }
            $9 ~ /(^|;)[ ]*ID=/ {
                id = attr($9, "ID"); par = attr($9, "Parent")
                if (par == "") top[id] = 1
                else { n = split(par, ps, ","); for (i = 1; i <= n; i++) kids[id] = kids[id] SUBSEP ps[i] }
                next }
            { t = gtf($9, "transcript_id"); g = gtf($9, "gene_id")
              if (t != "" && g != "") { print clean(t), clean(g); print clean(g), clean(g) } }
            END {
                for (id in kids) {
                    n = split(substr(kids[id], 2), ps, SUBSEP)
                    for (i = 1; i <= n; i++) if (ps[i] in top) { print clean(id), clean(ps[i]); print clean(ps[i]), clean(ps[i]) }
                } }' | LC_ALL=C sort -u > "$R/t2g.tsv.tmp"
        fi
        finish "$R/t2g.tsv.tmp" "$R/t2g.tsv"
    fi
    log "$ref: ready ($R)"
done
log "step 01 done"
