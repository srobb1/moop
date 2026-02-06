# JBrowse2 Integration Plan

## Overview
Integration of JBrowse2 genome browser with MOOP system for multi-organism, multi-assembly support with track server separation.

---

## JBrowse2 Architecture: KEY INSIGHT

**JBrowse2 runs entirely in the browser (client-side).** All computation (rendering, parsing, filtering) happens in JavaScript on the client.

### Implication for File Storage

- **Configuration files**: Lightweight JSON configs are served to the browser. Must be accessible via HTTP.
- **Data files** (FASTA, GFF, BAM, BigWig, etc.): The browser downloads these files ON-DEMAND. They must be:
  - **HTTP accessible** (not just local filesystem)
  - Indexed (FASTA.fai, GFF.tbi, BAM.bai)
  - Range-request compatible (HTTP 206) so browser can fetch specific byte ranges
  - Stored somewhere with good bandwidth (can be separate server/CDN)

### Installation Location: Simpler Than Expected

JBrowse2 itself is just a **static HTML/JS/CSS site**. It can run:
- Same machine as MOOP (serve static files alongside PHP)
- Separate machine (better for scaling)
- CDN (static content)

The **actual data files** are what need careful placement:
- FASTA, GFF, BAM, BigWig files need HTTP-accessible, indexed storage
- Can be on same server, different server, or S3-like object storage
- Must support HTTP range requests

**No significant server-side computation burden** — client does the work.

---

## Core Components

### 1. Config Files Structure
- **One config per organism-assembly pair**
- Location: `/jbrowse2/configs/` (or TBD)
- Format: JSON config files
- Naming convention: `{organism_name}_{assembly_version}.json`
- Example: `homo_sapiens_GRCh38.json`, `arabidopsis_thaliana_TAIR10.json`

### 2. Data Processing Pipeline

#### Genome FASTA Indexing
- Input: Raw genome FASTA files
- Process: Index FASTA (samtools faidx or similar)
- Output: `.fasta` + `.fai` index files
- Location: Centralized FASTA directory (accessible to JBrowse2)

#### GFF File Processing
- Input: Raw GFF3/GTF files
- Process:
  1. Sort GFF file (sort by chromosome, then by coordinate)
  2. Validate GFF format
  3. Index GFF file (bgzip + tabix, or similar)
- Output: Sorted `.gff3.gz` + `.tbi` index files
- Location: Centralized annotation directory (accessible to JBrowse2)

### 3. Track Server (Separate Machine)
- BigWig files (expression, coverage, etc.)
- BAM/BAI files (alignments)
- Benefits: Isolates large binary data from main infrastructure

### 4. MOOP Permission System (Existing)

MOOP has a multi-level permission system:

**Access Levels:**
- `ALL` / Admin: Full access to all organisms/assemblies
- `Public` (default): Access to organisms/assemblies tagged as "Public" in groups
- `Collaborator`: Access to specific organism/assembly pairs defined in their `$_SESSION['access']` array

**Data Structure:**
- `metadata/organism_assembly_groups.json`: Maps each organism-assembly pair to groups (e.g., "Public", "Bats", "Corals")
- `$_SESSION['access']`: For Collaborators, array like `["Anoura_caudifer" => ["GCA_004027475.1"]]`

**Helper Functions:**
- `getAccessibleAssemblies()`: Returns user's accessible organism-assembly pairs
- `is_public_assembly($organism, $assembly)`: Checks if assembly is in a public group
- `has_access($level)`: Checks user access level

### 5. JBrowse2 Permission Integration

**Challenge:** JBrowse2 is client-side only; configs are delivered to the browser. Must prevent users from:
- Loading configs for assemblies they don't have access to
- Accessing data files they shouldn't see

**New Approach: Separate Track Configs + Dynamic Assembly Config**

Instead of one monolithic config per organism-assembly, use a modular system:

1. **Separate Track Config Files** (stored in `/moop/metadata/jbrowse2-configs/tracks/`)
   - One file per track: `{organism}_{assembly}_{track_name}.json`
   - Example: `Anoura_caudifer_GCA_004027475.1_rna_seq.json`
   - Lightweight, only references track name, type, URL
   - Version-controlled, easy to update

