THIS_ORG=$1
ASSEMBLY=$2
GENE_SET=$3

[ -n "$THIS_ORG" ] && [ -n "$ASSEMBLY" ] && [ -n "$GENE_SET" ] \
  || { echo "Usage: $0 <organism> <assembly> <gene_set>"; exit 1; }

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
DATA=$REPO/data
LOGFILE=$REPO/copy2moop_$(date +%Y%m%d).log

log() { echo "$(date '+%Y-%m-%d %H:%M:%S')  $*" | tee -a "$LOGFILE"; }

source "$(dirname "${BASH_SOURCE[0]}")/paths.sh"
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

## THIS SCRIPT PUBLISHES TO THE LIVE SITE, so it must never report success it did
## not achieve. It used to: ssh's exit status was ignored, rsync's exit status was
## ignored, and `log "OK"` at the bottom ran unconditionally. A reload of
## Bipalium_kewense on 2026-07-28 built a correct database, printed
##
##   2026-07-28 18:44:22  OK    Bipalium_kewense  [bkew.kc1.ref/bkew.kc1]
##
## and nothing arrived. The caller's `|| exit 1` could not help, because this
## script exited 0. Every step below is now checked, and OK is only printed once
## the files are verified present on the far side.
FAILED=0

if ! ssh "$REMOTE" "mkdir -p '$REMOTE_GENESET_PATH'"; then
  log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — cannot ssh to '$REMOTE' or create $REMOTE_GENESET_PATH"
  log "      Nothing was copied. If this ran on a compute node, check that it can"
  log "      reach '$REMOTE' — a login node being able to is not the same thing."
  exit 1
fi

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
  [ "${#to_send[@]}" -eq 0 ] && return 0
  if ! rsync -azL "${to_send[@]}" "$REMOTE:$dest/"; then
    log "FAIL  rsync to $dest returned $? for: ${to_send[*]}"
    FAILED=1
  fi
}

## Confirm a file actually landed, and at the same size. rsync reporting success
## is not the same as the destination being correct -- an scp of this very database
## earlier the same day arrived truncated at 79 MB of 238 MB and read as
## "database disk image is malformed".
verify_remote() {
  local local_file="$1" remote_path="$2"
  [ -e "$local_file" ] || return 0
  local want have
  want=$(stat -c %s "$local_file")
  have=$(ssh "$REMOTE" "stat -c %s '$remote_path' 2>/dev/null" || echo missing)
  if [ "$have" = "$want" ]; then
    return 0
  fi
  log "FAIL  $(basename "$local_file"): local $want bytes, remote '$have'"
  FAILED=1
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

## organism.sqlite is the file the live site actually serves, so confirm it landed
## and at the right size rather than trusting rsync's exit code alone.
verify_remote "$ORG_DATA/organism.sqlite" "$REMOTE_ORG_PATH/organism.sqlite"

if [ "$FAILED" -ne 0 ]; then
  log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — copy incomplete, live site NOT updated"
  exit 1
fi

log "OK    $THIS_ORG  [$ASSEMBLY/$GENE_SET]"
