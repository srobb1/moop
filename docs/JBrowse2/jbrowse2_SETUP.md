# JBrowse2 Integration Setup & Testing Guide

## Overview

This document provides step-by-step instructions for setting up and testing the JBrowse2 integration on a new machine. The setup is designed to work on a single machine initially, with the tracks server simulated locally for testing. In production, the tracks server will run on a separate machine.

---

## Prerequisites

- PHP 7.4+ with command-line support
- Composer (for PHP dependency management)
- OpenSSL (for key generation)
- cURL (for testing)
- git (for version control)
- Access to MOOP source code

---

## Step 1: Install Dependencies

### 1.1 Install Firebase/PHP-JWT

The JWT token generation requires the Firebase JWT library.

```bash
cd /data/moop
composer require firebase/php-jwt
```

**Expected output:**
```
Using version ^6.10 for firebase/php-jwt
./composer.json has been updated
Running composer update firebase/php-jwt
...
```

### 1.2 Verify installation

```bash
php -r "require 'vendor/autoload.php'; echo 'JWT library loaded OK';"
```

**Expected output:**
```
JWT library loaded OK
```

---

## Step 2: Create Directory Structure

### 2.1 Create JBrowse2 configuration directories

```bash
mkdir -p /data/moop/metadata/jbrowse2-configs/tracks
mkdir -p /data/moop/lib/jbrowse
mkdir -p /data/moop/api/jbrowse2
```

### 2.2 Create track data directories

```bash
mkdir -p /data/moop/data/tracks/bigwig
mkdir -p /data/moop/data/tracks/bam
```

### 2.3 Create certificates directory

```bash
mkdir -p /data/moop/certs
```

### 2.4 Verify directory structure

```bash
tree -L 3 /data/moop/lib/jbrowse /data/moop/api/jbrowse2 /data/moop/metadata/jbrowse2-configs /data/moop/certs
```

---

## Step 3: Generate JWT Key Pair

The JWT key pair is used to sign and verify tokens for track access.

### 3.1 Generate private key (2048-bit RSA)

```bash
cd /data/moop/certs
openssl genrsa -out jwt_private_key.pem 2048
```

**Expected output:**
```
Generating RSA private key, 2048 bit long modulus (2 primes)
............+++++
.......+++++
e is 65537 (0x010001)
```

### 3.2 Generate public key from private key

```bash
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
```

**Expected output:**
```
writing RSA key
```

### 3.3 Verify keys

```bash
# Check private key
openssl rsa -in jwt_private_key.pem -check -noout

# Check public key
openssl pkey -in jwt_public_key.pem -text -noout
```

**Expected output:**
```
RSA key ok
```

### 3.4 Set appropriate permissions

```bash
chmod 600 /data/moop/certs/jwt_private_key.pem
chmod 644 /data/moop/certs/jwt_public_key.pem
ls -la /data/moop/certs/
```

---

## Step 4: Create Track Configuration Files

Track configs define which tracks are available and their access levels.

### 4.1 RNA-seq Coverage Track (Public)

Create `/data/moop/metadata/jbrowse2-configs/tracks/rna_seq_coverage.json`:

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

### 4.2 DNA Alignment Track (Admin Only)

Create `/data/moop/metadata/jbrowse2-configs/tracks/dna_alignment.json`:

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

### 4.3 H3K4me3 ChIP-seq Track (Collaborators Only)

Create `/data/moop/metadata/jbrowse2-configs/tracks/chip_seq_h3k4me3.json`:

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

### 4.4 Validate track configs

```bash
for file in /data/moop/metadata/jbrowse2-configs/tracks/*.json; do
  php -r "json_decode(file_get_contents('$file'), true) ?: exit(1);" && echo "✓ $(basename $file)" || echo "✗ $(basename $file)"
done
```

---

## Step 5: Create Test Track Data Files

For testing, we create small dummy files that simulate real track data. In production, these would be real BigWig/BAM files.

