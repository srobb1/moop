#!/bin/bash
################################################################################
# Add Multi-BigWig Track to JBrowse2
#
# This script creates a single track that displays multiple BigWig files
# together (similar to JBrowse1 MultiBigWig plugin). Useful for comparing
# multiple samples or showing strand-specific data (pos/neg).
#
# Usage:
#   ./add_multi_bigwig_track.sh <organism> <assembly> [options]
#
# Example - Strand-specific RNA-seq:
#   ./add_multi_bigwig_track.sh Nematostella_vectensis GCA_033964005.1 \
#       --name "2hr Wild Type vs Alpha-Amanitin" \
#       --track-id "2hr_amanitin_comparison" \
#       --bigwig "MOLNG-1901.1.pos.bw:2h_wild_type_pos:Maroon" \
#       --bigwig "MOLNG-1901.1.neg.bw:2h_wild_type_neg:Maroon" \
#       --bigwig "MOLNG-1901.2.pos.bw:2h_amanitin_pos:OrangeRed" \
#       --bigwig "MOLNG-1901.2.neg.bw:2h_amanitin_neg:OrangeRed" \
#       --category "RNA-seq/Comparisons" \
#       --access PUBLIC
#
# BigWig Format:
#   --bigwig "filename:display_name:color"
#   
#   Colors can be: hex (#ff0000), named (red), or rgb (rgb(255,0,0))
#
################################################################################

set -e

# Colors for output
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
TRACKS_DIR="$MOOP_ROOT/data/tracks/bigwig"
METADATA_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
ACCESS_LEVEL="PUBLIC"
CATEGORY="Multi-BigWig"

# Parse arguments
if [ $# -lt 2 ]; then
    cat << 'EOF'
Usage: $0 <organism> <assembly> [options]

Options:
  --name <name>              Track display name (required)
  --track-id <id>            Unique track identifier (auto-generated if not provided)
  --bigwig <file:name:color> Add a BigWig file (can specify multiple)
  --category <category>      Track category (default: "Multi-BigWig")
  --access <level>           Access level (PUBLIC, COLLABORATOR, ADMIN)
  --description <text>       Track description
  --autoscale <type>         Autoscale type: local, global, or globalsd (default: local)

BigWig Format:
  filename:display_name:color
  
  Examples:
    --bigwig "file.pos.bw:Positive Strand:#ff0000"
    --bigwig "file.neg.bw:Negative Strand:#0000ff"

Colors:
  - Hex: #ff0000, #00ff00, #0000ff
  - Named: red, blue, green, orange, purple, maroon, OrangeRed, etc.
  - RGB: rgb(255,0,0), rgb(0,255,0)

Example - Strand-specific comparison:
  $0 Nematostella_vectensis GCA_033964005.1 \
    --name "WT vs Treatment (2hr)" \
    --bigwig "wt.pos.bw:WT (+):#8b0000" \
    --bigwig "wt.neg.bw:WT (-):#8b0000" \
    --bigwig "treat.pos.bw:Treatment (+):#ff4500" \
    --bigwig "treat.neg.bw:Treatment (-):#ff4500" \
    --category "RNA-seq/Comparisons"

EOF
    exit 1
fi

ORGANISM="$1"
ASSEMBLY="$2"
shift 2

# Parse optional arguments
TRACK_NAME=""
TRACK_ID=""
DESCRIPTION=""
AUTOSCALE="local"
declare -a BIGWIG_FILES=()
declare -a BIGWIG_NAMES=()
declare -a BIGWIG_COLORS=()

while [[ $# -gt 0 ]]; do
    case $1 in
        --name) TRACK_NAME="$2"; shift 2 ;;
        --track-id) TRACK_ID="$2"; shift 2 ;;
        --category) CATEGORY="$2"; shift 2 ;;
        --access) ACCESS_LEVEL="$2"; shift 2 ;;
        --description) DESCRIPTION="$2"; shift 2 ;;
        --autoscale) AUTOSCALE="$2"; shift 2 ;;
        --bigwig)
            IFS=':' read -r file name color <<< "$2"
            BIGWIG_FILES+=("$file")
            BIGWIG_NAMES+=("$name")
            BIGWIG_COLORS+=("$color")
            shift 2
            ;;
        *) log_error "Unknown option: $1"; exit 1 ;;
    esac
done

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "    Add Multi-BigWig Track to JBrowse2"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Validation
if [ -z "$TRACK_NAME" ]; then
    log_error "Track name is required (--name)"
    exit 1
fi

