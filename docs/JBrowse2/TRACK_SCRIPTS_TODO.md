# JBrowse2 Track Scripts - Implementation Notes

**Date Created:** February 6, 2026 22:25 UTC  
**Status:** Track addition scripts complete, validation and Google Sheets integration pending

---

## What's Been Completed ✅

### Track Addition Scripts

1. **`add_track.sh`** - Master auto-detection script
   - Detects file type from extension (.bw, .bam, .vcf.gz)
   - Routes to appropriate handler
   - Auto-detects category from filename patterns
   - **Status:** Production ready

2. **`add_bigwig_track.sh`** - BigWig track handler
   - Processes quantitative data (RNA-seq, ChIP-seq)
   - Validates BigWig format (if bigWigInfo available)
   - Creates track metadata JSON
   - **Status:** Production ready

3. **`add_bam_track.sh`** - BAM alignment track handler
   - Processes sequence alignment files
   - Auto-creates .bai index if missing
   - Reports read statistics (total/mapped reads)
   - Validates BAM format with samtools
   - **Status:** Production ready

4. **`add_vcf_track.sh`** - VCF variant track handler
   - Processes variant files (SNPs, indels)
   - Auto-compresses .vcf to .vcf.gz if needed
   - Auto-creates .tbi tabix index
   - Reports variant and sample counts
   - **Status:** Production ready

### Features Implemented

✅ **Auto-detection** - File type from extension  
✅ **Auto-indexing** - Creates necessary indexes (.bai, .tbi)  
✅ **Metadata placeholders** - All Google Sheets fields included  
✅ **File validation** - Format checking before processing  
✅ **Smart naming** - Auto-generates track IDs and names  
✅ **Access control** - Public/Collaborator/ALL levels  
✅ **Category detection** - From filename patterns  

### Metadata Fields Supported

All your requested Google Sheets fields are included as placeholders:
- `technique` - Experimental technique
- `category` - Data category
- `institute` - Institution
- `source` - Data source
- `experiment` - Experiment ID
- `developmental_stage` - Development stage
- `tissue` - Tissue type
- `condition` - Experimental condition
- `summary` - Summary description
- `citation` - Citation
- `project` - Project name
- `accession` - Accession number
- `date` - Date
- `analyst` - Analyst name
- `filename` - Filename (auto-populated)

**Current Status:** Fields stored as empty strings in JSON metadata  
**Future:** Will be populated from Google Sheets automatically

---

## Next Steps (TODO)

### Priority 1: Validation Script (HIGH)

**Script:** `check_assembly.sh`

**Purpose:** Validate assembly files and configuration

**Features Needed:**
- Check all required files exist (FASTA, GFF, indexes)
- Verify file permissions (644 for files, 755 for directories)
- Validate FASTA format
- Validate GFF format
- Check indexes are up to date (not older than source files)
- Verify symlinks are valid
- Check metadata JSON syntax
- Report issues clearly with actionable fixes

**Deliverables:**
```bash
./check_assembly.sh Organism Assembly
# Output:
# ✓ FASTA file exists and is valid
# ✓ GFF file exists and is valid
# ✓ All indexes up to date
# ✗ Track metadata has invalid JSON (file.json)
# ⚠ FASTA index older than source file
```

**Estimated Time:** 1-2 hours

---

### Priority 2: Google Sheets Integration (HIGH)

**Script:** `fetch_metadata_from_sheets.py`

**Purpose:** Pull track metadata from Google Sheets and populate JSON files

**Requirements:**
- Google Sheets API credentials
- Python `google-api-python-client` library
- Sheet ID and range configuration
- Mapping between sheet columns and JSON fields

**Features Needed:**
- Authenticate with Google Sheets API
- Read metadata from specified sheet
- Match tracks by filename
- Update existing track JSON files with metadata
- Cache metadata locally for offline use
- Handle missing/incomplete metadata gracefully
- Dry-run mode to preview changes