2. **Dynamic Assembly Config Generation** (on-the-fly per user)
   - User requests `/api/jbrowse2/assembly?organism={org}&assembly={asm}`
   - Server validates permissions via `getAccessibleAssemblies()`
   - Server loads:
     * Base assembly config (FASTA, GFF references)
     * Available track configs
     * Filters tracks based on user's permission level
     * Generates dynamic complete config in memory
   - Returns single JBrowse2-ready config JSON

3. **Track Permission Filtering by Access Level**
   - Tracks tagged with `access_level: ["Public", "Collaborator", "ALL"]`
   - Example:
     ```json
     // Track config file
     {
       "name": "RNA-seq coverage",
       "type": "quantitative",
       "access_levels": ["Public", "Collaborator", "ALL"],
       "url": "https://tracks.example.com/api/track-file?token={token}&file=rna_seq.bw"
     }
     ```
   - Server filters tracks based on user's actual access level
   - Admin users see all; Public users see only "Public" tracks; Collaborators see their assigned tracks

Benefits:
- Tracks are independently versioned
- Easy to add/remove tracks without regenerating full config
- User gets minimal config (only what they can access)
- Server controls which tracks appear in browser
- Browser cannot bypass filtering

---

## File Organization

JBrowse2 static files can be on any web server. Data files need HTTP accessibility with range request support.

**Decided Structure: Option A - All on MOOP Server**

```
/data/moop/
├── jbrowse2/                          ← JBrowse2 frontend (created by: jbrowse create jbrowse2)
│   ├── package.json                   ✓ TRACK (dependencies)
│   ├── package-lock.json              ✓ TRACK (lock versions)
│   ├── config.json                    ✓ TRACK (MOOP API integration)
│   ├── public/                        ✓ TRACK (static assets)
│   ├── node_modules/                  ✗ IGNORE (500MB+, auto-installed)
│   └── dist/                          ✗ IGNORE (compiled output)
├── api/jbrowse2/
│   ├── assembly.php                   (dynamic config endpoint)
│   ├── test-assembly.php              (test API)
│   └── fake-tracks-server.php         (tracks server simulator)
├── lib/jbrowse/
│   ├── track_token.php                (JWT generation & validation)
│   └── index.php                      (autoloader)
├── metadata/
│   └── jbrowse2-configs/tracks/       (track definitions)
├── data/
│   └── tracks/                        (BigWig, BAM files) ✗ IGNORE
└── certs/
    ├── jwt_private_key.pem            (RSA key) ✗ IGNORE
    └── jwt_public_key.pem             (public key) ✗ IGNORE
```

Key: All data files must be **HTTP-accessible** with **range request support** for browser to fetch by region.

### Git Ignore Configuration

Add to `.gitignore`:

```gitignore
# JBrowse2 frontend (track config separately, ignore dependencies)
/jbrowse2/node_modules/
/jbrowse2/dist/
/jbrowse2/.env

# Track data (too large, installation-specific)
/data/tracks/
```

**Rationale:**
- `package.json` & `package-lock.json`: Tracked to enable `npm install` on new machines
- `config.json`: Tracked because it contains MOOP-specific integration (API endpoints)
- `public/`: Tracked as it contains static app assets
- `node_modules/`: Ignored (standard practice, 500MB+, auto-generated)
- `dist/`: Ignored (compiled output, auto-generated)
- `/data/tracks/`: Ignored (large data files, installation-specific)
- `*.pem`: Ignored by existing rule (security, never commit keys)

---

## Implementation: Detailed Breakdown

### Step 1: Track Config Files (Separate, Reusable)

Create individual track config files in `/moop/metadata/jbrowse2-configs/tracks/`:

