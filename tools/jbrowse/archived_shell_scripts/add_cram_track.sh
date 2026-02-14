#!/bin/bash
# Add CRAM Track to JBrowse2
# CRAM is a more space-efficient alternative to BAM
# Requires: .cram file and .crai index

set -e

# Source common configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../../config/config.sh"

usage() {
    cat << EOF
Usage: $0 -a ASSEMBLY_NAME -t TRACK_ID -n TRACK_NAME -f CRAM_FILE [-c CATEGORY] [-l ACCESS_LEVEL]

Add a CRAM alignment track to JBrowse2

Required:
    -a    Assembly name (organism identifier)
    -t    Track ID (unique identifier)
    -n    Track name (display name)
    -f    Path to CRAM file (must have .crai index)

Optional:
    -c    Category (default: "Aligned Reads")
    -l    Access level (public|ip_range|admin, default: public)
    -r    Remote URL (if file is remote)

Example:
    $0 -a Nvec200 -t sample1_cram -n "Sample 1 Alignments" -f /path/to/sample1.cram

Notes:
    - CRAM files require a .crai index file in the same directory
    - CRAM is more space-efficient than BAM (30-60% smaller)
    - Requires reference genome to decompress
EOF
    exit 1
}

# Parse arguments
ASSEMBLY=""
TRACK_ID=""
TRACK_NAME=""
CRAM_FILE=""
CATEGORY="Aligned Reads"
ACCESS_LEVEL="public"
REMOTE_URL=""

while getopts "a:t:n:f:c:l:r:h" opt; do
    case $opt in
        a) ASSEMBLY="$OPTARG" ;;
        t) TRACK_ID="$OPTARG" ;;
        n) TRACK_NAME="$OPTARG" ;;
        f) CRAM_FILE="$OPTARG" ;;
        c) CATEGORY="$OPTARG" ;;
        l) ACCESS_LEVEL="$OPTARG" ;;
        r) REMOTE_URL="$OPTARG" ;;
        h) usage ;;
        *) usage ;;
    esac
done

# Validate required arguments
if [[ -z "$ASSEMBLY" ]] || [[ -z "$TRACK_ID" ]] || [[ -z "$TRACK_NAME" ]] || [[ -z "$CRAM_FILE" ]]; then
    echo "ERROR: Missing required arguments"
    usage
fi

# Check if remote or local
if [[ -n "$REMOTE_URL" ]]; then
    CRAM_URI="$REMOTE_URL"
    INDEX_URI="${REMOTE_URL}.crai"
    echo "Using remote CRAM: $CRAM_URI"
else
    # Validate local file exists
    if [[ ! -f "$CRAM_FILE" ]]; then
        echo "ERROR: CRAM file not found: $CRAM_FILE"
        exit 1
    fi

    # Check for index
    CRAI_FILE="${CRAM_FILE}.crai"
    if [[ ! -f "$CRAI_FILE" ]]; then
        echo "ERROR: CRAM index (.crai) not found: $CRAI_FILE"
        echo "Create index with: samtools index $CRAM_FILE"
        exit 1
    fi

    # Setup paths
    JBROWSE_DIR="$JBROWSE_DATA_DIR"
    TRACKS_DIR="$JBROWSE_DIR/tracks/${ASSEMBLY}/cram"
    mkdir -p "$TRACKS_DIR"

    # Copy files
    FILENAME=$(basename "$CRAM_FILE")
    cp "$CRAM_FILE" "$TRACKS_DIR/"
    cp "$CRAI_FILE" "$TRACKS_DIR/"

    CRAM_URI="tracks/${ASSEMBLY}/cram/${FILENAME}"
    INDEX_URI="tracks/${ASSEMBLY}/cram/${FILENAME}.crai"
fi

# Generate track configuration
CONFIG_FILE="$JBROWSE_DIR/config.json"
TEMP_CONFIG="${CONFIG_FILE}.tmp"

# Create track JSON
TRACK_JSON=$(cat <<EOF
{
  "type": "AlignmentsTrack",
  "trackId": "${TRACK_ID}",
  "name": "${TRACK_NAME}",
  "category": ["${CATEGORY}"],
  "assemblyNames": ["${ASSEMBLY}"],
  "adapter": {
    "type": "CramAdapter",
    "cramLocation": {
      "uri": "${CRAM_URI}",
      "locationType": "UriLocation"
    },
    "craiLocation": {
      "uri": "${INDEX_URI}",
      "locationType": "UriLocation"
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
    
    print(f"✅ Added CRAM track: ${TRACK_NAME}")
    sys.exit(0)
    
except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PYTHON_SCRIPT

if [[ $? -eq 0 ]]; then
    echo "✅ Successfully added CRAM track: $TRACK_NAME"
    echo "   Track ID: $TRACK_ID"
    echo "   Assembly: $ASSEMBLY"
    echo "   File: $CRAM_URI"
else
    echo "❌ Failed to add CRAM track"
    exit 1
fi