### 5.1 Create test BigWig files

```bash
# RNA-seq coverage for Anoura_caudifer
cat > /data/moop/data/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw << 'EOF'
DUMMY_BIGWIG_DATA_FOR_TESTING
This is a test BigWig file simulating real track data.
In production, this would be a real BigWig binary file.
For testing HTTP range requests, we just need any file content.
EOF

# H3K4me3 ChIP-seq for Anoura_caudifer
cat > /data/moop/data/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_h3k4me3.bw << 'EOF'
DUMMY_BIGWIG_H3K4ME3_DATA_FOR_TESTING
Simulating H3K4me3 ChIP-seq coverage track.
EOF

# RNA-seq coverage for Montipora_capitata (Public)
cat > /data/moop/data/tracks/bigwig/Montipora_capitata_HIv3_rna_coverage.bw << 'EOF'
DUMMY_BIGWIG_CORAL_RNA_DATA_FOR_TESTING
EOF
```

### 5.2 Create test BAM files

```bash
# BAM alignment file
cat > /data/moop/data/tracks/bam/Anoura_caudifer_GCA_004027475.1_dna.bam << 'EOF'
DUMMY_BAM_DATA_FOR_TESTING
Simulating BAM alignment file.
EOF

# BAM index file
cat > /data/moop/data/tracks/bam/Anoura_caudifer_GCA_004027475.1_dna.bam.bai << 'EOF'
DUMMY_BAM_INDEX_DATA_FOR_TESTING
Simulating BAM index file (.bai).
EOF
```

### 5.3 Verify test files

```bash
ls -lah /data/moop/data/tracks/bigwig/
ls -lah /data/moop/data/tracks/bam/
```

---

## Step 6: Create PHP Libraries and API Endpoints

### 6.1 Create Track Token Library

Create `/data/moop/lib/jbrowse/track_token.php` with JWT generation and verification functions.

**Key functions:**
- `generateTrackToken($organism, $assembly, $access_level)` - Signs JWT with private key
- `verifyTrackToken($token)` - Verifies JWT signature and expiration
- `isWhitelistedIP()` - Checks if user IP is on internal network

### 6.2 Create Assembly Config API

Create `/data/moop/api/jbrowse2/assembly.php` to generate dynamic JBrowse2 configs.

**What it does:**
1. Validates user permissions via `getAccessibleAssemblies()`
2. Loads track config files from metadata
3. Filters tracks based on user access level
4. Generates JWT tokens for external access
5. Returns complete JBrowse2 config JSON

**Usage:**
```bash
curl "http://127.0.0.1/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1"
```

### 6.3 Create Fake Tracks Server

Create `/data/moop/api/jbrowse2/fake-tracks-server.php` to simulate a separate tracks server.

**What it does:**
1. Validates JWT tokens from query parameter
2. Allows local IPs without token validation
3. Serves track files from `/data/moop/data/tracks/`
4. Supports HTTP range requests (206 Partial Content)

**Usage:**
```bash
curl -H "Range: bytes=0-100" \
  "http://127.0.0.1/api/jbrowse2/fake-tracks-server.php?file=bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw"
```

---

## Step 7: Run Tests

### 7.1 Make test script executable

```bash
chmod +x /data/moop/tests/jbrowse2_integration_test.sh
```

### 7.2 Run basic tests (no web server needed)

```bash
cd /data/moop/tests
bash jbrowse2_integration_test.sh
```

This validates:
- Directory structure
- File existence
- PHP syntax
- JWT key validity
- JSON config validity

### 7.3 Start PHP development server

```bash
cd /data/moop
php -S 127.0.0.1:80 -t . > /tmp/php_server.log 2>&1 &
```

Or with more output:
```bash
php -S 127.0.0.1:80 -t /data/moop
```

**Note:** You may need sudo or use port 8080 instead.

### 7.4 Test API endpoints

