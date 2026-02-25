# MOOP Security Documentation Summary

**Last Updated:** 2026-02-25  
**Status:** Critical security fix implemented

---

## 📚 Documentation Files

### 1. **SECURITY_QUICKSTART.md** (START HERE)
**Location:** `/docs/JBrowse2/SECURITY_QUICKSTART.md`  
**For:** System administrators doing first-time setup  
**Contents:**
- Step-by-step blocking of direct file access (CRITICAL)
- Apache and nginx configuration examples
- Testing procedures
- Common issues and solutions
- Production deployment checklist

**Read this FIRST if you're setting up MOOP.**

---

### 2. **SECURITY.md** (Complete Technical Reference)
**Location:** `/docs/JBrowse2/technical/SECURITY.md`  
**For:** Security auditors, developers, advanced administrators  
**Contents:**
- Complete security architecture (3000+ lines)
- Authentication system details
- JWT token implementation (RS256)
- Web server configuration (Apache/nginx)
- Attack scenarios and mitigations
- Deployment guides
- Remote tracks server setup
- Security checklist
- FAQ

**Read this for complete understanding of the security model.**

---

### 3. **TODO_URL_WHITELIST_SECURITY.md** (Implementation Plan)
**Location:** `/docs/JBrowse2/technical/TODO_URL_WHITELIST_SECURITY.md`  
**For:** Developers implementing URL whitelist strategy  
**Contents:**
- Phase 0: Critical security fix (COMPLETED)
- Phase 1-6: URL whitelist implementation plan
- Code examples for all changes
- Testing procedures
- Migration guide
- Timeline and resources needed

**Use this for planned security enhancements.**

---

## 🚨 CRITICAL Security Issue Fixed (2026-02-25)

### What Was Wrong

Track files in `/data/tracks/` were web-accessible by default. Anyone who knew or guessed a file path could download files WITHOUT AUTHENTICATION by accessing them directly:

```
❌ VULNERABLE:
http://moop.example.com/moop/data/tracks/Organism/Assembly/file.bw
→ File downloaded, no JWT validation, no authentication
```

### What Was Fixed

1. **Created `.htaccess`** to block direct access
2. **Updated security documentation** with Apache/nginx configuration
3. **Added testing procedures** to verify blocking works
4. **Created quick start guide** for administrators

```
✅ SECURE (after fix):
http://moop.example.com/moop/api/jbrowse2/tracks.php?file=...&token=...
→ JWT validated, organism/assembly checked, file served only if authorized
```

### Action Required

**If you're running MOOP in production:** Follow `SECURITY_QUICKSTART.md` immediately to:
1. Block direct file access with Apache/nginx configuration
2. Verify JWT keys exist
3. Test that blocking works
4. Test that JBrowse2 still loads tracks

---

## 🔐 Security Architecture Overview

### Authentication Flow

```
User Login
    ↓
PHP Session Created (access_level assigned)
    ↓
User Requests JBrowse2 Config
    ↓
config.php Filters Assemblies/Tracks by Permission
    ↓
config.php Generates JWT Token (organism/assembly/user)
    ↓
config.php Injects Token into Track URLs
    ↓
Browser Receives Config with Tokenized URLs
    ↓
Browser Requests Track File (with token parameter)
    ↓
tracks.php Validates JWT Token
    ↓
tracks.php Checks Organism/Assembly Claims
    ↓
tracks.php Serves File (or denies access)
```

### Security Layers

1. **Web Server (Layer 0)** - NEW 2026-02-25
   - Blocks direct file access
   - Forces all requests through tracks.php
   - Prevents JWT bypass

2. **Session Authentication (Layer 1)**
   - PHP session-based login
   - Access levels: PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN
   - IP-based auto-authentication

3. **Config Filtering (Layer 2)**
   - Only authorized assemblies/tracks shown to user
   - Permission check before config generation

4. **JWT Authentication (Layer 3)**
   - RS256 asymmetric signatures (2048-bit RSA)
   - Token scoped to organism/assembly
   - 1-hour expiration (relaxed for IP whitelist)
   - Private key on MOOP server only
   - Public key on tracks servers

5. **Path Validation (Layer 4)**
   - Token organism/assembly must match requested file path
   - Prevents token reuse across assemblies

---

## 🔑 Key Security Features

### RS256 Asymmetric JWT

**MOOP Server:**
- Has private key → Can **sign** tokens
- Generates tokens for authenticated users

**Tracks Server:**
- Has public key only → Can **verify** tokens
- Cannot forge tokens (needs private key)

