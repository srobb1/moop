#!/usr/bin/bash
# DIAMOND evidence for a clade run: every member's proteins against every other member's, both directions.
# build_clade_homologs.py reads the results to put the sequence evidence on each ortholog pair
# (E-value, bitscore, coverage of both proteins, support label). OrthoFinder 3 keeps no search results.
#
#   run_clade_diamond.sh <run folder>        # e.g. orthofinder_v2/planarians (uses its input/*.fa)
#
# Writes <run folder>/diamond/<A>__<B>.tsv.gz (A = query member, B = database member), columns:
#   qseqid sseqid evalue bitscore qstart qend sstart send qlen slen pident
# E-value <= 1e-5, up to 25 targets per query; the builder applies its own stricter rule.
# Searches already done are skipped, so a rerun after a failure or a new member only does what is missing.
# Works in /scratch and copies each finished file back.
set -euo pipefail
RUN=$(cd "${1:?usage: run_clade_diamond.sh <run folder>}" && pwd)
CPUS=${SLURM_CPUS_PER_TASK:-${CPUS:-4}}
JOB=${SLURM_JOB_ID:-$$}
MODULE=${DIAMOND_MODULE:-diamond/2.1.13}
EVALUE=${DIAMOND_EVALUE:-1e-5}
WORK=/scratch/$USER/clade_diamond/$(basename "$RUN")_$JOB
log() { echo "[$(date '+%F %T')] $*"; }

[ -d "$RUN/input" ] || { echo "ERROR: $RUN/input missing"; exit 1; }
command -v module >/dev/null || source /etc/profile.d/modules.sh
module load "$MODULE"
mkdir -p "$WORK" "$RUN/diamond"
trap 'echo "ERROR: failed; scratch folder kept: $WORK"' ERR

# outgroups (outgroups.tsv, make_clade_set.py --outgroup) are in input/ for OrthoFinder only: no searches
OUTGROUPS=()
[ -f "$RUN/outgroups.tsv" ] && mapfile -t OUTGROUPS < <(tail -n +2 "$RUN/outgroups.tsv" | cut -f1)
mapfile -t MEMBERS < <(cd "$RUN/input" && ls *.fa | sed 's/\.fa$//' | grep -vxF -f <(printf '%s\n' "${OUTGROUPS[@]}" ""))
[ ${#OUTGROUPS[@]} -gt 0 ] && log "outgroups, not searched: ${OUTGROUPS[*]}"
[ ${#MEMBERS[@]} -ge 2 ] || { echo "ERROR: fewer than 2 members in $RUN/input"; exit 1; }
log "$(basename "$RUN"): ${#MEMBERS[@]} members, $CPUS threads, E <= $EVALUE"

for m in "${MEMBERS[@]}"; do
    diamond makedb --in "$RUN/input/$m.fa" -d "$WORK/$m" --quiet
done
n=0
for a in "${MEMBERS[@]}"; do
    for b in "${MEMBERS[@]}"; do
        [ "$a" = "$b" ] && continue
        out="$RUN/diamond/${a}__${b}.tsv.gz"
        [ -s "$out" ] && continue
        log "$a vs $b"
        diamond blastp --sensitive -q "$RUN/input/$a.fa" -d "$WORK/$b" -o "$WORK/${a}__${b}.tsv" \
            -p "$CPUS" -e "$EVALUE" -k 25 --quiet \
            -f 6 qseqid sseqid evalue bitscore qstart qend sstart send qlen slen pident
        gzip -c "$WORK/${a}__${b}.tsv" > "$out.tmp"
        mv "$out.tmp" "$out"
        rm -f "$WORK/${a}__${b}.tsv"
        n=$((n + 1))
    done
done
{
    echo -e "key\tvalue"
    echo -e "program\tdiamond $(diamond version 2>/dev/null | awk '{print $NF}')"
    echo -e "mode\tblastp --sensitive -k 25"
    echo -e "evalue\t$EVALUE"
    echo -e "members\t${#MEMBERS[@]}"
    echo -e "finished\t$(date '+%FT%T')"
} > "$RUN/diamond/diamond.tsv"
rm -rf "$WORK"
log "done: $n searches run, $(ls "$RUN"/diamond/*__*.tsv.gz | wc -l) files in $RUN/diamond"
