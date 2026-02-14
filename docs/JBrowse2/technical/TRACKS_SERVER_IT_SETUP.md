# Remote Tracks Server Setup Guide for IT

**Document Version:** 2.0  
**Last Updated:** February 14, 2026  
**For:** IT Team - Remote Tracks Server Deployment

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

Maintain hierarchical structure:
```
/var/tracks-data/
└── Organism_name/
    └── Assembly_ID/
        ├── bigwig/
        ├── bam/
        ├── gff/
        └── bed/
```

Example:
```bash
/var/tracks-data/Nematostella_vectensis/GCA_033964005.1/bigwig/test.bw
```

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

✅ JWT tokens signed with RS256  
✅ 1-hour token expiry  
✅ Organism/assembly validation  
✅ Directory traversal protection  
✅ HTTPS required  
✅ Whitelisted IP bypass available

---

## Contact

- **JWT/tracks.php issues:** MOOP development team
- **Track files/data:** MOOP bioinformatics team
- **Server infrastructure:** Your IT team

---

**Version:** 2.0  
**Date:** February 14, 2026
