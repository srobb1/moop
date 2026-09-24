#!/usr/bin/bash
# check_required_data.sh — dry-run check: does every ACTIVE geneset have the
# upstream analysis files process_one_geneset.sh actually needs to load and name
# genes, WITHOUT running any of the build?
#
# Why this exists: 2026-09-21, MOOP_RELOAD=1 runs for both Medicago_truncatula and
# Turritopsis_dohrnii failed at the same gate --
#
#     ERROR: Required file PANTHER.iprscan.moop.tsv is missing! Gene naming will fail.
#
# -- because their InterProScan results were never generated/delivered
# (Medicago_truncatula's interproscan/ dir has only interproscan_job_cmd.txt; the
# whole interproscan/ dir doesn't exist yet for Turritopsis_dohrnii). --reload had
# already DROPPED organism.sqlite by the time that was discovered. Before reloading
# every active geneset (run_all_v2.sh --reload with no target), it's worth knowing
# in advance which ones would just repeat this.
#
# This checks the SAME paths make_diamond_moop() / make_interproscan_moop() read
# (interproscan_results.tsv or .tsv.gz). ProtNLM is not checked: it is not used
# for any organism.
#
# Usage:
#   bash scripts/check_required_data.sh              # every active geneset
#   bash scripts/check_required_data.sh Medicago_truncatula      # one organism
#   bash scripts/check_required_data.sh Medicago_truncatula GCF_003473485.1 MedtrA17_geneset
#
# Output (stdout): one row per finding, tab-separated, six columns --
#
#   STATUS  ORGANISM  ASSEMBLY  GENESET  KIND  DETAIL
#
# STATUS is FAIL/WARN/OK (repeated on every row for that geneset, so a grep never
# loses which bucket a line belongs to). KIND is "summary" (one per geneset, DETAIL
# is "hard=N soft=N"), "blocking" (would reproduce today's failure), or "non-fatal"
# (logged/skipped by process_one_geneset.sh, does not stop the run). Every row
# carries the full org/assembly/geneset key, so `grep blocking` or `grep ^FAIL`
# alone is self-identifying -- no need to also grep -B for context.
#
# Examples:
#   bash scripts/check_required_data.sh | grep blocking          # every hard-missing input, with its geneset
#   bash scripts/check_required_data.sh | awk -F'\t' '$1=="FAIL"{print $2}' | sort -u   # just the organism names
#   bash scripts/check_required_data.sh | awk -F'\t' '$3=="summary"'                    # one line per geneset
#
# The human-readable totals line goes to stderr, so it never lands in the middle of
# a stdout grep/awk pipeline.
#
# Exit code: number of genesets that would hard-fail (capped at 255), 0 if none.

set -uo pipefail

SCRIPTS=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO=$(dirname "$SCRIPTS")
source "$SCRIPTS/paths.sh"

FILTER_ORG="${1:-}"
FILTER_ASM="${2:-}"
FILTER_GS="${3:-}"

TMP_LIST=$(mktemp)
trap 'rm -f "$TMP_LIST"' EXIT
bash "$SCRIPTS/list_active_genesets.sh" > "$TMP_LIST" || exit 1

FAIL=0
WARN=0
OK=0

