#!/bin/bash
# Rebuild every organism's FTS index, in parallel, without taking search down.
#
# DEFAULT IS A COPY, BECAUSE OF LOCKING. The organism databases are journal_mode=delete,
# so a write transaction holds an EXCLUSIVE lock for its whole duration and BLOCKS
# READERS. The rebuild is one transaction lasting ~2 minutes, so rebuilding in place makes
# that organism's search return "Search error" for two minutes -- 85 times over. By
# default each worker instead copies the database, rebuilds the copy, checks it, and
# renames it into place; rename(2) within one filesystem is atomic, so a request gets
# either the whole old file or the whole new one, never a half-built index.
#
# -i rebuilds in place: faster, and it skips copying ~33 GB, but ONLY safe when nobody is
# searching. It is still crash-safe -- build_fts_index.sql is one transaction, so an
# interrupt rolls back to the previous good index rather than leaving none.
#
# NEVER replaces a good database with an unverified one. Before the rename a copy must
# pass quick_check, must actually carry annotation_type_code, and must have exactly one
# FTS row per feature_annotation row. Any failure leaves the original untouched.
#
# Resumable: an organism that already carries the column is skipped, so re-running after
# a failure picks up where it stopped.
#
# usage:
#   scripts/rebuild_fts_indexes.sh              # all organisms, 4 at a time, via copies
#   scripts/rebuild_fts_indexes.sh -i -j 6      # in place, 6 at a time (no live traffic)
#   scripts/rebuild_fts_indexes.sh -n           # dry run: list what would be done
#   scripts/rebuild_fts_indexes.sh Nematostella_vectensis ...   # named organisms only
set -uo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
SQL="$ROOT/config/build_and_load_db/data_loaders/build_fts_index.sql"
ORGDIR="$ROOT/organisms"
LOGDIR="$ROOT/logs/fts_rebuild"
JOBS=4
DRY=0
INPLACE=0

while getopts "j:ni" opt; do
  case $opt in
    j) JOBS=$OPTARG ;;
    n) DRY=1 ;;
    i) INPLACE=1 ;;
    *) echo "usage: $0 [-j N] [-n] [-i] [organism ...]"; exit 2 ;;
  esac
done
shift $((OPTIND - 1))

[[ -r $SQL ]] || { echo "missing $SQL"; exit 1; }
mkdir -p "$LOGDIR"

# SQLite's temp files must NOT land in /tmp. VACUUM writes a full second copy of the
# database there, and the FTS merge spills too, so several GB per job -- while /tmp on
# this host is a 4 GB volume. Four parallel jobs exhausted it and reported "database or
# disk is full" even though the data volume had 649 GB free, which reads like a bug in
# the wrong place entirely. Keep temp on the same filesystem as the databases.
export SQLITE_TMPDIR="${SQLITE_TMPDIR:-$ORGDIR/.fts_tmp}"
mkdir -p "$SQLITE_TMPDIR" || { echo "cannot create $SQLITE_TMPDIR"; exit 1; }
avail=$(df -BG --output=avail "$SQLITE_TMPDIR" | tail -1 | tr -dc '0-9')
(( avail > 20 )) || { echo "only ${avail}G free at $SQLITE_TMPDIR -- VACUUM needs room"; exit 1; }

