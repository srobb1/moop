# Shell Script Audit - JBrowse Tools

**Date:** 2026-02-12 21:21 UTC  
**Purpose:** Track which shell scripts have PHP replacements

## Status Summary

| Status | Count | Description |
|--------|-------|-------------|
| ✅ Replaced | 3 | PHP replacement exists and tested |
| 🔄 Partial | 1 | Combo track has PHP replacement |
| ⏳ Needed | 8 | Need PHP replacements |
| 📦 Archived | 3 | In archived_shell_scripts/ |
| ℹ️ Utility | 5 | Admin/setup scripts (lower priority) |

**Total:** 20 shell scripts

---

## ✅ Replaced - PHP Exists (3)

### add_bigwig_track.sh → BigWigTrack.php
- **Status:** ✅ COMPLETE
- **PHP Class:** `lib/JBrowse/TrackTypes/BigWigTrack.php`
- **Location:** `archived_shell_scripts/add_bigwig_track.sh`
- **Tested:** Yes (28 test tracks)

### add_bam_track.sh → BamTrack.php
- **Status:** ✅ COMPLETE
- **PHP Class:** `lib/JBrowse/TrackTypes/BamTrack.php`
- **Location:** `archived_shell_scripts/add_bam_track.sh`
- **Tested:** Yes (1 test track)

### add_vcf_track.sh → VCFTrack.php
- **Status:** ✅ COMPLETE (2026-02-12)
- **PHP Class:** `lib/JBrowse/TrackTypes/VCFTrack.php`
- **Shell Script:** `tools/jbrowse/add_vcf_track.sh` (can be archived)
- **Tested:** Yes (test_variants track)
- **Features:** Validates VCF.gz + .tbi, extracts variant/sample counts, supports remote files

---

## 🔄 Partial Replacement (1)

### add_multi_bigwig_track.sh → ComboTrack.php
- **Status:** 🔄 PHP replacement exists, shell script kept for compatibility
- **PHP Class:** `lib/JBrowse/TrackTypes/ComboTrack.php`
- **Shell Script:** `tools/jbrowse/add_multi_bigwig_track.sh` (still present)
- **Note:** Made portable with ConfigManager integration
- **Action:** Can archive once all workflows use PHP version

---

## ⏳ PHP Replacement Needed (8)

### Track Type Scripts (7)

1. **add_gff_track.sh**
   - **Priority:** Medium (already have AutoTrack for AUTO GFF)
   - **Users:** Custom annotations
   - **Effort:** 1 hour
   - **PHP Class:** GFFTrack.php (to be created)
   - **Note:** AutoTrack.php handles AUTO case

2. **add_gtf_track.sh**
   - **Priority:** Low-Medium
   - **Users:** Transcriptome annotations
   - **Effort:** 1 hour (similar to GFF)
   - **PHP Class:** GTFTrack.php (to be created)

3. **add_cram_track.sh**
   - **Priority:** Low
   - **Users:** Compressed alignment data
   - **Effort:** 1 hour (similar to BAM)
   - **PHP Class:** CRAMTrack.php (to be created)

4. **add_paf_track.sh**
   - **Priority:** Low
   - **Users:** Pairwise alignment format
   - **Effort:** 1-2 hours
   - **PHP Class:** PAFTrack.php (to be created)

5. **add_maf_track.sh**
   - **Priority:** Low
   - **Users:** Multiple alignment format
   - **Effort:** 1-2 hours
   - **PHP Class:** MAFTrack.php (to be created)

6. **add_bed_track.sh**
   - **Priority:** Medium
   - **Users:** Feature annotations
   - **Effort:** 1 hour
   - **PHP Class:** BEDTrack.php (to be created)

7. **add_synteny_track.sh**
   - **Priority:** Low
   - **Users:** Comparative genomics
   - **Effort:** 2-3 hours (complex format)
   - **PHP Class:** SyntenyTrack.php (to be created)

### Generic Script (1)

9. **add_track.sh**
   - **Purpose:** Generic track addition wrapper
   - **Action:** Review if still used, may be obsolete
   - **Replacement:** TrackGenerator.php handles dispatching

---

## ℹ️ Utility/Setup Scripts (5)

These are admin/setup scripts with lower priority for replacement:

### 1. add_assembly_to_jbrowse.sh
- **Purpose:** Add assembly to JBrowse config
- **Status:** Setup script, manual use
- **PHP Equivalent:** AutoTrack.php creates assembly definitions
- **Priority:** Low (works fine as-is)

