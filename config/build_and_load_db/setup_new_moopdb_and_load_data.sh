REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPT_DIR=$REPO/data_loaders
ORG=$1
GENE_SET_NAME=${2:-primary}
ORG_DATA_DIR=${3:-.}   # organism-level output dir; defaults to cwd for backward compat

DB="$ORG_DATA_DIR/organism.sqlite"
LOCKFILE="$ORG_DATA_DIR/.organism.lock"

## Acquire an exclusive lock on the organism dir so parallel array tasks for the
## same organism don't race on DB creation, annotation loading, or cache generation.
exec 200>"$LOCKFILE"
flock -x 200

if [ ! -e "$DB" ]; then
  sqlite3 "$DB" < $SCRIPT_DIR/create_schema_sqlite.sql
fi

perl $SCRIPT_DIR/import_genes_sqlite.pl "$DB" features.tsv $GENE_SET_NAME

echo "Loading Annotations for: $ORG"

## load annotations

# Enable nullglob so empty globs expand to nothing instead of literal pattern
shopt -s nullglob

# Function to load annotation files
load_files() {
    local pattern="$1"
    local description="$2"

    echo "Loading $description"
    local files=(./$pattern)

    if [ ${#files[@]} -eq 0 ]; then
        echo "  Warning: No files found matching $pattern"
        return
    fi

    for file in "${files[@]}"; do
        echo "  $file"
        perl "$SCRIPT_DIR/load_annotations_fast.pl" "$DB" "$file"
    done
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

## Lock is released automatically when the script exits (fd 200 closes)
