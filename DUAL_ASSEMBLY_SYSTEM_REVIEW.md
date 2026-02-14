# Dual-Assembly Track System Review

**Date:** 2026-02-14  
**Status:** ✅ BUILT but ❌ NOT INTEGRATED into config.php

---

## Current Status Summary

### ✅ What Exists and Works

1. **Google Sheet Parser** - `SyntenyGoogleSheetsParser.php`
   - Parses dual-assembly tracks from Google Sheets
   - Required columns: `track_id`, `name`, `track_path`, `organism1`, `assembly1`, `organism2`, `assembly2`
   - Optional columns: `bed1_path`, `bed2_path` (for MCScan), `category`, `access_level`, metadata fields
   - **Status:** ✅ Complete

2. **Track Generator** - `SyntenyTrackGenerator.php`
   - Generates PAF, PIF, MCScan tracks (NOT MAF - that's single-assembly)
   - Creates metadata JSON files
   - **Storage location:** `metadata/jbrowse2-configs/tracks/synteny/{organism1}_{assembly1}_{organism2}_{assembly2}/{track_id}.json`
   - **Status:** ✅ Complete

3. **CLI Tool** - `generate_synteny_tracks_from_sheet.php`
   - Command-line interface for generating synteny tracks
   - **Usage:** `php generate_synteny_tracks_from_sheet.php SHEET_ID --gid GID`
   - **Status:** ✅ Complete

4. **Track Type Handlers**
   - PAFTrack.php - ✅ Exists (for .paf files)
   - PIFTrack.php - ? Need to verify
   - MCScanTrack.php - ? Need to verify

### ❌ What's Missing - Integration into JBrowse2

**CRITICAL:** The dual-assembly tracks are NOT loaded by `api/jbrowse2/config.php` yet!

Currently `config.php`:
- ✅ Loads single-assembly tracks from `tracks/{organism}/{assembly}/{type}/*.json`
- ❌ Does NOT load synteny tracks from `tracks/synteny/*/*.json`
- ❌ Does NOT create dual-assembly JBrowse2 configs

---

## How Dual-Assembly Should Work

### Track Storage Structure

**Single-assembly tracks:**
```
metadata/jbrowse2-configs/tracks/
└── Nematostella_vectensis/
    └── GCA_033964005.1/
        ├── bam/
        ├── bigwig/
        └── maf/        # MAF is single-assembly!
```

**Dual-assembly tracks:**
```
metadata/jbrowse2-configs/tracks/synteny/
└── Nematostella_vectensis_GCA_033964005.1_Anoura_caudifer_GCA_004027475.1/
    ├── paf/
    │   └── my_alignment.json
    ├── pif/
    │   └── my_synteny.json
    └── mcscan/
        └── my_orthologs.json
```

### JBrowse2 Config Structure (Planned but Not Implemented)

**Current:** Only single-assembly configs exist
```
jbrowse2/configs/
├── Nematostella_vectensis_GCA_033964005.1/
│   ├── PUBLIC.json
│   ├── ADMIN.json
│   └── ...
```

**Needed:** Dual-assembly configs
```
jbrowse2/configs/
├── Nematostella_vectensis_GCA_033964005.1/                    # Single (existing)
├── Anoura_caudifer_GCA_004027475.1/                           # Single (existing)
└── Anoura_caudifer_GCA_004027475.1_Nematostella_vectensis_GCA_033964005.1/   # NEW!
    ├── PUBLIC.json             # Has both assemblies + public synteny tracks
    ├── ADMIN.json              # Has both assemblies + all synteny tracks
    ├── Anoura_caudifer/
    │   └── COLLABORATOR.json   # For Anoura project collaborators
    └── Nematostella_vectensis/
        └── COLLABORATOR.json   # For Nematostella project collaborators
```

---

## Integration Plan

### Step 1: Add Synteny Track Loading to config.php

**File:** `/data/moop/api/jbrowse2/config.php`

**Function:** `loadFilteredTracks()` needs to also load synteny tracks

**Current logic:**
```php
// Loads tracks from: tracks/{organism}/{assembly}/{type}/*.json
$track_files = glob("$tracks_dir/$organism/$assembly/*/*.json");
```

**Need to add:**
```php
// AFTER loading single-assembly tracks, also check for synteny tracks
// Pattern: tracks/synteny/{organism1}_{assembly1}_{organism2}_{assembly2}/*/*.json

// Find synteny directories that include this organism/assembly
$synteny_pattern = "$tracks_dir/synteny/*{$organism}_{$assembly}*/*/*.json";
$synteny_files = glob($synteny_pattern);

// Also check reversed pattern (assembly might be listed second)
$synteny_pattern2 = "$tracks_dir/synteny/*_{$organism}_{$assembly}/*/*.json";
$synteny_files2 = glob($synteny_pattern2);

$all_synteny = array_merge($synteny_files, $synteny_files2);
```

**But wait!** This has a problem...

### The Dual-Assembly Problem

When a user loads assembly A, should they see synteny tracks between A and B?

**Options:**

1. **No** - Only show single-assembly tracks (current behavior)
   - Synteny tracks require special dual-assembly view
   - User must explicitly open dual-assembly comparison

2. **Yes** - Show synteny tracks as available (but indicate they need dual view)
   - Add synteny tracks to track list
   - Mark them as "Requires: Assembly B"
   - Clicking opens dual-assembly view

3. **Separate Interface** - Add "Compare with another assembly" button
   - Opens assembly selector
   - Loads dual-assembly config with synteny tracks

**Recommendation:** Option 3 is cleanest

### Step 2: Create Dual-Assembly View Loader

**New file:** `/data/moop/jbrowse2-synteny.php` (or add parameter to existing)

**Functionality:**
- User selects two assemblies
- Loads config from: `/moop/api/jbrowse2/synteny-config.php?assembly1=X&assembly2=Y`
- JBrowse2 opens with both assemblies side-by-side
- Synteny tracks displayed connecting the two

### Step 3: Create synteny-config.php API

**New file:** `/data/moop/api/jbrowse2/synteny-config.php`

**Parameters:**
- `assembly1` - First assembly name (e.g., Nematostella_vectensis_GCA_033964005.1)
- `assembly2` - Second assembly name

**Returns:**
```json
{
  "assemblies": [
    { ...assembly1 definition... },
    { ...assembly2 definition... }
  ],
  "tracks": [
    { ...synteny tracks connecting assembly1 and assembly2... }
  ],
  "defaultSession": {
    "name": "Assembly1 vs Assembly2",
    "views": [
      {
        "type": "LinearSyntenyView",
        "views": [
          { "type": "LinearGenomeView", "displayedRegions": [...], "tracks": [] },
          { "type": "LinearGenomeView", "displayedRegions": [...], "tracks": [] }
        ],
        "tracks": [
          { ...synteny track... }
        ]
      }
    ]
  }
}
```

---

## Testing the Current System

### Test 1: Can we generate synteny tracks?

```bash
cd /data/moop

# Create test Google Sheet with format:
# track_id | name | track_path | organism1 | assembly1 | organism2 | assembly2
# test_paf | Test PAF | /path/to/test.paf | Org1 | Asm1 | Org2 | Asm2

# Run generator
php tools/jbrowse/generate_synteny_tracks_from_sheet.php SHEET_ID --gid GID --dry-run

# Check if tracks would be created
```

### Test 2: Where do generated tracks go?

Expected location:
```
/data/moop/metadata/jbrowse2-configs/tracks/synteny/
└── Org1_Asm1_Org2_Asm2/
    └── paf/
        └── test_paf.json
```

### Test 3: Are they loaded by config.php?

```bash
# Check config.php for an assembly
curl "http://localhost/moop/api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1" | jq '.tracks[] | select(.type == "SyntenyTrack")'

# Expected: Empty (no synteny tracks loaded)
```

---

## What Needs to Be Done

### Priority 1: Verify Track Type Handlers Exist

```bash
ls -la /data/moop/lib/JBrowse/TrackTypes/{PAF,PIF,MCScan}Track.php
```

If missing, need to create them.

### Priority 2: Decide on Integration Approach

Three options:
1. **Auto-load synteny tracks** (complex, might confuse users)
2. **Separate dual-assembly UI** (cleanest, requires new interface)
3. **Hybrid** (show synteny tracks but don't load until user clicks)

### Priority 3: Implement synteny-config.php

Create API endpoint that:
- Takes two assembly names
- Loads both assembly definitions
- Loads synteny tracks between them
- Returns dual-assembly JBrowse2 config

### Priority 4: Update jbrowse2.php or Create New UI

Either:
- Add dual-assembly mode to existing jbrowse2.php
- Create new jbrowse2-synteny.php for comparisons

---

## Key Questions to Answer

1. **Do PAFTrack, PIFTrack, MCScanTrack handlers exist?**
   - Check: `/data/moop/lib/JBrowse/TrackTypes/`

2. **How should users access dual-assembly views?**
   - New page? Button on existing page? Automatic?

3. **Should synteny tracks appear in single-assembly view?**
   - Yes (with note they require dual view)?
   - No (keep them separate)?

4. **What JBrowse2 view type do we use?**
   - LinearSyntenyView (side-by-side)
   - DotplotView (2D comparison)
   - Both?

---

## Next Steps

1. ✅ **Review current system** (this document)
2. ⏳ **Check track type handlers exist**
3. ⏳ **Create test synteny track**
4. ⏳ **Decide on integration approach**
5. ⏳ **Implement synteny-config.php**
6. ⏳ **Add UI for dual-assembly comparison**

---

## MAF Track Note

**IMPORTANT:** MAF tracks are NOT dual-assembly/synteny tracks!

- MAF shows multiple sequence alignments
- Displayed on ONE reference assembly
- Similar to conservation tracks
- Already handled by single-assembly system
- The bug we fixed earlier was for MAF as single-assembly ✅

