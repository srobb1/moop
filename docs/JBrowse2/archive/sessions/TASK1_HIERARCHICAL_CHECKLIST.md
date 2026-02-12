# TASK 1: Hierarchical Track Metadata - Implementation Checklist

**Status:** Ready to implement  
**Estimated Time:** 3 hours  
**Risk Level:** Medium (requires migration)

---

## Files Requiring Updates

### ✅ CRITICAL: API Endpoint
**File:** `api/jbrowse2/assembly.php` (LINE 117)

```php
# OLD:
$track_files = glob("$tracks_dir/*.json");

# NEW:
$track_files = glob("$tracks_dir/$organism/$assembly/*/*.json");
```

### 🔧 Track Creation Scripts (14 files)

All need same pattern update:

```bash
# OLD:
METADATA_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
OUTPUT_FILE="$METADATA_DIR/${TRACK_ID}.json"

# NEW:
METADATA_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"
TRACK_TYPE="bigwig"  # Set appropriately per script
TRACK_DIR="$METADATA_DIR/${ORGANISM}/${ASSEMBLY}/${TRACK_TYPE}"
mkdir -p "$TRACK_DIR"
OUTPUT_FILE="$TRACK_DIR/${TRACK_ID}.json"
```

**Files:**
1. tools/jbrowse/add_bam_track.sh → TRACK_TYPE="bam"
2. tools/jbrowse/add_bed_track.sh → TRACK_TYPE="bed"
3. tools/jbrowse/add_bigwig_track.sh → TRACK_TYPE="bigwig"
4. tools/jbrowse/add_cram_track.sh → TRACK_TYPE="cram"
5. tools/jbrowse/add_gff_track.sh → TRACK_TYPE="gff"
6. tools/jbrowse/add_gtf_track.sh → TRACK_TYPE="gtf"
7. tools/jbrowse/add_maf_track.sh → TRACK_TYPE="maf"
8. tools/jbrowse/add_mcscan_track.sh → TRACK_TYPE="mcscan"
9. tools/jbrowse/add_multi_bigwig_track.sh → TRACK_TYPE="combo"
10. tools/jbrowse/add_paf_track.sh → TRACK_TYPE="paf"
11. tools/jbrowse/add_synteny_track.sh → TRACK_TYPE="synteny"
12. tools/jbrowse/add_vcf_track.sh → TRACK_TYPE="vcf"

### 🐍 Python Script Updates

**File:** `tools/jbrowse/generate_tracks_from_sheet.py`

Functions to update:

1. **track_exists()** - Check hierarchical paths
```python
def track_exists(track_id, metadata_dir, organism, assembly):
    for track_type in ['bigwig', 'bam', 'vcf', 'gff', 'gtf', 'bed', 
                       'cram', 'paf', 'maf', 'combo', 'mcscan', 'synteny']:
        track_path = metadata_dir / organism / assembly / track_type / f"{track_id}.json"
        if track_path.exists():
            return True
    return False
```

2. **clean_orphaned_tracks()** - Scan hierarchical structure
```python
# OLD: metadata_dir.glob('*.json')
# NEW: metadata_dir.glob(f'{organism}/{assembly}/*/*.json')
```

3. **--list-existing** - Scan hierarchical structure
```python
# OLD: for json_file in sorted(metadata_dir.glob('*.json'))
# NEW: for json_file in sorted(metadata_dir.glob(f'{organism}/{assembly}/*/*.json'))
```

### 🗑️ Removal Script

**File:** `tools/jbrowse/remove_jbrowse_data.sh`

Already handles assembly-level removal, just needs hierarchical glob:

```bash
# OLD:
for json in "$METADATA_TRACKS_DIR"/*.json; do

# NEW:
for json in "$METADATA_TRACKS_DIR/$ORGANISM/$ASSEMBLY"/*/*.json; do
```

---

## New File: Migration Script

**File:** `tools/jbrowse/migrate_track_metadata.sh`