```bash
# Test assembly config for public organism (Anoura_caudifer)
curl -s "http://127.0.0.1/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq .

# Test track count
curl -s "http://127.0.0.1/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq '.tracks | length'

# Test tracks server
curl -s "http://127.0.0.1/api/jbrowse2/fake-tracks-server.php?file=bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw" | head -c 50
```

### 7.5 Test HTTP range requests

```bash
# Request specific byte range
curl -H "Range: bytes=0-50" \
  "http://127.0.0.1/api/jbrowse2/fake-tracks-server.php?file=bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw" \
  -v 2>&1 | grep -E "HTTP|Content-Range"
```

---

## Step 8: Understanding Track Access Levels

The system implements three access levels:

### Public Tracks
- Visible to: Anyone (including unauthenticated users)
- Examples: RNA-seq coverage (access_levels includes "Public")
- Used for: Published data, reference annotations

### Collaborator Tracks
- Visible to: Users with specific organism-assembly pair in their session
- Examples: H3K4me3 ChIP-seq (access_levels includes "Collaborator")
- Used for: Unpublished, collaborative work

### Admin (ALL) Tracks
- Visible to: Admin users only (access_level === 'ALL')
- Examples: DNA-seq alignment (access_levels includes "ALL")
- Used for: Raw data, sensitive analyses

### Testing Track Filtering

```bash
# Public user sees: RNA-seq coverage only
curl -s "http://127.0.0.1/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq '.tracks[].name'

# Expected output: ["RNA-seq Coverage"]
```

---

## Step 9: Prepare for Production Deployment

When deploying to a separate tracks server:

### 9.1 Copy files to tracks server

```bash
# On MOOP server
scp /data/moop/certs/jwt_public_key.pem tracks-admin@tracks-server.example.com:/etc/tracks-server/

scp /data/moop/api/jbrowse2/fake-tracks-server.php tracks-admin@tracks-server.example.com:/var/www/tracks/verify-token.php

# Copy track data
rsync -av /data/moop/data/tracks/ tracks-admin@tracks-server.example.com:/var/www/tracks/data/
```

### 9.2 Configure nginx on tracks server

Use the nginx config from `notes/jbrowse2_integration_plan.md`:
- Enable token validation with `auth_request`
- Support HTTP range requests with `Accept-Ranges`
- Proxy to PHP token validation endpoint

### 9.3 Configure Apache on tracks server (alternative)

Use the Apache config from `notes/jbrowse2_quick_reference.md`:
- Two methods: `mod_auth_request` (modern) or `RewriteRule` (compatible)
- Enable range request support
- Validate tokens before serving files

---

## Troubleshooting

### JWT Library Not Found

**Error:** `Fatal error: Uncaught Error: Class 'Firebase\JWT\JWT' not found`

**Solution:**
```bash
cd /data/moop
composer install
# or
composer require firebase/php-jwt
```

### Keys Not Found

**Error:** `JWT private key not found at: /data/moop/certs/jwt_private_key.pem`

**Solution:**
```bash
# Regenerate keys
cd /data/moop/certs
openssl genrsa -out jwt_private_key.pem 2048
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
```

### Track Config JSON Invalid

**Error:** `INVALID JSON: track_name.json`

**Solution:**
```bash
# Check syntax
php -r "var_dump(json_decode(file_get_contents('/path/to/file.json')));"

# Use jq to validate
jq . /path/to/file.json
```

### API Returns Empty Tracks

**Problem:** Assembly config returns empty tracks array

**Debugging:**
1. Check user has access: `getAccessibleAssemblies($org, $asm)`
2. Check track configs exist: `ls /data/moop/metadata/jbrowse2-configs/tracks/`
3. Check track files exist: `ls /data/moop/data/tracks/bigwig/`
4. Check permissions: `is_readable()` on all files

### HTTP Range Requests Not Working

**Problem:** `curl -H "Range: bytes=0-100" ...` returns 200 instead of 206

