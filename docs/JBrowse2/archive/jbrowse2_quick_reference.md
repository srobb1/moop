# JBrowse2 Integration - Quick Reference Guide

## What We're Building

A permission-aware JBrowse2 integration where:
- Different users see different tracks based on their access level
- All track data secured with JWT tokens
- Separate tracks server keeps BigWig/BAM isolated
- MOOP handles authentication & permission logic

## Directory Structure

```
/data/moop/
├── jbrowse2/                          ← JBrowse2 frontend (static React app)
│   ├── package.json                   ✓ TRACK (dependencies)
│   ├── package-lock.json              ✓ TRACK (lock versions)
│   ├── config.json                    ✓ TRACK (MOOP integration config)
│   ├── public/                        ✓ TRACK (static HTML/CSS/JS)
│   ├── node_modules/                  ✗ IGNORE (auto-installed)
│   └── dist/                          ✗ IGNORE (compiled output)
├── api/jbrowse2/
│   ├── assembly.php                   ✓ TRACK (production API)
│   ├── test-assembly.php              ✓ TRACK (test API)
│   └── fake-tracks-server.php         ✓ TRACK (tracks simulator)
├── lib/jbrowse/
│   ├── track_token.php                ✓ TRACK (JWT functions)
│   └── index.php                      ✓ TRACK (autoloader)
├── metadata/jbrowse2-configs/
│   └── tracks/                        ✓ TRACK (track definitions)
├── data/tracks/                       ✗ IGNORE (data files)
└── certs/
    ├── jwt_private_key.pem            ✗ IGNORE (security)
    └── jwt_public_key.pem             ✗ IGNORE (security)
```

## Git Ignore Configuration

Add to `.gitignore`:

```gitignore
# JBrowse2 frontend (track config separately, ignore dependencies)
/jbrowse2/node_modules/
/jbrowse2/dist/
/jbrowse2/.env

# Track data (too large, installation-specific)
/data/tracks/
```

**Already ignored by existing rules:**
- `metadata/*` - Dynamic data
- `*.pem` - JWT keys (secrets)

**Deployment on new machine:**
```bash
git clone <repo>
cd /data/moop/jbrowse2
npm install              # Recreates node_modules from package-lock.json
```

---

## Three Main Files to Create

### 1. `/api/jbrowse2/assembly.php`

The heart of the system. Returns dynamic config per user.

```
GET /api/jbrowse2/assembly?organism=Anoura_caudifer&assembly=GCA_004027475.1

What it does:
1. Check if user has access to organism-assembly
2. Load all track configs from /metadata/jbrowse2-configs/tracks/
3. Filter tracks by user's access level
4. Generate JWT token for each accessible track
5. Embed tokens in track URLs
6. Return complete JBrowse2-ready config JSON
```

### 2. `/lib/track_token.php`

Generates JWT tokens.

```php
function generateTrackToken($organism, $assembly, $access_level)
  - Signs token with MOOP private key
  - Includes: user_id, organism, assembly, access_level, exp=+1hour
  - Returns: JWT string
```

### 3. Tracks Server: `/validate-jwt.php`

Validates tokens before serving track data.

```
What it does:
1. Receives token in query param (?token=JWT)
2. Verifies signature using public key (from MOOP)
3. Checks expiration
4. Verifies org/asm match requested file
5. Returns 200 (valid) or 403 (invalid)
```

---

## File Structure

```
/moop/metadata/jbrowse2-configs/tracks/

  rna_seq_coverage.json
  ├─ "name": "RNA-seq Coverage"
  ├─ "access_levels": ["Public", "Collaborator", "ALL"]
  ├─ "file_template": "{organism}_{assembly}_rna_coverage.bw"
  ├─ "file_format": "bigwig"
  └─ "display_config": {...}

  dna_alignment.json
  ├─ "name": "DNA Alignment"
  ├─ "access_levels": ["ALL"]
  ├─ "file_template": "{organism}_{assembly}_dna.bam"
  ├─ "file_format": "bam"
  └─ "display_config": {...}

  histone_h3k4me3.json
  ├─ "name": "H3K4me3 ChIP-seq"
  ├─ "access_levels": ["Collaborator", "ALL"]
  ├─ "file_template": "{organism}_{assembly}_h3k4me3.bw"
  ├─ "file_format": "bigwig"
  └─ "display_config": {...}
```

