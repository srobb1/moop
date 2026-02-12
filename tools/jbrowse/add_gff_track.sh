#!/bin/bash
################################################################################
# Add GFF Track to JBrowse2
#
# This script processes a GFF3 file and creates track metadata for JBrowse2.
# Unlike the automatic annotation track, this allows you to add multiple
# GFF tracks with different access levels and names.
#
# Usage:
#   ./add_gff_track.sh <gff_file> <organism> <assembly> [options]
#
# Example:
#   ./add_gff_track.sh genes.gff3 Nematostella_vectensis GCA_033964005.1 \
#       --name "Gene Models v2" \
#       --access ADMIN \
#       --category "Annotation"
#
# Features:
#   - Sorts and tidies GFF with genometools (gt gff3)
#   - Compresses with bgzip
#   - Indexes with tabix
#   - Creates text search index with JBrowse CLI (if available)
#   - Supports multiple GFF tracks per assembly
#   - Access control (PUBLIC, COLLABORATOR, ADMIN)
#
# Options:
#   --name         Display name for track
#   --track-id     Unique track identifier (auto-generated if not provided)
#   --category     Track category/group (default: "Annotation")
#   --access       Access level (PUBLIC, COLLABORATOR, ADMIN)
#   --description  Track description
#   --skip-sort    Skip sorting with genometools (if already sorted)
#   --skip-index   Skip text search indexing
#
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_warn() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error() { echo -e "${RED}✗${NC} $1" >&2; }

# Default values
MOOP_ROOT="/data/moop"
TRACKS_DIR="$MOOP_ROOT/data/tracks"
METADATA_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
ACCESS_LEVEL="PUBLIC"
CATEGORY="Annotation"
SKIP_SORT=false
SKIP_TEXT_INDEX=false

# Check for required tools
if ! command -v bgzip &> /dev/null; then
    log_error "bgzip is required but not installed"
    log_error "Install with: sudo apt-get install tabix"
    exit 1
fi

if ! command -v tabix &> /dev/null; then
    log_error "tabix is required but not installed"
    log_error "Install with: sudo apt-get install tabix"
    exit 1
fi

# Parse arguments
if [ $# -lt 3 ]; then
    echo "Usage: $0 <gff_file> <organism> <assembly> [options]"
    echo ""
    echo "Example:"
    echo "  $0 genes.gff3 Nematostella_vectensis GCA_033964005.1 \\"
    echo "      --name \"Gene Models v2\" \\"
    echo "      --access ADMIN"
    exit 1
fi

GFF_FILE="$1"
ORGANISM="$2"
ASSEMBLY="$3"
shift 3

# Parse optional arguments
TRACK_NAME=""
TRACK_ID=""
DESCRIPTION=""
TECHNIQUE=""
INSTITUTE=""
SOURCE=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --name) TRACK_NAME="$2"; shift 2 ;;
        --track-id) TRACK_ID="$2"; shift 2 ;;
        --category) CATEGORY="$2"; shift 2 ;;
        --access) ACCESS_LEVEL="$2"; shift 2 ;;
        --description) DESCRIPTION="$2"; shift 2 ;;
        --skip-sort) SKIP_SORT=true; shift ;;
        --skip-index) SKIP_TEXT_INDEX=true; shift ;;
        --technique) TECHNIQUE="$2"; shift 2 ;;
        --institute) INSTITUTE="$2"; shift 2 ;;
        --source) SOURCE="$2"; shift 2 ;;
        *) log_error "Unknown option: $1"; exit 1 ;;
    esac
done

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "    Add GFF Track to JBrowse2"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Validate input file
if [ ! -f "$GFF_FILE" ]; then
    log_error "GFF file not found: $GFF_FILE"
    exit 1
fi

# Check file extension
if [[ ! "$GFF_FILE" =~ \.(gff|gff3)$ ]] && [[ ! "$GFF_FILE" =~ \.(gff|gff3)\.gz$ ]]; then
    log_warn "File doesn't have .gff or .gff3 extension"
    log_warn "Continuing anyway, but verify this is a GFF3 file"
fi

# Generate track ID if not provided
if [ -z "$TRACK_ID" ]; then
    BASENAME=$(basename "$GFF_FILE" | sed 's/\.[^.]*$//' | sed 's/\.[^.]*$//')
    TRACK_ID="${ORGANISM}_${ASSEMBLY}_${BASENAME}"
    log_info "Auto-generated track ID: $TRACK_ID"
fi