**Your Sheet Columns:**
```
technique | category | institute | source | experiment | 
developmental-stage | tissue | condition | summary | citation | 
project | accession | date | analyst | filename
```

**Deliverables:**
```bash
# Setup (one time)
./setup_google_sheets_auth.sh

# Fetch and apply metadata
./fetch_metadata_from_sheets.py --sheet-id "YOUR_SHEET_ID" --apply

# Preview without applying
./fetch_metadata_from_sheets.py --sheet-id "YOUR_SHEET_ID" --dry-run
```

**Estimated Time:** 2-3 hours (including auth setup)

---

### Priority 3: Assembly Removal Script (MEDIUM)

**Script:** `remove_assembly.sh`

**Purpose:** Safely remove or archive an assembly

**Features Needed:**
- Archive rather than delete (safety first)
- Remove assembly metadata JSON
- Remove/archive track files
- Clean up symlinks
- Update logs
- Optional: Soft delete (mark as inactive)
- Confirmation prompt
- Dry-run mode

**Deliverables:**
```bash
# Remove and archive
./remove_assembly.sh Organism Assembly

# Dry run (preview)
./remove_assembly.sh Organism Assembly --dry-run

# Soft delete (mark inactive, don't remove files)
./remove_assembly.sh Organism Assembly --soft-delete
```

**Safety Features:**
- Archive to: `/data/moop/metadata/jbrowse2-configs/archived/YYYY-MM-DD/`
- Create removal log
- Reversible (can restore from archive)

**Estimated Time:** 1-2 hours

---

### Priority 4: Bulk Track Addition (MEDIUM)

**Script:** `bulk_add_tracks.sh`

**Purpose:** Add multiple tracks at once from a directory or list

**Features Needed:**
- Process all tracks in a directory
- Read from CSV/TSV file with metadata
- Parallel processing for speed
- Progress reporting
- Error handling (continue on failure)

**Deliverables:**
```bash
# Add all tracks from directory
./bulk_add_tracks.sh --dir /path/to/tracks/ \
    --organism Organism --assembly Assembly

# From CSV with metadata
./bulk_add_tracks.sh --csv tracks_manifest.csv
```

**CSV Format:**
```csv
file,organism,assembly,name,category,tissue,condition
track1.bw,Org,Asm,Track 1,RNA-seq,liver,control
track2.bam,Org,Asm,Track 2,Alignments,brain,treatment
```

**Estimated Time:** 2-3 hours

---

## Testing Checklist

Before considering scripts production-ready:

### Track Addition Scripts
- [x] BigWig script tested
- [x] BAM script tested
- [x] VCF script tested
- [x] Auto-detection tested
- [ ] Test with your real organism data
- [ ] Test metadata field population
- [ ] Test with remote tracks server

### Validation Script (when created)
- [ ] Test with valid assembly
- [ ] Test with missing files
- [ ] Test with invalid formats
- [ ] Test with outdated indexes
- [ ] Test with broken symlinks

### Google Sheets Integration (when created)
- [ ] Test authentication
- [ ] Test metadata fetch
- [ ] Test metadata application
- [ ] Test with missing sheet data
- [ ] Test dry-run mode

### Assembly Removal (when created)
- [ ] Test archival
- [ ] Test restoration
- [ ] Test soft delete
- [ ] Test dry-run mode

---

## Usage Examples

### Current (Working Now)

```bash
cd /data/moop/tools/jbrowse

# Add a BigWig track (auto-detected)
./add_track.sh /path/to/rnaseq_coverage.bw \
    Anoura_caudifer GCA_004027475.1 \
    --name "RNA-seq Brain Coverage" \
    --tissue "brain" \
    --condition "wildtype" \
    --experiment "EXP001"

# Add a BAM track
./add_track.sh /path/to/alignments.bam \
    Anoura_caudifer GCA_004027475.1 \
    --name "DNA-seq Alignments" \
    --category "Genomics"

# Add a VCF track
./add_track.sh /path/to/variants.vcf.gz \
    Anoura_caudifer GCA_004027475.1 \
    --name "SNPs and Indels"
```

