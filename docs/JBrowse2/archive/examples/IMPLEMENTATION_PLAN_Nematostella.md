# Implementation Plan: Nematostella vectensis JBrowse2 Integration

**Date:** February 10, 2026  
**Organism:** Nematostella vectensis  
**Assembly:** GCA_033964005.1  
**Purpose:** Detailed technical plan for integration and potential enhancements

---

## Executive Summary

This plan outlines the complete integration of Nematostella vectensis into the MOOP JBrowse2 system. The **good news** is that the existing infrastructure supports everything we need without code changes. However, several enhancements could improve user experience.

### Status

✅ **No blocking issues** - Existing scripts handle all requirements  
✅ **Genome reference** - Automatic via setup script  
✅ **Annotations (GFF)** - Automatic via setup script + assembly.php  
✅ **BAM tracks** - Script exists  
✅ **BigWig tracks** - Script exists  
⚠️ **Enhancements available** - See Phase 3 below

---

## Current System Analysis

### What Works Out of the Box

1. **Genome Indexing** (`setup_jbrowse_assembly.sh`)
   - ✅ Creates symlinks to genome.fa and genomic.gff
   - ✅ Indexes FASTA with samtools
   - ✅ Compresses GFF with bgzip
   - ✅ Indexes GFF with tabix

2. **Assembly Registration** (`add_assembly_to_jbrowse.sh`)
   - ✅ Creates assembly metadata JSON
   - ✅ Configures reference sequence track
   - ✅ Sets access control levels

3. **Annotations Auto-Load** (`/api/jbrowse2/assembly.php`)
   - ✅ Lines 242-261: Automatically adds annotations track if `annotations.gff3.gz` exists
   - ✅ Configures GffAdapter with tabix index
   - ✅ No manual track creation needed for GFF!

4. **Track Addition** (`add_bam_track.sh`, `add_bigwig_track.sh`)
   - ✅ Creates track metadata JSON
   - ✅ Supports rich metadata fields
   - ✅ Links tracks to assemblies

### Architecture Diagram

```
User Request
     ↓
jbrowse2.php (checks auth)
     ↓
get-config.php (lists assemblies) ──→ filters by access level
     ↓
assembly.php (loads specific assembly)
     ↓
Reads: /metadata/jbrowse2-configs/assemblies/{organism}_{assembly}.json
     ↓
Checks: /data/genomes/{org}/{asm}/annotations.gff3.gz
     ↓           ↓
     |           └──→ AUTO-ADDS Annotations Track (GffAdapter)
     ↓
Reads: /metadata/jbrowse2-configs/tracks/*.json
     ↓
Filters tracks by:
  - Assembly match
  - Access level
  - User permissions
     ↓
Returns: Complete JBrowse2 config
     ↓
JBrowse2 Renders
```

---

## Implementation Phases

### Phase 1: Basic Integration (15 minutes) ✅ READY

**Status:** Can be executed immediately with existing scripts

#### Step 1.1: Prepare Genome Files

```bash
cd /data/moop
./tools/jbrowse/setup_jbrowse_assembly.sh \
    /data/moop/organisms/Nematostella_vectensis/GCA_033964005.1
```

**Output:**
- `/data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/`
  - `reference.fasta` (symlink)
  - `reference.fasta.fai` (index)
  - `annotations.gff3` (symlink)
  - `annotations.gff3.gz` (compressed)
  - `annotations.gff3.gz.tbi` (index)

**Time:** ~5-10 minutes (depends on genome size)

#### Step 1.2: Register Assembly

```bash
cd /data/moop
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --display-name "Nematostella vectensis (Starlet Sea Anemone)" \
    --access-level PUBLIC \
    --alias "NVE" \
    --alias "GCA_033964005.1"
```

**Output:**
- `/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json`

**Result:** 
- Assembly visible in JBrowse2
- Reference sequence track working
- **Annotations track automatically added** by assembly.php

**Time:** ~30 seconds

---

### Phase 2: Add Experimental Tracks (10 minutes) ✅ READY

**Status:** Scripts exist, ready to execute

#### Step 2.1: Add BAM Track

```bash
cd /data/moop

BAM_FILE="/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bam/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S3-body-wall.bam"

./tools/jbrowse/add_bam_track.sh \
    "$BAM_FILE" \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Alignments (S3)" \
    --category "RNA-seq" \
    --access ADMIN \
    --tissue "body wall" \
    --experiment "MOLNG-2707" \
    --technique "RNA-seq" \
    --description "RNA-seq alignments from body wall tissue, sample S3 (Admin only)"
```

**Output:**
- `/metadata/jbrowse2-configs/tracks/body_wall_rna_seq_alignments_s3.json`

**Time:** ~2 minutes

#### Step 2.2: Add Positive Strand Coverage

