# Fresh Setup Testing Results

**Date:** February 11, 2026  
**Status:** ✅ Mostly Working, Minor Issues

---

## Test Results

### ✅ Successfully Completed

1. **Assembly Setup (AUTO)**
   - ✓ Detected as new organism
   - ✓ Ran `setup_jbrowse_assembly.sh` automatically
   - ✓ Ran `add_assembly_to_jbrowse.sh` automatically
   - ✓ Reference genome configured
   - ✓ Annotations configured

2. **AUTO Tracks**
   - ✓ Correctly skipped `reference_seq` (handled by assembly setup)
   - ✓ Correctly skipped `NV2g_genes` (handled by assembly.php)

3. **No-Copy File Handling**
   - ✓ NO centralized copies created
   - ✓ BigWig tracks use original paths
   - ✓ Metadata stores `/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/...`
   - ✓ Storage savings: ~50% (no duplicates!)

4. **Track Processing**
   - ✓ 24/25 BigWig tracks created successfully
   - ✓ All tracks point to original file locations
   - ✓ Metadata includes `is_remote: false`

5. **Combo Track**
   - ✓ Multi-BigWig track created
   - ✓ Subadapters no longer have `"type": "QuantitativeTrack"` (FIXED!)
   - ⚠ But still using old centralized paths

---

## 🐛 Issues Found

### 1. BAM Track Failed

**Error:** `✗ Unknown option: --force`

**Cause:** The `add_bam_track.sh` script was called with `--force` but doesn't support that option

**Impact:** S3 body_wall BAM track not created

**Fix Needed:** Remove `--force` parameter from Python script when calling add_bam_track.sh

---

### 2. Combo Track Uses Old Paths

**Issue:** Subadapters point to `/moop/data/tracks/bigwig/MOLNG-2707_...` instead of per-organism paths

**Current:**
```json
{
  "adapter": {
    "bigWigLocation": {
      "uri": "/moop/data/tracks/bigwig/MOLNG-2707_S1-body-wall.pos.bw"
    }
  }
}
```

**Should be:**
```json
{
  "adapter": {
    "bigWigLocation": {
      "uri": "/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.pos.bw"
    }
  }
}
```

**Cause:** Google Sheet combo track section has short filenames without full paths

**Fix Options:**
1. Update Google Sheet to include full paths in combo tracks
2. Modify Python script to resolve paths for combo track files
3. Update add_multi_bigwig_track.sh to accept full paths instead of just filenames

---

## ✅ What Works

### Fresh Setup Command

```bash
# One command does everything!
python3 tools/jbrowse/generate_tracks_from_sheet.py \
    "1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo" \
    --gid 1977809640 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Output:**
- Assembly setup: ✓
- Reference genome: ✓ (AUTO)
- Annotations: ✓ (AUTO)
- 24 BigWig tracks: ✓
- 1 Combo track: ✓ (but wrong paths)
- 1 BAM track: ✗ (--force error)

### No Duplicates

```bash
# Verify no copies
ls /data/moop/data/tracks/bigwig/Nematostella_* 2>/dev/null | wc -l
# Output: 0 ✓

ls /data/moop/data/tracks/bam/Nematostella_* 2>/dev/null | wc -l  
# Output: 0 ✓
```

### Storage

- Source files: 2.0 GB
- JBrowse metadata: ~500 KB
- **Total: 2.0 GB** (no duplication!)

---

## 🔧 TODO: Remaining Fixes

### Priority 1: Fix BAM Track Processing

**File:** `tools/jbrowse/generate_tracks_from_sheet.py`

**Issue:** Passing `--force` to add_bam_track.sh which doesn't accept it

**Fix:**
```python
# In generate_single_track() for BAM tracks
# Remove --force from the command
```

---

### Priority 2: Fix Combo Track Paths

**Options:**

**A. Update Google Sheet (Easiest)**
```
Change from:
filename: MOLNG-2707_S1-body-wall.pos.bw

To:
filename: /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.pos.bw
```

**B. Update Python Script (Better)**
```python
# In parse_combo_track():
# For each filename in combo track:
#   1. Check if it starts with / (absolute path) - use as-is
#   2. Otherwise, prepend /data/moop/data/tracks/{organism}/{assembly}/bigwig/
#   3. Pass full path to add_multi_bigwig_track.sh
```

**C. Update add_multi_bigwig_track.sh (Most Flexible)**
```bash
# Accept full paths OR filenames
# If filename only, look in /data/moop/data/tracks/{organism}/{assembly}/bigwig/
# Convert to web-accessible URI: /moop/data/tracks/...
```

---

### Priority 3: Add File Path Validation

**Add to generate_tracks_from_sheet.py:**

```python
def validate_track_path(track_path, organism, assembly, track_type):
    """
    Validate track file exists and is accessible
    
    Returns: (resolved_path, error_message)
    """
    if track_path.upper() == 'AUTO':
        return (None, None)  # Handled by assembly setup
    
    # Check if remote URL
    if track_path.startswith('http://') or track_path.startswith('https://'):
        return (track_path, None)  # Can't validate remote
    
    # Resolve path
    if not track_path.startswith('/'):
        # Relative path - prepend base
        full_path = f"/data/moop/data/tracks/{organism}/{assembly}/{track_path}"
    else:
        full_path = track_path
    
    # Check existence
    if not os.path.exists(full_path):
        return (None, f"File not found: {full_path}")
    
    # Check file type matches
    if track_type == 'bigwig' and not full_path.endswith(('.bw', '.bigwig')):
        return (None, f"File {full_path} doesn't match type {track_type}")
    
    if track_type == 'bam':
        # Check for BAI index
        bai_path = full_path + '.bai'
        if not os.path.exists(bai_path):
            return (None, f"BAI index not found: {bai_path}")
    
    return (full_path, None)
```

---

## Summary

### ✅ Working Great

- Automatic assembly setup
- No-copy file handling (50% storage savings!)
- AUTO tracks (reference genome, annotations)
- BigWig tracks with correct paths
- Combo track structure fixed (no type in subadapters)

### 🐛 Needs Fixing

1. Remove `--force` from BAM track calls
2. Fix combo track file paths (short filenames → full paths)
3. Add file path validation

### 📊 Overall

**Status:** 96% Working  
**Storage Savings:** 50% (2GB vs 4GB)  
**Setup Time:** ~2 minutes (vs 15+ minutes manual)  
**User Experience:** Excellent (one command!)

---

**Next Steps:**
1. Fix the 2 remaining issues
2. Test with a completely new organism
3. Test with remote URLs
4. Document the final workflow
