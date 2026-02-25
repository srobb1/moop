# TODO: Implement URL Whitelist Token Strategy

**Created:** 2026-02-25  
**Last Updated:** 2026-02-25  
**Priority:** CRITICAL - Security Enhancement  
**Status:** In Progress (Phase 0 - Critical Fix Complete)

---

## 🚨 CRITICAL SECURITY ISSUE DISCOVERED (2026-02-25)

### Direct File Access Vulnerability
**SEVERITY:** CRITICAL - JWT authentication can be completely bypassed

**Issue:** Track files in `/data/tracks/` are web-accessible by default. Anyone who knows or guesses a file path can download files without authentication by accessing them directly, bypassing `tracks.php` and the entire JWT system.

**Attack Example:**
```
❌ http://moop.example.com/moop/data/tracks/Organism/Assembly/file.bw
   → File downloaded, NO AUTHENTICATION
   → tracks.php never executed
   → JWT system bypassed
```

**Fix Status:** ✅ Phase 0 completed - `.htaccess` created, documentation updated

---

## Problem Statement

### Issue 1: Direct File Access (CRITICAL - FIXED in Phase 0)
Without web server configuration to block direct access, track files can be downloaded without authentication if the URL path is known.

**Root Cause:** Files in `/data/tracks/` are inside web root and served directly by Apache/nginx.

**Solution:** Block direct access with `.htaccess` (Apache) or `location` block (nginx).

### Issue 2: URL Whitelist Strategy (HIGH Priority - Original issue)
The current JWT token attachment logic uses `access_level` metadata to decide whether to add tokens to external URLs:
- External URL + `access_level="PUBLIC"` → No token
- External URL + `access_level="COLLABORATOR"` → Add token

