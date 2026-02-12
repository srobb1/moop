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
#   --force        Overwrite existing track without prompting
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
FORCE=0

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
CUSTOM_METADATA_JSON=""
SKIP_STATS=0

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
        --custom-metadata) CUSTOM_METADATA_JSON="$2"; shift 2 ;;
        --skip-stats) SKIP_STATS=1; shift ;;
        --force) FORCE=1; shift ;;
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

# Use the original file path directly (no copying)
# For web access, convert absolute path to URI
log_info "Processing BAM file..."
log_info "  Source: $BAM_FILE"
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"

# Verify source file exists
if [ ! -f "$BAM_FILE" ]; then
    log_error "BAM file not found: $BAM_FILE"
    exit 1
fi

# Verify BAM file
log_info "Validating BAM file..."
if samtools quickcheck "$BAM_FILE" 2>/dev/null; then
    log_success "Valid BAM file"
else
    log_error "Invalid BAM file"
    exit 1
fi

# Check for BAI index
BAI_SOURCE="${BAM_FILE}.bai"
if [ ! -f "$BAI_SOURCE" ]; then
    # Try alternate location (same dir, .bai extension)
    BAI_SOURCE="${BAM_FILE%.bam}.bai"
fi

if [ ! -f "$BAI_SOURCE" ]; then
    log_error "BAI index not found. Please create index with: samtools index $BAM_FILE"
    exit 1
fi

log_success "BAI index found: $BAI_SOURCE"

# Determine URIs for web access
# If path starts with http:// or https://, use as-is
# Otherwise, convert /data/moop/... to /moop/... for web access
if [[ "$BAM_FILE" =~ ^https?:// ]]; then
    FILE_URI="$BAM_FILE"
    INDEX_URI="${BAM_FILE}.bai"
    IS_REMOTE=true
else
    # Convert absolute paths to web-accessible URIs
    FILE_URI="${BAM_FILE#/data}"
    INDEX_URI="${BAI_SOURCE#/data}"
    IS_REMOTE=false
fi

log_info "  BAM URI: $FILE_URI"
log_info "  BAI URI: $INDEX_URI"

# Get BAM statistics (optional - can be slow for large files)
if [ $SKIP_STATS -eq 0 ]; then
    log_info "Gathering BAM statistics..."
    TOTAL_READS=$(samtools view -c "$BAM_FILE" 2>/dev/null || echo "unknown")
    MAPPED_READS=$(samtools view -c -F 4 "$BAM_FILE" 2>/dev/null || echo "unknown")
else
    log_info "Skipping BAM statistics (--skip-stats enabled)"
    TOTAL_READS="not_calculated"
    MAPPED_READS="not_calculated"
fi

# Create track metadata JSON with hierarchical structure
TRACK_TYPE="bam"
TRACK_DIR="$METADATA_DIR/${ORGANISM}/${ASSEMBLY}/${TRACK_TYPE}"
mkdir -p "$TRACK_DIR"
METADATA_FILE="$TRACK_DIR/${TRACK_ID}.json"

log_info "Creating track metadata: $METADATA_FILE"

# Build metadata JSON
# If custom metadata JSON exists, merge it in
if [ -n "$CUSTOM_METADATA_JSON" ] && command -v jq &> /dev/null; then
    CUSTOM_FIELDS=$(echo "$CUSTOM_METADATA_JSON" | jq -c '.')
else
    CUSTOM_FIELDS="{}"
fi

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
      "uri": "$FILE_URI",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "$INDEX_URI",
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
    "file_path": "$BAM_FILE",
    "file_size": $(stat -f%z "$BAM_FILE" 2>/dev/null || stat -c%s "$BAM_FILE"),
    "total_reads": "$TOTAL_READS",
    "mapped_reads": "$MAPPED_READS",
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
    },
    "custom_fields": $CUSTOM_FIELDS
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
