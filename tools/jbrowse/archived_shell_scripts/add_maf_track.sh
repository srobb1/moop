#!/bin/bash

# Add MAF (Multiple Alignment Format) track to JBrowse2
# Requires: jbrowse-plugin-mafviewer plugin installed

# Usage: add_maf_track.sh -f file.maf.gz -a assembly -t trackId -n "Track Name" -s samples_json -c category -l access_level

set -e

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -f|--file) MAF_FILE="$2"; shift 2;;
        -a|--assembly) ASSEMBLY="$2"; shift 2;;
        -t|--track-id) TRACK_ID="$2"; shift 2;;
        -n|--name) TRACK_NAME="$2"; shift 2;;
        -s|--samples) SAMPLES_JSON="$2"; shift 2;;
        -c|--category) CATEGORY="$2"; shift 2;;
        -l|--access) ACCESS_LEVEL="$2"; shift 2;;
        -r|--remote) IS_REMOTE="true"; shift;;
        *) echo "Unknown option: $1"; exit 1;;
    esac
done

# Required parameters
if [[ -z "$MAF_FILE" || -z "$ASSEMBLY" || -z "$TRACK_ID" || -z "$TRACK_NAME" || -z "$SAMPLES_JSON" ]]; then
    echo "Error: Missing required parameters"
    echo "Usage: $0 -f file.maf.gz -a assembly -t trackId -n \"Track Name\" -s samples_json [-c category] [-l access_level] [-r]"
    exit 1
fi

# Set defaults
CATEGORY="${CATEGORY:-Alignment}"
ACCESS_LEVEL="${ACCESS_LEVEL:-public}"
MOOP_ROOT="${MOOP_ROOT:-/data/moop}"

# Output directory
OUTPUT_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
mkdir -p "$OUTPUT_DIR"

# Determine file URI
if [[ "$IS_REMOTE" == "true" ]]; then
    URI="$MAF_FILE"
else
    # Convert absolute path to relative JBrowse path
    URI=$(echo "$MAF_FILE" | sed "s|^$MOOP_ROOT/||")
fi

# Create track configuration
CONFIG_FILE="$OUTPUT_DIR/${TRACK_ID}.json"

cat > "$CONFIG_FILE" << EOF
{
  "type": "MafTrack",
  "trackId": "$TRACK_ID",
  "name": "$TRACK_NAME",
  "adapter": {
    "type": "MafAdapter",
    "mafLocation": {
      "uri": "$URI",
      "locationType": "UriLocation"
    }
EOF

# Add index if file is compressed
if [[ "$MAF_FILE" == *.gz ]]; then
    cat >> "$CONFIG_FILE" << EOF
    ,
    "index": {
      "location": {
        "uri": "${URI}.gzi",
        "locationType": "UriLocation"
      }
    }
EOF
fi

cat >> "$CONFIG_FILE" << EOF
  },
  "assemblyNames": ["$ASSEMBLY"],
  "category": ["$CATEGORY"],
  "samples": $SAMPLES_JSON
}
EOF

echo "✓ Created MAF track configuration: $CONFIG_FILE"
echo "  Track ID: $TRACK_ID"
echo "  Assembly: $ASSEMBLY"
echo "  File: $MAF_FILE"
echo ""
echo "⚠ Note: This track requires the jbrowse-plugin-mafviewer plugin to be installed"
echo "  Install with: jbrowse add-plugin https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js"
