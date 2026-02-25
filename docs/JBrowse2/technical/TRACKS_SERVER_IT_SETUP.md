# Remote Tracks Server Setup Guide

**Version:** 3.1  
**Last Updated:** February 25, 2026  
**Audience:** IT Administrators, DevOps Engineers, System Architects

**Purpose:** Step-by-step guide for deploying a secure, stateless tracks server that serves genomic data files with JWT authentication for MOOP's JBrowse2 integration.

---

## Overview

### What is a Tracks Server?

A **tracks server** is a dedicated file server that:
- Serves genomic data files (BigWig, BAM, VCF, GFF3, etc.)
- Validates JWT tokens before serving files
- Supports HTTP range requests (critical for JBrowse2 performance)
- Requires NO database or sessions (fully stateless)
- Can be scaled horizontally (add more servers as needed)

### Architecture

```
┌────────────────────────┐
│   MOOP Web Server      │
│   (moop.example.com)   │
│                        │
│  • User authentication │
│  • JWT signing         │
│  • Private key stored  │
│  • Config generation   │
└───────────┬────────────┘
            │
            │ HTTPS (config API)
            │ Browser fetches config with JWT tokens
            │
┌───────────▼────────────┐
│   User Browser         │
│   (JBrowse2 React app) │
└───────────┬────────────┘
            │
            │ HTTPS (track data requests with JWT)
            ↓
┌────────────────────────┐
│   Tracks Server        │
│   (tracks.example.com) │
│                        │
│  • JWT verification    │
│  • Public key only     │
│  • File serving        │
│  • Range requests      │
│  • NO database         │
└────────────────────────┘
```

**Key Principle:** MOOP server keeps the private key (signs tokens), tracks servers only get the public key (verify tokens). Compromised tracks server cannot forge tokens.

---

## Quick Start Checklist

Use this checklist when setting up a new tracks server:

### Prerequisites
- [ ] Ubuntu 20.04+ or Debian-based Linux
- [ ] Root/sudo access
- [ ] Domain name or IP address
- [ ] SSL certificate (Let's Encrypt or AWS Certificate Manager)
- [ ] Contact info for MOOP administrator (to get files)

### Installation Steps
- [ ] Install web server (Apache recommended)
- [ ] Install PHP 7.4+ with required extensions
- [ ] Install Composer
- [ ] Install NTP (time synchronization - critical!)
- [ ] Create directory structure
- [ ] Install PHP dependencies (firebase/php-jwt)

### Configuration
- [ ] Obtain files from MOOP team (see below)
- [ ] Deploy `tracks.php` endpoint
- [ ] Deploy JWT public key
- [ ] Deploy validation libraries
- [ ] Configure Apache/Nginx virtual host
- [ ] Configure CORS headers
- [ ] Configure SSL/TLS

### Testing
- [ ] Test without token (should return 401)
- [ ] Test with invalid token (should return 403)
- [ ] Test with valid token (MOOP team provides)
- [ ] Test HTTP range requests
- [ ] Verify CORS headers
- [ ] Check error logging

### Monitoring
- [ ] Set up log rotation
- [ ] Configure monitoring for 403/401 errors
- [ ] Test alerting for failures

---

## Detailed Setup Instructions

### Step 1: System Preparation

```bash
# Update system
sudo apt-get update
sudo apt-get upgrade -y

# Install required packages
sudo apt-get install -y \
    apache2 \
    libapache2-mod-php8.1 \
    php8.1-cli \
    php8.1-json \
    php8.1-mbstring \
    composer \
    ntp \
    certbot \
    python3-certbot-apache

# Enable Apache modules
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod rewrite
sudo a2enmod php8.1

# Start and enable services
sudo systemctl start apache2 ntp
sudo systemctl enable apache2 ntp

# Verify NTP is syncing (CRITICAL for JWT expiry validation)
timedatectl status
```

**Why NTP is Critical:**
JWT tokens have an `exp` (expiration) claim with Unix timestamp. If server clocks are out of sync by more than a few minutes, valid tokens will be rejected or expired tokens will be accepted. NTP keeps clocks synchronized.

### Step 2: Create Directory Structure

```bash
# Create application directories
sudo mkdir -p /var/www/tracks-server/{api,lib,certs}
sudo mkdir -p /var/tracks-data

# Set ownership
sudo chown -R www-data:www-data /var/www/tracks-server
sudo chown -R www-data:www-data /var/tracks-data

# Set permissions
sudo chmod 755 /var/www/tracks-server
sudo chmod 755 /var/tracks-data
```

**Directory purposes:**
- `/var/www/tracks-server/api/` - PHP endpoints (tracks.php)
- `/var/www/tracks-server/lib/` - PHP libraries (token validation)
- `/var/www/tracks-server/certs/` - JWT public key
- `/var/tracks-data/` - Genomic data files

### Step 3: Install PHP Dependencies

```bash
cd /var/www/tracks-server

# Create composer.json
sudo cat > composer.json <<'EOF'
{
    "require": {
        "firebase/php-jwt": "^6.0"
    }
}
EOF

# Install dependencies
sudo composer install

# Verify installation
php -r "require 'vendor/autoload.php'; use Firebase\JWT\JWT; echo 'JWT library loaded successfully\n';"
```

### Step 4: Obtain Files from MOOP Team

Contact your MOOP administrator to obtain these files:

**Required files:**
1. `tracks.php` - Main file serving endpoint
2. `track_token.php` - JWT validation functions
3. `functions_access.php` - Access control helpers
4. `jwt_public_key.pem` - RSA public key for token verification

**File placement:**
```bash
/var/www/tracks-server/
├── api/
│   └── tracks.php              # Obtained from MOOP team
├── lib/
│   ├── track_token.php         # Obtained from MOOP team
│   └── functions_access.php     # Obtained from MOOP team
├── certs/
│   └── jwt_public_key.pem      # Obtained from MOOP team
├── vendor/                     # Created by composer
└── composer.json
```

**Verification:**
```bash
# Check files exist
ls -la /var/www/tracks-server/api/tracks.php
ls -la /var/www/tracks-server/lib/track_token.php
ls -la /var/www/tracks-server/certs/jwt_public_key.pem

# Verify public key format
openssl rsa -pubin -in /var/www/tracks-server/certs/jwt_public_key.pem -text -noout | head -1
# Should output: Public-Key: (2048 bit)
```

### Step 5: Configure Apache Virtual Host

**IMPORTANT:** This configuration includes CRITICAL security directives to block direct file access.

**For domain:** tracks.example.com

```bash
# Create virtual host configuration
sudo nano /etc/apache2/sites-available/tracks.example.com.conf
```

**Configuration:**
```apache
<VirtualHost *:80>
    ServerName tracks.example.com
    
    # Redirect all HTTP to HTTPS
    Redirect permanent / https://tracks.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName tracks.example.com
    DocumentRoot /var/www/tracks-server
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/tracks.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/tracks.example.com/privkey.pem
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLCipherSuite HIGH:!aNULL:!MD5
    
    # CORS Headers (CRITICAL - adjust origin to match your MOOP server)
    Header always set Access-Control-Allow-Origin "https://moop.example.com"
    Header always set Access-Control-Allow-Methods "GET, HEAD, OPTIONS"
    Header always set Access-Control-Allow-Headers "Range, Authorization"
    Header always set Access-Control-Expose-Headers "Content-Range, Content-Length, Accept-Ranges"
    Header always set Access-Control-Max-Age "3600"
    
    # Handle OPTIONS preflight requests
    <If "%{REQUEST_METHOD} == 'OPTIONS'">
        Header set Content-Length "0"
        Header set Content-Type "text/plain"
        Redirect 204 /
    </If>
    
    # Application directory
    <Directory /var/www/tracks-server>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        
        # PHP configuration
        php_flag display_errors Off
        php_flag log_errors On
        php_value error_log /var/log/apache2/tracks-php-error.log
    </Directory>
    
    # ========================================================================
    # CRITICAL SECURITY: Block direct access to track data files
    # ========================================================================
    # All track requests MUST go through tracks.php with JWT validation
    # Direct file access would bypass authentication entirely
    <Directory /var/tracks-data>
        # Block all direct access
        Require all denied
        
        # Note: tracks.php will still have filesystem access via PHP
        # This only blocks HTTP requests
    </Directory>
    
    # Block direct access to libraries and certificates
    <DirectoryMatch "^/var/www/tracks-server/(lib|certs|vendor)">
        Require all denied
    </DirectoryMatch>
    
    # Logging
    ErrorLog /var/log/apache2/tracks-error.log
    CustomLog /var/log/apache2/tracks-access.log combined
    LogLevel warn
</VirtualHost>
```

**Security Notes:**
- The `<Directory /var/tracks-data>` block is **CRITICAL**
- Without this, anyone can access files directly via HTTP
- tracks.php has filesystem access and serves files after validating JWT
- This prevents bypassing authentication

**Enable site:**
```bash
# Enable virtual host
sudo a2ensite tracks.example.com.conf

# Test configuration
sudo apache2ctl configtest
# Expected: Syntax OK

# Reload Apache
sudo systemctl reload apache2
```

### Step 5b: Alternative - Nginx Configuration

If using nginx instead of Apache:

```nginx
server {
    listen 443 ssl http2;
    server_name tracks.example.com;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/tracks.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tracks.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    root /var/www/tracks-server;
    index index.php;
    
    # CORS Headers
    add_header 'Access-Control-Allow-Origin' 'https://moop.example.com' always;
    add_header 'Access-Control-Allow-Methods' 'GET, HEAD, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Range, Authorization' always;
    add_header 'Access-Control-Expose-Headers' 'Content-Range, Content-Length, Accept-Ranges' always;
    
    # ========================================================================
    # CRITICAL SECURITY: Block direct access to track data
    # ========================================================================
    location ~ ^/tracks-data/ {
        deny all;
        return 403 "Access denied. Files must be accessed through API with JWT token.";
    }
    
    # Block direct access to sensitive directories
    location ~ ^/(lib|certs|vendor)/ {
        deny all;
    }
    
    # API endpoint (tracks.php)
    location ~ ^/api/tracks\.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Increase timeout for large files
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
    
    # Handle OPTIONS preflight
    if ($request_method = 'OPTIONS') {
        return 204;
    }
}

# HTTP redirect to HTTPS
server {
    listen 80;
    server_name tracks.example.com;
    return 301 https://$server_name$request_uri;
}
```

**Apply nginx config:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

### Step 6: Obtain SSL Certificate

```bash
# Using Let's Encrypt (free, automated)
sudo certbot --apache -d tracks.example.com

# Or using AWS Certificate Manager
# 1. Request certificate in AWS console
# 2. Validate domain ownership
# 3. Download certificate files
# 4. Copy to /etc/ssl/certs/ and update Apache config paths
```

### Step 7: Upload Track Data Files

Track files must follow this directory structure:

```
/var/tracks-data/
├── Organism_name/
│   ├── Assembly_ID/
│   │   ├── bigwig/
│   │   │   ├── sample1.bw
│   │   │   └── sample2.bw
│   │   ├── bam/
│   │   │   ├── reads1.bam
│   │   │   ├── reads1.bam.bai
│   │   │   └── reads2.bam
│   │   └── gff/
│   │       └── genes.gff3.gz
```

**Path requirements:**
- ✅ First two components MUST be: `Organism_name/Assembly_ID/`
- ✅ After Assembly_ID, any subdirectory structure is allowed
- ✅ Filenames can be anything

**Examples:**
```
✅ Nematostella_vectensis/GCA_033964005.1/bigwig/rnaseq.bw
✅ Nematostella_vectensis/GCA_033964005.1/experiment2/data.bam
✅ Organism_A/Assembly_1/project/subfolder/sample.vcf.gz
❌ Organism_A/track.bw  (missing assembly component)
```

**Upload methods:**
```bash
# SCP from local machine
scp -r /path/to/local/tracks/* admin@tracks.example.com:/var/tracks-data/

# rsync (better for large datasets)
rsync -avz --progress /path/to/local/tracks/ admin@tracks.example.com:/var/tracks-data/

# AWS S3 (if using AWS)
aws s3 sync s3://my-bucket/tracks/ /var/tracks-data/
```

**Set permissions:**
```bash
sudo chown -R www-data:www-data /var/tracks-data
sudo find /var/tracks-data -type d -exec chmod 755 {} \;
sudo find /var/tracks-data -type f -exec chmod 644 {} \;
```

---

## Testing

### Test 1: No Token (Should Fail)

```bash
curl -I https://tracks.example.com/api/tracks.php?file=Organism/Assembly/bigwig/test.bw
```

**Expected response:**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json
```

**Body:**
```json
{"error":"Authentication required"}
```

✅ Pass if you get 401

### Test 2: Invalid Token (Should Fail)

```bash
curl -I "https://tracks.example.com/api/tracks.php?file=Organism/Assembly/bigwig/test.bw&token=invalid"
```

**Expected response:**
```
HTTP/1.1 403 Forbidden
Content-Type: application/json
```

**Body:**
```json
{"error":"Invalid or expired token"}
```

✅ Pass if you get 403

### Test 3: Valid Token (Should Succeed)

**Obtain valid token from MOOP administrator:**
```bash
# MOOP admin generates test token
# On MOOP server:
php -r "
require 'lib/jbrowse/track_token.php';
session_start();
\$_SESSION['username'] = 'test_user';
\$token = generateTrackToken('Nematostella_vectensis', 'GCA_033964005.1', 'PUBLIC');
echo \$token . PHP_EOL;
"
```

**Test with valid token:**
```bash
TOKEN="<paste token here>"
curl -I "https://tracks.example.com/api/tracks.php?file=Nematostella_vectensis/GCA_033964005.1/bigwig/test.bw&token=$TOKEN"
```

**Expected response:**
```
HTTP/1.1 200 OK
Content-Type: application/octet-stream
Accept-Ranges: bytes
Content-Length: 12345
Access-Control-Allow-Origin: https://moop.example.com
```

✅ Pass if you get 200 and CORS headers present

### Test 4: Range Request

```bash
TOKEN="<valid token>"
curl -H "Range: bytes=0-1000" \
     "https://tracks.example.com/api/tracks.php?file=Nematostella_vectensis/GCA_033964005.1/bigwig/test.bw&token=$TOKEN"
```

**Expected response:**
```
HTTP/1.1 206 Partial Content
Content-Range: bytes 0-1000/12345
Content-Length: 1001
Accept-Ranges: bytes
```

✅ Pass if you get 206 (Partial Content)

### Test 5: Token Mismatch (Should Fail)

```bash
# Token for Organism_A/Assembly_1, but requesting Organism_B/Assembly_2
TOKEN="<token for Organism_A/Assembly_1>"
curl -I "https://tracks.example.com/api/tracks.php?file=Organism_B/Assembly_2/bigwig/test.bw&token=$TOKEN"
```

**Expected response:**
```
HTTP/1.1 403 Forbidden
Content-Type: application/json
```

**Body:**
```json
{"error":"Token does not grant access to this file"}
```

✅ Pass if you get 403

### Test 6: CORS Headers

```bash
# Preflight request
curl -X OPTIONS -H "Origin: https://moop.example.com" \
     -H "Access-Control-Request-Method: GET" \
     -I https://tracks.example.com/api/tracks.php
```

**Expected response:**
```
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: https://moop.example.com
Access-Control-Allow-Methods: GET, HEAD, OPTIONS
Access-Control-Allow-Headers: Range, Authorization
Access-Control-Max-Age: 3600
```

✅ Pass if CORS headers present

---

## Troubleshooting

### Issue: All Tokens Rejected with "Invalid or expired token"

**Possible causes:**
1. **Clock skew** - Server time out of sync
   ```bash
   # Check time
   date
   timedatectl status
   
   # Force NTP sync
   sudo systemctl restart ntp
   sudo ntpq -p
   ```

2. **Wrong public key** - Key doesn't match MOOP's private key
   ```bash
   # Get public key fingerprint from MOOP admin
   # Compare with tracks server key
   openssl rsa -pubin -in /var/www/tracks-server/certs/jwt_public_key.pem -outform DER | openssl dgst -sha256
   ```

3. **File permissions** - PHP can't read public key
   ```bash
   sudo chmod 644 /var/www/tracks-server/certs/jwt_public_key.pem
   sudo chown www-data:www-data /var/www/tracks-server/certs/jwt_public_key.pem
   ```

### Issue: CORS Errors in Browser

**Symptoms:** Browser console shows:
```
Access to fetch at 'https://tracks.example.com/...' from origin 'https://moop.example.com' has been blocked by CORS policy
```

**Fix:**
1. Verify `Access-Control-Allow-Origin` header matches MOOP domain exactly
2. Check preflight OPTIONS requests are handled
3. Ensure headers set for error responses too (use `Header always set`)

```bash
# Test CORS
curl -H "Origin: https://moop.example.com" \
     -I "https://tracks.example.com/api/tracks.php?file=test"
     
# Should see: Access-Control-Allow-Origin: https://moop.example.com
```

### Issue: Files Not Found (404)

**Check:**
```bash
# Verify file exists
ls -la /var/tracks-data/Organism/Assembly/bigwig/test.bw

# Check permissions
sudo -u www-data cat /var/tracks-data/Organism/Assembly/bigwig/test.bw > /dev/null
# Should succeed without "Permission denied"

# Check tracks.php base path
grep "TRACKS_BASE_DIR" /var/www/tracks-server/api/tracks.php
# Should be: /var/tracks-data
```

### Issue: Range Requests Not Working

**Symptoms:** JBrowse2 loads entire file instead of chunks

**Fix:**
```bash
# Verify HTTP 206 support
curl -H "Range: bytes=0-100" -I "<track URL with token>"
# Should return: HTTP/1.1 206 Partial Content

# Check Apache config allows range requests (should be default)
```

---

## Security Considerations

### IP Whitelisting (Optional)

If tracks server is on same network as MOOP server, you can bypass JWT for internal IPs:

**Edit:** `lib/track_token.php` - Function `isWhitelistedIP()`

```php
function isWhitelistedIP() {
    $trusted_ranges = [
        ['10.0.0.0', '10.255.255.255'],         // Your internal network
        ['192.168.1.0', '192.168.1.255'],       // Specific subnet
        ['172.31.0.0', '172.31.255.255']        // AWS VPC range
    ];
    
    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $visitor_ip_long = ip2long($visitor_ip);
    
    if ($visitor_ip_long === false) {
        return false;
    }
    
    foreach ($trusted_ranges as $range) {
        $start_long = ip2long($range[0]);
        $end_long = ip2long($range[1]);
        
        if ($visitor_ip_long >= $start_long && $visitor_ip_long <= $end_long) {
            return true;
        }
    }
    
    return false;
}
```

**Security note:** Only whitelist IPs you fully control. External collaborators should always use JWT tokens.

### Firewall Rules

```bash
# Allow HTTPS only
sudo ufw allow 443/tcp
sudo ufw deny 80/tcp  # Optional: if you don't want HTTP redirect

# Allow SSH (for management)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

### Regular Updates

```bash
# Create update script
sudo cat > /usr/local/bin/update-tracks-server.sh <<'EOF'
#!/bin/bash
apt-get update
apt-get upgrade -y
composer update --working-dir=/var/www/tracks-server
systemctl restart apache2
EOF

sudo chmod +x /usr/local/bin/update-tracks-server.sh

# Schedule monthly updates (cron)
sudo crontab -e
# Add: 0 2 1 * * /usr/local/bin/update-tracks-server.sh
```

---

## Monitoring

### Log Monitoring

```bash
# Watch error log in real-time
sudo tail -f /var/log/apache2/tracks-error.log

# Watch access log
sudo tail -f /var/log/apache2/tracks-access.log

# Find token validation failures
sudo grep "JWT verification failed" /var/log/apache2/tracks-error.log | tail -20

# Find 403 errors (permission denials)
sudo grep " 403 " /var/log/apache2/tracks-access.log | tail -20
```

### Alerting

**Set up alerts for:**
- Spike in 403 errors (possible attack)
- Spike in 401 errors (expired tokens, misconfiguration)
- Disk space low (track data storage)
- High CPU/memory usage

**Example: Simple email alert on many 403s**
```bash
#!/bin/bash
# /usr/local/bin/check-tracks-errors.sh

COUNT=$(grep " 403 " /var/log/apache2/tracks-access.log | grep "$(date +%Y-%m-%d)" | wc -l)

if [ $COUNT -gt 100 ]; then
    echo "ALERT: $COUNT 403 errors on tracks server today" | \
        mail -s "Tracks Server Alert" admin@example.com
fi
```

**Schedule check:**
```bash
# Run hourly
sudo crontab -e
# Add: 0 * * * * /usr/local/bin/check-tracks-errors.sh
```

---

## Scaling

### Adding More Tracks Servers

**Scenario:** Single tracks server can't handle load, want to add more

**Steps:**

1. **Set up new server** (follow this guide)
2. **Copy public key** from first server
   ```bash
   scp tracks1:/var/www/tracks-server/certs/jwt_public_key.pem \
       tracks2:/var/www/tracks-server/certs/
   ```
3. **Sync track data**
   ```bash
   rsync -avz tracks1:/var/tracks-data/ tracks2:/var/tracks-data/
   ```
4. **Update track URLs in MOOP metadata** to point to new server
5. **Set up load balancer** (optional)

**Load balancer example (Nginx):**
```nginx
upstream tracks_servers {
    server tracks1.example.com:443;
    server tracks2.example.com:443;
    server tracks3.example.com:443;
}

server {
    listen 443 ssl;
    server_name tracks.example.com;
    
    location / {
        proxy_pass https://tracks_servers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

---

## Maintenance

### Key Rotation

When MOOP admin rotates JWT keys (annually recommended):

```bash
# 1. Receive new public key from MOOP admin
scp moop-admin@moop.example.com:/data/moop/certs/jwt_public_key.pem /tmp/

# 2. Backup old key
sudo cp /var/www/tracks-server/certs/jwt_public_key.pem \
        /var/www/tracks-server/certs/jwt_public_key_$(date +%Y%m%d).pem.bak

# 3. Deploy new key
sudo mv /tmp/jwt_public_key.pem /var/www/tracks-server/certs/
sudo chown www-data:www-data /var/www/tracks-server/certs/jwt_public_key.pem
sudo chmod 644 /var/www/tracks-server/certs/jwt_public_key.pem

# 4. No restart needed - PHP reads key on each request
```

### Log Rotation

```bash
# Configure logrotate
sudo nano /etc/logrotate.d/tracks-server
```

```
/var/log/apache2/tracks-*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 640 root adm
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

---

## FAQ

**Q: Do I need a database?**  
A: No! The tracks server is completely stateless. All authentication is done via JWT tokens.

**Q: Can I use Nginx instead of Apache?**  
A: Yes, but Apache configuration is simpler for this use case. Nginx requires more complex JWT validation setup.

**Q: How much storage do I need?**  
A: Depends on your data. Typical: 1-10GB per assembly. Plan for growth. Use EBS/network storage for flexibility.

**Q: Can multiple MOOP servers use the same tracks server?**  
A: Yes, but they must share the same JWT key pair. Each MOOP server signs with the private key, tracks server verifies with the public key.

**Q: What happens if tracks server goes down?**  
A: JBrowse2 will show track loading errors. Users can still view assemblies but won't see track data. Deploy multiple tracks servers with load balancing for high availability.

**Q: Do I need to restart after uploading new track files?**  
A: No. Files are served directly from disk. Just ensure proper permissions.

**Q: How do I troubleshoot JWT errors?**  
A: Check `/var/log/apache2/tracks-error.log` for specific PHP errors. Common issues: clock skew, wrong public key, file permissions.

---

## Support

**For setup questions:** Contact your MOOP administrator  
**For security issues:** Report immediately to MOOP security team  
**For JBrowse2 questions:** See MOOP JBrowse2 documentation

**Required files from MOOP team:**
- `tracks.php`
- `track_token.php`
- `functions_access.php`
- `jwt_public_key.pem`

---

**Document Version:** 3.0  
**Last Updated:** February 18, 2026  
**Tested Platforms:** Ubuntu 20.04, Ubuntu 22.04, Debian 11  
**Apache Version:** 2.4+  
**PHP Version:** 7.4+, 8.0+, 8.1+

### AWS Instance Prerequisites Checklist:

**1. Instance Specs:**
- OS: Ubuntu 20.04+ (or similar Debian-based)
- Ensure NTP is available for time synchronization (critical for JWT validation)

**2. Software to Install:**
```bash
sudo apt-get update
sudo apt-get install -y apache2 libapache2-mod-php8.1 php8.1-cli \
    php8.1-json php8.1-mbstring composer ntp
```

**3. Directory Structure to Create:**
```bash
sudo mkdir -p /var/www/tracks-server/{api,lib,certs}
sudo mkdir -p /var/tracks-data
sudo chown -R www-data:www-data /var/www/tracks-server /var/tracks-data
```

**4. Composer Dependency:**
```bash
cd /var/www/tracks-server
sudo composer require firebase/php-jwt
```

**5. SSL Certificate:**
- Obtain SSL certificate for HTTPS (required)
- Can use AWS Certificate Manager or Let's Encrypt
- Configure for your domain (e.g., `tracks.yourdomain.com`)

**6. Network Configuration:**
- Open port 443 (HTTPS) in security group
- Configure DNS for tracks server domain
- Ensure CORS allows origin from main MOOP server domain

**7. Storage:**
- Size depends on track data volume
- `/var/tracks-data` for genome browser files
- Consider mounting EBS volume for track data
- Track files should follow structure: `/var/tracks-data/Organism_name/Assembly_ID/type/filename`
  - **Required:** First two path components (Organism_name/Assembly_ID) must match JWT token permissions
  - **Flexible:** Any subdirectory structure after Assembly_ID is allowed (e.g., `experiment2/sample.bam`)
  - **Recommended:** Use type directories (bigwig, bam, vcf, etc.) for organization

**8. Files from MOOP Team (required before launch):**

Place these files in the specified locations:
- `/var/www/tracks-server/api/tracks.php`
- `/var/www/tracks-server/lib/track_token.php`
- `/var/www/tracks-server/lib/functions_access.php`
- `/var/www/tracks-server/certs/jwt_public_key.pem` (public key for JWT validation)

**Important:** The JWT public key must match the private key on the main MOOP server.

**9. Apache Configuration:**
- See "Configure Apache" section below for complete virtual host configuration
- Includes required CORS headers for JBrowse2

**10. Testing:**
- Use curl commands in "Testing" section to verify setup
- Test both token validation and range requests

**11. Optional:**
- Whitelisted IP addresses if bypassing JWT for internal network

---

## Overview

This document provides instructions for setting up a dedicated tracks server using the **tracks.php** endpoint to serve JBrowse2 genome browser track files with JWT token authentication.

### Changes from v1.0

- **Simplified architecture:** Single PHP endpoint (tracks.php) handles everything
- **No Nginx auth_request needed:** PHP does validation and file serving
- **Hierarchical file paths:** organism/assembly/type/filename structure
- **Whitelisted IP support:** Optional bypass for internal network
- **Easier deployment:** Fewer components to configure

### Architecture

```
┌─────────────────────────┐
│   MOOP Web Server       │
│   (moop.example.com)    │
│  - User authentication  │
│  - JWT generation       │
│  - Private key (signs)  │
└──────────┬──────────────┘
            │ HTTPS
            ↓
 ┌─────────────────────────┐
 │  Tracks Server          │
 │  (tracks.example.com)   │
 │  - tracks.php (validates│
 │    JWT & serves files)  │
 │  - Public key (verifies)│
 │  - Track data files     │
 └─────────────────────────┘
```

### Key Components

- **tracks.php** - Single endpoint that:
  - Validates JWT tokens
  - Checks organism/assembly permissions
  - Prevents directory traversal
  - Serves files with range request support
  - Handles whitelisted IPs

---

## Prerequisites

### Software Requirements

- **OS:** Ubuntu 20.04+ or similar
- **Web Server:** Apache or Nginx  
- **PHP:** 7.4+ with extensions: json, mbstring
- **Composer:** For dependency management
- **SSL Certificate:** For HTTPS
- **NTP:** Time synchronization

### Installation

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y apache2 libapache2-mod-php8.1 php8.1-cli \
    php8.1-json php8.1-mbstring composer ntp

# Start services
sudo systemctl start apache2 ntp
sudo systemctl enable apache2 ntp
```

---

## Setup Steps

### 1. Create Directory Structure

```bash
sudo mkdir -p /var/www/tracks-server/{api,lib,certs}
sudo mkdir -p /var/tracks-data
sudo chown -R www-data:www-data /var/www/tracks-server /var/tracks-data
```

### 2. Install PHP Dependencies

```bash
cd /var/www/tracks-server
sudo composer require firebase/php-jwt
```

### 3. Get Files from MOOP Team

MOOP team will provide:
- `tracks.php` (main endpoint)
- `track_token.php` (JWT validation)
- `functions_access.php` (access control)
- `jwt_public_key.pem` (public key)

Place them in:
```
/var/www/tracks-server/api/tracks.php
/var/www/tracks-server/lib/track_token.php
/var/www/tracks-server/lib/functions_access.php
/var/www/tracks-server/certs/jwt_public_key.pem
```

### 4. Configure Apache

```apache
<VirtualHost *:443>
    ServerName tracks.example.com
    DocumentRoot /var/www/tracks-server
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/your-cert.crt
    SSLCertificateKeyFile /etc/ssl/private/your-key.key
    
    # CORS Headers
    Header always set Access-Control-Allow-Origin "https://moop.example.com"
    Header always set Access-Control-Allow-Methods "GET, HEAD, OPTIONS"
    Header always set Access-Control-Allow-Headers "Range"
    Header always set Access-Control-Expose-Headers "Content-Range, Content-Length, Accept-Ranges"
    
    <Directory /var/www/tracks-server>
        Require all granted
    </Directory>
    
    ErrorLog /var/log/apache2/tracks-error.log
    CustomLog /var/log/apache2/tracks-access.log combined
</VirtualHost>
```

### 5. Upload Track Files

Maintain hierarchical structure (recommended organization):
```
/var/tracks-data/
└── Organism_name/
    └── Assembly_ID/
        ├── bigwig/
        ├── bam/
        ├── gff/
        └── bed/
```

Examples:
```bash
# Standard organization (recommended)
/var/tracks-data/Nematostella_vectensis/GCA_033964005.1/bigwig/test.bw
/var/tracks-data/Nematostella_vectensis/GCA_033964005.1/bam/sample.bam

# Custom subdirectories (also supported)
/var/tracks-data/Nematostella_vectensis/GCA_033964005.1/experiment2/test.bw
/var/tracks-data/Nematostella_vectensis/GCA_033964005.1/project/subfolder/sample.bam
```

**Path Requirements:**
- ✅ Must start with: `Organism_name/Assembly_ID/`
- ✅ Can have any subdirectory structure after Assembly_ID
- ✅ Organism/Assembly must match JWT token permissions

---

## Testing

### Test Token Validation

```bash
# Without token (should return 401)
curl -I https://tracks.example.com/api/tracks.php?file=test.bw

# With valid token (MOOP team provides)
TOKEN="xxx"
curl -I "https://tracks.example.com/api/tracks.php?file=Organism/Assembly/bigwig/test.bw&token=$TOKEN"
```

### Test Range Requests

```bash
curl -H "Range: bytes=0-1000" "https://tracks.example.com/api/tracks.php?file=path&token=$TOKEN"
# Should return HTTP 206 Partial Content
```

---

## Monitoring

```bash
# Error log
tail -f /var/log/apache2/tracks-error.log

# Access log  
tail -f /var/log/apache2/tracks-access.log

# Check for auth failures
grep "403\|401" /var/log/apache2/tracks-access.log
```

---

## Troubleshooting

| Error | Cause | Solution |
|-------|-------|----------|
| 401 | No token | Add ?token=... to URL |
| 403 | Invalid/expired token | Get fresh token, check clock sync |
| 403 | Wrong organism/assembly | Check file path matches token |
| 404 | File not found | Verify file exists and permissions |
| 500 | Config error | Check public key file exists |

---

## Security

✅ **Current Implementation:**
- JWT tokens signed with RS256 (2048-bit RSA asymmetric keys)
- 1-hour token expiry  
- Organism/assembly claim validation against file paths
- Directory traversal protection  
- HTTPS required  
- Whitelisted IP bypass available for internal networks
- Stateless token verification (no database needed on tracks server)

**Security Properties:**
- Private key stays on MOOP server (signs tokens)
- Public key deployed to tracks servers (verifies tokens)
- Compromised tracks server cannot forge tokens
- Each token locked to specific organism/assembly pair
- Path validation: Only first two components (organism/assembly) are enforced
- Flexible subdirectory structure after assembly (e.g., `experiment2/sample.bam`)

---

## Contact

- **JWT/tracks.php issues:** MOOP development team
- **Track files/data:** MOOP bioinformatics team
- **Server infrastructure:** Your IT team

---

**Version:** 2.0  
**Date:** February 14, 2026
