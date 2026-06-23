#!/bin/bash
# Sort, bgzip, and tabix-index uncompressed track files.
# Handles: .gff, .gff3, .gtf, .bed, .bedGraph, .bg, .vcf
# Skips files that already have a matching .gz.tbi or .gz.csi pair.
#
# Auto-fixes applied during processing:
#   - GFF3 ##FASTA embedded sequences stripped (tabix can't index them)
#   - Records where end < start: coordinates swapped (logged as NOTE)
#   - Lines with non-numeric start/end skipped with a WARNING count
#   - Contig sets too large for tbi: automatically retried with csi index
#
# Usage:
#   tabix_index_tracks.sh [directory]   # default: current directory
#
# Set BGZIP and TABIX env vars to override tool paths:
#   BGZIP=~/bin/bgzip TABIX=~/bin/tabix tabix_index_tracks.sh /path/to/tracks

BGZIP=${BGZIP:-bgzip}
TABIX=${TABIX:-tabix}
SEARCH_DIR=${1:-.}

if ! command -v "$BGZIP" &>/dev/null; then
    echo "ERROR: bgzip not found (set BGZIP=/path/to/bgzip)" >&2
    exit 1
fi
if ! command -v "$TABIX" &>/dev/null; then
    echo "ERROR: tabix not found (set TABIX=/path/to/tabix)" >&2
    exit 1
fi

# Run tabix; if tbi fails due to coordinate range overflow, retry with csi.
try_tabix() {
    local fmt="$1"
    local gz="$2"
    local errtmp
    errtmp=$(mktemp)

    if "$TABIX" -p "$fmt" "$gz" 2>"$errtmp"; then
        rm -f "$errtmp"
        return 0
    fi

    if grep -q "cannot be stored in a tbi index" "$errtmp"; then
        echo "  (tbi range exceeded — retrying with csi)" >&2
        rm -f "${gz}.tbi" "$errtmp"
        "$TABIX" --csi -p "$fmt" "$gz"
        return $?
    fi

    cat "$errtmp" >&2
    rm -f "$errtmp"
    return 1
}

process_gff() {
    local f="$1"
    local fmt="$2"

    if [[ -f "${f}.gz" && ( -f "${f}.gz.tbi" || -f "${f}.gz.csi" ) ]]; then
        echo "SKIP (already indexed): $f"
        return
    fi

    echo "Sorting: $f"
    local sorted="${f}.sorted.tmp"

    # 1. Preserve ##gff-version header at top of output.
    # 2. Strip ##FASTA section and everything after it; skip blank lines and comments.
    # 3. Validate that start (col4) and end (col5) are integers; skip malformed lines.
    # 4. Swap start/end when end < start (e.g. primer pair GFFs stored in reverse).
    # 5. Sort by chromosome then start position.
    (
        grep "^##gff-version" "$f"
        awk 'BEGIN{FS="\t"}
             /^##FASTA/ { exit }
             /^[[:space:]]*$/ || /^#/ { next }
             1' "$f" \
        | awk -v fname="$f" 'BEGIN{FS="\t"; OFS="\t"}
            NF < 5 || !($4 ~ /^[0-9]+$/ && $5 ~ /^[0-9]+$/) {
                bad++; next
            }
            $4+0 < 1 || $5+0 < 1 {
                zero++; next
            }
            {
                if ($4+0 > $5+0) { t=$4; $4=$5; $5=t; fixed++ }
                print
            }
            END {
                if (bad   > 0) print "  WARNING: skipped " bad   " malformed line(s) (non-numeric start/end) in " fname > "/dev/stderr"
                if (zero  > 0) print "  WARNING: skipped " zero  " line(s) with coordinate <= 0 (invalid GFF3) in " fname > "/dev/stderr"
                if (fixed > 0) print "  NOTE: swapped start>end in " fixed " record(s) in " fname > "/dev/stderr"
            }' \
        | sort -k1,1 -k4,4n
    ) > "$sorted"

    echo "Compressing: $f"
    "$BGZIP" -c "$sorted" > "${f}.gz"
    rm "$sorted"

    echo "Indexing: ${f}.gz"
    if try_tabix "$fmt" "${f}.gz"; then
        local idx
        [[ -f "${f}.gz.csi" ]] && idx="csi" || idx="tbi"
        echo "Done: ${f}.gz + ${f}.gz.${idx}"
    else
        echo "ERROR: tabix indexing failed for ${f}.gz" >&2
        rm -f "${f}.gz.tbi" "${f}.gz.csi"
    fi
}

process_bed() {
    local f="$1"

    if [[ -f "${f}.gz" && ( -f "${f}.gz.tbi" || -f "${f}.gz.csi" ) ]]; then
        echo "SKIP (already indexed): $f"
        return
    fi

    echo "Sorting: $f"
    local sorted="${f}.sorted.tmp"

    # Strip track/browser/comment headers; swap chromEnd < chromStart records.
    grep -v "^track\|^browser\|^#" "$f" \
    | awk 'BEGIN{FS="\t"; OFS="\t"}
        NF < 3 { next }
        {
            if ($2 ~ /^[0-9]+$/ && $3 ~ /^[0-9]+$/ && $2+0 > $3+0) {
                t=$2; $2=$3; $3=t; fixed++
            }
            print
        }
        END { if (fixed > 0) print "  NOTE: swapped start>end in " fixed " record(s)" > "/dev/stderr" }
    ' \
    | sort -k1,1 -k2,2n > "$sorted"

    echo "Compressing: $f"
    "$BGZIP" -c "$sorted" > "${f}.gz"
    rm "$sorted"

    echo "Indexing: ${f}.gz"
    if try_tabix bed "${f}.gz"; then
        local idx
        [[ -f "${f}.gz.csi" ]] && idx="csi" || idx="tbi"
        echo "Done: ${f}.gz + ${f}.gz.${idx}"
    else
        echo "ERROR: tabix indexing failed for ${f}.gz" >&2
        rm -f "${f}.gz.tbi" "${f}.gz.csi"
    fi
}

process_vcf() {
    local f="$1"

    if [[ -f "${f}.gz" && ( -f "${f}.gz.tbi" || -f "${f}.gz.csi" ) ]]; then
        echo "SKIP (already indexed): $f"
        return
    fi

    echo "Compressing: $f"
    "$BGZIP" -c "$f" > "${f}.gz"

    echo "Indexing: ${f}.gz"
    if try_tabix vcf "${f}.gz"; then
        local idx
        [[ -f "${f}.gz.csi" ]] && idx="csi" || idx="tbi"
        echo "Done: ${f}.gz + ${f}.gz.${idx}"
    else
        echo "ERROR: tabix indexing failed for ${f}.gz" >&2
        rm -f "${f}.gz.tbi" "${f}.gz.csi"
    fi
}

echo "Searching: $SEARCH_DIR"
echo "Tools: bgzip=$BGZIP  tabix=$TABIX"
echo ""

while IFS= read -r -d '' f; do
    ext="${f##*.}"
    case "$ext" in
        gff|gff3|gtf) process_gff "$f" "gff" ;;
        bed|bedGraph|bg) process_bed "$f" ;;
        vcf) process_vcf "$f" ;;
    esac
done < <(find "$SEARCH_DIR" -type f \( \
    -name "*.gff" -o -name "*.gff3" -o -name "*.gtf" \
    -o -name "*.bed" -o -name "*.bedGraph" -o -name "*.bg" \
    -o -name "*.vcf" \
\) -print0)

echo ""
echo "All done."