```json
// rna_seq_coverage.json
{
  "name": "RNA-seq Coverage",
  "description": "RNA-seq expression coverage across replicates",
  "type": "quantitative",
  "track_id": "rna_seq_coverage",
  "access_levels": ["Public", "Collaborator", "ALL"],
  "groups": ["Transcriptomics"],
  "file_template": "{organism}_{assembly}_rna_coverage.bw",
  "format": "bigwig",
  "color": "#1f77b4",
  "display": {
    "type": "WiggleYScaleQuantitativeTrack"
  }
}
```

```json
// dna_seq_alignment.json
{
  "name": "DNA-seq Alignment",
  "description": "Whole genome sequencing alignment",
  "type": "alignment",
  "track_id": "dna_alignment",
  "access_levels": ["ALL"],  // Only admins
  "groups": ["Sequencing"],
  "file_template": "{organism}_{assembly}_dna.bam",
  "format": "bam",
  "display": {
    "type": "LinearAlignmentsDisplay"
  }
}
```

Benefits:
- Version-controlled separately from assemblies
- Reusable across assemblies
- Easy to update metadata without regenerating all configs
- Clear access control per track

### Step 2: Dynamic Assembly Config Generation

Create `/api/jbrowse2/assembly.php`:

```php
<?php
/**
 * /api/jbrowse2/assembly.php
 * Generates complete JBrowse2 config for an organism-assembly
 * Dynamically filters tracks based on user access level
 */

require_once '../includes/access_control.php';
require_once '../lib/functions_access.php';

header('Content-Type: application/json');
header('Cache-Control: max-age=300, private');  // 5 min cache

$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';

if (empty($organism) || empty($assembly)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing organism or assembly']);
    exit;
}

// 1. VALIDATE PERMISSIONS
$accessible = getAccessibleAssemblies($organism, $assembly);
if (empty($accessible)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this assembly']);
    exit;
}

$config = ConfigManager::getInstance();
$metadata_path = $config->getPath('metadata_path');
$organism_data = $config->getPath('organism_data');

// 2. BUILD BASE ASSEMBLY CONFIG (FASTA/GFF)
$assembly_config = [
    'organism' => $organism,
    'assembly' => $assembly,
    'assemblies' => [
        [
            'name' => $assembly,
            'sequence' => [
                'type' => 'ReferenceSequenceTrack',
                'trackId' => 'reference',
                'adapter' => [
                    'type' => 'IndexedFastaAdapter',
                    'fastaLocation' => [
                        'uri' => "/data/genomes/{$organism}/{$assembly}/reference.fasta"
                    ],
                    'faiLocation' => [
                        'uri' => "/data/genomes/{$organism}/{$assembly}/reference.fasta.fai"
                    ]
                ]
            ]
        ]
    ],
    'tracks' => []
];

// 3. LOAD ALL AVAILABLE TRACK CONFIGS
$tracks_dir = "$metadata_path/jbrowse2-configs/tracks";
$track_files = glob("$tracks_dir/*.json");
$available_tracks = [];

foreach ($track_files as $track_file) {
    $track = json_decode(file_get_contents($track_file), true);
    if ($track) {
        $available_tracks[] = $track;
    }
}

// 4. FILTER TRACKS BY USER ACCESS LEVEL
$user_access_level = get_access_level();
$user_access = get_user_access();
$is_whitelisted_ip = is_whitelisted_ip();  // Check if IP is in auto_login range

foreach ($available_tracks as $track) {
    // Check if user can access this track's access levels
    $track_access_levels = $track['access_levels'] ?? ['Public'];
    
    $user_can_access = false;
    
    if ($user_access_level === 'ALL') {
        $user_can_access = true;
    } elseif (in_array('Public', $track_access_levels)) {
        $user_can_access = true;
    } elseif ($user_access_level === 'Collaborator' && in_array('Collaborator', $track_access_levels)) {
        // Check if they have access to this specific assembly
        if (isset($user_access[$organism]) && in_array($assembly, $user_access[$organism])) {
            $user_can_access = true;
        }
    }
    
    if (!$user_can_access) {
        continue;  // Skip this track
    }
    
    // 5. BUILD TRACK URL WITH TOKEN IF NEEDED
    $file_template = $track['file_template'] ?? '';
    $file_name = str_replace(
        ['{organism}', '{assembly}'],
        [$organism, $assembly],
        $file_template
    );
    
    if ($user_access_level === 'ALL' && $is_whitelisted_ip) {
        // Direct access for whitelisted IPs (internal users)
        $track_url = "https://tracks.example.com/{$track['format']}/{$file_name}";
    } else {
        // Generate token for external/collaborator access
        $token = generateTrackToken($organism, $assembly, $user_access_level);
        $track_url = "https://tracks.example.com/{$track['format']}/{$file_name}?token=" . urlencode($token);
    }
    
    // 6. ADD TRACK TO CONFIG
    $track_config = [
        'name' => $track['name'],
        'trackId' => "{$organism}_{$assembly}_{$track['track_id']}",
        'assemblyNames' => [$assembly],
        'type' => $track['type'],
        'adapter' => [
            'type' => $track['format'] === 'bam' ? 'BamAdapter' : 'BigWigAdapter',
            'bamLocation' => $track['format'] === 'bam' ? 
                ['uri' => $track_url] : 
                null,
            'baiLocation' => $track['format'] === 'bam' ? 
                ['uri' => "$track_url.bai"] : 
                null,
            'bigWigLocation' => $track['format'] !== 'bam' ? 
                ['uri' => $track_url] : 
                null
        ],
        'displays' => [$track['display'] ?? []]
    ];
    
    // Remove null values
    $track_config['adapter'] = array_filter($track_config['adapter']);
    
    $assembly_config['tracks'][] = $track_config;
}

// 7. ADD GENOME ANNOTATIONS (GFF)
if (file_exists("$organism_data/$organism/$assembly/annotations.gff3.gz")) {
    $assembly_config['tracks'][] = [
        'name' => 'Annotations',
        'trackId' => 'annotations',
        'assemblyNames' => [$assembly],
        'type' => 'FeatureTrack',
        'adapter' => [
            'type' => 'GffAdapter',
            'gffLocation' => [
                'uri' => "/data/genomes/{$organism}/{$assembly}/annotations.gff3.gz"
            ],
            'index' => [
                'location' => [
                    'uri' => "/data/genomes/{$organism}/{$assembly}/annotations.gff3.gz.tbi"
                ]
            ]
        ]
    ];
}

// 8. RETURN CONFIG
echo json_encode($assembly_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
```

