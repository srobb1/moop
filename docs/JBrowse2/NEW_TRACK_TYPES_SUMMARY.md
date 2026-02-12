# New Track Types Implementation Summary

## ✅ Completed

All new track types have been added to the MOOP JBrowse2 integration system.

---

## 🎯 Track Types Added

### 1. CRAM Tracks
- **File:** `add_cram_track.sh`
- **Extension:** `.cram` + `.crai` index
- **Use:** More space-efficient than BAM (30-60% smaller)
- **Adapter:** `CramAdapter`

### 2. PAF Tracks
- **File:** `add_paf_track.sh`
- **Extension:** `.paf`
- **Use:** Long-read alignments (minimap2, PacBio, Nanopore)
- **Adapter:** `PAFAdapter`

### 3. GTF Tracks
- **File:** `add_gtf_track.sh`
- **Extension:** `.gtf`
- **Use:** Gene annotations (Ensembl format)
- **Adapter:** `GtfAdapter`
- **Features:** Auto text-search indexing

### 4. BED Tracks
- **File:** `add_bed_track.sh`
- **Extension:** `.bed.gz` + `.tbi` index
- **Use:** Generic genomic features
- **Adapter:** `BedTabixAdapter`

### 5. Synteny Tracks (PIF.GZ)
- **File:** `add_synteny_track.sh`
- **Extension:** `.pif.gz` + `.tbi` index
- **Use:** Whole genome synteny between two assemblies
- **Adapter:** `PairwiseIndexedPAFAdapter`
- **Special:** Requires `ASSEMBLY1` and `ASSEMBLY2` columns

### 6. Synteny Tracks (MCScan)
- **File:** `add_mcscan_track.sh`
- **Extension:** `.anchors` + 2 `.bed` files
- **Use:** Orthologous gene pairs between genomes
- **Adapter:** `MCScanAnchorsAdapter`
- **Special:** Requires `ASSEMBLY1`, `ASSEMBLY2`, `BED1_PATH`, `BED2_PATH`

---

## 📝 Scripts Created

### Track Addition Scripts

All scripts follow the same pattern and accept both local files and remote URLs:

```bash
/data/moop/tools/jbrowse/
├── add_cram_track.sh       # CRAM alignments
├── add_paf_track.sh        # PAF long-read alignments
├── add_gtf_track.sh        # GTF annotations
├── add_bed_track.sh        # BED features
├── add_synteny_track.sh    # PIF.GZ synteny
└── add_mcscan_track.sh     # MCScan synteny
```

All scripts are executable (`chmod +x` applied).

---

## 🔄 Integration Updates

### Python Script Updates

`generate_tracks_from_sheet.py` has been updated with:

1. **Extended `determine_track_type()`:**
   - Detects all new file extensions
   - Special handling for synteny tracks (checks for ASSEMBLY1/ASSEMBLY2)

2. **Updated `generate_single_track()`:**
   - Handlers for CRAM, PAF, GTF, BED
   - Proper argument passing to bash scripts

3. **New `generate_synteny_track()`:**
   - Dedicated function for synteny tracks
   - Validates both assemblies exist
   - Handles PIF.GZ and MCScan formats
   - Validates required BED files for MCScan

4. **Updated main processing:**
   - Separates synteny tracks from regular tracks
   - Processes synteny tracks with proper assembly handling
   - Reports statistics for each track type

---

## 📚 Documentation Created

### 1. SUPPORTED_FORMATS.md
Complete reference of ALL JBrowse2 formats:
- 50+ adapter types documented
- Priority recommendations for MOOP
- Extension points for future formats
- Decision guide for format selection

### 2. SYNTENY_TRACKS_GUIDE.md
Comprehensive guide for synteny tracks:
- Google Sheet column format
- Both PIF.GZ and MCScan workflows
- File generation commands
- Troubleshooting guide
- Integration with your original scripts

### 3. Updated CODE_REVIEW
- New track types added to priority list
- CRAM and PAF moved to High Priority
- Clear extension points documented

### 4. Updated ADDING_NEW_TRACK_TYPES.md
- Step-by-step for adding more formats
- Examples for common formats

---

## 📊 Google Sheet Format

### Regular Tracks

