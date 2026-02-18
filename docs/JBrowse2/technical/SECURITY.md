# MOOP JBrowse2 Security Architecture

**Version:** 3.1  
**Last Updated:** February 18, 2026  
**Status:** Production - RS256 JWT Authentication

**For:** JBrowse2 Community & Security Auditors

---

## Recent Security Updates (2026-02-18)

**Critical improvements implemented:**

1. **Enhanced IP Whitelist Security**
   - All users (including whitelisted IPs) now require JWT tokens
   - Whitelisted IPs: Relaxed expiry checking (can use expired tokens)
   - All IPs: Organism/assembly claims always validated
   - Prevents unauthorized access by file path guessing

2. **External URL Token Protection**
   - External URLs (`https://`, `http://`, `ftp://`) never get tokens added
   - Prevents JWT token leakage to external servers
   - Enables safe use of public reference data (UCSC, Ensembl, NCBI)

**Security benefits:** Defense-in-depth for all users, audit trail preserved, no token leakage.

---

## Executive Summary

MOOP implements a multi-layered security architecture for JBrowse2 that provides fine-grained access control to genomic data. The system combines session-based user authentication with stateless JWT tokens for track file access, enabling secure data sharing with external collaborators while maintaining strict permission boundaries.

**Key Features:**
- ✅ RS256 asymmetric JWT signatures (2048-bit RSA)
- ✅ Per-assembly, per-user token scoping
- ✅ Dynamic configuration generation with permission filtering
- ✅ Stateless tracks server (no database required)
- ✅ HTTP range request support for efficient data streaming
- ✅ IP-based whitelisting with relaxed expiry (2026-02-18 update)
- ✅ External URL protection (no token leakage)

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Authentication System](#authentication-system)
3. [Dynamic Configuration Generation](#dynamic-configuration-generation)
4. [JWT Token System](#jwt-token-system)
5. [Track File Server](#track-file-server)
6. [Security Model](#security-model)
7. [Deployment Guide](#deployment-guide)

---

## Architecture Overview

### System Components

```
┌──────────────────────────────────────────────────────────────┐
│                     MOOP Web Server                          │
│              (moop.example.com / Port 443)                   │
├──────────────────────────────────────────────────────────────┤
│  • PHP Session-based Authentication                          │
│  • User Access Control (PUBLIC/COLLABORATOR/IP_IN_RANGE)    │
│  • Dynamic JBrowse2 Config Generation (config.php)          │
│  • JWT Token Generation (RS256, private key)                │
│  • JBrowse2 Web Interface (React)                           │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 │ HTTPS (Config API Calls)
                 ↓
┌──────────────────────────────────────────────────────────────┐
│              JBrowse2 Client (Browser)                       │
├──────────────────────────────────────────────────────────────┤
│  • Fetches filtered config from config.php                   │
│  • Config includes JWT tokens embedded in track URLs         │
│  • Makes authenticated track data requests                   │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 │ HTTPS (Track Data Requests with JWT tokens)
                 ↓
┌──────────────────────────────────────────────────────────────┐
│                  Tracks Server                               │
│          (tracks.example.com OR localhost:8888)              │
├──────────────────────────────────────────────────────────────┤
│  • JWT Token Validation (RS256, public key only)            │
│  • Organism/Assembly Permission Verification                 │
│  • Track File Serving (tracks.php)                          │
│  • HTTP Range Request Support                               │
│  • NO database, NO sessions - fully stateless               │
└──────────────────────────────────────────────────────────────┘
```

### Security Layers

The system implements defense-in-depth with four security layers:

**Layer 1: Session Authentication**
- PHP session-based login required for restricted content
- Access levels: `PUBLIC` < `COLLABORATOR` < `IP_IN_RANGE` < `ADMIN`
- IP-based auto-authentication for internal networks

**Layer 2: Assembly Filtering**
- Config API filters assemblies by `defaultAccessLevel` metadata
- Users only see assemblies they're authorized to access
- COLLABORATOR users verified against specific assembly permissions

**Layer 3: Track Filtering**  
- Track metadata defines `access_level` (PUBLIC/COLLABORATOR/etc.)
- Config API filters tracks during configuration generation
- Only authorized tracks included in JBrowse2 config

**Layer 4: JWT Track Authentication**
- Each track URL includes cryptographically signed JWT token
- Tokens scoped to specific organism/assembly pair
- Tracks server validates token before serving any file
- 1-hour expiration enforces re-authentication

---

## Authentication System

### PHP Session-Based Authentication

MOOP uses standard PHP sessions for user authentication with access control integration.

#### Session Variables

```php
$_SESSION['logged_in']     // boolean - Authentication status
$_SESSION['username']      // string - User identifier
$_SESSION['access_level']  // string - "PUBLIC", "COLLABORATOR", "IP_IN_RANGE", "ADMIN"
$_SESSION['is_admin']      // boolean - Administrative privileges
$_SESSION['access']        // array - Organism/assembly permissions
                           //   Example: ['Organism_A' => ['GCA_001', 'GCA_002']]
```

#### Access Level Hierarchy

| Level | Value | Description | Sees |
|-------|-------|-------------|------|
| **ADMIN** | 4 | Full system access | Everything |
| **IP_IN_RANGE** | 3 | Auto-authenticated internal IPs | Everything (no JWT tokens needed) |
| **COLLABORATOR** | 2 | Authenticated external users | PUBLIC + explicitly granted assemblies |
| **PUBLIC** | 1 | Anonymous users | Only PUBLIC assemblies/tracks |

#### IP-Based Auto-Authentication

Internal network IPs automatically log in with `IP_IN_RANGE` access:

```php
// File: includes/access_control.php

$ip_ranges = [
    ['start' => '10.0.0.0', 'end' => '10.255.255.255'],      // Private Class A
    ['start' => '172.16.0.0', 'end' => '172.31.255.255'],    // Private Class B
    ['start' => '192.168.0.0', 'end' => '192.168.255.255'],  // Private Class C
    ['start' => '127.0.0.1', 'end' => '127.255.255.255']     // Localhost
];

// Auto-login matching IPs
if (ip_in_range($_SERVER['REMOTE_ADDR'], $ip_ranges)) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = "IP_USER_{$_SERVER['REMOTE_ADDR']}";
    $_SESSION['access_level'] = 'IP_IN_RANGE';
}
```

**Benefits:**
- ✅ Internal researchers get automatic access (no manual login)
- ✅ Relaxed token expiry (can use expired tokens for convenience)
- ✅ Still protected by JWT organism/assembly validation
- ✅ Full audit trail (user_id in tokens)
- ✅ External collaborators get strict enforcement

**Security Update (2026-02-18):**
- IP whitelisted users now ALWAYS get JWT tokens with organism/assembly claims
- Tokens required for all track requests (defense-in-depth)
- Whitelisted IPs benefit: Can use expired tokens (no 1-hour limit)
- Prevents unauthorized access by file path guessing

---

## Dynamic Configuration Generation

### How MOOP Generates JBrowse2 Configs

Unlike traditional JBrowse2 deployments with static `config.json` files, MOOP generates configurations dynamically per-request based on user permissions.

### Configuration API Endpoint

**File:** `api/jbrowse2/config.php`

**Dual-Mode Operation:**

1. **Assembly List Mode** (no parameters)
   ```
   GET /moop/api/jbrowse2/config.php
   ```
   Returns: List of assemblies user can access
   
2. **Full Config Mode** (with organism/assembly)
   ```
   GET /moop/api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
   ```
   Returns: Complete JBrowse2 config with all accessible tracks + JWT tokens

### Metadata-Driven System

All assemblies and tracks are defined in JSON metadata files:

**Assembly Metadata:**
```
/metadata/jbrowse2-configs/assemblies/
├── Nematostella_vectensis_GCA_033964005.1.json
├── Organism_name_Assembly_ID.json
└── ...
```

**Track Metadata:**
```
/metadata/jbrowse2-configs/tracks/
├── Organism_name/
│   ├── Assembly_ID/
│   │   ├── bigwig/
│   │   │   └── track1.json
│   │   ├── bam/
│   │   │   └── track2.json
│   │   └── gff/
│   │       └── track3.json
└── synteny/  (for dual-assembly comparisons)
```

### Assembly Definition Example

```json
{
    "name": "Nematostella_vectensis_GCA_033964005.1",
    "displayName": "Nematostella vectensis (GCA_033964005.1)",
    "organism": "Nematostella_vectensis",
    "assemblyId": "GCA_033964005.1",
    "aliases": ["GCA_033964005.1", "Nvec200"],
    "defaultAccessLevel": "PUBLIC",
    "sequence": {
        "type": "ReferenceSequenceTrack",
        "trackId": "Nematostella_vectensis_GCA_033964005.1-ReferenceSequenceTrack",
        "adapter": {
            "type": "IndexedFastaAdapter",
            "fastaLocation": {
                "uri": "/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/reference.fasta",
                "locationType": "UriLocation"
            },
            "faiLocation": {
                "uri": "/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/reference.fasta.fai",
                "locationType": "UriLocation"
            }
        }
    }
}
```

### Track Definition Example

```json
{
    "trackId": "track_e1f2d5134e",
    "name": "RNA-Seq Coverage",
    "assemblyNames": ["Nematostella_vectensis_GCA_033964005.1"],
    "category": ["RNA-Seq", "Coverage"],
    "type": "QuantitativeTrack",
    "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
            "uri": "/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/sample.bw",
            "locationType": "UriLocation"
        }
    },
    "metadata": {
        "access_level": "COLLABORATOR",
        "description": "RNA-Seq coverage from experiment X"
    }
}
```

### Permission Filtering Logic

**File:** `api/jbrowse2/config.php` - Function: `loadFilteredTracks()`

```php
// Access hierarchy for comparison
$access_hierarchy = [
    'ADMIN' => 4,
    'IP_IN_RANGE' => 3,
    'COLLABORATOR' => 2,
    'PUBLIC' => 1
];

$user_level_value = $access_hierarchy[$user_access_level] ?? 0;

foreach ($track_files as $track_file) {
    $track_def = json_decode(file_get_contents($track_file), true);
    $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
    $track_level_value = $access_hierarchy[$track_access_level] ?? 1;
    
    // Check 1: User must meet minimum access level
    if ($user_level_value < $track_level_value) {
        continue; // Skip this track
    }
    
    // Check 2: COLLABORATOR users need explicit assembly permission
    if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
        $user_access = $_SESSION['access'] ?? [];
        if (!isset($user_access[$organism]) || 
            !in_array($assembly, (array)$user_access[$organism])) {
            continue; // Skip - no explicit permission
        }
    }
    
    // Track passed permission checks - add JWT token and include
    $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly, 
                                          $user_access_level, $is_whitelisted);
    $filtered_tracks[] = $track_with_tokens;
}
```

**Key Points:**
- ✅ Tracks filtered BEFORE config sent to client
- ✅ Users cannot request tracks they shouldn't see
- ✅ COLLABORATOR users must have explicit assembly access in `$_SESSION['access']`
- ✅ IP whitelisted users bypass JWT token generation

---

## JWT Token System

### Purpose

JWT tokens authenticate individual track file requests to the tracks server. This enables:
- **Stateless authentication** - Tracks server needs no database/sessions
- **Distributed architecture** - Multiple tracks servers can validate independently  
- **Time-limited access** - Tokens expire after 1 hour
- **Scope restriction** - Tokens locked to specific organism/assembly

### RS256 Asymmetric Cryptography

**Algorithm:** RSA with SHA-256 (2048-bit keys)

**Key Files:**
- `/data/moop/certs/jwt_private_key.pem` - Private key (signs tokens, MOOP server only)
- `/data/moop/certs/jwt_public_key.pem` - Public key (verifies tokens, tracks servers)

**Security Model:**
```
┌─────────────────────┐
│   MOOP Server       │
│                     │
│  Private Key        │────> Signs JWT tokens
│  (kept secret)      │      (only MOOP can create valid tokens)
└─────────────────────┘

┌─────────────────────┐
│  Tracks Server 1    │
│                     │
│  Public Key         │────> Verifies JWT tokens  
│  (can be shared)    │      (cannot create tokens)
└─────────────────────┘

┌─────────────────────┐
│  Tracks Server 2    │
│                     │
│  Public Key         │────> Verifies JWT tokens
│  (same public key)  │      (cannot create tokens)
└─────────────────────┘
```

**Why RS256 (not HS256)?**
- ✅ Compromised tracks server cannot forge tokens (needs private key to sign)
- ✅ Public key can be deployed to multiple servers safely
- ✅ Limited blast radius if tracks server hacked
- ✅ Industry best practice for distributed systems

### Token Structure

**Claims (payload):**
```json
{
    "user_id": "researcher123",
    "organism": "Nematostella_vectensis",
    "assembly": "GCA_033964005.1",
    "access_level": "COLLABORATOR",
    "iat": 1708280000,
    "exp": 1708283600
}
```

| Claim | Purpose |
|-------|---------|
| `user_id` | Username for audit logging |
| `organism` | Restricts token to specific organism |
| `assembly` | Restricts token to specific assembly |
| `access_level` | User's permission tier |
| `iat` | Issued At timestamp (debugging/logging) |
| `exp` | Expiration timestamp (1 hour from issue) |

### Token Generation

**File:** `lib/jbrowse/track_token.php` - Function: `generateTrackToken()`

```php
function generateTrackToken($organism, $assembly, $access_level) {
    $private_key_path = '/data/moop/certs/jwt_private_key.pem';
    $private_key = file_get_contents($private_key_path);
    
    $token_data = [
        'user_id' => $_SESSION['username'] ?? 'anonymous',
        'organism' => $organism,
        'assembly' => $assembly,
        'access_level' => $access_level,
        'iat' => time(),
        'exp' => time() + 3600  // 1 hour
    ];
    
    // Sign with RS256 using private key
    $jwt = JWT::encode($token_data, $private_key, 'RS256');
    return $jwt;
}
```

### Token Embedding in Track URLs

Tokens are appended as query parameters to MOOP-hosted track URIs during config generation.

**Important:** External URLs (`https://`, `http://`, `ftp://`) are never modified to prevent token leakage.

```php
// MOOP-hosted track URI
"uri": "/moop/data/tracks/Organism/Assembly/bigwig/sample.bw"

// After token injection (all users get tokens now)
"uri": "/moop/api/jbrowse2/tracks.php?file=Organism%2FAssembly%2Fbigwig%2Fsample.bw&token=eyJhbGc..."

// External public track URI
"uri": "https://hgdownload.soe.ucsc.edu/goldenPath/hg38/data.bw"

// After processing (unchanged - no token added)
"uri": "https://hgdownload.soe.ucsc.edu/goldenPath/hg38/data.bw"
```

**File:** `api/jbrowse2/config.php` - Function: `addTokenToAdapterUrls()`

```php
function addTokenToAdapterUrls($adapter, $token) {
    foreach ($adapter as $key => &$value) {
        if (is_array($value)) {
            if (isset($value['uri']) && !empty($value['uri'])) {
                $uri = $value['uri'];
                
                // CASE 1: External URLs - DO NOT add tokens (security!)
                if (preg_match('#^(https?|ftp)://#i', $uri)) {
                    continue; // Leave unchanged
                }
                
                // CASE 2: MOOP tracks - Route through tracks.php with token
                if (preg_match('#^/moop/data/tracks/(.+)$#', $uri, $matches)) {
                    $file_path = $matches[1];
                    $value['uri'] = '/moop/api/jbrowse2/tracks.php?file=' . urlencode($file_path);
                    $value['uri'] .= '&token=' . urlencode($token);
                }
                
                // CASE 3: Other MOOP paths - Add token
                elseif (preg_match('#^/moop/#', $uri)) {
                    $separator = strpos($uri, '?') !== false ? '&' : '?';
                    $value['uri'] .= $separator . 'token=' . urlencode($token);
                }
            } else {
                // Recurse into nested adapter structures
                $value = addTokenToAdapterUrls($value, $token);
            }
        }
    }
    return $adapter;
}
```

**Security Note:** All users (including IP-whitelisted) now receive JWT tokens. Whitelisted IPs benefit from relaxed expiry validation but still require valid organism/assembly claims.

### Token Verification

**File:** `lib/jbrowse/track_token.php` - Function: `verifyTrackToken()`

```php
function verifyTrackToken($token) {
    $public_key_path = '/data/moop/certs/jwt_public_key.pem';
    $public_key = file_get_contents($public_key_path);
    
    try {
        // Verify signature using public key (RS256)
        $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
        
        // Check expiration
        if ($decoded->exp < time()) {
            return false;  // Token expired
        }
        
        return $decoded;  // Valid - return claims
        
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}
```

**Validation Steps:**
1. ✅ Cryptographic signature verification (RS256 with public key)
2. ✅ Expiration check (`exp` < current time)
3. ✅ Claims validation (organism/assembly match requested file)

---

## Track File Server

### Single Secure Endpoint

**File:** `api/jbrowse2/tracks.php`

This endpoint handles ALL track file serving with security validation.

**URL Pattern:**
```
GET /moop/api/jbrowse2/tracks.php?file=<path>&token=<jwt>
```

**Examples:**
```
/moop/api/jbrowse2/tracks.php?file=Nematostella_vectensis/GCA_033964005.1/bigwig/sample.bw&token=eyJ...
/moop/api/jbrowse2/tracks.php?file=Organism/Assembly/bam/reads.bam&token=eyJ...
```

### Security Validation Flow

**Updated 2026-02-18:** All users now require JWT tokens with organism/assembly validation.

```php
// 1. VALIDATE FILE PARAMETER
if (empty($file) || strpos($file, '..') !== false) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid file path']));
}

// 2. CHECK IP WHITELIST (for relaxed validation, not bypass)
$is_whitelisted = isWhitelistedIP();

// 3. ALWAYS REQUIRE JWT TOKEN (even for whitelisted IPs)
if (empty($token)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Authentication required']));
}

// 4. VALIDATE JWT TOKEN
$token_data = verifyTrackToken($token);

if (!$token_data) {
    // Token invalid or expired
    if ($is_whitelisted) {
        // WHITELISTED IP RELAXATION: Allow expired tokens
        // Internal users don't need to worry about 1-hour expiry
        // But token must still be structurally valid
        try {
            $public_key = file_get_contents('/certs/jwt_public_key.pem');
            $token_data = JWT::decode($token, new Key($public_key, 'RS256'));
            error_log("Whitelisted IP using expired token - allowed");
        } catch (Exception $e) {
            http_response_code(403);
            exit(json_encode(['error' => 'Invalid token structure']));
        }
    } else {
        // NON-WHITELISTED IPs: Strict enforcement
        http_response_code(403);
        exit(json_encode(['error' => 'Invalid or expired token']));
    }
}

// 5. ALWAYS VALIDATE FILE PATH MATCHES TOKEN PERMISSIONS
// This prevents access by path guessing, even for whitelisted IPs
$file_parts = explode('/', $file);

if (count($file_parts) < 2) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid file path format']));
}

$file_organism = $file_parts[0];
$file_assembly = $file_parts[1];

// Token must grant access to THIS specific organism/assembly
if ($token_data->organism !== $file_organism || 
    $token_data->assembly !== $file_assembly) {
    http_response_code(403);
    exit(json_encode([
        'error' => 'Token does not grant access to this file',
        'token_scope' => "{$token_data->organism}/{$token_data->assembly}",
        'requested_file' => "$file_organism/$file_assembly"
    ]));
    error_log("Token scope mismatch: {$token_data->user_id} tried to access $file");
}

// 6. SERVE FILE (with range request support)
$file_path = $TRACKS_BASE_DIR . '/' . $file;
serveFileWithRangeSupport($file_path);
```

**Key Security Properties:**
- ✅ All users require tokens (no bypass for whitelisted IPs)
- ✅ Whitelisted IPs can use expired tokens (convenience)
- ✅ Organism/assembly claims always validated (defense-in-depth)
- ✅ Audit trail with user_id in all tokens
- ✅ Prevents unauthorized access by file path guessing
```

### HTTP Range Request Support

Critical for JBrowse2 performance - enables seeking in large files:

```php
// Parse Range header: "bytes=0-1000"
$range_header = $_SERVER['HTTP_RANGE'] ?? '';

if (preg_match('/bytes=(\d+)-(\d*)/', $range_header, $matches)) {
    $start = (int)$matches[1];
    $end = !empty($matches[2]) ? (int)$matches[2] : $file_size - 1;
    $length = $end - $start + 1;
    
    // Send HTTP 206 Partial Content
    http_response_code(206);
    header('Accept-Ranges: bytes');
    header("Content-Range: bytes $start-$end/$file_size");
    header("Content-Length: $length");
    
    $fp = fopen($file_path, 'rb');
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
}
```

**Why This Matters:**
- ✅ JBrowse2 only fetches needed byte ranges (efficient)
- ✅ BigWig/BAM files support indexed random access
- ✅ Dramatically reduces bandwidth for sparse viewing

### File Path Structure

**Required format:**
```
organism/assembly/[subdirectories...]/filename
```

**Validation rules:**
- ✅ First two components MUST be organism/assembly (validated against JWT)
- ✅ After assembly, any subdirectory structure allowed
- ✅ No `..` directory traversal allowed
- ✅ Paths are relative to `$TRACKS_BASE_DIR`

**Examples:**
```
✅ Nematostella_vectensis/GCA_033964005.1/bigwig/sample.bw
✅ Nematostella_vectensis/GCA_033964005.1/experiment2/data.bam
✅ Organism_A/Assembly_1/project/subfolder/track.vcf.gz
❌ ../../../etc/passwd
❌ Organism_A/track.bw  (missing assembly component)
```

---

## Security Model

### Threat Model & Mitigations

| Threat | Risk | Mitigation | Status |
|--------|------|------------|--------|
| **Unauthorized assembly access** | High | Session auth + permission filtering | ✅ Implemented |
| **Token forgery** | Critical | RS256 asymmetric signatures | ✅ Implemented |
| **Token replay attack** | Medium | 1-hour expiry + HTTPS only | ✅ Implemented |
| **Token reuse across assemblies** | High | Organism/assembly claims validation | ✅ Implemented |
| **Token theft** | Medium | HTTPS, HttpOnly cookies, short expiry | ✅ Implemented |
| **Directory traversal** | Critical | Path validation, `..` blocking | ✅ Implemented |
| **Compromised tracks server** | High | Public key only (can't forge tokens) | ✅ Mitigated |
| **Session hijacking** | High | Secure cookies, session regeneration | ⚠️ Needs review |
| **Brute force attacks** | Low | Rate limiting | ❌ Not implemented |
| **XSS/CSRF** | Medium | Input sanitization, CSP headers | ⚠️ Needs review |

### Attack Scenarios

#### Scenario 1: User Tries to Access Restricted Track

**Attack:** User manually crafts URL to restricted track file

**Defense:**
1. Track not included in config (filtered at Layer 3)
2. Even if URL guessed, JWT token required
3. Token must be valid and grant access to that organism/assembly
4. Token expires after 1 hour

**Result:** ❌ Access denied

#### Scenario 2: Tracks Server Compromise

**Attack:** Attacker gains root access to tracks server

**What attacker gets:**
- ✅ Public key only (can verify tokens)
- ✅ Track files on that server

**What attacker CANNOT do:**
- ❌ Forge new tokens (needs private key from MOOP server)
- ❌ Access other organisms/assemblies (tokens are scoped)
- ❌ Create persistent access (tokens expire hourly)

**Blast radius:** Limited to single tracks server

#### Scenario 3: JWT Token Stolen

**Attack:** Token intercepted or leaked

**Limitations:**
- Token only valid for 1 organism/assembly pair
- Token expires in 1 hour
- HTTPS prevents interception
- Cannot be used for other assemblies

**Response:** User re-authenticates to get fresh token

### Key Rotation Procedure

**When to rotate:**
- Annually (best practice)
- Immediately if private key compromised
- Before/after major security audits

**Steps:**

```bash
# 1. Generate new RSA key pair (4096-bit recommended for new keys)
cd /data/moop/certs
openssl genrsa -out jwt_private_key_new.pem 4096
openssl rsa -in jwt_private_key_new.pem -pubout -out jwt_public_key_new.pem

# 2. Set proper permissions
chmod 600 jwt_private_key_new.pem
chmod 644 jwt_public_key_new.pem

# 3. Deploy new public key to ALL tracks servers
for server in tracks1 tracks2 tracks3; do
    scp jwt_public_key_new.pem admin@$server:/etc/tracks-server/jwt_public_key.pem
done

# 4. Backup old keys
mv jwt_private_key.pem jwt_private_key_$(date +%Y%m%d).pem.bak
mv jwt_public_key.pem jwt_public_key_$(date +%Y%m%d).pem.bak

# 5. Activate new keys
mv jwt_private_key_new.pem jwt_private_key.pem
mv jwt_public_key_new.pem jwt_public_key.pem

# 6. Restart services (all existing tokens invalidated)
sudo systemctl restart php-fpm  # MOOP server
# Users will automatically get new tokens on next config request
```

**Impact:**  
- ⚠️ All existing tokens immediately invalid
- ✅ Users automatically get new tokens when config refreshes
- ✅ No database/session changes needed

---

## Deployment Guide

### Development Setup (Single Server)

**Use case:** Testing, development, small deployments

```
┌──────────────────────────────────┐
│     Single Server                │
│                                  │
│  MOOP + Tracks Server            │
│  • Private key: signs tokens     │
│  • Public key: verifies tokens   │
│  • Track files served locally    │
│  • IP whitelist bypasses JWT     │
└──────────────────────────────────┘
```

**Configuration:**
- JWT keys in `/data/moop/certs/`
- Track files in `/data/moop/data/tracks/`
- `tracks.php` validates using local public key
- Internal IPs auto-authenticated (no tokens)

### Production Setup (Separate Tracks Server)

**Use case:** External collaborators, high security, scalability

```
┌─────────────────────┐         ┌─────────────────────┐
│   MOOP Server       │         │  Tracks Server 1    │
│                     │         │                     │
│  Private key only   │────────>│  Public key only    │
│  Generates tokens   │  HTTPS  │  Validates tokens   │
│  No track files     │         │  Serves files       │
└─────────────────────┘         └─────────────────────┘
                                
                                ┌─────────────────────┐
                                │  Tracks Server 2    │
                                │                     │
                                │  Public key only    │
                                │  Validates tokens   │
                                │  Serves files       │
                                └─────────────────────┘
```

**Setup steps:**

1. **Generate keys on MOOP server** (if not already done):
```bash
cd /data/moop/certs
openssl genrsa -out jwt_private_key.pem 2048
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
chmod 600 jwt_private_key.pem
chmod 644 jwt_public_key.pem
```

2. **Deploy public key to tracks servers**:
```bash
scp /data/moop/certs/jwt_public_key.pem tracks-admin@tracks1.example.com:/etc/tracks-server/
scp /data/moop/certs/jwt_public_key.pem tracks-admin@tracks2.example.com:/etc/tracks-server/
```

3. **Update track URLs in assembly metadata**:
```json
{
    "sequence": {
        "adapter": {
            "fastaLocation": {
                "uri": "https://tracks1.example.com/data/Organism/Assembly/reference.fasta"
            }
        }
    }
}
```

4. **Configure CORS on tracks servers**:
```apache
# Apache config
Header always set Access-Control-Allow-Origin "https://moop.example.com"
Header always set Access-Control-Allow-Methods "GET, HEAD, OPTIONS"
Header always set Access-Control-Allow-Headers "Range"
Header always set Access-Control-Expose-Headers "Content-Range, Content-Length, Accept-Ranges"
```

5. **Deploy `tracks.php` to tracks servers** (see TRACKS_SERVER_IT_SETUP.md)

### Security Checklist

**MOOP Server:**
- [ ] Private key permissions: `chmod 600 jwt_private_key.pem`
- [ ] Keys stored outside web root
- [ ] HTTPS enabled with valid certificate
- [ ] Session cookies: HttpOnly, Secure, SameSite=Lax
- [ ] Session timeout configured (recommended: 1-4 hours)
- [ ] IP whitelist configured for internal network
- [ ] Error logging enabled (not exposed to users)
- [ ] PHP version up to date (7.4+ required)
- [ ] `firebase/php-jwt` library installed via Composer

**Tracks Servers:**
- [ ] Public key deployed: `/etc/tracks-server/jwt_public_key.pem`
- [ ] Public key permissions: `chmod 644`
- [ ] `tracks.php` endpoint deployed
- [ ] CORS headers configured
- [ ] HTTPS enabled
- [ ] No database/session access (stateless)
- [ ] HTTP range requests enabled
- [ ] Error logging enabled
- [ ] Time synchronization (NTP) - critical for JWT expiry

**Network:**
- [ ] HTTPS enforced (redirect HTTP -> HTTPS)
- [ ] Firewall rules: MOOP + tracks servers on port 443
- [ ] Internal network IP ranges defined
- [ ] DNS configured for tracks servers

---

## Monitoring & Incident Response

### Security Logs

**MOOP Server:**
```bash
# JWT generation errors
grep "Failed to generate token" /var/log/php-error.log

# Permission denials
grep "Access denied" /var/log/apache2/moop-error.log
```

**Tracks Server:**
```bash
# Token validation failures
grep "JWT verification failed" /var/log/php-error.log
grep "Invalid or expired token" /var/log/php-error.log

# Suspicious patterns
grep "403" /var/log/apache2/tracks-access.log | tail -50
```

### Alert Conditions

**Immediate investigation:**
- ⚠️ Spike in 403 errors from single IP (brute force attempt)
- ⚠️ JWT verification failures with valid-looking tokens (key mismatch)
- ⚠️ Directory traversal attempts (`..` in file paths)
- ⚠️ Requests for non-existent organisms/assemblies (reconnaissance)

**Routine monitoring:**
- Track most accessed files (bandwidth optimization)
- Token expiry patterns (consider refresh endpoint)
- IP-based access vs authenticated access ratio

### Incident Response: Key Compromise

**If private key compromised:**

```bash
# IMMEDIATE: Generate new keys
cd /data/moop/certs
openssl genrsa -out jwt_private_key.pem 4096
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
chmod 600 jwt_private_key.pem

# Deploy new public key to ALL tracks servers
for server in $(cat /etc/tracks-servers.txt); do
    scp jwt_public_key.pem admin@$server:/etc/tracks-server/
done

# Restart services (invalidates all tokens)
sudo systemctl restart php-fpm

# Audit access logs for suspicious activity
grep -E "$(date -d '7 days ago' +%Y-%m-%d)" /var/log/apache2/tracks-access.log | \
    grep "200" | awk '{print $1}' | sort | uniq -c | sort -rn

# Notify users of security incident (if warranted)
```

---

## FAQ

**Q: Can users share JBrowse2 URLs with others?**  
A: URLs contain JWT tokens that expire in 1 hour. External users: shared links stop working after expiry. Internal (whitelisted) users: links work beyond 1 hour. For persistent sharing, create a PUBLIC assembly.

**Q: How do I grant a collaborator access to a specific assembly?**  
A: Edit their user record to add organism/assembly to `$_SESSION['access']` array. Set assembly's `defaultAccessLevel` to `COLLABORATOR` or higher.

**Q: What happens if clocks are out of sync?**  
A: JWT `exp` claim validation will fail for external users. Whitelisted IPs can still use expired tokens. Ensure NTP is running on all servers. Acceptable clock skew: ±30 seconds.

**Q: Can I increase token expiry beyond 1 hour?**  
A: Yes, edit `track_token.php` line: `'exp' => time() + 7200` for 2 hours. Longer expiry = larger security window if token stolen. Whitelisted IPs aren't affected by expiry.

**Q: Do whitelisted IPs still need JWT tokens?**  
A: Yes (as of 2026-02-18). All users get tokens with organism/assembly claims. Whitelisted IPs benefit: can use expired tokens. This prevents unauthorized access by file path guessing.

**Q: Can I use external URLs for tracks (UCSC, Ensembl)?**  
A: Yes! External URLs (`https://`, `http://`, `ftp://`) are left unchanged. No JWT tokens added. Perfect for public reference data.

**Q: How do I add a new tracks server?**  
A: Copy public key to new server, deploy `tracks.php`, configure CORS, update track URIs in metadata. No MOOP server changes needed.

**Q: Do reference sequences (FASTA) need JWT tokens?**  
A: Yes, unless on IP whitelist. All URIs in config get tokens added (except whitelisted IPs).

**Q: What if a user's session expires mid-session?**  
A: JBrowse2 will continue working with existing tokens until they expire (1 hour). After that, tracks will fail to load. User must refresh page to re-authenticate.

**Q: Can I use HS256 instead of RS256?**  
A: Not recommended. HS256 uses symmetric keys - same secret on MOOP + tracks servers. If tracks server compromised, attacker can forge tokens. RS256 prevents this.

---

**Document Version:** 3.0  
**Last Security Audit:** February 18, 2026  
**Next Review:** August 18, 2026

**Contact:**  
For security issues, contact MOOP administrator immediately.

### Security Layers

JBrowse2 in MOOP has multiple security layers:

```
┌─────────────────────────────────────────────────────────┐
│ Layer 1: User Authentication (Session-based)            │
│   - Login required for restricted assemblies            │
│   - Access levels: Public, Collaborator, Admin          │
└────────────────┬────────────────────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────────────────────┐
│ Layer 2: Assembly Filtering (API)                       │
│   - Metadata defines defaultAccessLevel                 │
│   - API filters by user's session access level          │
└────────────────┬────────────────────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────────────────────┐
│ Layer 3: Track Filtering (API)                          │
│   - Track metadata defines access_levels array          │
│   - API filters tracks by user access                   │
└────────────────┬────────────────────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────────────────────┐
│ Layer 4: JWT Token Authentication (Tracks Server)       │
│   - Tokens generated per-assembly, per-user             │
│   - 1-hour expiry forces re-authentication              │
│   - Tracks server validates before serving files        │
└─────────────────────────────────────────────────────────┘
```

### Security Principles

- **Defense in Depth** - Multiple layers of security
- **Least Privilege** - Users see only what they're allowed to access
- **Secure by Default** - Anonymous users get minimal access
- **Stateless Validation** - JWT tokens enable scalable auth
- **Time-Limited Access** - Tokens expire after 1 hour

---

## Authentication

### Session-Based Authentication

MOOP uses PHP sessions for user authentication.

#### Session Variables

```php
$_SESSION['user_id']       // Integer user ID
$_SESSION['username']      // String username
$_SESSION['access_level']  // "Public", "Collaborator", or custom
$_SESSION['is_admin']      // Boolean admin flag
$_SESSION['access']        // Array of organism/assembly permissions
```

#### Access Level Determination

```php
// File: api/jbrowse2/get-config.php

// Default for anonymous users
$user_access_level = 'Public';

if (isset($_SESSION['user_id'])) {
    // Logged-in user
    $user_access_level = $_SESSION['access_level'] ?? 'Collaborator';
    
    // Admin override
    if ($_SESSION['is_admin'] ?? false) {
        $user_access_level = 'ALL';
    }
}
```

#### Session Security

**Hardening recommendations:**

```php
// In session configuration
ini_set('session.cookie_httponly', 1);     // Prevent JavaScript access
ini_set('session.cookie_secure', 1);       // HTTPS only
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
ini_set('session.use_strict_mode', 1);     // Reject uninitialized sessions
ini_set('session.gc_maxlifetime', 3600);   // 1 hour lifetime
```

---

## JWT Token System

### Purpose

JWT tokens authenticate track file requests to prevent unauthorized access to BigWig/BAM files.

### Current Implementation (RS256) ✅

**Algorithm:** RSA with SHA-256 (asymmetric keys)

**Files:**
- `/data/moop/certs/jwt_private_key.pem` - RSA private key (2048-bit), signs tokens on MOOP server
- `/data/moop/certs/jwt_public_key.pem` - RSA public key, verifies tokens on tracks server

**Security Benefits:**
- ✅ MOOP server keeps private key (signs tokens)
- ✅ Tracks servers get public key only (verify tokens)
- ✅ Compromised tracks server cannot forge tokens
- ✅ Can distribute public key to multiple servers safely
- ✅ Limited blast radius if tracks server is compromised

### Implementation Details

**Algorithm:** RSA with SHA-256 (asymmetric keys)

**Benefits:**
- MOOP keeps private key (signs tokens)
- Tracks servers get public key only (verify tokens)
- Compromised tracks server can't forge tokens
- Can distribute public key to multiple servers safely

#### Key Management

**Current Keys (already deployed):**

```bash
cd /data/moop/certs
ls -la
# jwt_private_key.pem (2048-bit RSA private key)
# jwt_public_key.pem (RSA public key)
```

**Verify Keys:**

```bash
# Check private key
openssl rsa -in jwt_private_key.pem -text -noout | head -1
# Should show: Private-Key: (2048 bit, 2 primes)

# Check public key
openssl rsa -pubin -in jwt_public_key.pem -text -noout | head -1
# Should show: Public-Key: (2048 bit)
```

**Token Generation (current implementation in `lib/jbrowse/track_token.php`):**

```php
function generateTrackToken($organism, $assembly, $access_level) {
    $private_key = file_get_contents('/data/moop/certs/jwt_private_key.pem');
    
    $token_data = [
        'user_id' => $_SESSION['username'] ?? 'anonymous',
        'organism' => $organism,
        'assembly' => $assembly,
        'access_level' => $access_level,
        'iat' => time(),
        'exp' => time() + 3600
    ];
    
    return JWT::encode($token_data, $private_key, 'RS256');
}
```

**Token Verification (current implementation in `lib/jbrowse/track_token.php`):**

```php
function verifyTrackToken($token) {
    $public_key = file_get_contents('/data/moop/certs/jwt_public_key.pem');
    
    try {
        $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
        
        if ($decoded->exp < time()) {
            return false;
        }
        
        return $decoded;
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}
```

**Deploy Public Key to Tracks Servers**

```bash
# Copy to each tracks server
scp /data/moop/certs/jwt_public_key.pem tracks-admin@tracks1.example.com:/etc/tracks-server/
scp /data/moop/certs/jwt_public_key.pem tracks-admin@tracks2.example.com:/etc/tracks-server/

# Verify permissions on remote servers
ssh tracks-admin@tracks1.example.com "ls -la /etc/tracks-server/jwt_public_key.pem"
```

### Token Claims Validation ✅

**Implementation:** Tokens are verified AND claims are checked against requested files.

**Security:** Prevents token reuse across different organisms/assemblies.

**Current Implementation (in `api/jbrowse2/tracks.php`):**

```php
// File path format: organism/assembly/type/filename
$file_parts = explode('/', $file);

if (count($file_parts) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file path format']);
    exit;
}

$file_organism = $file_parts[0];
$file_assembly = $file_parts[1];

// Check if token grants access to this organism/assembly
if ($token_data->organism !== $file_organism || $token_data->assembly !== $file_assembly) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Access denied',
        'message' => 'Token does not grant access to this file'
    ]);
    exit;
}
```

**What This Prevents:**
- ❌ User cannot use token for "Organism_A/Assembly_1" to access "Organism_B/Assembly_2"
- ❌ Token is locked to specific organism/assembly pair
- ✅ Each assembly requires its own valid token

### Token Refresh

**Problem:** Tokens expire after 1 hour. Long research sessions interrupted.

**Solution:** Add token refresh endpoint

```php
<?php
// New file: api/jbrowse2/refresh-token.php

session_start();
require_once '../../lib/jbrowse/track_token.php';
require_once '../../lib/functions_access.php';

header('Content-Type: application/json');

$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';

if (empty($organism) || empty($assembly)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing organism or assembly parameter']);
    exit;
}

// Verify user still has access
$accessible = getAccessibleAssemblies($organism, $assembly);
if (empty($accessible)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this assembly']);
    exit;
}

// Generate new token
$access_level = getUserAccessLevel();
$new_token = generateTrackToken($organism, $assembly, $access_level);

echo json_encode([
    'token' => $new_token,
    'expires_at' => time() + 3600,
    'organism' => $organism,
    'assembly' => $assembly
]);
?>
```

**JavaScript client:**

```javascript
// In jbrowse2-loader.js

// Refresh tokens every 45 minutes
setInterval(async () => {
    const assemblies = getCurrentAssemblies();
    
    for (const asm of assemblies) {
        try {
            const response = await fetch(
                `/moop/api/jbrowse2/refresh-token.php?organism=${asm.organism}&assembly=${asm.assembly}`
            );
            const data = await response.json();
            
            if (response.ok) {
                updateTrackUrls(asm, data.token);
                console.log(`Token refreshed for ${asm.organism}/${asm.assembly}`);
            }
        } catch (error) {
            console.error(`Failed to refresh token for ${asm.organism}/${asm.assembly}:`, error);
        }
    }
}, 45 * 60 * 1000); // 45 minutes
```

---

## Remote Tracks Server

### Architecture

**Production setup:**

```
┌─────────────────────────┐
│   MOOP Web Server       │
│   (moop.example.com)    │
│                         │
│  - User authentication  │
│  - JBrowse2 UI          │
│  - Config API           │
│  - JWT generation       │
│  - Private key only     │
└──────────┬──────────────┘
           │
           │ HTTPS
           │
           ↓
┌─────────────────────────┐
│  Tracks Server(s)       │
│  (tracks.example.com)   │
│                         │
│  - JWT validation       │
│  - File serving         │
│  - HTTP range support   │
│  - Public key only      │
└─────────────────────────┘
```

### Nginx Configuration

**File:** `/etc/nginx/sites-available/tracks.example.com`

```nginx
server {
    listen 443 ssl http2;
    server_name tracks.example.com;
    
    # SSL certificates
    ssl_certificate /etc/ssl/certs/tracks.example.com.crt;
    ssl_certificate_key /etc/ssl/private/tracks.example.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Document root
    root /var/tracks/data;
    
    # CORS for JBrowse2
    add_header Access-Control-Allow-Origin "https://moop.example.com" always;
    add_header Access-Control-Allow-Methods "GET, HEAD, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Range, Authorization" always;
    add_header Access-Control-Expose-Headers "Content-Range, Content-Length, Accept-Ranges" always;
    add_header Access-Control-Max-Age "3600" always;
    
    # Handle OPTIONS preflight
    if ($request_method = 'OPTIONS') {
        return 204;
    }
    
    # BigWig files
    location ~ ^/bigwig/(.+\.bw)$ {
        # Require token
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        
        # Validate token
        auth_request /validate-jwt;
        
        # Enable range requests (critical for BigWig)
        add_header Accept-Ranges bytes always;
        add_header Content-Type application/octet-stream always;
        
        # Serve file
        try_files $uri =404;
    }
    
    # BAM files
    location ~ ^/bam/(.+\.bam)$ {
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        auth_request /validate-jwt;
        add_header Accept-Ranges bytes always;
        try_files $uri =404;
    }
    
    # Index files (.bai, .tbi)
    location ~ ^/(bam|bigwig)/(.+\.(bai|tbi))$ {
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        auth_request /validate-jwt;
        try_files $uri =404;
    }
    
    # Internal JWT validation endpoint
    location = /validate-jwt {
        internal;
        
        # FastCGI to PHP validation script
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/tracks/validate-jwt.php;
        fastcgi_param HTTP_X_TOKEN $arg_token;
        fastcgi_param HTTP_X_REQUEST_URI $request_uri;
        include fastcgi_params;
    }
    
    # Deny access to validation script directly
    location ~ /validate-jwt\.php$ {
        deny all;
    }
}
```

### Apache Configuration

**File:** `/etc/apache2/sites-available/tracks.example.com.conf`

```apache
<VirtualHost *:443>
    ServerName tracks.example.com
    DocumentRoot /var/tracks/data
    
    # SSL
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/tracks.example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/tracks.example.com.key
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    
    # CORS headers
    Header always set Access-Control-Allow-Origin "https://moop.example.com"
    Header always set Access-Control-Allow-Methods "GET, HEAD, OPTIONS"
    Header always set Access-Control-Allow-Headers "Range, Authorization"
    Header always set Access-Control-Expose-Headers "Content-Range, Content-Length, Accept-Ranges"
    Header always set Access-Control-Max-Age "3600"
    
    # Handle OPTIONS preflight
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=204,L]
    
    # Enable rewrite for token validation
    RewriteEngine On
    
    # BigWig files
    <LocationMatch "^/bigwig/.*\.bw$">
        # Check token exists
        RewriteCond %{QUERY_STRING} !token=
        RewriteRule .* - [F,L]
        
        # Validate token
        RewriteCond %{QUERY_STRING} token=([^&]+)
        RewriteRule .* - [E=JWT_TOKEN:%1]
        
        # Call validation script (mod_ext_filter or custom module)
        # Or use mod_rewrite to proxy to validation endpoint
        
        # Enable range requests
        Header set Accept-Ranges bytes
        Header set Content-Type application/octet-stream
    </LocationMatch>
    
    # JWT validation script
    <Files "validate-jwt.php">
        Require all denied
    </Files>
</VirtualHost>
```

**Note:** Apache configuration is more complex. Consider using Nginx for simpler setup.

### Validation Script (for Tracks Server)

**File:** `/var/www/tracks/validate-jwt.php`

```php
<?php
require_once '/usr/share/php/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Get token from request
$token = $_SERVER['HTTP_X_TOKEN'] ?? $_GET['token'] ?? '';
$request_uri = $_SERVER['HTTP_X_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '';

if (empty($token)) {
    http_response_code(403);
    error_log("Tracks server: No token provided");
    exit("No token");
}

try {
    // Load public key (deployed from MOOP server)
    $public_key_path = '/etc/tracks-server/jwt_public_key.pem';
    if (!file_exists($public_key_path)) {
        http_response_code(500);
        error_log("Tracks server: Public key not found at $public_key_path");
        exit("Configuration error");
    }
    
    $public_key = file_get_contents($public_key_path);
    
    // Verify JWT signature (RS256 algorithm)
    $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
    
    // Check expiration
    if ($decoded->exp < time()) {
        http_response_code(403);
        error_log("Tracks server: Token expired for user {$decoded->user_id}");
        exit("Token expired");
    }
    
    // Validate token claims against requested file
    if (preg_match('#/(bigwig|bam)/(\w+)_([^_/]+)_#', $request_uri, $matches)) {
        $format = $matches[1];
        $uri_organism = $matches[2];
        $uri_assembly = $matches[3];
        
        // Verify token organism/assembly matches file
        if ($decoded->organism !== $uri_organism || 
            $decoded->assembly !== $uri_assembly) {
            http_response_code(403);
            error_log("Tracks server: Token mismatch - token({$decoded->organism}/{$decoded->assembly}) vs file($uri_organism/$uri_assembly)");
            exit("Token organism/assembly mismatch");
        }
    }
    
    // Token is valid
    http_response_code(200);
    error_log("Tracks server: Valid token for {$decoded->user_id} accessing {$decoded->organism}/{$decoded->assembly}");
    
} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(403);
    error_log("Tracks server: Token expired - " . $e->getMessage());
    exit("Token expired");
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    http_response_code(403);
    error_log("Tracks server: Invalid signature - " . $e->getMessage());
    exit("Invalid token signature");
} catch (Exception $e) {
    http_response_code(403);
    error_log("Tracks server: Token validation failed - " . $e->getMessage());
    exit("Invalid token");
}
?>
```

---

## Security Best Practices

### Key Management

#### DO ✅

- ✅ Use RS256 (asymmetric) for JWT tokens
- ✅ Keep private key on MOOP server only (`chmod 600`)
- ✅ Distribute public key to tracks servers (`chmod 644`)
- ✅ Store keys outside web root (`/data/moop/certs/`)
- ✅ Backup keys securely (encrypted backups)
- ✅ Rotate keys annually or when compromised

#### DON'T ❌

- ❌ Don't use HS256 in production (symmetric key)
- ❌ Don't commit private keys to git
- ❌ Don't share private keys via insecure channels
- ❌ Don't store keys in web-accessible directories
- ❌ Don't use same key for multiple purposes

### Session Security

#### DO ✅

- ✅ Use HTTPS only (enforce with redirect)
- ✅ Set `HttpOnly` flag on session cookies
- ✅ Set `Secure` flag on session cookies
- ✅ Use `SameSite=Lax` or `Strict`
- ✅ Implement session timeout (1-4 hours)
- ✅ Regenerate session ID on login

#### DON'T ❌

- ❌ Don't allow HTTP access
- ❌ Don't allow JavaScript access to session cookies
- ❌ Don't use predictable session IDs
- ❌ Don't skip CSRF protection
- ❌ Don't store sensitive data in sessions (use database)

### API Security

#### DO ✅

- ✅ Validate all inputs
- ✅ Sanitize file paths (prevent directory traversal)
- ✅ Use parameterized queries (if using database)
- ✅ Implement rate limiting
- ✅ Log security events
- ✅ Return generic error messages to users

#### DON'T ❌

- ❌ Don't trust client input
- ❌ Don't expose internal paths in errors
- ❌ Don't leak stack traces to users
- ❌ Don't allow unrestricted file access
- ❌ Don't skip access control checks

### Monitoring

```bash
# Monitor JWT validation failures
tail -f /var/log/nginx/tracks.error.log | grep "Token"

# Monitor access patterns
tail -f /var/log/nginx/tracks.access.log | grep "bigwig"

# Alert on suspicious activity
# (e.g., many token failures from same IP)
```

---

## Threat Model

### Threats & Mitigations

| Threat | Risk | Mitigation |
|--------|------|------------|
| **Unauthorized Assembly Access** | High | Session-based auth + access level filtering |
| **Token Forgery** | High | ✅ RS256 asymmetric signatures (private key stays on MOOP server) |
| **Token Replay** | Medium | 1-hour expiry + HTTPS |
| **Token Theft** | Medium | HTTPS only, HttpOnly cookies |
| **Directory Traversal** | High | Path validation, filename whitelist |
| **CSRF** | Medium | SameSite cookies, token validation |
| **XSS** | Medium | Input sanitization, CSP headers |
| **Session Hijacking** | High | Secure cookies, session regeneration |
| **Brute Force** | Low | Rate limiting (not yet implemented) |

### Attack Scenarios

#### Scenario 1: Unauthorized Track Access

**Attack:** User tries to access restricted track files directly

**Flow:**
1. Attacker discovers track URL with token
2. Tries to use token for different file

**Mitigation:**
- Token claims validation checks organism/assembly match
- Token expires after 1 hour
- Different users get different tokens

#### Scenario 2: Tracks Server Compromise

**Attack:** Tracks server is compromised

**Impact with Current RS256 Implementation:**
- ✅ Attacker has only public key
- ✅ Cannot forge tokens (requires private key for signing)
- ✅ Cannot access other assemblies (token claims are validated)
- ✅ Limited blast radius - compromise only affects that tracks server
- ✅ MOOP server remains secure (private key never leaves)

#### Scenario 3: Session Hijacking

**Attack:** Attacker steals session cookie

**Mitigation:**
- HTTPS prevents cookie interception
- HttpOnly prevents JavaScript access
- SameSite prevents CSRF attacks
- Session timeout limits exposure window

---

## Incident Response

### Key Compromise

**If JWT private key is compromised:**

```bash
# 1. Generate new key pair immediately
cd /data/moop/certs
openssl genrsa -out jwt_private_key.pem 4096
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
chmod 600 jwt_private_key.pem
chmod 644 jwt_public_key.pem

# 2. Deploy new public key to ALL tracks servers
for server in tracks1 tracks2 tracks3; do
    scp jwt_public_key.pem admin@$server:/etc/tracks-server/
done

# 3. All existing tokens are now invalid (users must re-auth)
# 4. Review access logs for suspicious activity
grep "Token" /var/log/nginx/tracks.error.log | grep -v "Valid token"

# 5. Backup compromised key for investigation
mv /data/moop/certs/jwt_private_key.pem.backup \
   /data/moop/certs/compromised_key_$(date +%s).pem
```

### Unauthorized Access Detection

**Signs of unauthorized access:**
- Many token validation failures
- Access patterns outside normal hours
- Unusual organism/assembly access
- High download volumes

**Investigation:**

```bash
# Check access logs
grep "403" /var/log/nginx/tracks.access.log | tail -50

# Check error logs
grep "Token" /var/log/nginx/tracks.error.log | tail -50

# Identify suspicious IPs
awk '{print $1}' /var/log/nginx/tracks.access.log | sort | uniq -c | sort -rn | head -20
```

---

## Security Checklist

### Current Status

- [x] ✅ RS256 asymmetric JWT signatures implemented
- [x] ✅ Token claims validation (organism/assembly matching)
- [x] ✅ HTTPS enabled
- [x] ✅ IP whitelisting for internal network
- [x] ✅ Directory traversal protection
- [x] ✅ HTTP range request support
- [x] ✅ 1-hour token expiry
- [ ] ⚠️ Secure cookie flags (HttpOnly, Secure, SameSite) - needs verification
- [ ] ⚠️ Rate limiting on token endpoints
- [ ] ⚠️ Centralized security logging/monitoring
- [ ] ⚠️ Token refresh endpoint for long sessions

### Ongoing Maintenance

- [ ] Monitor JWT validation failures
- [ ] Review access logs weekly
- [ ] Rotate JWT keys annually (last rotated: Feb 2026)
- [ ] Keep dependencies updated (firebase/php-jwt)
- [ ] Regular security audits
- [ ] Test key rotation procedure

---

## Resources

- [JWT Best Practices](https://tools.ietf.org/html/rfc8725)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Firebase JWT PHP](https://github.com/firebase/php-jwt)

---

**Security Contact:** Contact MOOP administrator for security issues

**Last Security Audit:** 2026-02-06  
**Next Audit Due:** 2026-08-06