while IFS=$'\t' read -r ORG ASM GS; do
  [ -n "$FILTER_ORG" ] && [ "$ORG" != "$FILTER_ORG" ] && continue
  [ -n "$FILTER_ASM" ] && [ "$ASM" != "$FILTER_ASM" ] && continue
  [ -n "$FILTER_GS" ]  && [ "$GS"  != "$FILTER_GS" ]  && continue

  GENESET_DIR="$GENOMES/$ORG/$ASM/$GS"
  ANALYSIS_DIR="$ANNOTATIONS/$ORG/$ASM/$GS"

  HAS_GFF=false
  HAS_T2G=false
  [ -s "$GENESET_DIR/genes.gff" ]           && HAS_GFF=true
  [ -s "$GENESET_DIR/transcript2gene.txt" ] && HAS_T2G=true

  hard_missing=()
  soft_missing=()

  if ! $HAS_GFF && ! $HAS_T2G; then
    hard_missing+=("no genes.gff or transcript2gene.txt under $GENESET_DIR")
  else
    # Source files, same list process_one_geneset.sh's own gates require.
    if $HAS_GFF; then
      for f in genes.gff protein.aa.fa cds.nt.fa transcript.nt.fa metadata.yaml; do
        [ -s "$GENESET_DIR/$f" ] || hard_missing+=("$f")
      done
    else
      for f in protein2gene.txt transcript2gene.txt protein.aa.fa transcript.nt.fa metadata.yaml; do
        [ -s "$GENESET_DIR/$f" ] || hard_missing+=("$f")
      done
    fi

    # Diamond UniProtKB/Swiss-Prot — feeds UniProtKB_Swiss-Prot.homologs.moop.tsv,
    # unconditionally required by build_gene_name_params()'s PARAMS list.
    SPROT="$ANALYSIS_DIR/diamond/UNIPROT_sprot"
    if [ ! -s "$SPROT/diamond_results.tsv" ] && [ ! -s "$SPROT/diamond_results.tsv.gz" ]; then
      hard_missing+=("diamond/UNIPROT_sprot/diamond_results.tsv(.gz)")
    fi

    # InterProScan — feeds PANTHER.iprscan.moop.tsv, unconditionally required.
    IDIR="$ANALYSIS_DIR/interproscan"
    if [ ! -s "$IDIR/interproscan_results.tsv" ] && [ ! -s "$IDIR/interproscan_results.tsv.gz" ]; then
      hard_missing+=("interproscan/interproscan_results.tsv(.gz)")
    fi

    # Optional inputs: missing ones are logged/printed by process_one_geneset.sh
    # but do not stop the run (no set -e, and each has its own skip path).
    [ -s "$ANALYSIS_DIR/eggnog_mapper/eggnog_mapper_results.tsv" ] \
      || soft_missing+=("eggnog_mapper/eggnog_mapper_results.tsv")
    [ -s "$ANALYSIS_DIR/signalp6/signalp6_results.tsv" ] \
      || soft_missing+=("signalp6/signalp6_results.tsv (skipped cleanly if absent)")
    [ -s "$ANALYSIS_DIR/deeptmhmm/deeptmhmm_results.gff3" ] \
      || soft_missing+=("deeptmhmm/deeptmhmm_results.gff3 (skipped cleanly if absent)")
  fi

  # Every row is STATUS<TAB>ORG<TAB>ASSEMBLY<TAB>GENESET<TAB>KIND<TAB>DETAIL so a
  # grep on any column (blocking, non-fatal, an organism name, FAIL/WARN/OK) stays
  # self-identifying -- `grep blocking` alone used to return bare detail lines with
  # no way to tell which geneset they belonged to.
  KEY="$ORG	$ASM	$GS"
  if [ "${#hard_missing[@]}" -gt 0 ]; then
    FAIL=$((FAIL + 1))
    STATUS=FAIL
  elif [ "${#soft_missing[@]}" -gt 0 ]; then
    WARN=$((WARN + 1))
    STATUS=WARN
  else
    OK=$((OK + 1))
    STATUS=OK
  fi
  printf '%s\t%s\tsummary\thard=%d soft=%d\n' "$STATUS" "$KEY" "${#hard_missing[@]}" "${#soft_missing[@]}"
  for m in "${hard_missing[@]}"; do printf '%s\t%s\tblocking\t%s\n' "$STATUS" "$KEY" "$m"; done
  for m in "${soft_missing[@]}"; do printf '%s\t%s\tnon-fatal\t%s\n' "$STATUS" "$KEY" "$m"; done
done < "$TMP_LIST"

# Summary goes to stderr, not stdout -- so piping/grepping stdout (the data) is
# never interrupted by a line that doesn't match the column format above.
echo "# $OK complete, $WARN with only non-fatal gaps, $FAIL would fail like Medicago_truncatula/Turritopsis_dohrnii did today." >&2

[ "$FAIL" -gt 255 ] && FAIL=255
exit "$FAIL"