**Why This Matters:**
If tracks server is compromised, attacker cannot create valid tokens.

---

### Token Claims

Every JWT token contains:
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

These claims are validated on every file request.

---

### IP Whitelist

Internal network users get special treatment:
- ✅ Still get JWT tokens (defense-in-depth)
- ✅ Can use expired tokens (convenience)
- ✅ Organism/assembly still validated (security)

---

### External URL Protection

External URLs (UCSC, Ensembl, NCBI) never get JWT tokens:
- ✅ Prevents token leakage to external servers
- ✅ Your tokens stay private
- ✅ External public data still accessible

---

## 📋 Quick Security Checklist

### CRITICAL (Must Have)
- [ ] Direct file access blocked (Apache/.htaccess or nginx)
- [ ] JWT keys generated with correct permissions
- [ ] HTTPS enabled
- [ ] tracks.php validates all requests

### RECOMMENDED (Should Have)
- [ ] Session cookies: HttpOnly, Secure, SameSite
- [ ] Session timeout configured
- [ ] Error logging enabled
- [ ] IP whitelist configured
- [ ] Firewall rules configured

### OPTIONAL (Nice to Have)
- [ ] Remote tracks server deployed
- [ ] URL whitelist strategy implemented
- [ ] Rate limiting on API endpoints
- [ ] Security monitoring/alerts

---

## 🐛 Common Security Mistakes

### ❌ Mistake 1: Not Blocking Direct Access
**Problem:** Files accessible without authentication  
**Fix:** Configure Apache/.htaccess or nginx  
**Test:** `curl -I http://server/moop/data/tracks/file.bw` → Should get 403

### ❌ Mistake 2: JWT Keys Have Wrong Permissions
**Problem:** Private key readable by everyone  
**Fix:** `chmod 600 jwt_private_key.pem`  
**Test:** `ls -la /certs/jwt_private_key.pem` → Should be `-rw-------`

### ❌ Mistake 3: AllowOverride None in Apache
**Problem:** .htaccess file ignored  
**Fix:** Set `AllowOverride All` in site config  
**Test:** Direct access should be blocked

### ❌ Mistake 4: Using HS256 Instead of RS256
**Problem:** Tracks server can forge tokens  
**Fix:** Use RS256 (already default in MOOP)  
**Verify:** Check `track_token.php` uses `'RS256'`

---

## 📖 Reading Order

1. **First Time Setup:**
   - Read: `SECURITY_QUICKSTART.md`
   - Follow all steps
   - Test security configuration
   - Verify JBrowse2 works

2. **Understanding the System:**
   - Read: `SECURITY.md` sections 1-4
   - Understand authentication flow
   - Learn about JWT tokens
   - Review security layers

3. **Production Deployment:**
   - Read: `SECURITY.md` section 8 (Deployment Guide)
   - Follow security checklist
   - Configure HTTPS
   - Set up remote tracks server (if needed)

4. **Implementing Enhancements:**
   - Read: `TODO_URL_WHITELIST_SECURITY.md`
   - Follow phases 1-6
   - Test each phase
   - Update documentation

---

## 🆘 Getting Help

### Something Not Working?

1. **Check error logs:**
   ```bash
   sudo tail -f /var/log/apache2/error.log
   sudo tail -f /var/log/php8.1-fpm.log
   ```

2. **Test security configuration:**
   ```bash
   # Should return 403
   curl -I http://localhost/moop/data/tracks/test.bw
   
   # Should return 401
   curl http://localhost/moop/api/jbrowse2/tracks.php?file=test.bw
   ```

3. **Verify JWT keys:**
   ```bash
   ls -la /var/www/html/moop/certs/jwt_*.pem
   ```

4. **Check Apache/nginx config:**
   ```bash
   sudo apache2ctl configtest
   # or
   sudo nginx -t
   ```

### Still Stuck?

- Review FAQ in `SECURITY.md`
- Check common issues in `SECURITY_QUICKSTART.md`
- Verify all steps in deployment checklist

---

## 📝 Version History

- **v3.2 (2026-02-25):** Critical fix for direct file access vulnerability
- **v3.1 (2026-02-18):** Enhanced IP whitelist, external URL protection
- **v3.0 (2026-02-14):** Initial JWT security implementation

---

## 🔒 Security Contacts

For security vulnerabilities or questions:
- Review documentation first
- Check error logs
- Follow testing procedures
- Contact MOOP administrator if needed

---

**Remember:** Security is a process, not a product. Regularly review and update your configuration.
