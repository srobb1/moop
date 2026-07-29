#!/bin/bash
# Sort, bgzip, and tabix-index uncompressed track files.
# Handles: .gff, .gff3, .gtf, .bed, .bedGraph, .bg, .vcf
# Skips files whose .gz index is NEWER than the source. A stale index is rebuilt.
#
# Auto-fixes applied during processing:
#   - GFF3 ##FASTA embedded sequences stripped (tabix can't index them)
#   - Records where end < start: coordinates swapped (logged as NOTE)
#   - Lines with non-numeric start/end skipped with a WARNING count
#   - Contig sets too large for tbi: automatically retried with csi index
#
# Usage:
#   tabix_index_tracks.sh [directory]           # default: current directory
#   tabix_index_tracks.sh --force [directory]   # rebuild even if current
#
# Set BGZIP and TABIX env vars to override tool paths:
#   BGZIP=~/bin/bgzip TABIX=~/bin/tabix tabix_index_tracks.sh /path/to/tracks

BGZIP=${BGZIP:-bgzip}
TABIX=${TABIX:-tabix}

FORCE=false
SEARCH_DIR=.
while [ $# -gt 0 ]; do
    case "$1" in
        --force) FORCE=true ;;
        -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
        -*) echo "Unknown option: $1" >&2; exit 1 ;;
        *) SEARCH_DIR="$1" ;;
    esac
    shift
done

if ! command -v "$BGZIP" &>/dev/null; then
    echo "ERROR: bgzip not found (set BGZIP=/path/to/bgzip)" >&2
    exit 1
fi
if ! command -v "$TABIX" &>/dev/null; then
    echo "ERROR: tabix not found (set TABIX=/path/to/tabix)" >&2
    exit 1
fi

## FRESHNESS, not existence -- and that distinction is the whole point of this check.
##
## This used to be "does a .gz exist?", which is an INPUT test, so the script could not
## do the one job it exists for: when a gene set is rebuilt the source GFF changes and
## the .gz must be regenerated, but the .gz was still sitting there and every file was
## skipped. Nothing reported it, because SKIP reads like success.
##
## Measured 2026-07-29: 41 of 69 JBrowse gene tracks were serving indexes older than
## their own GFF -- some by two months. Two of them (the Bradypodion pair, whose feature
## IDs had just been shortened) were serving IDs that no longer existed in the database
## at all, so every gene-page link into JBrowse missed.
##
## -nt follows symlinks, which matters here: data/genomes/*/*/*/annotations.gff3 is a
## symlink into organisms/, so the comparison is against the real GFF's mtime.
index_is_current() {
    local f="$1"
    $FORCE && return 1
    [[ -f "${f}.gz" ]] || return 1
    [[ -f "${f}.gz.tbi" || -f "${f}.gz.csi" ]] || return 1
    [[ "${f}.gz" -nt "$f" ]]
}

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

    if index_is_current "$f"; then
        echo "SKIP (index is current): $f"
        return 2
    fi
    # A regenerated .gz invalidates whichever index kind was there before; leaving a
    # stale .csi beside a fresh .tbi would let JBrowse read the old one.
    rm -f "${f}.gz.tbi" "${f}.gz.csi"

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
    ) > "$sorted" || { echo "ERROR: could not write $sorted (permission?)" >&2; rm -f "$sorted"; return 1; }

    echo "Compressing: $f"
    if ! "$BGZIP" -c "$sorted" > "${f}.gz"; then
        echo "ERROR: bgzip failed for $f (permission?)" >&2
        rm -f "$sorted"
        return 1
    fi
    rm -f "$sorted"

    echo "Indexing: ${f}.gz"
    if try_tabix "$fmt" "${f}.gz"; then
        local idx
        [[ -f "${f}.gz.csi" ]] && idx="csi" || idx="tbi"
        echo "Done: ${f}.gz + ${f}.gz.${idx}"
        return 0
    fi
    echo "ERROR: tabix indexing failed for ${f}.gz" >&2
    rm -f "${f}.gz.tbi" "${f}.gz.csi"
    return 1
}

