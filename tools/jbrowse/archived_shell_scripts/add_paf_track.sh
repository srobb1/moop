#!/bin/bash
# Add PAF (Pairwise mApping Format) Track to JBrowse2
# PAF files contain long-read alignments (minimap2 output)

set -e

# Source common configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../../config/config.sh"

usage() {
    cat << EOF
Usage: $0 -a ASSEMBLY_NAME -t TRACK_ID -n TRACK_NAME -f PAF_FILE [-c CATEGORY] [-l ACCESS_LEVEL]

Add a PAF alignment track to JBrowse2

Required:
    -a    Assembly name (organism identifier)
    -t    Track ID (unique identifier)
    -n    Track name (display name)
    -f    Path to PAF file

Optional:
    -c    Category (default: "Long-read Alignments")
    -l    Access level (public|ip_range|admin, default: public)
    -r    Remote URL (if file is remote)

Example:
    $0 -a Nvec200 -t nanopore_paf -n "Nanopore Reads" -f /path/to/alignments.paf

Notes:
    - PAF is minimap2's pairwise alignment format
    - Good for long-read alignments (PacBio, Nanopore)
    - No index file required for simple PAF
EOF
    exit 1
}

# Parse arguments
ASSEMBLY=""
TRACK_ID=""
TRACK_NAME=""
PAF_FILE=""
CATEGORY="Long-read Alignments"
ACCESS_LEVEL="public"
REMOTE_URL=""

while getopts "a:t:n:f:c:l:r:h" opt; do
    case $opt in
        a) ASSEMBLY="$OPTARG" ;;
        t) TRACK_ID="$OPTARG" ;;
        n) TRACK_NAME="$OPTARG" ;;
        f) PAF_FILE="$OPTARG" ;;
        c) CATEGORY="$OPTARG" ;;
        l) ACCESS_LEVEL="$OPTARG" ;;
        r) REMOTE_URL="$OPTARG" ;;
        h) usage ;;
        *) usage ;;
    esac
done

# Validate required arguments
if [[ -z "$ASSEMBLY" ]] || [[ -z "$TRACK_ID" ]] || [[ -z "$TRACK_NAME" ]] || [[ -z "$PAF_FILE" ]]; then
    echo "ERROR: Missing required arguments"
    usage
fi

# Check if remote or local
if [[ -n "$REMOTE_URL" ]]; then
    PAF_URI="$REMOTE_URL"
    echo "Using remote PAF: $PAF_URI"
else
    # Validate local file exists
    if [[ ! -f "$PAF_FILE" ]]; then
        echo "ERROR: PAF file not found: $PAF_FILE"
        exit 1
    fi

    # Setup paths
    JBROWSE_DIR="$JBROWSE_DATA_DIR"
    TRACKS_DIR="$JBROWSE_DIR/tracks/${ASSEMBLY}/paf"
    mkdir -p "$TRACKS_DIR"

    # Copy file
    FILENAME=$(basename "$PAF_FILE")
    cp "$PAF_FILE" "$TRACKS_DIR/"

    PAF_URI="tracks/${ASSEMBLY}/paf/${FILENAME}"
fi

# Generate track configuration
CONFIG_FILE="$JBROWSE_DIR/config.json"

# Create track JSON
TRACK_JSON=$(cat <<EOF
{
  "type": "AlignmentsTrack",
  "trackId": "${TRACK_ID}",
  "name": "${TRACK_NAME}",
  "category": ["${CATEGORY}"],
  "assemblyNames": ["${ASSEMBLY}"],
  "adapter": {
    "type": "PAFAdapter",
    "pafLocation": {
      "uri": "${PAF_URI}",
      "locationType": "UriLocation"
    },
    "assemblyNames": ["${ASSEMBLY}"]
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
    
    print(f"✅ Added PAF track: ${TRACK_NAME}")
    sys.exit(0)
    
except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PYTHON_SCRIPT

if [[ $? -eq 0 ]]; then
    echo "✅ Successfully added PAF track: $TRACK_NAME"
    echo "   Track ID: $TRACK_ID"
    echo "   Assembly: $ASSEMBLY"
    echo "   File: $PAF_URI"
else
    echo "❌ Failed to add PAF track"
    exit 1
fi
