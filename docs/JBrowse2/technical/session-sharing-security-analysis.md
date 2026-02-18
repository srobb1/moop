# JBrowse2 Session Sharing Security Analysis

**Date:** 2026-02-17  
**Status:** 🟡 INFORMATION DISCLOSURE VULNERABILITY CONFIRMED  
**Sharing Status:** ENABLED  
**Test Date:** 2026-02-17 20:31 UTC

---

## Overview

This document analyzes the security implications of JBrowse2 session sharing in the MOOP system, specifically focusing on how JWT tokens and track permissions interact with shareable session URLs.

---

## Permission System Architecture

### Two-Level Permission Model

1. **Assembly Level** (`defaultAccessLevel` in assembly JSON)
   - Controls if assembly appears in the user's assembly list
   - Values: PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN
   
2. **Track Level** (`access_level` in track metadata)
   - Controls which tracks appear within an assembly
   - Values: PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN
   - **Independent of assembly access level**

### Access Hierarchy

```
PUBLIC (1) < COLLABORATOR (2) < IP_IN_RANGE (3) < ADMIN (4)
```

Users can only see tracks at or below their access level, with additional checks for COLLABORATOR users.

---

## Scenario Analysis

### Scenario 1: PUBLIC Assembly with Mixed Track Access Levels

**Assembly Configuration:**
```json
{
  "name": "Nematostella_vectensis_GCA_033964005.1",
  "defaultAccessLevel": "PUBLIC",
  "organism": "Nematostella_vectensis",
  "assemblyId": "GCA_033964005.1"
}
```

**Track Configuration:**
- **Track A** (Gene Annotations): `access_level: "PUBLIC"`
- **Track B** (RNA-seq Coverage): `access_level: "COLLABORATOR"`  
- **Track C** (Raw Alignments): `access_level: "ADMIN"`

---

#### What a PUBLIC User Sees

**Assembly List:**
```
✅ Nematostella_vectensis_GCA_033964005.1 (visible - assembly is PUBLIC)
```

**When Opening Assembly:**

Browser requests:
```
GET /moop/api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
```

Server filters tracks (lines 295-298 in config.php):
```php
if ($user_level_value < $track_level_value) {
    continue; // Skip this track
}
```

**Config Response Contains:**
```json
{
  "assemblies": [...],
  "tracks": [
    {
      "trackId": "Track_A",
      "name": "Gene Annotations",
      "metadata": {"access_level": "PUBLIC"},
      "adapter": {
        "uri": "/moop/api/jbrowse2/tracks.php?file=...&token=JWT_PUBLIC_TOKEN"
      }
    }
  ]
}
```

**Result:**
- ✅ **Sees:** Track A (Gene Annotations)
- ❌ **Does NOT see:** Track B (not in config)
- ❌ **Does NOT see:** Track C (not in config)
- 🔒 **Knowledge:** PUBLIC user has **no knowledge** that Track B or C exist
- 🎫 **Token:** JWT contains `access_level: "PUBLIC"`, `organism`, `assembly`, expires in 1 hour

---

#### What a COLLABORATOR User Sees

**Assembly List:**
```
✅ Nematostella_vectensis_GCA_033964005.1 (visible - assembly is PUBLIC)
```

**Additional Check (line 303-308):**
```php
if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
    $user_access = $_SESSION['access'] ?? [];
    if (!isset($user_access[$organism]) || !in_array($assembly, (array)$user_access[$organism])) {
        continue; // Skip - no explicit assembly access
    }
}
```

**Config Response Contains:**
```json
{
  "assemblies": [...],
  "tracks": [
    {
      "trackId": "Track_A",
      "name": "Gene Annotations",
      "metadata": {"access_level": "PUBLIC"},
      "adapter": {
        "uri": "/moop/api/jbrowse2/tracks.php?file=...&token=JWT_PUBLIC_TOKEN"
      }
    },
    {
      "trackId": "Track_B",
      "name": "RNA-seq Coverage",
      "metadata": {"access_level": "COLLABORATOR"},
      "adapter": {
        "uri": "/moop/api/jbrowse2/tracks.php?file=...&token=JWT_COLLAB_TOKEN"
      }
    }
  ]
}
```

