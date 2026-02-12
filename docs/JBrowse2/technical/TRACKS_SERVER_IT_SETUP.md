# Remote Tracks Server Setup Guide for IT

**Document Version:** 1.0  
**Last Updated:** February 6, 2026  
**For:** IT Team - Remote Tracks Server Deployment

---

## Overview

This document provides instructions for setting up a dedicated tracks server to serve JBrowse2 genome browser track files (BigWig, BAM, VCF, etc.) with JWT token authentication.

### Architecture

```
┌─────────────────────────┐
│   MOOP Web Server       │
│   (moop.example.com)    │
│                         │
│  - User authentication  │
│  - JBrowse2 UI          │
│  - Config API           │
│  - JWT generation       │
│  - Private key (signs)  │
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
│  - Public key (verifies)│
└─────────────────────────┘
```

### Key Points

- **MOOP server** signs JWT tokens with private key
- **Tracks server** validates tokens with public key (can't forge tokens)
- **HTTPS required** - tokens are passed in URLs
- **HTTP range requests** - essential for BigWig/BAM files
- **Token expiry** - 1 hour, forces re-authentication
- **CORS enabled** - for cross-origin requests from JBrowse2

---

## Prerequisites

### Hardware

- **CPU:** 4+ cores recommended
- **RAM:** 8GB+ (depends on concurrent users)
- **Storage:** Sufficient for track files (varies by genome count)
  - Typical: 1-10GB per genome assembly
  - Plan for growth
- **Network:** High bandwidth for file serving

### Software

- **OS:** Ubuntu 20.04+ or similar Linux distribution
- **Web Server:** Nginx (recommended) or Apache
- **PHP:** 7.4+ with php-fpm
- **Composer:** For PHP dependency management
- **SSL Certificate:** For HTTPS (Let's Encrypt or commercial)
- **NTP:** For time synchronization with MOOP server

### Installation Commands

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y nginx php8.1-fpm php8.1-cli composer ntp

# Start and enable services
sudo systemctl start nginx php8.1-fpm ntp
sudo systemctl enable nginx php8.1-fpm ntp
```

---

## Setup Instructions

### Step 1: Create Directory Structure

```bash
# Create directories
sudo mkdir -p /etc/tracks-server
sudo mkdir -p /var/tracks/data/bigwig
sudo mkdir -p /var/tracks/data/bam
sudo mkdir -p /var/www/tracks

# Set permissions
sudo chown -R www-data:www-data /var/tracks/data
sudo chmod 755 /var/tracks/data /var/tracks/data/bigwig /var/tracks/data/bam
```

### Step 2: Install JWT Library

```bash
# Install Firebase JWT library for PHP
cd /var/www/tracks
sudo composer require firebase/php-jwt

# Verify installation
sudo ls -la /var/www/tracks/vendor/firebase/php-jwt/
```

### Step 3: Obtain Files from MOOP Server

**The MOOP team will provide you with:**
1. `jwt_public_key.pem` - Public key for JWT validation
2. `validate-jwt.php` - PHP script to validate tokens
3. `nginx-tracks-server.conf` - Nginx configuration template

**Transfer from MOOP server:**

```bash
# From MOOP server (MOOP team will run this)
scp /data/moop/certs/jwt_public_key.pem admin@tracks.example.com:/tmp/

# MOOP team will also send validate-jwt.php and nginx config
```

**On tracks server:**

```bash
# Move public key to secure location
sudo mv /tmp/jwt_public_key.pem /etc/tracks-server/
sudo chmod 644 /etc/tracks-server/jwt_public_key.pem
sudo chown root:root /etc/tracks-server/jwt_public_key.pem

# Move validation script
sudo mv /tmp/validate-jwt.php /var/www/tracks/
sudo chmod 644 /var/www/tracks/validate-jwt.php
sudo chown www-data:www-data /var/www/tracks/validate-jwt.php
```

### Step 4: Configure Nginx

**Create Nginx configuration:**

```bash
# Edit the provided config file
sudo nano /etc/nginx/sites-available/tracks.example.com
```

**Configuration template** (provided by MOOP team, customize as needed):

```nginx
server {
    listen 443 ssl http2;
    server_name tracks.example.com;  # ← Change to your domain
    
    # SSL certificates
    ssl_certificate /etc/ssl/certs/tracks.example.com.crt;      # ← Your SSL cert
    ssl_certificate_key /etc/ssl/private/tracks.example.com.key; # ← Your SSL key
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Document root for track files
    root /var/tracks/data;
    
    # CORS headers (adjust origin to MOOP server URL)
    add_header Access-Control-Allow-Origin "https://moop.example.com" always;  # ← MOOP URL
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
        if ($arg_token = "") {
            return 403 "No token provided";
        }
        auth_request /validate-jwt;
        add_header Accept-Ranges bytes always;
        add_header Content-Type application/octet-stream always;
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
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;  # ← Adjust PHP version if needed
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
```

**Enable site:**

```bash
# Create symlink to enable site
sudo ln -s /etc/nginx/sites-available/tracks.example.com /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# If test passes, reload Nginx
sudo systemctl reload nginx
```

### Step 5: Upload Track Files

**MOOP team will upload track files via:**

```bash
# Example: MOOP team runs this
scp /data/moop/data/tracks/*.bw admin@tracks.example.com:/tmp/tracks-bigwig/
scp /data/moop/data/tracks/*.bam admin@tracks.example.com:/tmp/tracks-bam/
scp /data/moop/data/tracks/*.bai admin@tracks.example.com:/tmp/tracks-bam/
```

**On tracks server, move files to proper location:**

```bash
# Move track files
sudo mv /tmp/tracks-bigwig/* /var/tracks/data/bigwig/
sudo mv /tmp/tracks-bam/* /var/tracks/data/bam/

# Set permissions
sudo chown -R www-data:www-data /var/tracks/data
sudo find /var/tracks/data -type f -exec chmod 644 {} \;
sudo find /var/tracks/data -type d -exec chmod 755 {} \;
```

**File naming convention:**
- Format: `{organism}_{assembly}_{trackname}.{ext}`
- Example: `Anoura_caudifer_GCA_004027475.1_rna_coverage.bw`

---

## Testing

### Test 1: Nginx Configuration

```bash
sudo nginx -t
# Expected: "syntax is okay" and "test is successful"
```

### Test 2: PHP-FPM

```bash
sudo systemctl status php8.1-fpm
# Expected: "active (running)"
```

### Test 3: JWT Validation Script

```bash
# Test script can be loaded
sudo -u www-data php /var/www/tracks/validate-jwt.php
# Expected: "No token" error (normal - no token provided)
```

### Test 4: File Permissions

```bash
# Check track files are readable
ls -la /var/tracks/data/bigwig/
ls -la /var/tracks/data/bam/
# Expected: Files owned by www-data with 644 permissions
```

### Test 5: Access Without Token

```bash
curl -I https://tracks.example.com/bigwig/test.bw
# Expected: 403 Forbidden
```

### Test 6: Access With Valid Token

**MOOP team will generate a test token and run:**

```bash
TOKEN="<provided_by_moop_team>"
curl -I "https://tracks.example.com/bigwig/Organism_Assembly_test.bw?token=$TOKEN"
# Expected: 200 OK (if file exists) or 404 Not Found (if file doesn't exist)
# Should NOT be: 403 Forbidden
```

---

## Monitoring & Maintenance

### Log Files

```bash
# Access logs (successful requests)
tail -f /var/log/nginx/tracks.access.log

# Error logs (failures, token issues)
tail -f /var/log/nginx/tracks.error.log

# Filter for token-related errors
tail -f /var/log/nginx/tracks.error.log | grep "Token"
```

### Common Log Messages

**Normal (successful access):**
```
Tracks server: Valid token for username accessing Organism/Assembly
```

**Token expired:**
```
Tracks server: Token expired for user username
```

**Invalid token:**
```
Tracks server: Invalid signature
Tracks server: Token organism/assembly mismatch
```

### Health Checks

**Daily:**
- Check disk space: `df -h /var/tracks`
- Check service status: `systemctl status nginx php8.1-fpm`

**Weekly:**
- Review error logs for patterns
- Check for failed authentication attempts
- Monitor bandwidth usage

**Monthly:**
- Update system packages: `sudo apt-get update && sudo apt-get upgrade`
- Review SSL certificate expiry: `openssl x509 -in /etc/ssl/certs/tracks.example.com.crt -noout -dates`

### Backup Strategy

**What to backup:**
1. Track files: `/var/tracks/data/` (large, MOOP team may have originals)
2. Configuration: `/etc/nginx/sites-available/tracks.example.com`
3. JWT public key: `/etc/tracks-server/jwt_public_key.pem`

**Backup frequency:**
- Configuration: Daily
- Track files: Coordinate with MOOP team (they may be source of truth)

---

## Troubleshooting

### Issue: "No token provided"

**Symptoms:** All requests return 403 with "No token provided"

**Causes & Solutions:**
1. Token not in URL query parameter
   - Check MOOP server is generating token URLs correctly
2. Nginx not passing token to validation script
   - Verify `fastcgi_param HTTP_X_TOKEN $arg_token;` in nginx config

### Issue: "Invalid token signature"

**Symptoms:** 403 with "Invalid token signature"

**Causes & Solutions:**
1. Public key doesn't match MOOP private key
   - Obtain fresh public key from MOOP team
2. Algorithm mismatch (RS256 vs HS256)
   - Contact MOOP team to verify algorithm
3. Corrupted key file
   - Verify key format: `openssl rsa -pubin -in /etc/tracks-server/jwt_public_key.pem -text -noout`

### Issue: "Token expired"

**Symptoms:** Works initially, then 403 with "Token expired" after 1 hour

**Causes & Solutions:**
1. Normal behavior - tokens expire after 1 hour
   - Users need to refresh page (MOOP generates new token)
2. Clock skew between servers
   - Sync time: `sudo ntpdate -s time.nist.gov`
   - Enable NTP: `sudo systemctl enable ntp`

### Issue: "Configuration error"

**Symptoms:** 500 error with "Configuration error"

**Causes & Solutions:**
1. Public key file not found
   - Check: `ls -la /etc/tracks-server/jwt_public_key.pem`
   - Permissions: `sudo chmod 644 /etc/tracks-server/jwt_public_key.pem`

### Issue: "File not found"

**Symptoms:** 404 Not Found for existing files

**Causes & Solutions:**
1. File permissions wrong
   - Fix: `sudo chown www-data:www-data /var/tracks/data/**/*`
   - Fix: `sudo chmod 644 /var/tracks/data/**/*`
2. Wrong file path in Nginx root
   - Verify: `root /var/tracks/data;` in nginx config

### Issue: High CPU or Memory Usage

**Causes & Solutions:**
1. Too many concurrent requests
   - Monitor: `htop` or `top`
   - Consider adding more servers (load balancing)
2. Large file access
   - Normal for BigWig/BAM files
   - HTTP range requests mitigate this

---

## Security Considerations

### What's Secure

✅ **JWT tokens are signed** - Can't be forged without private key  
✅ **Public key only** - Tracks server can't generate tokens  
✅ **HTTPS required** - Tokens in URLs protected by TLS  
✅ **Token expiry** - 1-hour limit reduces exposure  
✅ **Claims validation** - Token must match requested file  
✅ **CORS restricted** - Only MOOP domain can make requests

### What to Protect

🔒 **Public key file** - Keep secure, though compromise is limited  
🔒 **SSL private key** - Standard web server security  
🔒 **Server access** - Standard Linux hardening  
🔒 **Log files** - May contain user/organism information

### Security Best Practices

1. **Keep system updated:**
   ```bash
   sudo apt-get update && sudo apt-get upgrade
   ```

2. **Monitor failed authentication:**
   ```bash
   grep "403" /var/log/nginx/tracks.error.log | tail -50
   ```

3. **Restrict SSH access:**
   - Use key-based authentication
   - Disable root login
   - Use firewall (ufw/iptables)

4. **Regular key rotation:**
   - When MOOP team rotates keys (annually or if compromised)
   - Simply replace `/etc/tracks-server/jwt_public_key.pem`
   - No service restart needed (key read on each request)

---

## Firewall Configuration

### Required Ports

```bash
# Allow HTTPS (track file access)
sudo ufw allow 443/tcp

# Allow SSH (management)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

### Optional: Restrict to MOOP Server IP

```bash
# If MOOP has static IP, restrict HTTPS to that IP only
sudo ufw allow from <MOOP_SERVER_IP> to any port 443 proto tcp
```

---

## Scaling & Performance

### Single Server Capacity

**Typical capacity:**
- **Concurrent users:** 50-100
- **Bandwidth:** Depends on file sizes and usage patterns
- **Bottleneck:** Usually disk I/O for large files

### Load Balancing (if needed)

**If single server insufficient:**

1. **Deploy multiple tracks servers** (tracks1, tracks2, tracks3)
2. **Copy public key to all servers** (same key works for all)
3. **Use load balancer** (Nginx, HAProxy, AWS ALB)
4. **Sync track files** (rsync, NFS, or object storage)

**Load balancer config (Nginx):**

```nginx
upstream tracks_backend {
    server tracks1.example.com:443;
    server tracks2.example.com:443;
    server tracks3.example.com:443;
}

server {
    listen 443 ssl;
    server_name tracks.example.com;
    
    location / {
        proxy_pass https://tracks_backend;
        proxy_set_header Host $host;
    }
}
```

---

## Support Contacts

**For issues with:**

- **JWT tokens, authentication:** Contact MOOP development team
- **Track files, data:** Contact MOOP bioinformatics team
- **Server infrastructure:** Your IT team

**Escalation:**
- MOOP team will need: Error logs, token examples (not sensitive), timeline
- Provide: `/var/log/nginx/tracks.error.log` entries

---

## Appendix: validate-jwt.php Script

**This script will be provided by MOOP team. For reference:**

```php
<?php
require_once '/usr/share/php/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$token = $_SERVER['HTTP_X_TOKEN'] ?? $_GET['token'] ?? '';
$request_uri = $_SERVER['HTTP_X_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '';

if (empty($token)) {
    http_response_code(403);
    error_log("Tracks server: No token provided for $request_uri");
    exit("No token");
}

try {
    $public_key_path = '/etc/tracks-server/jwt_public_key.pem';
    if (!file_exists($public_key_path)) {
        http_response_code(500);
        exit("Configuration error");
    }
    
    $public_key = file_get_contents($public_key_path);
    $decoded = JWT::decode($token, new Key($public_key, 'RS256'));
    
    if ($decoded->exp < time()) {
        http_response_code(403);
        exit("Token expired");
    }
    
    // Validate organism/assembly match
    if (preg_match('#/(bigwig|bam)/(\w+)_([^_/]+)_#', $request_uri, $matches)) {
        if ($decoded->organism !== $matches[2] || $decoded->assembly !== $matches[3]) {
            http_response_code(403);
            exit("Token mismatch");
        }
    }
    
    http_response_code(200);
} catch (Exception $e) {
    http_response_code(403);
    error_log("Token validation failed: " . $e->getMessage());
    exit("Invalid token");
}
?>
```

---

## Changelog

- **2026-02-06:** Initial version

---

**Document provided by:** MOOP Development Team  
**Questions?** Contact MOOP team for clarifications