---

## Access Levels Explained

### Public
- Visible to: Anyone
- Required: Track has "Public" in access_levels
- Examples: Reference annotations, published data

### Collaborator
- Visible to: Users with this org-assembly in $_SESSION['access']
- Required: Track has "Collaborator" in access_levels
- Examples: Unpublished ChIP-seq, draft genomes

### ALL
- Visible to: Admins only (access_level === 'ALL')
- Required: Track has "ALL" in access_levels
- Examples: Raw BAM files, private data

---

## Request Flow

```
1. User logs into MOOP
   └─ Session established with access_level, access array

2. Browser requests assembly config
   GET /api/jbrowse2/assembly?organism=Anoura&assembly=GCA_004027475.1

3. MOOP /api/jbrowse2/assembly.php
   a. getAccessibleAssemblies() → Check permission
   b. Load track config files
   c. For each track:
      - Is user's access_level in track's access_levels?
      - If YES: Generate token, embed in URL
      - If NO: Skip this track
   d. Return config JSON with tokenized URLs

4. Browser receives config
   tracks_array = [
     {url: "https://tracks/file1.bw?token=JWT1"},
     {url: "https://tracks/file2.bw?token=JWT2"}
   ]

5. Browser makes HTTP range request
   GET /bigwig/file1.bw?token=JWT1
   Range: bytes=0-1000

6. Tracks server /validate-jwt.php
   a. Extract token from query param
   b. Verify JWT signature
   c. Check expiration (hasn't expired)
   d. Verify org/asm from token match file
   e. Return 200 → serve bytes

7. Browser receives 206 Partial Content
   Displays data in genome browser
```

---

## Data Flow Diagram

```
MOOP Server (Trusted)
  │
  ├─ Private Key: /etc/moop/jwt_private_key.pem
  │  (generates tokens)
  │
  ├─ Track Configs: /metadata/jbrowse2-configs/tracks/
  │  (defines access_levels per track)
  │
  └─ Permission Data: $_SESSION, organism_assembly_groups.json
     (knows what user can access)

                    ↓ Token + Track URL

User's Browser (Untrusted)
  │
  ├─ Cannot forge tokens (needs private key)
  ├─ Cannot see tracks without token
  └─ Sends token with range requests

                    ↓ Token + Range Header

Tracks Server (Stateless)
  │
  ├─ Public Key: /etc/tracks-server/jwt_public_key.pem
  │  (verifies tokens)
  │
  └─ BigWig/BAM Files: /var/tracks/data/
     (serves only with valid token)
```

---

## JWT Token Contents

```json
{
  "user_id": "alice",
  "organism": "Anoura_caudifer",
  "assembly": "GCA_004027475.1",
  "access_level": "Collaborator",
  "iat": 1707090000,           // Issued at
  "exp": 1707093600            // Expires in 1 hour
}
```

Signed with MOOP's private key. Tracks server verifies signature with public key.

---

## Setting Up Keys (One-Time)

```bash
# On MOOP server
openssl genrsa -out /etc/moop/jwt_private_key.pem 4096
openssl rsa -in /etc/moop/jwt_private_key.pem -pubout \
  -out /etc/moop/jwt_public_key.pem

# Copy public key to tracks server
scp /etc/moop/jwt_public_key.pem tracks-admin@tracks.example.com:/etc/tracks-server/

# Verify
ls -la /etc/moop/jwt_*.pem
ssh tracks-admin@tracks.example.com ls -la /etc/tracks-server/jwt_public_key.pem
```

---

## Nginx Config (Tracks Server)

```nginx
location ~ ^/bigwig/(.+\.bw)$ {
    # Require token for external IPs
    if ($remote_addr !~* ^10\.0\.0\.0/8) {
        if ($arg_token = "") {
            return 403;
        }
        auth_request /validate-jwt;
    }
    
    # Enable HTTP range requests
    add_header Accept-Ranges bytes;
    try_files $uri =404;
}

location = /validate-jwt {
    internal;
    proxy_pass http://127.0.0.1:9000/validate-jwt.php;
    proxy_pass_request_body off;
    proxy_set_header X-Token $arg_token;
}
```

