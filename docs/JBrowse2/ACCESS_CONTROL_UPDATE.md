# Access Control Update Summary

**Date:** February 10, 2026  
**Update:** Corrected access level terminology and track permissions

---

## Changes Made

### Access Level Terminology

**Corrected from:**
- ~~ALL~~ → **ADMIN**

**Current access levels:**
- **ADMIN** - Admin users only
- **COLLABORATOR** - Logged in users
- **PUBLIC** - Everyone (including anonymous)
- **IP_IN_RANGE** - IP whitelist (treated like ADMIN)

---

## Nematostella vectensis Access Strategy

### Assembly Level
- **PUBLIC** - Everyone can see the organism

### Track Levels
- **Genome Reference** (FASTA) → PUBLIC (automatic)
- **Annotations** (GFF) → PUBLIC (auto-loaded)
- **BigWig Coverage Tracks** → **PUBLIC**
  - Positive strand coverage
  - Negative strand coverage
- **BAM Alignment Track** → **ADMIN**
  - Raw sequencing alignments restricted to admins

### Rationale

This configuration allows:
- ✅ Public users can browse the genome and see gene annotations
- ✅ Public users can see RNA-seq **coverage patterns**
- ✅ Only admins can see individual read **alignments** (more detailed/sensitive)

---

## Updated Files

1. **WALKTHROUGH_Nematostella_vectensis.md**
   - Fixed access level terminology (ADMIN not ALL)
   - Updated BAM command to use `--access ADMIN`
   - Added detailed access control section
   - Updated filenames to match actual files

2. **integrate_nematostella.sh**
   - Changed BAM track to `--access ADMIN`
   - Updated summary output to reflect access levels
   - Updated filenames to match actual files

3. **INTEGRATION_SUMMARY_Nematostella.md**
   - Updated track access level display
   - Clarified PUBLIC vs ADMIN tracks

4. **IMPLEMENTATION_PLAN_Nematostella.md**
   - Updated BAM track example to use ADMIN

5. **QUICK_REFERENCE_Nematostella.md**
   - Added access control strategy section
   - Clarified track visibility

---

## Commands Updated

### BAM Track (was PUBLIC, now ADMIN)

**Before:**
```bash
./tools/jbrowse/add_bam_track.sh \
    <file> <organism> <assembly> \
    --access PUBLIC
```

**After:**
```bash
./tools/jbrowse/add_bam_track.sh \
    <file> <organism> <assembly> \
    --access ADMIN
```

### BigWig Tracks (remain PUBLIC)

```bash
./tools/jbrowse/add_bigwig_track.sh \
    <file> <organism> <assembly> \
    --access PUBLIC
```

---

## Access Level Reference

### For Assembly Registration

```bash
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    <organism> <assembly> \
    --access-level PUBLIC      # or COLLABORATOR or ADMIN
```

### For Track Addition

```bash
# Public track - everyone can see
--access PUBLIC

# Collaborator track - logged in users only
--access COLLABORATOR

# Admin track - admins and IP whitelist only
--access ADMIN
```

---

## Who Sees What

### Anonymous User (not logged in)
- ✅ Assembly (PUBLIC)
- ✅ Reference sequence
- ✅ Annotations (GFF)
- ✅ BigWig tracks (PUBLIC)
- ❌ BAM track (ADMIN only)

### Logged In User (COLLABORATOR level)
- ✅ Assembly (PUBLIC)
- ✅ Reference sequence
- ✅ Annotations (GFF)
- ✅ BigWig tracks (PUBLIC)
- ❌ BAM track (ADMIN only)

### Admin User (ADMIN level)
- ✅ Assembly
- ✅ Reference sequence
- ✅ Annotations (GFF)
- ✅ BigWig tracks
- ✅ BAM track (ADMIN)

### IP Whitelist User (IP_IN_RANGE)
- ✅ Everything (same as ADMIN)

---

## Testing Access Control

### Test as Anonymous User

```bash
# Clear cookies/use incognito mode
# Navigate to: http://localhost:8888/moop/jbrowse2.php
# Open Nematostella vectensis
# Expected: See BigWig tracks, NO BAM track
```

### Test as Admin User

```bash
# Login as admin
# Navigate to: http://localhost:8888/moop/jbrowse2.php
# Open Nematostella vectensis
# Expected: See ALL tracks including BAM
```

---

## Integration Script Updated

The automated script now correctly sets:
```bash
cd /data/moop/tools/jbrowse
./integrate_nematostella.sh
```

This will:
1. ✅ Create assembly as PUBLIC
2. ✅ Add BAM track as ADMIN
3. ✅ Add BigWig tracks as PUBLIC

---

## Summary

All documentation now correctly uses:
- **ADMIN** (not ~~ALL~~)
- **PUBLIC**
- **COLLABORATOR**
- **IP_IN_RANGE**

Nematostella vectensis will be PUBLIC with ADMIN-only BAM track and PUBLIC BigWig tracks.

---

**Status:** ✅ Documentation updated and consistent  
**Ready to execute:** Yes  
**Access strategy:** Approved
