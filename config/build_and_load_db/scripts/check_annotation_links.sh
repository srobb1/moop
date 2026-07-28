#!/bin/bash
# Diagnose MISS_DIAMOND / MISS_EGGNOG / MISS_IPRSCAN / MISS_PROTNLM.
#
# check_status.sh tests these inputs with `-f`, which FOLLOWS symlinks. A dangling
# link and an absent file are therefore indistinguishable in its output, and a moved
# analysis tree looks exactly like analysis that was never run. This says which it is.
#
# Usage:
#   bash scripts/check_annotation_links.sh              # diagnose
#   bash scripts/check_annotation_links.sh --repair OLD NEW
#       re-point dangling symlinks whose target contains OLD so it reads NEW.
#       Prints what it would do first; re-run with --yes to actually do it.
#
#   ANNOTATIONS=/some/other/base bash scripts/check_annotation_links.sh
#       override the base without editing the script.

set -uo pipefail
REPO="$(cd "$(dirname "$0")/.." && pwd)"

# GENOMES and ANNOTATIONS come from the single definition, so this checks exactly the
# tree the build will read. Override either in the environment to test a moved tree.
source "$(dirname "${BASH_SOURCE[0]}")/paths.sh"

REPAIR=0; DO_IT=0; OLD=""; NEW=""
while [ $# -gt 0 ]; do
  case "$1" in
    --repair) REPAIR=1; OLD=${2:-}; NEW=${3:-}; shift 3 ;;
    --yes)    DO_IT=1; shift ;;
    *) echo "unknown arg: $1" >&2; exit 1 ;;
  esac
done

echo "ANNOTATIONS = $ANNOTATIONS"
if [ -d "$ANNOTATIONS" ]; then
  echo "  base exists"
else
  echo "  ** BASE DOES NOT EXIST ** — this alone explains every MISS_* tag."
fi

# Sibling dated trees: if the analysis was regenerated under a new name, it shows here.
parent=$(dirname "$ANNOTATIONS")
if [ -d "$parent" ]; then
  echo ""
  echo "Sibling trees in $parent (newest last):"
  ls -1dt "$parent"/*/ 2>/dev/null | tail -8 | sed 's/^/  /'
fi

# The four files check_status.sh tests, in its order.
probe() {   # probe <geneset_dir>
  local d=$1
  echo "$d/diamond_blast/UNIPROT_sprot/tophit.tsv.gz|MISS_DIAMOND"
  echo "$d/eggnog_mapper/emapper.annotations|MISS_EGGNOG"
  echo "$d/interproscan/iprscan_results.tsv.gz|MISS_IPRSCAN"
  echo "$d/protnlm/protnlm_pred_results.tsv|MISS_PROTNLM"
}

ok=0; dangling=0; absent=0
declare -a DANGLERS

echo ""
echo "Per-gene-set probe (only problems are listed):"
while IFS=$'\t' read -r org asm gs; do
  [ -n "${org:-}" ] || continue
  dir="$ANNOTATIONS/$org/$asm/$gs"
  while IFS='|' read -r f tag; do
    base=${f%.gz}
    if [ -f "$f" ] || [ -f "$base" ]; then
      ok=$((ok+1))
    elif [ -L "$f" ] || [ -L "$base" ]; then
      link=$([ -L "$f" ] && echo "$f" || echo "$base")
      target=$(readlink "$link")
      dangling=$((dangling+1)); DANGLERS+=("$link")
      echo "  DANGLING $tag  $org/$asm/$gs"
      echo "           link -> $target"
    else
      absent=$((absent+1))
      echo "  ABSENT   $tag  $org/$asm/$gs"
    fi
  done < <(probe "$dir")
done < <(bash "$REPO/scripts/list_active_genesets.sh" 2>/dev/null)

echo ""
echo "─────────────────────────────────────────────"
echo "  present:  $ok"
echo "  DANGLING: $dangling   (symlink exists, target missing -> someone moved files)"
echo "  absent:   $absent     (nothing there at all -> analysis genuinely not run)"
echo "─────────────────────────────────────────────"
echo ""
if [ "$dangling" -gt 0 ] && [ "$absent" -eq 0 ]; then
  echo "Diagnosis: MOVED FILES. The analysis exists; the links do not resolve."
  echo "  Repair:  bash scripts/check_annotation_links.sh --repair OLD NEW --yes"
elif [ "$absent" -gt 0 ] && [ "$dangling" -eq 0 ]; then
  echo "Diagnosis: analysis genuinely missing at this base path."
  echo "  If a sibling tree above looks newer, re-run with ANNOTATIONS=<that path>."
fi

if [ "$REPAIR" = "1" ]; then
  [ -n "$OLD" ] && [ -n "$NEW" ] || { echo "--repair needs OLD and NEW" >&2; exit 1; }
  echo ""
  echo "Repair: replacing '$OLD' with '$NEW' in dangling targets"
  [ "$DO_IT" = "1" ] || echo "(dry run — add --yes to apply)"
  for link in "${DANGLERS[@]:-}"; do
    [ -n "$link" ] || continue
    t=$(readlink "$link"); case "$t" in *"$OLD"*) ;; *) continue ;; esac
    nt=${t//$OLD/$NEW}
    if [ -e "$nt" ]; then
      echo "  $link"
      echo "    -> $nt"
      [ "$DO_IT" = "1" ] && ln -sfn "$nt" "$link"
    else
      echo "  SKIP $link — new target does not exist either: $nt"
    fi
  done
fi

echo ""
echo "NOTE: ANNOTATIONS carries a DATE and is defined in scripts/paths.sh. When the"
echo "      analysis tree is regenerated under a new name, change it THERE, once --"
echo "      every script reads that one value. Forgetting makes every gene set"
echo "      report MISS_*, which looks identical to analysis that never ran."
