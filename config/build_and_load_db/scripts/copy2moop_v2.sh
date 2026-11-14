#!/usr/bin/bash
##
## Publish built organism data to the live MOOP site.
##
##   copy2moop_v2.sh <organism> <assembly> <gene_set>   one gene set
##   copy2moop_v2.sh --all                              every active gene set, then the clades
##   copy2moop_v2.sh --clades                           only the clade homolog files
##   copy2moop_v2.sh --kegg                             only the KEGG reference snapshot
##
## Besides each gene set's files, an organism's crosswalk.sqlite (RosettaStone puts it in
## data/<Organism>/) goes with it, and data/.clades/<clade>/homologs.sqlite (build_clade_homologs.py)
## goes to organisms/.clades/<clade>/. The KEGG reference snapshot (fetch_kegg_reference.sh,
## $REFERENCE_DATA/kegg/current/) goes to organisms/.kegg/: the site reads each pathway's full KO list
## (coverage "38 of 120 KOs"), pathway names and classes, and the snapshot date from it.
##
## Options:
##   --all         walk $GENOMES/*/*/*/metadata.yaml and copy every gene set marked
##                 `active: true`. Same detection as run_all_v2.sh, so the two agree.
##   --clades      copy only data/.clades/*/homologs.sqlite (--all does these too).
##   --kegg        copy only the KEGG reference snapshot (--all does it too).
##   --dry-run     report what WOULD be copied and change nothing on the far side.
##   --force       re-send every file even if the remote copy already matches.
##   --checksum    compare by content instead of size+mtime. Thorough and slow --
##                 it reads every byte on BOTH ends. For an integrity audit, not
##                 for routine publishing.
##
## Copying only what changed is rsync's own behaviour, not something added here:
## with -a it compares size and mtime, and -a also PRESERVES mtime, so a file that
## landed correctly last time matches and is skipped. What this script adds is the
## gene-set level decision (skip the whole thing, and say so) and a report that
## distinguishes "copied" from "already current" from "never built".

set -u

usage() {
  awk 'NR > 2 && /^##/ { sub(/^## ?/, ""); print; next } NR > 2 { exit }' "${BASH_SOURCE[0]}"
  exit "${1:-1}"
}

ALL=false
CLADES_ONLY=false
KEGG_ONLY=false
DRY_RUN=false
RSYNC_EXTRA=()
POSITIONAL=()

while [ $# -gt 0 ]; do
  case "$1" in
    --all)      ALL=true ;;
    --clades)   CLADES_ONLY=true ;;
    --kegg)     KEGG_ONLY=true ;;
    --dry-run)  DRY_RUN=true; RSYNC_EXTRA+=(--dry-run) ;;
    ## --ignore-times defeats the size+mtime quick check, which is exactly what
    ## "copy it again even though it looks fine" means.
    --force)    RSYNC_EXTRA+=(--ignore-times) ;;
    --checksum) RSYNC_EXTRA+=(--checksum) ;;
    -h|--help)  usage 0 ;;
    -*)         echo "Unknown option: $1" >&2; usage 1 ;;
    *)          POSITIONAL+=("$1") ;;
  esac
  shift
done

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

REMOTE=moop
REMOTE_ROOT=/var/www/html/moop/organisms

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

## ssh -n for the BARE ssh calls only. ssh reads stdin greedily, so a caller looping
## over this script on stdin -- the obvious way to re-copy a handful of organisms --
## had its remaining input eaten by the first mkdir and silently stopped after one
## iteration, reporting OK. (copy_all_to_moop.sbatch only escaped this by feeding its
## loop on fd 3.) -n redirects stdin from /dev/null and the hazard is gone.
##
## NOT on rsync's -e: rsync drives that ssh's stdin as its own protocol channel, and
## -n there would break the transfer outright.
SSH=(ssh -n "${SSH_OPTS[@]}")

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

## Per-gene-set state, reset by copy_one_geneset.
FAILED=0
XFER=0

