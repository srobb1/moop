#!/usr/bin/bash
#SBATCH --job-name=synteny
#SBATCH --cpus-per-task=16
#SBATCH --mem=32gb
#SBATCH --time=12:00:00
#SBATCH --output=slurm_logs/synteny_%j.log
##
## One synteny comparison, start to finish.
##
##   sbatch run_pair.sh <code_a> <code_b> genes
##   sbatch run_pair.sh <code_a> <code_b> genome [asm5|asm10|asm20]
##   bash   run_pair.sh ...          (same thing, without the scheduler)
##
## Codes come from genomes.tsv. Normally started by submit_all.sh, not by hand.
##
## genes   gene-anchor synteny (jcvi = MCScan-python), as in the 2024 chameleon run:
##           prep_gene_set.pl (both genomes) -> catalog ortholog -> synteny screen
##           -> label_and_check.pl
## genome  whole-genome alignment: minimap2 -cx <preset> A.fa B.fa > A.B.paf
##           B is the query, A the target. No --cs: -c already writes the CIGAR the
##           browser draws, and --cs made the PAF several times larger. Indexing the PAF
##           (jbrowse make-pif) happens on MOOP when it is published.
##
## Results: $SYNTENY_OUT/published/<OrgA>__<OrgB>/  (organism names sorted, so a pair has
## one home whichever way round it was asked for). A pair is DONE when its done.txt exists;
## submit_all.sh skips it. Work files stay in $SYNTENY_OUT/work/ for debugging.
set -euo pipefail

SCRIPT_DIR=${SLURM_SUBMIT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}
source "$SCRIPT_DIR/settings.sh"

CODE_A=${1:?usage: run_pair.sh code_a code_b genes|genome [preset]}
CODE_B=${2:?usage: run_pair.sh code_a code_b genes|genome [preset]}
METHOD=${3:?usage: run_pair.sh code_a code_b genes|genome [preset]}
PRESET=${4:-$WGA_PRESET}
THREADS=${SLURM_CPUS_PER_TASK:-$THREADS}

