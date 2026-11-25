# MOOP Track File Access Flow Diagram

**Version:** 2.0 (Updated 2026-02-25)  
**Shows:** Multi-layer security with direct access blocking

---

## Complete Security Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         USER REQUESTS TRACK FILE                        │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
        
    🚫 INSECURE PATH                    ✅ SECURE PATH
    (Direct File Access)                (API with JWT Token)
                    │                               │
                    │                               │
                    ▼                               ▼
                                        
┌───────────────────────────────┐   ┌───────────────────────────────┐
│ http://server.com/            │   │ http://server.com/            │
│   /moop/data/tracks/          │   │   /moop/api/jbrowse2/         │
│   Organism/Assembly/file.bw   │   │   tracks.php?                 │
│                               │   │   file=...&token=eyJ...       │
└───────────────┬───────────────┘   └───────────────┬───────────────┘
                │                                   │
                │                                   │
                ▼                                   ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                      LAYER 0: WEB SERVER (APACHE/NGINX)                 │
│                                                                         │
│  📍 Check 1: Is path /data/tracks/* or /data/genomes/*?                │
└─────────────────────────────────────────────────────────────────────────┘
                │                                   │
                │                                   │
     ┌──────────┴──────────┐           ┌───────────┴───────────┐
     │ YES - Direct access │           │ NO - API endpoint     │
     │ to protected files  │           │ /api/jbrowse2/*       │
     └──────────┬──────────┘           └───────────┬───────────┘
                │                                   │
                ▼                                   │
                                                    │
    🛑 403 FORBIDDEN                                │
    (Blocked by .htaccess                           │
     or nginx location)                             │
                                                    │
    ❌ FILE NOT SERVED                              │
    Security layer prevents                         │
    JWT bypass completely                           │
                                                    │
                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                     LAYER 1: PHP EXECUTION (tracks.php)                 │
│                                                                         │
│  📍 Check 2: Does request have token parameter?                        │
└─────────────────────────────────────────────────────────────────────────┘
                                                    │
                                        ┌───────────┴───────────┐
                                        │                       │
                                        ▼                       ▼
                            
                            🛑 NO TOKEN               ✅ TOKEN PROVIDED
                            
                            Return 401               Continue to validation
                            Unauthorized                    │
                                                            │
                                                            ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                 LAYER 2: JWT SIGNATURE VALIDATION                       │
│                                                                         │
│  📍 Check 3: Is JWT signature valid? (RS256 with public key)           │
└─────────────────────────────────────────────────────────────────────────┘
                                                            │
                                        ┌───────────────────┴───────────────┐
                                        │                                   │
                                        ▼                                   ▼
                            
                            🛑 INVALID SIGNATURE          ✅ VALID SIGNATURE
                            
                            Return 403                   Continue to expiry
                            Forbidden                           │
                                                                │
                                                                ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                    LAYER 3: TOKEN EXPIRY CHECK                          │
│                                                                         │
│  📍 Check 4: Is token expired? (exp claim vs current time)             │
└─────────────────────────────────────────────────────────────────────────┘
                                                                │
                                            ┌───────────────────┴───────────┐
                                            │                               │
                                            ▼                               ▼
                            
                            🛑 EXPIRED                      ✅ NOT EXPIRED
                            (External IPs)                  
                                            │                               │
                            Return 403      │               Continue to claims
                            Forbidden       │                       │
                                            │                       │
                                            ▼                       │
                                                                    │
                            ⚠️ EXPIRED BUT                          │
                            WHITELISTED IP                          │
                                            │                       │
                            Log & allow     │                       │
                            (relaxed for    │                       │
                             internal IPs)  │                       │
                                            └───────────────────────┘
                                                        │
                                                        ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 4: ORGANISM/ASSEMBLY VALIDATION                 │
│                                                                         │
│  📍 Check 5: Does token organism/assembly match requested file path?   │
│                                                                         │
│  Token claims:        { organism: "Organism_X",                        │
│                         assembly: "Assembly_1" }                        │
│                                                                         │
│  Requested file:      Organism_X/Assembly_1/bigwig/file.bw             │
└─────────────────────────────────────────────────────────────────────────┘
                                                        │
                                    ┌───────────────────┴───────────────┐
                                    │                                   │
                                    ▼                                   ▼
                        
                        🛑 MISMATCH                      ✅ MATCH
                        
                        Token for Organism_A              Token claims
                        but file is Organism_B            match file path
                                    │                                   │
                        Return 403  │                                   │
                        Access Denied                                   │
                                                                        ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                      LAYER 5: FILE EXISTENCE CHECK                      │
│                                                                         │
│  📍 Check 6: Does file exist and is it readable?                       │
└─────────────────────────────────────────────────────────────────────────┘
                                                                        │
                                    ┌───────────────────────────────────┘
                                    │
                        ┌───────────┴───────────┐
                        │                       │
                        ▼                       ▼
            
            🛑 FILE NOT FOUND           ✅ FILE EXISTS
            or NOT READABLE              & READABLE
                        │                       │
            Return 404  │                       │
            Not Found   │                       │
                        │                       │
                        │                       ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                          ✅ SERVE FILE                                  │
│                                                                         │
│  • HTTP 200 OK or 206 Partial Content (for Range requests)             │
│  • File content streamed to user                                       │
│  • Access logged with user_id, organism, assembly                      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Security Layer Summary

### Layer 0: Web Server (NEW - CRITICAL)
**Purpose:** Blocks direct file access, forces API usage  
**Technology:** Apache `.htaccess` or nginx `location` blocks  
**Result:** Direct URLs return 403 Forbidden  
**Why Critical:** Without this, JWT is completely bypassed

### Layer 1: PHP Execution
**Purpose:** Enforces token requirement  
**Technology:** tracks.php checks `$_GET['token']`  
**Result:** No token = 401 Unauthorized

### Layer 2: JWT Signature
**Purpose:** Validates cryptographic signature  
**Technology:** RS256 with public key verification  
**Result:** Invalid signature = 403 Forbidden

### Layer 3: Token Expiry
**Purpose:** Time-limited access  
**Technology:** `exp` claim check (relaxed for whitelisted IPs)  
**Result:** Expired token = 403 (or allowed for internal IPs)

### Layer 4: Claims Validation
**Purpose:** Prevents token reuse across assemblies  
**Technology:** Organism/assembly matching  
**Result:** Mismatch = 403 Access Denied

### Layer 5: File System
**Purpose:** Verify file exists  
**Technology:** PHP `file_exists()` and `is_readable()`  
**Result:** Not found = 404

---

## Attack Prevention

### Attack 1: Direct File Access (Without Layer 0)
```
❌ VULNERABLE (before fix):
Request: http://server.com/moop/data/tracks/Organism/Assembly/file.bw
Result:  File downloaded (NO AUTHENTICATION!)
```

```
✅ SECURE (after fix):
Request: http://server.com/moop/data/tracks/Organism/Assembly/file.bw
Layer 0: Web server checks path → matches /data/tracks/*
Result:  403 Forbidden (JWT never executed)
```

### Attack 2: Missing Token
```
Request: http://server.com/moop/api/jbrowse2/tracks.php?file=test.bw
Layer 0: ✅ Path is /api/* → allowed to continue
Layer 1: 🛑 No token parameter → 401 Unauthorized
```

### Attack 3: Invalid Token
```
Request: http://server.com/moop/api/jbrowse2/tracks.php?file=test.bw&token=fake123
Layer 0: ✅ Path is /api/* → allowed
Layer 1: ✅ Token exists → continue
Layer 2: 🛑 Signature invalid → 403 Forbidden
```

### Attack 4: Token Reuse Across Assemblies
```
Request: http://server.com/moop/api/jbrowse2/tracks.php?
         file=Organism_B/Assembly_2/file.bw&
         token=eyJ...(token for Organism_A/Assembly_1)

Layer 0: ✅ Path is /api/* → allowed
Layer 1: ✅ Token exists → continue
Layer 2: ✅ Signature valid → continue
Layer 3: ✅ Not expired → continue
Layer 4: 🛑 Token says Organism_A/Assembly_1 but file is Organism_B/Assembly_2
Result:  403 Access Denied
```

---

## Configuration Comparison

### WITHOUT Layer 0 (VULNERABLE)
```
Request → Apache/Nginx → Direct file access → FILE SERVED ❌
             ↓
        OR   ↓
             ↓
          tracks.php → Token validation → Serve file ✅
          (can be bypassed!)
```

### WITH Layer 0 (SECURE)
```
Request → Apache/Nginx → Check path
             ↓
             ├─→ /data/tracks/* → 403 FORBIDDEN ✅
             │   (blocked by .htaccess/nginx)
             │
             └─→ /api/tracks.php → Token validation → Serve file ✅
                  ↑
            This is where validation happens
            (ONLY way to access files)
```

---

## Key Takeaways

1. **Layer 0 is MANDATORY** - Without it, JWT security can be bypassed entirely
2. **All tracks need tokens** - Even "public" tracks (forces use of API)
3. **Defense in depth** - Multiple layers provide fail-safe security
4. **Stateless validation** - No database lookup needed for file serving
5. **Audit trail** - All access goes through tracks.php and is logged

---

**Updated:** 2026-02-25  
**Version:** 2.0 with Layer 0 (Web Server Blocking)
