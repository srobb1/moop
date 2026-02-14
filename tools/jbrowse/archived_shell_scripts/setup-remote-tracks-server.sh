#!/bin/bash
################################################################################
# JBrowse2 Remote Tracks Server Setup Script
#
# This script prepares the tracks server configuration for deployment.
# It creates the necessary configuration files for both Nginx and Apache.
#
# Usage:
#   ./setup-remote-tracks-server.sh [output_directory]
#
# Example:
#   ./setup-remote-tracks-server.sh /tmp/tracks-server-config
#
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_warn() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error() { echo -e "${RED}✗${NC} $1" >&2; }

# Configuration
OUTPUT_DIR="${1:-/tmp/tracks-server-config}"
MOOP_ROOT="/data/moop"

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "    JBrowse2 Remote Tracks Server Setup"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Create output directory
mkdir -p "$OUTPUT_DIR"
log_success "Created output directory: $OUTPUT_DIR"

# ============================================================================
# 1. Copy public key
# ============================================================================

log_info "Copying JWT public key..."

if [ ! -f "$MOOP_ROOT/certs/jwt_public_key.pem" ]; then
    log_error "JWT public key not found at $MOOP_ROOT/certs/jwt_public_key.pem"
    log_error "Run key generation first!"
    exit 1
fi

cp "$MOOP_ROOT/certs/jwt_public_key.pem" "$OUTPUT_DIR/"
log_success "Public key copied to: $OUTPUT_DIR/jwt_public_key.pem"

# ============================================================================
# 2. Create JWT validation script
# ============================================================================

log_info "Creating JWT validation script..."

cat > "$OUTPUT_DIR/validate-jwt.php" << 'EOFPHP'
<?php
/**
 * JWT Token Validation for Tracks Server
 * 
 * This script validates JWT tokens for track file requests.
 * It should be deployed to the tracks server and called by the web server
 * (Nginx auth_request or Apache rewrite).
 * 
 * Deployment:
 *   - Copy to: /var/www/tracks/validate-jwt.php
 *   - Copy jwt_public_key.pem to: /etc/tracks-server/jwt_public_key.pem
 *   - Ensure web server can execute this script
 */

