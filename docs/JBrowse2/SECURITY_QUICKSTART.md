# MOOP JBrowse2 Security Quick Start Guide

**For:** System Administrators  
**Last Updated:** 2026-02-25  
**Version:** 1.0

---

## 🚨 CRITICAL: First-Time Setup

If you're setting up MOOP for the first time or haven't configured web server security, follow these steps **immediately**.

---

## Step 1: Block Direct File Access (REQUIRED)

### Why This Is Critical

Without this configuration, **anyone can download your track files without authentication** if they know the file path.

**Vulnerable:**
```
http://your-server.com/moop/data/tracks/Organism/Assembly/file.bw
→ File downloads, NO AUTHENTICATION ❌
```

**Secure:**
```
http://your-server.com/moop/api/jbrowse2/tracks.php?file=...&token=...
→ JWT validated, file served only if authorized ✅
```

---

### Apache Setup (Recommended: .htaccess)

#### Option A: Using .htaccess File

**1. Check if .htaccess exists:**
```bash
ls -la /var/www/html/moop/data/tracks/.htaccess
```

**2. If missing, create it:**
```bash
cat > /var/www/html/moop/data/tracks/.htaccess << 'EOF'
# SECURITY: Block direct access to track files
<IfVersion >= 2.4>
    Require all denied
</IfVersion>
<IfVersion < 2.4>
    Order Deny,Allow
    Deny from all
</IfVersion>
ErrorDocument 403 "Access denied. Track files must be accessed through the API endpoint with valid JWT token."
EOF
```

**3. Enable .htaccess support:**
```bash
# Edit your site configuration
sudo nano /etc/apache2/sites-available/moop.conf
```

Find the `<Directory>` block and ensure `AllowOverride All`:
```apache
<Directory /var/www/html/moop>
    Options Indexes FollowSymLinks
    AllowOverride All  # ← MUST be "All", not "None"
    Require all granted
</Directory>
```

**4. Test and restart:**
```bash
sudo apache2ctl configtest
sudo systemctl restart apache2
```

**5. Verify blocking works:**
```bash
curl -I http://localhost/moop/data/tracks/test.bw
# Expected: HTTP/1.1 403 Forbidden
```

---

#### Option B: Server Configuration Only

If you prefer server config over .htaccess:

```bash
sudo nano /etc/apache2/sites-available/moop.conf
```

Add this block:
```apache
<Directory /var/www/html/moop/data/tracks>
    Require all denied
</Directory>

<Directory /var/www/html/moop/data/genomes>
    Require all denied
</Directory>
```

Test and restart:
```bash
sudo apache2ctl configtest
sudo systemctl restart apache2
```

---

### Nginx Setup

**1. Edit your site configuration:**
```bash
sudo nano /etc/nginx/sites-available/moop
```

**2. Add these location blocks:**
```nginx
server {
    # ... existing config ...
    
    # Block direct access to track files
    location ~ ^/moop/data/tracks/ {
        deny all;
        return 403 "Access denied. Track files must be accessed through the API endpoint with valid JWT token.";
    }
    
    # Block direct access to genome files
    location ~ ^/moop/data/genomes/ {
        deny all;
        return 403 "Access denied. Genome files must be accessed through the API endpoint with valid JWT token.";
    }
    
    # Allow API endpoints
    location ~ ^/moop/api/ {
        try_files $uri $uri/ /index.php?$query_string;
        
        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }
}
```

**3. Test and reload:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Step 2: Verify JWT Keys Exist

```bash
# Check for JWT keys
ls -la /var/www/html/moop/certs/jwt_*.pem

# Should see:
# -rw------- jwt_private_key.pem (600 permissions)
# -rw-r--r-- jwt_public_key.pem  (644 permissions)
```

If keys don't exist, generate them:
```bash
cd /var/www/html/moop/certs
openssl genrsa -out jwt_private_key.pem 2048
openssl rsa -in jwt_private_key.pem -pubout -out jwt_public_key.pem
chmod 600 jwt_private_key.pem
chmod 644 jwt_public_key.pem
```

---

## Step 3: Test Security Configuration

### Test 1: Direct Access Should Be BLOCKED ✅

