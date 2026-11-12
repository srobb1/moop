#!/bin/bash
#
# oma_status_check.sh - report OMA pipeline progress for every organism run in OMA_v2
#
# Usage:  ./oma_status_check.sh [-n] [-p] [-w] [-c] [OMA_v2 root dir]   (default: OMA_V2 from oma.conf)
#           -n  skip oma-status (slurm logs only, faster)
#           -p  only show runs in progress (jobs in the queue)
#           -w  only show runs waiting (not complete, nothing queued - a step needs launching)
#           -c  only show complete runs
#         -p/-w/-c can be combined (e.g. -pw); with none of them all runs are shown
#
# A run dir is any <Organism>/<Assembly>/<Annotation> dir containing parameters.drw, Output or mapGO.
#
# How each step is judged:
#   part1  slurm/oma1-*.log says "Database conversion successfully finished"; an Error in the log = FAILED
#          (Cache/conversion.done is only used when there is no slurm log - it gets written before
#          later part1 checks such as OutgroupSpecies can still fail)
#   part2  an slurm/oma.*.out says "all jobs successfully finished", or `oma-status -i` shows EVERY genome
#          pair with finished chunks = expected chunks, and no oma2 job of the run is queued or running.
#          The summary line is rounded: 95,844 of 95,845 chunks prints "100.00%" while the last chunk
#          (CHACAL/NEMVE 940 of 941) is still being computed -- Chamaeleo MENDER_20260701, 2026-09-29 --
#          so "100.00%" alone is not done; a chunk that never started would hide the same way.
#   part3  Output/ has results (HierarchicalGroups.orthoxml etc.); a failed oma3 log is flagged; an oma3
#          log that found the all-vs-all unfinished ("still computing the all-vs-all") is flagged as
#          exited early -- that part3 did nothing and must be submitted again after part2 finishes
#   mapGO  mapGO/<PREFIX>.GO.tsv exists and is non-empty
#   template  "OMA template:" line of README.exportedAllAll (or its genome list); not $DEFAULT_TEMPLATE = legacy
#   COMPLETE = Output and mapGO both have results
#
# Jobs currently in squeue are matched to run dirs by their working directory.
# Slurm job ids come from the log file names (latest job last). Every line of a run's block
# starts with its organism prefix, so e.g.  ./oma_status_check.sh | grep ACRMIL  shows one run.

HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
OMA_CONF=${OMA_CONF:-$HERE/oma.conf}
[ -r "$OMA_CONF" ] || { echo "ERROR: no settings file $OMA_CONF (copy oma.conf.example to oma.conf)" >&2; exit 1; }
# shellcheck disable=SC1090
source "$OMA_CONF"
OMA_TEMPLATES=${OMA_TEMPLATES:-$OMA_V2/OMA_TEMPLATE_RUNS}
RUN_OMA_STATUS=1
# OMA_BIN and DEFAULT_TEMPLATE come from oma.conf; runs made from any other template are flagged "legacy"

SHOW=""        # states to show: any of "progress waiting complete"; empty = all

while getopts "npwc" opt; do
    case $opt in
        n) RUN_OMA_STATUS=0 ;;
        p) SHOW+=" progress" ;;
        w) SHOW+=" waiting" ;;
        c) SHOW+=" complete" ;;
        *) sed -n '5,10s/^# \{0,1\}//p' "$0" >&2; exit 1 ;;
    esac
done
shift $((OPTIND - 1))

# pwd -P: resolve symlinks (e.g. ~/sciproj -> /n/sci) so run dirs match squeue's working dirs
ROOT=$(cd "${1:-$OMA_V2}" && pwd -P)
TEMPLATE_DIR=$(cd "$OMA_TEMPLATES" && pwd -P)
TEMPLATE_NAME=$(basename "$TEMPLATE_DIR")

# reference species codes = every .fa in the template DB dirs; anything else in a run's DB is the target
REF_CODES=" $(ls "$TEMPLATE_DIR"/*/OMA.2.7.0/DB/*.fa 2>/dev/null | xargs -n1 basename | sed 's/\.fa$//' | sort -u | tr '\n' ' ') "