if [ ${#BIGWIG_FILES[@]} -eq 0 ]; then
    log_error "At least one BigWig file is required (--bigwig)"
    exit 1
fi

if [ ${#BIGWIG_FILES[@]} -lt 2 ]; then
    log_warn "Only one BigWig file specified. Consider using add_bigwig_track.sh instead."
fi

# Generate track ID if not provided
if [ -z "$TRACK_ID" ]; then
    TRACK_ID="${ORGANISM}_${ASSEMBLY}_multi_$(echo "$TRACK_NAME" | tr ' ' '_' | tr '[:upper:]' '[:lower:]')"
    log_info "Auto-generated track ID: $TRACK_ID"
fi

log_info "Processing Multi-BigWig track..."
log_info "  Organism: $ORGANISM"
log_info "  Assembly: $ASSEMBLY"
log_info "  Track ID: $TRACK_ID"
log_info "  Name: $TRACK_NAME"
log_info "  Access: $ACCESS_LEVEL"
log_info "  BigWig files: ${#BIGWIG_FILES[@]}"

# Validate all BigWig files exist
for i in "${!BIGWIG_FILES[@]}"; do
    file="${BIGWIG_FILES[$i]}"
    name="${BIGWIG_NAMES[$i]}"
    color="${BIGWIG_COLORS[$i]}"
    
    # Check if file is absolute path or relative to tracks dir
    if [[ "$file" == /* ]]; then
        # Absolute path
        if [ ! -f "$file" ]; then
            log_error "BigWig file not found: $file"
            exit 1
        fi
        FILE_PATH="$file"
    else
        # Relative to tracks dir
        FILE_PATH="$TRACKS_DIR/$file"
        if [ ! -f "$FILE_PATH" ]; then
            log_error "BigWig file not found: $FILE_PATH"
            log_error "Provide absolute path or place file in: $TRACKS_DIR"
            exit 1
        fi
    fi
    
    log_info "  [$((i+1))] $name"
    log_info "      File: $(basename "$FILE_PATH")"
    log_info "      Color: $color"
done

# Build subtrack configurations JSON array
SUBTRACKS_JSON="["
for i in "${!BIGWIG_FILES[@]}"; do
    file="${BIGWIG_FILES[$i]}"
    name="${BIGWIG_NAMES[$i]}"
    color="${BIGWIG_COLORS[$i]}"
    
    # Get just filename if absolute path
    if [[ "$file" == /* ]]; then
        filename=$(basename "$file")
    else
        filename="$file"
    fi
    
    # Add comma for all but first
    if [ $i -gt 0 ]; then
        SUBTRACKS_JSON+=","
    fi
    
    SUBTRACKS_JSON+=$(cat << EOF

    {
      "trackId": "${TRACK_ID}_sub${i}",
      "name": "$name",
      "assemblyNames": ["${ORGANISM}_${ASSEMBLY}"],
      "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
          "uri": "/moop/data/tracks/bigwig/$filename",
          "locationType": "UriLocation"
        }
      },
      "displays": [
        {
          "type": "LinearWiggleDisplay",
          "displayId": "${TRACK_ID}_sub${i}-LinearWiggleDisplay",
          "defaultRendering": "xyplot",
          "renderer": {
            "type": "XYPlotRenderer",
            "color": "$color"
          }
        }
      ]
    }
EOF
)
done
SUBTRACKS_JSON+="]"

# Create track metadata JSON with hierarchical structure
TRACK_TYPE="combo"
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
  "type": "MultiQuantitativeTrack",
  "adapter": {
    "type": "MultiWiggleAdapter",
    "subadapters": $SUBTRACKS_JSON
  },
  "displays": [
    {
      "type": "MultiLinearWiggleDisplay",
      "displayId": "${TRACK_ID}-MultiLinearWiggleDisplay",
      "autoscale": "$AUTOSCALE"
    }
  ],
  "metadata": {
    "description": "$DESCRIPTION",
    "access_level": "$ACCESS_LEVEL",
    "num_files": ${#BIGWIG_FILES[@]},
    "added_date": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
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
        cat "$METADATA_FILE"
        exit 1
    fi
else
    log_warn "jq not found, skipping JSON validation"
fi

echo ""
echo "════════════════════════════════════════════════════════════════"
log_success "Multi-BigWig track added successfully!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Track Details:"
echo "  Track ID: $TRACK_ID"
echo "  Name: $TRACK_NAME"
echo "  Category: $CATEGORY"
echo "  Access: $ACCESS_LEVEL"
echo "  BigWig Files: ${#BIGWIG_FILES[@]}"
for i in "${!BIGWIG_FILES[@]}"; do
    echo "    - ${BIGWIG_NAMES[$i]}"
done
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
