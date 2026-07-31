#!/usr/bin/bash
#SBATCH --job-name=MOOP_LOAD
#SBATCH --cpus-per-task=4
#SBATCH --mem=16gb
#SBATCH --time=1-00:00:00
#SBATCH --output=slurm_logs/%x_%A_%a.out

## Build and load ONE ORGANISM: every active gene set it has, then the whole-database
## steps once, then the copy to moop.
##
## Usage:
##   Single job : sbatch moop_process_genome_data_v2.sbatch <organism> [assembly] [gene_set]
##   Array job  : sbatch --array=0-80%10 scripts/moop_process_genome_data_v2.sbatch
##                (reads the organism from $ORGANISMS_FILE by task index)
##
## The assembly and gene_set arguments NARROW the run to part of the organism. They
## are for working on one gene set without reprocessing the rest:
##
##   sbatch ...sbatch Schmidtea_mediterranea
##   sbatch ...sbatch Schmidtea_mediterranea schMedA2h1
##   sbatch ...sbatch Schmidtea_mediterranea schMedA2h1 schMedA2h1_orig
##
## ─────────────────────────────────────────────────────────────────────────────
## WHY THE ORGANISM IS THE UNIT OF WORK
##
## organism.sqlite is per ORGANISM. This job used to be per GENE SET, and that
## mismatch is where the sharp edges were:
##
##   * Reloading meant dropping a database shared with gene sets the job was not
##     processing, so a forced reload of one gene set silently deleted its siblings.
##     It now cannot: the job owns the organism, so its scope and the database's
##     scope are the same thing.
##   * The FTS index rebuild and VACUUM ran once per gene set over the whole
##     database. Phagocata_velata is 1.8 GB across 3 gene sets: three full rebuilds
##     and three VACUUMs where one of each will do.
##   * copy2moop_v2.sh rsync'd organism.sqlite once per gene set, so a task could
##     ship a database holding only its own gene set to the live site.
##
## 81 organisms hold 92 active gene sets, so with --array ...%10 the concurrency is
## effectively unchanged; only 7 organisms have more than one gene set at all.
## ─────────────────────────────────────────────────────────────────────────────

set -uo pipefail

REPO=${SLURM_SUBMIT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}
SCRIPTS=$REPO/scripts
DATA=$REPO/data

## REPO comes from SLURM_SUBMIT_DIR -- the directory sbatch was SUBMITTED FROM, not
## where this script lives, and it wins over the fallback above. Submitting from the
## wrong directory therefore points DATA (where organism.sqlite is built) and every
## loader path at somewhere arbitrary, and the failure that follows names a missing
## file rather than the actual mistake. Fail here instead, saying what to do.
## run_all_v2.sh is unaffected: it cds to its own location before submitting.
if [ ! -d "$SCRIPTS" ] || [ ! -d "$REPO/data_loaders" ]; then
  echo "ERROR: REPO=$REPO does not look like the pipeline directory." >&2
  echo "       (expected $REPO/scripts and $REPO/data_loaders to exist)" >&2
  echo "" >&2
  echo "       sbatch takes REPO from SLURM_SUBMIT_DIR, i.e. WHERE YOU RAN sbatch." >&2
  echo "       cd to .../moop-pipeline/config/build_and_load_db first, then submit:" >&2
  echo "           sbatch scripts/$(basename "$0") --reload <organism>" >&2
  exit 1
fi

## --reload / --force may be given as a FLAG, anywhere among the arguments, as well
## as through the MOOP_RELOAD environment variable. The array path has to use the
## environment (run_all_v2.sh passes it via sbatch --export), but a hand-run should
## not depend on remembering that these two differ:
##
##     MOOP_RELOAD=1 sbatch ... Org      # works: sets it in sbatch's environment
##     MOOP_RELOAD=1 ; sbatch ... Org    # SILENTLY DOES NOT: a plain shell variable
##                                       # is not exported, so the job never sees it,
##                                       # MOOP_RELOAD defaults to 0, every step is
##                                       # skipped as "already built", and the run
##                                       # reports success having done nothing.
##
## NOTE: the flag must come AFTER the script path, or sbatch will try to parse it.
CLI_RELOAD=0
ARGS=()
for arg in "$@"; do
  case "$arg" in
    --reload|--force) CLI_RELOAD=1 ;;
    *)                ARGS+=("$arg") ;;
  esac
done
set -- "${ARGS[@]:-}"

if [[ -n "${SLURM_ARRAY_TASK_ID:-}" ]]; then
  ORGANISMS_FILE="${ORGANISMS_FILE:-$REPO/active_organisms.tsv}"
  THIS_ORG=$(sed -n "$((SLURM_ARRAY_TASK_ID + 1))p" "$ORGANISMS_FILE")
  WANT_ASSEMBLY=""
  WANT_GENESET=""
else
  THIS_ORG=${1:-}
  WANT_ASSEMBLY=${2:-}
  WANT_GENESET=${3:-}
fi

