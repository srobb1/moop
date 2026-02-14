# Dynamic Dual-Assembly Config Design

**Date:** 2026-02-14  
**Approach:** Extend existing dynamic config system to support dual-assembly views

---

## Current Dynamic Config System

**Single Assembly:**
```
GET /moop/api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
```

Returns:
- 1 assembly definition
- All tracks for that assembly (filtered by user access level)
- Tracks include: BAM, BigWig, VCF, GFF, MAF, etc.

---

## Proposed Dual-Assembly Extension

**Dual Assembly:**
```
GET /moop/api/jbrowse2/config.php?assembly1=Nematostella_vectensis_GCA_033964005.1&assembly2=Anoura_caudifer_GCA_004027475.1
```

Returns:
- 2 assembly definitions
- All tracks for assembly1 (filtered by user access)
- All tracks for assembly2 (filtered by user access)
- Synteny tracks connecting assembly1 and assembly2 (filtered by user access)

---

## Implementation Plan

### Step 1: Detect Dual-Assembly Mode in config.php

```php
// In config.php
$assembly1 = $_GET['assembly1'] ?? null;
$assembly2 = $_GET['assembly2'] ?? null;

if ($assembly1 && $assembly2) {
    // Dual-assembly mode
    generateDualAssemblyConfig($assembly1, $assembly2, $user_access_level);
} elseif ($organism && $assembly) {
    // Single-assembly mode (existing)
    generateAssemblyConfig($organism, $assembly, $user_access_level);
} else {
    // Assembly list mode (existing)
    generateAssemblyList($user_access_level);
}
```

### Step 2: Create generateDualAssemblyConfig()

```php
function generateDualAssemblyConfig($assembly1, $assembly2, $user_access_level) {
    // Parse assembly names to get organism/assembly pairs
    // Format: Organism_Assembly
    list($organism1, $asm1) = parseAssemblyName($assembly1);
    list($organism2, $asm2) = parseAssemblyName($assembly2);
    
    // Load assembly definitions
    $assembly1_def = loadAssemblyDefinition($organism1, $asm1);
    $assembly2_def = loadAssemblyDefinition($organism2, $asm2);
    
    if (!$assembly1_def || !$assembly2_def) {
        http_response_code(404);
        echo json_encode(['error' => 'One or both assemblies not found']);
        exit;
    }
    
    // Check user has access to both assemblies
    if (!canUserAccessAssembly($user_access_level, $assembly1_def['defaultAccessLevel'], $organism1, $asm1) ||
        !canUserAccessAssembly($user_access_level, $assembly2_def['defaultAccessLevel'], $organism2, $asm2)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to one or both assemblies']);
        exit;
    }
    
    // Build config with both assemblies
    $config = [
        'assemblies' => [
            $assembly1_def,
            $assembly2_def
        ],
        'plugins' => getJBrowse2PluginConfiguration(),
        'tracks' => [],
        'defaultSession' => [
            'name' => "$assembly1 vs $assembly2 Comparison",
            'views' => [
                [
                    'type' => 'LinearSyntenyView',
                    'views' => [
                        [
                            'type' => 'LinearGenomeView',
                            'assemblyNames' => [$assembly1_def['name']],
                            'tracks' => []
                        ],
                        [
                            'type' => 'LinearGenomeView',
                            'assemblyNames' => [$assembly2_def['name']],
                            'tracks' => []
                        ]
                    ],
                    'tracks' => []  // Synteny tracks go here
                ]
            ]
        ]
    ];
    
    // Load tracks for assembly1
    $tracks1 = loadFilteredTracks($organism1, $asm1, $user_access_level);
    
    // Load tracks for assembly2
    $tracks2 = loadFilteredTracks($organism2, $asm2, $user_access_level);
    
    // Load synteny tracks connecting these assemblies
    $synteny_tracks = loadSyntenyTracks($assembly1, $assembly2, $user_access_level);
    
    // Combine all tracks
    $config['tracks'] = array_merge($tracks1, $tracks2, $synteny_tracks);
    
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
```

### Step 3: Create loadSyntenyTracks()

