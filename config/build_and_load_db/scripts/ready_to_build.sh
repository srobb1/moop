#!/usr/bin/bash
# ready_to_build.sh -- which active gene sets have a finished annotations/v2 run that
# MOOP has not built yet? Read-only: it submits nothing and writes no files.
#
# Per active gene set (same list as run_all_v2.sh, via list_active_genesets.sh):
#   NO_RUN       no $ANNOTATIONS/<org>/<asm>/<gs> run yet
#   RUN_PARTIAL  run exists but an analysis has no .done (missing ones listed)
#   NO_OMA       run done, but no OMA Output/ (or REFERENCE_GENOME.txt) or no mapGO GO file
#   NEW          ready, organism never built (no data/<org>/organism.sqlite)
#   REBUILD      ready, run finished after the organism's last build
#   BUILT        ready and already built since the run finished
#
# The build is per organism, so the closing list names each organism that has a
# NEW or REBUILD gene set, as sbatch lines. An organism is held back when another
# of its gene sets is RUN_PARTIAL or NO_OMA (its build would load an incomplete set);
# --include-partial lists it anyway.
#
# Usage:
#   bash scripts/ready_to_build.sh                  # table + sbatch lines
#   bash scripts/ready_to_build.sh | grep -E '^(NEW|REBUILD)'
#   bash scripts/ready_to_build.sh --include-partial

set -uo pipefail

SCRIPTS=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO=$(dirname "$SCRIPTS")
source "$SCRIPTS/paths.sh"

INCLUDE_PARTIAL=false
[ "${1:-}" = "--include-partial" ] && INCLUDE_PARTIAL=true

ANALYSES=(eggnog_mapper interproscan signalp6 deeptmhmm deeploc2)

LIST=$(mktemp)
trap 'rm -f "$LIST"' EXIT
bash "$SCRIPTS/list_active_genesets.sh" > "$LIST" || exit 1

declare -A ORG_READY ORG_BLOCKED

printf '%-12s %-30s %-40s %-28s %-17s %-17s %s\n' STATUS ORGANISM ASSEMBLY GENESET RUN_DONE BUILT DETAIL
while IFS=$'\t' read -r org asm gs; do
    [ -n "$org" ] || continue
    run="$ANNOTATIONS/$org/$asm/$gs"
    db="$REPO/data/$org/organism.sqlite"
    built=-; [ -f "$db" ] && built=$(date -r "$db" '+%Y-%m-%d %H:%M')
    status= ; detail= ; last=-

    if [ ! -d "$run" ]; then
        status=NO_RUN
    else
        missing=()
        for a in "${ANALYSES[@]}"; do [ -e "$run/$a/.done" ] || missing+=("$a"); done
        # every DIAMOND database the run started must have finished
        nd=0; ndone=0
        for d in "$run"/diamond/*/; do
            [ -d "$d" ] || continue
            nd=$((nd + 1)); [ -e "$d/.done" ] && ndone=$((ndone + 1))
        done
        [ "$nd" -gt 0 ] && [ "$ndone" -eq "$nd" ] || missing+=("diamond($ndone/$nd)")
        last_epoch=$(find "$run" -name .done -printf '%T@\n' 2>/dev/null | sort -n | tail -1)
        [ -n "$last_epoch" ] && last=$(date -d "@${last_epoch%.*}" '+%Y-%m-%d %H:%M')

        if [ ${#missing[@]} -gt 0 ]; then
            status=RUN_PARTIAL; detail="missing: ${missing[*]}"
        else
            oma="$OMA_BASE/$org/$asm/$gs"
            if [ -e "$oma/REFERENCE_GENOME.txt" ]; then
                detail="OMA reference genome"
            elif [ ! -d "$oma/Output" ]; then
                status=NO_OMA; detail="no $oma/Output"
            elif ! compgen -G "$oma/mapGO/*GO*" >/dev/null; then
                status=NO_OMA; detail="OMA done, mapGO not run"
            fi
            if [ -z "$status" ]; then
                if [ ! -f "$db" ]; then
                    status=NEW
                elif [ "${last_epoch%.*}" -gt "$(stat -c %Y "$db")" ]; then
                    status=REBUILD
                else
                    status=BUILT
                fi
            fi
        fi
    fi

    case $status in
        NEW|REBUILD) ORG_READY[$org]=1 ;;
        RUN_PARTIAL|NO_OMA) ORG_BLOCKED[$org]+="$gs($status) " ;;
    esac
    printf '%-12s %-30s %-40s %-28s %-17s %-17s %s\n' "$status" "$org" "$asm" "$gs" "$last" "$built" "$detail"
done < "$LIST"

echo
echo "# Organisms to build (from $REPO):"
n=0
for org in $(printf '%s\n' "${!ORG_READY[@]}" | sort); do
    if [ -n "${ORG_BLOCKED[$org]:-}" ] && ! $INCLUDE_PARTIAL; then
        echo "#   held back: $org -- also has ${ORG_BLOCKED[$org]}(use --include-partial to list it)"
        continue
    fi
    echo "MOOP_RELOAD=1 sbatch scripts/moop_process_genome_data_v2.sbatch $org"
    n=$((n + 1))
done
[ "$n" -gt 0 ] || echo "#   none"