## code -> organism/assembly/gene_set, from genomes.tsv
gene_set_of() {
  local code=$1
  local found
  found=$(awk -F'\t' -v code="$code" '$1 == code { print $2 }' "$GENOMES_TSV")
  if [ -z "$found" ]; then
    echo "ERROR: '$code' is not in $GENOMES_TSV" >&2
    exit 1
  fi
  echo "$found"
}
GS_A=$(gene_set_of "$CODE_A")
GS_B=$(gene_set_of "$CODE_B")
ORG_A=${GS_A%%/*}
ORG_B=${GS_B%%/*}
DIR_A="$MOOP_DATA/$GS_A"
DIR_B="$MOOP_DATA/$GS_B"

## One home per pair, by sorted organism name.
if [[ "$ORG_A" < "$ORG_B" ]]; then PAIR_DIR="${ORG_A}__${ORG_B}"; else PAIR_DIR="${ORG_B}__${ORG_A}"; fi
PUBLISHED="$SYNTENY_OUT/published/$PAIR_DIR"
WORK="$SYNTENY_OUT/work/$CODE_A.$CODE_B.$METHOD"
PREP="$SYNTENY_OUT/prep"
mkdir -p "$PUBLISHED" "$WORK" "$PREP"

echo "== $CODE_A ($GS_A)  vs  $CODE_B ($GS_B)  method=$METHOD  $(date)"

## Prepare one genome's gene inputs, once, shared by every pair that uses it.
## Redone only when genes.gff or cds.nt.fa is newer than the prepared BED.
prep() {
  local code=$1 dir=$2
  if [ -s "$PREP/$code.bed" ] && [ "$PREP/$code.bed" -nt "$dir/genes.gff" ] && [ "$PREP/$code.bed" -nt "$dir/cds.nt.fa" ]; then
    echo "prep $code: current"
    return
  fi
  perl "$SYNTENY_DIR/prep_gene_set.pl" --gene-set "$dir" --code "$code" --outdir "$PREP"
}

run_genes() {
  prep "$CODE_A" "$DIR_A"
  prep "$CODE_B" "$DIR_B"
  set +u; module load "$JCVI_MODULE"; set -u    # module scripts trip set -u
  cd "$WORK"
  ## jcvi finds its inputs by name in the current directory: <code>.bed and <code>.cds
  for code in "$CODE_A" "$CODE_B"; do
    ln -sf "$PREP/$code.bed" "$code.bed"
    ln -sf "$PREP/$code.cds" "$code.cds"
  done
  ## --no_strip_names: without it jcvi drops ".1" version suffixes and the names no
  ## longer match the BED.
  python -m jcvi.compara.catalog ortholog "$CODE_A" "$CODE_B" --no_strip_names --cscore="$CSCORE"
  python -m jcvi.compara.synteny screen --minspan="$MINSPAN" --simple \
    "$CODE_A.$CODE_B.anchors" "$CODE_A.$CODE_B.anchors.new"
  perl "$SYNTENY_DIR/label_and_check.pl" --a "$CODE_A" --b "$CODE_B" \
    --work "$WORK" --prep "$PREP" --outdir "$PUBLISHED"
  cp "$PREP/$CODE_A.prep.txt" "$PREP/$CODE_B.prep.txt" "$PUBLISHED/"
  ## The dot plot jcvi draws is a quick sanity check worth keeping beside the result.
  for pdf in "$CODE_A.$CODE_B".*.pdf; do
    [ -e "$pdf" ] && cp "$pdf" "$PUBLISHED/"
  done
  return 0
}

run_genome() {
  local fa_a="$DIR_A/../genome.fa" fa_b="$DIR_B/../genome.fa"
  for fa in "$fa_a" "$fa_b"; do
    [ -s "$fa" ] || { echo "ERROR: missing $fa" >&2; exit 1; }
  done
  set +u; module load "$MINIMAP2_MODULE"; set -u
  local paf="$WORK/$CODE_A.$CODE_B.$PRESET.paf"
  minimap2 -c -x "$PRESET" -t "$THREADS" "$fa_a" "$fa_b" > "$paf.tmp"
  mv "$paf.tmp" "$paf"
  if [ ! -s "$paf" ]; then
    echo "ERROR: minimap2 found no alignments between $CODE_A and $CODE_B with $PRESET" >&2
    exit 1
  fi
  ## How much of each genome lines up -- the number to compare presets on.
  awk -F'\t' -v a="$CODE_A" -v b="$CODE_B" '
    { qcov += $4 - $3; tcov += $9 - $8; n++ }
    END { printf "alignments\t%d\naligned_bp_%s\t%d\naligned_bp_%s\t%d\n", n, b, qcov, a, tcov }
  ' "$paf" > "$PUBLISHED/$CODE_A.$CODE_B.$PRESET.paf.stats.txt"
  cp "$paf" "$PUBLISHED/"
  return 0
}

case "$METHOD" in
  genes)  run_genes ;;
  genome) run_genome ;;
  *) echo "ERROR: method must be genes or genome, not '$METHOD'" >&2; exit 1 ;;
esac

{
  echo -e "a\t$CODE_A\t$GS_A"
  echo -e "b\t$CODE_B\t$GS_B"
  echo -e "method\t$METHOD"
  [ "$METHOD" = genome ] && echo -e "preset\t$PRESET"
  [ "$METHOD" = genes ] && echo -e "cscore\t$CSCORE\nminspan\t$MINSPAN"
  echo -e "finished\t$(date '+%Y-%m-%d %H:%M')"
  echo -e "host\t$(hostname)"
} > "$PUBLISHED/done.$CODE_A.$CODE_B.$METHOD.txt"
echo "== done: $PUBLISHED"