```bash
#!/bin/bash
# Migrate flat track structure to hierarchical

MOOP_ROOT="/data/moop"
TRACKS_DIR="$MOOP_ROOT/metadata/jbrowse2-configs/tracks"

echo "Migrating track metadata to hierarchical structure..."
echo ""

migrated=0
errors=0

for json in "$TRACKS_DIR"/*.json; do
    [ -f "$json" ] || continue
    
    filename=$(basename "$json")
    
    # Extract organism and assembly from JSON
    assembly_name=$(jq -r '.assemblyNames[0]' "$json" 2>/dev/null)
    
    if [ -z "$assembly_name" ] || [ "$assembly_name" = "null" ]; then
        echo "⚠ Skipping $filename: No assemblyNames found"
        ((errors++))
        continue
    fi
    
    # Split organism_assembly
    ORGANISM=$(echo "$assembly_name" | cut -d_ -f1)
    ASSEMBLY=$(echo "$assembly_name" | cut -d_ -f2-)
    
    # Determine track type from JSON
    type=$(jq -r '.type' "$json" 2>/dev/null)
    case $type in
        QuantitativeTrack) track_type="bigwig" ;;
        AlignmentsTrack) track_type="bam" ;;
        VariantTrack) track_type="vcf" ;;
        FeatureTrack) track_type="gff" ;;
        MultiQuantitativeTrack) track_type="combo" ;;
        LinearSyntenyTrack) track_type="synteny" ;;
        *) track_type="other" ;;
    esac
    
    # Create target directory
    target_dir="$TRACKS_DIR/$ORGANISM/$ASSEMBLY/$track_type"
    mkdir -p "$target_dir"
    
    # Move file
    mv "$json" "$target_dir/"
    echo "✓ Moved: $filename → $ORGANISM/$ASSEMBLY/$track_type/"
    ((migrated++))
done

echo ""
echo "Migration complete:"
echo "  Migrated: $migrated tracks"
echo "  Errors: $errors tracks"
```

---

## Implementation Steps

### Step 1: Backup (5 min)
```bash
cd /data/moop
tar -czf metadata-tracks-backup-$(date +%Y%m%d-%H%M).tar.gz metadata/jbrowse2-configs/tracks/
```

### Step 2: Create Migration Script (10 min)
Create `tools/jbrowse/migrate_track_metadata.sh`

### Step 3: Test Migration (5 min)
```bash
# Dry-run first (modify script to echo commands instead of executing)
bash tools/jbrowse/migrate_track_metadata.sh
```

### Step 4: Run Migration (2 min)
```bash
bash tools/jbrowse/migrate_track_metadata.sh
```

### Step 5: Update API Endpoint (5 min)
Edit `api/jbrowse2/assembly.php` line 117

### Step 6: Update Track Scripts (1 hour)
Use sed to batch-update all 14 scripts

### Step 7: Update Python Script (30 min)
Update 3 functions

### Step 8: Update Removal Script (10 min)
Update glob pattern

### Step 9: Test (30 min)
1. Verify tracks load in JBrowse2
2. Create new track, check location
3. Test --list-existing
4. Test --clean
5. Test removal

---

## Testing Checklist

- [ ] Backup created
- [ ] Migration script created
- [ ] Migration dry-run successful
- [ ] Migration run successful
- [ ] API updated
- [ ] Tracks still load in JBrowse2
- [ ] New track creates in hierarchical location
- [ ] --list-existing works
- [ ] --clean works
- [ ] Removal script works
- [ ] All 14 add scripts updated
- [ ] Python script updated

---

## Rollback Plan

If something goes wrong:

```bash
# Stop changes
cd /data/moop

# Remove hierarchical structure
rm -rf metadata/jbrowse2-configs/tracks/*/

# Restore backup
tar -xzf metadata-tracks-backup-*.tar.gz

# Revert code changes
git checkout api/jbrowse2/assembly.php
git checkout tools/jbrowse/*.sh
git checkout tools/jbrowse/*.py
```

---

## Final Structure

```
metadata/jbrowse2-configs/tracks/
├── Nematostella_vectensis/
│   └── GCA_033964005.1/
│       ├── bigwig/
│       │   ├── MOLNG-2707_S1-body-wall.pos.bw.json
│       │   └── MOLNG-2707_S1-body-wall.neg.bw.json
│       ├── bam/
│       ├── gff/
│       │   ├── NV2g_genes.json
│       │   └── NV2t_transcripts.json
│       └── combo/
│           └── simr:four_adult_tissues_molng-2707.json
├── Anoura_caudifer/
│   └── GCA_004027475.1/
│       ├── bigwig/
│       └── bam/
```

---

## Ready to Start?

All research done. Checklist complete. Ready to implement when you are!

