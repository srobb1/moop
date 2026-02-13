# Dual-Assembly Config Generation Plan

## Problem
Synteny tracks (PIF, MCScan) reference TWO assemblies and need special config directories.

## Current Structure
```
jbrowse2/configs/
├── Assembly1/
│   ├── config.json
│   ├── PUBLIC.json
│   ├── COLLABORATOR.json
│   ├── IP_IN_RANGE.json
│   └── ADMIN.json
```

## New Structure (for dual-assembly tracks)
```
jbrowse2/configs/
├── Assembly1/                                  # Single assembly (unchanged)
├── Assembly2/                                  # Single assembly (unchanged)
└── Assembly1_Assembly2/                        # NEW: Dual assembly
    ├── config.json                             # Base config with BOTH assemblies
    ├── PUBLIC.json                             # Public synteny tracks
    ├── IP_IN_RANGE.json                        # IP range synteny tracks
    ├── ADMIN.json                              # Admin synteny tracks
    ├── Assembly1/
    │   └── COLLABORATOR.json                   # Assembly1 project collaborators
    └── Assembly2/
        └── COLLABORATOR.json                   # Assembly2 project collaborators
```

## Implementation Steps

### 1. Detect Dual-Assembly Tracks
Scan track metadata for tracks with:
- `assemblyNames` array with 2 entries
- Track types: SyntenyTrack (PIF, MCScan)

### 2. Group Tracks by Assembly Pair
Create groups like:
- `Anoura_caudifer_GCA_004027475.1_Nematostella_vectensis_GCA_033964005.1`
- Sort assembly names alphabetically for consistency

### 3. Generate Dual-Assembly Configs

#### Base Config Structure
```json
{
  "assemblies": [
    { assembly1 definition },
    { assembly2 definition }
  ],
  "tracks": [
    { synteny tracks referencing both assemblies }
  ]
}
```

#### Access Level Filtering
- **PUBLIC/IP_IN_RANGE/ADMIN**: Include all tracks of that level (universal)
- **COLLABORATOR**: Split into two sub-configs by primary assembly
  - Assembly1/COLLABORATOR.json: Tracks where assembly1 is primary
  - Assembly2/COLLABORATOR.json: Tracks where assembly2 is primary

### 4. Directory Naming Convention
Format: `{Assembly1}_{Assembly2}` (alphabetically sorted)

Example:
- If assemblies are: `Nematostella_vectensis_GCA_033964005.1` and `Anoura_caudifer_GCA_004027475.1`
- Directory: `Anoura_caudifer_GCA_004027475.1_Nematostella_vectensis_GCA_033964005.1`
  (Anoura comes first alphabetically)

### 5. JBrowse2 Loading
The dual-assembly config can be loaded via URL:
```
/jbrowse2/?config=/moop/jbrowse2/configs/Assembly1_Assembly2/config.json
```

Or with access level:
```
/jbrowse2/?config=/moop/jbrowse2/configs/Assembly1_Assembly2/PUBLIC.json
```

## Code Changes Needed

### generate-jbrowse-configs.php
1. Add `findDualAssemblyTracks()` function
2. Add `generateDualAssemblyConfigs()` function
3. Modify `processAssemblies()` to call dual-assembly generation after single

### Track Metadata
PIF and MCScan tracks already have:
- `assemblyNames: [asm1, asm2]`
- This is enough to detect them

## Testing
1. Create test synteny tracks
2. Run config generator
3. Verify directory structure created
4. Test loading in JBrowse2 with different access levels
