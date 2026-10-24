#!/usr/bin/bash
## Submit every pair in pairs.tsv that has not been done yet, one SLURM job each.
##
##   bash submit_all.sh             submit what is missing
##   bash submit_all.sh --dry-run   list what would be submitted, submit nothing
##   bash submit_all.sh --force     resubmit every pair, done or not
##
## A pair is done when $SYNTENY_OUT/published/<OrgA>__<OrgB>/done.<A>.<B>.<method>.txt
## exists (run_pair.sh writes it last, so a failed run never looks done).
## A pair that keeps failing: read its slurm_logs/synteny_<jobid>.log.
set -u
DRY_RUN=false
FORCE=false
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=true ;;
    --force)   FORCE=true ;;
    *) echo "Unknown argument: $arg" >&2; exit 1 ;;
  esac
done

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
cd "$SCRIPT_DIR"
source "$SCRIPT_DIR/settings.sh"
mkdir -p slurm_logs

submitted=0; skipped=0
while IFS=$'\t' read -r code_a code_b method preset; do
  case "$code_a" in \#*|'') continue ;; esac
  preset=${preset:-}
  org_a=$(awk -F'\t' -v c="$code_a" '$1 == c { split($2, p, "/"); print p[1] }' "$GENOMES_TSV")
  org_b=$(awk -F'\t' -v c="$code_b" '$1 == c { split($2, p, "/"); print p[1] }' "$GENOMES_TSV")
  if [ -z "$org_a" ] || [ -z "$org_b" ]; then
    echo "SKIP $code_a $code_b: not in $GENOMES_TSV" >&2; continue
  fi
  if [[ "$org_a" < "$org_b" ]]; then pair="${org_a}__${org_b}"; else pair="${org_b}__${org_a}"; fi
  done_file="$SYNTENY_OUT/published/$pair/done.$code_a.$code_b.$method.txt"
  ## Done = finished AND newer than both genomes' inputs. When the MOOP pipeline rebuilds a
  ## gene set (or its assembly), every pair using it is stale -- IDs, names and coordinates
  ## can all change -- and is rerun, instead of old results sitting there looking current.
  why=new
  if [ -e "$done_file" ]; then
    why=""
    for code in "$code_a" "$code_b"; do
      gs=$(awk -F'\t' -v c="$code" '$1 == c { print $2 }' "$GENOMES_TSV")
      for input in "$MOOP_DATA/$gs/genes.gff" "$MOOP_DATA/$gs/cds.nt.fa" "$MOOP_DATA/$gs/../genome.fa"; do
        if [ "$input" -nt "$done_file" ]; then why="$code rebuilt"; fi
      done
    done
    $FORCE && why=forced
    if [ -z "$why" ]; then
      skipped=$((skipped + 1)); continue
    fi
  fi
  if $DRY_RUN; then
    echo "would submit ($why): $code_a $code_b $method $preset"
  else
    sbatch --job-name="syn_${code_a}_${code_b}" run_pair.sh "$code_a" "$code_b" "$method" $preset
  fi
  submitted=$((submitted + 1))
done < "$PAIRS_TSV"
echo "$submitted to run, $skipped already done"