### Step 3: Track Token Generation

Create `/lib/track_token.php`:

```php
<?php
/**
 * Generate JWT token for track access
 * Token includes organism, assembly, and access level
 * Track server can verify signature without calling back to MOOP
 */

function generateTrackToken($organism, $assembly, $access_level) {
    $config = ConfigManager::getInstance();
    $private_key_path = $config->getPath('jwt_private_key');
    
    if (!file_exists($private_key_path)) {
        throw new Exception('JWT private key not found');
    }
    
    $private_key = file_get_contents($private_key_path);
    
    $token = [
        'user_id' => get_username(),
        'organism' => $organism,
        'assembly' => $assembly,
        'access_level' => $access_level,
        'iat' => time(),
        'exp' => time() + 3600  // 1 hour expiry
    ];
    
    return \Firebase\JWT\JWT::encode($token, $private_key, 'HS256');
}

function is_whitelisted_ip() {
    $config = ConfigManager::getInstance();
    $ip_ranges = $config->getArray('auto_login_ip_ranges', []);
    
    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $visitor_ip_long = ip2long($visitor_ip);
    
    foreach ($ip_ranges as $range) {
        $start_long = ip2long($range['start']);
        $end_long = ip2long($range['end']);
        
        if ($visitor_ip_long !== false && 
            $visitor_ip_long >= $start_long && 
            $visitor_ip_long <= $end_long) {
            return true;
        }
    }
    
    return false;
}
?>
```

### Step 4: File Organization

