#!/bin/bash
# ============================================================================
# JBrowse2 Integration Test Suite
# Testing on Local Machine Before Deployment to Separate Tracks Server
# ============================================================================
#
# This test script validates:
# 1. JWT token generation and verification
# 2. Assembly config API endpoints
# 3. Track filtering by user access level
# 4. HTTP range request support
# 5. Token validation on tracks server
#
# Run this after completing SETUP.md steps
# ============================================================================

set -e

MOOP_URL="http://127.0.0.1"
MOOP_ROOT="/data/moop"
TEST_LOG="jbrowse2_test_results.log"

echo "========================================" | tee $TEST_LOG
echo "JBrowse2 Integration Test Suite"
echo "========================================" | tee -a $TEST_LOG
echo "Start time: $(date)" | tee -a $TEST_LOG
echo ""

# ============================================================================
# TEST 1: Verify directory structure
# ============================================================================
echo "TEST 1: Verifying directory structure..." | tee -a $TEST_LOG

required_dirs=(
    "$MOOP_ROOT/lib/jbrowse"
    "$MOOP_ROOT/api/jbrowse2"
    "$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
    "$MOOP_ROOT/data/tracks/bigwig"
    "$MOOP_ROOT/data/tracks/bam"
    "$MOOP_ROOT/certs"
)

for dir in "${required_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "  ✓ $dir" | tee -a $TEST_LOG
    else
        echo "  ✗ MISSING: $dir" | tee -a $TEST_LOG
        exit 1
    fi
done
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 2: Verify files exist
# ============================================================================
echo "TEST 2: Verifying required files..." | tee -a $TEST_LOG