**Problem:** This breaks for public tracks hosted on OUR tracks server because:
1. Config.php sees external URL (https://tracks.yourlab.edu) + PUBLIC → doesn't add token
2. tracks.php receives request without token → blocks with 401
3. Result: Public tracks on our server don't work

### Security Gaps
- **CRITICAL:** Direct file access bypasses all JWT authentication
- **HIGH:** Track metadata can be misconfigured (wrong access_level)
- **MEDIUM:** No clear distinction between "external public" (UCSC) vs "our external server"
- **MEDIUM:** tracks.php has no knowledge of public vs private (always requires token)

---

## Proposed Solution

### URL Whitelist Approach
Define trusted tracks servers in site config. If URL matches trusted server, ALWAYS add token. Otherwise, NEVER add token.

**Benefits:**
- ✅ Centralized security policy (one config)
- ✅ Eliminates metadata misconfiguration risk
- ✅ Clear separation: "our servers" vs "external servers"
- ✅ Tracks server can remain stateless (always requires token)
- ✅ Enables hosting public tracks on our tracks server

---

## Implementation Tasks

### Phase 0: CRITICAL Security Fix (COMPLETED 2026-02-25) ✅

**Priority:** IMMEDIATE - Blocks direct file access vulnerability

#### Task 0.1: Create .htaccess File ✅
**File:** `/data/moop/data/tracks/.htaccess`

**Status:** ✅ COMPLETED
- [x] .htaccess file created with deny rules
- [x] Apache 2.2 and 2.4 compatibility
- [x] Error message configured

**Testing Required:**
- [ ] Verify Apache has `AllowOverride All` enabled
- [ ] Test direct access returns 403
- [ ] Test API access still works
- [ ] Deploy to production

---

#### Task 0.2: Update Security Documentation ✅
**Files:** `docs/JBrowse2/technical/SECURITY.md`, `TODO_URL_WHITELIST_SECURITY.md`

**Status:** ✅ COMPLETED
- [x] Added "Web Server Configuration (REQUIRED)" section
- [x] Apache configuration examples (.htaccess and server config)
- [x] Nginx configuration examples
- [x] Testing procedures
- [x] Migration guide for existing deployments
- [x] FAQ updated with common questions
- [x] Security checklist updated

---

#### Task 0.3: Verify Apache Configuration
**Status:** ⚠️ NEEDS ATTENTION

**Required Action:**
```bash
# 1. Check if AllowOverride is enabled
grep -r "AllowOverride" /etc/apache2/sites-enabled/

# 2. If set to "None", update to "All" in site config
sudo nano /etc/apache2/sites-available/moop.conf
# Change: AllowOverride None → AllowOverride All

# 3. Test configuration
sudo apache2ctl configtest

# 4. Restart Apache
sudo systemctl restart apache2

# 5. Test blocking
curl -I http://localhost/moop/data/tracks/test.bw
# Expected: HTTP 403 Forbidden
```

**Testing:**
- [ ] `.htaccess` is being read by Apache
- [ ] Direct file access blocked (403)
- [ ] API endpoint still works (with token)
- [ ] JBrowse2 loads tracks normally

---

### Phase 1: Configuration (Week 1)

#### Task 1.1: Add Configuration to site_config.php
**File:** `config/site_config.php`

```php
'jbrowse2' => [
    // ... existing config ...
    
    /**
     * Trusted Tracks Servers
     * 
     * URLs in this list will ALWAYS have JWT tokens attached.
     * Do NOT include external public servers (UCSC, Ensembl, NCBI).
     * 
     * These should be servers YOU control and manage.
     */
    'trusted_tracks_servers' => [
        // Main MOOP server (self)
        'https://moop.example.com',
        'http://localhost',  // For development
        
        // Your remote tracks servers
        // 'https://tracks.yourlab.edu',
        // 'https://tracks1.yourlab.edu',
        // 'https://tracks2.yourlab.edu',
    ],
    
    /**
     * Log warnings for misconfigured tracks
     * External URLs with access_level != PUBLIC will be logged
     */
    'warn_on_external_private_tracks' => true,
],
```

**Testing:**
- [ ] Verify config loads correctly
- [ ] Test with empty array
- [ ] Test with multiple URLs

---

#### Task 1.2: Add ConfigManager Helper Method
**File:** `lib/ConfigManager.php`

```php
/**
 * Check if URL matches any trusted tracks server
 * 
 * @param string $url URL to check
 * @return bool True if URL matches a trusted server
 */
public function isTrustedTracksServer($url) {
    $trusted_servers = $this->getArray('jbrowse2.trusted_tracks_servers', []);
    
    foreach ($trusted_servers as $server) {
        // Match if URL starts with trusted server
        if (strpos($url, $server) === 0) {
            return true;
        }
    }
    
    return false;
}
```

**Testing:**
- [ ] Test exact match: `https://moop.example.com/path`
- [ ] Test subdomain: `https://sub.moop.example.com` (should NOT match)
- [ ] Test HTTP vs HTTPS variants
- [ ] Test with trailing slash variations

---

### Phase 2: Update Token Logic (Week 1-2)

#### Task 2.1: Update addTokenToAdapterUrls() Function
**File:** `api/jbrowse2/config.php`

Replace current logic with URL whitelist approach:

```php
/**
 * Recursively add token parameter to adapter URIs
 * 
 * TOKEN STRATEGY (Updated 2026-02-25):
 * 1. Check if URL matches trusted_tracks_servers
 *    → YES: Always add token (your servers)
 *    → NO: Never add token (external public servers)
 * 
 * 2. Validation warnings:
 *    - External URL + access_level != PUBLIC → Log warning
 *    - Cannot enforce authentication on external servers
 * 
 * @param array $adapter - Adapter configuration (may contain nested arrays)
 * @param string $token - JWT token to add
 * @param string $track_access_level - Track's access level (for validation warnings)
 * @return array - Adapter config with tokens added appropriately
 */
function addTokenToAdapterUrls($adapter, $token, $track_access_level = 'PUBLIC') {
    $config = ConfigManager::getInstance();
    $warn_on_external_private = $config->getBoolean('jbrowse2.warn_on_external_private_tracks', true);
    
    foreach ($adapter as $key => &$value) {
        if (is_array($value)) {
            if (isset($value['uri']) && !empty($value['uri'])) {
                $uri = $value['uri'];
                
                // Detect external URLs (http://, https://, ftp://)
                $is_external = preg_match('#^(https?|ftp)://#i', $uri);
                
                if ($is_external) {
                    // Check if this is a trusted server
                    if ($config->isTrustedTracksServer($uri)) {
                        // CASE 1: Trusted external server → ALWAYS add token
                        $separator = strpos($uri, '?') !== false ? '&' : '?';
                        $value['uri'] .= $separator . 'token=' . urlencode($token);
                        
                    } else {
                        // CASE 2: Untrusted external server → NEVER add token
                        
                        // Validation: Warn if marked as private (can't enforce auth)
                        if ($warn_on_external_private && $track_access_level !== 'PUBLIC') {
                            error_log(
                                "WARNING: Track has external URL with access_level='{$track_access_level}' " .
                                "but server is not in trusted_tracks_servers list. " .
                                "Cannot enforce authentication. URL: $uri"
                            );
                        }
                        
                        // Leave URL unchanged (no token leakage to external servers)
                        continue;
                    }
                    
                } else {
                    // CASE 3: MOOP internal paths → Route through tracks.php with token
                    if (preg_match('#^/moop/data/tracks/(.+)$#', $uri, $matches)) {
                        $file_path = $matches[1];
                        $value['uri'] = '/moop/api/jbrowse2/tracks.php?file=' . urlencode($file_path);
                        $value['uri'] .= '&token=' . urlencode($token);
                        
                    } elseif (preg_match('#^/moop/#', $uri)) {
                        // CASE 4: Other MOOP paths → Add token
                        $separator = strpos($uri, '?') !== false ? '&' : '?';
                        $value['uri'] .= $separator . 'token=' . urlencode($token);
                    }
                    // CASE 5: Other paths (relative, absolute) → Leave unchanged
                }
                
            } else {
                // Recurse into nested adapter structures
                $value = addTokenToAdapterUrls($value, $token, $track_access_level);
            }
        }
    }
    return $adapter;
}
```

**Testing:**
- [ ] Trusted server URL gets token
- [ ] UCSC URL doesn't get token
- [ ] MOOP internal paths get token
- [ ] Warning logged for external + COLLABORATOR

---

#### Task 2.2: Update addTokensToTrack() Function
**File:** `api/jbrowse2/config.php`

Update documentation:

```php
/**
 * Add JWT tokens to track adapter configuration
 * 
 * SECURITY UPDATE (2026-02-25):
 * - Uses URL whitelist from trusted_tracks_servers config
 * - Trusted servers always get tokens (regardless of access_level)
 * - External servers never get tokens (prevents leakage)
 * - Logs warnings for misconfigured tracks
 * 
 * @param array $track_def - Track definition from metadata
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @param bool $is_whitelisted - Whether user's IP is whitelisted
 * @return array|null - Track config with tokens, or null if token generation fails
 */
function addTokensToTrack($track_def, $organism, $assembly, $user_access_level, $is_whitelisted) {
    // Generate token for all users
    try {
        $token = generateTrackToken($organism, $assembly, $user_access_level);
    } catch (Exception $e) {
        error_log("Failed to generate token for track {$track_def['trackId']}: " . $e->getMessage());
        return null;
    }
    
    // Get track access level for validation warnings
    $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
    
    // Add token to adapter URLs using URL whitelist strategy
    if (isset($track_def['adapter'])) {
        $track_def['adapter'] = addTokenToAdapterUrls($track_def['adapter'], $token, $track_access_level);
    }
    
    return $track_def;
}
```

**Testing:**
- [ ] Token generation still works
- [ ] Adapter URLs correctly modified
- [ ] Warnings logged appropriately

---

### Phase 3: Update Documentation (Week 2)

#### Task 3.1: Update SECURITY.md
**File:** `docs/JBrowse2/technical/SECURITY.md`

Update sections:
- [ ] Architecture Overview (mention URL whitelist)
- [ ] JWT Token System (new token attachment strategy)
- [ ] Track File Server (clarify always requires token)
- [ ] Deployment Guide (how to configure trusted servers)
- [ ] FAQ (add "How do I host public tracks on my tracks server?")

Key additions:
```markdown
### URL Whitelist Token Strategy (2026-02-25)

MOOP uses a URL whitelist to determine which servers receive JWT tokens:

**Trusted Servers (in config):**
- Always receive JWT tokens
- Your tracks servers that validate tokens
- Example: `https://tracks.yourlab.edu`

**External Servers (not in config):**
- Never receive JWT tokens
- Public resources you don't control
- Example: `https://hgdownload.soe.ucsc.edu`

This prevents:
- JWT token leakage to external servers
- Misconfiguration via track metadata
- Ambiguity about "public" vs "private" tracks
```

---

#### Task 3.2: Update Track Configuration Guide
**File:** `docs/JBrowse2/ADMIN_GUIDE.md`

Add section on hosting tracks:

```markdown
## Hosting Tracks on Remote Server

If you host tracks on your own remote server, you MUST:

1. Add server URL to `trusted_tracks_servers` config:
   ```php
   'trusted_tracks_servers' => [
       'https://tracks.yourlab.edu',
   ]
   ```

2. Deploy tracks.php and JWT public key to that server

3. Configure tracks.php to always require JWT tokens

4. Track metadata `access_level` still controls filtering:
   - PUBLIC tracks: Everyone sees them (with token)
   - COLLABORATOR tracks: Only authorized users see them (with token)

### Public Tracks on Your Server

To host a public reference genome on your tracks server:

```json
{
    "uri": "https://tracks.yourlab.edu/Reference/GCA_000001405/genome.fa",
    "metadata": {
        "access_level": "PUBLIC"
    }
}
```

MOOP will:
1. Add JWT token to URL (server is in trusted list)
2. Show track to all users (access_level is PUBLIC)
3. tracks.php validates token and serves file

This allows public tracks on your infrastructure while maintaining audit trail.
```

---

#### Task 3.3: Add Migration Guide
**File:** `docs/JBrowse2/MIGRATION_URL_WHITELIST.md`

Create migration guide for existing deployments:

```markdown
# Migration Guide: URL Whitelist Token Strategy

## Overview
This guide helps you migrate from access_level-based token strategy to URL whitelist strategy.

## Pre-Migration Checklist
- [ ] Review all track metadata files
- [ ] Identify external URLs in tracks
- [ ] Categorize: Your servers vs external public servers
- [ ] Backup current config

## Migration Steps

### Step 1: Audit Current Tracks
```bash
# Find all external URLs in track metadata
cd /data/moop/metadata/jbrowse2-configs/tracks
grep -r '"uri".*https://' . | grep -v "hgdownload\|ensembl\|ncbi"
```

### Step 2: Configure Trusted Servers
Add your servers to config (see Task 1.1)

### Step 3: Test with Sample Track
1. Pick a track on your remote server
2. Verify it has token in URL after config change
3. Test file loads in JBrowse2

### Step 4: Deploy Updated Code
1. Update config.php with new logic
2. Test all track types (BigWig, BAM, VCF)
3. Monitor error logs for warnings

### Step 5: Review Warnings
Check logs for misconfigured tracks:
```bash
grep "WARNING.*external URL" /var/log/apache2/error.log
```

## Rollback Plan
If issues occur:
1. Revert config.php to previous version
2. Git checkout previous commit
3. Restart PHP-FPM
```

---

### Phase 4: Testing (Week 2)

#### Task 4.1: Unit Tests
Create test cases:

```php
// Test file: tests/JBrowse2/TokenUrlWhitelistTest.php

class TokenUrlWhitelistTest extends TestCase {
    
    public function testTrustedServerGetsToken() {
        // Config: trusted_tracks_servers = ['https://tracks.example.com']
        // Input: URI = 'https://tracks.example.com/data.bw'
        // Expected: Token added
    }
    
    public function testExternalServerNoToken() {
        // Config: trusted_tracks_servers = ['https://tracks.example.com']
        // Input: URI = 'https://hgdownload.soe.ucsc.edu/data.bw'
        // Expected: No token added
    }
    
    public function testMoopInternalGetsToken() {
        // Input: URI = '/moop/data/tracks/Organism/Assembly/data.bw'
        // Expected: Token added
    }
    
    public function testWarningLoggedForExternalPrivate() {
        // Input: URI = 'https://external.com/data.bw', access_level = 'COLLABORATOR'
        // Expected: Warning logged
    }
    
    public function testSubdomainNotMatchedByDefault() {
        // Config: trusted_tracks_servers = ['https://tracks.example.com']
        // Input: URI = 'https://sub.tracks.example.com/data.bw'
        // Expected: No token (doesn't match)
    }
}
```

**Testing:**
- [ ] All unit tests pass
- [ ] Edge cases covered
- [ ] URL matching logic correct

---

#### Task 4.2: Integration Tests
Test scenarios:

**Scenario 1: Public Track on Your Server**
```
Track: Reference genome on tracks.yourlab.edu
Metadata: access_level = "PUBLIC"
Config: tracks.yourlab.edu in trusted_tracks_servers
Expected: Token added, all users can access
```

**Scenario 2: Private Track on Your Server**
```
Track: Experimental data on tracks.yourlab.edu
Metadata: access_level = "COLLABORATOR"
Config: tracks.yourlab.edu in trusted_tracks_servers
Expected: Token added, only authorized users see track
```

**Scenario 3: UCSC Reference Genome**
```
Track: UCSC hg38 genes
URI: https://hgdownload.soe.ucsc.edu/...
Metadata: access_level = "PUBLIC"
Config: UCSC NOT in trusted_tracks_servers
Expected: No token added, loads normally
```

**Scenario 4: Misconfigured Track**
```
Track: External server, marked private
URI: https://someserver.com/data.bw
Metadata: access_level = "COLLABORATOR"
Config: someserver.com NOT in trusted_tracks_servers
Expected: Warning logged, no token added
```

Testing checklist:
- [ ] Scenario 1 works
- [ ] Scenario 2 works
- [ ] Scenario 3 works
- [ ] Scenario 4 logs warning

---

### Phase 5: Apache/Nginx Configuration (Week 3)

## Question: Do we configure web server to validate tokens?

**Answer: Optional - Defense in Depth**

Currently, `tracks.php` validates tokens at the application level. You CAN add web server validation as an additional security layer, but it's not required.

---

#### Option A: Application-Only (Current - Recommended)

**Pros:**
- ✅ Simpler configuration
- ✅ Easier to debug
- ✅ Portable across web servers
- ✅ Token validation logic in one place

**Cons:**
- ⚠️ PHP executes for every request (minor performance cost)

**Configuration:** None needed - tracks.php handles everything

---

#### Option B: Web Server + Application (Defense in Depth)

Add nginx/Apache validation BEFORE PHP executes.

##### Task 5.1: Nginx Configuration (Optional)

**File:** `/etc/nginx/sites-available/moop`

```nginx
location ~ ^/moop/api/jbrowse2/tracks\.php$ {
    # OPTIONAL: Reject requests without token parameter at nginx level
    # This prevents PHP from executing for obviously invalid requests
    
    if ($arg_token = "") {
        return 401 '{"error":"Authentication required"}';
        add_header Content-Type application/json always;
    }
    
    # Pass to PHP-FPM for actual validation
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
}
```

**Pros:**
- ✅ Faster rejection of obviously invalid requests
- ✅ Reduces PHP load
- ✅ Extra security layer

**Cons:**
- ⚠️ Only checks token EXISTS, not if it's valid
- ⚠️ More configuration complexity
- ⚠️ Harder to maintain

**Testing:**
- [ ] Request without token → 401 at nginx level
- [ ] Request with token → passes to PHP
- [ ] PHP still validates token properly

---

##### Task 5.2: Apache Configuration (Optional)

**File:** `/etc/apache2/sites-available/moop.conf`

```apache
<Location /moop/api/jbrowse2/tracks.php>
    # OPTIONAL: Require token parameter
    RewriteEngine On
    RewriteCond %{QUERY_STRING} !token=
    RewriteRule .* - [R=401,L]
    
    # Pass to PHP for actual validation
</Location>
```

**Testing:**
- [ ] Same as nginx tests

---

#### Recommendation: Skip Web Server Validation

**Reason:**
- tracks.php already validates tokens correctly
- Added complexity for minimal benefit
- Application-level validation is sufficient
- Easier to maintain and debug

**Only implement if:**
- You have very high traffic (>1000 req/sec)
- You want to reduce PHP load
- You have dedicated DevOps team

---

### Phase 6: Deployment (Week 3-4)

#### Task 6.1: Staging Deployment

1. **Deploy to staging environment**
   ```bash
   # Backup current code
   cd /var/www/staging/moop
   git branch backup-before-url-whitelist
   
   # Pull changes
   git pull origin main
   
   # Update config
   nano config/site_config.php
   # Add trusted_tracks_servers
   
   # Restart services
   sudo systemctl restart php-fpm
   ```

2. **Test all scenarios**
   - [ ] Load sample assembly
   - [ ] Verify tracks load
   - [ ] Check error logs
   - [ ] Test different user types

3. **Performance testing**
   - [ ] Measure config generation time
   - [ ] Check for memory leaks
   - [ ] Monitor error rates

---

#### Task 6.2: Production Deployment

1. **Pre-deployment**
   - [ ] Announce maintenance window
   - [ ] Backup database/files
   - [ ] Document rollback procedure

2. **Deploy**
   ```bash
   cd /var/www/html/moop
   git pull origin main
   nano config/site_config.php
   sudo systemctl restart php-fpm
   ```

3. **Validation**
   - [ ] Test with real user account
   - [ ] Verify all tracks load
   - [ ] Monitor error logs for 24 hours
   - [ ] Check performance metrics

4. **Post-deployment**
   - [ ] Update CHANGELOG
   - [ ] Notify users of changes
   - [ ] Archive old documentation

---

## Testing Checklist

### Before Deployment
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing on dev environment
- [ ] Staging environment validated
- [ ] Documentation updated
- [ ] Rollback plan documented

### After Deployment
- [ ] All tracks load correctly
- [ ] No 401 errors for valid requests
- [ ] Warnings logged for misconfigured tracks
- [ ] Performance acceptable
- [ ] No security regressions

---

## Rollback Plan

If issues occur after deployment:

```bash
# 1. Revert to previous code
cd /var/www/html/moop
git revert HEAD
sudo systemctl restart php-fpm

# 2. Or checkout previous version
git checkout backup-before-url-whitelist

# 3. Clear any caches
rm -rf /tmp/php-cache/*
```

---

## Success Criteria

✅ **Phase 1 Complete:** Config added, loads correctly  
✅ **Phase 2 Complete:** Token logic updated, tests pass  
✅ **Phase 3 Complete:** Documentation updated  
✅ **Phase 4 Complete:** All tests passing  
✅ **Phase 5 Complete:** Web server config (if needed)  
✅ **Phase 6 Complete:** Deployed to production, validated  

---

## Timeline

| Phase | Duration | Completion Date |
|-------|----------|-----------------|
| Phase 1: Configuration | 2 days | TBD |
| Phase 2: Token Logic | 3 days | TBD |
| Phase 3: Documentation | 2 days | TBD |
| Phase 4: Testing | 3 days | TBD |
| Phase 5: Web Server (Optional) | 2 days | TBD |
| Phase 6: Deployment | 3 days | TBD |
| **Total** | **15 days** | **TBD** |

---

## Resources Needed

- [ ] Developer time: 15 days
- [ ] Staging environment access
- [ ] Production deployment window
- [ ] Code review from security team

---

## Notes

- This is a security enhancement, not a bug fix
- Current system is secure but has design flaw for public tracks on your server
- URL whitelist approach is industry best practice
- Similar to CORS policy configuration

---

## Questions / Decisions

1. **Do we need web server validation?**
   - **Answer:** No, application-level is sufficient

2. **Should we validate subdomain matching?**
   - **Answer:** No, require exact hostname match for security

3. **What about IPv6 addresses?**
   - **Answer:** Support in URL matching if needed

4. **Backward compatibility?**
   - **Answer:** Changes are transparent to users, only affects config

---

**Document Status:** DRAFT  
**Last Updated:** 2026-02-25  
**Next Review:** After Phase 2 completion