```
/moop/
├── api/
│   └── jbrowse2/
│       ├── assembly.php           (GET dynamic config)
│       ├── generate_track_configs.php (maintenance)
│       └── validate-token.php      (validate JWT for external calls)
├── metadata/
│   └── jbrowse2-configs/
│       ├── tracks/                (separate track configs)
│       │   ├── rna_seq_coverage.json
│       │   ├── dna_alignment.json
│       │   ├── chip_seq.json
│       │   └── ...
│       └── (assembly configs removed - generated on-the-fly)
├── lib/
│   └── track_token.php            (JWT generation)
├── certs/
│   ├── jwt_private_key.pem
│   └── jwt_public_key.pem
└── data/
    └── genomes/                   (HTTP-accessible FASTA/GFF)
        ├── Anoura_caudifer/GCA_004027475.1/
        ├── Lasiurus_cinereus/GCA_011751095.1/
        └── ...
```

## Additional Considerations

### Remote Tracks Server Security (Critical)

The separate tracks server must prevent unauthorized access to BigWig/BAM files. Three approaches:

#### **Approach A: Token-Based Access (Recommended)**

Tokens are ephemeral, session-linked credentials passed by MOOP to clients.

```
Workflow:
1. User logs in to MOOP (session established)
2. Browser requests assembly config: /api/jbrowse2/assembly?org={org}&asm={asm}
3. MOOP API:
   a. Validates user permissions via getAccessibleAssemblies()
   b. Generates short-lived token (e.g., JWT, 1-hour expiry)
   c. Embeds token in track URLs: https://tracks.example.com/file.bw?token={token}
   d. Returns config with tokenized URLs
4. Browser requests track data: https://tracks.example.com/file.bw?token={token}&range=0-1000
5. Track server validates token before serving byte range
```

**Token Implementation Options:**

1. **JWT (JSON Web Tokens)**
   - Sign at MOOP with private key
   - Track server verifies signature with public key
   - No shared state needed; scale horizontally
   - Include claims: organism, assembly, user_id, exp timestamp
   
   ```php
   // MOOP generates JWT
   $token = [
     'user_id' => $_SESSION['username'],
     'organism' => 'Anoura_caudifer',
     'assembly' => 'GCA_004027475.1',
     'access_level' => $_SESSION['access_level'],
     'exp' => time() + 3600  // 1 hour
   ];
   $jwt = JWT::encode($token, $private_key, 'HS256');
   // Return URL: https://tracks.example.com/file.bw?token={$jwt}
   ```
   
   ```nginx
   # Nginx at tracks server validates JWT (or passes to PHP)
   location /file.bw {
     # Verify token in query param via PHP or custom auth module
     auth_request /verify-token;
     # Serve file with range support
     ...
   }
    ```apache
    # Apache at tracks server validates JWT
    <Location /file.bw>
        AuthRequest /verify-token
        AuthRequestOptions environ
        Header set Accept-Ranges bytes
    </Location>
    
    <Location /verify-token>
        SetHandler proxy:http://127.0.0.1:9000/verify-token.php
        Order allow,deny
        Allow all
    </Location>
    ```

2. **Session Token (Simpler)**
   - MOOP generates random token, stores in cache/DB with expiry
   - Maps token → {user_id, organism, assembly, access_level, exp}
   - Track server queries MOOP to validate token
   - Simpler but requires network call to MOOP for each request
   
   ```php
   // MOOP generates and caches token
   $token = bin2hex(random_bytes(32));
   $_cache->set($token, [
     'user_id' => $_SESSION['username'],
     'organism' => 'Anoura_caudifer',
     'assembly' => 'GCA_004027475.1',
     'exp' => time() + 3600
   ]);
   
   // Track server validates token by querying MOOP
   // GET /api/jbrowse2/validate-token?token={token}
   ```

#### **Approach B: Proxy All Track Requests Through MOOP**

MOOP becomes a gatekeeper for all track access.

```
Workflow:
1. Browser loads assembly config with URLs: /api/jbrowse2/track-proxy?track=rna_seq.bw&range=0-1000
2. MOOP checks session, validates user has access
3. MOOP proxies range request to tracks server (internal network, no auth needed)
4. Returns bytes to browser
```

**Pros:** Simple, central control, easy to audit
**Cons:** All track requests go through MOOP (bandwidth bottleneck), higher latency

```php
// /api/jbrowse2/track-proxy.php
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    exit('Forbidden');
}

