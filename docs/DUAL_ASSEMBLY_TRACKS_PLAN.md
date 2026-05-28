
---

## Session Notes - 2026-02-13 01:16 UTC

### ✅ Completed Today

1. **Created Separate Synteny Track System**
   - SyntenyGoogleSheetsParser.php - Parses dual-assembly Google Sheets
   - SyntenyTrackGenerator.php - Handles PAF, PIF, MCScan (NOT MAF)
   - generate_synteny_tracks_from_sheet.php - CLI for synteny tracks
   - Test files and validation

2. **Separated Track Generators**
   - TrackGenerator: 9 single-assembly types (removed PAF, PIF, MAF, MCScan)
   - SyntenyTrackGenerator: 3 dual-assembly types (PAF, PIF, MCScan)
   - Clear documentation: SINGLE_VS_DUAL_ASSEMBLY_TRACKS.md

3. **Fixed Access Control Bug**
   - Track types now properly read access_level from trackData
   - Cached configs filter correctly (PUBLIC vs ADMIN)
   - GoogleSheetsParser handles uppercase/lowercase columns

### ❌ Still TODO - MAF Track Issues

**CRITICAL DISCOVERY: MAF is a SINGLE-ASSEMBLY track!**

MAF tracks are NOT synteny tracks. They show multiple sequence alignments 
displayed on ONE reference assembly (like conservation tracks).

#### Problems to Fix:

1. **MAF Uses BigBed Files (Not .maf.gz)**
   - JBrowse1 used: `.bb` file as index pointing to raw MAF file
   - Current implementation: Uses `.maf.gz` with `.gzi` index (WRONG)
   - Need to update MAFTrack.php to use BigBed format

2. **MAF Track Type Location**
   - Currently: In SyntenyTrackGenerator (WRONG - removed from commit)
   - Should be: In TrackGenerator with single-assembly tracks
   - Action: Move MAFTrack back to single-assembly system

3. **Google Sheet Metadata for MAF**
   - Current columns: organism, assembly (single)
   - Need additional column: `bb_path` (BigBed index file path)
   - MAF file structure:
     * Raw MAF file: `/path/to/alignment.maf`
     * BigBed index: `/path/to/alignment.bb`
   - Track displays on single reference assembly but shows alignments from multiple species

4. **MAF Track Configuration Structure**
   ```json
   {
     "type": "FeatureTrack",
     "storeClass": "JBrowse/Store/SeqFeature/BigBed",
     "urlTemplate": "path/to/alignment.bb",
     "onClick": {
       // Custom dialog showing MAF alignment details
       // Points to raw MAF file for sequence data
     }
   }
   ```

#### MAF Track Google Sheet Format (TODO):
```
track_id | name | category | track_path | bb_path | access_level | organism | assembly
maf_001  | Conservation | Conservation | /data/alignment.maf | /data/alignment.bb | PUBLIC | Nematostella_vectensis | GCA_033964005.1
```

### ⏳ Still TODO - Dual-Assembly Config Generation

1. **Update generate-jbrowse-configs.php**
   - Add functions from DUAL_ASSEMBLY_CONFIG_PLAN.md:
     * findDualAssemblyTracks()
     * generateDualAssemblyConfigs()
     * generateDualCollaboratorConfigs()
   
2. **Scan synteny/ directory**
   - Look in: metadata/jbrowse2-configs/tracks/synteny/
   - Group by assembly pair
   - Create dual-assembly config structure:
     ```
     jbrowse2/configs/Assembly1_Assembly2/
     ├── config.json
     ├── PUBLIC.json
     ├── IP_IN_RANGE.json
     ├── ADMIN.json
     ├── Assembly1/
     │   └── COLLABORATOR.json
     └── Assembly2/
         └── COLLABORATOR.json
     ```

### ⏳ Testing Needed

1. **Test Synteny Track Generation**
   - Create test Google Sheet with dual-assembly format
   - Run generate_synteny_tracks_from_sheet.php
   - Verify tracks created in synteny/ directory
   - Check metadata includes both assemblies

2. **Test Dual-Assembly Config Generation**
   - Run generate-jbrowse-configs.php
   - Verify dual-assembly directories created
   - Verify COLLABORATOR split by assembly
   - Test loading in JBrowse2 with both assemblies

3. **Test MAF Tracks (After Fixes)**
   - Create MAF track with BigBed index
   - Test single-assembly display
   - Verify onClick dialog works with raw MAF file

### Summary of Track Type Organization

**Single-Assembly Tracks (TrackGenerator):**
- BigWig, BAM, CRAM, VCF, BED, GFF, GTF, Combo, Auto
- **TODO: Add MAF back here** (with BigBed support)

**Dual-Assembly Synteny Tracks (SyntenyTrackGenerator):**
- PAF (pairwise alignments)
- PIF (whole genome synteny)
- MCScan (ortholog synteny)

**NOT Synteny Tracks:**
- MAF - single assembly, shows multiple species conservation

### Next Session Action Items

1. Fix MAF track implementation:
   - Update MAFTrack.php to use BigBed files
   - Move MAFTrack from SyntenyTrackGenerator to TrackGenerator
   - Update Google Sheet parser to expect bb_path column
   - Test with real MAF/BigBed files

2. Implement dual-assembly config generation:
   - Add functions to generate-jbrowse-configs.php
   - Test with synteny tracks
   - Verify assembly-specific COLLABORATOR configs

3. End-to-end testing:
   - Generate synteny tracks from Google Sheet
   - Generate configs
   - Load in JBrowse2
   - Verify access control works