```csv
track_id,name,category,TRACK_PATH,ACCESS_LEVEL
sample_cram,Sample CRAM,Alignments,/data/tracks/sample.cram,public
sample_gtf,Genes GTF,Annotations,/data/tracks/genes.gtf,public
nanopore,Nanopore Reads,Long-read,/data/tracks/reads.paf,public
peaks,ChIP Peaks,Features,/data/tracks/peaks.bed.gz,public
```

### Synteny Tracks

```csv
track_id,name,category,ASSEMBLY1,ASSEMBLY2,TRACK_PATH,BED1_PATH,BED2_PATH,ACCESS_LEVEL
nvec_synteny,Nvec Synteny,Synteny,Nvec200,Nvec100,/data/synteny/genomes.pif.gz,,,public
nvec_orthologs,Nvec Orthologs,Synteny,Nvec200,Nvec100,/data/synteny/genes.anchors,/data/synteny/nvec200.bed,/data/synteny/nvec100.bed,public
```

**Key points:**
- Synteny tracks REQUIRE `ASSEMBLY1` and `ASSEMBLY2` columns
- MCScan tracks REQUIRE `BED1_PATH` and `BED2_PATH` columns
- PIF.GZ tracks only need `TRACK_PATH`
- Both assemblies must already be loaded in JBrowse2

---

## 🔍 Track Type Detection

The system auto-detects track type from file extension:

| Extension | Track Type | Handler Script |
|-----------|------------|----------------|
| `.bw`, `.bigwig` | BigWig | `add_bigwig_track.sh` |
| `.bam` | BAM | `add_bam_track.sh` |
| `.cram` | CRAM | `add_cram_track.sh` ✨ NEW |
| `.vcf.gz` | VCF | `add_vcf_track.sh` |
| `.gff.gz`, `.gff3.gz` | GFF | `add_gff_track.sh` |
| `.gtf` | GTF | `add_gtf_track.sh` ✨ NEW |
| `.bed.gz` | BED | `add_bed_track.sh` ✨ NEW |
| `.paf` | PAF | `add_paf_track.sh` ✨ NEW |
| `.pif.gz` + assemblies | Synteny | `add_synteny_track.sh` ✨ NEW |
| `.anchors` + assemblies | MCScan | `add_mcscan_track.sh` ✨ NEW |

---

## 🧪 Testing

### Quick Test Commands

```bash
# Test CRAM track
/data/moop/tools/jbrowse/add_cram_track.sh \
  -a Nvec200 -t test_cram -n "Test CRAM" \
  -f /path/to/sample.cram

# Test PAF track
/data/moop/tools/jbrowse/add_paf_track.sh \
  -a Nvec200 -t test_paf -n "Test PAF" \
  -f /path/to/reads.paf

# Test GTF track
/data/moop/tools/jbrowse/add_gtf_track.sh \
  -a Nvec200 -t test_gtf -n "Test GTF" \
  -f /path/to/genes.gtf -i

# Test BED track
/data/moop/tools/jbrowse/add_bed_track.sh \
  -a Nvec200 -t test_bed -n "Test BED" \
  -f /path/to/features.bed.gz

# Test PIF.GZ synteny
/data/moop/tools/jbrowse/add_synteny_track.sh \
  -1 Nvec200 -2 Nvec100 -t test_syn -n "Test Synteny" \
  -f /path/to/genomes.pif.gz

# Test MCScan synteny
/data/moop/tools/jbrowse/add_mcscan_track.sh \
  -1 Nvec200 -2 Nvec100 -t test_mcscan -n "Test MCScan" \
  -f /path/to/genes.anchors \
  -b /path/to/nvec200.bed \
  -b /path/to/nvec100.bed
```

### Test with Google Sheet

```bash
# Create test Google Sheet with sample tracks
# Add rows for each new track type
# Run automation:

python3 /data/moop/tools/jbrowse/generate_tracks_from_sheet.py \
  --sheet-url "https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit" \
  --organism Nematostella_vectensis \
  --assembly GCA_033964005.1 \
  --dry-run
```

---

## 🎨 Color System

The existing color system works for all track types:

