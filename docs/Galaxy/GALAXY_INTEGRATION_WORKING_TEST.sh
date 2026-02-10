#!/bin/bash

# WORKING REFERENCE: Galaxy MAFFT Alignment Pipeline
# This script successfully ran on 2026-02-04 and demonstrates:
# 1. Creating a Galaxy history
# 2. Uploading sequences
# 3. Running MAFFT alignment
# 4. Monitoring job completion
# 5. Retrieving results

# Usage: ./GALAXY_INTEGRATION_WORKING_TEST.sh sequences.fasta YOUR_API_KEY

set -e

# Configuration
GALAXY_URL="https://usegalaxy.org"
INPUT_FILE="${1:-sequences.fasta}"
API_KEY="${2}"

if [ -z "$API_KEY" ]; then
    echo "Error: API key required"
    echo "Usage: $0 <input_fasta> <api_key>"
    echo ""
    echo "Get your API key from: https://usegalaxy.org/user/api_key"
    exit 1
fi

if [ ! -f "$INPUT_FILE" ]; then
    echo "Error: Input file '$INPUT_FILE' not found"
    exit 1
fi

echo "=== Galaxy MAFFT Alignment Pipeline ==="
echo "Galaxy URL: $GALAXY_URL"
echo "Input file: $INPUT_FILE"
echo ""

# Step 1: Create a new history
echo "[1/5] Creating new history..."
HISTORY_NAME="MAFFT_alignment_$(date +%Y%m%d_%H%M%S)"
HISTORY_ID=$(curl -s -X POST "$GALAXY_URL/api/histories" \
    -H "x-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{\"name\": \"$HISTORY_NAME\"}" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)

if [ -z "$HISTORY_ID" ]; then
    echo "Error: Failed to create history"
    exit 1
fi
echo "History created: $HISTORY_ID"

# Step 2: Upload FASTA file
echo "[2/5] Uploading FASTA file..."
UPLOAD_RESPONSE=$(curl -s -X POST "$GALAXY_URL/api/tools" \
    -H "x-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{
        \"history_id\": \"$HISTORY_ID\",
        \"tool_id\": \"upload1\",
        \"inputs\": {
            \"files_0|url_paste\": \"$(cat $INPUT_FILE | sed ':a;N;$!ba;s/\n/\\n/g')\",
            \"files_0|type\": \"upload_dataset\",
            \"files_0|NAME\": \"$INPUT_FILE\",
            \"dbkey\": \"?\",
            \"file_type\": \"fasta\"
        }
    }")

DATASET_ID=$(echo "$UPLOAD_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
if [ -z "$DATASET_ID" ]; then
    echo "Error: Failed to upload file"
    echo "Response: $UPLOAD_RESPONSE"
    exit 1
fi
echo "File uploaded: $DATASET_ID"

# Step 3: Wait for upload to complete
echo "[3/5] Waiting for upload to complete..."
sleep 5
for i in {1..30}; do
    STATE=$(curl -s "$GALAXY_URL/api/datasets/$DATASET_ID" \
        -H "x-api-key: $API_KEY" | grep -o '"state":"[^"]*"' | cut -d'"' -f4)
    echo "  Status: $STATE"
    if [ "$STATE" = "ok" ]; then
        break
    elif [ "$STATE" = "error" ]; then
        echo "Error: Upload failed"
        exit 1
    fi
    sleep 2
done

# Step 4: Run MAFFT
echo "[4/5] Running MAFFT alignment..."
MAFFT_RESPONSE=$(curl -s -X POST "$GALAXY_URL/api/tools" \
    -H "x-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{
        \"history_id\": \"$HISTORY_ID\",
        \"tool_id\": \"toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.3\",
        \"inputs\": {
            \"inputSequences\": {\"src\": \"hda\", \"id\": \"$DATASET_ID\"},
            \"outputFormat\": \"fasta\",
            \"matrix_condition|matrix\": \"BLOSUM62\",
            \"flavour\": \"mafft-fftns\"
        }
    }")

MAFFT_JOB_ID=$(echo "$MAFFT_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
if [ -z "$MAFFT_JOB_ID" ]; then
    echo "Error: Failed to start MAFFT"
    echo "Response: $MAFFT_RESPONSE"
    exit 1
fi
echo "MAFFT job started: $MAFFT_JOB_ID"

# Step 5: Monitor job and download results
echo "[5/5] Monitoring MAFFT job..."
for i in {1..60}; do
    JOB_STATE=$(curl -s "$GALAXY_URL/api/datasets/$MAFFT_JOB_ID" \
        -H "x-api-key: $API_KEY" | grep -o '"state":"[^"]*"' | cut -d'"' -f4)
    echo "  Status: $JOB_STATE"
    
    if [ "$JOB_STATE" = "ok" ]; then
        echo ""
        echo "=== Alignment Complete ==="
        OUTPUT_FILE="mafft_alignment_$(date +%Y%m%d_%H%M%S).fasta"
        curl -s "$GALAXY_URL/api/datasets/$MAFFT_JOB_ID/display" \
            -H "x-api-key: $API_KEY" > "$OUTPUT_FILE"
        echo "Results saved to: $OUTPUT_FILE"
        echo "View in Galaxy: $GALAXY_URL/histories/view?id=$HISTORY_ID"
        exit 0
    elif [ "$JOB_STATE" = "error" ]; then
        echo "Error: MAFFT job failed"
        exit 1
    fi
    sleep 5
done

echo "Warning: Job monitoring timeout. Check Galaxy manually:"
echo "$GALAXY_URL/histories/view?id=$HISTORY_ID"
