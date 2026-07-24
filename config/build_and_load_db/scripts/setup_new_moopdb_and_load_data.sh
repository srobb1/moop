#!/usr/bin/env bash
#
# Build an organism.sqlite and load one gene set plus its annotation files.
#
# Usage:  setup_new_moopdb_and_load_data.sh <ORGANISM> [GENE_SET_NAME] [ORG_DATA_DIR]
#
# Run from the directory holding features.tsv and the *.moop.tsv annotation files,
# or pass ORG_DATA_DIR.

set -euo pipefail

# Locate the loader scripts.
#
# Two layouts are in use and both must work:
#   compute:  .../v2/scripts/<this script>  with  .../v2/data_loaders/*.pl
#   moop repo: config/build_and_load_db/  -- everything flat, side by side
#
# This used to be a bare "$REPO/data_loaders", which resolved to a directory
# that does not exist in the repo, so every perl/sqlite3 path below was silently
# broken in a fresh checkout. Probe the candidates and fail loudly if none has
# the loaders, rather than running on with paths that cannot work.
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
SCRIPT_DIR=""
for candidate in "$HERE" "$HERE/data_loaders" "$HERE/data_loader" \
                 "$HERE/../data_loaders" "$HERE/../data_loader"; do
    if [ -f "$candidate/import_genes_sqlite.pl" ]; then
        SCRIPT_DIR=$(cd "$candidate" && pwd)
        break
    fi
done
if [ -z "$SCRIPT_DIR" ]; then
    echo "ERROR: cannot find import_genes_sqlite.pl near $HERE" >&2
    echo "       Looked in: . data_loaders data_loader ../data_loaders ../data_loader" >&2
    exit 1
fi

ORG=${1:?Usage: $0 <ORGANISM> [GENE_SET_NAME] [ORG_DATA_DIR]}
GENE_SET_NAME=${2:-primary}
ORG_DATA_DIR=${3:-.}   # organism-level output dir; defaults to cwd for backward compat

DB="$ORG_DATA_DIR/organism.sqlite"
LOCKFILE="$ORG_DATA_DIR/.organism.lock"
FEATURES="$ORG_DATA_DIR/features.tsv"

if [ ! -d "$ORG_DATA_DIR" ]; then
    echo "ERROR: organism data dir not found: $ORG_DATA_DIR" >&2
    exit 1
fi
if [ ! -s "$FEATURES" ]; then
    echo "ERROR: features file missing or empty: $FEATURES" >&2
    echo "       Refusing to load annotations against a database with no features." >&2
    exit 1
fi

## Acquire an exclusive lock on the organism dir so parallel array tasks for the
## same organism don't race on DB creation, annotation loading, or cache generation.
exec 200>"$LOCKFILE"
flock -x 200

if [ ! -e "$DB" ]; then
  echo "Creating schema: $DB"
  sqlite3 "$DB" < "$SCRIPT_DIR/create_schema_sqlite.sql"
fi

echo "Loading gene set '$GENE_SET_NAME' for: $ORG"
perl "$SCRIPT_DIR/import_genes_sqlite.pl" "$DB" "$FEATURES" "$GENE_SET_NAME"

# Gate annotation loading on features actually existing.
#
# Annotation files identify features by uniquename. Loading them against a
# database whose features are missing produces annotation rows that nothing
# points at, and every individual command still succeeds -- which is how one
# organism ended up with 306,781 annotations, 57 sources and zero features while
# the pipeline reported success. Check once, here, rather than trusting exit codes
# alone.
feature_count=$(sqlite3 "$DB" "SELECT COUNT(*) FROM feature;")
if [ "$feature_count" -eq 0 ]; then
    echo "ERROR: no features in $DB after importing $FEATURES." >&2
    echo "       Not loading annotations -- they would all be orphaned." >&2
    exit 1
fi
echo "  features in database: $feature_count"

echo "Loading Annotations for: $ORG"

# Enable nullglob so empty globs expand to nothing instead of literal pattern
shopt -s nullglob

# Function to load annotation files
load_files() {
    local pattern="$1"
    local description="$2"

    echo "Loading $description"
    local files=("$ORG_DATA_DIR"/$pattern)

    if [ ${#files[@]} -eq 0 ]; then
        echo "  Warning: No files found matching $pattern"
        return 0
    fi

    ## Pass every matched file to a SINGLE perl invocation instead of one
    ## invocation per file -- load_annotations_fast.pl builds its feature /
    ## feature_annotation caches once per invocation by scanning the whole
    ## (shared, per-organism) DB, so calling it once per file made loading
    ## scale as O(files * db_size) instead of O(db_size + total_rows).
    ##
    ## set -e aborts on a non-zero exit. Annotation loading is not best-effort:
    ## a file that fails to load leaves the database in a state nobody inspects,
    ## and the site then shows fewer results with no error anywhere.
    printf '  %s\n' "${files[@]}"
    perl "$SCRIPT_DIR/load_annotations_fast.pl" "$DB" "${files[@]}"
}

# Load all annotation types
load_files "*.oma_orthologs.moop.tsv" "OMA Orthologs"
load_files "*.oma_pairs.moop.tsv" "OMA Pairwise Orthologs"
load_files "eggnog_orthologs.moop.tsv" "EGGNOG Orthologs"
load_files "*.homologs.moop.tsv" "Blast homologs"
load_files "*.RBBH.moop.tsv" "Reciprocal Blast Best Hit homologs"
load_files "*.iprscan.moop.tsv" "Domains and IPRSCAN2GO and PANTHER2GO"
load_files "protnlm.moop.tsv" "Protnlm"
load_files "EggNOG2GO.eggnog.reduced.moop.tsv" "Eggnog2GO"
load_files "*OMA2GO.moop.tsv" "OMA2GO"

echo "Generating annotation_sources_cache.json"
perl "$SCRIPT_DIR/make_annotation_sources_cache.pl" "$DB" "$ORG_DATA_DIR/annotation_sources_cache.json"

echo "Building FTS5 search index"
sqlite3 "$DB" < "$SCRIPT_DIR/build_fts_index.sql"

# The FTS index is contentless, and a rebuild frees the old pages inside the file
# without returning them to the filesystem. VACUUM is what actually shrinks it --
# worth it here because database size is what competes for page cache, and cold
# reads are this deployment's dominant cost. Needs temporary free space roughly
# equal to the database size. Set MOOP_SKIP_VACUUM=1 to skip.
if [ "${MOOP_SKIP_VACUUM:-0}" != "1" ]; then
    echo "Compacting database (VACUUM)"
    sqlite3 "$DB" "VACUUM;"
fi

echo "Done: $ORG / $GENE_SET_NAME"

## Lock is released automatically when the script exits (fd 200 closes)