$organism = $_GET['organism'];
$assembly = $_GET['assembly'];
$track = $_GET['track'];
$range = $_GET['range'];  // e.g., "0-1000"

// Validate user has access to this organism-assembly
$accessible = getAccessibleAssemblies($organism, $assembly);
if (empty($accessible)) {
    http_response_code(403);
    exit('Access denied');
}

// Proxy request to internal tracks server
$internal_url = "https://internal-tracks.local/bigwig/{$organism}_{$assembly}_{$track}.bw";
$ch = curl_init($internal_url);
curl_setopt($ch, CURLOPT_RANGE, $range);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Internal network

$data = curl_exec($ch);
$info = curl_getinfo($ch);

// Return with proper range headers
header('HTTP/1.1 206 Partial Content');
header("Content-Length: " . strlen($data));
header("Content-Range: bytes {$range}/{$info['content_length_download']}");
header("Accept-Ranges: bytes");
header("Content-Type: application/octet-stream");

echo $data;
```

#### **Approach C: IP Whitelist + Session Validation (Hybrid)**

For internal networks where tracks server is trusted.

```
Setup:
1. Tracks server only accessible from MOOP server IP
2. MOOP validates session before issuing config
3. Track URLs can be simple (no auth per-request)
4. Browser requests tracks; tracks server trusts requests from MOOP's IP
```

**Pros:** Minimal overhead, simple
**Cons:** Only works for internal networks; doesn't handle external JBrowse2 access

---

### Recommended Architecture: Token-Based (Approach A + Hybrid)

**For internal/trusted users (ALL access IP range):**
- Simple file serving, IP-whitelisted access
- No per-request auth overhead
- Fast, efficient

**For external/collaborators:**
- JWT tokens embedded in track URLs
- Tokens include claims about access level
- Track server validates token, extracts user info
- Browser fetches via tokenized URL
- Tokens expire (1 hour)

**Config generation handles both:**
```php
// /api/jbrowse2/assembly.php
$user_access_level = get_access_level();

foreach ($available_tracks as $track) {
    // Check if user can access this track
    if (!in_array($user_access_level, $track['access_levels'])) {
        continue;  // Skip this track for this user
    }
    
    if ($user_access_level === 'ALL' && is_ip_whitelisted()) {
        // Fast path: direct URL, no token needed
        $track_url = "https://tracks.example.com/bigwig/file.bw";
    } else {
        // Generate token for external/collaborator access
        $token = generateToken([
            'user_id' => get_username(),
            'organism' => $organism,
            'assembly' => $assembly,
            'access_level' => $user_access_level,
            'exp' => time() + 3600
        ]);
        $track_url = "https://tracks.example.com/bigwig/file.bw?token=" . urlencode($token);
    }
    
    // Add to config
    $config['tracks'][] = [
        'name' => $track['name'],
        'url' => $track_url,
        'access_levels' => $track['access_levels']
    ];
}

return $config;
```

---

### Tracks Server Implementation (Nginx + PHP)

```nginx
# /etc/nginx/sites-available/tracks.example.com

upstream php_backend {
    server 127.0.0.1:9000;
}

server {
    listen 443 ssl http2;
    server_name tracks.example.com;

    ssl_certificate /etc/ssl/certs/tracks.crt;
    ssl_certificate_key /etc/ssl/private/tracks.key;

    root /var/www/tracks;

    # BigWig files
    location ~ ^/bigwig/(.+\.bw)$ {
        # Require token for non-whitelisted IPs
        if ($remote_addr !~* ^(10\.0\.0\.0/8|172\.16\.0\.0/12|192\.168\.0\.0/16)) {
            # External request: validate token
            auth_request /verify-token;
        }

        # Enable range requests
        proxy_set_header Range $http_range;
        add_header Accept-Ranges bytes;
        
        try_files $uri =404;
    }

    # Token validation endpoint
    location = /verify-token {
        internal;
        
        # Call PHP to validate JWT token
        proxy_pass http://php_backend/verify-token.php;
        proxy_pass_request_body off;
        proxy_set_header Content-Length "";
        proxy_set_header X-Token $arg_token;
        proxy_set_header X-Original-URL $request_uri;
    }

    # BAM files (similar structure)
    location ~ ^/bam/(.+\.bam)$ {
        if ($remote_addr !~* ^(10\.0\.0\.0/8|172\.16\.0\.0/12|192\.168\.0\.0/16)) {
            auth_request /verify-token;
        }
        add_header Accept-Ranges bytes;
        try_files $uri =404;
    }
}
```

### Tracks Server Implementation (Apache + PHP)

```apache
# /etc/apache2/sites-available/tracks.example.com.conf