- **Color groups:** blues, reds, greens, purples, oranges, cyans, pinks, browns, grays, diffs, etc.
- **20+ color groups** with 6-17 colors each
- **Automatic color assignment** for combo tracks
- **Manual override:** Use `exact=ColorName` or `blues3` syntax
- **Error messages:** Clear warnings when group has too few colors

---

## 🔐 Access Levels

All track types support access level control:

- `public` - Anyone can view
- `ip_range` - Restricted to IP ranges
- `admin` - Admin users only

Set via `ACCESS_LEVEL` column in Google Sheet.

---

## 🚀 Usage Workflow

### 1. Prepare Your Data

```bash
# CRAM
samtools view -C -T reference.fa input.bam -o output.cram
samtools index output.cram

# PAF
minimap2 -x map-ont reference.fa reads.fastq > alignments.paf

# GTF
# (Usually downloaded from Ensembl/NCBI)

# BED
sort -k1,1 -k2,2n features.bed | bgzip > features.bed.gz
tabix -p bed features.bed.gz

# PIF.GZ (from PAF)
sort -k6,6 -k8,8n alignment.paf | bgzip > genomes.pif.gz
tabix -p bed genomes.pif.gz

# MCScan
python -m jcvi.compara.catalog ortholog genome1 genome2
```

### 2. Add Rows to Google Sheet

Open your Google Sheet and add rows following the format in `SYNTENY_TRACKS_GUIDE.md`.

### 3. Run Automation Script

```bash
cd /data/moop
python3 tools/jbrowse/generate_tracks_from_sheet.py \
  --sheet-url "YOUR_GOOGLE_SHEET_URL" \
  --organism Nematostella_vectensis \
  --assembly GCA_033964005.1
```

### 4. Verify in JBrowse2

Open JBrowse2 and check that tracks appear in the track selector.

---

## 📖 Documentation Files

All documentation is in `/data/moop/docs/JBrowse2/`:

1. **SUPPORTED_FORMATS.md** - Complete format reference
2. **SYNTENY_TRACKS_GUIDE.md** - Synteny-specific guide
3. **GOOGLE_SHEETS_AUTOMATION.md** - Main automation guide
4. **CODE_REVIEW_generate_tracks_from_sheet.md** - Code review
5. **ADDING_NEW_TRACK_TYPES.md** - Extension guide

---

## 🎯 Next Steps

### Immediate

1. Test each new track type with sample data
2. Add tracks to your Nematostella example
3. Update your Google Sheet with new track types

### Future Enhancements

Consider adding these formats (from SUPPORTED_FORMATS.md):

- **BigBed** (`.bb`) - Efficient for large BED files
- **BedGraph** (`.bedgraph`) - Text-based signal data
- **HiC** (`.hic`) - Chromatin interaction data
- **Chain** (`.chain`) - UCSC liftover chains

All infrastructure is in place - just add detection + handler script.

---

## ✅ Checklist

- [x] CRAM track support added
- [x] PAF track support added
- [x] GTF track support added
- [x] BED track support added
- [x] PIF.GZ synteny support added
- [x] MCScan synteny support added
- [x] All scripts created and made executable
- [x] Python automation script updated
- [x] Synteny track handling implemented
- [x] Documentation created
- [x] Google Sheet format documented
- [x] Extension points documented

---

## 🔧 Maintenance

To add more track types in the future:

1. **Create bash script:** `tools/jbrowse/add_XXX_track.sh`
   - Follow existing script patterns
   - Use appropriate JBrowse2 adapter

2. **Update Python script:** `tools/jbrowse/generate_tracks_from_sheet.py`
   - Add extension to `determine_track_type()`
   - Add handler in `generate_single_track()`

3. **Update documentation:** `docs/JBrowse2/SUPPORTED_FORMATS.md`

4. **Test with sample data**

---

## 📞 References

- **JBrowse2 Config Guide:** https://jbrowse.org/jb2/docs/config_guide/
- **Adapter Types:** https://jbrowse.org/jb2/docs/config_guide/#adapter-types
- **Your original scripts:** Referenced in `SYNTENY_TRACKS_GUIDE.md`

---

**Summary:** All requested track types (CRAM, PAF, GTF, BED, synteny) are now fully implemented and integrated into the Google Sheets automation system. The infrastructure supports easy addition of more formats in the future.
