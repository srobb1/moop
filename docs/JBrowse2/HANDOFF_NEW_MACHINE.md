# JBrowse2 Integration - Handoff Document for New Machine Setup

## Overview

This document provides complete instructions for setting up the JBrowse2 integration on a new machine. Everything has been tested and verified on the current machine. Follow these steps exactly in order.

---

## Prerequisites

Before starting, ensure the new machine has:
- Ubuntu 20.04+ or equivalent Linux distribution
- PHP 7.4+ (PHP 8.3+ recommended)
- Required PHP extensions: posix, json, sqlite3, openssl, curl
- Git (to clone MOOP repository)
- OpenSSL (should come with OS)
- Composer (will be installed)
- curl (for testing)
- jq (optional, for pretty-printing JSON)

### Quick Check
```bash
php --version
php -m | grep -E "posix|json|sqlite3|openssl|curl"
```

---

## Complete Setup Steps

### STEP 1: Clone/Update MOOP Repository
```bash
# If cloning fresh
git clone https://github.com/your-org/moop.git /data/moop
cd /data/moop

# If updating existing repo
cd /data/moop
git pull origin main
```

### STEP 2: Create Directory Structure
```bash
mkdir -p /data/moop/metadata/jbrowse2-configs/tracks
mkdir -p /data/moop/lib/jbrowse
mkdir -p /data/moop/api/jbrowse2
mkdir -p /data/moop/data/tracks/bigwig
mkdir -p /data/moop/data/tracks/bam
mkdir -p /data/moop/certs
mkdir -p /data/moop/tests
```

### STEP 3: Install Composer
```bash
cd /data/moop
curl -sS https://getcomposer.org/installer | php

# Verify installation
php composer.phar --version
```

### STEP 4: Install Firebase JWT Library
```bash
cd /data/moop
php composer.phar require firebase/php-jwt

# Verify installation
php -r "require 'vendor/autoload.php'; use Firebase\JWT\JWT; echo 'JWT library loaded: OK';"
```

### STEP 5: Generate JWT Keys
```bash
cd /data/moop/certs

# Generate private key (2048-bit RSA)
openssl genrsa -out jwt_private_key.pem 2048

# Generate public key from private key
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem

# Verify keys (both should say "valid" or "OK")
openssl rsa -in jwt_private_key.pem -check -noout
grep "BEGIN PUBLIC KEY" jwt_public_key.pem && echo "Public key OK"

# Set appropriate permissions
chmod 600 jwt_private_key.pem
chmod 644 jwt_public_key.pem
```

### STEP 6: Copy Track Configuration Files

These files define which tracks are available and who can access them.

**File 1:** `/data/moop/metadata/jbrowse2-configs/tracks/rna_seq_coverage.json`
```json
{
  "name": "RNA-seq Coverage",
  "description": "RNA-seq expression coverage across replicates",
  "type": "quantitative",
  "track_id": "rna_seq_coverage",
  "access_levels": ["Public", "Collaborator", "ALL"],
  "groups": ["Transcriptomics"],
  "file_template": "{organism}_{assembly}_rna_coverage.bw",
  "format": "bigwig",
  "color": "#1f77b4",
  "display": {
    "type": "WiggleYScaleQuantitativeTrack"
  }
}
```

**File 2:** `/data/moop/metadata/jbrowse2-configs/tracks/dna_alignment.json`
```json
{
  "name": "DNA-seq Alignment",
  "description": "Whole genome sequencing alignment",
  "type": "alignment",
  "track_id": "dna_alignment",
  "access_levels": ["ALL"],
  "groups": ["Sequencing"],
  "file_template": "{organism}_{assembly}_dna.bam",
  "format": "bam",
  "display": {
    "type": "LinearAlignmentsDisplay"
  }
}
```

**File 3:** `/data/moop/metadata/jbrowse2-configs/tracks/chip_seq_h3k4me3.json`
```json
{
  "name": "H3K4me3 ChIP-seq",
  "description": "H3K4me3 histone modification ChIP-seq data",
  "type": "quantitative",
  "track_id": "chip_seq_h3k4me3",
  "access_levels": ["Collaborator", "ALL"],
  "groups": ["ChIP-seq", "Epigenomics"],
  "file_template": "{organism}_{assembly}_h3k4me3.bw",
  "format": "bigwig",
  "color": "#ff7f0e",
  "display": {
    "type": "WiggleYScaleQuantitativeTrack"
  }
}
```