**Solution:**
1. Verify tracks server returns proper headers
2. Check PHP file handles `$_SERVER['HTTP_RANGE']`
3. Ensure nginx/Apache has `add_header Accept-Ranges bytes;`

---

## Step 12: Create JBrowse2 Frontend

The JBrowse2 frontend is a static React application that serves as the genome browser UI.

### Installation

```bash
cd /data/moop
jbrowse create jbrowse2
```

### Directory Structure

```
/data/moop/jbrowse2/
├── package.json         ✓ TRACK in git (dependencies)
├── package-lock.json    ✓ TRACK in git (lock versions)
├── config.json          ✓ TRACK in git (MOOP integration config)
├── public/              ✓ TRACK in git (static HTML/CSS/JS)
├── node_modules/        ✗ IGNORE (auto-installed by npm)
├── dist/                ✗ IGNORE (compiled output)
└── .env                 ✗ IGNORE (secrets)
```

### Git Configuration

Add to `/data/moop/.gitignore`:

```gitignore
# JBrowse2 frontend (track config separately, ignore dependencies)
/jbrowse2/node_modules/
/jbrowse2/dist/
/jbrowse2/.env

# Track data (too large, installation-specific)
/data/tracks/
```

**Why:**
- Track `package.json` to enable `npm install` on new machines
- Track `config.json` because it contains MOOP-specific API endpoints
- Ignore `node_modules/` (500MB+, auto-generated)
- Ignore `/data/tracks/` (large data files)

### File Manifest

This is the complete list of files created for this setup:

```
/data/moop/
├── jbrowse2/                         (Created by: jbrowse create jbrowse2)
│   ├── package.json                  ✓ TRACK
│   ├── package-lock.json             ✓ TRACK
│   ├── config.json                   ✓ TRACK (API integration)
│   ├── public/                       ✓ TRACK
│   └── node_modules/                 ✗ IGNORE
├── certs/
│   ├── jwt_private_key.pem           (generated via openssl)
│   └── jwt_public_key.pem            (generated via openssl)
├── lib/jbrowse/
│   └── track_token.php               (JWT token generation & validation)
├── api/jbrowse2/
│   ├── assembly.php                  (Dynamic config generator)
│   ├── test-assembly.php             (Test API)
│   └── fake-tracks-server.php        (Test tracks server)
├── metadata/jbrowse2-configs/tracks/
│   ├── rna_seq_coverage.json         (Track config: Public)
│   ├── dna_alignment.json            (Track config: Admin only)
│   └── chip_seq_h3k4me3.json         (Track config: Collaborator)
├── data/tracks/                      ✗ IGNORE (data files)
│   ├── bigwig/
│   └── bam/
└── tests/
    └── jbrowse2_integration_test.sh   (Comprehensive test suite)
```

### Deployment on New Machine

```bash
# Clone repo
git clone <repo>
cd /data/moop

# Install JBrowse2 dependencies
cd jbrowse2
npm install

# node_modules is automatically installed from package-lock.json
# Everything else is in git
```

---

## Next Steps

1. **Run the test suite:** `bash /data/moop/tests/jbrowse2_integration_test.sh`
2. **Create JBrowse2 frontend:** `jbrowse create jbrowse2`
3. **Configure JBrowse2:** Update `config.json` to point to `/api/jbrowse2/assembly.php`
4. **Start web server:** `php -S 127.0.0.1:8888 -t /data/moop`
5. **Test end-to-end:** Load JBrowse2 in browser and test with different access levels
6. **Review the architecture:** Read `notes/jbrowse2_integration_plan.md`
7. **Plan production deployment:** Set up separate tracks server using nginx/Apache configs

---

## References

- **Architecture Guide:** `/data/moop/notes/jbrowse2_integration_plan.md`
- **Quick Reference:** `/data/moop/notes/jbrowse2_quick_reference.md`
- **Firebase JWT Library:** https://github.com/firebase/php-jwt
- **JBrowse2 Documentation:** https://jbrowse.org/jb2/docs/
