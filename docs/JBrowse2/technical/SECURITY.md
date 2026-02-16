# JBrowse2 Security Architecture

**Version:** 2.0  
**Last Updated:** February 16, 2026  
**Status:** Production Deployed with RS256

**Recent Changes:**
- Updated documentation to reflect current RS256 implementation (was incorrectly documented as HS256)
- Verified all security features are implemented and working
- Added current status checklist with completed items marked

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [JWT Token System](#jwt-token-system)
4. [Remote Tracks Server](#remote-tracks-server)
5. [Security Best Practices](#security-best-practices)
6. [Threat Model](#threat-model)
7. [Incident Response](#incident-response)

---

## Overview

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