## Safely rsync explicit files and globs to a remote destination.
send() {
  local dest="$1"; shift
  local to_send=()
  local arg
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

  ## --itemize-changes is what makes "did anything actually move?" answerable. rsync
  ## prints one line per file it acts on and nothing for files already current, so
  ## counting those lines separates a real publish from a no-op reconcile. Without it
  ## every run looks identical in the log whether it copied 36 files or zero.
  local out; out=$(mktemp)

  ## --partial-dir keeps the bytes of an interrupted transfer so a retry resumes instead
  ## of restarting a 2 GB genome from zero; --timeout turns a silently wedged
  ## connection into a failure the retry can act on, rather than a hung job.
  ##
  ## --partial-dir, NOT the bare --partial this used to pass. Both resume; they differ
  ## in what an interrupted transfer leaves at the DESTINATION, which is a live file the
  ## web server is serving. Measured on rsync 3.2.5 -- an 80 MB source replacing a 70 MB
  ## destination, SIGINT mid-transfer:
  ##
  ##   --partial                    destination = 24.7 MB fragment -- the good file is GONE
  ##   --partial-dir=.rsync-partial destination = still the intact 70 MB file; the 24.7 MB
  ##                                fragment parks in .rsync-partial/, the retry resumes
  ##                                from it, and rsync removes the directory on success
  ##
  ## (Beware measuring this: with -a, rsync skips a file whose size AND mtime already
  ## match, so a test using two same-sized files transfers nothing and every flag looks
  ## safe. Differ the size to be sure a transfer actually happened.)
  ##
  ## Both write via a temp file and rename on SUCCESS, so a completed copy was always
  ## atomic; the difference is only on the failure path. But this script's own history
  ## (see the 2026-07-28 note above) is that rsync here does fail, and --timeout=120
  ## exists precisely because transfers wedge. A truncated organism.sqlite left at the
  ## live path is a corrupt database for every visitor to that organism until a retry
  ## succeeds -- and if the job dies, it simply stays corrupt. The old good file is the
  ## right thing to leave behind on failure.
  ##
  ## The exit status reported here used to be `$?` read inside `if ! rsync ...; then`,
  ## which is the status of the `!` -- always 0. Every transport failure logged
  ## "returned 0", which is precisely the message that sends you looking at the data
  ## instead of the network. retry() captures the real status.
  if ! retry "rsync to $dest" \
       rsync -azL --partial-dir=.rsync-partial --timeout=120 --itemize-changes "${RSYNC_EXTRA[@]}" \
         -e "ssh ${SSH_OPTS[*]}" \
         "${to_send[@]}" "$REMOTE:$dest/" >"$out"; then
    logerr "      files: ${to_send[*]}"
    FAILED=1
    rm -f "$out"
    return 1
  fi

  ## '<' is "being sent to the remote" (every push of a file: rsync's man page, --itemize-changes),
  ## '>' is "being received locally", 'c' is "created there" (directories, links). '.' lines are
  ## files rsync looked at and left alone -- those are the ones we want to be silent.
  ## (Until 2026-10-06 this counted '>' and 'c' only: a push that UPDATED a file on the site
  ## counted 0 and was logged "SAME ... nothing to do" although rsync had copied it.)
  local n
  n=$(grep -cE '^(<|>|c[dfL])' "$out" || true)
  XFER=$(( XFER + n ))
  [ "$n" -gt 0 ] && sed 's/^/        /' "$out"
  rm -f "$out"
  return 0
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
                "${SSH[@]}" "$REMOTE" \
                  "stat -c %s '$remote_path' 2>/dev/null || echo ABSENT"); then
    log "FAIL  cannot reach $REMOTE to verify $(basename "$local_file") — publish unconfirmed"
    FAILED=1
    return 1
  fi
  if [ "$have" = "$want" ]; then
    return 0
  fi
  ## Under --dry-run a mismatch is the expected finding, not an error: it is precisely
  ## the work the real run would do.
  if $DRY_RUN; then
    log "      would update organism.sqlite: local $want bytes, remote '$have'"
    return 0
  fi
  log "FAIL  $(basename "$local_file"): local $want bytes, remote '$have'"
  FAILED=1
}