**Validate JSON:**
```bash
for f in /data/moop/metadata/jbrowse2-configs/tracks/*.json; do
  php -r "json_decode(file_get_contents('$f'), true) ?: exit(1);" && echo "✓ $(basename $f)" || echo "✗ $(basename $f)"
done
```

### STEP 7: Copy PHP Library Files

**File:** `/data/moop/lib/jbrowse/track_token.php` (112 lines)
- See: `lib/jbrowse/track_token.php` in repository
- Or: Copy from existing machine

**File:** `/data/moop/lib/jbrowse/index.php` (18 lines)
- See: `lib/jbrowse/index.php` in repository

**Verify PHP Syntax:**
```bash
php -l /data/moop/lib/jbrowse/track_token.php
php -l /data/moop/lib/jbrowse/index.php
```

### STEP 8: Copy API Endpoint Files

**File 1:** `/data/moop/api/jbrowse2/assembly.php` (202 lines)
- Full MOOP integration version
- See: `api/jbrowse2/assembly.php` in repository

**File 2:** `/data/moop/api/jbrowse2/test-assembly.php` (167 lines)
- Test version without session dependency
- See: `api/jbrowse2/test-assembly.php` in repository

**File 3:** `/data/moop/api/jbrowse2/fake-tracks-server.php` (128 lines)
- Simulates tracks server for testing
- See: `api/jbrowse2/fake-tracks-server.php` in repository

**Verify PHP Syntax:**
```bash
php -l /data/moop/api/jbrowse2/assembly.php
php -l /data/moop/api/jbrowse2/test-assembly.php
php -l /data/moop/api/jbrowse2/fake-tracks-server.php
```

### STEP 9: Copy Test Data Files

Create small test data files for each track:

```bash
# BigWig test files
cat > /data/moop/data/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw << 'EOF'
DUMMY_BIGWIG_DATA_FOR_TESTING
This is a test BigWig file.
EOF

cat > /data/moop/data/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_h3k4me3.bw << 'EOF'
DUMMY_BIGWIG_H3K4ME3_DATA_FOR_TESTING
EOF

cat > /data/moop/data/tracks/bigwig/Montipora_capitata_HIv3_rna_coverage.bw << 'EOF'
DUMMY_BIGWIG_CORAL_RNA_DATA_FOR_TESTING
EOF

# BAM test files
cat > /data/moop/data/tracks/bam/Anoura_caudifer_GCA_004027475.1_dna.bam << 'EOF'
DUMMY_BAM_DATA_FOR_TESTING
EOF

cat > /data/moop/data/tracks/bam/Anoura_caudifer_GCA_004027475.1_dna.bam.bai << 'EOF'
DUMMY_BAM_INDEX_DATA_FOR_TESTING
EOF

# Verify
ls -lah /data/moop/data/tracks/bigwig/
ls -lah /data/moop/data/tracks/bam/
```

### STEP 10: Copy Test Script

**File:** `/data/moop/tests/jbrowse2_integration_test.sh` (222 lines)
- See: `tests/jbrowse2_integration_test.sh` in repository

```bash
chmod +x /data/moop/tests/jbrowse2_integration_test.sh
```

### STEP 11: Run Tests

**Test 1: Pre-flight checks (no web server needed)**
```bash
cd /data/moop
bash tests/jbrowse2_integration_test.sh
```

Expected output:
```
TEST 1: Verifying directory structure... ✓ 6/6
TEST 2: Verifying required files....... ✓ 8/8
TEST 3: Verifying track data files.... ✓ 4/4
TEST 4: Checking PHP syntax........... ✓ 3/3
TEST 5: Verifying JWT keys........... ✓ 2/2
TEST 6: Validating track config JSON. ✓ 3/3
```

**Test 2: Start PHP web server**
```bash
cd /data/moop
php -S 127.0.0.1:8888 > /tmp/php_server.log 2>&1 &

# Verify it's running
ps aux | grep "php -S"
```

**Test 3: Test API endpoints**
```bash
# Public access - should see 1 track
curl "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public" | jq '.tracks | length'

# Collaborator access - should see 2 tracks
curl "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Collaborator" | jq '.tracks | length'

# Admin access - should see 3 tracks
curl "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL" | jq '.tracks | length'
```

