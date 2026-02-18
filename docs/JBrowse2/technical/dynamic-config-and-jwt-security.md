# MOOP JBrowse2: Dynamic Configuration Architecture

**Version:** 3.1  
**Last Updated:** February 18, 2026  
**For:** JBrowse2 Community & Developers

**Purpose:** Technical deep-dive into how MOOP dynamically generates per-user JBrowse2 configurations with permission-based filtering and JWT-secured track access.

---

## Recent Updates (2026-02-18)

**Security improvements:**
1. All users (including IP-whitelisted) now receive JWT tokens
2. External URLs (`https://`, `http://`, `ftp://`) never modified (no token leakage)
3. Whitelisted IPs: Relaxed expiry checking but organism/assembly validation enforced

---

## Executive Summary

MOOP implements a novel approach to JBrowse2 deployment: instead of static `config.json` files, configurations are generated dynamically per-request based on user authentication state. This enables fine-grained access control while maintaining JBrowse2's standard API.

**Key Innovation:** Metadata-driven + permission-filtered + token-authenticated

**Benefits:**
- ✅ Single JBrowse2 instance serves multiple access levels
- ✅ Tracks invisible to unauthorized users (filtered server-side)
- ✅ No client-side security decisions
- ✅ Standard JBrowse2 React app (no forks/patches)
- ✅ Scalable: stateless tracks servers with JWT validation
- ✅ External URLs supported (public reference data)
- ✅ Scalable: stateless tracks servers with JWT validation

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Request Flow](#request-flow)
3. [Metadata System](#metadata-system)
4. [Configuration API](#configuration-api)
5. [Permission Filtering](#permission-filtering)
6. [JWT Token Integration](#jwt-token-integration)
7. [JBrowse2 Client Integration](#jbrowse2-client-integration)

---

## System Architecture

### Component Overview

```
┌────────────────────────────────────────────────────────────────┐
│                  MOOP Web Application                          │
│                  (PHP + Apache/Nginx)                          │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌──────────────────────┐     ┌──────────────────────┐       │
│  │  jbrowse2.php        │     │  Session Auth        │       │
│  │  (entry point)       │────>│  (access_control.php)│       │
│  └──────────────────────┘     └──────────────────────┘       │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │  JBrowse2 React App (standard @jbrowse/react-linear-genome-view)  │
│  │  - Embedded in MOOP layout                           │    │
│  │  - Fetches config from API                           │    │
│  │  - Makes track requests with embedded JWT tokens     │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │  api/jbrowse2/config.php (Configuration Generator)   │    │
│  │  ┌────────────────────────────────────────────┐     │    │
│  │  │  1. Check user session + access level      │     │    │
│  │  │  2. Load assembly/track metadata from JSON │     │    │
│  │  │  3. Filter by permissions                   │     │    │
│  │  │  4. Generate JWT tokens                     │     │    │
│  │  │  5. Inject tokens into track URIs          │     │    │
│  │  │  6. Return complete JBrowse2 config        │     │    │
│  │  └────────────────────────────────────────────┘     │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │  lib/jbrowse/track_token.php                         │    │
│  │  - generateTrackToken(): Sign with RS256 + private key│   │
│  │  - verifyTrackToken(): Verify with RS256 + public key│   │
│  │  - isWhitelistedIP(): Check IP-based bypass          │   │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │  api/jbrowse2/tracks.php (File Server)               │    │
│  │  - Validates JWT tokens                               │    │
│  │  - Verifies organism/assembly claims                  │    │
│  │  - Serves files with HTTP range support              │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
└────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────┐
│               Metadata (JSON Files)                            │
├────────────────────────────────────────────────────────────────┤
│  metadata/jbrowse2-configs/                                   │
│  ├── assemblies/                                              │
│  │   └── Organism_Assembly.json  (assembly definitions)      │
│  └── tracks/                                                  │
│      └── Organism/Assembly/type/track.json (track configs)   │
└────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────┐
│              Track Data Files                                  │
├────────────────────────────────────────────────────────────────┤
│  data/tracks/ or data/genomes/                                │
│  └── Organism/Assembly/                                       │
│      ├── bigwig/*.bw                                          │
│      ├── bam/*.bam                                            │
│      └── gff/*.gff3.gz                                        │
└────────────────────────────────────────────────────────────────┘
```

---

## Request Flow

### Flow Diagram: User Opens JBrowse2

```
┌─────────┐                 ┌──────────┐                 ┌──────────┐
│ Browser │                 │   MOOP   │                 │  Tracks  │
│         │                 │  Server  │                 │  Server  │
└────┬────┘                 └────┬─────┘                 └────┬─────┘
     │                           │                            │
     │ 1. GET /jbrowse2.php      │                            │
     ├──────────────────────────>│                            │
     │                           │                            │
     │                           │ Check session auth         │
     │                           │ Determine access level     │
     │                           │                            │
     │ 2. HTML + React app       │                            │
     │<──────────────────────────┤                            │
     │                           │                            │
     │ 3. GET /api/jbrowse2/config.php                       │
     ├──────────────────────────>│                            │
     │                           │                            │
     │                           │ Load metadata              │
     │                           │ Filter by permissions      │
     │                           │                            │
     │ 4. Assembly list (JSON)   │                            │
     │<──────────────────────────┤                            │
     │   {assemblies: [...]}     │                            │
     │                           │                            │
     │ User clicks assembly      │                            │
     │                           │                            │
     │ 5. GET /api/jbrowse2/config.php?organism=X&assembly=Y │
     ├──────────────────────────>│                            │
     │                           │                            │
     │                           │ Verify user access         │
     │                           │ Load assembly definition   │
     │                           │ Load all track metadata    │
     │                           │ Filter tracks by access    │
     │                           │ Generate JWT token:        │
     │                           │   {organism: X,            │
     │                           │    assembly: Y,            │
     │                           │    exp: now+3600}          │
     │                           │ Inject token into URIs     │
     │                           │                            │
     │ 6. Full config + tokens   │                            │
     │<──────────────────────────┤                            │
     │   {assemblies: [...],     │                            │
     │    tracks: [               │                            │
     │      {uri: "tracks.php?file=X/Y/sample.bw&token=JWT"}  │
     │    ]}                     │                            │
     │                           │                            │
     │ JBrowse2 renders genome   │                            │
     │ User navigates to region  │                            │
     │                           │                            │
     │ 7. GET tracks.php?file=X/Y/sample.bw&token=JWT        │
     ├───────────────────────────┼───────────────────────────>│
     │                           │                            │
     │                           │                            │ Verify JWT sig
     │                           │                            │ Check exp < now
     │                           │                            │ Validate X/Y match
     │                           │                            │ Check file exists
     │                           │                            │
     │ 8. Binary track data (HTTP 206 w/ range)              │
     │<───────────────────────────────────────────────────────┤
     │                           │                            │
     │ JBrowse2 renders track    │                            │
     │                           │                            │
```

### Detailed Steps

**Step 1-2: Page Load**
- User requests `/jbrowse2.php`
- PHP checks `$_SESSION` for authentication
- Returns HTML with JBrowse2 React app embedded
- User info passed to JavaScript: `window.moopUserInfo = {logged_in, username, access_level}`

**Step 3-4: Assembly List**
- React app calls `/api/jbrowse2/config.php` (no organism/assembly params)
- PHP determines user access level from session
- Loads all assembly metadata from `metadata/jbrowse2-configs/assemblies/*.json`
- Filters assemblies by `defaultAccessLevel` vs user level
- Returns JSON with accessible assemblies only

**Step 5-6: Full Configuration**
- User clicks assembly, React calls `/api/jbrowse2/config.php?organism=X&assembly=Y`
- PHP validates user has access to this assembly
- Loads assembly definition + all track metadata for that assembly
- Filters tracks by `access_level` metadata vs user permissions
- Generates JWT token scoped to organism/assembly (for ALL users)
- Recursively injects token into MOOP-hosted track URIs (skips external URLs)
- Returns complete JBrowse2 config (standard format) with tokens embedded

**Step 7-8: Track Data Request**
- JBrowse2 requests track data using URI from config (includes JWT token)
- `tracks.php` validates JWT signature and organism/assembly claims
- Whitelisted IPs: Can use expired tokens (relaxed validation)
- Serves file with HTTP range support
- Serves file with HTTP range support (critical for BigWig/BAM)

---

## Metadata System

### Directory Structure

```
metadata/jbrowse2-configs/
├── assemblies/
│   ├── Nematostella_vectensis_GCA_033964005.1.json
│   ├── Organism2_GCA_000002.1.json
│   └── ...
└── tracks/
    ├── Nematostella_vectensis/
    │   └── GCA_033964005.1/
    │       ├── bigwig/
    │       │   ├── rnaseq_sample1.json
    │       │   └── chipseq_sample2.json
    │       ├── bam/
    │       │   └── reads.json
    │       ├── gff/
    │       │   └── genes.json
    │       └── bed/
    │           └── features.json
    └── synteny/  (for dual-assembly comparisons)
        └── Assembly1_Assembly2/
            └── maf/
                └── alignment.json
```

### Assembly Metadata Format

**File:** `metadata/jbrowse2-configs/assemblies/Organism_Assembly.json`

```json
{
    "name": "Nematostella_vectensis_GCA_033964005.1",
    "displayName": "Nematostella vectensis (GCA_033964005.1)",
    "organism": "Nematostella_vectensis",
    "assemblyId": "GCA_033964005.1",
    "aliases": ["GCA_033964005.1", "Nvec200"],
    "defaultAccessLevel": "PUBLIC",
    
    "sequence": {
        "type": "ReferenceSequenceTrack",
        "trackId": "Nematostella_vectensis_GCA_033964005.1-ReferenceSequenceTrack",
        "adapter": {
            "type": "IndexedFastaAdapter",
            "fastaLocation": {
                "uri": "/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/reference.fasta",
                "locationType": "UriLocation"
            },
            "faiLocation": {
                "uri": "/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/reference.fasta.fai",
                "locationType": "UriLocation"
            }
        }
    },
    
    "metadata": {
        "createdAt": "2026-02-18T17:27:09Z",
        "source": "PHP track generator",
        "description": "Assembly definition for Nematostella vectensis"
    }
}
```

**Key Fields:**
- `name`: Unique assembly identifier (used in config)
- `organism` + `assemblyId`: Used for JWT token scoping
- `defaultAccessLevel`: Controls who can see this assembly (PUBLIC/COLLABORATOR/ADMIN)
- `sequence`: Standard JBrowse2 reference sequence track definition
- `aliases`: Alternative names (e.g., short names, version numbers)

### Track Metadata Format

**File:** `metadata/jbrowse2-configs/tracks/Organism/Assembly/type/trackname.json`

```json
{
    "trackId": "track_e1f2d5134e",
    "name": "RNA-Seq Sample 1",
    "assemblyNames": ["Nematostella_vectensis_GCA_033964005.1"],
    "category": ["RNA-Seq", "Coverage"],
    
    "type": "QuantitativeTrack",
    "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
            "uri": "/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/sample1.bw",
            "locationType": "UriLocation"
        }
    },
    
    "displays": [
        {
            "type": "LinearWiggleDisplay",
            "displayId": "track_e1f2d5134e-LinearWiggleDisplay",
            "defaultRendering": "density"
        }
    ],
    
    "metadata": {
        "management_track_id": "rnaseq_sample1",
        "description": "RNA-Seq coverage from experiment XYZ",
        "access_level": "COLLABORATOR",
        "file_path": "/var/www/html/moop/data/tracks/.../sample1.bw",
        "is_remote": false,
        "added_date": "2026-02-18T17:27:09Z",
        
        "google_sheets_metadata": {
            "technique": "RNA-Seq",
            "institute": "Vienna",
            "source": "Lab_A",
            "experiment": "Developmental_Timecourse",
            "summary": "Embryonic stages 0-72h"
        }
    }
}
```

**Key Fields:**
- `trackId`: Unique identifier (auto-generated hash)
- `type`: JBrowse2 track type (QuantitativeTrack, FeatureTrack, AlignmentsTrack, etc.)
- `adapter`: Standard JBrowse2 adapter configuration
- `metadata.access_level`: Controls who can see this track (PUBLIC/COLLABORATOR/etc.)
- `metadata.google_sheets_metadata`: Custom metadata from Google Sheets workflow

**Supported Track Types:**
- `QuantitativeTrack` - BigWig files (coverage, signals)
- `FeatureTrack` - GFF3, BED, GTF (genes, features)
- `AlignmentsTrack` - BAM, CRAM (read alignments)
- `VariantTrack` - VCF (variants)
- `MultiQuantitativeTrack` - Multi-sample BigWig
- `SyntenyTrack` - MAF, PAF (genome alignments)

### Metadata Generation

Tracks are added via automated scripts that:
1. Read Google Sheets with track metadata
2. Validate file paths exist
3. Generate JBrowse2 adapter configs
4. Assign unique track IDs
5. Write JSON metadata files

**Script:** `tools/generate_tracks_from_sheet.php`

---

## Configuration API

### Endpoint: `api/jbrowse2/config.php`

**Purpose:** Dynamic configuration generator - returns different configs based on user authentication.

**Modes:**

#### Mode 1: Assembly List (No Parameters)

**Request:**
```http
GET /moop/api/jbrowse2/config.php
Cookie: PHPSESSID=abc123...
```

**Response:**
```json
{
    "assemblies": [
        {
            "name": "Nematostella_vectensis_GCA_033964005.1",
            "displayName": "Nematostella vectensis (GCA_033964005.1)",
            "aliases": ["GCA_033964005.1", "Nvec200"],
            "accessLevel": "PUBLIC",
            "sequence": { "type": "ReferenceSequenceTrack", "..." }
        }
    ],
    "plugins": [],
    "configuration": {},
    "connections": [],
    "defaultSession": {"name": "New Session"},
    "tracks": [],
    "userAccessLevel": "PUBLIC"
}
```

**Logic:**
```php
function generateAssemblyList($user_access_level) {
    $assembly_files = glob("$metadata_path/assemblies/*.json");
    $accessible_assemblies = [];
    
    foreach ($assembly_files as $file) {
        $assembly_def = json_decode(file_get_contents($file), true);
        $assembly_access_level = $assembly_def['defaultAccessLevel'] ?? 'PUBLIC';
        
        // Permission check
        if (canUserAccessAssembly($user_access_level, $assembly_access_level, 
                                  $assembly_def['organism'], $assembly_def['assemblyId'])) {
            $accessible_assemblies[] = $assembly_def;
        }
    }
    
    return [
        'assemblies' => $accessible_assemblies,
        'plugins' => getJBrowse2PluginConfiguration(),
        'tracks' => [],
        'userAccessLevel' => $user_access_level
    ];
}
```

#### Mode 2: Full Configuration (With Organism/Assembly)

**Request:**
```http
GET /moop/api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
Cookie: PHPSESSID=abc123...
```

**Response:**
```json
{
    "assemblies": [
        {
            "name": "Nematostella_vectensis_GCA_033964005.1",
            "displayName": "Nematostella vectensis (GCA_033964005.1)",
            "sequence": { "adapter": { "..." } }
        }
    ],
    "plugins": [],
    "configuration": {},
    "tracks": [
        {
            "trackId": "track_abc123",
            "name": "RNA-Seq Sample 1",
            "type": "QuantitativeTrack",
            "adapter": {
                "type": "BigWigAdapter",
                "bigWigLocation": {
                    "uri": "/moop/api/jbrowse2/tracks.php?file=Nematostella_vectensis/GCA_033964005.1/bigwig/sample1.bw&token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
                    "locationType": "UriLocation"
                }
            }
        }
    ],
    "defaultSession": {
        "name": "Nematostella vectensis",
        "view": {
            "type": "LinearGenomeView",
            "tracks": []
        }
    }
}
```

**Logic:**
```php
function generateAssemblyConfig($organism, $assembly, $user_access_level) {
    // 1. Verify user has access
    $accessible = getAccessibleAssemblies($organism, $assembly);
    if (empty($accessible)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Access denied']));
    }
    
    // 2. Load assembly definition
    $assembly_def = loadAssemblyDefinition($organism, $assembly);
    
    // 3. Build base config
    $config = [
        'assemblies' => [$assembly_def],
        'plugins' => getJBrowse2PluginConfiguration(),
        'tracks' => [],
        'defaultSession' => createDefaultSession($organism, $assembly)
    ];
    
    // 4. Load and filter tracks
    $config['tracks'] = loadFilteredTracks($organism, $assembly, $user_access_level);
    
    // 5. Return with gzip compression
    header('Content-Encoding: gzip');
    echo gzencode(json_encode($config));
}
```

---

## Permission Filtering

### Access Level Hierarchy

```
┌─────────────────────────────────────┐
│  ADMIN (value: 4)                   │
│  • Sees everything                  │
│  • No restrictions                  │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  IP_IN_RANGE (value: 3)             │
│  • Sees everything                  │
│  • No JWT tokens (whitelisted IP)   │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  COLLABORATOR (value: 2)            │
│  • Sees PUBLIC content              │
│  • Sees explicitly granted assemblies│
│  • Requires JWT tokens              │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│  PUBLIC (value: 1)                  │
│  • Sees only PUBLIC content         │
│  • Requires JWT tokens              │
└─────────────────────────────────────┘
```

### Assembly Filtering

**Function:** `canUserAccessAssembly()` in `config.php`

```php
function canUserAccessAssembly($user_level, $assembly_level, $organism, $assembly_id) {
    // Rule 1: Admin and IP_IN_RANGE see everything
    if ($user_level === 'ADMIN' || $user_level === 'IP_IN_RANGE') {
        return true;
    }
    
    // Rule 2: Public assemblies visible to all
    if ($assembly_level === 'PUBLIC') {
        return true;
    }
    
    // Rule 3: Collaborator needs specific permission
    if ($user_level === 'COLLABORATOR') {
        if ($organism && $assembly_id) {
            $user_access = $_SESSION['access'] ?? [];
            // Check if user has access to this organism/assembly
            return isset($user_access[$organism]) && 
                   in_array($assembly_id, (array)$user_access[$organism]);
        }
        return false;
    }
    
    // Rule 4: Public users can't see non-public assemblies
    return false;
}
```

**Example Session Access:**
```php
$_SESSION['access'] = [
    'Nematostella_vectensis' => ['GCA_033964005.1', 'GCA_000002.1'],
    'Organism_B' => ['GCA_000003.1']
];
```

This COLLABORATOR user can access:
- Nematostella_vectensis / GCA_033964005.1
- Nematostella_vectensis / GCA_000002.1
- Organism_B / GCA_000003.1
- Any PUBLIC assemblies

### Track Filtering

**Function:** `loadFilteredTracks()` in `config.php`

```php
function loadFilteredTracks($organism, $assembly, $user_access_level) {
    $tracks_dir = "/metadata/jbrowse2-configs/tracks";
    $track_files = glob("$tracks_dir/$organism/$assembly/*/*.json");
    
    $access_hierarchy = [
        'ADMIN' => 4,
        'IP_IN_RANGE' => 3,
        'COLLABORATOR' => 2,
        'PUBLIC' => 1
    ];
    
    $user_level_value = $access_hierarchy[$user_access_level] ?? 0;
    $filtered_tracks = [];
    
    foreach ($track_files as $track_file) {
        $track_def = json_decode(file_get_contents($track_file), true);
        $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
        $track_level_value = $access_hierarchy[$track_access_level] ?? 1;
        
        // Check 1: User meets minimum access level
        if ($user_level_value < $track_level_value) {
            continue; // Skip - insufficient access
        }
        
        // Check 2: COLLABORATOR users need explicit assembly access
        if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
            $user_access = $_SESSION['access'] ?? [];
            if (!isset($user_access[$organism]) || 
                !in_array($assembly, (array)$user_access[$organism])) {
                continue; // Skip - no explicit permission
            }
        }
        
        // Track passed checks - add JWT token and include
        $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly, 
                                              $user_access_level, isWhitelistedIP());
        $filtered_tracks[] = $track_with_tokens;
    }
    
    return $filtered_tracks;
}
```

**Security Properties:**
- ✅ Filtering happens server-side before config sent
- ✅ Client never sees unauthorized track definitions
- ✅ Cannot bypass by crafting custom requests
- ✅ COLLABORATOR permissions verified against session data

---

## JWT Token Integration

### Token Generation Per-Assembly

When generating config for a specific assembly, MOOP creates a JWT token scoped to that organism/assembly pair:

```php
$token = generateTrackToken($organism, $assembly, $user_access_level);
// Token claims: {organism: "X", assembly: "Y", user_id: "researcher", exp: now+3600}
```

**Key Points:**
- Token generated ONCE per config request
- Same token used for ALL tracks in that assembly
- Token cannot be used for different organism/assembly
- Expires in 1 hour

### Token Injection into Track URIs

**Function:** `addTokenToAdapterUrls()` in `config.php`

Recursively traverses track adapter configuration to find all URIs and inject tokens:

```php
function addTokenToAdapterUrls($adapter, $token) {
    foreach ($adapter as $key => &$value) {
        if (is_array($value)) {
            // Check if this is a URI location
            if (isset($value['uri']) && !empty($value['uri'])) {
                $uri = $value['uri'];
                
                // Route through tracks.php API
                if (preg_match('#^/moop/data/tracks/(.+)$#', $uri, $matches)) {
                    $file_path = $matches[1];
                    $value['uri'] = '/moop/api/jbrowse2/tracks.php?file=' . urlencode($file_path);
                    
                    // Add token (unless whitelisted IP)
                    if ($token) {
                        $value['uri'] .= '&token=' . urlencode($token);
                    }
                }
            } else {
                // Recurse into nested structures
                $value = addTokenToAdapterUrls($value, $token);
            }
        }
    }
    return $adapter;
}
```

**Before:**
```json
{
    "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
            "uri": "/moop/data/tracks/Organism/Assembly/bigwig/sample.bw"
        }
    }
}
```

**After (with token):**
```json
{
    "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
            "uri": "/moop/api/jbrowse2/tracks.php?file=Organism%2FAssembly%2Fbigwig%2Fsample.bw&token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoicmVzZWFyY2hlciIsIm9yZ2FuaXNtIjoiT3JnYW5pc20iLCJhc3NlbWJseSI6IkFzc2VtYmx5IiwiYWNjZXNzX2xldmVsIjoiQ09MTEFCT1JBVE9SIiwiaWF0IjoxNzA4MjgwMDAwLCJleHAiOjE3MDgyODM2MDB9..."
        }
    }
}
```

### IP Whitelisting (Relaxed Expiry)

**Updated 2026-02-18:** Internal network IPs now receive JWT tokens but with relaxed expiry checking.

```php
$is_whitelisted = isWhitelistedIP();  // Checks against 10.x, 192.168.x, 127.x ranges

// ALWAYS generate tokens (even for whitelisted IPs)
$token = generateTrackToken($organism, $assembly, $user_access_level);

// Get track access level for token injection strategy
$track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';

$track_with_tokens = addTokensToTrack($track_def, $organism, $assembly, 
                                      $user_access_level, $is_whitelisted);
```

**Benefits:**
- ✅ Defense-in-depth: All users need valid organism/assembly tokens
- ✅ Audit trail: All requests logged with user_id
- ✅ Prevents unauthorized access by path guessing
- ✅ Whitelisted IPs still get convenience: Can use expired tokens
- ✅ No 1-hour expiry limit for internal researchers

**On tracks server:**
- Whitelisted IPs: Token structure validated, expiry check skipped
- Non-whitelisted IPs: Full validation including expiry
- All IPs: Organism/assembly claims must match requested file

---

## External URL Handling

**Added 2026-02-18:** Support for public reference data from external servers.

### Token Strategy Based on access_level

MOOP uses the track's `access_level` metadata to determine whether to add JWT tokens to external URLs:

**Rule:** External URL + `access_level="PUBLIC"` → No token (prevents leakage)

```php
function addTokenToAdapterUrls($adapter, $token, $track_access_level) {
    // CASE 1: External URL + PUBLIC → Skip token
    if (preg_match('#^(https?|ftp)://#i', $uri) && $track_access_level === 'PUBLIC') {
        continue; // Leave unchanged - external public resource
    }
    
    // CASE 2: External URL + NOT PUBLIC → Add token
    elseif (preg_match('#^(https?|ftp)://#i', $uri)) {
        // Your remote tracks server with protected data
        $value['uri'] .= '?token=' . urlencode($token);
    }
    
    // CASE 3: MOOP tracks → Always add token
    if (preg_match('#^/moop/data/tracks/(.+)$#', $uri)) {
        $value['uri'] = '/moop/api/jbrowse2/tracks.php?file=' . urlencode($file_path);
        $value['uri'] .= '&token=' . urlencode($token);
    }
}
```

### Example Scenarios

#### Scenario 1: UCSC Public Conservation Track

**Track Metadata:**
```json
{
    "name": "UCSC Conservation",
    "metadata": {
        "access_level": "PUBLIC",
        "is_remote": true,
        "remote_url": "https://hgdownload.soe.ucsc.edu/"
    },
    "adapter": {
        "bigWigLocation": {
            "uri": "https://hgdownload.soe.ucsc.edu/goldenPath/hg38/phyloP100way.bw"
        }
    }
}
```

**After config generation:**
```json
{
    "adapter": {
        "bigWigLocation": {
            "uri": "https://hgdownload.soe.ucsc.edu/goldenPath/hg38/phyloP100way.bw"
        }
    }
}
```
✅ **Unchanged** - External + PUBLIC = No token

---

#### Scenario 2: Your Lab's Data (Local Server)

**Track Metadata:**
```json
{
    "name": "Lab RNA-Seq",
    "metadata": {
        "access_level": "PUBLIC"
    },
    "adapter": {
        "bigWigLocation": {
            "uri": "/moop/data/tracks/Organism/Assembly/bigwig/rnaseq.bw"
        }
    }
}
```

**After config generation:**
```json
{
    "adapter": {
        "bigWigLocation": {
            "uri": "/moop/api/jbrowse2/tracks.php?file=Organism%2FAssembly%2Fbigwig%2Frnaseq.bw&token=eyJhbGc..."
        }
    }
}
```
✅ **Token added** - Your data, even if PUBLIC (audit trail)

---

#### Scenario 3: Collaborator Data on Remote Tracks Server

**Track Metadata:**
```json
{
    "name": "Collaboration Project",
    "metadata": {
        "access_level": "COLLABORATOR"
    },
    "adapter": {
        "bigWigLocation": {
            "uri": "https://tracks.yourlab.edu/data/Organism/Assembly/restricted.bw"
        }
    }
}
```

**After config generation:**
```json
{
    "adapter": {
        "bigWigLocation": {
            "uri": "https://tracks.yourlab.edu/data/Organism/Assembly/restricted.bw?token=eyJhbGc..."
        }
    }
}
```
✅ **Token added** - Your remote tracks server with protected data

---

#### Scenario 4: Mixed Tracks (Local + External)

**Track Metadata:**
```json
{
    "tracks": [
        {
            "name": "Our Data",
            "metadata": {"access_level": "COLLABORATOR"},
            "adapter": {
                "uri": "/moop/data/tracks/Org/Asm/sample.bw"
            }
        },
        {
            "name": "UCSC Reference",
            "metadata": {"access_level": "PUBLIC"},
            "adapter": {
                "uri": "https://hgdownload.soe.ucsc.edu/data.bw"
            }
        }
    ]
}
```

**After config generation:**
```json
{
    "tracks": [
        {
            "name": "Our Data",
            "adapter": {
                "uri": "/moop/api/jbrowse2/tracks.php?file=Org%2FAsm%2Fsample.bw&token=JWT"
            }
        },
        {
            "name": "UCSC Reference",
            "adapter": {
                "uri": "https://hgdownload.soe.ucsc.edu/data.bw"
            }
        }
    ]
}
```
✅ **Local gets token, external unchanged**

### Security Benefits

**Prevents Token Leakage:**
- ❌ Old behavior: All URIs got tokens → JWT tokens in UCSC logs
- ✅ New behavior: External PUBLIC URIs unchanged → No leakage

**Secures Your Remote Tracks Servers:**
- ❌ Old behavior: All external URLs skipped tokens → Can't secure your remote server
- ✅ New behavior: External COLLABORATOR+ gets tokens → Your remote servers secured

**Consistent Audit Trail:**
- ✅ All YOUR data (local or remote) has tokens with user_id
- ✅ Can track who accessed what, when
- ✅ Even PUBLIC tracks on your server get tokens

---

## JBrowse2 Client Integration

### Entry Point: `jbrowse2.php`

Standard MOOP page with JBrowse2 React app embedded:

```php
<?php
include_once 'includes/access_control.php';
include_once 'includes/layout.php';

$user_info = [
    'logged_in' => is_logged_in(),
    'username' => get_username(),
    'access_level' => get_access_level(),
    'is_admin' => ($_SESSION['is_admin'] ?? false),
];

echo render_display_page(
    'tools/pages/jbrowse2.php',
    [
        'user_info' => json_encode($user_info),
        'page_script' => '/moop/js/jbrowse2-loader.js',
        'inline_scripts' => [
            "window.moopUserInfo = " . json_encode($user_info) . ";"
        ]
    ],
    'JBrowse2 - Genome Browser'
);
?>
```

### JavaScript Loader: `js/jbrowse2-loader.js`

Fetches assembly list and creates view:

```javascript
async function loadAssemblies() {
    const response = await fetch('/moop/api/jbrowse2/config.php');
    const config = await response.json();
    
    // Display assembly list filtered by user permissions
    displayAssemblies(config.assemblies);
}

async function openAssembly(assembly) {
    const organism = assembly.organism;  // From assembly name
    const assemblyId = assembly.assemblyId;
    
    // Fetch full config with tracks
    const response = await fetch(
        `/moop/api/jbrowse2/config.php?organism=${organism}&assembly=${assemblyId}`
    );
    const config = await response.json();
    
    // Initialize JBrowse2 with config (standard API)
    const {createViewState, JBrowseLinearGenomeView} = JBrowseReactLinearGenomeView;
    const state = createViewState({
        assembly: config.assemblies[0],
        tracks: config.tracks,
        configuration: config.configuration,
        plugins: config.plugins
    });
    
    ReactDOM.render(
        React.createElement(JBrowseLinearGenomeView, {viewState: state}),
        document.getElementById('jbrowse-container')
    );
}
```

**Key Points:**
- ✅ Uses standard `@jbrowse/react-linear-genome-view` package
- ✅ No custom patches or forks
- ✅ JWT tokens already embedded in config - JBrowse2 just uses the URIs
- ✅ Track requests automatically include tokens

---

## Performance Considerations

### Gzip Compression

Config API uses gzip compression for large configs:

```php
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

// Generate config...
echo json_encode($config);
```

**Performance Impact:**
- 500 tracks: ~2MB → 200KB (10x reduction)
- 1000 tracks: ~4MB → 400KB (10x reduction)

### Lazy Loading (Available)

For assemblies >1000 tracks, alternative endpoint with lazy loading:

**File:** `api/jbrowse2/config-optimized.php`

Returns track URIs instead of full definitions:
```json
{
    "tracks": [
        {
            "type": "track",
            "uri": "/moop/api/jbrowse2/track-config.php?id=track_123&token=JWT"
        }
    ]
}
```

Not currently used - standard endpoint handles current scale (<1000 tracks/assembly).

---

## Security Benefits

### Server-Side Filtering

Unlike client-side security (easily bypassed), MOOP filters at config generation:

```
❌ Client-side (insecure):
   - Send full config to client
   - JavaScript hides unauthorized tracks
   - User can inspect network, modify JavaScript, see all tracks

✅ Server-side (secure):
   - Filter tracks before sending config
   - Client only receives authorized track definitions
   - No way to access hidden tracks (not in config)
```

### Stateless Tracks Server

Tracks server needs NO database or sessions:

```php
// tracks.php validates purely from JWT
$token_data = verifyTrackToken($token);  // Cryptographic verification
// Token contains all needed info: organism, assembly, access_level, expiry
```

**Benefits:**
- ✅ Horizontal scaling (add more tracks servers trivially)
- ✅ No single point of failure (no shared database)
- ✅ Fast (no database queries)
- ✅ Secure (compromised tracks server can't forge tokens)

### Defense in Depth

Even if attacker bypasses one layer, others protect:

1. **Config filtered** - Unauthorized tracks not in config
2. **JWT required** - Can't access tracks without valid token
3. **Claims validated** - Token must match organism/assembly
4. **Expiry enforced** - Token invalid after 1 hour

---

## FAQ

**Q: Why not use static config.json files?**  
A: Static configs can't filter by user permissions. Would need separate configs per access level, causing maintenance nightmare and security risks.

**Q: Does this work with standard JBrowse2?**  
A: Yes! We use unmodified `@jbrowse/react-linear-genome-view`. The config format is standard JBrowse2 JSON.

**Q: Can users edit configs in browser console?**  
A: They can try, but track requests still require valid JWT tokens. Modifying client-side config doesn't bypass server validation.

**Q: What if user's session expires during viewing?**  
A: JBrowse2 continues working. External users: tokens expire in 1 hour. Whitelisted users: expired tokens still work. After expiry, external users must refresh page.

**Q: How do you handle multiple assemblies for one user?**  
A: Each assembly gets its own JWT token scoped to that organism/assembly pair. Tokens cannot be reused across assemblies.

**Q: Can I cache configs?**  
A: Not recommended - configs are permission-dependent and user-specific. Caching could leak unauthorized tracks.

**Q: How do you add new tracks?**  
A: Add JSON metadata file to `metadata/jbrowse2-configs/tracks/Organism/Assembly/type/`. Next config request automatically includes it (if user has access).

**Q: Can I use external URLs for tracks (UCSC, Ensembl)?**  
A: Yes! Mark them `access_level: "PUBLIC"` and use `https://` URLs. No JWT tokens added - works perfectly for public reference data.

**Q: What about my own remote tracks server with protected data?**  
A: Mark tracks `access_level: "COLLABORATOR"` or higher. Tokens will be added automatically. Your remote tracks server validates tokens before serving files.

**Q: Do whitelisted IPs still need JWT tokens?**  
A: Yes (as of 2026-02-18). All users get tokens with organism/assembly claims. Whitelisted IPs benefit: can use expired tokens (no 1-hour limit).

**Q: What happens if I mark a UCSC track as COLLABORATOR by mistake?**  
A: Token gets added to UCSC URL. UCSC ignores it and track still loads. Minor information leakage to UCSC logs. Fix: Mark external public tracks as PUBLIC.

---

**Document Version:** 3.0  
**Last Updated:** February 18, 2026  
**JBrowse2 Version:** 2.x (React Linear Genome View)  
**MOOP Integration:** Production Ready

The system uses PHP to dynamically generate JBrowse2 configurations based on user authentication state, rather than serving static JSON files.

### Session-Based Authentication

When you access `/moop/jbrowse2.php`, the server checks your session (`$_SESSION`) to determine:
- `logged_in`: Boolean indicating authentication status
- `username`: Your user identifier
- `access_level`: Your permission tier (Public/Collaborator/ALL)
- `is_admin`: Boolean for administrative privileges

### Modular Metadata System

Assembly and track definitions are stored as JSON files in `/metadata/jbrowse2-configs/`:
- `assemblies/*.json`: Reference genome definitions with access level requirements
- `tracks/*.json`: Track metadata (BAM, BigWig files) with access permissions

### Access Filtering Pipeline

The `/api/jbrowse2/config.php` endpoint implements the following pipeline:

1. **Permission Validation**: Calls `getAccessibleAssemblies()` to verify you can view the requested organism/assembly

2. **Assembly Loading**: Reads the assembly definition from metadata files

3. **Track Discovery**: Scans all available track definitions

4. **Access Control Logic**: Filters tracks based on hierarchical permissions:
   - `ADMIN`: Sees everything
   - `IP_IN_RANGE`: Sees everything (whitelisted IPs)
   - `COLLABORATOR`: Sees PUBLIC + specific assemblies they're granted access to
   - `PUBLIC`: Sees only public tracks

5. **Token Generation**: Generates JWT tokens for non-whitelisted IPs

6. **Config Assembly**: Constructs complete JBrowse2 JSON config with only authorized content

---

## 2. JWT-Based Track Request Security

### JWT (JSON Web Token)

A cryptographically signed token containing user claims (identity, permissions, expiration). It's stateless—the tracks server doesn't need to query a database.

### Asymmetric Cryptography (RS256) ✅

**Current Implementation:** Uses RSA public/private key pair (2048-bit)

- **Private Key** (`/certs/jwt_private_key.pem`): Kept secret on the MOOP server, used to *sign* tokens
- **Public Key** (`/certs/jwt_public_key.pem`): Can be shared with the tracks server, used to *verify* tokens

**Security Benefits:**
- ✅ Tracks server cannot forge tokens (needs private key to sign)
- ✅ Safe to deploy public key to multiple tracks servers
- ✅ Compromised tracks server cannot create valid tokens
- ✅ Private key never leaves MOOP server

### Token Generation (Current Implementation)

The `generateTrackToken()` function in `/lib/jbrowse/track_token.php` creates tokens:

```php
$token_data = [
    'user_id' => $_SESSION['username'],
    'organism' => $organism,
    'assembly' => $assembly,
    'access_level' => $access_level,
    'iat' => time(),                    // Issued At timestamp
    'exp' => time() + 3600              // Expires in 1 hour
];
$jwt = JWT::encode($token_data, $private_key, 'RS256');
```

**Token Claims Explained:**
- `user_id`: Username for audit logging
- `organism`/`assembly`: Restricts token to specific genome
- `access_level`: Permission tier for authorization
- `iat`: Issued at timestamp (for debugging/logging)
- `exp`: Expiration timestamp (security: limits token lifetime)

### Track URL Construction

When building track URLs in `config.php`, tokens are appended:

```
http://127.0.0.1:8888/tracks/bigwig/organism_assembly_track.bw?token={JWT}
```

### Token Verification (Current Implementation)

The `/api/jbrowse2/tracks.php` endpoint performs:

1. **Extract Token**: Reads `?token=` query parameter from track request

2. **Cryptographic Validation**: Uses public key (RS256) to verify the signature—only tokens signed by the matching private key are valid

3. **Expiration Check**: Ensures current time < `exp` timestamp (prevents replay attacks with old tokens)

4. **Claim Validation**: Verifies the token's `organism`/`assembly` matches the requested file path (prevents token reuse for unauthorized data)

5. **Range Request Support**: Serves file with HTTP range headers for efficient seeking in large genomic files

**Path Validation:**
```php
// File path format: organism/assembly/type/filename (or any structure after assembly)
$file_parts = explode('/', $file);

if (count($file_parts) < 2) {
    http_response_code(400);
    exit;
}

$file_organism = $file_parts[0];
$file_assembly = $file_parts[1];

// Verify token organism/assembly matches file path
if ($token_data->organism !== $file_organism || 
    $token_data->assembly !== $file_assembly) {
    http_response_code(403);
    exit;
}
```

---

## 3. Security Properties

### 🔐 Authentication ✅
JWTs prove the user was authenticated at token generation time—forged tokens fail RS256 signature verification.

### 🕐 Time-Limited Access ✅
1-hour expiration (`exp` claim) limits exposure if a token is compromised. Users must re-authenticate to get new tokens.

### 🎯 Scope Restriction ✅
Tokens are bound to specific `organism`/`assembly` pairs and validated against file paths—can't use a token for one genome to access another.

### 🔒 Cryptographic Integrity ✅
RS256 asymmetric signature ensures tokens can't be tampered with or forged. Changing any claim (like upgrading `access_level`) invalidates the signature. Only the private key holder (MOOP server) can create valid tokens.

### 🌐 Distributed Architecture ✅
The tracks server doesn't need direct database access or shared sessions—it only needs the public key to verify tokens independently.

### 🛡️ Path Traversal Protection ✅
The tracks server validates file paths to prevent `../` directory traversal attacks and ensures paths start with valid organism/assembly.

### 📍 IP Whitelisting ✅
Internal IPs (10.x.x.x, 192.168.x.x, 127.x.x.x) bypass token requirements—useful for trusted networks while enforcing security for external collaborators.

### 🚫 Stateless Verification ✅
The tracks server doesn't maintain sessions or connection to the auth database—it verifies requests purely from the cryptographic token, making it horizontally scalable.

---

## 4. Request Flow Diagram

```
User Browser                 MOOP Server              Tracks Server
     |                           |                          |
     |---(1) GET /jbrowse2.php-->|                          |
     |                           |                          |
     |<--(2) HTML + JS ----------|                          |
     |                           |                          |
     |---(3) GET /api/jbrowse2/config.php?organism=X&assembly=Y
     |                           |                          |
     |                      [Check session]                 |
     |                      [Load metadata]                 |
     |                      [Filter tracks]                 |
     |                      [Generate JWT]                  |
     |                           |                          |
     |<--(4) JSON config with----|                          |
     |       track URLs + tokens |                          |
     |                           |                          |
     |---(5) GET tracks.php?file=track.bw&token=JWT---->    |
     |                           |                          |
     |                           |                   [Verify JWT]
     |                           |                   [Validate claims]
     |                           |                   [Check expiration]
     |                           |                          |
     |<--(6) Binary track data--------------------------|   |
     |                           |                          |
```

---

## 5. Key Files

### Configuration Generation
- `/jbrowse2.php` - Main entry point with MOOP layout
- `/api/jbrowse2/config.php` - Primary endpoint: Returns filtered assembly list OR complete JBrowse2 config with tokens
- `/api/jbrowse2/config-optimized.php` - Optimized endpoint for >1000 tracks (available but not currently used)
- `/js/jbrowse2-loader.js` - Client-side assembly list and loader

### JWT System
- `/lib/jbrowse/track_token.php` - Token generation and verification functions
- `/certs/jwt_private_key.pem` - RSA private key (secret, for signing)
- `/certs/jwt_public_key.pem` - RSA public key (for verification)

### Track Server (Current Implementation)
- `/api/jbrowse2/tracks.php` - Track file server with RS256 JWT verification and claim validation (critical infrastructure)

### Metadata
- `/metadata/jbrowse2-configs/assemblies/*.json` - Assembly definitions
- `/metadata/jbrowse2-configs/tracks/*.json` - Track definitions

---

## 6. Production Deployment Considerations

### Separate Tracks Server

In production, the tracks server should run on separate infrastructure:

1. **Copy public key** to tracks server: `/path/to/jwt_public_key.pem`
2. **Update track URLs** in config.php to point to external server
3. **Configure CORS** headers on tracks server for JBrowse2 access
4. **Enable HTTPS** for secure token transmission
5. **Monitor token verification logs** for security auditing

### Token Management

- **Rotation**: Regenerate key pairs periodically (requires coordinated deployment)
- **Expiration**: Adjust token lifetime based on security requirements
- **Refresh**: Implement token refresh endpoint for long sessions
- **Revocation**: For compromised tokens, key rotation is the primary mitigation

### Performance Optimization

- **Caching**: Enable HTTP caching headers for track data (immutable genomic data)
- **CDN**: Use CDN for tracks server if serving external collaborators globally
- **Connection Pooling**: Tracks server should reuse database connections
- **Range Request Optimization**: Ensure efficient byte-range serving for large files

---

## 7. Security Best Practices

### Key Management
- Store private keys with restricted file permissions (600)
- Never commit keys to version control
- Use different keys for dev/staging/production
- Store keys outside web root directory

### Token Security
- Always use HTTPS in production (prevents token interception)
- Log token verification failures for security monitoring
- Implement rate limiting on token endpoints
- Consider shorter expiration times for sensitive data

### Access Control
- Regularly audit user access levels
- Implement "least privilege" principle
- Monitor for unusual access patterns
- Maintain audit logs of data access

---

## 8. Troubleshooting

### Token Verification Fails
- Check server time synchronization (JWT exp claim is time-based)
- Verify public/private key pair matches
- Check file permissions on key files
- Review error logs for specific JWT library errors

### Tracks Not Loading
- Inspect browser console for CORS errors
- Verify token is present in track URLs
- Check tracks server logs for verification errors
- Ensure file paths are correct and accessible

### Access Denied
- Verify user session is active
- Check access_level in session matches assembly requirements
- Review getAccessibleAssemblies() logic
- Confirm metadata files have correct access_levels

---

## 9. System Architecture Notes

### Current Implementation (as of 2026-02-17)

The system uses **`config.php`** as the primary configuration endpoint (consolidated on 2026-02-14):

**Single Endpoint Pattern:**
- `GET /api/jbrowse2/config.php` - Returns filtered assembly list
- `GET /api/jbrowse2/config.php?organism=X&assembly=Y` - Returns complete JBrowse2 config with tracks

**Benefits:**
- Single security implementation point
- Consistent permission filtering
- Simplified maintenance
- Clear API contract

**Available Alternative:**
- `config-optimized.php` - Ready for assemblies with >1000 tracks using lazy-loading pattern

**Historical Note:**
The original `assembly.php` endpoint was consolidated into `config.php` on 2026-02-14 to streamline the API architecture. All functionality remains the same - only the endpoint name changed.

---

**Last Updated**: 2026-02-17

**Current Status:**
- ✅ RS256 asymmetric JWT implementation
- ✅ Token claims validation (organism/assembly matching)
- ✅ IP whitelisting for internal networks
- ✅ HTTP range request support
- ✅ Directory traversal protection
- ✅ 1-hour token expiry
