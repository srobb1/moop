THIS_ORG=$1
ASSEMBLY=$2
GENE_SET=$3

[ -n "$THIS_ORG" ] && [ -n "$ASSEMBLY" ] && [ -n "$GENE_SET" ] \
  || { echo "Usage: $0 <organism> <assembly> <gene_set>"; exit 1; }

## WHERE THE DATA IS. This must agree with the caller, and it used not to.
##
## This was `realpath "$(dirname "${BASH_SOURCE[0]}")/.."`, and realpath RESOLVES
## SYMLINKS. Where the working tree's scripts/ is a symlink into the git checkout
## -- which is how the v2 tree is arranged -- that walked out of the tree entirely:
##
##   invoked from   .../moop/build_and_load_db/v2
##   REPO became    .../moop/moop-pipeline/config/build_and_load_db
##
## The sbatch takes REPO from $SLURM_SUBMIT_DIR, so the BUILD wrote to v2/data
## while the COPY looked in moop-pipeline/data. One pipeline, two answers to the
## same question, and the file simply was not where this half expected it.
##
## Now: an inherited REPO wins (the caller already knows, and passing it beats
## re-deriving it), and the fallback uses `cd`+`pwd`, which keeps the logical path
## rather than resolving the symlink out from under us.
REPO=${REPO:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}
DATA=$REPO/data
LOGFILE=$REPO/copy2moop_$(date +%Y%m%d).log

log() { echo "$(date '+%Y-%m-%d %H:%M:%S')  $*" | tee -a "$LOGFILE"; }
## Same thing on stderr, for anything that runs inside $( ). log() writes to stdout,
## so a retry notice emitted during `have=$(... )` would be captured as the value.
logerr() { echo "$(date '+%Y-%m-%d %H:%M:%S')  $*" | tee -a "$LOGFILE" >&2; }

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

## EVERY connection here goes to one host, and the whole SLURM array hits it at once.
## sshd's default MaxStartups is 10:30:100 -- it starts randomly REFUSING connections
## at 10 concurrent unauthenticated sessions. The array runs at concurrency 10 and each
## organism opens several connections, so the limit is reached routinely.
##
## On 2026-07-28 that dropped the copy for 10 of 81 organisms: `rsync error: unexplained
## error (code 255)`, "connection unexpectedly closed", clustered in time with two pairs
## sharing a timestamp to the second. Every one of those databases had built correctly.
## The data was fine; the transport was not. Nothing about the retry below makes a
## genuine failure pass -- it only stops a refused connection from being reported as one.
##
## Jitter is not decoration: a fixed delay re-synchronises the herd it is meant to
## break up, so all ten tasks would collide again on the next attempt.
SSH_OPTS=(-o ConnectTimeout=30 -o ServerAliveInterval=15 -o ServerAliveCountMax=4)
RETRIES=${COPY_RETRIES:-4}

retry() {
  local what="$1"; shift
  local attempt=1 rc=0 delay
  while : ; do
    "$@" && return 0
    rc=$?
    [ "$attempt" -ge "$RETRIES" ] && break
    delay=$(( attempt * 10 + RANDOM % 15 ))
    logerr "WARN  $what — exit $rc, attempt $attempt/$RETRIES, retrying in ${delay}s"
    sleep "$delay"
    attempt=$(( attempt + 1 ))
  done
  logerr "FAIL  $what — exit $rc after $RETRIES attempts"
  return "$rc"
}

if ! retry "ssh mkdir $REMOTE_GENESET_PATH" \
     ssh "${SSH_OPTS[@]}" "$REMOTE" "mkdir -p '$REMOTE_GENESET_PATH'"; then
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
  ## --partial keeps the bytes of an interrupted transfer so a retry resumes instead
  ## of restarting a 2 GB genome from zero; --timeout turns a silently wedged
  ## connection into a failure the retry can act on, rather than a hung job.
  ##
  ## The exit status reported here used to be `$?` read inside `if ! rsync ...; then`,
  ## which is the status of the `!` -- always 0. Every transport failure logged
  ## "returned 0", which is precisely the message that sends you looking at the data
  ## instead of the network. retry() captures the real status.
  if ! retry "rsync to $dest" \
       rsync -azL --partial --timeout=120 -e "ssh ${SSH_OPTS[*]}" \
         "${to_send[@]}" "$REMOTE:$dest/"; then
    logerr "      files: ${to_send[*]}"
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
  ## `|| echo missing` conflated two different things: ssh not running, and the file
  ## not being there. A dropped connection -- the exact failure being retried against
  ## above -- would then report a publish that HAD succeeded as incomplete. Now the
  ## remote side answers ABSENT for a missing file (and exits 0), so a non-zero ssh
  ## means transport, and only transport.
  if ! have=$(retry "stat $remote_path" \
                ssh "${SSH_OPTS[@]}" "$REMOTE" \
                  "stat -c %s '$remote_path' 2>/dev/null || echo ABSENT"); then
    log "FAIL  cannot reach $REMOTE to verify $(basename "$local_file") — publish unconfirmed"
    FAILED=1
    return 1
  fi
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
##
## Check it exists LOCALLY first. send() skips a missing file (`elif [ -e ]`) and
## verify_remote returns early on one, so a database that was never built looked
## exactly like a successful publish: 36 files copied, "OK" logged, and the live
## site still serving a three-week-old database. For the one file the site
## actually serves, absent is a failure, not a skip.
if [ ! -s "$ORG_DATA/organism.sqlite" ]; then
  log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — $ORG_DATA/organism.sqlite is missing or empty"
  log "      Nothing was published. The build did not leave a database where the copy"
  log "      step looks for it (REPO=$REPO, so ORG_DATA=$ORG_DATA)."
  exit 1
fi
verify_remote "$ORG_DATA/organism.sqlite" "$REMOTE_ORG_PATH/organism.sqlite"

if [ "$FAILED" -ne 0 ]; then
  log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — copy incomplete, live site NOT updated"
  exit 1
fi

log "OK    $THIS_ORG  [$ASSEMBLY/$GENE_SET]"