# running/pending jobs: "workdir|jobname|state|arrayjobid"
SQUEUE=$(squeue -u "$USER" -h -o "%Z|%j|%T|%F|%i" 2>/dev/null)

if [ -t 1 ]; then
    GRN=$'\e[32m'; RED=$'\e[31m'; YEL=$'\e[33m'; CYN=$'\e[36m'; BLD=$'\e[1m'; RST=$'\e[0m'
else
    GRN=; RED=; YEL=; CYN=; BLD=; RST=
fi

# does the file set given contain the pattern?
logs_have() {
    local pattern=$1; shift
    [ $# -gt 0 ] && grep -qs -- "$pattern" "$@"
}

jobs_running_in() {
    # prints e.g. "oma2 job 5314902: 2 running, 3 pending" for jobs whose workdir is $1.
    # squeue shows pending array tasks as ONE row ("5314902_[3-5]"), so tasks are counted
    # from the id, not by rows.
    echo "$SQUEUE" | awk -F'|' -v d="$1" '
        $1 == d {
            tasks = 1
            if (match($5, /_\[[^]]*\]/)) {
                spec = substr($5, RSTART + 2, RLENGTH - 3)
                sub(/%.*/, "", spec)                        # throttle, e.g. [3-5%10]
                tasks = 0
                n = split(spec, parts, ",")
                for (i = 1; i <= n; i++) {
                    if (split(parts[i], range, "-") == 2) tasks += range[2] - range[1] + 1
                    else tasks += 1
                }
            }
            key = $2 " job " $4
            count[key, tolower($3)] += tasks
            keys[key] = 1
        }
        END {
            first = 1
            for (key in keys) {
                line = key ":"
                sep = " "
                for (state in count) {
                    split(state, pieces, SUBSEP)
                    if (pieces[1] != key) continue
                    line = line sep count[state] " " pieces[2]
                    sep = ", "
                }
                printf "%s%s", (first ? "" : "; "), line
                first = 0
            }
        }'
}