## A database can be PRESENT, non-empty, and still not publishable.
##
## Amphimedon_queenslandica's 2026-07-28 build died at makeblastdb on a duplicate
## seq_id AFTER the loader had already written 90,829 features and 831,932
## annotations, so it left an 84 MB database that looks fully populated. The build
## stopped before the whole-database steps, which is where the FTS index is created.
## The pipeline knew exactly what that meant and said so:
##
##   ERROR: gene set GCF_000090795.2/RS_2026_04 failed -- stopping.
##          Not running the whole-database steps and not copying to moop
##
## A copy driven from the filesystem cannot see that log line. Checking only for a
## missing organism.sqlite let --all publish it, and because MOOP's search is
## FTS-only the live site then answered `no such table: feature_annotation_search`
## for that organism -- an error page, not an empty result.
##
## The FTS tables are created by the whole-database steps and by nothing else, so
## their presence IS the surviving record of a build that ran to completion. This
## reconstructs the pipeline's own decision from the artifact rather than trusting
## that a file exists.
##
## Fails CLOSED: if sqlite3 cannot be run or the database cannot be read, that is a
## refusal to publish, not a reason to skip the check. sqlite3 is already required
## on this host -- moop_process_genome_data_v2.sbatch builds the FTS index with it.
db_is_complete() {
  local db="$1" n
  n=$(sqlite3 -readonly "$db" \
        "SELECT count(*) FROM sqlite_master
          WHERE type='table' AND name IN ('feature_search','feature_annotation_search');" 2>/dev/null) \
    || return 1
  [ "$n" = "2" ]
}

## Returns 0 published/current, 1 failed, 2 nothing publishable built locally,
## 3 not built yet (no annotation run or no OMA run).
copy_one_geneset() {
  local THIS_ORG="$1" ASSEMBLY="$2" GENE_SET="$3"

  FAILED=0
  XFER=0

  local GENOME_DIR=$GENOMES/$THIS_ORG/$ASSEMBLY
  local ORG_DATA=$DATA/$THIS_ORG
  ## data/ and moop name the assembly by its genome-accession (data_assembly, paths.sh)
  local DATA_ASSEMBLY
  DATA_ASSEMBLY=$(data_assembly "$THIS_ORG" "$ASSEMBLY") \
    || { log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — cannot tell its genome-accession (above)"; return 1; }
  local ASSEMBLY_DATA=$ORG_DATA/$DATA_ASSEMBLY
  local GENESET_DATA=$ASSEMBLY_DATA/$GENE_SET

  local REMOTE_ORG_PATH="$REMOTE_ROOT/$THIS_ORG"
  local REMOTE_ASSEMBLY_PATH="$REMOTE_ORG_PATH/$DATA_ASSEMBLY"
  local REMOTE_GENESET_PATH="$REMOTE_ASSEMBLY_PATH/$GENE_SET"

  ## organism.sqlite is the file the live site actually serves, so its absence is
  ## checked FIRST and locally -- before a single connection is opened.
  ##
  ## send() skips a missing file (`elif [ -e ]`) and verify_remote returns early on
  ## one, so a database that was never built looked exactly like a successful publish:
  ## 36 files copied, "OK" logged, and the live site still serving a three-week-old
  ## database. For the one file the site actually serves, absent is a failure.
  if [ ! -s "$ORG_DATA/organism.sqlite" ]; then
    ## A gene set whose inputs do not exist yet was never meant to be built: say so in
    ## one quiet line (return 3) and keep the loud warning below for the real problem,
    ## a gene set that has its inputs but no database. Same tests as ready_to_build.sh.
    local RUN_DIR="$ANNOTATIONS/$THIS_ORG/$ASSEMBLY/$GENE_SET"
    local OMA_DIR="$OMA_BASE/$THIS_ORG/$ASSEMBLY/$GENE_SET"
    if [ ! -d "$RUN_DIR" ]; then
      log "WAIT  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — not built yet: no annotation run ($RUN_DIR)"
      return 3
    fi
    if [ ! -e "$OMA_DIR/REFERENCE_GENOME.txt" ] && [ ! -d "$OMA_DIR/Output" ]; then
      log "WAIT  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — not built yet: no OMA run ($OMA_DIR)"
      return 3
    fi
    log "SKIP  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — no organism.sqlite in $ORG_DATA"
    log "      Nothing published. The build did not leave a database where the copy"
    log "      step looks for it (REPO=$REPO). This gene set needs a build, not a copy."
    return 2
  fi

  if ! db_is_complete "$ORG_DATA/organism.sqlite"; then
    log "SKIP  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — organism.sqlite has no FTS index"
    log "      The build wrote features and annotations but stopped before the"
    log "      whole-database steps, so this database is incomplete and searching it"
    log "      would fail. Publishing it would REPLACE a working copy on the live"
    log "      site. Fix the build and re-run this organism; do not copy."
    return 2
  fi

  if [ ! -d "$GENESET_DATA" ]; then
    log "SKIP  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — no build directory $GENESET_DATA"
    return 2
  fi
  cd "$GENESET_DATA" || { log "FAIL  cannot cd to $GENESET_DATA"; return 1; }

  local HAS_GENOME=false
  ( [ -e "$ASSEMBLY_DATA/genome.fa" ] || [ -e "$GENOME_DIR/genome.fa" ] ) && HAS_GENOME=true

  ## mkdir even under --dry-run: it creates no data, and without it a first-time
  ## dry run fails on a missing destination and reports nothing useful.
  if ! retry "ssh mkdir $REMOTE_GENESET_PATH" \
       "${SSH[@]}" "$REMOTE" "mkdir -p '$REMOTE_GENESET_PATH'"; then
    log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — cannot ssh to '$REMOTE' or create $REMOTE_GENESET_PATH"
    log "      Nothing was copied. If this ran on a compute node, check that it can"
    log "      reach '$REMOTE' — a login node being able to is not the same thing."
    return 1
  fi

  ## Organism level (sqlite, json, cache all live at the organism dir, not geneset dir)
  ## crosswalk.sqlite: RosettaStone's, when the organism has one (send() skips it otherwise)
  send "$REMOTE_ORG_PATH" \
    "$ORG_DATA/organism.sqlite" "$ORG_DATA/organism.json" "$ORG_DATA/annotation_sources_cache.json" \
    "$ORG_DATA/crosswalk.sqlite"

  ## Assembly level: genome.json + genome + fai + nucl BLAST index (genome-backed only)
  send "$REMOTE_ASSEMBLY_PATH" "$ASSEMBLY_DATA/genome.json"
  if $HAS_GENOME; then
    send "$REMOTE_ASSEMBLY_PATH" \
      "$ASSEMBLY_DATA/genome.fa" "$ASSEMBLY_DATA/genome.fa.fai" "$ASSEMBLY_DATA/genome.fa.n*"
  fi
  ## Assembly statistics (process_one_geneset.sh): sent when there are some; otherwise any copy
  ## left on the server by an earlier run is removed, as for busco.json below.
  if [ -s "$ASSEMBLY_DATA/assembly_stats.json" ]; then
    send "$REMOTE_ASSEMBLY_PATH" "$ASSEMBLY_DATA/assembly_stats.json"
  else
    "${SSH[@]}" "$REMOTE" "rm -f '$REMOTE_ASSEMBLY_PATH/assembly_stats.json'" \
      || log "WARN  $THIS_ORG  [$ASSEMBLY] — could not remove a stale assembly_stats.json on $REMOTE"
  fi

  ## Gene-set level: geneset.json + GFF (genome-backed), FASTAs + indexes (always)
  ##
  ## NOT feature_coords.tsv / exon_coords.tsv (2026-11-12, website): the site builds them from the genes.gff
  ## shipped here, adding every ID without its gene-/rna-/cds-/id- prefix and each CDS's protein name, which
  ## the database IDs (LOC..., XP_...) need to find their location. One producer per file: a copy from here
  ## would replace those rows, and being newer than genes.gff it would pass the site's staleness check, so
  ## the lookups would fail silently. A new genes.gff makes the site rebuild them (hourly housekeeping).
  ##
  ## The ".fai" entries are NOT covered by the BLAST globs and have to be named. Those
  ## globs are keyed on the BLAST database suffixes -- ".p*" catches .pdb/.phr/.pin/.psq
  ## and ".n*" catches .ndb/.nhr/.nin/.nsq -- and a samtools index is ".fai", which starts
  ## with neither. Without these lines the pipeline builds the indexes on compute and they
  ## simply never arrive: MOOP falls back to blastdbcmd forever, nothing errors, and the
  ## only symptom is that gene pages stay slow. The genome-level copy above already names
  ## genome.fa.fai explicitly for the same reason.
  ##
  ## MOOP refuses an index older than its FASTA, so a .fai left behind by a partial copy
  ## is ignored rather than trusted -- but that costs the speedup silently, so copy both.
  if $HAS_GENOME; then
    send "$REMOTE_GENESET_PATH" \
      geneset.json genes.gff \
      protein.aa.fa "protein.aa.fa.p*" protein.aa.fa.fai \
      transcript.nt.fa "transcript.nt.fa.n*" transcript.nt.fa.fai \
      cds.nt.fa "cds.nt.fa.n*" cds.nt.fa.fai
  else
    send "$REMOTE_GENESET_PATH" \
      geneset.json \
      protein.aa.fa "protein.aa.fa.p*" protein.aa.fa.fai \
      transcript.nt.fa "transcript.nt.fa.n*" transcript.nt.fa.fai \
      cds.nt.fa "cds.nt.fa.n*" cds.nt.fa.fai
  fi

  ## BUSCO (process_one_geneset.sh): sent when there is one; when there is not -- no results,
  ## or they failed to parse -- any copy left on the server from an earlier run is removed, so
  ## the page never shows the BUSCO of gene models that are no longer there.
  if [ -s busco.json ]; then
    send "$REMOTE_GENESET_PATH" busco.json
  else
    "${SSH[@]}" "$REMOTE" "rm -f '$REMOTE_GENESET_PATH/busco.json'" \
      || log "WARN  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — could not remove a stale busco.json on $REMOTE"
  fi

  verify_remote "$ORG_DATA/organism.sqlite" "$REMOTE_ORG_PATH/organism.sqlite"

  if [ "$FAILED" -ne 0 ]; then
    log "FAIL  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — copy incomplete, live site NOT updated"
    return 1
  fi

  if [ "$XFER" -eq 0 ]; then
    ## "Same" alone doesn't say WHETHER that's this run's fresh build already
    ## published (e.g. by a job that was killed after copying but before
    ## logging) or something older that was never rebuilt -- both look
    ## identical without checking. Naming the local build's own mtime lets
    ## that be told apart at a glance instead of by hand-diffing local vs
    ## remote.
    local built
    built=$(stat -c '%y' "$ORG_DATA/organism.sqlite" 2>/dev/null | cut -d. -f1)
    log "SAME  $THIS_ORG  [$ASSEMBLY/$GENE_SET] — remote already matches this build (local built $built), nothing to do"
  elif $DRY_RUN; then
    log "WOULD $THIS_ORG  [$ASSEMBLY/$GENE_SET] — $XFER file(s) differ"
  else
    log "OK    $THIS_ORG  [$ASSEMBLY/$GENE_SET] — $XFER file(s) copied"
  fi
  return 0
}

## Clade homolog files (docs/CLADE_HOMOLOGS_FORMAT.md): data/.clades/<clade>/homologs.sqlite ->
## organisms/.clades/<clade>/. A file without meta.format = clade_homologs/1 or /2, or one that fails
## integrity_check, is not sent (the site would refuse it anyway). Sets CLADE_FAILED.
copy_clades() {
  CLADE_FAILED=0
  local f clade fmt n=0
  shopt -s nullglob
  local files=("$DATA"/.clades/*/homologs.sqlite)
  shopt -u nullglob
  if [ "${#files[@]}" -eq 0 ]; then
    log "CLADE none in $DATA/.clades"
    return 0
  fi
  for f in "${files[@]}"; do
    clade=$(basename "$(dirname "$f")")
    FAILED=0; XFER=0
    fmt=$(sqlite3 -readonly "$f" "SELECT value FROM meta WHERE key = 'format'" 2>/dev/null)
    ## clade_homologs/2 (pair evidence) goes only once the site reads it: CLADE_FORMAT2_OK=1 copy2moop_v2.sh ...
    if [ "$fmt" = "clade_homologs/2" ] && [ "${CLADE_FORMAT2_OK:-0}" != 1 ]; then
      log "FAIL  clade $clade — clade_homologs/2 file; not sent until the site reads it (set CLADE_FORMAT2_OK=1)"
      CLADE_FAILED=$(( CLADE_FAILED + 1 )); continue
    fi
    if { [ "$fmt" != "clade_homologs/1" ] && [ "$fmt" != "clade_homologs/2" ]; } || \
       [ "$(sqlite3 -readonly "$f" 'PRAGMA quick_check' 2>/dev/null)" != ok ]; then
      log "FAIL  clade $clade — $f is not a valid clade_homologs/1 or /2 file; not sent"
      CLADE_FAILED=$(( CLADE_FAILED + 1 )); continue
    fi
    ## same modes as the organism folders: directories 2775, files 664 (group apache comes from organisms/)
    if ! retry "ssh mkdir $REMOTE_ROOT/.clades/$clade" \
         "${SSH[@]}" "$REMOTE" "mkdir -p '$REMOTE_ROOT/.clades/$clade' && chmod 2775 '$REMOTE_ROOT/.clades' '$REMOTE_ROOT/.clades/$clade'"; then
      log "FAIL  clade $clade — cannot create $REMOTE_ROOT/.clades/$clade on $REMOTE"
      CLADE_FAILED=$(( CLADE_FAILED + 1 )); continue
    fi
    ## rsync writes to a temporary file and renames it into place (no --inplace), so a page reading
    ## homologs.sqlite never sees a half-written one; --chmod gives it 664 whatever the local mode
    RSYNC_EXTRA+=(--chmod=F664)
    send "$REMOTE_ROOT/.clades/$clade" "$f"
    unset 'RSYNC_EXTRA[${#RSYNC_EXTRA[@]}-1]'
    if [ "$FAILED" -ne 0 ]; then
      log "FAIL  clade $clade — copy incomplete"
      CLADE_FAILED=$(( CLADE_FAILED + 1 ))
    elif [ "$XFER" -eq 0 ]; then
      log "SAME  clade $clade — remote already matches"
    elif $DRY_RUN; then
      log "WOULD clade $clade — homologs.sqlite differs"
    else
      log "OK    clade $clade — homologs.sqlite copied"
    fi
    n=$(( n + 1 ))
  done
  return 0
}

## KEGG reference snapshot -> organisms/.kegg/ (notes/KEGG_GENE_PAGE_PLAN.md). The gene sets' KEGG
## annotations carry the snapshot date in annotation_source_version, so the site can tell when the copy
## here is a different snapshot from the one a gene set was loaded with. Sets KEGG_FAILED.
KEGG_FILES=(info.txt pathway.tsv module.tsv ko_pathway.tsv ko_module.tsv pathway_classes.json)
copy_kegg() {
  KEGG_FAILED=0
  local src=$REFERENCE_DATA/kegg/current f files=()
  for f in "${KEGG_FILES[@]}"; do
    if [ ! -s "$src/$f" ]; then
      log "FAIL  kegg — $src/$f missing or empty (run fetch_kegg_reference.sh); not sent"
      KEGG_FAILED=1; return 0
    fi
    files+=("$src/$f")
  done
  FAILED=0; XFER=0
  if ! retry "ssh mkdir $REMOTE_ROOT/.kegg" \
       "${SSH[@]}" "$REMOTE" "mkdir -p '$REMOTE_ROOT/.kegg' && chmod 2775 '$REMOTE_ROOT/.kegg'"; then
    log "FAIL  kegg — cannot create $REMOTE_ROOT/.kegg on $REMOTE"
    KEGG_FAILED=1; return 0
  fi
  ## send uses rsync -L, so current/ (a symlink to the dated snapshot) is followed; 664 like the clades
  RSYNC_EXTRA+=(--chmod=F664)
  send "$REMOTE_ROOT/.kegg" "${files[@]}"
  unset 'RSYNC_EXTRA[${#RSYNC_EXTRA[@]}-1]'
  if [ "$FAILED" -ne 0 ]; then
    log "FAIL  kegg — copy incomplete"; KEGG_FAILED=1
  elif [ "$XFER" -eq 0 ]; then
    log "SAME  kegg — remote already matches ($(readlink "$src"))"
  elif $DRY_RUN; then
    log "WOULD kegg — snapshot $(readlink "$src") differs"
  else
    log "OK    kegg — snapshot $(readlink "$src") copied"
  fi
  return 0
}

## ---------------------------------------------------------------- drivers

if $KEGG_ONLY; then
  [ "${#POSITIONAL[@]}" -eq 0 ] && ! $ALL && ! $CLADES_ONLY \
    || { echo "--kegg takes no other arguments (--all already includes it)" >&2; usage 1; }
  log "==== copy2moop --kegg$($DRY_RUN && echo ' [DRY RUN]')"
  copy_kegg
  [ "$KEGG_FAILED" -eq 0 ] || exit 1
  exit 0
fi

if $CLADES_ONLY; then
  [ "${#POSITIONAL[@]}" -eq 0 ] && ! $ALL \
    || { echo "--clades takes no other arguments (--all already includes the clades)" >&2; usage 1; }
  log "==== copy2moop --clades$($DRY_RUN && echo ' [DRY RUN]')"
  copy_clades
  [ "$CLADE_FAILED" -eq 0 ] || exit 1
  exit 0
fi

if $ALL; then
  [ "${#POSITIONAL[@]}" -eq 0 ] \
    || { echo "--all takes no organism/assembly/gene_set arguments" >&2; usage 1; }

  ## Same active-gene-set detection as run_all_v2.sh, so the two cannot disagree
  ## about what the site is supposed to contain.
  TASKS=()
  for META in "$GENOMES"/*/*/*/metadata.yaml; do
    [ -f "$META" ] || continue
    grep -qiE '^active:[[:space:]]*true[[:space:]]*$' "$META" || continue
    GENESET_DIR=$(dirname "$META")
    TASKS+=("$(basename "$(dirname "$(dirname "$GENESET_DIR")")")"$'\t'"$(basename "$(dirname "$GENESET_DIR")")"$'\t'"$(basename "$GENESET_DIR")")
  done

  [ "${#TASKS[@]}" -gt 0 ] || { log "FAIL  --all found no active gene sets under $GENOMES"; exit 1; }

  log "==== copy2moop --all: ${#TASKS[@]} active gene set(s)$($DRY_RUN && echo ' [DRY RUN]')"

  n_ok=0; n_same=0; n_nobuild=0; n_wait=0; n_fail=0
  FAILED_LIST=()
  for task in "${TASKS[@]}"; do
    IFS=$'\t' read -r ORG ASM GS <<<"$task"
    ## Sequential by design: one connection at a time cannot trip MaxStartups, which
    ## is what broke the parallel array run in the first place.
    copy_one_geneset "$ORG" "$ASM" "$GS"
    case $? in
      0) if [ "$XFER" -eq 0 ]; then n_same=$(( n_same + 1 )); else n_ok=$(( n_ok + 1 )); fi ;;
      2) n_nobuild=$(( n_nobuild + 1 )) ;;
      3) n_wait=$(( n_wait + 1 )) ;;
      *) n_fail=$(( n_fail + 1 )); FAILED_LIST+=("$ORG $ASM $GS") ;;
    esac
  done

  log "==== summary"
  ## Under --dry-run nothing was copied, so do not say it was. A report that reads
  ## the same whether or not it changed anything is the kind of log that gets trusted
  ## when it should not be.
  log "     $($DRY_RUN && echo 'would copy ' || echo 'copied     ') : $n_ok"
  log "     already ok  : $n_same"
  log "     not built yet   : $n_wait   (no annotation run or no OMA run yet — expected)"
  log "     not publishable : $n_nobuild   (inputs ready but database missing or incomplete — needs a build, not a copy)"
  log "     FAILED      : $n_fail"
  for f in ${FAILED_LIST+"${FAILED_LIST[@]}"}; do log "       $f"; done

  log "==== clades"
  copy_clades

  log "==== kegg"
  copy_kegg

  [ "$n_fail" -eq 0 ] && [ "$CLADE_FAILED" -eq 0 ] && [ "$KEGG_FAILED" -eq 0 ] || exit 1
  exit 0
fi

[ "${#POSITIONAL[@]}" -eq 3 ] || usage 1
copy_one_geneset "${POSITIONAL[0]}" "${POSITIONAL[1]}" "${POSITIONAL[2]}"
rc=$?
## A gene set with nothing built is not a publish failure in --all, but on an explicit
## single invocation the caller asked for THIS one by name, so absent is an error.
[ "$rc" -eq 0 ] || exit 1
exit 0
