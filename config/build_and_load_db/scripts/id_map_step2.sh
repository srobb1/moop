#!/bin/bash
# Step 2 of notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md: run the RefSeq/Ensembl ID + name steps on COPIES
# and check them. Read-only on the datastore, the build tree and the databases; writes only <out_dir>.
#
#   bash scripts/id_map_step2.sh <out_dir> <organism> <assembly> <gene_set> <refseq|ensembl> [...]
#
# Per gene set, in <out_dir>/<organism>__<gene_set>/:
#   id_map.tsv           build_id_map.pl on the GFF naming reads (Ensembl: genes.versioned.gff)
#   genes.formatted.gff  format_gff_ids.pl on today's built genes.gff
#   ship/                what would be shipped: genes.gff (+ updateGFF.pl names) and the three
#                        FASTAs (+ updateFASTA.pl --keep-unlisted --by-gene names)
#   gate.details.tsv     check_id_alignment.pl on ship/, with id_alignment_exceptions.tsv
#   checks.txt           the byte checks: FASTA ids and sequences unchanged; genes.gff unchanged
#                        apart from ID/Parent/Alias and the name attributes
set -u
SCRIPTS=$(cd "$(dirname "$0")" && pwd)
REPO=$(dirname "$SCRIPTS")
source "$SCRIPTS/paths.sh"
DATA=${DATA:-/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/build_and_load_db/v2/data}
OUT=${1:?Usage: id_map_step2.sh <out_dir> <organism> <assembly> <gene_set> <source> [...]}
shift
mkdir -p "$OUT"

## a GFF with the attributes this step may change removed and escapes undone, for comparison
gff_rest() {
  perl -MURI::Escape -ne '
    if (/^#/) { print; next }
    chomp; my @f = split /\t/;
    if (@f < 9) { print "$_\n"; next }
    my @keep;
    foreach my $pair (split /;/, $f[8]) {
      next if $pair eq "" || $pair =~ /^(?:ID|Parent|Alias|Name|Note|namesrc)=/;
      push @keep, uri_unescape($pair);
    }
    $f[8] = join(";", @keep);
    print join("\t", map { uri_unescape($_) } @f), "\n";
  ' "$1"
}

one_gene_set() {
  local org=$1 asm=$2 gs=$3 source=$4
  local built="$DATA/$org/$(data_assembly "$org" "$asm")/$gs" delivered="$GENOMES/$org/$asm/$gs" work="$OUT/${org}__${gs}"
  mkdir -p "$work/ship"
  local naming_gff="$delivered/genes.gff"
  [ "$source" = ensembl ] && naming_gff="$built/genes.versioned.gff"

  perl "$REPO/analysis_parsers/build_id_map.pl" --gff "$naming_gff" --source "$source" --out "$work/id_map.tsv" \
    --protein "$delivered/protein.aa.fa" --cds "$delivered/cds.nt.fa" --transcript "$delivered/transcript.nt.fa" \
    || { echo "$org $gs: build_id_map.pl failed"; return 1; }
  perl "$SCRIPTS/format_gff_ids.pl" "$built/genes.gff" "$work/id_map.tsv" > "$work/genes.formatted.gff" \
    || { echo "$org $gs: format_gff_ids.pl failed"; return 1; }
  perl "$REPO/analysis_parsers/updateGFF.pl" "$work/genes.formatted.gff" "$built/geneNames.tsv" > "$work/ship/genes.gff" \
    || { echo "$org $gs: updateGFF.pl failed"; return 1; }
  local fasta
  for fasta in protein.aa.fa cds.nt.fa transcript.nt.fa; do
    [ -s "$built/$fasta" ] || continue
    perl "$REPO/analysis_parsers/updateFASTA.pl" --keep-unlisted --by-gene "$built/$fasta" "$built/geneNames.tsv" > "$work/ship/$fasta" \
      || { echo "$org $gs: updateFASTA.pl $fasta failed"; return 1; }
  done

  {
    echo "== gate"
    perl "$SCRIPTS/check_id_alignment.pl" "$org" "$DATA/$org/organism.sqlite" "$(data_assembly "$org" "$asm")" "$gs" "$source" \
      "$work/ship" "$work/gate.details.tsv" "$REPO/id_alignment_exceptions.tsv"
    echo "gate exit $?"
    echo "== FASTA ids and sequences unchanged"
    for fasta in protein.aa.fa cds.nt.fa transcript.nt.fa; do
      [ -s "$work/ship/$fasta" ] || continue
      if cmp -s <(sed -E 's/^(>[^ ]*).*/\1/' "$built/$fasta") <(sed -E 's/^(>[^ ]*).*/\1/' "$work/ship/$fasta"); then
        echo "$fasta: identical"
      else
        echo "$fasta: DIFFERS"
      fi
    done
    echo "== genes.gff unchanged apart from ID/Parent/Alias/Name/Note/namesrc"
    if cmp -s <(gff_rest "$built/genes.gff") <(gff_rest "$work/ship/genes.gff"); then
      echo "genes.gff: identical"
    else
      echo "genes.gff: DIFFERS"
      diff <(gff_rest "$built/genes.gff") <(gff_rest "$work/ship/genes.gff") | head -6
    fi
    echo "== source Note= removed by updateGFF.pl (lines)"
    grep -v '^#' "$built/genes.gff" | grep -c -E '(^|;)Note=' || true
  } > "$work/checks.txt" 2>&1
  echo "$org $gs: $(grep -A1 '^== gate' "$work/checks.txt" | tail -1)"
}

while [ $# -ge 4 ]; do
  one_gene_set "$1" "$2" "$3" "$4"
  shift 4
done