```php
function loadSyntenyTracks($assembly1, $assembly2, $user_access_level) {
    $tracks_dir = __DIR__ . "/../../metadata/jbrowse2-configs/tracks/synteny";
    
    // Synteny tracks can be stored in either direction
    // Try both: assembly1_assembly2 and assembly2_assembly1
    $pattern1 = "{$assembly1}_{$assembly2}";
    $pattern2 = "{$assembly2}_{$assembly1}";
    
    $track_files = [];
    
    // Check first pattern
    if (is_dir("$tracks_dir/$pattern1")) {
        $track_files = array_merge($track_files, glob("$tracks_dir/$pattern1/*/*.json"));
    }
    
    // Check second pattern (reversed)
    if (is_dir("$tracks_dir/$pattern2")) {
        $track_files = array_merge($track_files, glob("$tracks_dir/$pattern2/*/*.json"));
    }
    
    if (empty($track_files)) {
        return [];
    }
    
    $filtered_tracks = [];
    $is_whitelisted = isWhitelistedIP();
    
    $access_hierarchy = [
        'ADMIN' => 4,
        'IP_IN_RANGE' => 3,
        'COLLABORATOR' => 2,
        'PUBLIC' => 1
    ];
    
    $user_level_value = $access_hierarchy[$user_access_level] ?? 0;
    
    foreach ($track_files as $track_file) {
        $track_def = json_decode(file_get_contents($track_file), true);
        
        if (!$track_def) continue;
        
        // Get track access level
        $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
        $track_level_value = $access_hierarchy[$track_access_level] ?? 1;
        
        // Check if user has access
        if ($user_level_value < $track_level_value) {
            continue;
        }
        
        // COLLABORATOR check: Must have access to at least one of the assemblies
        if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
            $user_access = $_SESSION['access'] ?? [];
            
            // Parse assembly names to get organism/assembly
            list($org1, $asm1) = parseAssemblyName($assembly1);
            list($org2, $asm2) = parseAssemblyName($assembly2);
            
            $has_access = false;
            
            // Check if user has access to assembly1
            if (isset($user_access[$org1]) && in_array($asm1, (array)$user_access[$org1])) {
                $has_access = true;
            }
            
            // Check if user has access to assembly2
            if (isset($user_access[$org2]) && in_array($asm2, (array)$user_access[$org2])) {
                $has_access = true;
            }
            
            if (!$has_access) {
                continue;
            }
        }
        
        // Add JWT tokens to track URLs
        $track_with_tokens = addTokensToTrack($track_def, null, null, $user_access_level, $is_whitelisted);
        
        if ($track_with_tokens) {
            $filtered_tracks[] = $track_with_tokens;
        }
    }
    
    return $filtered_tracks;
}
```

### Step 4: Create parseAssemblyName() Helper

```php
function parseAssemblyName($full_name) {
    // Parse "Nematostella_vectensis_GCA_033964005.1" 
    // into ["Nematostella_vectensis", "GCA_033964005.1"]
    
    // Assembly IDs typically start with GCA_ or GCF_
    if (preg_match('/^(.+?)_(GC[AF]_\d+\.\d+)$/', $full_name, $matches)) {
        return [$matches[1], $matches[2]];
    }
    
    // Fallback: split on last underscore
    $parts = explode('_', $full_name);
    $assembly = array_pop($parts);
    $organism = implode('_', $parts);
    
    return [$organism, $assembly];
}
```

---

## Frontend Integration

### Option 1: Dedicated Synteny Page

**File:** `/data/moop/jbrowse2-synteny.php`

```php
<?php
session_start();
require_once 'includes/config_init.php';
require_once 'includes/access_control.php';

$assembly1 = $_GET['assembly1'] ?? '';
$assembly2 = $_GET['assembly2'] ?? '';

if (empty($assembly1) || empty($assembly2)) {
    // Show assembly selector
    include 'templates/synteny-selector.php';
    exit;
}

// Load JBrowse2 with dual-assembly config
?>
<!DOCTYPE html>
<html>
<head>
    <title>Synteny View: <?php echo htmlspecialchars($assembly1); ?> vs <?php echo htmlspecialchars($assembly2); ?></title>
    <script src="/moop/jbrowse2/jbrowse_linear_view.js"></script>
</head>
<body>
    <div id="jbrowse"></div>
    <script>
        const configUrl = '/moop/api/jbrowse2/config.php?assembly1=<?php echo urlencode($assembly1); ?>&assembly2=<?php echo urlencode($assembly2); ?>';
        
        fetch(configUrl)
            .then(response => response.json())
            .then(config => {
                JBrowseLinearView.create({
                    container: document.getElementById('jbrowse'),
                    config: config
                });
            });
    </script>
</body>
</html>
```

### Option 2: Add Button to Existing jbrowse2.php

Add "Compare with another assembly" button that:
1. Shows assembly selector dialog
2. Redirects to: `/moop/jbrowse2-synteny.php?assembly1=X&assembly2=Y`
3. Or reloads same page with dual-assembly config

---

## Storage Structure

### Synteny Tracks Storage