## Apache Config (Tracks Server)

```apache
<Directory /var/www/tracks/bigwig>
    # Enable mod_headers and mod_rewrite
    Options +FollowSymLinks
    
    # Allow range requests
    Header set Accept-Ranges bytes
    
    # Require token for external IPs
    SetEnvIf Remote_Addr "^10\.0\.0\." local_network
    
    <RequireAll>
        Require all granted
        Require env local_network
    </RequireAll>
    
    # If not on local network, validate token
    <If "!env('local_network')">
        # Check if token parameter exists
        RewriteEngine On
        RewriteCond %{QUERY_STRING} !token=.+
        RewriteRule ^.*$ - [F,L]
        
        # Proxy validation request to validate-jwt.php
        RewriteRule ^(.+\.bw)$ http://127.0.0.1:9000/validate-jwt.php?file=$1 [P,QSA,L]
    </If>
</Directory>

<Location /validate-jwt>
    SetHandler proxy:http://127.0.0.1:9000/validate-jwt.php
    Order allow,deny
    Allow all
</Location>
```

Alternatively, using `mod_auth_request` (Apache 2.4.5+):

```apache
<Directory /var/www/tracks/bigwig>
    Options +FollowSymLinks
    
    # Enable range requests
    Header set Accept-Ranges bytes
    
    # Allow local network without auth
    SetEnvIf Remote_Addr "^10\.0\.0\." skip_auth
    
    # For external IPs, validate token
    <If "!env('skip_auth')">
        AuthRequest /validate-jwt
    </If>
</Directory>

<Location /validate-jwt>
    SetHandler proxy:http://127.0.0.1:9000/validate-jwt.php
    AuthRequestOptions environ
    Order allow,deny
    Allow all
</Location>
```

---

## Testing Checklist

```bash
# 1. Test token generation
curl "http://moop.local/api/jbrowse2/assembly?organism=Anoura&assembly=GCA_004027475.1" \
  -b "PHPSESSID=..." | jq '.tracks[0].adapter'
# Should see: "uri": "https://tracks.example.com/bigwig/file.bw?token=eyJ..."

# 2. Test token validation (from external IP)
curl "https://tracks.example.com/bigwig/Anoura_caudifer_GCA_004027475.1_rna.bw?token=INVALID" \
  -v
# Should return: 403 Forbidden

# 3. Test with valid token
TOKEN=$(curl ... | jq '.tracks[0].adapter.uri' | grep -oP '(?<=token=)[^"]*')
curl "https://tracks.example.com/bigwig/Anoura_caudifer_GCA_004027475.1_rna.bw?token=$TOKEN" \
  -H "Range: bytes=0-1000" -v
# Should return: 206 Partial Content + bytes

# 4. Test access control (different users)
# Login as public user → only public tracks appear
# Login as collaborator → public + collaborator tracks
# Login as admin → all tracks

# 5. Test token expiration
# Wait 1+ hour, try old token → 403
```

---

## Common Issues & Solutions

| Problem | Cause | Solution |
|---------|-------|----------|
| Browser gets 403 on track | No token in URL | Check assembly.php is generating tokens |
| Token validation fails | Public key doesn't match private key | Verify key pair was generated correctly |
| Token always invalid | System clocks out of sync | Sync NTP between servers |
| Can't see track metadata | Track config file missing | Create track config in /metadata/jbrowse2-configs/tracks/ |
| Different users see same tracks | access_levels not filtering | Check getAccessibleAssemblies() result, check filtering logic |
| HTTP range requests fail | Track server not accepting Range header | Verify Nginx has `add_header Accept-Ranges bytes` |

---

## Next Steps

1. Generate JWT keys
2. Create `/api/jbrowse2/assembly.php`
3. Create `/lib/track_token.php`
4. Create track config files
5. Set up tracks server with `/validate-jwt.php`
6. Test with curl
7. Deploy JBrowse2 frontend
8. Test end-to-end with different user accounts

See detailed documentation in:
- `/notes/jbrowse2_integration_plan.md` - Full architecture
- `/notes/jbrowse2_track_access_security.md` - Security implementation
- `/notes/jbrowse2_track_config_guide.md` - Track config examples