[ -n "$THIS_ORG" ] || {
  echo "Usage: $0 [--reload] <organism> [assembly] [gene_set]  (or run as an array job)"
  echo "       --reload may also be given as MOOP_RELOAD=1 in the environment."
  exit 1
}

## ── --reload: rebuild and reload, ignoring the "already built" checks ─────────
##
## Set by run_all_v2.sh --reload, or by hand:
##     MOOP_RELOAD=1 sbatch ...sbatch Schmidtea_mediterranea
##
## THE INVARIANT: a reload invalidates exactly what the invocation names, and
## nothing else.
##
##   organism scope  -> drop organism.sqlite entirely and rebuild every gene set
##   narrowed scope  -> delete ONLY the named gene sets' rows from the database
##                      (leaving their siblings untouched), remove only their
##                      intermediates, and reload only them
##
## The narrowed case deletes rows first rather than just re-running the loaders,
## because both loaders UPSERT: load_genes_sqlite.pl matches on
## (uniquename, gene_set_id) and updates, load_annotations_sqlite.pl on
## (feature_id, annotation_id). Re-running therefore corrects what is still present
## and adds what is new, but leaves behind anything the new files no longer contain.
## That is not hypothetical: commit 3566673 stopped emitting :cds/:pep rows for
## non-coding transcripts, so a plain re-run with the fixed parser would add the
## correct rows and keep every bogus one, and report success.
MOOP_RELOAD=${MOOP_RELOAD:-${FORCE_RELOAD:-0}}     # FORCE_RELOAD: accepted alias
[ "$CLI_RELOAD" = "1" ] && MOOP_RELOAD=1           # --reload flag wins over the default
export MOOP_RELOAD

NARROWED=false
[ -n "$WANT_ASSEMBLY" ] || [ -n "$WANT_GENESET" ] && NARROWED=true

## ── Which gene sets does this run cover? ──────────────────────────────────────
##
## Always derived from the shared definition of "active", then narrowed by the
## optional arguments. A narrowing that matches nothing is an ERROR, not an empty
## success -- silently doing nothing on a typo is this pipeline's recurring failure
## mode, and it is how a gene set gets quietly left out of a release.
##
## In an array run, read the list FROZEN at submission ($GENESETS_FILE) rather than
## re-scanning the genomes tree, so every task of a run agrees about what was active
## even if the tree changes underneath a long run. Standalone, scan live.
if [ -n "${GENESETS_FILE:-}" ] && [ -s "${GENESETS_FILE:-}" ]; then
  geneset_source() { cat "$GENESETS_FILE"; }
else
  geneset_source() { bash "$SCRIPTS/list_active_genesets.sh"; }
fi

mapfile -t GENESETS < <(
  geneset_source \
    | awk -F'\t' -v o="$THIS_ORG" -v a="$WANT_ASSEMBLY" -v g="$WANT_GENESET" '
        $1 == o && (a == "" || $2 == a) && (g == "" || $3 == g) { print $2 "\t" $3 }'
)

