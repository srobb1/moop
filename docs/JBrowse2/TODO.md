# JBrowse2 TODO - Active Tasks

**Last Updated:** 2026-02-14  
**Priority Order:** Top = Most Important

---

## 🔴 HIGH PRIORITY - Blocking Features

### 1. Test MAF Tracks with Real Data
**Status:** ⏳ Waiting for real data  
**Blocker:** Test file has BED12+13 format but may not match plugin expectations

**What's needed:**
- Get real MAF alignment file in BED12+13 format
- Or convert existing MAF file to proper BED12+13
- Test in JBrowse2 browser to verify display

**What was fixed:**
- ✅ Missing samples array (critical bug)
- ✅ BED format parser added
- ✅ Sample auto-detection working

**Files to review:**
- `/data/moop/MAF_TRACK_FIX_SUMMARY.md` - Complete implementation
- `/data/moop/docs/JBrowse2/reference/MAF_TRACKS.md` - Format reference

**Test regions:**
- chr1:1000-5060
- chr2:10000-10070
- chr3:20000-20055

---

### 2. Create Dual-Assembly Frontend UI
**Status:** ✅ Backend complete, ⏳ Frontend needed  
**API:** Working and tested

**What exists:**
- ✅ Dynamic config API endpoint
- ✅ Assembly name parser
- ✅ Synteny track loader
- ✅ Access control
- ✅ Tested with 2 assemblies

**What's needed:**
- Create `/data/moop/jbrowse2-synteny.php`
- Assembly selector UI (dropdown or autocomplete)
- Load JBrowse2 with LinearSyntenyView
- Test in browser

**API endpoint:**
```
GET /moop/api/jbrowse2/config.php?assembly1=Org1_Asm1&assembly2=Org2_Asm2
```

**Files to review:**
- `/data/moop/SYNTENY_MIGRATION_PLAN.md` - Implementation complete
- `/data/moop/DUAL_ASSEMBLY_SYSTEM_REVIEW.md` - Architecture

**Test assemblies:**
- Nematostella_vectensis_GCA_033964005.1
- Anoura_caudifer_GCA_004027475.1

---

## 🟡 MEDIUM PRIORITY - Nice to Have

### 3. Generate Test Synteny Tracks
**Status:** ⏳ System ready, need data  
**Purpose:** Test dual-assembly view with real tracks

**Tools available:**
- `tools/jbrowse/generate_synteny_tracks_from_sheet.php`
- PAFTrack, PIFTrack, MCScanTrack handlers exist

**What's needed:**
- Create test PAF/PIF/MCScan file
- Create Google Sheet with dual-assembly format
- Generate synteny track
- Test loading in dual-assembly view

**Google Sheet format:**
```
track_id | name | track_path | organism1 | assembly1 | organism2 | assembly2
```

---

### 4. Document MAF BED12+13 Format
**Status:** ⏳ Needs clarification  
**Issue:** Multiple possible formats for MAF data

**What's unclear:**
- Exact BED12+13 format plugin expects
- Whether we need BigBed (.bb) index
- Column 13 encoding format

**Action:**
- Test with real file OR
- Check jbrowse-plugin-mafviewer source code

---

## 🟢 LOW PRIORITY - Future Enhancements

### 5. Add "Compare Assemblies" to Navigation
**Status:** ⏳ After frontend UI works

Add button/link to jbrowse2.php that opens synteny comparison.

---

### 6. Consolidate Root Documentation
**Status:** ⏳ After review

Move today's docs from root to `docs/JBrowse2/implementation/`:
- Combine 3 MAF docs → `implementation/MAF_TRACKS.md`
- Combine 3 synteny docs → `implementation/DUAL_ASSEMBLY_SYNTENY.md`

---

### 7. Update DEVELOPER_GUIDE.md
**Status:** ⏳ When settled

Add sections for:
- Dual-assembly system architecture
- MAF track implementation
- Synteny track generation

---

## ✅ COMPLETED (This Session)

- ✅ Fix MAF track samples array bug
- ✅ Add BED format parser for MAF tracks
- ✅ Improve track generator output
- ✅ Fix trackExists() to include 'bed' type
- ✅ Build dual-assembly dynamic config system
- ✅ Test dual-assembly API with 2 assemblies
- ✅ Create shared config_functions.php
- ✅ Create comprehensive documentation

---

## Quick Start for Tomorrow

**To work on MAF tracks:**
1. Read `/data/moop/MAF_TRACK_FIX_SUMMARY.md`
2. Get real BED12+13 MAF file
3. Test in browser at chr1:1000-5060

**To work on dual-assembly UI:**
1. Read `/data/moop/SYNTENY_MIGRATION_PLAN.md`
2. Copy `jbrowse2.php` to `jbrowse2-synteny.php`
3. Add assembly selector
4. Test with Nematostella + Anoura

**To generate synteny tracks:**
1. Create Google Sheet with dual-assembly format
2. Run: `php tools/jbrowse/generate_synteny_tracks_from_sheet.php SHEET_ID --gid GID`
3. Check files created in `metadata/jbrowse2-configs/tracks/synteny/`