### Future (When Complete)

```bash
# Validate assembly
./check_assembly.sh Anoura_caudifer GCA_004027475.1

# Fetch metadata from Google Sheets
./fetch_metadata_from_sheets.py --sheet-id "YOUR_ID" --apply

# Bulk add tracks
./bulk_add_tracks.sh --csv tracks_list.csv

# Remove assembly
./remove_assembly.sh Old_Organism Old_Assembly --archive
```

---

## File Locations

### Scripts
- `/data/moop/tools/jbrowse/add_track.sh` - Master script
- `/data/moop/tools/jbrowse/add_bigwig_track.sh` - BigWig handler
- `/data/moop/tools/jbrowse/add_bam_track.sh` - BAM handler
- `/data/moop/tools/jbrowse/add_vcf_track.sh` - VCF handler
- `/data/moop/tools/jbrowse/check_assembly.sh` - TODO
- `/data/moop/tools/jbrowse/remove_assembly.sh` - TODO
- `/data/moop/tools/jbrowse/fetch_metadata_from_sheets.py` - TODO

### Track Files
- `/data/moop/data/tracks/bigwig/` - BigWig files
- `/data/moop/data/tracks/bam/` - BAM files
- `/data/moop/data/tracks/vcf/` - VCF files

### Metadata
- `/data/moop/metadata/jbrowse2-configs/tracks/` - Track JSON metadata
- `/data/moop/metadata/jbrowse2-configs/assemblies/` - Assembly JSON metadata

---

## Dependencies

### Currently Required
- `samtools` - BAM file processing (✅ installed)
- `bgzip` - VCF compression (✅ installed)
- `tabix` - VCF indexing (✅ installed)
- `jq` - JSON validation (optional but recommended)

### Future Requirements
- Python 3.6+ with pip
- `google-api-python-client` - For Google Sheets
- `google-auth-httplib2` - For authentication
- `google-auth-oauthlib` - For OAuth flow

Install future dependencies:
```bash
pip3 install --user google-api-python-client google-auth-httplib2 google-auth-oauthlib
```

---

## Notes for Future Development

### Google Sheets Setup

1. **Enable Google Sheets API:**
   - Go to Google Cloud Console
   - Create project
   - Enable Sheets API
   - Create service account
   - Download credentials JSON

2. **Share Sheet:**
   - Share your Google Sheet with service account email
   - Give "Viewer" permissions

3. **Configuration:**
   - Store sheet ID in config file
   - Store credentials JSON securely
   - Map sheet columns to metadata fields

### Metadata Field Mapping

Create mapping file: `/data/moop/config/google_sheets_mapping.json`

```json
{
  "sheet_id": "YOUR_GOOGLE_SHEET_ID",
  "range": "Sheet1!A2:O",
  "column_mapping": {
    "A": "technique",
    "B": "category",
    "C": "institute",
    "D": "source",
    "E": "experiment",
    "F": "developmental_stage",
    "G": "tissue",
    "H": "condition",
    "I": "summary",
    "J": "citation",
    "K": "project",
    "L": "accession",
    "M": "date",
    "N": "analyst",
    "O": "filename"
  }
}
```

---

## Questions to Answer

Before implementing Google Sheets integration:

1. **Where is your Google Sheet?**
   - Sheet ID?
   - Which tab/range?
   
2. **How do you identify tracks?**
   - By filename?
   - By track ID?
   - By experiment ID?

3. **Update frequency?**
   - Manual on-demand?
   - Automatic on track addition?
   - Cron job daily?

4. **Conflict resolution?**
   - Sheet overrides JSON?
   - JSON overrides sheet?
   - Manual merge?

---

## Changelog

- **2026-02-06 22:25** - Created document
- **2026-02-06 22:25** - Track addition scripts completed
- **2026-02-06 22:25** - Next steps defined

---

**Last Updated:** 2026-02-06 22:25 UTC  
**Status:** Track scripts ready, validation and Google Sheets integration next