process_bed() {
    local f="$1"

    if index_is_current "$f"; then
        echo "SKIP (index is current): $f"
        return 2
    fi
    # A regenerated .gz invalidates whichever index kind was there before; leaving a
    # stale .csi beside a fresh .tbi would let JBrowse read the old one.
    rm -f "${f}.gz.tbi" "${f}.gz.csi"

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
    | sort -k1,1 -k2,2n > "$sorted" || { echo "ERROR: could not write $sorted (permission?)" >&2; rm -f "$sorted"; return 1; }

    echo "Compressing: $f"
    if ! "$BGZIP" -c "$sorted" > "${f}.gz"; then
        echo "ERROR: bgzip failed for $f (permission?)" >&2
        rm -f "$sorted"
        return 1
    fi
    rm -f "$sorted"

    echo "Indexing: ${f}.gz"
    if try_tabix bed "${f}.gz"; then
        local idx
        [[ -f "${f}.gz.csi" ]] && idx="csi" || idx="tbi"
        echo "Done: ${f}.gz + ${f}.gz.${idx}"
        return 0
    fi
    echo "ERROR: tabix indexing failed for ${f}.gz" >&2
    rm -f "${f}.gz.tbi" "${f}.gz.csi"
    return 1
}

process_vcf() {
    local f="$1"

    if index_is_current "$f"; then
        echo "SKIP (index is current): $f"
        return 2
    fi
    # A regenerated .gz invalidates whichever index kind was there before; leaving a
    # stale .csi beside a fresh .tbi would let JBrowse read the old one.
    rm -f "${f}.gz.tbi" "${f}.gz.csi"

    echo "Compressing: $f"
    if ! "$BGZIP" -c "$f" > "${f}.gz"; then
        echo "ERROR: bgzip failed for $f (permission?)" >&2
        return 1
    fi

    echo "Indexing: ${f}.gz"
    if try_tabix vcf "${f}.gz"; then
        local idx
        [[ -f "${f}.gz.csi" ]] && idx="csi" || idx="tbi"
        echo "Done: ${f}.gz + ${f}.gz.${idx}"
        return 0
    fi
    echo "ERROR: tabix indexing failed for ${f}.gz" >&2
    rm -f "${f}.gz.tbi" "${f}.gz.csi"
    return 1
}

## Counts, and a non-zero exit when anything failed.
##
## This script used to print "All done." and exit 0 unconditionally -- including the
## run where every single file failed with "Permission denied". Anything driving it
## (a housekeeping task, an admin button, the pipeline) would have reported success.
## That is the same defect copy2moop_v2.sh had, and it is worth more here because the
## caller is a UI that tells the user the job is finished.
N_INDEXED=0
N_SKIPPED=0
N_FAILED=0
FAILED_FILES=()

echo "Searching: $SEARCH_DIR"
echo "Tools: bgzip=$BGZIP  tabix=$TABIX"
echo ""

## `done < <(...)` below is process substitution, not a pipe, and that is load-bearing:
## it keeps this loop in the CURRENT shell so the counters survive it. A pipe would run
## the loop in a subshell and every count would come back zero.
while IFS= read -r -d '' f; do
    ext="${f##*.}"
    rc=0
    case "$ext" in
        gff|gff3|gtf)    process_gff "$f" "gff" || rc=$? ;;
        bed|bedGraph|bg) process_bed "$f"       || rc=$? ;;
        vcf)             process_vcf "$f"       || rc=$? ;;
        *) continue ;;
    esac
    case "$rc" in
        0) N_INDEXED=$(( N_INDEXED + 1 )) ;;
        2) N_SKIPPED=$(( N_SKIPPED + 1 )) ;;
        *) N_FAILED=$(( N_FAILED + 1 )); FAILED_FILES+=("$f") ;;
    esac
## -L DEREFERENCES symlinks, and without it this script could not see the files it
## exists to index. data/genomes/*/*/*/annotations.gff3 is a symlink into organisms/,
## and plain `-type f` matches regular files only -- so every JBrowse gene track was
## silently skipped by the find itself, before any of the logic below ran. Combined
## with the existence-not-freshness check above, the script was a no-op on precisely
## the data it was written for, and said "All done." while doing nothing.
##
## -L also drops broken symlinks, which is the behaviour we want: a dangling link has
## no GFF to index.
done < <(find -L "$SEARCH_DIR" -type f \( \
    -name "*.gff" -o -name "*.gff3" -o -name "*.gtf" \
    -o -name "*.bed" -o -name "*.bedGraph" -o -name "*.bg" \
    -o -name "*.vcf" \
\) -print0)

echo ""
echo "indexed: $N_INDEXED   skipped (current): $N_SKIPPED   FAILED: $N_FAILED"
for bad in ${FAILED_FILES+"${FAILED_FILES[@]}"}; do
    echo "  FAILED: $bad"
done
[ "$N_FAILED" -eq 0 ] || exit 1
echo "All done."