**Result:**
- ✅ **Sees:** Track A (Gene Annotations)
- ✅ **Sees:** Track B (RNA-seq Coverage) - **IF** they have explicit access to this organism/assembly
- ❌ **Does NOT see:** Track C (needs ADMIN)
- 🎫 **Token:** JWT contains `access_level: "COLLABORATOR"`, `organism`, `assembly`, expires in 1 hour

---

### Scenario 2: COLLABORATOR-Only Assembly

**Assembly Configuration:**
```json
{
  "name": "Restricted_species_GCA_999999999.1",
  "defaultAccessLevel": "COLLABORATOR",
  "organism": "Restricted_species",
  "assemblyId": "GCA_999999999.1"
}
```

**Track Configuration:**
- **Track X**: `access_level: "PUBLIC"`
- **Track Y**: `access_level: "COLLABORATOR"`

---

#### What a PUBLIC User Sees

**Assembly List:**
```
❌ Restricted_species_GCA_999999999.1 (NOT VISIBLE - filtered at line 148-152)
```

**If Public User Somehow Gets Direct URL:**
```
GET /moop/api/jbrowse2/config.php?organism=Restricted_species&assembly=GCA_999999999.1
```

**Server Response:**
```json
HTTP 403 Forbidden
{"error": "Access denied to this assembly"}
```

**Result:**
- ❌ **Cannot see assembly in list**
- ❌ **Cannot access assembly even with direct URL**
- 🔒 **Knowledge:** PUBLIC user doesn't know this assembly exists

---

#### What a COLLABORATOR User Sees (With Access)

**Assembly List:**
```
✅ Restricted_species_GCA_999999999.1 (visible - user in $_SESSION['access']['Restricted_species'])
```

**Config Response:**
```json
{
  "assemblies": [...],
  "tracks": [
    {
      "trackId": "Track_X",
      "metadata": {"access_level": "PUBLIC"}
    },
    {
      "trackId": "Track_Y",
      "metadata": {"access_level": "COLLABORATOR"}
    }
  ]
}
```

**Result:**
- ✅ **Sees:** Assembly in list
- ✅ **Sees:** Track X (PUBLIC)
- ✅ **Sees:** Track Y (COLLABORATOR)

---

## Session Sharing Security Analysis

### How JBrowse2 Session Sharing Works

When a user clicks the "Share" button in JBrowse2, it generates a URL containing:

1. **Config URL**: Pointer to the configuration endpoint
2. **Session State**: JSON-encoded view state including:
   - Open tracks (by trackId)
   - Visible region (chromosome, start, end)
   - Zoom level
   - Track heights and display settings
   - **Potentially: Full track configurations with embedded URLs**

### Session URL Format

Typical JBrowse2 share URL:
```
/moop/jbrowse2/index.html?config=<encoded_config_url>&session=<encoded_session_state>
```

Or:
```
/moop/jbrowse2/index.html?config=<encoded_config_url>&sessionTracks=<track_ids>&loc=chr1:1000-5000
```

---

## 🔴 SECURITY CONCERN: Token Leakage via Session URLs

### Potential Attack Vector

**Question:** Can JWT tokens leak through shareable session URLs?

**Concern:** If JBrowse2 encodes full track adapter configurations (including JWT tokens) in the session URL, a COLLABORATOR could share a link that contains valid tokens, allowing a PUBLIC user to access restricted tracks.

### What Needs Testing

1. **Generate a share link as COLLABORATOR user**
   - Open an assembly with COLLABORATOR tracks
   - Click "Share" button
   - Inspect the generated URL

2. **Check if session contains:**
   - ❌ Just track IDs (safe)
   - ⚠️ Full track URIs without tokens (moderate risk)
   - 🔴 Full track URIs with JWT tokens (CRITICAL SECURITY ISSUE)