```bash
curl -I http://localhost/moop/data/tracks/any/file.bw

# Expected output:
# HTTP/1.1 403 Forbidden
```

❌ If you get `HTTP/1.1 200 OK` → Security is NOT working, check configuration

---

### Test 2: API Without Token Should Be DENIED ✅

```bash
curl -s http://localhost/moop/api/jbrowse2/tracks.php?file=test.bw

# Expected output:
# {"error":"Authentication required"}
```

---

### Test 3: JBrowse2 Should Load Normally ✅

1. Open MOOP in browser
2. Navigate to JBrowse2
3. Select an assembly
4. Tracks should load normally
5. Check browser console (F12) for errors

❌ If tracks don't load → Check error logs

---

## Step 4: Check Error Logs

```bash
# Apache
sudo tail -f /var/log/apache2/error.log

# Nginx
sudo tail -f /var/log/nginx/error.log

# PHP
sudo tail -f /var/log/php8.1-fpm.log
```

**Look for:**
- ❌ "JWT private key not found"
- ❌ "Failed to generate token"
- ❌ "Authentication required"
- ✅ "Valid token for user X accessing Organism/Assembly"

---

## Step 5: Security Checklist

After configuration, verify:

- [ ] Direct file access returns 403 Forbidden
- [ ] API without token returns 401 Unauthorized
- [ ] API with invalid token returns 403 Forbidden
- [ ] JBrowse2 loads tracks successfully
- [ ] No errors in browser console
- [ ] JWT keys exist with correct permissions
- [ ] Apache/nginx restarted after configuration
- [ ] Changes tested from external network (not just localhost)

---

## Common Issues & Solutions

### Issue: .htaccess not working (still get 200 OK)

**Cause:** Apache not configured to read .htaccess files

**Solution:**
```bash
# Check Apache config
grep -r "AllowOverride" /etc/apache2/sites-enabled/

# Must be "All", not "None"
sudo nano /etc/apache2/sites-available/moop.conf
# Change: AllowOverride None → AllowOverride All

sudo systemctl restart apache2
```

---

### Issue: Tracks don't load in JBrowse2

**Possible causes:**
1. JWT keys missing
2. Wrong file permissions
3. tracks.php not accessible

**Debug:**
```bash
# Check JWT keys
ls -la /var/www/html/moop/certs/

# Check tracks.php is accessible
curl -s http://localhost/moop/api/jbrowse2/tracks.php?file=test.bw
# Should get: {"error":"Authentication required"}

# Check PHP error log
sudo tail -f /var/log/php8.1-fpm.log
```

---

### Issue: 500 Internal Server Error

**Cause:** Usually PHP syntax error or missing dependencies

**Solution:**
```bash
# Check PHP syntax
php -l /var/www/html/moop/api/jbrowse2/tracks.php

# Check dependencies
cd /var/www/html/moop
composer install

# Check error log
sudo tail -50 /var/log/apache2/error.log
```

---

## Production Deployment Checklist

Before going live:

- [ ] Web server blocks direct file access (tested)
- [ ] HTTPS enabled with valid certificate
- [ ] JWT keys generated with secure permissions
- [ ] Session security configured (HttpOnly, Secure cookies)
- [ ] Error logging enabled
- [ ] Firewall configured
- [ ] Backup procedures in place
- [ ] Security documentation reviewed
- [ ] Tested from external network
- [ ] Tested with different user roles

---

## Need Help?

1. **Security Documentation:** `/docs/JBrowse2/technical/SECURITY.md`
2. **Full Implementation Guide:** `/docs/JBrowse2/technical/TODO_URL_WHITELIST_SECURITY.md`
3. **Check error logs:** Apache, nginx, and PHP logs
4. **Test configuration:** Use curl commands above

---

## Next Steps

After securing file access:

1. Review full security documentation
2. Configure user access levels
3. Set up collaborator accounts
4. Configure IP whitelist for internal network
5. Set up remote tracks server (if needed)
6. Implement URL whitelist strategy (Phase 1-6 in TODO document)

---

**Remember:** Direct file access blocking is **mandatory** for security. Without it, JWT authentication is completely bypassed.