```bash
cd /data/moop

BIGWIG_POS="/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.pos.bw"

./tools/jbrowse/add_bigwig_track.sh \
    "$BIGWIG_POS" \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Coverage (+)" \
    --track-id "nv_body_wall_pos" \
    --category "RNA-seq Coverage" \
    --access PUBLIC \
    --color "#1f77b4" \
    --tissue "body wall" \
    --experiment "MOLNG-2707" \
    --technique "RNA-seq" \
    --description "RNA-seq coverage on positive strand, sample S1"
```

**Output:**
- `/metadata/jbrowse2-configs/tracks/body_wall_rna_seq_coverage_pos.json`

**Time:** ~2 minutes

#### Step 2.3: Add Negative Strand Coverage

```bash
cd /data/moop

BIGWIG_NEG="/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.neg.bw"

./tools/jbrowse/add_bigwig_track.sh \
    "$BIGWIG_NEG" \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Coverage (-)" \
    --track-id "nv_body_wall_neg" \
    --category "RNA-seq Coverage" \
    --access PUBLIC \
    --color "#ff7f0e" \
    --tissue "body wall" \
    --experiment "MOLNG-2707" \
    --technique "RNA-seq" \
    --description "RNA-seq coverage on negative strand, sample S1"
```

**Output:**
- `/metadata/jbrowse2-configs/tracks/body_wall_rna_seq_coverage_neg.json`

**Time:** ~2 minutes

---

### Phase 3: Optional Enhancements ⚠️ FUTURE

These enhancements would improve the system but are NOT required for basic functionality.

#### Enhancement 3.1: Grouped Strand Tracks

**Problem:** Positive/negative strand tracks show as separate items in track list

**Solution:** Create a "subtracks" configuration in JBrowse2

**Implementation:**

Create a new track type in metadata that groups related tracks:

```json
{
  "name": "Body Wall RNA-seq Coverage (Stranded)",
  "type": "MultiQuantitativeTrack",
  "track_id": "nv_body_wall_stranded",
  "access_levels": ["PUBLIC"],
  "groups": ["RNA-seq Coverage"],
  "subtracks": [
    {
      "name": "Positive Strand",
      "track_id": "nv_body_wall_pos",
      "color": "#1f77b4"
    },
    {
      "name": "Negative Strand", 
      "track_id": "nv_body_wall_neg",
      "color": "#ff7f0e"
    }
  ]
}
```

**Code Changes Needed:**
- Modify `/api/jbrowse2/assembly.php` to handle `MultiQuantitativeTrack` type
- Add logic to expand subtracks
- Update track scripts to support `--parent-track` option

**Effort:** ~2-3 hours  
**Priority:** Low (nice-to-have)

#### Enhancement 3.2: Auto-Discovery of Track Files

**Problem:** Each track requires manual script execution

**Solution:** Scan tracks directory and auto-create metadata

**Implementation:**

Create `tools/jbrowse/auto_discover_tracks.sh`:

```bash
#!/bin/bash
# Scans /data/tracks/{organism}/{assembly}/ and creates metadata

ORGANISM=$1
ASSEMBLY=$2
TRACKS_BASE="/data/moop/data/tracks/$ORGANISM/$ASSEMBLY"

# Find all BAM files
for bam in "$TRACKS_BASE"/bam/*.bam; do
    # Extract sample name from filename
    SAMPLE=$(basename "$bam" .bam)
    
    # Check if metadata already exists
    if [ ! -f "/data/moop/metadata/jbrowse2-configs/tracks/${SAMPLE}.json" ]; then
        ./add_bam_track.sh "$bam" "$ORGANISM" "$ASSEMBLY" \
            --name "$SAMPLE" \
            --category "Auto-discovered" \
            --auto-generated
    fi
done

# Similar for BigWig files...
```

**Code Changes Needed:**
- Create new script
- Add `--auto-generated` flag to existing scripts
- Add validation to prevent duplicate metadata

**Effort:** ~3-4 hours  
**Priority:** Medium (useful for bulk imports)

#### Enhancement 3.3: Track Metadata from Filename Parsing

**Problem:** Metadata requires manual input

**Solution:** Parse structured filenames for metadata

**Implementation:**

If filenames follow pattern:
```
{organism}_{assembly}_{experiment}_{sample}_{tissue}.{bam|bw}
```

Parse components:
```bash
parse_filename() {
    local filename="$1"
    local base=$(basename "$filename")
    
    # Split by underscores
    IFS='_' read -ra PARTS <<< "$base"
    
    ORGANISM="${PARTS[0]}"
    ASSEMBLY="${PARTS[1]}"
    EXPERIMENT="${PARTS[2]}"
    SAMPLE="${PARTS[3]}"
    TISSUE="${PARTS[4]%.bam}"  # Remove extension
    
    # Auto-populate metadata
    echo "Organism: $ORGANISM"
    echo "Assembly: $ASSEMBLY"
    echo "Experiment: $EXPERIMENT"
    echo "Sample: $SAMPLE"
    echo "Tissue: $TISSUE"
}
```

