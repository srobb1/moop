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

# Determine target filename
TARGET_FILENAME="${ORGANISM}_${ASSEMBLY}_$(basename "$BIGWIG_FILE")"
TARGET_PATH="$TRACKS_DIR/bigwig/$TARGET_FILENAME"

log_info "Processing BigWig file..."
log_info "  Source: $BIGWIG_FILE"
log_info "  Target: $TARGET_PATH"
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"

# Create tracks directory if needed
mkdir -p "$TRACKS_DIR/bigwig"

# Copy or symlink file
if [ -f "$TARGET_PATH" ]; then
    log_warn "Target file already exists: $TARGET_PATH"
    read -p "Overwrite? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Aborted"
        exit 1
    fi
fi

log_info "Copying BigWig file to tracks directory..."
cp "$BIGWIG_FILE" "$TARGET_PATH"
chmod 644 "$TARGET_PATH"
log_success "File copied"

# Verify it's a valid BigWig file (optional, requires bigWigInfo)
if command -v bigWigInfo &> /dev/null; then
    log_info "Validating BigWig format..."
    if bigWigInfo "$TARGET_PATH" > /dev/null 2>&1; then
        log_success "Valid BigWig file"
    else
        log_error "Invalid BigWig file format"
        rm "$TARGET_PATH"
        exit 1
    fi
else
    log_warn "bigWigInfo not found, skipping format validation"
fi

# Create track metadata JSON
METADATA_FILE="$METADATA_DIR/${TRACK_ID}.json"

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
      "uri": "/moop/data/tracks/bigwig/$TARGET_FILENAME",
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
    "file_path": "$TARGET_PATH",
    "file_size": $(stat -f%z "$TARGET_PATH" 2>/dev/null || stat -c%s "$TARGET_PATH"),
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