**Test 4: Test tracks server with range requests**
```bash
# Without token (local IP)
curl "http://127.0.0.1:8888/api/jbrowse2/fake-tracks-server.php?file=bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw" | head

# With token and range request
TOKEN=$(curl -s "http://127.0.0.1:8888/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public" | jq -r '.tracks[0].adapter.bigWigLocation.uri' | grep -oP '(?<=token=)[^"&]*')

curl -H "Range: bytes=0-50" \
  "http://127.0.0.1:8888/api/jbrowse2/fake-tracks-server.php?file=bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw&token=$TOKEN" \
  -v 2>&1 | grep "206\|Content-Range"
```

---

## Verification Checklist

After completing all steps, verify:

- [ ] All directories exist and are writable
- [ ] JWT keys exist and are valid
- [ ] Composer installed successfully
- [ ] Firebase/JWT library loads without error
- [ ] All PHP files have correct syntax
- [ ] All JSON track configs are valid
- [ ] Test data files exist
- [ ] Pre-flight test script passes all checks
- [ ] PHP web server starts on port 8888
- [ ] API endpoints return valid JSON
- [ ] Track filtering works (different counts per access level)
- [ ] HTTP range requests return 206 status

---

## Troubleshooting

### Composer Installation Issues
```bash
# If curl-based install fails
php -r "readfile('https://getcomposer.org/installer');" | php
```

### JWT Library Not Found
```bash
# Reinstall
cd /data/moop
php composer.phar require firebase/php-jwt

# Check if vendor/ directory exists
ls -la vendor/firebase/
```

### API Returns 500 Error
```bash
# Check server logs
tail -50 /tmp/php_server.log

# Common issues:
# - JWT keys not found: check /data/moop/certs/
# - Track configs not found: check /data/moop/metadata/jbrowse2-configs/tracks/
# - Missing PHP includes: verify lib/ and api/jbrowse2/ files exist
```

### Keys Invalid
```bash
# Regenerate
cd /data/moop/certs
rm jwt_*.pem
openssl genrsa -out jwt_private_key.pem 2048
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
```

---

## What Each Component Does

### JWT Token Library (`lib/jbrowse/track_token.php`)
- `generateTrackToken()` - Creates signed JWT for track access
- `verifyTrackToken()` - Validates JWT signature and expiration
- `isWhitelistedIP()` - Checks if user is on internal network

### Test Assembly API (`api/jbrowse2/test-assembly.php`)
- Accepts: organism, assembly, access_level
- Returns: Complete JBrowse2 config with tracks filtered by access level
- No session dependency (perfect for testing)

### Fake Tracks Server (`api/jbrowse2/fake-tracks-server.php`)
- Accepts: file path, token query parameter, HTTP Range header
- Validates JWT token before serving files
- Supports HTTP 206 Partial Content for range requests
- Allows local IPs without token

### Track Configs (`metadata/jbrowse2-configs/tracks/*.json`)
- Define track name, description, file format
- Specify access levels: Public, Collaborator, ALL
- Template file names with {organism} and {assembly} placeholders

---

## Next Steps After Setup

1. **Test with real MOOP session:** Integrate with `/api/jbrowse2/assembly.php`
2. **Deploy tracks server:** Use configs from `notes/jbrowse2_quick_reference.md` 
3. **Deploy JBrowse2 frontend:** Point to `/api/jbrowse2/assembly.php` endpoint
4. **Add real track data:** Replace test files with actual BigWig/BAM files
5. **Configure web server:** Set up Apache/Nginx vhost for MOOP

---

## Reference Documentation

- **Architecture Guide:** `notes/jbrowse2_integration_plan.md`
- **Quick Reference:** `notes/jbrowse2_quick_reference.md`  
- **Setup Guide:** `notes/jbrowse2_SETUP.md`
- **Test Results:** `notes/jbrowse2_TEST_RESULTS.md`
- **PHP Safety Analysis:** `notes/PHP_VERSION_SAFETY.md`

---

## Support

If issues arise:
1. Check test results: `bash /data/moop/tests/jbrowse2_integration_test.sh`
2. Check server logs: `tail -50 /tmp/php_server.log`
3. Verify all files copied: `ls -la /data/moop/lib/jbrowse/ /data/moop/api/jbrowse2/`
4. Validate JSON: `php -r "var_dump(json_last_error_msg());"`
5. Test JWT keys: `openssl rsa -in /data/moop/certs/jwt_private_key.pem -check -noout`

---

**This setup has been tested and verified. Follow steps in order for success.**
