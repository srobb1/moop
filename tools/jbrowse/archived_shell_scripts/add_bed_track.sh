#!/bin/bash
# Add BED Track to JBrowse2
# BED format genomic features (requires tabix index)

set -e

# Source common configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../../config/config.sh"

usage() {
    cat << EOF
Usage: $0 -a ASSEMBLY_NAME -t TRACK_ID -n TRACK_NAME -f BED_FILE [-c CATEGORY] [-l ACCESS_LEVEL]

Add a BED feature track to JBrowse2

Required:
    -a    Assembly name (organism identifier)
    -t    Track ID (unique identifier)
    -n    Track name (display name)
    -f    Path to BED file (must be bgzipped and have .tbi index)

Optional:
    -c    Category (default: "Features")
    -l    Access level (public|ip_range|admin, default: public)
    -r    Remote URL (if file is remote)

Example:
    $0 -a Nvec200 -t peaks_bed -n "ChIP-seq Peaks" -f /path/to/peaks.bed.gz

Notes:
    - BED files must be bgzipped: bgzip file.bed
    - Must have tabix index: tabix -p bed file.bed.gz
    - For unindexed BED, use BedAdapter instead (not recommended for large files)
EOF
    exit 1
}

# Parse arguments
ASSEMBLY=""
TRACK_ID=""
TRACK_NAME=""
BED_FILE=""
CATEGORY="Features"
ACCESS_LEVEL="public"
REMOTE_URL=""

while getopts "a:t:n:f:c:l:r:h" opt; do
    case $opt in
        a) ASSEMBLY="$OPTARG" ;;
        t) TRACK_ID="$OPTARG" ;;
        n) TRACK_NAME="$OPTARG" ;;
        f) BED_FILE="$OPTARG" ;;
        c) CATEGORY="$OPTARG" ;;
        l) ACCESS_LEVEL="$OPTARG" ;;
        r) REMOTE_URL="$OPTARG" ;;
        h) usage ;;
        *) usage ;;
    esac
done

# Validate required arguments
if [[ -z "$ASSEMBLY" ]] || [[ -z "$TRACK_ID" ]] || [[ -z "$TRACK_NAME" ]] || [[ -z "$BED_FILE" ]]; then
    echo "ERROR: Missing required arguments"
    usage
fi

# Check if remote or local
if [[ -n "$REMOTE_URL" ]]; then
    BED_URI="$REMOTE_URL"
    INDEX_URI="${REMOTE_URL}.tbi"
    echo "Using remote BED: $BED_URI"
else
    # Validate local file exists
    if [[ ! -f "$BED_FILE" ]]; then
        echo "ERROR: BED file not found: $BED_FILE"
        exit 1
    fi

    # Check for index
    TBI_FILE="${BED_FILE}.tbi"
    if [[ ! -f "$TBI_FILE" ]]; then
        echo "ERROR: Tabix index (.tbi) not found: $TBI_FILE"
        echo "Create index with: tabix -p bed $BED_FILE"
        exit 1
    fi

    # Check if bgzipped
    if [[ ! "$BED_FILE" =~ \.gz$ ]]; then
        echo "ERROR: BED file must be bgzipped (end with .gz)"
        echo "Compress with: bgzip $BED_FILE"
        exit 1
    fi

    # Setup paths
    JBROWSE_DIR="$JBROWSE_DATA_DIR"
    TRACKS_DIR="$JBROWSE_DIR/tracks/${ASSEMBLY}/bed"
    mkdir -p "$TRACKS_DIR"

    # Copy files
    FILENAME=$(basename "$BED_FILE")
    cp "$BED_FILE" "$TRACKS_DIR/"
    cp "$TBI_FILE" "$TRACKS_DIR/"

    BED_URI="tracks/${ASSEMBLY}/bed/${FILENAME}"
    INDEX_URI="tracks/${ASSEMBLY}/bed/${FILENAME}.tbi"
fi

# Generate track configuration
CONFIG_FILE="$JBROWSE_DIR/config.json"

# Create track JSON
TRACK_JSON=$(cat <<EOF
{
  "type": "FeatureTrack",
  "trackId": "${TRACK_ID}",
  "name": "${TRACK_NAME}",
  "category": ["${CATEGORY}"],
  "assemblyNames": ["${ASSEMBLY}"],
  "adapter": {
    "type": "BedTabixAdapter",
    "bedGzLocation": {
      "uri": "${BED_URI}",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "${INDEX_URI}",
        "locationType": "UriLocation"
      },
      "indexType": "TBI"
    }
  }
}
EOF
)

# Add track to config.json
if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "ERROR: JBrowse config not found: $CONFIG_FILE"
    exit 1
fi

# Check if track already exists
if grep -q "\"trackId\": \"${TRACK_ID}\"" "$CONFIG_FILE"; then
    echo "WARNING: Track ${TRACK_ID} already exists, skipping"
    exit 0
fi

# Insert track into config
python3 - <<PYTHON_SCRIPT
import json
import sys

config_file = "${CONFIG_FILE}"
track_json = '''${TRACK_JSON}'''

try:
    with open(config_file, 'r') as f:
        config = json.load(f)
    
    track = json.loads(track_json)
    
    if 'tracks' not in config:
        config['tracks'] = []
    
    config['tracks'].append(track)
    
    with open(config_file, 'w') as f:
        json.dump(config, f, indent=2)
    
    print(f"✅ Added BED track: ${TRACK_NAME}")
    sys.exit(0)
    
except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PYTHON_SCRIPT

if [[ $? -eq 0 ]]; then
    echo "✅ Successfully added BED track: $TRACK_NAME"
    echo "   Track ID: $TRACK_ID"
    echo "   Assembly: $ASSEMBLY"
    echo "   File: $BED_URI"
else
    echo "❌ Failed to add BED track"
    exit 1
fi