# slurm job ids from log file names: job_ids <sed regex with \\( \\) around the id> files...
job_ids() {
    local re=$1; shift
    [ $# -gt 0 ] || return
    printf '%s\n' "$@" | xargs -n1 basename | sed -n "s/$re/\1/p" | sort -un | paste -sd, -
}

# template a run was copied from: the "OMA template:" line of its README.exportedAllAll,
# or for runs made before that line existed, the export's genome codes joined with _
run_template() {
    local readme=$1/README.exportedAllAll
    if [ ! -f "$readme" ]; then
        echo "unknown"
    elif grep -q '^OMA template:' "$readme"; then
        sed -n 's/^OMA template:[[:space:]]*//p' "$readme" | head -1
    else
        sed -n 's/^  - \([A-Z0-9]*\):.*/\1/p' "$readme" | sort | paste -sd_ -
    fi
}

jobs_label() {
    [ -z "$1" ] && return
    [[ $1 == *,* ]] && echo "  [jobs $1]" || echo "  [job $1]"
}

printf "OMA status for %s   (%s)\n\n" "$ROOT" "$(date '+%Y-%m-%d %H:%M')"

declare -a TODO

while IFS= read -r run; do
    rel=${run#$ROOT/}

    # a gene set that IS a reference genome (make_oma_db_files.pl wrote REFERENCE_GENOME.txt):
    # no run of its own -- its orthologs come from the template's reference run
    if [ -e "$run/REFERENCE_GENOME.txt" ]; then
        ref_code=$(sed -n 's/^reference:[[:space:]]*//p' "$run/REFERENCE_GENOME.txt")
        ref_identical=$(sed -n 's/^identical:[[:space:]]*//p' "$run/REFERENCE_GENOME.txt")
        ref_template=$(sed -n 's/^template:[[:space:]]*//p' "$run/REFERENCE_GENOME.txt")
        ref_run="$TEMPLATE_DIR/${ref_template:-$DEFAULT_TEMPLATE}/reference_run"
        next=""
        # nothing is ever run in this directory; the steps are those of the shared reference run
        # (listed once, under REFRUN, in the summary)
        ref_step=""
        if [ ! -d "$ref_run" ]; then
            ref_step="bash $HERE/make_reference_run.sh"
        elif ! grep -qs "Database conversion successfully finished" "$ref_run"/slurm/oma1-*.log; then
            ref_step="cd ${ref_run#$ROOT/} && sbatch oma-part1.sh"
        elif [ ! -s "$ref_run/Output/HierarchicalGroups.orthoxml" ]; then
            ref_step="cd ${ref_run#$ROOT/} && sbatch oma-part3.sh"
        elif [ ! -s "$ref_run/mapGO/go.tsv" ]; then
            ref_step="cd ${ref_run#$ROOT/}/mapGO && ./get_OMA_GO_terms.sh NEMVE"
        fi
        if [ -z "$ref_step" ]; then
            state=complete; overall="${GRN}${BLD}COMPLETE${RST} (reference genome: uses the shared reference run)"
        else
            state=waiting; overall="${RED}waiting${RST} for the shared reference run"
            next="nothing to run in THIS directory; the shared reference run needs: $ref_step"
        fi
        [ -n "$SHOW" ] && [[ $SHOW != *$state* ]] && continue
        printf "%-8s ${BLD}%s${RST}  => %s\n" "$ref_code" "$rel" "$overall"
        printf "%-8s    reference genome %s of %s (%s identical proteins); no OMA run of its own\n" \
            "$ref_code" "$ref_code" "${ref_template:-?}" "$ref_identical"
        # printed here, not added to the summary: the reference run lists its own step there
        [ -n "$next" ] && printf "%-8s    ${CYN}next:${RST}   %s\n" "$ref_code" "$next"
        echo
        continue
    fi

    # the template's reference run: reference genomes only, all-against-all precomputed
    is_reference_run=0
    [[ $rel == */reference_run ]] && is_reference_run=1

    # target organism prefix (e.g. DanRer, ACRMIL)
    prefix=""
    for fa in "$run"/DB/*.fa; do
        [ -e "$fa" ] || continue
        code=$(basename "$fa" .fa)
        [[ $REF_CODES == *" $code "* ]] || prefix=$code
    done
    # runs without a DB dir: take it from the mapGO output name instead
    if [ -z "$prefix" ]; then
        go_tsv=$(ls "$run"/mapGO/*.GO.tsv 2>/dev/null | head -1)
        [ -n "$go_tsv" ] && prefix=$(basename "$go_tsv" .GO.tsv)
    fi
    tag=${prefix:-UNKNOWN}
    [ $is_reference_run -eq 1 ] && tag=REFRUN

    running=$(jobs_running_in "$run")

    # ---- part 1
    shopt -s nullglob
    p1_logs=("$run"/slurm/oma1-*.log)
    p2_logs=("$run"/slurm/oma.*.out)
    p3_logs=("$run"/slurm/oma3.*.out)
    shopt -u nullglob
    # newest oma3 log first
    [ ${#p3_logs[@]} -gt 0 ] && mapfile -t p3_logs < <(ls -t "${p3_logs[@]}")
    # runs done elsewhere (e.g. Output/mapGO symlinked in) have no DB dir: parts 1-2 not applicable
    [ -d "$run/DB" ] && local_run=1 || local_run=0
    p1_jobs=$(job_ids '^oma1-\([0-9]*\)\.log$' "${p1_logs[@]}")
    p2_jobs=$(job_ids '^oma\.[0-9]*\.\([0-9]*\)\.out$' "${p2_logs[@]}")
    p3_jobs=$(job_ids '^oma3\.\([0-9]*\)\.out$' "${p3_logs[@]}")

    p1_failed=0
    if [ $local_run -eq 0 ]; then
        p1="-"; p1_ok=0
    elif [ ${#p1_logs[@]} -gt 0 ]; then
        # judge by the newest oma1 log
        p1log=$(ls -t "${p1_logs[@]}" | head -1)
        if grep -qs "Database conversion successfully finished" "$p1log"; then
            p1="${GRN}done${RST}"; p1_ok=1
        elif [[ $running != *oma1* ]] && grep -qs "^Error" "$p1log"; then
            p1="${RED}FAILED${RST}"; p1_ok=0; p1_failed=1
        else
            p1="${YEL}started${RST}"; p1_ok=0
        fi
    elif [ -e "$run/Cache/conversion.done" ]; then
        p1="${GRN}done${RST}*"; p1_ok=1          # * = inferred from Cache, no slurm log
    else
        p1="${RED}no${RST}"; p1_ok=0
    fi

    # ---- part 3 (Output)
    p3_ok=0; p3_failed=0; p3_early=0
    if [ -s "$run/Output/HierarchicalGroups.orthoxml" ] || [ -s "$run/Output/OrthologousGroups.txt" ]; then
        p3_ok=1; p3="${GRN}done${RST}"
    elif [ ${#p3_logs[@]} -gt 0 ]; then
        latest=${p3_logs[0]}
        if [[ $running != *oma3* ]] && ! grep -qs "Done!!" "$latest" && grep -qs "^Error" "$latest"; then
            p3_failed=1; p3="${RED}FAILED${RST}"
        elif [[ $running != *oma3* ]] && grep -qs "still computing the all-vs-all" "$latest"; then
            # part3 started while part2 was still computing: OMA stops at once (exit 0) and does nothing
            p3_early=1; p3="${RED}exited early${RST} (all-vs-all was not finished; submit part3 again after part2)"
        else
            p3="${YEL}started${RST}"
        fi
    else
        p3="${RED}no${RST}"
    fi

    # ---- mapGO
    go_ok=0
    if [ -n "$prefix" ] && [ -s "$run/mapGO/$prefix.GO.tsv" ]; then
        go_ok=1
    elif [ -z "$prefix" ] && compgen -G "$run/mapGO/*.GO.tsv" >/dev/null; then
        go_ok=1
    fi
    [ $go_ok -eq 1 ] && go="${GRN}done${RST}" || go="${RED}no${RST}"
    # the reference run only needs mapGO/go.tsv (GO names), for gene sets that are a reference genome
    if [ $is_reference_run -eq 1 ]; then
        if [ -s "$run/mapGO/go.tsv" ]; then
            go_ok=1; go="${GRN}done${RST} (go.tsv)"
        else
            go_ok=0; go="${RED}no${RST} (go.tsv needed)"
        fi
    fi

    # ---- state filter (-p/-w/-c)
    if [ $p3_ok -eq 1 ] && [ $go_ok -eq 1 ]; then state=complete
    elif [ -n "$running" ]; then state=progress
    else state=waiting
    fi
    [ -n "$SHOW" ] && [[ $SHOW != *$state* ]] && continue

    # ---- part 2
    p2_ok=0; pct=""; started=""
    if logs_have "all jobs successfully finished" "${p2_logs[@]}"; then
        p2_ok=1
    fi
    if [ $RUN_OMA_STATUS -eq 1 ] && [ -d "$run/Cache/DB" ] && [ -d "$run/DB" ]; then
        status_out=$(cd "$run" && timeout 300 "$OMA_BIN/oma-status" -i . 2>/dev/null)
        pct=$(echo "$status_out" | sed -n 's/^Nr chunks finished: [0-9]* (\(.*\))$/\1/p')
        # per genome pair: Genome1 Genome2 Exported Expected Finished Started Fraction. The summary
        # percentage is rounded, so done = every pair finished = expected, and no oma2 job left.
        unfinished=$(echo "$status_out" | awk -F'\t' 'NF >= 7 && $4 ~ /^[0-9]+$/ && $5 + 0 < $4 + 0 {
                                                        n++; missing += $4 - $5; if (pairs == "") pairs = $1 "/" $2; else pairs = pairs "," $1 "/" $2 }
                                                      END { if (n) print missing " chunk(s) in " pairs }')
        n_pairs=$(echo "$status_out" | awk -F'\t' 'NF >= 7 && $4 ~ /^[0-9]+$/' | wc -l)
        if [ "$n_pairs" -gt 0 ] && [ -z "$unfinished" ] && [[ $running != *oma2* ]]; then
            p2_ok=1
        elif [ -n "$unfinished" ]; then
            pct="${pct:+$pct shown, but }$unfinished not finished"
        fi
    fi
    if [ $local_run -eq 0 ]; then
        p2="-"
    elif [ $is_reference_run -eq 1 ]; then
        p2="- (not needed: all-against-all precomputed in the template)"
    elif [ $p2_ok -eq 1 ]; then
        p2="${GRN}done${RST}"
    elif [ ${#p2_logs[@]} -gt 0 ] || [[ $running == *oma2* ]]; then
        p2="${YEL}${pct:-started}${RST}"
    elif [ -n "$pct" ] && [ "$pct" != "0.00%" ]; then
        # template ships the reference-vs-reference all-vs-all, so this is not zero before part2 runs
        p2="${RED}no${RST} (${pct} pre-computed from template)"
    else
        p2="${RED}no${RST}"
    fi


    # ---- overall + next step
    # next step = the one after the furthest part that is running or done;
    # if that part is still running, the next job is submitted with a slurm dependency on it
    p2_run_ids=$(echo "$SQUEUE" | awk -F'|' -v d="$run" '$1==d && $2 ~ /^oma2/ {print $4}' | sort -u | paste -sd: -)
    p3_running=0; [[ $running == *oma3* ]] && p3_running=1
    next=""
    if [ $state = complete ]; then
        overall="${GRN}${BLD}COMPLETE${RST}"
    else
        # in progress = something in the queue; waiting = nothing queued, a step needs launching
        [ $state = progress ] && overall="${YEL}in progress${RST}" || overall="${RED}waiting${RST}"
        p1_run_ids=$(echo "$SQUEUE" | awk -F'|' -v d="$run" '$1==d && $2 ~ /^oma1/ {print $4}' | sort -u | paste -sd: -)
        if [ $p3_failed -eq 1 ]; then
            next="part3 failed: $(grep -m1 -A1 '^Error' "${p3_logs[0]}" | tr -s ' \n' ' ')(see $rel/slurm/$(basename "${p3_logs[0]}"))"
        elif [ $is_reference_run -eq 1 ] && { [ $p3_ok -eq 1 ] || [ $p3_running -eq 1 ]; }; then
            # any reference code works: only go.tsv (GO term names) is used
            next="cd $rel/mapGO && ./get_OMA_GO_terms.sh NEMVE   (makes go.tsv for gene sets that are a reference genome)"
            [ $p3_ok -eq 0 ] && next="$next   (after part3 finishes)"
        elif [ $is_reference_run -eq 1 ] && [ $p1_ok -eq 1 ]; then
            next="cd $rel && sbatch oma-part3.sh   (reference run: skip part2)"
        elif [ $is_reference_run -eq 1 ] && [ -n "$p1_run_ids" ]; then
            next="cd $rel && sbatch --dependency=afterok:$p1_run_ids oma-part3.sh   (reference run: skip part2)"
        elif [ $p3_ok -eq 1 ] || [ $p3_running -eq 1 ]; then
            next="cd $rel/mapGO && ./get_OMA_GO_terms.sh ${prefix:-<PREFIX>}"
            [ $p3_ok -eq 0 ] && next="$next   (after part3 finishes)"
        elif [ $p2_ok -eq 1 ]; then
            next="cd $rel && sbatch oma-part3.sh"
            [ $p3_early -eq 1 ] && next="$next   (again: the earlier part3 exited early)"
        elif [ -n "$p2_run_ids" ]; then
            next="cd $rel && sbatch --dependency=afterok:$p2_run_ids oma-part3.sh"
            [ $p3_early -eq 1 ] && next="$next   (again: the earlier part3 exited early)"
        elif [ ${#p2_logs[@]} -gt 0 ]; then
            # part2 ran but not to 100%: part3 (plain 'oma') finishes any remaining chunks first
            next="cd $rel && sbatch oma-part3.sh"
        elif [ $p1_failed -eq 1 ]; then
            next="part1 failed: $(grep -m1 -A1 '^Error' "$p1log" | tr -s ' \n' ' ')(see $rel/slurm/$(basename "$p1log"))"
        elif [ $p1_ok -eq 1 ]; then
            next="cd $rel && sbatch $(basename "$(ls "$run"/oma-part2*.sh 2>/dev/null | head -1)")"
        elif [[ $running == *oma1* ]]; then
            next="wait for part1 to finish"
        else
            next="cd $rel && sbatch oma-part1.sh"
        fi
    fi

    template=$(run_template "$run")
    if [ "$template" = "$DEFAULT_TEMPLATE" ]; then
        template_label="$template"
    else
        template_label="${RED}${template} (legacy)${RST}"
        [ -n "$next" ] && next="$next   [legacy template: consider restarting on $DEFAULT_TEMPLATE]"
    fi

    printf "%-8s ${BLD}%s${RST}  => %s\n" "$tag" "$rel" "$overall"
    printf "%-8s    template: %s\n" "$tag" "$template_label"
    printf "%-8s    part1:  %s%s\n" "$tag" "$p1" "$(jobs_label "$p1_jobs")"
    printf "%-8s    part2:  %s%s\n" "$tag" "$p2" "$(jobs_label "$p2_jobs")"
    printf "%-8s    part3:  %s%s\n" "$tag" "$p3" "$(jobs_label "$p3_jobs")"
    printf "%-8s    mapGO:  %s\n"   "$tag" "$go"
    [ -n "$running" ] && printf "%-8s    queue:  %s\n" "$tag" "$running"
    [ -n "$next" ] && printf "%-8s    ${CYN}next:${RST}   %s\n" "$tag" "$next" && TODO+=("$(printf '%-8s %s: %s' "$tag" "$rel" "$next")")
    echo

done < <( { find "$ROOT" -mindepth 1 -maxdepth 4 \
                \( -name "$TEMPLATE_NAME" -o -name ToyExample \) -prune -o \
                \( -name parameters.drw -o -name Output -o -name mapGO -o -name REFERENCE_GENOME.txt \) -printf '%h\n'
              # each template's reference run
              for reference_run in "$TEMPLATE_DIR"/*/reference_run; do
                  [ -d "$reference_run" ] && echo "$reference_run"
              done; } | sort -u)

# organism dirs with no run dir at all (only in the unfiltered view)
[ -z "$SHOW" ] && for org in "$ROOT"/*/; do
    org=${org%/}
    [ "$(basename "$org")" = "$TEMPLATE_NAME" ] && continue
    if [ -z "$(find "$org" -maxdepth 3 \( -name parameters.drw -o -name Output -o -name mapGO -o -name REFERENCE_GENOME.txt \) -print -quit)" ]; then
        printf "%-8s ${BLD}%s${RST}  => ${RED}no OMA run directory found${RST}\n\n" "NONE" "${org#$ROOT/}"
    fi
done

echo "================ Summary: steps to launch / check ================"
if [ ${#TODO[@]} -eq 0 ]; then
    [ -z "$SHOW" ] && echo "Everything is complete." || echo "Nothing to launch for the runs shown."
else
    printf '%s\n' "${TODO[@]}"
fi
[ $RUN_OMA_STATUS -eq 0 ] && echo "(oma-status skipped: part2 judged from slurm logs only)"
echo "('-' = run was done outside this directory, e.g. Output/mapGO symlinked in)"
exit 0
