#!/usr/bin/bash
# Remake geneNames.readable.tsv for built gene sets, in place, without a build: the step
# process_one_geneset.sh runs at the end (make_readable_gene_names.pl), for when its wording changes.
# Seconds per gene set. The database and the shipped files are not touched (this file is for people).
#
#   bash scripts/remake_readable_gene_names.sh                  # every active gene set that is built
#   bash scripts/remake_readable_gene_names.sh <organism> ...   # only these organisms
#
# Gene sets that take names from no other gene set go first, so a gene set whose names were inferred
# from a partner reads the partner's new file. Run from build_and_load_db/v2 (data/ is ./data).
set -uo pipefail
SCRIPTS=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO=$(cd "$SCRIPTS/.." && pwd)
# shellcheck disable=SC1091
source "$SCRIPTS/paths.sh"
DATA=${DATA:-$PWD/data}
[ -d "$DATA" ] || { echo "ERROR: no data folder $DATA (run from build_and_load_db/v2)" >&2; exit 1; }

ONLY=" $* "
first=() later=()
while IFS=$'\t' read -r org asm gs; do
  [ -n "${1:-}" ] && [[ $ONLY != *" $org "* ]] && continue
  dir="$DATA/$org/$(data_assembly "$org" "$asm")/$gs"
  [ -s "$dir/features.tsv" ] && [ -s "$dir/geneNames.tsv" ] || continue
  mapfile -d '' -t config < <(perl "$SCRIPTS/geneset_config.pl" "$SCRIPTS/geneset_config.yaml" "$org" "$asm" "$gs" 2>/dev/null)
  closest=()
  for (( i = 0; i < ${#config[@]}; i++ )); do
    [ "${config[$i]}" = --closest-species ] && closest+=(--closest-species "${config[$((i + 1))]}")
  done
  # the OMA run's DB/ turns OMA ids into Ensembl/RefSeq ids (the gene set's own run, else the reference run)
  oma_db="$OMA_BASE/$org/$asm/$gs/DB"
  [ -d "$oma_db" ] || oma_db="$OMA_REFERENCE_RUN/DB"
  entry=$(printf '%s\t' "$dir" "$oma_db" "${closest[@]}")
  if [ ${#closest[@]} -eq 0 ]; then first+=("$entry"); else later+=("$entry"); fi
done < <(bash "$SCRIPTS/list_active_genesets.sh")

n_ok=0 n_fail=0
for entry in ${first[@]+"${first[@]}"} ${later[@]+"${later[@]}"}; do
  IFS=$'\t' read -r -a fields <<< "${entry%$'\t'}"
  dir=${fields[0]} oma_db=${fields[1]}
  oma_arg=(); [ -d "$oma_db" ] && oma_arg=("$oma_db")
  if ( cd "$dir" && perl "$REPO/analysis_parsers/make_readable_gene_names.pl" "${fields[@]:2}" \
         features.tsv geneNames.tsv "${oma_arg[@]}" > geneNames.readable.tsv.tmp \
       && mv geneNames.readable.tsv.tmp geneNames.readable.tsv ); then
    n_ok=$(( n_ok + 1 ))
    echo "ok    ${dir#$DATA/}"
  else
    rm -f "$dir/geneNames.readable.tsv.tmp"
    n_fail=$(( n_fail + 1 ))
    echo "FAIL  ${dir#$DATA/}"
  fi
done
echo "remade $n_ok, failed $n_fail"
[ "$n_fail" -eq 0 ]
