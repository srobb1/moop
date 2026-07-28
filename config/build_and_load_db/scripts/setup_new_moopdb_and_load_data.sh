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
    if [ -f "$candidate/load_genes_sqlite.pl" ]; then
        SCRIPT_DIR=$(cd "$candidate" && pwd)
        break
    fi
done
if [ -z "$SCRIPT_DIR" ]; then
    echo "ERROR: cannot find load_genes_sqlite.pl near $HERE" >&2
    echo "       Looked in: . data_loaders data_loader ../data_loaders ../data_loader" >&2
    exit 1
fi

ORG=${1:?Usage: $0 <ORGANISM> [GENE_SET_NAME] [ORG_DATA_DIR] [GENESET_DATA_DIR]}
GENE_SET_NAME=${2:-primary}
ORG_DATA_DIR=${3:-.}   # organism-level: holds organism.sqlite and the lock

## TWO DIRECTORIES, and they are not the same one.
##
## The database is per ORGANISM. features.tsv and the *.moop.tsv annotation files are
## per GENE SET, and live under <org>/<assembly>/<gene_set>/. Before the organism
## became the unit of work both were the cwd, so one variable served for both. The
## refactor started passing the organism directory as ORG_DATA_DIR -- which correctly
## moved the database up a level, and silently took features.tsv and every annotation
## file with it.
##
## The result was a reload that built every intermediate correctly and then failed with
## "features file missing or empty: <org>/features.tsv" while features.tsv sat in
## <org>/<assembly>/<gene_set>/. Worse, had that check passed, load_files() globbed the
## same wrong directory, so the load would have attached ZERO annotations and the only
## sign would have been the loader's own integrity gate.
##
## Defaults to cwd, which is where process_one_geneset.sh runs from, so an old caller
## passing three arguments still behaves as it did.
GENESET_DATA_DIR=${4:-$PWD}

DB="$ORG_DATA_DIR/organism.sqlite"
LOCKFILE="$ORG_DATA_DIR/.organism.lock"
FEATURES="$GENESET_DATA_DIR/features.tsv"

if [ ! -d "$ORG_DATA_DIR" ]; then
    echo "ERROR: organism data dir not found: $ORG_DATA_DIR" >&2
    exit 1
fi
if [ ! -d "$GENESET_DATA_DIR" ]; then
    echo "ERROR: gene-set data dir not found: $GENESET_DATA_DIR" >&2
    exit 1
fi
if [ ! -s "$FEATURES" ]; then
    echo "ERROR: features file missing or empty: $FEATURES" >&2
    echo "       Refusing to load annotations against a database with no features." >&2
    echo "       (organism dir: $ORG_DATA_DIR)" >&2
    echo "       (gene-set dir: $GENESET_DATA_DIR — features.tsv belongs HERE)" >&2
    exit 1
fi

## Acquire an exclusive lock on the organism dir so parallel array tasks for the
## same organism don't race on DB creation, annotation loading, or cache generation.
exec 200>"$LOCKFILE"
flock -x 200

## NOTE: this script never drops the database. Invalidation belongs to the caller,
## moop_process_genome_data_v2.sbatch, which owns the whole ORGANISM and therefore the
## whole file -- see MOOP_RELOAD there. A per-gene-set drop is not expressible safely:
## organism.sqlite is shared, so gene set B would delete what gene set A just loaded.

if [ ! -e "$DB" ]; then
  echo "Creating schema: $DB"
  sqlite3 "$DB" < "$SCRIPT_DIR/create_schema_sqlite.sql"
fi

echo "Loading gene set '$GENE_SET_NAME' for: $ORG"
perl "$SCRIPT_DIR/load_genes_sqlite.pl" "$DB" "$FEATURES" "$GENE_SET_NAME"

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
    ## Per GENE SET, like features.tsv -- not the organism directory, which holds
    ## only organism.sqlite and the lock.
    local files=("$GENESET_DATA_DIR"/$pattern)

    ## An unmatched glob leaves the pattern itself as the single element, so a
    ## count of 1 does not mean a file was found. Checking existence is what makes
    ## "no annotation files here" visible instead of passing a literal '*' to perl.
    if [ ${#files[@]} -eq 0 ] || [ ! -e "${files[0]}" ]; then
        echo "  Warning: No files found matching $pattern in $GENESET_DATA_DIR"
        return 0
    fi

    ## Pass every matched file to a SINGLE perl invocation instead of one
    ## invocation per file -- load_annotations_sqlite.pl builds its feature /
    ## feature_annotation caches once per invocation by scanning the whole
    ## (shared, per-organism) DB, so calling it once per file made loading
    ## scale as O(files * db_size) instead of O(db_size + total_rows).
    ##
    ## set -e aborts on a non-zero exit. Annotation loading is not best-effort:
    ## a file that fails to load leaves the database in a state nobody inspects,
    ## and the site then shows fewer results with no error anywhere.
    printf '  %s\n' "${files[@]}"
    perl "$SCRIPT_DIR/load_annotations_sqlite.pl" "$DB" "${files[@]}"
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

## The whole-database steps -- annotation_sources_cache.json, the FTS index rebuild
## and VACUUM -- deliberately do NOT run here.
##
## They rewrite the entire organism database, which every gene set of an organism
## shares, so running them per gene set did the same work N times over an ever-growing
## file: Phagocata_velata is 1.8 GB across 3 gene sets, so three full FTS rebuilds and
## three VACUUMs where one of each will do. They now run once, in
## moop_process_genome_data_v2.sbatch, after every gene set has loaded.

echo "Done: $ORG / $GENE_SET_NAME"

## Lock is released automatically when the script exits (fd 200 closes)