# Generate track name if not provided
if [ -z "$TRACK_NAME" ]; then
    TRACK_NAME=$(basename "$GFF_FILE" | sed 's/\.[^.]*$//' | sed 's/\.[^.]*$//' | sed 's/_/ /g')
    log_info "Auto-generated track name: $TRACK_NAME"
fi

# Determine target filename
TARGET_FILENAME="${TRACK_ID}.sorted.gff3.gz"
TARGET_PATH="$TRACKS_DIR/gff/$TARGET_FILENAME"
INDEX_PATH="${TARGET_PATH}.tbi"

log_info "Processing GFF file..."
log_info "  Source: $GFF_FILE"
log_info "  Target: $TARGET_PATH"
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"
log_info "  Access: $ACCESS_LEVEL"

# Create tracks directory if needed
mkdir -p "$TRACKS_DIR/gff"

# Process GFF file
TEMP_GFF="/tmp/${TRACK_ID}.sorted.gff3"

if [ "$SKIP_SORT" = true ]; then
    log_info "Skipping sort (--skip-sort specified)"
    if [[ "$GFF_FILE" =~ \.gz$ ]]; then
        zcat "$GFF_FILE" > "$TEMP_GFF"
    else
        cp "$GFF_FILE" "$TEMP_GFF"
    fi
else
    # Check for genometools
    if command -v gt &> /dev/null; then
        log_info "Sorting and tidying GFF with genometools..."
        if [[ "$GFF_FILE" =~ \.gz$ ]]; then
            zcat "$GFF_FILE" | gt gff3 -sortlines -tidy -retainids - > "$TEMP_GFF"
        else
            gt gff3 -sortlines -tidy -retainids "$GFF_FILE" > "$TEMP_GFF"
        fi
        log_success "GFF sorted and tidied"
    else
        log_warn "genometools (gt) not found - copying without sort"
        log_warn "Install with: sudo apt-get install genometools"
        if [[ "$GFF_FILE" =~ \.gz$ ]]; then
            zcat "$GFF_FILE" > "$TEMP_GFF"
        else
            cp "$GFF_FILE" "$TEMP_GFF"
        fi
    fi
fi

# Compress with bgzip
log_info "Compressing with bgzip..."
bgzip -f "$TEMP_GFF"
TEMP_GFF_GZ="${TEMP_GFF}.gz"

# Move to final location
if [ -f "$TARGET_PATH" ]; then
    log_warn "Target file already exists: $TARGET_PATH"
    read -p "Overwrite? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Aborted"
        rm "$TEMP_GFF_GZ"
        exit 1
    fi
fi

mv "$TEMP_GFF_GZ" "$TARGET_PATH"
chmod 644 "$TARGET_PATH"
log_success "GFF file compressed and moved"

# Index with tabix
log_info "Creating tabix index..."
tabix -p gff "$TARGET_PATH"
log_success "Tabix index created: $INDEX_PATH"

# Create text search index with JBrowse CLI (if available)
if [ "$SKIP_TEXT_INDEX" = false ] && command -v jbrowse &> /dev/null; then
    log_info "Creating text search index with JBrowse CLI..."
    
    # Create temporary JBrowse config for text indexing
    TEMP_JBROWSE_DIR="/tmp/jbrowse_text_index_${TRACK_ID}"
    mkdir -p "$TEMP_JBROWSE_DIR"
    
    # Minimal config.json
    cat > "$TEMP_JBROWSE_DIR/config.json" << EOF
{
  "assemblies": [],
  "tracks": []
}
EOF
    
    # Add track using JBrowse CLI
    jbrowse add-track "$TARGET_PATH" \
        --assemblyNames "${ORGANISM}_${ASSEMBLY}" \
        --trackId "$TRACK_ID" \
        --name "$TRACK_NAME" \
        --load inPlace \
        --target "$TEMP_JBROWSE_DIR/config.json" 2>&1 || log_warn "Failed to add track for indexing"
    
    # Create text index
    TRIX_DIR="$TRACKS_DIR/trix"
    mkdir -p "$TRIX_DIR"
    
    jbrowse text-index \
        --out "$TEMP_JBROWSE_DIR" \
        --perTrack \
        --tracks="$TRACK_ID" 2>&1 || log_warn "Text indexing failed"
    
    # Move trix files to tracks directory
    if [ -d "$TEMP_JBROWSE_DIR/trix" ]; then
        mv "$TEMP_JBROWSE_DIR/trix/${TRACK_ID}"* "$TRIX_DIR/" 2>/dev/null || true
        log_success "Text search index created"
    else
        log_warn "Text indexing did not produce expected files"
    fi
    
    # Clean up
    rm -rf "$TEMP_JBROWSE_DIR"