if [ ${#GENESETS[@]} -eq 0 ]; then
  echo "ERROR: no active gene set matches ${THIS_ORG}${WANT_ASSEMBLY:+/$WANT_ASSEMBLY}${WANT_GENESET:+/$WANT_GENESET}" >&2
  echo "" >&2
  echo "Active gene sets for $THIS_ORG:" >&2
  available=$(geneset_source | awk -F'\t' -v o="$THIS_ORG" '$1 == o { print "  " $2 "/" $3 }')
  if [ -n "$available" ]; then
    printf '%s\n' "$available" >&2
  else
    echo "  (none — is metadata.yaml missing 'active: true'?)" >&2
  fi
  exit 1
fi

echo "Organism  : $THIS_ORG"
echo "Gene sets : ${#GENESETS[@]}$($NARROWED && echo "  (narrowed to ${WANT_ASSEMBLY:-*}/${WANT_GENESET:-*})")"
printf '            %s\n' "${GENESETS[@]}"
[ "$MOOP_RELOAD" = "1" ] && echo "Reload    : yes — invalidating what this run covers"

ORG_DATA=$DATA/$THIS_ORG
DB=$ORG_DATA/organism.sqlite
mkdir -p "$ORG_DATA"

## ── Reload: invalidate, scoped to what was named ──────────────────────────────
if [ "$MOOP_RELOAD" = "1" ]; then
  if $NARROWED; then
    # Narrowed: surgically remove just these gene sets, keep the rest of the DB.
    while IFS=$'\t' read -r asm gs; do
      echo "Reload: removing gene set '$gs' from $DB"
      bash "$SCRIPTS/delete_gene_set.sh" "$DB" "$gs" || exit 1
      # Intermediates, so they are rebuilt from source rather than reused.
      rm -f "$ORG_DATA/$asm/$gs/features.tsv" \
            "$ORG_DATA/$asm/$gs/isoforms.tsv" \
            "$ORG_DATA/$asm/$gs/geneNames.tsv"
    done < <(printf '%s\n' "${GENESETS[@]}")
  else
    # Whole organism: drop the file. A schema is not editable in place -- UNIQUE,
    # NOT NULL and a corrected FOREIGN KEY cannot be added to an existing SQLite
    # file, so loading into a surviving database silently keeps the OLD schema.
    echo "Reload: dropping $DB so it is recreated from the current schema"
    rm -f "$DB" "$DB-wal" "$DB-shm" "$ORG_DATA/annotation_sources_cache.json"
    while IFS=$'\t' read -r asm gs; do
      rm -f "$ORG_DATA/$asm/$gs/features.tsv" \
            "$ORG_DATA/$asm/$gs/isoforms.tsv" \
            "$ORG_DATA/$asm/$gs/geneNames.tsv"
    done < <(printf '%s\n' "${GENESETS[@]}")
  fi
fi

## ── Build and load each gene set ──────────────────────────────────────────────
##
## Serial by design: they share one database, and the loads were already serialised
## by the flock in setup_new_moopdb_and_load_data.sh. A failure stops the organism
## rather than letting a half-loaded database reach the copy step below.
for entry in "${GENESETS[@]}"; do
  IFS=$'\t' read -r asm gs <<< "$entry"
  echo ""
  echo "═══ $THIS_ORG / $asm / $gs ═══"
  bash "$SCRIPTS/process_one_geneset.sh" "$THIS_ORG" "$asm" "$gs" || {
    echo "ERROR: gene set $asm/$gs failed for $THIS_ORG — stopping." >&2
    echo "       Not running the whole-database steps and not copying to moop:" >&2
    echo "       organism.sqlite may be incomplete." >&2
    exit 1
  }
done

## ── Whole-database steps: ONCE, after every gene set has loaded ───────────────
if [ ! -s "$DB" ]; then
  echo "ERROR: $DB does not exist after processing ${#GENESETS[@]} gene set(s)." >&2
  exit 1
fi

echo ""
echo "═══ $THIS_ORG: whole-database steps ═══"

echo "Generating annotation_sources_cache.json"
perl "$REPO/data_loaders/make_annotation_sources_cache.pl" \
  "$DB" "$ORG_DATA/annotation_sources_cache.json" || exit 1

# Keep SQLite's scratch files next to the database, NOT in the node's /tmp.
#
# Both steps below spill: the FTS merge writes intermediate segments, and VACUUM writes
# a COMPLETE second copy of the database. Unset, SQLite uses /tmp, which on a compute
# node may be small, node-local, or shared with every other job on the box. When this
# happened on the web host -- a 4 GB /tmp, four concurrent rebuilds -- it reported
# "database or disk is full" while the data volume had 649 GB free, which sends you
# looking at entirely the wrong filesystem. $ORG_DATA is guaranteed to have room,
# because the database being written already lives there.
export SQLITE_TMPDIR="${SQLITE_TMPDIR:-$ORG_DATA}"

echo "Building FTS5 search index"
sqlite3 "$DB" < "$REPO/data_loaders/build_fts_index.sql" || exit 1

# The FTS index is contentless, and a rebuild frees the old pages inside the file
# without returning them to the filesystem. VACUUM is what actually shrinks it --
# worth it because database size is what competes for page cache, and cold reads are
# this deployment's dominant cost. Measured 2026-07-31: compacting made a representative
# COUNT(*) 17.6x faster cold (7,051 -> 401 ms) on identical SQL, purely by making the
# table's pages contiguous rather than scattered through the file.
#
# Needs temporary free space roughly equal to the database size. Set MOOP_SKIP_VACUUM=1
# to skip.
#
# A FAILURE HERE IS NOT FATAL. The index above is already built and committed; VACUUM
# only reclaims freed pages, so failing it leaves a CORRECT database that is merely
# larger than it needs to be. Exiting would discard a good build over a space
# optimisation -- and the organism would then be missing from the site entirely, which
# is far worse than a fragmented file.
if [ "${MOOP_SKIP_VACUUM:-0}" != "1" ]; then
  echo "Compacting database (VACUUM)"
  if ! sqlite3 "$DB" "VACUUM;"; then
    echo "WARNING: VACUUM failed for $THIS_ORG -- database is correct but not compacted."
    echo "WARNING: re-run 'sqlite3 $DB \"VACUUM;\"' with space available in $SQLITE_TMPDIR."
  fi
fi

## ── Copy to moop ──────────────────────────────────────────────────────────────
## After every gene set has loaded, so the database that reaches the live site is
## never missing one.
if [ "${SKIP_COPY:-0}" -eq 1 ]; then
  echo "SKIP_COPY=1: not copying $THIS_ORG to moop."
else
  for entry in "${GENESETS[@]}"; do
    IFS=$'\t' read -r asm gs <<< "$entry"
    ## Pass REPO explicitly. This script has it from $SLURM_SUBMIT_DIR; letting the
    ## copy re-derive it from its own location sent it into the git checkout when
    ## scripts/ is a symlink, so the build wrote to one tree and the copy read another.
    REPO="$REPO" sh "$SCRIPTS/copy2moop_v2.sh" "$THIS_ORG" "$asm" "$gs" || exit 1
  done
fi

echo ""
echo "Done: $THIS_ORG (${#GENESETS[@]} gene set(s))"
