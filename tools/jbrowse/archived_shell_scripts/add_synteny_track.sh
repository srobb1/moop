#!/bin/bash
# Add Synteny Track (PIF.GZ format) to JBrowse2
# For whole genome synteny visualization between two assemblies

set -e

# Source common configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../../config/config.sh"

usage() {
    cat << EOF
Usage: $0 -1 ASSEMBLY1 -2 ASSEMBLY2 -t TRACK_ID -n TRACK_NAME -f PIF_FILE [-c CATEGORY]

Add a synteny track (PIF.GZ format) to JBrowse2

Required:
    -1    First assembly name (target genome)
    -2    Second assembly name (query genome)
    -t    Track ID (unique identifier)
    -n    Track name (display name)
    -f    Path to PIF.GZ file (must have .tbi index)

Optional:
    -c    Category (default: "Synteny")
    -r    Remote URL (if file is remote)

Example:
    $0 -1 Nvec200 -2 Nvec100 -t nvec_synteny -n "Nvec200 vs Nvec100 Synteny" -f genome1_genome2.pif.gz

Notes:
    - PIF.GZ files must be bgzipped and tabix-indexed (.tbi)
    - Generated from PAF: minimap2 | sort | bgzip | tabix
    - Both assemblies must already be loaded in JBrowse2
    - Track will appear in both genome views
EOF
    exit 1
}

# Parse arguments
ASSEMBLY1=""
ASSEMBLY2=""
TRACK_ID=""
TRACK_NAME=""
PIF_FILE=""
CATEGORY="Synteny"
REMOTE_URL=""

while getopts "1:2:t:n:f:c:r:h" opt; do
    case $opt in
        1) ASSEMBLY1="$OPTARG" ;;
        2) ASSEMBLY2="$OPTARG" ;;
        t) TRACK_ID="$OPTARG" ;;
        n) TRACK_NAME="$OPTARG" ;;
        f) PIF_FILE="$OPTARG" ;;
        c) CATEGORY="$OPTARG" ;;
        r) REMOTE_URL="$OPTARG" ;;
        h) usage ;;
        *) usage ;;
    esac
done

# Validate required arguments
if [[ -z "$ASSEMBLY1" ]] || [[ -z "$ASSEMBLY2" ]] || [[ -z "$TRACK_ID" ]] || [[ -z "$TRACK_NAME" ]] || [[ -z "$PIF_FILE" ]]; then
    echo "ERROR: Missing required arguments"
    usage
fi

# Check if remote or local
if [[ -n "$REMOTE_URL" ]]; then
    PIF_URI="$REMOTE_URL"
    INDEX_URI="${REMOTE_URL}.tbi"
    echo "Using remote PIF.GZ: $PIF_URI"
else
    # Validate local file exists
    if [[ ! -f "$PIF_FILE" ]]; then
        echo "ERROR: PIF.GZ file not found: $PIF_FILE"
        exit 1
    fi

    # Check for index
    TBI_FILE="${PIF_FILE}.tbi"
    if [[ ! -f "$TBI_FILE" ]]; then
        echo "ERROR: Tabix index (.tbi) not found: $TBI_FILE"
        echo "Create index with: tabix -p bed $PIF_FILE"
        exit 1
    fi

    # Setup paths
    JBROWSE_DIR="$JBROWSE_DATA_DIR"
    TRACKS_DIR="$JBROWSE_DIR/tracks/synteny"
    mkdir -p "$TRACKS_DIR"

    # Copy files
    FILENAME=$(basename "$PIF_FILE")
    cp "$PIF_FILE" "$TRACKS_DIR/"
    cp "$TBI_FILE" "$TRACKS_DIR/"

    PIF_URI="tracks/synteny/${FILENAME}"
    INDEX_URI="tracks/synteny/${FILENAME}.tbi"
fi

# Generate track configuration
CONFIG_FILE="$JBROWSE_DIR/config.json"

# Create track JSON
TRACK_JSON=$(cat <<EOF
{
  "type": "SyntenyTrack",
  "trackId": "${TRACK_ID}",
  "name": "${TRACK_NAME}",
  "category": ["${CATEGORY}"],
  "assemblyNames": ["${ASSEMBLY2}", "${ASSEMBLY1}"],
  "adapter": {
    "type": "PairwiseIndexedPAFAdapter",
    "pifGzLocation": {
      "uri": "${PIF_URI}",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "${INDEX_URI}",
        "locationType": "UriLocation"
      },
      "indexType": "TBI"
    },
    "assemblyNames": ["${ASSEMBLY2}", "${ASSEMBLY1}"]
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

# Verify assemblies exist
if ! grep -q "\"name\": \"${ASSEMBLY1}\"" "$CONFIG_FILE"; then
    echo "ERROR: Assembly ${ASSEMBLY1} not found in JBrowse config"
    exit 1
fi

if ! grep -q "\"name\": \"${ASSEMBLY2}\"" "$CONFIG_FILE"; then
    echo "ERROR: Assembly ${ASSEMBLY2} not found in JBrowse config"
    exit 1
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
    
    print(f"✅ Added synteny track: ${TRACK_NAME}")
    sys.exit(0)
    
except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PYTHON_SCRIPT

if [[ $? -eq 0 ]]; then
    echo "✅ Successfully added synteny track: $TRACK_NAME"
    echo "   Track ID: $TRACK_ID"
    echo "   Assemblies: $ASSEMBLY1 <-> $ASSEMBLY2"
    echo "   File: $PIF_URI"
else
    echo "❌ Failed to add synteny track"
    exit 1
fi