**Code Changes Needed:**
- Add parsing logic to track scripts
- Add `--parse-filename` option
- Define naming convention standard

**Effort:** ~2 hours  
**Priority:** Medium (reduces manual work)

#### Enhancement 3.4: Track Categories UI

**Problem:** Many tracks clutter the interface

**Solution:** Organize tracks by category with expand/collapse

**Implementation:**

This is more of a frontend change. The backend already supports categories via `groups` field in track metadata.

**Code Changes Needed:**
- Modify JBrowse2 configuration to group tracks by category
- Add custom React components for category UI (if needed)
- Update `js/jbrowse2-loader.js` to process categories

**Effort:** ~4-6 hours  
**Priority:** Low (UI polish)

#### Enhancement 3.5: Track Search/Filter

**Problem:** Hard to find specific tracks when many exist

**Solution:** Add search/filter UI in JBrowse2

**Implementation:**

Add metadata search to API:

```php
// /api/jbrowse2/search-tracks.php
$query = $_GET['q'] ?? '';
$tissue = $_GET['tissue'] ?? '';
$experiment = $_GET['experiment'] ?? '';

// Filter tracks by metadata fields
$filtered_tracks = array_filter($tracks, function($track) use ($query, $tissue, $experiment) {
    if ($tissue && ($track['metadata']['tissue'] ?? '') !== $tissue) return false;
    if ($experiment && ($track['metadata']['experiment'] ?? '') !== $experiment) return false;
    if ($query && stripos(json_encode($track), $query) === false) return false;
    return true;
});
```

**Code Changes Needed:**
- Create new API endpoint
- Add UI for search box
- Update JBrowse2 config to filter displayed tracks

**Effort:** ~6-8 hours  
**Priority:** Medium (very useful with many tracks)

---

## Code Review: Current Implementation

### Files Examined

1. `/api/jbrowse2/assembly.php` - ✅ Perfect, no changes needed
2. `/tools/jbrowse/setup_jbrowse_assembly.sh` - ✅ Works as-is
3. `/tools/jbrowse/add_assembly_to_jbrowse.sh` - ✅ Works as-is
4. `/tools/jbrowse/add_bam_track.sh` - ✅ Works as-is
5. `/tools/jbrowse/add_bigwig_track.sh` - ✅ Works as-is

### Key Finding: Annotations Auto-Load

The most important discovery is in `/api/jbrowse2/assembly.php` lines 242-261:

```php
// 7. ADD REFERENCE ANNOTATIONS IF AVAILABLE
$annotations_file = __DIR__ . "/../../data/genomes/{$organism}/{$assembly}/annotations.gff3.gz";
if (file_exists($annotations_file)) {
    $assembly_config['tracks'][] = [
        'name' => 'Annotations',
        'trackId' => 'annotations',
        'assemblyNames' => [$assembly],
        'type' => 'FeatureTrack',
        'adapter' => [
            'type' => 'GffAdapter',
            'gffLocation' => [
                'uri' => "/jbrowse2/data/{$organism}/{$assembly}/annotations.gff3.gz"
            ],
            'index' => [
                'location' => [
                    'uri' => "/jbrowse2/data/{$organism}/{$assembly}/annotations.gff3.gz.tbi"
                ]
            ]
        ]
    ];
}
```

**This means:**
- ✅ Annotations track is **automatically added** if GFF exists
- ✅ No manual track metadata needed for GFF
- ✅ Just run `setup_jbrowse_assembly.sh` and it works!

---

## Risk Assessment

### Low Risk ✅

- Using existing, tested scripts
- No code modifications required
- Can be rolled back easily (just delete metadata files)

### Potential Issues

1. **File permissions** - Ensure www-data can read all files
   ```bash
   chmod 644 /data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/*
   chmod 644 /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/*/*
   ```

2. **Large file loading** - BAM file is 1GB, may be slow
   - **Solution:** This is expected, JBrowse2 handles it well with indexed access

3. **Track metadata naming conflicts** - Multiple tracks might create conflicts
   - **Solution:** Use unique `--track-id` for each track

---

## Testing Plan

### Test 1: Assembly Visibility

```bash
# Check API returns assembly
curl -s "http://localhost:8888/api/jbrowse2/get-config.php" | \
    jq '.assemblies[] | select(.organism == "Nematostella_vectensis")'
```

**Expected:** Assembly object returned with reference sequence config

### Test 2: Annotations Track