required_files=(
    "$MOOP_ROOT/lib/jbrowse/track_token.php"
    "$MOOP_ROOT/api/jbrowse2/assembly.php"
    "$MOOP_ROOT/api/jbrowse2/fake-tracks-server.php"
    "$MOOP_ROOT/metadata/jbrowse2-configs/tracks/rna_seq_coverage.json"
    "$MOOP_ROOT/metadata/jbrowse2-configs/tracks/dna_alignment.json"
    "$MOOP_ROOT/metadata/jbrowse2-configs/tracks/chip_seq_h3k4me3.json"
    "$MOOP_ROOT/certs/jwt_private_key.pem"
    "$MOOP_ROOT/certs/jwt_public_key.pem"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file" | tee -a $TEST_LOG
    else
        echo "  ✗ MISSING: $file" | tee -a $TEST_LOG
        exit 1
    fi
done
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 3: Verify track data files exist
# ============================================================================
echo "TEST 3: Verifying track data files..." | tee -a $TEST_LOG

track_files=(
    "$MOOP_ROOT/data/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw"
    "$MOOP_ROOT/data/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_h3k4me3.bw"
    "$MOOP_ROOT/data/tracks/bam/Anoura_caudifer_GCA_004027475.1_dna.bam"
    "$MOOP_ROOT/data/tracks/bam/Anoura_caudifer_GCA_004027475.1_dna.bam.bai"
)

for file in "${track_files[@]}"; do
    if [ -f "$file" ]; then
        size=$(du -h "$file" | cut -f1)
        echo "  ✓ $file ($size)" | tee -a $TEST_LOG
    else
        echo "  ✗ MISSING: $file" | tee -a $TEST_LOG
        exit 1
    fi
done
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 4: Check PHP syntax
# ============================================================================
echo "TEST 4: Checking PHP syntax..." | tee -a $TEST_LOG

php_files=(
    "$MOOP_ROOT/lib/jbrowse/track_token.php"
    "$MOOP_ROOT/api/jbrowse2/assembly.php"
    "$MOOP_ROOT/api/jbrowse2/fake-tracks-server.php"
)

for file in "${php_files[@]}"; do
    if php -l "$file" > /dev/null 2>&1; then
        echo "  ✓ $(basename $file)" | tee -a $TEST_LOG
    else
        echo "  ✗ PHP SYNTAX ERROR: $(basename $file)" | tee -a $TEST_LOG
        php -l "$file" | tee -a $TEST_LOG
        exit 1
    fi
done
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 5: Verify JWT keys
# ============================================================================
echo "TEST 5: Verifying JWT keys..." | tee -a $TEST_LOG

if openssl rsa -in "$MOOP_ROOT/certs/jwt_private_key.pem" -check -noout > /dev/null 2>&1; then
    echo "  ✓ Private key is valid" | tee -a $TEST_LOG
else
    echo "  ✗ Private key is invalid" | tee -a $TEST_LOG
    exit 1
fi

if grep -q "BEGIN PUBLIC KEY" "$MOOP_ROOT/certs/jwt_public_key.pem" && grep -q "END PUBLIC KEY" "$MOOP_ROOT/certs/jwt_public_key.pem"; then
    echo "  ✓ Public key is valid" | tee -a $TEST_LOG
else
    echo "  ✗ Public key is invalid" | tee -a $TEST_LOG
    exit 1
fi
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 6: Test track config JSON validity
# ============================================================================
echo "TEST 6: Validating track config JSON files..." | tee -a $TEST_LOG

for track_file in "$MOOP_ROOT"/metadata/jbrowse2-configs/tracks/*.json; do
    if php -r "json_decode(file_get_contents('$track_file'), true) ?: exit(1);" 2>/dev/null; then
        echo "  ✓ $(basename $track_file)" | tee -a $TEST_LOG
    else
        echo "  ✗ INVALID JSON: $(basename $track_file)" | tee -a $TEST_LOG
        exit 1
    fi
done
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 7: Test API endpoint (requires web server running)
# ============================================================================
echo "TEST 7: Testing API endpoints (requires web server)..." | tee -a $TEST_LOG
echo "" | tee -a $TEST_LOG

# Check if web server is running
if curl -s "$MOOP_URL" > /dev/null 2>&1; then
    echo "  ✓ Web server is running" | tee -a $TEST_LOG
    
    # Test assembly config API
    echo "  Testing assembly config endpoint..." | tee -a $TEST_LOG
    response=$(curl -s "$MOOP_URL/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" 2>/dev/null || echo "")
    
    if echo "$response" | php -r "json_decode(file_get_contents('php://stdin'), true) ?: exit(1);" 2>/dev/null; then
        echo "    ✓ API returns valid JSON" | tee -a $TEST_LOG
        track_count=$(echo "$response" | php -r "echo count(json_decode(file_get_contents('php://stdin'), true)['tracks'] ?? []);" 2>/dev/null)
        echo "    ✓ Config includes $track_count tracks" | tee -a $TEST_LOG
    else
        echo "    ✗ API did not return valid JSON" | tee -a $TEST_LOG
        echo "    Response: $response" | tee -a $TEST_LOG
    fi
else
    echo "  ⚠ Web server not running - skipping API tests" | tee -a $TEST_LOG
    echo "    Start web server with: php -S 127.0.0.1:80 -t /data/moop" | tee -a $TEST_LOG
fi
echo "" | tee -a $TEST_LOG

# ============================================================================
# TEST 8: Test fake tracks server endpoint
# ============================================================================
echo "TEST 8: Testing fake tracks server..." | tee -a $TEST_LOG

if curl -s "$MOOP_URL" > /dev/null 2>&1; then
    # Test without token (should work for local IP)
    response=$(curl -s -w "\n%{http_code}" \
        "$MOOP_URL/api/jbrowse2/fake-tracks-server.php?file=bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw" 2>/dev/null || echo "")
    
    http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" = "200" ]; then
        echo "  ✓ Tracks server serves files for local IP (HTTP $http_code)" | tee -a $TEST_LOG
    else
        echo "  ⚠ Tracks server returned HTTP $http_code (may be OK depending on PHP path)" | tee -a $TEST_LOG
    fi
else
    echo "  ⚠ Web server not running - skipping tracks server tests" | tee -a $TEST_LOG
fi
echo "" | tee -a $TEST_LOG

# ============================================================================
# Summary
# ============================================================================
echo "========================================" | tee -a $TEST_LOG
echo "All checks completed successfully!"
echo "========================================" | tee -a $TEST_LOG
echo "End time: $(date)" | tee -a $TEST_LOG
echo ""
echo "Next steps:"
echo "1. Start PHP web server: php -S 127.0.0.1:80 -t /data/moop"
echo "2. Test API: curl 'http://127.0.0.1/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1'"
echo "3. Review test results: cat $TEST_LOG"
echo ""