3. **Test PUBLIC user accessing shared link:**
   - Does it re-request config with PUBLIC permissions?
   - Does it try to use embedded tokens?
   - Do tracks load or fail?

---

## Possible Attack Scenarios

### Scenario A: Session Contains Track IDs Only (SAFE ✅)

**COLLABORATOR shares:**
```
?session={
  "tracks": ["Track_A", "Track_B"],
  "location": "chr1:1000-5000"
}
```

**PUBLIC user loads link:**
1. Browser requests `config.php` with PUBLIC session
2. Config returns only PUBLIC tracks (Track A)
3. JBrowse2 tries to load Track B by ID
4. Track B not in config → **Error or ignored**
5. **Result:** PUBLIC user only sees Track A ✅

**Security:** SAFE - Config is re-fetched with current user's permissions

---

### Scenario B: Session Contains Full Track Configs WITHOUT Tokens (MODERATE ⚠️)

**COLLABORATOR shares:**
```
?session={
  "sessionTracks": [{
    "trackId": "Track_B",
    "name": "RNA-seq",
    "adapter": {
      "uri": "/moop/api/jbrowse2/tracks.php?file=Nematostella/GCA_033/Track_B.bw"
    }
  }]
}
```

**PUBLIC user loads link:**
1. Browser requests `config.php` (gets PUBLIC tracks)
2. JBrowse2 **also** tries to load sessionTracks
3. Browser requests: `GET /moop/api/jbrowse2/tracks.php?file=...` (NO TOKEN)
4. Tracks server checks: No token provided, not whitelisted IP
5. **Response:** HTTP 401 Unauthorized ✅

**Security:** SAFE - Tracks server rejects requests without valid tokens

---

### Scenario C: Session Contains Full Track Configs WITH Tokens (🔴 CRITICAL)

**COLLABORATOR shares (within 1 hour of generating):**
```
?session={
  "sessionTracks": [{
    "trackId": "Track_B",
    "name": "RNA-seq",
    "adapter": {
      "uri": "/moop/api/jbrowse2/tracks.php?file=Nematostella/GCA_033/Track_B.bw&token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
    }
  }]
}
```

**PUBLIC user loads link (within token expiry):**
1. Browser requests `config.php` (gets PUBLIC tracks)
2. JBrowse2 loads sessionTracks with embedded token
3. Browser requests: `GET tracks.php?file=...&token=VALID_JWT`
4. Tracks server validates token:
   - ✅ Valid signature
   - ✅ Not expired
   - ✅ Organism/assembly matches file path
5. **Response:** HTTP 200 - Track data served 🔴

**Security:** 🔴 **BREACH** - PUBLIC user accessed COLLABORATOR track

**Mitigation:** Token expires in 1 hour, but still a window of vulnerability

---

## Risk Assessment

### Test Results (2026-02-17)

- [x] **Does JBrowse2 embed full track configs in session URLs?** 
  - ✅ YES - Full track configurations including track IDs and display settings are embedded
  
- [x] **Are JWT tokens included in session exports?**
  - ✅ NO - Tokens are NOT embedded in session URLs (GOOD)
  
- [x] **Does session loading re-fetch config or use embedded data?**
  - ⚠️ PARTIAL - Session contains track references, but config is re-fetched based on current user permissions
  
- [x] **Can unauthorized users access data through shared sessions?**
  - ✅ NO - Tracks do not load data, tracks not available in track selector (GOOD)
  
- [x] **Information Disclosure?**
  - 🟡 YES - Track IDs and configurations are visible in session JSON (MODERATE RISK)

### Worst-Case Impact

**Actual Impact (Based on Testing):**

1. **Data Leakage:** ✅ **NO** - Tracks do not load data for unauthorized users
2. **Information Disclosure:** 🟡 **YES** - Track names and configurations visible in session JSON
3. **Token Leakage:** ✅ **NO** - JWT tokens are not embedded in session URLs
4. **Authorization Bypass:** ✅ **NO** - Config is re-fetched with current user permissions

**Risk Level: 🟡 MODERATE (Information Disclosure)**

