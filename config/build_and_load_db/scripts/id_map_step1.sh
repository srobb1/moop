#!/bin/bash
# Step 1 of notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md, for every active gene set: build its id_map.tsv
# from the DELIVERED files and compare it with the organism.sqlite already built. Read-only:
# nothing in the datastore, the build tree or the databases is written.
#
#   bash scripts/id_map_step1.sh <out_dir> [<active_genesets.tsv>]
#
# Writes <out_dir>/<Org>__<Asm>__<GeneSet>.id_map.tsv and .diff.tsv, and <out_dir>/summary.tsv
# (one line per gene set). The GFF source is classified exactly as process_one_geneset.sh does.
set -u
SCRIPTS=$(cd "$(dirname "$0")" && pwd)
REPO=$(dirname "$SCRIPTS")
source "$SCRIPTS/paths.sh"
OUT=${1:?Usage: id_map_step1.sh <out_dir> [active_genesets.tsv]}
LIST=${2:-$REPO/../../../build_and_load_db/v2/active_genesets.tsv}
DATA=${DATA:-/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/build_and_load_db/v2/data}
mkdir -p "$OUT"

## a top-level "key: value" from metadata.yaml, as process_one_geneset.sh reads it
meta_key() {
  local value
  value=$(sed -n "s/^$2:[[:space:]]*//p" "$1/metadata.yaml" 2>/dev/null | head -1)
  value=${value%$'\r'}
  value=${value%\"}; value=${value#\"}
  value=${value%\'}; value=${value#\'}
  printf '%s' "$value"
}

## process_one_geneset.sh's GFF_SOURCE, including the lift override
gff_source() {
  local gff=$1 source tool cds_has_id
  source=$(perl -ne '
    next if /^#/;
    my @f = split /\t/;
    next unless @f >= 9 && $f[2] eq "gene";
    if    ($f[8] =~ /\bID=gene:/)                                 { print "ensembl"; exit }
    elsif ($f[8] =~ /\bID=gene-/ || $f[8] =~ /\bDbxref=GeneID:/) { print "refseq";  exit }
    print "other"; exit
  ' "$gff")
  source=${source:-other}
  if [ "$source" = refseq ]; then
    tool=$(awk -F'\t' '!/^#/ && NF>=9 {print $2; exit}' "$gff")
    cds_has_id=$(awk -F'\t' '$3=="CDS" && index($9,"ID=")>0 {print 1; exit}' "$gff")
    if echo "$tool" | grep -qiE '^lift(on|off)$' || [ -z "$cds_has_id" ]; then
      source=lift
    fi
  fi
  printf '%s' "$source"
}

one_gene_set() {
  local org=$1 asm=$2 gs=$3
  local dir="$GENOMES/$org/$asm/$gs" tag="${org}__${asm}__${gs}" source args
  if [ ! -d "$dir" ]; then
    printf '%s\t%s\t%s\t%s\n' "$org" "$asm" "$gs" "not in \$GENOMES (active_genesets.tsv points nowhere)"
    return
  fi
  if [ ! -s "$dir/genes.gff" ]; then
    printf '%s\t%s\t%s\t%s\n' "$org" "$asm" "$gs" "no_gff (transcriptome: phase 2)"
    return
  fi
  if [ ! -s "$DATA/$org/organism.sqlite" ]; then
    printf '%s\t%s\t%s\t%s\n' "$org" "$asm" "$gs" "no organism.sqlite"
    return
  fi
  source=$(gff_source "$dir/genes.gff")
  args=(--gff "$dir/genes.gff" --source "$source" --out "$OUT/$tag.id_map.tsv")
  [ -s "$dir/protein.aa.fa" ]    && args+=(--protein "$dir/protein.aa.fa")
  [ -s "$dir/cds.nt.fa" ]        && args+=(--cds "$dir/cds.nt.fa")
  [ -s "$dir/transcript.nt.fa" ] && args+=(--transcript "$dir/transcript.nt.fa")
  local strip add lift
  strip=$(meta_key "$dir" moop-strip-id-prefix)
  add=$(meta_key "$dir" moop-add-id-prefix)
  lift=$(meta_key "$dir" moop-lift-prefix)
  [ -n "$strip" ] && args+=(--strip "$strip" --add "$add")
  [ -n "$lift" ]  && args+=(--lift-prefix "$lift")
  local built compared
  if ! built=$(perl "$REPO/analysis_parsers/build_id_map.pl" "${args[@]}" 2>&1); then
    printf '%s\t%s\t%s\t%s\t%s\n' "$org" "$asm" "$gs" "$source" "BUILD FAILED: ${built//$'\n'/ }"
    return
  fi
  if ! compared=$(perl "$SCRIPTS/compare_id_map_to_db.pl" "$OUT/$tag.id_map.tsv" "$DATA/$org/organism.sqlite" \
                   "$asm" "$gs" "$OUT/$tag.diff.tsv" 2>&1); then
    printf '%s\t%s\t%s\t%s\t%s\n' "$org" "$asm" "$gs" "$source" "COMPARE FAILED: ${compared//$'\n'/ }"
    return
  fi
  printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$org" "$asm" "$gs" "$source" "$compared" "${built#build_id_map.pl }"
}
export -f one_gene_set meta_key gff_source
export GENOMES OUT DATA REPO SCRIPTS

{
  printf 'organism\tassembly\tgene_set\tsource\tin_both\tsame\tparent_diff\ttype_diff\tonly_map\tonly_db\tdetail\tbuild\n'
  ## organism, assembly and gene set names carry no spaces, so three words per gene set
  grep -v '^#' "$LIST" | awk -F'\t' 'NF>=3 {print $1, $2, $3}' \
    | xargs -P "${JOBS:-4}" -n 3 bash -c 'one_gene_set "$@"' _ \
    | sort
} > "$OUT/summary.tsv"
echo "wrote $OUT/summary.tsv"