require_once '/usr/share/php/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Get token from request
$token = $_SERVER['HTTP_X_TOKEN'] ?? $_GET['token'] ?? '';
$request_uri = $_SERVER['HTTP_X_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '';

if (empty($token)) {
    http_response_code(403);
    error_log("Tracks server: No token provided for $request_uri");
    exit("No token");
}

try {
    // Load public key
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
    // Expected format: /(bigwig|bam)/{organism}_{assembly}_{trackname}.{ext}
    if (preg_match('#/(bigwig|bam)/(\w+)_([^_/]+)_#', $request_uri, $matches)) {
        $format = $matches[1];
        $uri_organism = $matches[2];
        $uri_assembly = $matches[3];
        
        // Verify token organism/assembly matches file
        if ($decoded->organism !== $uri_organism || 
            $decoded->assembly !== $uri_assembly) {
            http_response_code(403);
            error_log("Tracks server: Token mismatch - token({$decoded->organism}/{$decoded->assembly}) vs file($uri_organism/$uri_assembly) for user {$decoded->user_id}");
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
EOFPHP

log_success "JWT validation script created: $OUTPUT_DIR/validate-jwt.php"

# ============================================================================
# 3. Create Nginx configuration
# ============================================================================

log_info "Creating Nginx configuration..."

cat > "$OUTPUT_DIR/nginx-tracks-server.conf" << 'EOFNGINX'
# /etc/nginx/sites-available/tracks.example.com

server {
    listen 443 ssl http2;
    server_name tracks.example.com;
    
    # SSL certificates (adjust paths)
    ssl_certificate /etc/ssl/certs/tracks.example.com.crt;
    ssl_certificate_key /etc/ssl/private/tracks.example.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Document root for track files
    root /var/tracks/data;
    
    # CORS headers for JBrowse2
    add_header Access-Control-Allow-Origin "https://moop.example.com" always;
    add_header Access-Control-Allow-Methods "GET, HEAD, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Range, Authorization" always;
    add_header Access-Control-Expose-Headers "Content-Range, Content-Length, Accept-Ranges" always;
    add_header Access-Control-Max-Age "3600" always;
    
    # Handle OPTIONS preflight requests
    if ($request_method = 'OPTIONS') {
        return 204;
    }
    
    # BigWig files
    location ~ ^/bigwig/(.+\.bw)$ {
        # Require token
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        
        # Validate token via internal request
        auth_request /validate-jwt;
        
        # Enable HTTP range requests (critical for BigWig)
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
    
    # Deny direct access to validation script
    location ~ /validate-jwt\.php$ {
        deny all;
    }
    
    # Logging
    access_log /var/log/nginx/tracks.access.log;
    error_log /var/log/nginx/tracks.error.log;
}
EOFNGINX

log_success "Nginx config created: $OUTPUT_DIR/nginx-tracks-server.conf"

# ============================================================================
# 4. Create deployment instructions
# ============================================================================

log_info "Creating deployment instructions..."

cat > "$OUTPUT_DIR/DEPLOYMENT.md" << 'EOFDEPLOY'
# Tracks Server Deployment Instructions

## Prerequisites

### On Tracks Server

1. **Web server** (Nginx or Apache)
2. **PHP 7.4+** with php-fpm
3. **Composer** (for JWT library)
4. **SSL certificate** for HTTPS

### Install Dependencies

```bash
# On tracks server
sudo apt-get update
sudo apt-get install -y nginx php8.1-fpm php8.1-cli composer

# Install Firebase JWT library
cd /var/www/tracks
composer require firebase/php-jwt
```

## Deployment Steps

### 1. Copy Files to Tracks Server

```bash
# From MOOP server, copy files to tracks server
scp jwt_public_key.pem admin@tracks.example.com:/etc/tracks-server/
scp validate-jwt.php admin@tracks.example.com:/var/www/tracks/
scp nginx-tracks-server.conf admin@tracks.example.com:/tmp/
```

### 2. Configure Tracks Server

#### A. Create Directories

```bash
# On tracks server
sudo mkdir -p /etc/tracks-server
sudo mkdir -p /var/tracks/data/bigwig
sudo mkdir -p /var/tracks/data/bam
sudo mkdir -p /var/www/tracks
```

#### B. Set Up JWT Public Key

```bash
# On tracks server
sudo mv /etc/tracks-server/jwt_public_key.pem /etc/tracks-server/
sudo chmod 644 /etc/tracks-server/jwt_public_key.pem
sudo chown root:root /etc/tracks-server/jwt_public_key.pem
```

#### C. Set Up Validation Script

```bash
# On tracks server
sudo mv /var/www/tracks/validate-jwt.php /var/www/tracks/
sudo chmod 644 /var/www/tracks/validate-jwt.php
sudo chown www-data:www-data /var/www/tracks/validate-jwt.php
```

### 3. Configure Nginx

```bash
# On tracks server

# Edit the config (adjust domain, SSL paths, PHP socket)
sudo nano /tmp/nginx-tracks-server.conf

# Move to sites-available
sudo mv /tmp/nginx-tracks-server.conf /etc/nginx/sites-available/tracks.example.com

# Create symlink to enable
sudo ln -s /etc/nginx/sites-available/tracks.example.com /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

### 4. Upload Track Files

```bash
# Copy track files to tracks server
# Format: {organism}_{assembly}_{trackname}.{bw,bam}

# Example from MOOP server:
scp /data/moop/data/tracks/*.bw admin@tracks.example.com:/var/tracks/data/bigwig/
scp /data/moop/data/tracks/*.bam admin@tracks.example.com:/var/tracks/data/bam/
scp /data/moop/data/tracks/*.bai admin@tracks.example.com:/var/tracks/data/bam/

# Set permissions
ssh admin@tracks.example.com "
    sudo chown -R www-data:www-data /var/tracks/data
    sudo chmod -R 644 /var/tracks/data/**/*
    sudo chmod 755 /var/tracks/data /var/tracks/data/bigwig /var/tracks/data/bam
"
```

### 5. Update MOOP Configuration

On MOOP server, update track URLs to point to remote server:

```php
// In api/jbrowse2/assembly.php
// Change track URL from local to remote
$track_url = "https://tracks.example.com/{$format}/{$file_name}";
```

### 6. Test Setup

#### Test JWT Validation

```bash
# On tracks server
echo "<?php require 'validate-jwt.php'; ?>" | php
# Should output: "No token"
```

#### Test Track Access (with token)

```bash
# From MOOP server, generate test token
TOKEN=$(php -r "
session_start();
\$_SESSION['username'] = 'test';
require 'lib/jbrowse/track_token.php';
echo generateTrackToken('Anoura_caudifer', 'GCA_004027475.1', 'Public');
")

# Test track file access
curl -I "https://tracks.example.com/bigwig/Anoura_caudifer_GCA_004027475.1_test.bw?token=$TOKEN"
# Should return: 200 OK or 404 Not Found (if file doesn't exist)
# Should NOT return: 403 Forbidden
```

#### Test Without Token

```bash
curl -I "https://tracks.example.com/bigwig/test.bw"
# Should return: 403 Forbidden
```

## Troubleshooting

### "No token provided"

- Check Nginx config passes `$arg_token` to validation script
- Verify FastCGI params include `HTTP_X_TOKEN`

### "Invalid token signature"

- Verify public key matches MOOP private key
- Check algorithm is RS256 (not HS256)
- Test with: `openssl rsa -pubin -in jwt_public_key.pem -text -noout`

### "Token expired"

- Check system time on both servers (must be synced)
- Install NTP: `sudo apt-get install ntp`

### "Configuration error"

- Verify public key path: `/etc/tracks-server/jwt_public_key.pem`
- Check file permissions: `ls -la /etc/tracks-server/jwt_public_key.pem`

### Files Not Found

- Check track file permissions: `ls -la /var/tracks/data/bigwig/`
- Verify Nginx root path matches file location

## Security Notes

- ✅ Only JWT public key on tracks server (can't forge tokens)
- ✅ HTTPS required (tokens in URLs)
- ✅ Token expiry forces re-authentication (1 hour)
- ✅ Claims validation prevents token reuse
- ✅ CORS restricted to MOOP domain

## Maintenance

### Rotate JWT Keys

When keys are rotated on MOOP server:

```bash
# Copy new public key to tracks servers
scp /data/moop/certs/jwt_public_key.pem admin@tracks1.example.com:/etc/tracks-server/
scp /data/moop/certs/jwt_public_key.pem admin@tracks2.example.com:/etc/tracks-server/

# No need to restart Nginx (key is read on each request)
```

### Monitor Logs

```bash
# On tracks server
tail -f /var/log/nginx/tracks.error.log | grep "Token"
tail -f /var/log/nginx/tracks.access.log
```

### Add New Track Files

```bash
# Upload to tracks server
scp new_track.bw admin@tracks.example.com:/var/tracks/data/bigwig/

# Set permissions
ssh admin@tracks.example.com "
    sudo chown www-data:www-data /var/tracks/data/bigwig/new_track.bw
    sudo chmod 644 /var/tracks/data/bigwig/new_track.bw
"
```

---

**Need help?** See `/data/moop/docs/JBrowse2/SECURITY.md` for more details.
EOFDEPLOY

log_success "Deployment instructions created: $OUTPUT_DIR/DEPLOYMENT.md"

# ============================================================================
# Summary
# ============================================================================

echo ""
echo "════════════════════════════════════════════════════════════════"
log_success "Tracks server configuration prepared!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Output directory: $OUTPUT_DIR"
echo ""
echo "Generated files:"
echo "  ✓ jwt_public_key.pem          - Public key for JWT validation"
echo "  ✓ validate-jwt.php            - JWT validation script"
echo "  ✓ nginx-tracks-server.conf    - Nginx configuration"
echo "  ✓ DEPLOYMENT.md               - Deployment instructions"
echo ""
echo "Next steps:"
echo "  1. Review $OUTPUT_DIR/DEPLOYMENT.md"
echo "  2. Copy files to tracks server(s)"
echo "  3. Configure web server (Nginx/Apache)"
echo "  4. Upload track files"
echo "  5. Test token validation"
echo ""
echo "See docs/JBrowse2/SECURITY.md for complete details"
echo ""