**What is leaked:**
- Track IDs (e.g., "MOLNG-2707_S3-body-wall.bam")
- Track types (AlignmentsTrack, QuantitativeTrack, etc.)
- Display configurations
- Track existence (users learn restricted tracks exist)

**What is NOT leaked:**
- Actual track data
- JWT tokens
- File paths (not directly visible in session)
- User credentials

**Impact Assessment:**
- **Confidentiality:** Low to Moderate - Track names may reveal research details
- **Integrity:** None - No data modification possible
- **Availability:** None - No denial of service risk

---

## Recommended Security Measures

### Immediate Actions (Pre-Testing)

1. **Document current behavior** (this document)
2. **Test session sharing** with different user levels
3. **Inspect generated session URLs** for token presence
4. **Review JBrowse2 configuration options** for session security

## Recommended Security Measures

### Current Status: Information Disclosure Only

Based on testing, the system correctly prevents unauthorized data access, but does leak metadata about restricted tracks.

### Risk-Based Decision Matrix

#### Option 1: Accept Current Risk (Recommended for Most Cases)
**Rationale:**
- No actual data breach occurs
- Track names alone provide limited information
- Users still cannot access the data
- System correctly enforces authorization

**When to choose:**
- Track names are not highly sensitive
- Research is not confidential
- Users understand naming conventions may be visible in shared links

---

#### Option 2: Disable Session Sharing (Most Secure)
**Implementation:**
```json
{
  "configuration": {
    "disableAnalytics": true,
    "shareURL": false
  }
}
```

**When to choose:**
- Track names contain sensitive/confidential information
- Any information disclosure is unacceptable
- Compliance requirements prohibit metadata leakage

**Trade-offs:**
- Users lose ability to share views
- Collaboration becomes harder
- Screenshots become primary sharing method

---

#### Option 3: User Education (Lightweight Mitigation)
**Implementation:**
- Add warning in UI: "Shared links may reveal track names"
- Documentation for users about session sharing
- Training for ADMIN users about information disclosure

**When to choose:**
- Low-risk environment
- Trusted user community
- Need to maintain sharing functionality

---

#### Option 4: Server-Side Session Storage (Advanced)
**Implementation:**
- Store sessions server-side with UUIDs
- Validate user permissions on session load
- Filter session tracks based on current user access
- Regenerate tokens for current user

**Complexity:** High
**Maintenance:** Requires database/storage for sessions

**When to choose:**
- Need full security + sharing functionality
- Have development resources
- Want fine-grained control

---

#### Option 5: Client-Side Session Sanitization (Advanced)
**Implementation:**
- Create JBrowse2 plugin to strip track IDs from exports
- Replace track IDs with position/view state only
- Force config re-fetch on session load

**Complexity:** Moderate to High
**Maintenance:** Requires JBrowse2 plugin development

**When to choose:**
- Want to reduce information disclosure
- Maintain most sharing functionality
- Have JavaScript development resources

---

### Recommended Action Plan

**For MOOP (Current Assessment):**

1. ✅ **Accept current risk** with documentation
2. ✅ **Add user warning** about track name visibility
3. ✅ **Document in user guide** how session sharing works
4. 📋 **Review track naming conventions** (avoid sensitive names)
5. 📋 **Periodic audits** of shared sessions if needed

**Reasoning:**
- Authorization is correctly enforced (no data breach)
- JWT tokens are not leaked (good security)
- Information disclosure is limited to track metadata
- Sharing functionality is valuable for collaboration
- Track names in current system appear non-sensitive

---

## Testing Plan

### Test 1: Inspect Session URL Structure

**Steps:**
1. Log in as COLLABORATOR user
2. Open assembly with mixed PUBLIC/COLLABORATOR tracks
3. Load COLLABORATOR track in view
4. Click "Share" button
5. Copy generated URL
6. Decode URL parameters
7. Check for presence of JWT tokens

**Expected Outcomes:**
- **Best:** Session contains only track IDs
- **Moderate:** Session contains URIs without tokens
- **Critical:** Session contains full URIs with JWT tokens