one() {
  local org=$1
  local db="$ORGDIR/$org/organism.sqlite"
  local tmp="$db.rebuild.$$" vac=""
  local log="$LOGDIR/$org.log"
  [[ -f $db ]] || { echo "SKIP  $org (no database)"; return 0; }

  # Already carries the column? Nothing to do -- makes the script resumable after a
  # failure or an interrupted run, which matters when the whole pass takes hours.
  if sqlite3 "file:$db?mode=ro" \
       "SELECT sql FROM sqlite_master WHERE name='feature_annotation_search';" 2>/dev/null \
       | grep -q annotation_type_code; then
    echo "DONE  $org (already rebuilt)"; return 0
  fi

  if (( DRY )); then echo "WOULD $org"; return 0; fi

  local t0=$SECONDS
  if (( INPLACE )); then
    # No copy. Safe only because build_fts_index.sql wraps its drop+rebuild in one
    # transaction, so an interrupt rolls back to the PREVIOUS good index rather than
    # leaving none. The reader-blocking above still applies -- use this when nobody is
    # searching, not to save time on a live site.
    tmp=$db
    sqlite3 "$db" < "$SQL"       >"$log" 2>&1      || { echo "FAIL  $org (rebuild, see $log)"; return 1; }
    # VACUUM only reclaims the pages the old index freed. Failing it leaves a CORRECT
    # database that is merely larger than it needs to be, so it is a warning, not a
    # failure -- calling it FAIL sent three already-good rebuilds back for a re-run.
    sqlite3 "$db" "VACUUM;"     >>"$log" 2>&1      || vac=" (vacuum skipped: $log)"
  else
    rm -f "$tmp"
    cp "$db" "$tmp"                                || { echo "FAIL  $org (copy)"; rm -f "$tmp"; return 1; }
    sqlite3 "$tmp" < "$SQL"      >"$log" 2>&1      || { echo "FAIL  $org (rebuild, see $log)"; rm -f "$tmp"; return 1; }
    sqlite3 "$tmp" "VACUUM;"    >>"$log" 2>&1      || vac=" (vacuum skipped: $log)"
  fi

  # --- verify before replacing anything ---------------------------------------------
  local chk cols n_fts n_fa
  chk=$(sqlite3 "file:$tmp?mode=ro" "PRAGMA quick_check;" 2>>"$log")
  cols=$(sqlite3 "file:$tmp?mode=ro" "SELECT sql FROM sqlite_master WHERE name='feature_annotation_search';" 2>>"$log")
  n_fts=$(sqlite3 "file:$tmp?mode=ro" "SELECT COUNT(*) FROM feature_annotation_search_docsize;" 2>>"$log")
  n_fa=$(sqlite3 "file:$tmp?mode=ro"  "SELECT COUNT(*) FROM feature_annotation;" 2>>"$log")

  if [[ $chk != ok ]] || ! grep -q annotation_type_code <<<"$cols" \
     || [[ -z $n_fts || -z $n_fa || $n_fts -ne $n_fa ]]; then
    # NEVER rm here when in place: $tmp IS the live database, and deleting it would
    # destroy the organism while printing "original untouched". Only the copy path has
    # a throwaway file to remove.
    if (( INPLACE )); then
      echo "FAIL  $org (verify: check=$chk fts=$n_fts fa=$n_fa) -- database LEFT AS IS, re-run to retry"
    else
      echo "FAIL  $org (verify: check=$chk fts=$n_fts fa=$n_fa) -- original untouched"
      rm -f "$tmp"
    fi
    return 1
  fi

  if (( ! INPLACE )); then
    chmod --reference="$db" "$tmp" 2>/dev/null
    chown --reference="$db" "$tmp" 2>/dev/null
    mv -f "$tmp" "$db"                             || { echo "FAIL  $org (rename)"; rm -f "$tmp"; return 1; }
  fi
  printf 'OK    %-38s %4ds  %s rows%s\n' "$org" "$((SECONDS - t0))" "$n_fts" "$vac"
}
export -f one
export ORGDIR SQL LOGDIR DRY INPLACE

if (( $# )); then orgs=("$@"); else
  mapfile -t orgs < <(cd "$ORGDIR" && ls -d */ 2>/dev/null | tr -d /)
fi

echo "  ${#orgs[@]} organisms, $JOBS at a time, logs in $LOGDIR"
start=$SECONDS
printf '%s\n' "${orgs[@]}" | xargs -P "$JOBS" -I{} bash -c 'one "$@"' _ {}
echo "  finished in $(( (SECONDS - start) / 60 ))m $(( (SECONDS - start) % 60 ))s"