```bash
# Check assembly API includes annotations
curl -s "http://localhost:8888/api/jbrowse2/assembly.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1" | \
    jq '.tracks[] | select(.trackId == "annotations")'
```

**Expected:** Annotations track object with GffAdapter

### Test 3: BAM Track

```bash
# Check track metadata created
jq . /data/moop/metadata/jbrowse2-configs/tracks/body_wall_rna_seq_*.json
```

**Expected:** Valid JSON with BAM adapter configuration

### Test 4: Browser Loading

1. Navigate to `http://localhost:8888/moop/jbrowse2.php`
2. Find "Nematostella vectensis" in assembly list
3. Click to open
4. Verify tracks load:
   - Reference sequence
   - Annotations (genes)
   - RNA-seq BAM
   - RNA-seq coverage (pos/neg)

### Test 5: Access Control

Test with different user types:

```bash
# Anonymous user - should see if PUBLIC
curl -s "http://localhost:8888/api/jbrowse2/get-config.php" | \
    jq '.assemblies[] | select(.organism == "Nematostella_vectensis")'

# Logged in user - should see if PUBLIC or COLLABORATOR
# (test in browser while logged in)

# Admin user - should always see
# (test in browser as admin)
```

---

## Timeline Estimate

| Phase | Task | Time | Status |
|-------|------|------|--------|
| 1.1 | Prepare genome files | 5-10 min | Ready to execute |
| 1.2 | Register assembly | 30 sec | Ready to execute |
| 2.1 | Add BAM track | 2 min | Ready to execute |
| 2.2 | Add BigWig pos | 2 min | Ready to execute |
| 2.3 | Add BigWig neg | 2 min | Ready to execute |
| Testing | Full verification | 10 min | After execution |
| **Total** | **Basic Integration** | **~20-30 min** | **Ready Now** |
|  |  |  |  |
| 3.1 | Grouped strands | 2-3 hours | Optional |
| 3.2 | Auto-discovery | 3-4 hours | Optional |
| 3.3 | Filename parsing | 2 hours | Optional |
| 3.4 | Category UI | 4-6 hours | Optional |
| 3.5 | Track search | 6-8 hours | Optional |
| **Total** | **Enhancements** | **17-23 hours** | **Future** |

---

## Rollback Plan

If anything goes wrong:

```bash
# Remove assembly metadata
rm /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json

# Remove track metadata
rm /data/moop/metadata/jbrowse2-configs/tracks/nematostella_*.json
rm /data/moop/metadata/jbrowse2-configs/tracks/body_wall_*.json

# Remove genome data (optional - can keep for retry)
rm -rf /data/moop/data/genomes/Nematostella_vectensis/

# Clear browser cache
# Refresh JBrowse2 interface
```

**Result:** Assembly and tracks disappear from JBrowse2

---

## Success Criteria

Integration is successful when:

- ✅ Assembly appears in JBrowse2 interface
- ✅ Reference sequence loads and is browsable
- ✅ Annotations track shows genes from GFF
- ✅ BAM track loads and shows alignments
- ✅ BigWig tracks show coverage plots
- ✅ All tracks respond to zoom/pan
- ✅ Access control works correctly
- ✅ No JavaScript errors in browser console
- ✅ API responses are valid JSON

---

## Recommendations

### Immediate Action (Today)

1. ✅ Execute Phase 1 & 2 (basic integration)
2. ✅ Test thoroughly
3. ✅ Document any issues encountered
4. ✅ Take screenshots for documentation

### Short-term (This Week)

1. Add more Nematostella tracks if available
2. Document naming conventions for future tracks
3. Create template track metadata files

### Long-term (Future Sprints)

1. Implement Enhancement 3.2 (auto-discovery) - Most useful
2. Implement Enhancement 3.5 (track search) - Scales better
3. Consider Enhancement 3.1 (grouped strands) - Better UX

---

## Conclusion

**The good news:** Everything is ready to go! The existing MOOP JBrowse2 infrastructure is well-designed and handles all our requirements without code changes.

**Key Insight:** The automatic annotations loading in `assembly.php` means we don't need to manually configure GFF tracks - they just work!

**Next Step:** Execute the integration following the walkthrough document.

**Estimated Time to Production:** 20-30 minutes

---

## References

- Main walkthrough: [WALKTHROUGH_Nematostella_vectensis.md](WALKTHROUGH_Nematostella_vectensis.md)
- Admin guide: [ADMIN_GUIDE.md](ADMIN_GUIDE.md)
- API reference: [API_REFERENCE.md](API_REFERENCE.md)
- Scripts docs: [/tools/jbrowse/README.md](../../tools/jbrowse/README.md)

---

**Status:** Ready for execution  
**Risk Level:** Low  
**Code Changes Required:** None  
**Recommended Start Date:** Immediately
