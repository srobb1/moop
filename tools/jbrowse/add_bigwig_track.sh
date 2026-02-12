#!/bin/bash
################################################################################
# Add BigWig Track to JBrowse2
#
# This script processes a BigWig file and creates track metadata for JBrowse2.
# BigWig files are used for quantitative data (RNA-seq, ChIP-seq coverage).
#
# Usage:
#   ./add_bigwig_track.sh <bigwig_file> <organism> <assembly> [options]
#
# Example:
#   ./add_bigwig_track.sh data.bw Anoura_caudifer GCA_004027475.1 \
#       --name "RNA-seq Coverage" \
#       --category "Transcriptomics"
#
# Options:
#   --name         Display name for track
#   --track-id     Unique track identifier (auto-generated if not provided)
#   --category     Track category/group
#   --access       Access level (Public, Collaborator, ALL)
#   --color        Track color (hex code)
#   --description  Track description
#
# Metadata fields (for Google Sheets integration):
#   --technique, --institute, --source, --experiment
#   --developmental-stage, --tissue, --condition
#   --summary, --citation, --project, --accession
#   --date, --analyst
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
ACCESS_LEVEL="Public"
COLOR="#1f77b4"
FORCE=0

# Parse arguments
if [ $# -lt 3 ]; then
    echo "Usage: $0 <bigwig_file> <organism> <assembly> [options]"
    echo ""
    echo "Example:"
    echo "  $0 rna_coverage.bw Anoura_caudifer GCA_004027475.1 \\"
    echo "      --name \"RNA-seq Coverage\" \\"
    echo "      --category \"Transcriptomics\""
    exit 1
fi

BIGWIG_FILE="$1"
ORGANISM="$2"
ASSEMBLY="$3"
shift 3

# Parse optional arguments
TRACK_NAME=""
TRACK_ID=""
CATEGORY=""
DESCRIPTION=""
TECHNIQUE=""
INSTITUTE=""
SOURCE=""
EXPERIMENT=""
DEV_STAGE=""
TISSUE=""
CONDITION=""
SUMMARY=""
CITATION=""
PROJECT=""
ACCESSION=""
DATE=""
ANALYST=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --name) TRACK_NAME="$2"; shift 2 ;;
        --track-id) TRACK_ID="$2"; shift 2 ;;
        --category) CATEGORY="$2"; shift 2 ;;
        --access) ACCESS_LEVEL="$2"; shift 2 ;;
        --color) COLOR="$2"; shift 2 ;;
        --description) DESCRIPTION="$2"; shift 2 ;;
        --technique) TECHNIQUE="$2"; shift 2 ;;
        --institute) INSTITUTE="$2"; shift 2 ;;
        --source) SOURCE="$2"; shift 2 ;;
        --experiment) EXPERIMENT="$2"; shift 2 ;;
        --developmental-stage) DEV_STAGE="$2"; shift 2 ;;
        --tissue) TISSUE="$2"; shift 2 ;;
        --condition) CONDITION="$2"; shift 2 ;;
        --summary) SUMMARY="$2"; shift 2 ;;
        --citation) CITATION="$2"; shift 2 ;;
        --project) PROJECT="$2"; shift 2 ;;
        --accession) ACCESSION="$2"; shift 2 ;;
        --date) DATE="$2"; shift 2 ;;
        --analyst) ANALYST="$2"; shift 2 ;;
        --force) FORCE=1; shift ;;
        *) log_error "Unknown option: $1"; exit 1 ;;
    esac
done

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "    Add BigWig Track to JBrowse2"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Validate input file
if [ ! -f "$BIGWIG_FILE" ]; then
    log_error "BigWig file not found: $BIGWIG_FILE"
    exit 1
fi

# Check file extension
if [[ ! "$BIGWIG_FILE" =~ \.(bw|bigwig)$ ]]; then
    log_warn "File doesn't have .bw or .bigwig extension"
    log_warn "Continuing anyway, but verify this is a BigWig file"
fi

# Generate track ID if not provided
if [ -z "$TRACK_ID" ]; then
    BASENAME=$(basename "$BIGWIG_FILE" | sed 's/\.[^.]*$//')
    TRACK_ID="${ORGANISM}_${ASSEMBLY}_${BASENAME}"
    log_info "Auto-generated track ID: $TRACK_ID"
fi

# Generate track name if not provided
if [ -z "$TRACK_NAME" ]; then
    TRACK_NAME=$(basename "$BIGWIG_FILE" | sed 's/\.[^.]*$//' | sed 's/_/ /g')
    log_info "Auto-generated track name: $TRACK_NAME"
