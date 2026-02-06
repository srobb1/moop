#!/bin/bash
################################################################################
# Add VCF Track to JBrowse2
#
# This script processes a VCF file and creates track metadata for JBrowse2.
# VCF files contain variant information (SNPs, indels, structural variants).
#
# Usage:
#   ./add_vcf_track.sh <vcf_file> <organism> <assembly> [options]
#
# Example:
#   ./add_vcf_track.sh variants.vcf.gz Anoura_caudifer GCA_004027475.1 \
#       --name "SNPs and Indels" \
#       --category "Variants"
#
# The script requires VCF to be bgzip compressed (.vcf.gz).
# It will automatically create a .tbi index if missing.
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
    echo "Usage: $0 <vcf_file> <organism> <assembly> [options]"
    echo ""
    echo "Note: VCF must be bgzip compressed (.vcf.gz)"
    echo ""
    echo "Example:"
    echo "  $0 variants.vcf.gz Anoura_caudifer GCA_004027475.1 \\"
    echo "      --name \"SNPs and Indels\" \\"
    echo "      --category \"Variants\""
    exit 1
fi

VCF_FILE="$1"
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
echo "    Add VCF Track to JBrowse2"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Validate input file
if [ ! -f "$VCF_FILE" ]; then
    log_error "VCF file not found: $VCF_FILE"
    exit 1
fi

# Check if file is compressed
if [[ ! "$VCF_FILE" =~ \.vcf\.gz$ ]]; then
    if [[ "$VCF_FILE" =~ \.vcf$ ]]; then
        log_info "Uncompressed VCF detected, compressing..."
        COMPRESSED_VCF="${VCF_FILE}.gz"
        bgzip -c "$VCF_FILE" > "$COMPRESSED_VCF"
        VCF_FILE="$COMPRESSED_VCF"
        log_success "VCF compressed"
    else
        log_error "File must be a .vcf or .vcf.gz file"
        exit 1
    fi
fi

# Generate track ID if not provided
if [ -z "$TRACK_ID" ]; then
    BASENAME=$(basename "$VCF_FILE" .vcf.gz)
    TRACK_ID="${ORGANISM}_${ASSEMBLY}_${BASENAME}"
    log_info "Auto-generated track ID: $TRACK_ID"
fi

# Generate track name if not provided
if [ -z "$TRACK_NAME" ]; then
    TRACK_NAME=$(basename "$VCF_FILE" .vcf.gz | sed 's/_/ /g')
    log_info "Auto-generated track name: $TRACK_NAME"
fi

# Set category if not provided
if [ -z "$CATEGORY" ]; then
    CATEGORY="Variants"
fi

# Determine target filename
TARGET_FILENAME="${ORGANISM}_${ASSEMBLY}_$(basename "$VCF_FILE")"
TARGET_PATH="$TRACKS_DIR/vcf/$TARGET_FILENAME"
INDEX_PATH="${TARGET_PATH}.tbi"

log_info "Processing VCF file..."
log_info "  Source: $VCF_FILE"
log_info "  Target: $TARGET_PATH"
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"

# Create tracks directory if needed
mkdir -p "$TRACKS_DIR/vcf"

# Copy VCF file
if [ -f "$TARGET_PATH" ]; then
    log_warn "Target file already exists: $TARGET_PATH"
    read -p "Overwrite? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Aborted"
        exit 1
    fi
fi

log_info "Copying VCF file to tracks directory..."
cp "$VCF_FILE" "$TARGET_PATH"
chmod 644 "$TARGET_PATH"
log_success "VCF file copied"

# Check for tabix index
TBI_SOURCE="${VCF_FILE}.tbi"
if [ -f "$TBI_SOURCE" ]; then
    log_info "Found existing tabix index, copying..."
    cp "$TBI_SOURCE" "$INDEX_PATH"
    chmod 644 "$INDEX_PATH"
    log_success "Tabix index copied"
else
    log_warn "Tabix index not found, creating..."
    tabix -p vcf "$TARGET_PATH"
    log_success "Tabix index created: $INDEX_PATH"
fi

# Get VCF statistics
log_info "Gathering VCF statistics..."
VARIANT_COUNT=$(zcat "$TARGET_PATH" | grep -v "^#" | wc -l | tr -d ' ')
SAMPLE_COUNT=$(zcat "$TARGET_PATH" | grep "^#CHROM" | head -1 | awk '{print NF-9}')

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
  "type": "VariantTrack",
  "adapter": {
    "type": "VcfTabixAdapter",
    "vcfGzLocation": {
      "uri": "/moop/data/tracks/vcf/$TARGET_FILENAME",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "/moop/data/tracks/vcf/${TARGET_FILENAME}.tbi",
        "locationType": "UriLocation"
      }
    }
  },
  "displays": [
    {
      "type": "LinearVariantDisplay",
      "displayId": "${TRACK_ID}-LinearVariantDisplay",
      "renderer": {
        "type": "SvgFeatureRenderer"
      }
    }
  ],
  "metadata": {
    "description": "$DESCRIPTION",
    "access_level": "$ACCESS_LEVEL",
    "file_path": "$TARGET_PATH",
    "file_size": $(stat -f%z "$TARGET_PATH" 2>/dev/null || stat -c%s "$TARGET_PATH"),
    "variant_count": "$VARIANT_COUNT",
    "sample_count": "$SAMPLE_COUNT",
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
log_success "VCF track added successfully!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Track Details:"
echo "  Track ID: $TRACK_ID"
echo "  Name: $TRACK_NAME"
echo "  Category: $CATEGORY"
echo "  Access: $ACCESS_LEVEL"
echo "  VCF File: $TARGET_PATH"
echo "  TBI Index: $INDEX_PATH"
echo "  Variants: $VARIANT_COUNT"
echo "  Samples: $SAMPLE_COUNT"
echo "  Metadata: $METADATA_FILE"
echo ""
echo "Next steps:"
echo "  1. Track will appear in assembly: ${ORGANISM}_${ASSEMBLY}"
echo "  2. Refresh JBrowse2 page to see the track"
echo "  3. Edit metadata file to add/update Google Sheets data"
echo ""