<VirtualHost *:443>
    ServerName tracks.example.com
    DocumentRoot /var/www/tracks
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/tracks.crt
    SSLCertificateKeyFile /etc/ssl/private/tracks.key
    
    # Enable required modules
    # a2enmod headers
    # a2enmod rewrite
    # a2enmod proxy_http
    # a2enmod mod_auth_request (Apache 2.4.5+) OR use rewrite method below

    # BigWig files - Method 1: Using mod_auth_request (Apache 2.4.5+)
    <Directory /var/www/tracks/bigwig>
        Options +FollowSymLinks
        Header set Accept-Ranges bytes
        
        SetEnvIf Remote_Addr "^10\.0\.0\." trusted_ip
        SetEnvIf Remote_Addr "^172\.16\.0\." trusted_ip
        SetEnvIf Remote_Addr "^192\.168\.0\." trusted_ip
        
        <If "!env('trusted_ip')">
            # External IP: require token validation
            AuthRequest /verify-token
            AuthRequestOptions environ
        </If>
    </Directory>
    
    # BigWig files - Method 2: Using RewriteRule (if mod_auth_request unavailable)
    <Directory /var/www/tracks/bigwig>
        Options +FollowSymLinks
        Header set Accept-Ranges bytes
        
        RewriteEngine On
        
        SetEnvIf Remote_Addr "^10\.0\.0\." trusted_ip
        SetEnvIf Remote_Addr "^172\.16\.0\." trusted_ip
        SetEnvIf Remote_Addr "^192\.168\.0\." trusted_ip
        
        # If not from trusted IP and no token parameter, deny
        <If "!env('trusted_ip')">
            RewriteCond %{QUERY_STRING} !token=.+
            RewriteRule ^.*$ - [F,L]
            # If token exists, proxy to PHP for validation
            RewriteCond %{QUERY_STRING} token=(.+)
            RewriteRule ^(.+\.bw)$ http://127.0.0.1:9000/verify-token.php?file=$1&token=%1 [P,QSA,L]
        </If>
    </Directory>
    
    # Token validation endpoint
    <Location /verify-token>
        SetHandler proxy:http://127.0.0.1:9000/verify-token.php
        Order allow,deny
        Allow all
    </Location>
    
    # BAM files
    <Directory /var/www/tracks/bam>
        Options +FollowSymLinks
        Header set Accept-Ranges bytes
        
        SetEnvIf Remote_Addr "^10\.0\.0\." trusted_ip
        SetEnvIf Remote_Addr "^172\.16\.0\." trusted_ip
        SetEnvIf Remote_Addr "^192\.168\.0\." trusted_ip
        
        <If "!env('trusted_ip')">
            AuthRequest /verify-token
            AuthRequestOptions environ
        </If>
    </Directory>
    
    # BAI (BAM index) files
    <Directory /var/www/tracks/bam>
        <FilesMatch "\.bai$">
            Header set Accept-Ranges bytes
            SetEnvIf Remote_Addr "^10\.0\.0\." trusted_ip
            SetEnvIf Remote_Addr "^172\.16\.0\." trusted_ip
            SetEnvIf Remote_Addr "^192\.168\.0\." trusted_ip
            
            <If "!env('trusted_ip')">
                AuthRequest /verify-token
                AuthRequestOptions environ
            </If>
        </FilesMatch>
    </Directory>