fi

# Set category if not provided
if [ -z "$CATEGORY" ]; then
    CATEGORY="Quantitative"
fi

# Use the original file path directly (no copying)
# For web access, convert absolute path to URI
log_info "Processing BigWig file..."
log_info "  Source: $BIGWIG_FILE"
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"

# Verify source file exists
if [ ! -f "$BIGWIG_FILE" ]; then
    log_error "BigWig file not found: $BIGWIG_FILE"
    exit 1
fi

# Verify it's a valid BigWig file (optional, requires bigWigInfo)
if command -v bigWigInfo &> /dev/null; then
    log_info "Validating BigWig format..."
    if bigWigInfo "$BIGWIG_FILE" > /dev/null 2>&1; then
        log_success "Valid BigWig file"
    else
        log_error "Invalid BigWig file format"
        exit 1
    fi
else
    log_warn "bigWigInfo not found, skipping format validation"
fi

# Determine URI for web access
# If path starts with http:// or https://, use as-is
# Otherwise, convert /data/moop/... to /moop/... for web access
if [[ "$BIGWIG_FILE" =~ ^https?:// ]]; then
    FILE_URI="$BIGWIG_FILE"
    IS_REMOTE=true
else
    # Convert absolute path to web-accessible URI
    FILE_URI="${BIGWIG_FILE#/data}"
    IS_REMOTE=false
fi

log_info "  URI: $FILE_URI"

# Create track metadata JSON with hierarchical structure
TRACK_TYPE="bigwig"
TRACK_DIR="$METADATA_DIR/${ORGANISM}/${ASSEMBLY}/${TRACK_TYPE}"
mkdir -p "$TRACK_DIR"
METADATA_FILE="$TRACK_DIR/${TRACK_ID}.json"

log_info "Creating track metadata: $METADATA_FILE"

# Build metadata JSON
cat > "$METADATA_FILE" << EOF
{
  "trackId": "$TRACK_ID",
  "name": "$TRACK_NAME",
  "assemblyNames": ["${ORGANISM}_${ASSEMBLY}"],
  "category": ["$CATEGORY"],
  "type": "QuantitativeTrack",
  "adapter": {
    "type": "BigWigAdapter",
    "bigWigLocation": {
      "uri": "$FILE_URI",
      "locationType": "UriLocation"
    }
  },
  "displays": [
    {
      "type": "LinearWiggleDisplay",
      "displayId": "${TRACK_ID}-LinearWiggleDisplay",
      "renderer": {
        "type": "XYPlotRenderer",
        "color": "$COLOR"
      }
    }
  ],
  "metadata": {
    "description": "$DESCRIPTION",
    "access_level": "$ACCESS_LEVEL",
    "file_path": "$BIGWIG_FILE",
    "file_size": $(stat -f%z "$BIGWIG_FILE" 2>/dev/null || stat -c%s "$BIGWIG_FILE"),
    "is_remote": $IS_REMOTE,
    "added_date": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "google_sheets_metadata": {
      "technique": "$TECHNIQUE",
      "institute": "$INSTITUTE",
      "source": "$SOURCE",
      "experiment": "$EXPERIMENT",
      "developmental_stage": "$DEV_STAGE",
      "tissue": "$TISSUE",
      "condition": "$CONDITION",
      "summary": "$SUMMARY",
      "citation": "$CITATION",
      "project": "$PROJECT",
      "accession": "$ACCESSION",
      "date": "$DATE",
      "analyst": "$ANALYST"
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
log_success "BigWig track added successfully!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Track Details:"
echo "  Track ID: $TRACK_ID"
echo "  Name: $TRACK_NAME"
echo "  Category: $CATEGORY"
echo "  Access: $ACCESS_LEVEL"
echo "  File: $TARGET_PATH"
echo "  Metadata: $METADATA_FILE"
echo ""
echo "Next steps:"
echo "  1. Track will appear in assembly: ${ORGANISM}_${ASSEMBLY}"
echo "  2. Refresh JBrowse2 page to see the track"
echo "  3. Edit metadata file to add/update Google Sheets data"
echo ""
echo "To add Google Sheets metadata:"
echo "  1. Edit: $METADATA_FILE"
echo "  2. Update the 'google_sheets_metadata' section"
echo "  3. Or use: fetch_metadata_from_sheets.sh (when available)"
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
