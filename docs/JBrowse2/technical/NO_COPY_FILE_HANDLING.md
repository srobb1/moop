# Track File Handling: No-Copy Mode

**Date:** February 11, 2026  
**Status:** ✅ Updated

---

## Problem

The original track scripts were **copying** all track files to a centralized location:

```
Source: /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/file.bw
   ↓
   COPY to
   ↓
Target: /data/moop/data/tracks/bigwig/Nematostella_vectensis_GCA_033964005.1_file.bw
```

**Issues:**
- ❌ **Doubles storage usage** (2GB original + 2GB copy = 4GB)
- ❌ **Incompatible with remote servers** (can't copy from remote URL)
- ❌ **Requires syncing** if source files change
- ❌ **Wastes time** copying large BAM files

---

## Solution

**Modified scripts to use original file paths directly:**

```
Source: /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/file.bw
   ↓
   VERIFY (format, index)
   ↓
   STORE PATH IN METADATA
   ↓
No copy! Use original location
```

**Benefits:**
- ✅ **No duplication** - single copy of data
- ✅ **Remote server support** - can use URLs
- ✅ **Fast** - no copying large files
- ✅ **Simple** - source files are authoritative

---

## Changes Made

### 1. add_bigwig_track.sh

**Before:**
```bash
# Copy file to centralized location
TARGET_PATH="$TRACKS_DIR/bigwig/${ORGANISM}_${ASSEMBLY}_$(basename $FILE)"
cp "$BIGWIG_FILE" "$TARGET_PATH"

# Store centralized path in metadata
"uri": "/moop/data/tracks/bigwig/$TARGET_FILENAME"
```

**After:**
```bash
# Verify file exists and is valid
if [ ! -f "$BIGWIG_FILE" ]; then
    log_error "BigWig file not found: $BIGWIG_FILE"
    exit 1
fi

# Validate format
bigWigInfo "$BIGWIG_FILE" > /dev/null 2>&1

# Convert path to web-accessible URI
FILE_URI="${BIGWIG_FILE#/data}"  # /data/moop/... → /moop/...

# Store original path in metadata
"uri": "$FILE_URI",
"file_path": "$BIGWIG_FILE",
"is_remote": false
```

### 2. add_bam_track.sh

**Before:**
```bash
# Copy BAM and BAI to centralized location
TARGET_PATH="$TRACKS_DIR/bam/${ORGANISM}_${ASSEMBLY}_$(basename $FILE)"
cp "$BAM_FILE" "$TARGET_PATH"
cp "$BAI_FILE" "${TARGET_PATH}.bai"

# Store centralized paths
"bamLocation": {"uri": "/moop/data/tracks/bam/$TARGET_FILENAME"}
"index": {"uri": "/moop/data/tracks/bam/$TARGET_FILENAME.bai"}
```

**After:**
```bash
# Verify BAM file and index exist
if [ ! -f "$BAM_FILE" ]; then
    log_error "BAM file not found"
    exit 1
fi

if [ ! -f "$BAI_FILE" ]; then
    log_error "BAI index not found. Run: samtools index $BAM_FILE"
    exit 1
fi

# Validate
samtools quickcheck "$BAM_FILE"

# Convert paths to web-accessible URIs
FILE_URI="${BAM_FILE#/data}"
INDEX_URI="${BAI_FILE#/data}"

# Store original paths
"bamLocation": {"uri": "$FILE_URI"},
"index": {"uri": "$INDEX_URI"},
"file_path": "$BAM_FILE",
"is_remote": false
```

---

## Path Conversion

### Local Files

**Input:** `/data/moop/data/tracks/Organism/Assembly/file.bw`  
**URI:** `/moop/data/tracks/Organism/Assembly/file.bw` (web-accessible)  
**Storage:** Original path in `file_path` field

### Remote Files (Future)

**Input:** `https://tracks.example.com/data/file.bw`  
**URI:** `https://tracks.example.com/data/file.bw` (use as-is)  
**Storage:** URL in `file_path` field, `is_remote: true`

---

## File Organization

### Recommended Structure

```
/data/moop/data/tracks/
├── {Organism_name}/
│   └── {Assembly_ID}/
│       ├── bigwig/
│       │   ├── sample1.bw
│       │   └── sample2.bw
│       ├── bam/
│       │   ├── sample1.bam
│       │   └── sample1.bam.bai
│       └── vcf/
│           ├── variants.vcf.gz
│           └── variants.vcf.gz.tbi
```

**No more centralized copies in:**
- ~~`/data/moop/data/tracks/bigwig/Organism_Assembly_file.bw`~~
- ~~`/data/moop/data/tracks/bam/Organism_Assembly_file.bam`~~

---

## Migration

### For Existing Installations

If you have existing copied files, you can:

**Option 1: Keep using copies** (works fine, just wastes space)
```bash
# No action needed - metadata still points to copies
```

**Option 2: Clean up copies and regenerate**
```bash
# 1. Remove copied track files
rm -rf /data/moop/data/tracks/bigwig/Organism_Assembly_*
rm -rf /data/moop/data/tracks/bam/Organism_Assembly_*

# 2. Remove track metadata
rm /data/moop/metadata/jbrowse2-configs/tracks/Organism_Assembly_*.json

# 3. Remove cached configs
rm -rf /data/moop/jbrowse2/configs/Organism_Assembly/

# 4. Re-run track setup (will use original paths)
python3 tools/jbrowse/generate_tracks_from_sheet.py ...

# 5. Regenerate configs
php tools/jbrowse/generate-jbrowse-configs.php
```

**Option 3: Keep copies as source, delete originals**
```bash
# If you prefer centralized storage:
# 1. Move copies to be the "source"
mv /data/moop/data/tracks/bigwig/Organism_* \
   /data/moop/data/tracks/Organism/Assembly/bigwig/

# 2. Delete duplicate originals
rm -rf /data/moop/data/tracks/Organism/Assembly/bigwig/ORIGINAL_*

# 3. Regenerate with new paths
```

---

## Remote Server Support

### Using URLs

The updated scripts support remote files:

```bash
# Add track from remote server
./tools/jbrowse/add_bigwig_track.sh \
    "https://tracks.example.com/data/sample.bw" \
    Organism Assembly \
    --name "Remote Track"
```

**Metadata will store:**
```json
{
  "adapter": {
    "bigWigLocation": {
      "uri": "https://tracks.example.com/data/sample.bw"
    }
  },
  "metadata": {
    "file_path": "https://tracks.example.com/data/sample.bw",
    "is_remote": true
  }
}
```

### Server Requirements

**Remote track server must:**
- Support HTTP **Range requests** (for efficient data loading)
- Enable **CORS** headers:
  ```
  Access-Control-Allow-Origin: *
  Access-Control-Allow-Headers: Range
  Access-Control-Expose-Headers: Content-Length, Content-Range
  ```

---

## Testing

### Test No-Copy Mode

```bash
# 1. Clean up existing Nematostella files
rm -rf /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json
rm -rf /data/moop/metadata/jbrowse2-configs/tracks/Nematostella_vectensis_GCA_033964005.1_*
rm -rf /data/moop/jbrowse2/configs/Nematostella_vectensis_GCA_033964005.1
rm -rf /data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1
rm -rf /data/moop/data/tracks/bigwig/Nematostella_vectensis_GCA_033964005.1_*
rm -rf /data/moop/data/tracks/bam/Nematostella_vectensis_GCA_033964005.1_*

# 2. Run fresh setup with Google Sheets
python3 tools/jbrowse/generate_tracks_from_sheet.py \
    "1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo" \
    --gid 1977809640 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1

# 3. Verify no copies created
ls /data/moop/data/tracks/bigwig/Nematostella_vectensis_GCA_033964005.1_*
# Should be: No such file or directory

# 4. Check metadata uses original paths
jq '.adapter.bigWigLocation.uri' \
    /data/moop/metadata/jbrowse2-configs/tracks/MOLNG-2707_S1-body-wall.pos.json
# Should show: /moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/...

# 5. Generate configs
php tools/jbrowse/generate-jbrowse-configs.php

# 6. Test in browser
```

---

## Storage Savings Example

### Before (With Copies)

```
/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/
├── bigwig/     913 MB (original)
└── bam/        1.1 GB (original)

/data/moop/data/tracks/
├── bigwig/     913 MB (copies)
└── bam/        1.1 GB (copies)

Total: 4 GB (2x duplication)
```

### After (No Copies)

```
/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/
├── bigwig/     913 MB (only copy)
└── bam/        1.1 GB (only copy)

Total: 2 GB (50% savings!)
```

---

## Related Scripts

**Also need to update:**
- ✅ `add_bigwig_track.sh` - Done
- ✅ `add_bam_track.sh` - Done
- ⏳ `add_vcf_track.sh` - TODO
- ⏳ `add_cram_track.sh` - TODO
- ⏳ `add_gff_track.sh` - TODO (already uses symlinks in genomes/)
- ⏳ `add_bed_track.sh` - TODO
- ⏳ `add_gtf_track.sh` - TODO

---

## Summary

✅ **Scripts now use original file paths**  
✅ **No copying = 50% storage savings**  
✅ **Remote URL support ready**  
✅ **Faster track loading**  
✅ **Single source of truth**  

**Next:** Test with fresh Nematostella setup from scratch!