---

### Test 2: Cross-User Session Access

**Steps:**
1. COLLABORATOR generates share link with Track B open
2. Log out
3. Log in as PUBLIC user (or use incognito)
4. Paste shared URL
5. Observe behavior

**Check:**
- Does Track B appear?
- Does Track B load data?
- Are there errors in browser console?
- What requests are made to tracks.php?

**Expected Safe Behavior:**
- Track B should NOT load
- Config should be re-fetched with PUBLIC permissions
- Track B should not appear in track list

**Dangerous Behavior:**
- Track B loads successfully
- Data is visible to PUBLIC user
- Token from session is used

---

### Test 3: Token Expiry

**Steps:**
1. COLLABORATOR generates share link
2. Wait 1+ hours
3. PUBLIC user accesses link
4. Check if Track B loads

**Expected:**
- Token should be expired
- Tracks server returns 403
- Track fails to load

---

### Test 4: Direct Token Reuse

**Steps:**
1. Extract JWT token from COLLABORATOR session
2. Try to use token for different organism/assembly
3. Verify tracks server rejects (organism/assembly validation)

**Expected:**
- Tracks server validates organism/assembly in token
- Request rejected with 403
- Confirms tokens are scoped correctly

---

## Test Results Summary

### Test 2: Cross-User Access (COMPLETED 2026-02-17)

**Scenario Tested:**
1. ADMIN user logged in and opened `Nematostella_vectensis GCA_033964005.1`
2. ADMIN loaded ADMIN-only tracks:
   - `MOLNG-2707_S3-body-wall.bam` (access_level: ADMIN)
   - `MOLNG-2707_S3-body-wall.cram` (access_level: ADMIN)
3. ADMIN clicked "Share" button and copied URL
4. ADMIN logged out
5. PUBLIC user (anonymous) accessed the shared URL

**Results:**

✅ **GOOD - Data Protected:**
- Tracks do NOT load data for PUBLIC user
- Tracks are NOT available in track selector
- Config is re-fetched with PUBLIC user permissions
- No authorization bypass occurs

🟡 **CONCERN - Information Disclosure:**
- Session JSON visible in browser shows track IDs:
  - `"configuration": "MOLNG-2707_S3-body-wall.bam"`
  - `"configuration": "MOLNG-2707_S3-body-wall.cram"`
- PUBLIC user can see track names and structure
- PUBLIC user learns that ADMIN tracks exist
- Track metadata visible (track types, display configurations)

❌ **NOT FOUND - Token Leakage:**
- JWT tokens are NOT embedded in session URLs
- No tokens visible in session state
- Tracks correctly fail to load without valid authentication

**Conclusion:**
- **Authorization:** ✅ SECURE - No data breach
- **Information Disclosure:** 🟡 MODERATE RISK - Track existence leaked
- **Token Security:** ✅ SECURE - No token leakage

---

## Next Steps

1. ✅ **Document scenarios** (this document)
2. ✅ **Run Test 1:** Inspect session URL structure
3. ✅ **Run Test 2:** Test cross-user access - **INFORMATION DISCLOSURE CONFIRMED**
4. ⏳ **Run Test 3:** Test token expiry (requires waiting)
5. ⏳ **Run Test 4:** Test token scope validation
6. ✅ **Update documentation** with findings
7. 🔄 **Evaluate mitigations** based on risk tolerance

---

## References

- Main Config Generator: `/api/jbrowse2/config.php`
- Track Permission Filtering: Lines 261-320 in `config.php`
- JWT Token Generation: `/lib/jbrowse/track_token.php`
- Tracks Server: `/api/jbrowse2/tracks.php`
- JWT Validation: Lines 54-95 in `tracks.php`
- Architecture Doc: `/docs/JBrowse2/technical/dynamic-config-and-jwt-security.md`

---

**Status:** 🔴 TESTING REQUIRED  
**Priority:** HIGH - Potential data leakage  
**Next Review:** After testing completion

