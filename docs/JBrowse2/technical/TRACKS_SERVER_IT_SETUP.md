# Remote Tracks Server Setup Guide for IT

**Document Version:** 2.1  
**Last Updated:** February 15, 2026  
**For:** IT Team - Remote Tracks Server Deployment

---

## Quick Checklist for AWS Instance Setup

Use this checklist when delegating setup to IT staff or third-party administrators:

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