```
metadata/jbrowse2-configs/tracks/synteny/
├── Anoura_caudifer_GCA_004027475.1_Nematostella_vectensis_GCA_033964005.1/
│   ├── paf/
│   │   └── anoura_vs_nematostella.json
│   ├── pif/
│   │   └── whole_genome_synteny.json
│   └── mcscan/
│       └── orthologs.json
└── Nematostella_vectensis_GCA_033964005.1_Hydra_vulgaris_GCA_000004535.1/
    └── paf/
        └── nematostella_vs_hydra.json
```

**Naming Convention:** Alphabetically sort assembly names for consistency

### Track Metadata Format

```json
{
  "type": "SyntenyTrack",
  "trackId": "anoura_vs_nematostella_paf",
  "name": "Anoura vs Nematostella PAF Alignment",
  "assemblyNames": [
    "Anoura_caudifer_GCA_004027475.1",
    "Nematostella_vectensis_GCA_033964005.1"
  ],
  "adapter": {
    "type": "PAFAdapter",
    "pafLocation": {
      "uri": "/moop/data/tracks/synteny/anoura_nematostella.paf",
      "locationType": "UriLocation"
    },
    "assemblyNames": [
      "Anoura_caudifer_GCA_004027475.1",
      "Nematostella_vectensis_GCA_033964005.1"
    ]
  },
  "metadata": {
    "access_level": "PUBLIC",
    "track_type": "synteny_paf"
  }
}
```

---

## Advantages of Dynamic Approach

1. **No pre-generation needed** - Configs built on-demand
2. **Always up-to-date** - No need to regenerate after adding tracks
3. **Automatic permission filtering** - Uses existing access control
4. **Single codebase** - Reuses loadFilteredTracks() logic
5. **Flexible** - Can compare any two assemblies dynamically
6. **JWT tokens added dynamically** - Same security as single-assembly

---

## Access Control for Synteny Tracks

### Public User
- Can see PUBLIC synteny tracks
- Can see PUBLIC tracks from both assemblies

### COLLABORATOR User (Project A)
- Can see synteny tracks if they have access to Assembly A or Assembly B
- Can see all tracks from assemblies they have access to
- Can see PUBLIC tracks from other assemblies

### IP_IN_RANGE / ADMIN
- Can see all synteny tracks
- Can see all tracks from all assemblies

---

## Testing Plan

### Test 1: Public User Views Synteny

```bash
# As public user (no login)
curl "http://localhost/moop/api/jbrowse2/config.php?assembly1=Nematostella_vectensis_GCA_033964005.1&assembly2=Anoura_caudifer_GCA_004027475.1" | jq .

# Expected:
# - 2 assemblies
# - PUBLIC tracks from both assemblies
# - PUBLIC synteny tracks
```

### Test 2: COLLABORATOR User

```bash
# As collaborator for Nematostella only
curl -b "session_cookie" "http://localhost/moop/api/jbrowse2/config.php?assembly1=Nematostella_vectensis_GCA_033964005.1&assembly2=Anoura_caudifer_GCA_004027475.1" | jq .

# Expected:
# - 2 assemblies
# - ALL Nematostella tracks (PUBLIC + COLLABORATOR)
# - Only PUBLIC Anoura tracks
# - Synteny tracks (if user has access to either assembly)
```

### Test 3: Assembly Not Found

```bash
curl "http://localhost/moop/api/jbrowse2/config.php?assembly1=Invalid&assembly2=AlsoInvalid"

# Expected: 404 error
```

---

## Implementation Checklist

- [ ] Add dual-assembly detection to config.php
- [ ] Implement generateDualAssemblyConfig()
- [ ] Implement loadSyntenyTracks()
- [ ] Implement parseAssemblyName()
- [ ] Test with public user
- [ ] Test with collaborator user
- [ ] Test with admin user
- [ ] Create jbrowse2-synteny.php UI
- [ ] Add assembly selector component
- [ ] Test loading in JBrowse2
- [ ] Document usage

---

## URL Structure

### Single Assembly (Existing)
```
/moop/jbrowse2.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
```

### Dual Assembly (New)
```
/moop/jbrowse2-synteny.php?assembly1=Nematostella_vectensis_GCA_033964005.1&assembly2=Anoura_caudifer_GCA_004027475.1
```

Or reuse existing:
```
/moop/jbrowse2.php?assembly1=Nematostella_vectensis_GCA_033964005.1&assembly2=Anoura_caudifer_GCA_004027475.1
```

---

## Next Steps

1. ✅ Design complete (this document)
2. ⏳ Implement in config.php
3. ⏳ Create jbrowse2-synteny.php
4. ⏳ Test with generated synteny tracks
5. ⏳ Add assembly selector UI
6. ⏳ Document for users

