#!/bin/bash
################################################################################
# Add BAM Track to JBrowse2
#
# This script processes a BAM alignment file and creates track metadata.
# BAM files show read alignments from sequencing experiments.
#
# Usage:
#   ./add_bam_track.sh <bam_file> <organism> <assembly> [options]
#
# Example:
#   ./add_bam_track.sh alignments.bam Anoura_caudifer GCA_004027475.1 \
#       --name "DNA-seq Alignments" \
#       --category "Alignments"
#
# The script will automatically create a .bai index if missing.
#
# Options:
#   --name         Display name for track
#   --track-id     Unique track identifier (auto-generated if not provided)
#   --category     Track category/group
#   --access       Access level (Public, Collaborator, ALL)
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

# Check for samtools
if ! command -v samtools &> /dev/null; then
    log_error "samtools is required but not installed"
    exit 1
fi

# Parse arguments
if [ $# -lt 3 ]; then
    echo "Usage: $0 <bam_file> <organism> <assembly> [options]"
    echo ""
    echo "Example:"
    echo "  $0 alignments.bam Anoura_caudifer GCA_004027475.1 \\"
    echo "      --name \"DNA-seq Alignments\" \\"
    echo "      --category \"Alignments\""
    exit 1
fi

BAM_FILE="$1"
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
echo "    Add BAM Track to JBrowse2"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Validate input file
if [ ! -f "$BAM_FILE" ]; then
    log_error "BAM file not found: $BAM_FILE"
    exit 1
fi

# Check file extension
if [[ ! "$BAM_FILE" =~ \.bam$ ]]; then
    log_error "File must have .bam extension"
    exit 1
fi

# Generate track ID if not provided
if [ -z "$TRACK_ID" ]; then
    BASENAME=$(basename "$BAM_FILE" .bam)
    TRACK_ID="${ORGANISM}_${ASSEMBLY}_${BASENAME}"
    log_info "Auto-generated track ID: $TRACK_ID"
fi

# Generate track name if not provided
if [ -z "$TRACK_NAME" ]; then
    TRACK_NAME=$(basename "$BAM_FILE" .bam | sed 's/_/ /g')
    log_info "Auto-generated track name: $TRACK_NAME"
fi

# Set category if not provided
if [ -z "$CATEGORY" ]; then
    CATEGORY="Alignments"
fi

# Determine target filename
TARGET_FILENAME="${ORGANISM}_${ASSEMBLY}_$(basename "$BAM_FILE")"
TARGET_PATH="$TRACKS_DIR/bam/$TARGET_FILENAME"
INDEX_PATH="${TARGET_PATH}.bai"

log_info "Processing BAM file..."
log_info "  Source: $BAM_FILE"
log_info "  Target: $TARGET_PATH"
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"

# Create tracks directory if needed
mkdir -p "$TRACKS_DIR/bam"

# Copy BAM file
if [ -f "$TARGET_PATH" ]; then
    log_warn "Target file already exists: $TARGET_PATH"
    read -p "Overwrite? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Aborted"
        exit 1
    fi
fi

log_info "Copying BAM file to tracks directory..."
cp "$BAM_FILE" "$TARGET_PATH"
chmod 644 "$TARGET_PATH"
log_success "BAM file copied"

# Check for BAI index
BAI_SOURCE="${BAM_FILE}.bai"
if [ ! -f "$BAI_SOURCE" ]; then
    # Try alternate location (same dir, .bai extension)
    BAI_SOURCE="${BAM_FILE%.bam}.bai"
fi

if [ -f "$BAI_SOURCE" ]; then
    log_info "Found existing BAI index, copying..."
    cp "$BAI_SOURCE" "$INDEX_PATH"
    chmod 644 "$INDEX_PATH"
    log_success "BAI index copied"
else
    log_warn "BAI index not found, creating..."
    samtools index "$TARGET_PATH"
    log_success "BAI index created: $INDEX_PATH"
fi

# Verify BAM file
log_info "Validating BAM file..."
if samtools quickcheck "$TARGET_PATH" 2>/dev/null; then
    log_success "Valid BAM file"
else
    log_error "Invalid BAM file"
    rm -f "$TARGET_PATH" "$INDEX_PATH"
    exit 1
fi

# Get BAM statistics
log_info "Gathering BAM statistics..."
TOTAL_READS=$(samtools view -c "$TARGET_PATH" 2>/dev/null || echo "unknown")
MAPPED_READS=$(samtools view -c -F 4 "$TARGET_PATH" 2>/dev/null || echo "unknown")

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
  "type": "AlignmentsTrack",
  "adapter": {
    "type": "BamAdapter",
    "bamLocation": {
      "uri": "/moop/data/tracks/bam/$TARGET_FILENAME",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "/moop/data/tracks/bam/${TARGET_FILENAME}.bai",
        "locationType": "UriLocation"
      }
    }
  },
  "displays": [
    {
      "type": "LinearAlignmentsDisplay",
      "displayId": "${TRACK_ID}-LinearAlignmentsDisplay"
    },
    {
      "type": "LinearPileupDisplay",
      "displayId": "${TRACK_ID}-LinearPileupDisplay"
    }
  ],
  "metadata": {
    "description": "$DESCRIPTION",
    "access_level": "$ACCESS_LEVEL",
    "file_path": "$TARGET_PATH",
    "file_size": $(stat -f%z "$TARGET_PATH" 2>/dev/null || stat -c%s "$TARGET_PATH"),
    "total_reads": "$TOTAL_READS",
    "mapped_reads": "$MAPPED_READS",
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
log_success "BAM track added successfully!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Track Details:"
echo "  Track ID: $TRACK_ID"
echo "  Name: $TRACK_NAME"
echo "  Category: $CATEGORY"
echo "  Access: $ACCESS_LEVEL"
echo "  BAM File: $TARGET_PATH"
echo "  BAI Index: $INDEX_PATH"
echo "  Total Reads: $TOTAL_READS"
echo "  Mapped Reads: $MAPPED_READS"
echo "  Metadata: $METADATA_FILE"
echo ""
echo "Next steps:"
echo "  1. Track will appear in assembly: ${ORGANISM}_${ASSEMBLY}"
echo "  2. Refresh JBrowse2 page to see the track"
echo "  3. Edit metadata file to add/update Google Sheets data"
echo ""
