#!/bin/bash
# Add GTF Track to JBrowse2
# GTF format gene annotations (common from Ensembl)

set -e

# Source common configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/../../config/config.sh"

usage() {
    cat << EOF
Usage: $0 -a ASSEMBLY_NAME -t TRACK_ID -n TRACK_NAME -f GTF_FILE [-c CATEGORY] [-l ACCESS_LEVEL]

Add a GTF annotation track to JBrowse2

Required:
    -a    Assembly name (organism identifier)
    -t    Track ID (unique identifier)
    -n    Track name (display name)
    -f    Path to GTF file

Optional:
    -c    Category (default: "Annotations")
    -l    Access level (public|ip_range|admin, default: public)
    -r    Remote URL (if file is remote)
    -i    Enable text search indexing (flag)

Example:
    $0 -a Nvec200 -t genes_gtf -n "Gene Annotations" -f /path/to/genes.gtf -i

Notes:
    - GTF is common for Ensembl gene annotations
    - Similar to GFF3 but different attribute format
    - Text search indexing requires jbrowse CLI
EOF
    exit 1
}

# Parse arguments
ASSEMBLY=""
TRACK_ID=""
TRACK_NAME=""
GTF_FILE=""
CATEGORY="Annotations"
ACCESS_LEVEL="public"
REMOTE_URL=""
INDEX_TEXT=false

while getopts "a:t:n:f:c:l:r:ih" opt; do
    case $opt in
        a) ASSEMBLY="$OPTARG" ;;
        t) TRACK_ID="$OPTARG" ;;
        n) TRACK_NAME="$OPTARG" ;;
        f) GTF_FILE="$OPTARG" ;;
        c) CATEGORY="$OPTARG" ;;
        l) ACCESS_LEVEL="$OPTARG" ;;
        r) REMOTE_URL="$OPTARG" ;;
        i) INDEX_TEXT=true ;;
        h) usage ;;
        *) usage ;;
    esac
done

# Validate required arguments
if [[ -z "$ASSEMBLY" ]] || [[ -z "$TRACK_ID" ]] || [[ -z "$TRACK_NAME" ]] || [[ -z "$GTF_FILE" ]]; then
    echo "ERROR: Missing required arguments"
    usage
fi

# Check if remote or local
if [[ -n "$REMOTE_URL" ]]; then
    GTF_URI="$REMOTE_URL"
    echo "Using remote GTF: $GTF_URI"
else
    # Validate local file exists
    if [[ ! -f "$GTF_FILE" ]]; then
        echo "ERROR: GTF file not found: $GTF_FILE"
        exit 1
    fi

    # Setup paths
    JBROWSE_DIR="$JBROWSE_DATA_DIR"
    TRACKS_DIR="$JBROWSE_DIR/tracks/${ASSEMBLY}/gtf"
    mkdir -p "$TRACKS_DIR"

    # Copy file
    FILENAME=$(basename "$GTF_FILE")
    cp "$GTF_FILE" "$TRACKS_DIR/"

    GTF_URI="tracks/${ASSEMBLY}/gtf/${FILENAME}"
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
    "type": "GtfAdapter",
    "gtfLocation": {
      "uri": "${GTF_URI}",
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
    
    print(f"✅ Added GTF track: ${TRACK_NAME}")
    sys.exit(0)
    
except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PYTHON_SCRIPT

if [[ $? -ne 0 ]]; then
    echo "❌ Failed to add GTF track"
    exit 1
fi

echo "✅ Successfully added GTF track: $TRACK_NAME"
echo "   Track ID: $TRACK_ID"
echo "   Assembly: $ASSEMBLY"
echo "   File: $GTF_URI"

# Text indexing if requested
if [[ "$INDEX_TEXT" == true ]]; then
    if command -v jbrowse &> /dev/null; then
        echo "Creating text search index..."
        cd "$JBROWSE_DIR"
        jbrowse text-index --out "$JBROWSE_DIR" --perTrack --tracks="$TRACK_ID" --attributes=gene_id,gene_name,transcript_id 2>&1 || echo "Warning: Text indexing failed"
        echo "✅ Text search index created"
    else
        echo "⚠️  JBrowse CLI not found, skipping text indexing"
    fi
fi