</VirtualHost>
```

```php
// /var/www/tracks/verify-token.php

<?php
// This runs as internal auth_request from Nginx
// Input: X-Token header, X-Original-URL
// Output: 200 (valid) or 403 (invalid)

$token = $_SERVER['HTTP_X_TOKEN'] ?? '';
$url = $_SERVER['HTTP_X_ORIGINAL_URL'] ?? '';

if (empty($token)) {
    http_response_code(403);
    exit('No token provided');
}

// Verify JWT
$public_key = file_get_contents('/etc/tracks-server/moop-public-key.pem');

try {
    $decoded = \Firebase\JWT\JWT::decode($token, new Key($public_key, 'HS256'));
    
    // Optional: Check token claims (organism, assembly, expiry)
    if ($decoded->exp < time()) {
        http_response_code(403);
        exit('Token expired');
    }
    
    // Optional: Further validation based on requested file
    // e.g., verify organism/assembly in URL matches token claims
    
    http_response_code(200);
} catch (Exception $e) {
    http_response_code(403);
    exit('Invalid token: ' . $e->getMessage());
}
```

---

### Session Token Approach (If Avoiding JWT)

Alternative: MOOP caches tokens, track server validates via API callback.

```php
// MOOP: /api/jbrowse2/assembly.php
$token = bin2hex(random_bytes(32));
$_SESSION['track_token'] = $token;
$_SESSION['track_token_exp'] = time() + 3600;
// Store in cache/DB with expiry
$cache->set("track_token:{$token}", $_SESSION['username']);

// Return URLs with token
$track_url = "https://tracks.example.com/bigwig/file.bw?token={$token}";
```

```php
// Tracks server: /verify-token.php
$token = $_SERVER['HTTP_X_TOKEN'] ?? '';

// Query MOOP to validate token
$moop_url = "https://moop.internal/api/jbrowse2/validate-token?token={$token}";
$ch = curl_init($moop_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Internal network
$response = json_decode(curl_exec($ch));

if ($response->valid) {
    http_response_code(200);
} else {
    http_response_code(403);
}
```

Simpler but adds latency (tracks server must call MOOP for every request).

```apache
# Tracks server: /verify-token.php using Apache
# Method 1: Direct cURL approach (rewrite-based)
<Location /verify-token>
    SetHandler proxy:http://127.0.0.1:9000/verify-token.php
    Order allow,deny
    Allow all
</Location>

# Alternatively, configure in Apache to proxy token validation requests
<VirtualHost *:443>
    ServerName tracks.example.com
    
    RewriteEngine On
    
    # For BigWig/BAM files, validate token before serving
    <Directory /var/www/tracks/bigwig>
        RewriteCond %{QUERY_STRING} !token=.+
        RewriteRule ^.*$ - [F,L]
        
        # If token exists, pass it to verify-token endpoint
        RewriteRule ^(.+\.bw)$ http://127.0.0.1:9000/verify-token.php?file=$1 [P,QSA,L]
    </Directory>
    
    <Directory /var/www/tracks/bam>
        RewriteCond %{QUERY_STRING} !token=.+
        RewriteRule ^.*$ - [F,L]
        
        RewriteRule ^(.+\.bam)$ http://127.0.0.1:9000/verify-token.php?file=$1 [P,QSA,L]
    </Directory>
</VirtualHost>
```

---

## Technology Stack

- **JBrowse2**: Browser-based genome viewer
- **MOOP Backend**: Permission & config management
- **Indexing Tools**: samtools (FASTA), bgzip/tabix (GFF)
- **File System**: NFS or local, depending on machine separation
- **API**: REST endpoint for permission checking
- **Track Server Protocol**: HTTP or NFS (TBD)

---

## Security Considerations

- [ ] Config files must not expose paths to data user shouldn't access
- [ ] API endpoints for config loading must validate permissions
- [ ] Track server URLs should be user-permission-filtered
- [ ] Consider CORS if JBrowse2 is on separate domain
- [ ] Audit logging for genome browser access
