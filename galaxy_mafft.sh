#!/bin/bash

# Galaxy API Script for MAFFT Alignment
# Usage: ./galaxy_mafft.sh <input_fasta> <api_key>

set -e

GALAXY_URL="https://usegalaxy.org"
INPUT_FILE="${1:-sequences.fasta}"
API_KEY="${2}"

if [ -z "$API_KEY" ]; then
    echo "Error: API key required"
    exit 1
fi

if [ ! -f "$INPUT_FILE" ]; then
    echo "Error: Input file '$INPUT_FILE' not found"
    exit 1
fi

echo "=== Galaxy MAFFT Alignment ==="
echo "Galaxy URL: $GALAXY_URL"
echo "Input file: $INPUT_FILE"
echo ""

# Create history
echo "[1/4] Creating history..."
HISTORY_RESPONSE=$(curl -s -X POST "$GALAXY_URL/api/histories" \
    -H "x-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{\"name\": \"MAFFT_$(date +%s)\"}")
HISTORY_ID=$(echo "$HISTORY_RESPONSE" | jq -r '.id')
echo "History: $HISTORY_ID"

# Upload FASTA
echo "[2/4] Uploading FASTA file..."
FASTA_CONTENT=$(cat "$INPUT_FILE" | sed ':a;N;$!ba;s/\n/\\n/g')
UPLOAD_RESPONSE=$(curl -s -X POST "$GALAXY_URL/api/tools" \
    -H "x-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{
        \"history_id\": \"$HISTORY_ID\",
        \"tool_id\": \"upload1\",
        \"inputs\": {
            \"files_0|url_paste\": \"$FASTA_CONTENT\",
            \"files_0|type\": \"upload_dataset\",
            \"files_0|NAME\": \"sequences.fasta\",
            \"file_type\": \"fasta\"
        }
    }")
DATASET_ID=$(echo "$UPLOAD_RESPONSE" | jq -r '.outputs[0].id')
echo "Dataset: $DATASET_ID"

# Wait for upload
echo "[3/4] Waiting for upload..."
sleep 2
for i in {1..30}; do
    STATE=$(curl -s "$GALAXY_URL/api/datasets/$DATASET_ID" \
        -H "x-api-key: $API_KEY" | jq -r '.state')
    [ "$STATE" = "ok" ] && break
    [ "$STATE" = "error" ] && { echo "Upload failed"; exit 1; }
    sleep 1
done

# Run MAFFT
echo "[4/4] Running MAFFT..."
MAFFT_RESPONSE=$(curl -s -X POST "$GALAXY_URL/api/tools" \
    -H "x-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{
        \"history_id\": \"$HISTORY_ID\",
        \"tool_id\": \"toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.526+galaxy2\",
        \"inputs\": {
            \"inputSequences\": {\"src\": \"hda\", \"id\": \"$DATASET_ID\"}
        }
    }")

MAFFT_ID=$(echo "$MAFFT_RESPONSE" | jq -r '.outputs[0].id')
if [ -z "$MAFFT_ID" ] || [ "$MAFFT_ID" = "null" ]; then
    echo "MAFFT start failed!"
    echo "$MAFFT_RESPONSE" | jq '.'
    exit 1
fi
echo "MAFFT Job: $MAFFT_ID"

# Monitor
echo ""
echo "Monitoring job (max 120 seconds)..."
for i in {1..120}; do
    STATE=$(curl -s "$GALAXY_URL/api/datasets/$MAFFT_ID" \
        -H "x-api-key: $API_KEY" | jq -r '.state')
    echo "  Status: $STATE"
    
    if [ "$STATE" = "ok" ]; then
        OUTPUT_FILE="mafft_alignment_$(date +%Y%m%d_%H%M%S).fasta"
        curl -s "$GALAXY_URL/api/datasets/$MAFFT_ID/display" \
            -H "x-api-key: $API_KEY" > "$OUTPUT_FILE"
        echo ""
        echo "✓ Alignment Complete!"
        echo "Results: $OUTPUT_FILE"
        echo ""
        echo "View Results:"
        echo "  History: $GALAXY_URL/histories/view?id=$HISTORY_ID"
        echo "  Visualization: $GALAXY_URL/visualizations/display?visualization=alignmentviewer&dataset_id=$MAFFT_ID"
        exit 0
    elif [ "$STATE" = "error" ]; then
        echo "✗ Job failed"
        exit 1
    fi
    sleep 3
done

echo "Timeout - check Galaxy manually:"
echo "$GALAXY_URL/histories/view?id=$HISTORY_ID"