### 2. bulk_load_assemblies.sh
- **Purpose:** Bulk load multiple assemblies
- **Status:** Batch processing script
- **Note:** Uses setup_jbrowse_assembly.sh
- **Priority:** Low (admin tool)

### 3. setup_jbrowse_assembly.sh
- **Purpose:** Initialize assembly directory structure
- **Status:** One-time setup per assembly
- **Priority:** Low (setup tool)

### 4. index_default_annotations.sh
- **Purpose:** Index annotation files
- **Status:** Preprocessing script
- **Priority:** Low (runs once)

### 5. integrate_nematostella.sh
- **Purpose:** Organism-specific integration
- **Status:** One-off migration script
- **Note:** May be obsolete
- **Priority:** Very Low (review if needed)

---

## 📦 Archived (3)

### 1. archived_shell_scripts/add_bam_track.sh
- Replaced by BamTrack.php

### 2. archived_shell_scripts/add_bigwig_track.sh
- Replaced by BigWigTrack.php

### 3. archived_shell_scripts/remove_jbrowse_data.sh
- Purpose unclear, archived during cleanup

---

## 🎯 Specialized Scripts (2)

### 1. add_mcscan_track.sh
- **Purpose:** MCScan synteny visualization
- **Status:** Specialized tool
- **Priority:** Low (niche use case)
- **Action:** Keep as-is unless issues arise

### 2. setup-remote-tracks-server.sh
- **Purpose:** Configure remote track server
- **Status:** Infrastructure setup
- **Priority:** Low (one-time setup)

---

## Recommended Action Plan

### Phase 2C: Essential Track Types (4-6 hours)
Implement based on user demand:
1. VCFTrack.php (variants)
2. GFFTrack.php (custom annotations)
3. GTFTrack.php (transcriptome)
4. BEDTrack.php (features)

### Phase 2D: Advanced Track Types (4-6 hours)
If/when needed:
5. CRAMTrack.php
6. PAFTrack.php
7. MAFTrack.php
8. SyntenyTrack.php

### Phase 3: Archive Multi-BigWig Shell Script
- Move `add_multi_bigwig_track.sh` to archived_shell_scripts/
- Verify all workflows use ComboTrack.php

### Lower Priority: Utility Scripts
- Review `add_track.sh` - may be obsolete
- Review `integrate_nematostella.sh` - may be obsolete
- Keep setup scripts as-is (work fine, rarely used)

---

## Template for Creating New Track Types

```php
<?php
// lib/JBrowse/TrackTypes/VCFTrack.php
require_once __DIR__ . '/TrackTypeInterface.php';

class VCFTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    public function __construct($pathResolver, $config) {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    public function getValidExtensions() {
        return ['.vcf', '.vcf.gz', '.bcf'];
    }
    
    public function validate($trackData) {
        // Validate track data
        return ['valid' => true, 'errors' => []];
    }
    
    public function generate($trackData, $organism, $assembly, $options = []) {
        // 1. Validate track data
        // 2. Check if file exists
        // 3. Build track configuration JSON
        // 4. Write metadata file
        // 5. Return success/failure
    }
}
```

Then register in `TrackGenerator.php`:
```php
$this->trackTypes['vcf'] = new VCFTrack($this->pathResolver, $this->config);
```

---

## Decision Matrix

| Script | User Demand | Complexity | Priority | Effort |
|--------|-------------|------------|----------|--------|
| VCF | High | Low | High | 1h |
| GFF | Medium | Low | Medium | 1h |
| GTF | Medium | Low | Medium | 1h |
| BED | Medium | Low | Medium | 1h |
| CRAM | Low | Low | Low | 1h |
| PAF | Low | Medium | Low | 1-2h |
| MAF | Low | Medium | Low | 1-2h |
| Synteny | Low | High | Low | 2-3h |

**Total Estimated Effort:** 10-14 hours for all track types

---

## Quick Command Reference

```bash
# List all shell scripts
find tools/jbrowse/ -name "*.sh" -type f | sort

# Check if script is used in cron
grep -r "script_name.sh" /etc/cron* /var/spool/cron/

# Check if script is called by PHP
grep -r "script_name.sh" --include="*.php" .

# Find last modification
stat tools/jbrowse/script_name.sh

# Check git history
git log --all --follow tools/jbrowse/script_name.sh
```

---

*Last Updated: 2026-02-12 21:21 UTC*  
*Next Review: When implementing Phase 2C or when users request new track types*