elif [ "$SKIP_TEXT_INDEX" = false ]; then
    log_warn "JBrowse CLI not found - skipping text search index"
    log_warn "Install with: npm install -g @jbrowse/cli"
fi

# Create track metadata JSON
METADATA_FILE="$METADATA_DIR/${TRACK_ID}.json"

log_info "Creating track metadata: $METADATA_FILE"

# Check if text index files exist
TEXT_SEARCH_CONFIG=""
if [ -f "$TRACKS_DIR/trix/${TRACK_ID}.ix" ]; then
    TEXT_SEARCH_CONFIG=$(cat << EOF
,
  "textSearching": {
    "textSearchAdapter": {
      "type": "TrixTextSearchAdapter",
      "textSearchAdapterId": "${TRACK_ID}-index",
      "ixFilePath": {
        "uri": "/moop/data/tracks/trix/${TRACK_ID}.ix",
        "locationType": "UriLocation"
      },
      "ixxFilePath": {
        "uri": "/moop/data/tracks/trix/${TRACK_ID}.ixx",
        "locationType": "UriLocation"
      },
      "metaFilePath": {
        "uri": "/moop/data/tracks/trix/${TRACK_ID}_meta.json",
        "locationType": "UriLocation"
      },
      "assemblyNames": ["${ORGANISM}_${ASSEMBLY}"]
    }
  }
EOF
)
fi

# Build metadata JSON
cat > "$METADATA_FILE" << EOF
{
  "trackId": "$TRACK_ID",
  "name": "$TRACK_NAME",
  "assemblyNames": ["${ORGANISM}_${ASSEMBLY}"],
  "category": ["$CATEGORY"],
  "type": "FeatureTrack",
  "adapter": {
    "type": "Gff3TabixAdapter",
    "gffGzLocation": {
      "uri": "/moop/data/tracks/gff/$TARGET_FILENAME",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "/moop/data/tracks/gff/${TARGET_FILENAME}.tbi",
        "locationType": "UriLocation"
      },
      "indexType": "TBI"
    }
  }${TEXT_SEARCH_CONFIG},
  "metadata": {
    "description": "$DESCRIPTION",
    "access_level": "$ACCESS_LEVEL",
    "file_path": "$TARGET_PATH",
    "file_size": $(stat -f%z "$TARGET_PATH" 2>/dev/null || stat -c%s "$TARGET_PATH"),
    "added_date": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "google_sheets_metadata": {
      "technique": "$TECHNIQUE",
      "institute": "$INSTITUTE",
      "source": "$SOURCE"
    }
  }
}
EOF

log_success "Track metadata created"

# Validate JSON
if command -v jq &> /dev/null; then
    log_info "Validating JSON syntax..."
    if jq empty "$METADATA_FILE" 2>/dev/null; then
        log_success "Valid JSON"
    else
        log_error "Invalid JSON in metadata file"
        exit 1
    fi
else
    log_warn "jq not found, skipping JSON validation"
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
log_success "GFF track added successfully!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Track Details:"
echo "  Track ID: $TRACK_ID"
echo "  Name: $TRACK_NAME"
echo "  Category: $CATEGORY"
echo "  Access: $ACCESS_LEVEL"
echo "  GFF File: $TARGET_PATH"
echo "  TBI Index: $INDEX_PATH"
if [ -f "$TRACKS_DIR/trix/${TRACK_ID}.ix" ]; then
echo "  Text Index: $TRACKS_DIR/trix/${TRACK_ID}.ix"
fi
echo "  Metadata: $METADATA_FILE"
echo ""
echo "Next steps:"
echo "  1. Track will appear in assembly: ${ORGANISM}_${ASSEMBLY}"
echo "  2. Regenerate configs: php tools/jbrowse/generate-jbrowse-configs.php"
echo "  3. Refresh JBrowse2 page to see the track"
echo ""

# Regenerate JBrowse2 config
log_info "Regenerating JBrowse2 configs..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if php "$SCRIPT_DIR/generate-jbrowse-configs.php" > /dev/null 2>&1; then
    log_success "Configs regenerated - track is now visible"
else
    log_warn "Could not regenerate configs automatically"
    log_warn "Run manually: php $SCRIPT_DIR/generate-jbrowse-configs.php"
fi
echo ""
