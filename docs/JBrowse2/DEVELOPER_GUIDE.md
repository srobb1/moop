# JBrowse2 Developer Guide

**Audience:** Developers, System Integrators  
**Purpose:** Architecture, customization, and extension

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Dynamic Configuration System](#dynamic-configuration-system)
3. [Authentication Flow](#authentication-flow)
4. [API Integration](#api-integration)
5. [Customization](#customization)
6. [Performance Optimization](#performance-optimization)
7. [Testing](#testing)

---

## Architecture Overview

### High-Level Design

```
┌──────────────────────────────────────────────────────────────┐
│                         Browser                               │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐ │
│  │  jbrowse2.php  │  │ jbrowse2-      │  │   JBrowse2     │ │
│  │  (MOOP Layout) │→ │ loader.js      │→ │   React App    │ │
│  └────────────────┘  └────────────────┘  └────────────────┘ │
└────────────┬──────────────────────┬──────────────┬───────────┘
             │                      │              │
             ↓                      ↓              ↓
┌────────────────────────┐ ┌──────────────┐ ┌─────────────────┐
│  Session Management    │ │  Config API  │ │  Tracks Server  │
│  (access_control.php)  │ │ (get-config  │ │  (JWT validated)│
│                        │ │  .php)       │ │                 │
│  - User auth           │ │              │ │  - BigWig files │
│  - Access levels       │ │ - Reads meta │ │  - BAM files    │
│  - Permissions         │ │ - Filters by │ │  - VCF files    │
│                        │ │   user level │ │                 │
└────────────────────────┘ └──────┬───────┘ └─────────────────┘
                                  │
                                  ↓
                         ┌──────────────────┐
                         │ Metadata Storage │
                         │                  │
                         │ assemblies/*.json│
                         │ tracks/*.json    │
                         └──────────────────┘
```

### Key Components

| Component | Purpose | Location |
|-----------|---------|----------|
| **jbrowse2.php** | Main entry point with MOOP layout | `/jbrowse2.php` |
| **get-config.php** | Dynamic config API | `/api/jbrowse2/get-config.php` |
| **jbrowse2-loader.js** | Frontend loader | `/js/jbrowse2-loader.js` |
| **track_token.php** | JWT token library | `/lib/jbrowse/track_token.php` |
| **Assembly metadata** | Assembly definitions | `/metadata/jbrowse2-configs/assemblies/` |
| **Track metadata** | Track definitions | `/metadata/jbrowse2-configs/tracks/` |

---

## Dynamic Configuration System

### Design Principles

1. **No Static Config** - No hardcoded assemblies in config.json
2. **User-Specific** - Each user gets personalized config
3. **Modular Metadata** - One JSON file per assembly/track
4. **Auto-Discovery** - Scans metadata directory dynamically
5. **Access-Filtered** - Respects user permissions

### Configuration Flow

```javascript
// 1. User visits jbrowse2.php
// 2. Page loads jbrowse2-loader.js
// 3. Loader calls API

fetch('/moop/api/jbrowse2/get-config.php')
  .then(r => r.json())
  .then(config => {
    // config.assemblies = filtered by user access
    // config.userAccessLevel = 'Public', 'Collaborator', or 'ALL'
    displayAssemblies(config.assemblies);
  });
```

### Assembly Metadata Schema

**File:** `/metadata/jbrowse2-configs/assemblies/{organism}_{assembly}.json`

```json
{
  "name": "Anoura_caudifer_GCA_004027475.1",
  "displayName": "Anoura caudifer (GCA_004027475.1)",
  "organism": "Anoura_caudifer",
  "assemblyId": "GCA_004027475.1",
  "aliases": ["ACA1", "GCA_004027475.1"],
  "defaultAccessLevel": "Public",
  "sequence": {
    "type": "ReferenceSequenceTrack",
    "trackId": "Anoura_caudifer_GCA_004027475.1-ReferenceSequenceTrack",
    "adapter": {
      "type": "IndexedFastaAdapter",
      "fastaLocation": {
        "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta",
        "locationType": "UriLocation"
      },
      "faiLocation": {
        "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai",
        "locationType": "UriLocation"
      }
    }
  },
  "metadata": {
    "createdAt": "2026-02-05T20:42:35Z",
    "source": "setup script",
    "description": "Automatically generated assembly definition"
  }
}
```

**Required Fields:**
- `name` - Unique identifier
- `displayName` - Human-readable name
- `defaultAccessLevel` - "Public", "Collaborator", or "ALL"
- `sequence` - JBrowse2 sequence adapter configuration

### Track Metadata Schema

**File:** `/metadata/jbrowse2-configs/tracks/{track_name}.json`

```json
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
    "type": "LinearWiggleDisplay",
    "displayCrossHatches": true
  }
}
```

**Required Fields:**
- `name` - Display name
- `track_id` - Unique identifier
- `access_levels` - Array of allowed access levels
- `file_template` - Filename pattern with placeholders
- `format` - "bigwig", "bam", "vcf", etc.

### Access Control Logic

**File:** `api/jbrowse2/get-config.php`

```php
// Determine user access level
$user_access_level = 'Public'; // Default for anonymous

if (isset($_SESSION['user_id'])) {
    $user_access_level = $_SESSION['access_level'] ?? 'Collaborator';
    
    // Admin override
    if ($_SESSION['is_admin'] ?? false) {
        $user_access_level = 'ALL';
    }
}

// Filter assemblies
foreach ($assembly_files as $file) {
    $assembly_def = json_decode(file_get_contents($file), true);
    $assembly_access_level = $assembly_def['defaultAccessLevel'] ?? 'Public';
    
    // Check access
    $user_can_access = false;
    
    if ($user_access_level === 'ALL') {
        $user_can_access = true;
    } elseif ($assembly_access_level === 'Public') {
        $user_can_access = true;
    } elseif ($user_access_level === 'Collaborator' && 
              $assembly_access_level === 'Collaborator') {
        $user_can_access = true;
    }
    
    if ($user_can_access) {
        $config['assemblies'][] = $assembly_def;
    }
}
```

---

## Authentication Flow

### Session-Based Authentication

```
┌─────────────────┐
│  User logs in   │
│  (login.php)    │
└────────┬────────┘
         │
         ↓
┌─────────────────────────┐
│ Session created         │
│ $_SESSION['user_id']    │
│ $_SESSION['access_level']│
│ $_SESSION['is_admin']   │
└────────┬────────────────┘
         │
         ↓
┌─────────────────────────┐
│ User visits jbrowse2.php│
└────────┬────────────────┘
         │
         ↓
┌─────────────────────────┐
│ API checks session      │
│ Filters assemblies      │
└────────┬────────────────┘
         │
         ↓
┌─────────────────────────┐
│ Returns user-specific   │
│ config with assemblies  │
└─────────────────────────┘
```

### JWT Token Generation (for Tracks)

```php
// File: lib/jbrowse/track_token.php

function generateTrackToken($organism, $assembly, $access_level) {
    $private_key = file_get_contents('/data/moop/certs/jwt_private_key.pem');
    
    $token_data = [
        'user_id' => $_SESSION['username'] ?? 'anonymous',
        'organism' => $organism,
        'assembly' => $assembly,
        'access_level' => $access_level,
        'iat' => time(),
        'exp' => time() + 3600  // 1 hour expiry
    ];
    
    return JWT::encode($token_data, $private_key, 'HS256');
}
```

### JWT Token Validation (on Tracks Server)

```php
// File: api/jbrowse2/fake-tracks-server.php (or remote server)

function verifyTrackToken($token) {
    $public_key = file_get_contents('/data/moop/certs/jwt_public_key.pem');
    
    try {
        $decoded = JWT::decode($token, new Key($public_key, 'HS256'));
        
        // Check expiry
        if ($decoded->exp < time()) {
            return false;
        }
        
        return $decoded;
    } catch (Exception $e) {
        error_log("JWT verification failed: " . $e->getMessage());
        return false;
    }
}
```

---

## API Integration

See [API_REFERENCE.md](API_REFERENCE.md) for complete endpoint documentation.

### Key Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/jbrowse2/get-config.php` | GET | Get user's accessible assemblies |
| `/api/jbrowse2/assembly.php` | GET | Get specific assembly with tracks |
| `/api/jbrowse2/fake-tracks-server.php` | GET | Serve track files (test server) |

### Adding Custom Endpoints

**Example: Assembly count endpoint**

```php
<?php
// File: api/jbrowse2/assembly-count.php

header('Content-Type: application/json');
session_start();

$metadata_path = __DIR__ . '/../../metadata/jbrowse2-configs/assemblies';
$files = glob("$metadata_path/*.json");

$user_access_level = 'Public';
if (isset($_SESSION['user_id'])) {
    $user_access_level = $_SESSION['access_level'] ?? 'Collaborator';
    if ($_SESSION['is_admin'] ?? false) {
        $user_access_level = 'ALL';
    }
}

$count = 0;
foreach ($files as $file) {
    $assembly = json_decode(file_get_contents($file), true);
    $access_level = $assembly['defaultAccessLevel'] ?? 'Public';
    
    if ($user_access_level === 'ALL' || 
        $access_level === 'Public' ||
        ($user_access_level === 'Collaborator' && $access_level === 'Collaborator')) {
        $count++;
    }
}

echo json_encode(['count' => $count]);
?>
```

---

## Customization

### Adding New Access Levels

**1. Update access level hierarchy**

```php
// In get-config.php
$access_hierarchy = [
    'Public' => 0,
    'Student' => 1,      // NEW
    'Collaborator' => 2,
    'PI' => 3,           // NEW
    'ALL' => 99
];

function hasAccessTo($user_level, $required_level, $hierarchy) {
    return $hierarchy[$user_level] >= $hierarchy[$required_level];
}
```

**2. Update assembly metadata schema**

```json
{
  "defaultAccessLevel": "Student"  // NEW option
}
```

**3. Update UI badges**

```javascript
// In jbrowse2-loader.js
const accessLevelClass = {
    'Public': 'badge-light border-success',
    'Student': 'badge-info',              // NEW
    'Collaborator': 'badge-warning',
    'PI': 'badge-primary',                // NEW
    'ALL': 'badge-danger'
};
```

### Custom Track Types

**Example: Adding VCF track support**

**1. Create track metadata template:**

```json
{
  "name": "SNPs and Variants",
  "track_id": "variants",
  "access_levels": ["Collaborator", "ALL"],
  "file_template": "{organism}_{assembly}_variants.vcf.gz",
  "format": "vcf",
  "display": {
    "type": "LinearVariantDisplay",
    "renderer": {
      "type": "SvgFeatureRenderer"
    }
  }
}
```

**2. Add VCF adapter in assembly.php:**

```php
if ($track['format'] === 'vcf') {
    $track_config['adapter'] = [
        'type' => 'VcfTabixAdapter',
        'vcfGzLocation' => ['uri' => $track_url],
        'index' => [
            'location' => ['uri' => $track_url . '.tbi']
        ]
    ];
}
```

### Custom UI Themes

**Add theme to jbrowse2.php:**

```php
<?php
$inline_scripts[] = "
    // Custom JBrowse2 theme
    window.JBrowseConfig = {
        theme: {
            palette: {
                primary: { main: '#1976d2' },
                secondary: { main: '#dc004e' }
            }
        }
    };
";
?>
```

---

## Performance Optimization

### 1. Response Caching

**Add APCu caching to get-config.php:**

```php
// After line 18 in get-config.php
$cache_key = "jbrowse_config_{$user_access_level}_" . md5(serialize($_SESSION));
$cache_ttl = 300; // 5 minutes

$cached = apcu_fetch($cache_key);
if ($cached !== false) {
    echo $cached;
    exit;
}

// ... existing code ...

// Before final echo
$output = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
apcu_store($cache_key, $output, $cache_ttl);
echo $output;
```

**Benefits:**
- 90%+ reduction in file I/O
- Response time: ~50ms → ~5ms
- Automatic cache invalidation via TTL

### 2. Lazy Loading

**Load assemblies on demand:**

```javascript
// In jbrowse2-loader.js
async function loadAssemblies(page = 1, perPage = 10) {
    const response = await fetch(`/moop/api/jbrowse2/get-config.php?page=${page}&per_page=${perPage}`);
    const config = await response.json();
    
    displayAssemblies(config.assemblies);
    
    // Load more on scroll
    if (config.hasMore) {
        window.addEventListener('scroll', () => {
            if (atBottomOfPage()) {
                loadAssemblies(page + 1, perPage);
            }
        });
    }
}
```

### 3. Metadata Indexing

**Create index file for fast lookups:**

```php
<?php
// tools/jbrowse/build-metadata-index.php

$assemblies = glob('/data/moop/metadata/jbrowse2-configs/assemblies/*.json');
$index = [];

foreach ($assemblies as $file) {
    $data = json_decode(file_get_contents($file), true);
    $index[$data['name']] = [
        'file' => basename($file),
        'accessLevel' => $data['defaultAccessLevel'],
        'organism' => $data['organism'],
        'displayName' => $data['displayName']
    ];
}

file_put_contents(
    '/data/moop/metadata/jbrowse2-configs/assemblies/index.json',
    json_encode($index, JSON_PRETTY_PRINT)
);
?>
```

**Use in get-config.php:**

```php
// Load index instead of scanning directory
$index = json_decode(file_get_contents("$metadata_path/index.json"), true);

foreach ($index as $name => $info) {
    if (userCanAccess($user_access_level, $info['accessLevel'])) {
        // Load full assembly only if needed
        $assembly = json_decode(file_get_contents("$metadata_path/{$info['file']}"), true);
        $config['assemblies'][] = $assembly;
    }
}
```

---

## Testing

### Unit Tests (PHPUnit)

```php
<?php
// tests/JBrowse2/ConfigApiTest.php

class ConfigApiTest extends PHPUnit\Framework\TestCase {
    
    public function testAnonymousUserSeesOnlyPublicAssemblies() {
        $_SESSION = [];
        
        $response = $this->apiRequest('/api/jbrowse2/get-config.php');
        $config = json_decode($response, true);
        
        $this->assertEquals('Public', $config['userAccessLevel']);
        
        foreach ($config['assemblies'] as $assembly) {
            $this->assertEquals('Public', $assembly['accessLevel']);
        }
    }
    
    public function testAdminSeesAllAssemblies() {
        $_SESSION = [
            'user_id' => 1,
            'is_admin' => true
        ];
        
        $response = $this->apiRequest('/api/jbrowse2/get-config.php');
        $config = json_decode($response, true);
        
        $this->assertEquals('ALL', $config['userAccessLevel']);
        $this->assertGreaterThan(0, count($config['assemblies']));
    }
}
?>
```

### Integration Tests

```bash
#!/bin/bash
# tests/jbrowse2_integration_test.sh

# Test assembly setup
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Test/TestAssembly
./tools/jbrowse/add_assembly_to_jbrowse.sh Test TestAssembly

# Test API
curl -sf "http://localhost:8888/api/jbrowse2/get-config.php" | jq '.assemblies[] | select(.name=="Test_TestAssembly")' || exit 1

# Cleanup
rm -rf /data/moop/data/genomes/Test
rm /data/moop/metadata/jbrowse2-configs/assemblies/Test_TestAssembly.json

echo "✓ Integration test passed"
```

### Performance Tests

```bash
#!/bin/bash
# tests/jbrowse2_performance_test.sh

echo "Testing API response times..."

# Without cache
echo -n "First request (cold): "
time curl -s "http://localhost:8888/api/jbrowse2/get-config.php" > /dev/null

# With cache
echo -n "Second request (cached): "
time curl -s "http://localhost:8888/api/jbrowse2/get-config.php" > /dev/null

# Load test
echo "Load test (100 requests):"
ab -n 100 -c 10 "http://localhost:8888/api/jbrowse2/get-config.php"
```

---

## Development Workflow

### Local Development

```bash
# 1. Start PHP dev server
cd /data/moop
php -S 127.0.0.1:8888 &

# 2. Watch logs
tail -f logs/jbrowse2.log

# 3. Test changes
curl -s "http://127.0.0.1:8888/api/jbrowse2/get-config.php" | jq .

# 4. Stop server
pkill -f "php -S 127.0.0.1:8888"
```

### Debugging

**Enable debug mode:**

```php
// Add to get-config.php
$debug = isset($_GET['debug']) && $_GET['debug'] === 'true';

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Add debug info to response
    $config['debug'] = [
        'user_access_level' => $user_access_level,
        'session' => $_SESSION,
        'assembly_count' => count($assembly_files),
        'filtered_count' => count($config['assemblies'])
    ];
}
```

**Use:**
```bash
curl -s "http://localhost:8888/api/jbrowse2/get-config.php?debug=true" | jq .debug
```

---

## Best Practices

### Code Organization

- ✅ Keep assembly logic in dedicated directory (`/lib/jbrowse/`)
- ✅ Use consistent naming conventions
- ✅ Document API endpoints
- ✅ Validate all inputs
- ✅ Handle errors gracefully

### Security

- ✅ Always validate user sessions
- ✅ Never trust client input
- ✅ Use parameterized queries (if using database)
- ✅ Keep JWT private keys secure (chmod 600)
- ✅ Rotate JWT keys annually

### Performance

- ✅ Cache API responses
- ✅ Use indexes for large datasets
- ✅ Minimize JSON file reads
- ✅ Lazy load when possible
- ✅ Monitor response times

---

## Resources

- [API Reference](API_REFERENCE.md)
- [Security Guide](SECURITY.md)
- [JBrowse2 Official Docs](https://jbrowse.org/jb2/docs/)
- [Firebase JWT PHP](https://github.com/firebase/php-jwt)

---

**Questions?** Check the API reference or contact the development team.
