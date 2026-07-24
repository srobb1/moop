THIS_ORG=$1
ASSEMBLY=$2
GENE_SET=$3

[ -n "$THIS_ORG" ] && [ -n "$ASSEMBLY" ] && [ -n "$GENE_SET" ] \
  || { echo "Usage: $0 <organism> <assembly> <gene_set>"; exit 1; }

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
DATA=$REPO/data
LOGFILE=$REPO/copy2moop_$(date +%Y%m%d).log

log() { echo "$(date '+%Y-%m-%d %H:%M:%S')  $*" | tee -a "$LOGFILE"; }

GENOMES=/n/sci/SCI-004223-SBGENOMES/genomes_v2
GENOME_DIR=$GENOMES/$THIS_ORG/$ASSEMBLY
ORG_DATA=$DATA/$THIS_ORG
ASSEMBLY_DATA=$ORG_DATA/$ASSEMBLY
GENESET_DATA=$ASSEMBLY_DATA/$GENE_SET

HAS_GENOME=false
( [ -e "$ASSEMBLY_DATA/genome.fa" ] || [ -e "$GENOME_DIR/genome.fa" ] ) && HAS_GENOME=true

cd "$GENESET_DATA"

REMOTE=moop
REMOTE_ORG_PATH="/var/www/html/moop/organisms/$THIS_ORG"
REMOTE_ASSEMBLY_PATH="$REMOTE_ORG_PATH/$ASSEMBLY"
REMOTE_GENESET_PATH="$REMOTE_ASSEMBLY_PATH/$GENE_SET"

ssh $REMOTE "mkdir -p $REMOTE_GENESET_PATH"

## Safely rsync explicit files and globs to a remote destination.
send() {
  local dest="$1"; shift
  local to_send=()
  for arg in "$@"; do
    if [[ "$arg" == *'*'* ]]; then
      shopt -s nullglob
      local expanded=($arg)
      shopt -u nullglob
      to_send+=("${expanded[@]}")
    elif [ -e "$arg" ]; then
      to_send+=("$arg")
    fi
  done
  [ "${#to_send[@]}" -gt 0 ] && rsync -azL "${to_send[@]}" "$REMOTE:$dest/"
}

## Organism level (sqlite, json, cache all live at the organism dir, not geneset dir)
send "$REMOTE_ORG_PATH" \
  "$ORG_DATA/organism.sqlite" "$ORG_DATA/organism.json" "$ORG_DATA/annotation_sources_cache.json"

## Assembly level: genome.json + genome + fai + nucl BLAST index (genome-backed only)
send "$REMOTE_ASSEMBLY_PATH" "$ASSEMBLY_DATA/genome.json"
if $HAS_GENOME; then
  send "$REMOTE_ASSEMBLY_PATH" \
    "$ASSEMBLY_DATA/genome.fa" "$ASSEMBLY_DATA/genome.fa.fai" "$ASSEMBLY_DATA/genome.fa.n*"
fi

## Gene-set level: geneset.json + GFF + coords (genome-backed), FASTAs + BLAST indexes (always)
if $HAS_GENOME; then
  send "$REMOTE_GENESET_PATH" \
    geneset.json genes.gff feature_coords.tsv \
    protein.aa.fa "protein.aa.fa.p*" \
    transcript.nt.fa "transcript.nt.fa.n*" \
    cds.nt.fa "cds.nt.fa.n*"
else
  send "$REMOTE_GENESET_PATH" \
    geneset.json \
    protein.aa.fa "protein.aa.fa.p*" \
    transcript.nt.fa "transcript.nt.fa.n*" \
    cds.nt.fa "cds.nt.fa.n*"
fi

log "OK    $THIS_ORG  [$ASSEMBLY/$GENE_SET]"
