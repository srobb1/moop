# Walkthrough: Google Sheets Automation for JBrowse2

**Date:** February 11, 2026  
**Purpose:** Automate JBrowse2 track creation from Google Sheets metadata  
**Example:** Nematostella vectensis GCA_033964005.1

---

## Overview

This walkthrough demonstrates the **Google Sheets automation system** that allows you to:

1. Define all track metadata in a Google Sheet
2. Auto-generate track configurations with a single command
3. Support multiple track types (BigWig, BAM, GFF, VCF, synteny, etc.)
4. Use color grouping for multi-track visualizations
5. Control access levels (PUBLIC, COLLABORATOR, ADMIN)

**Key Benefit:** Update your sheet → run one command → all tracks are created with proper configs!

---

## Prerequisites

### 1. Python Dependencies

The automation script requires no external Python packages (uses only standard library).

```bash
# Verify Python 3 is available
python3 --version  # Should be 3.6+
```

### 2. Example Google Sheet

We'll use this pre-configured sheet:
https://docs.google.com/spreadsheets/d/1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo/edit?gid=1977809640

### 3. Data Files

Ensure your track files exist at the paths listed in `TRACK_PATH` column:

```bash
# Check that example data exists
ls -lh /var/www/html/moop/data/tracks/bigwig/
ls -lh /var/www/html/moop/data/tracks/bam/
```

---

## Step 1: Understand the Google Sheet Format

### Required Columns

| Column | Description | Example |
|--------|-------------|---------|
| `track_id` | Unique identifier (used as trackId) | `nvec-rnaseq-s1-pos` |
| `name` | Display name in JBrowse2 | `S1 Body Wall RNA-seq (positive strand)` |
| `category` | Organizational category | `Gene Expression` |
| `TRACK_PATH` | File path or URL | `/data/moop/data/tracks/bigwig/S1.pos.bw` |

### Optional Columns

- `access_level`: PUBLIC, COLLABORATOR, or ADMIN (default: PUBLIC)
- `description`: Track description
- `technique`: Experimental technique (RNA-seq, ChIP-seq, etc.)
- `condition`: Experimental condition
- `tissue`: Tissue/organ type
- Any custom columns for your metadata
- `#column_name`: Columns starting with # are ignored

### Synteny Track Columns

For synteny tracks (genome comparisons), add:
- `ASSEMBLY1`: First genome name (target)
- `ASSEMBLY2`: Second genome name (query)
- `BED1_PATH`: BED file for genome 1 (MCScan only)
- `BED2_PATH`: BED file for genome 2 (MCScan only)

### Multi-BigWig (Combo) Tracks

Group related tracks with special markers:

```
# Combo Track Display Name
## colorgroup: Group 1 Name
track_id	name	category	TRACK_PATH
sample1	Sample 1	Expression	/path/to/sample1.bw
sample2	Sample 2	Expression	/path/to/sample2.bw
## blues: Group 2 Name
sample3	Sample 3	Expression	/path/to/sample3.bw
sample4	Sample 4	Expression	/path/to/sample4.bw
### end
```

- Each track is created individually **AND** as part of the combo track
- Color groups: `blues`, `reds`, `purples`, `rainbow`, etc. (see `--list-colors`)
- Use `exact=ColorName` for specific colors
- Use `blues3` to pick the 4th blue (0-indexed)

---

## Step 2: Explore Color Groups

The script has extensive color palette support:

```bash
cd /data/moop/tools/jbrowse

# List all available color groups
python3 generate_tracks_from_sheet.py --list-colors

# Get suggestions for 8 files
python3 generate_tracks_from_sheet.py --suggest-colors 8
```

**Popular color groups:**
- `blues` (11 colors) - Good for replicates
- `rainbow` (20 colors) - Maximum variety
- `monoblues` (9 colors) - Intensity gradients
- `pastels` (16 colors) - Subtle differences
- `vibrant` (16 colors) - High contrast

**Error handling:** If a color group has too few colors, the script will:
1. Show clear error message
2. Suggest suitable alternatives
3. Continue with fallback color

---

## Step 3: Test with Dry Run

Always test first to see what would be created:

```bash
cd /data/moop/tools/jbrowse

# Dry run - shows what would happen without making changes
python3 generate_tracks_from_sheet.py \
    "1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo" \
    --gid 1977809640 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --dry-run
```

**What it checks:**
- ✓ Downloads and parses Google Sheet
- ✓ Validates required columns
- ✓ Checks file extensions for track types
- ✓ Shows which tracks exist vs. need creation
- ✓ Identifies any issues (missing files, invalid paths)
- ✗ **Does NOT** create any track files

---

## Step 4: Generate Tracks

Once the dry run looks good, create the tracks:

```bash
# Generate all tracks from the sheet
python3 generate_tracks_from_sheet.py \
    "1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo" \
    --gid 1977809640 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --regenerate
```

**The `--regenerate` flag:**
- Automatically runs `generate-jbrowse-configs.php` after track creation
- Merges all track metadata into final JBrowse2 config
- Required for tracks to appear in browser

**Expected output:**
```
==================================================================
Google Sheets to JBrowse2 Track Generator
==================================================================
Sheet ID: 1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo
Organism: Nematostella_vectensis
Assembly: GCA_033964005.1
==================================================================

Downloading sheet from: https://docs.google.com/...
Found 10 regular tracks
Found 2 combo tracks

Processing regular tracks...
→ Creating bigwig track (local): S1 Body Wall Positive
  ✓ Created: S1 Body Wall Positive
→ Creating bigwig track (local): S1 Body Wall Negative
  ✓ Created: S1 Body Wall Negative
...
Regular tracks: 10/10 created

Processing combo tracks...
→ Creating multi-BigWig track: RNA-seq Body Wall
  ✓ Created combo track: RNA-seq Body Wall
Combo tracks: 2/2 created

Regenerating JBrowse2 configs...
✓ Configs regenerated

==================================================================
SUMMARY
==================================================================
Regular tracks: 10/10 created
Combo tracks: 2/2 created

✓ All tracks processed successfully!
==================================================================
```

---

## Step 5: Verify Tracks in JBrowse2

1. **Open JBrowse2:**
   ```
   http://localhost/moop/jbrowse2.php
   ```

2. **Select organism:**
   - Choose "Nematostella vectensis"
   - Choose assembly "GCA_033964005.1"

3. **Check tracks appear:**
   - Open track selector
   - Look for your categories (Gene Expression, Alignments, etc.)
   - Verify individual tracks and combo tracks

4. **Test track display:**
   - Add a track to view
   - Navigate to a gene region
   - Verify data loads correctly

---

## Step 6: Update Tracks

When you need to add/modify tracks:

1. **Edit Google Sheet:**
   - Add new rows for new tracks
   - Update existing rows
   - Change colors, names, categories, etc.

2. **Re-run the script:**
   ```bash
   python3 generate_tracks_from_sheet.py \
       "YOUR_SHEET_ID" \
       --gid 1977809640 \
       --organism Nematostella_vectensis \
       --assembly GCA_033964005.1 \
       --regenerate
   ```

3. **The script automatically:**
   - Skips tracks that already exist
   - Only creates new/changed tracks
   - Updates combo tracks if membership changed

---

## Supported Track Types

The script auto-detects track type from file extension:

| Extension | Track Type | JBrowse2 Adapter | Index Required |
|-----------|------------|------------------|----------------|
| `.bw`, `.bigwig` | BigWig | BigWigAdapter | No |
| `.bam` | BAM alignment | BamAdapter | `.bai` |
| `.cram` | CRAM alignment | CramAdapter | `.crai` |
| `.vcf.gz` | VCF variants | VcfTabixAdapter | `.tbi` |
| `.gff`, `.gff3`, `.gff.gz` | GFF annotations | Gff3TabixAdapter | `.tbi` (if .gz) |
| `.gtf` | GTF annotations | GtfAdapter | Optional `.tbi` |
| `.bed.gz` | BED features | BedTabixAdapter | `.tbi` |
| `.paf` | PAF alignments | PAFAdapter | No |
| `.pif.gz` | Synteny (whole genome) | PairwiseIndexedPAFAdapter | `.tbi` |
| `.anchors` | Synteny (MCScan) | MCScanAnchorsAdapter | BED files required |
| `.maf`, `.maf.gz` | Multiple alignment | MAFViewer plugin | No |

**Note:** MAF tracks require the MAFViewer plugin:
```bash
jbrowse add-plugin https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js
```

---

## Advanced Features

### 1. Remote Tracks (HTTP/HTTPS)

Use URLs directly in `TRACK_PATH`:

```
TRACK_PATH
https://data.org/tracks/sample.bw
http://server.edu/data/file.bam
```

### 2. Access Levels

Control who can see tracks:

```
track_id	name	access_level	TRACK_PATH
public-track	Public Data	PUBLIC	/path/to/public.bw
collab-track	Shared Data	COLLABORATOR	/path/to/shared.bw
admin-track	Admin Only	ADMIN	/path/to/admin.bw
```

### 3. MAF Multiple Alignments

For MAF files, the script auto-parses sample IDs:

```
track_id	name	category	TRACK_PATH
maf-align	Species Alignment	Conservation	/path/to/alignment.maf.gz
```

The script will:
- Extract species names from MAF file
- Assign rainbow colors automatically
- Create proper MAFViewer config

### 4. Synteny Tracks

**Whole genome synteny (PIF.GZ):**
```
track_id	name	ASSEMBLY1	ASSEMBLY2	TRACK_PATH
nvec-hsap-syn	Nvec vs Human	Nematostella_vectensis	Human_GRCh38	/path/to/nvec_hsap.pif.gz
```

**Gene ortholog synteny (MCScan):**
```
track_id	name	ASSEMBLY1	ASSEMBLY2	TRACK_PATH	BED1_PATH	BED2_PATH
nvec-ortho	Orthologs	Assembly1	Assembly2	/path/to/anchors	/path/to/A1.bed	/path/to/A2.bed
```

---

## Troubleshooting

### Issue: "File not found"

**Problem:** Track file doesn't exist at specified path

**Solution:**
```bash
# Check TRACK_PATH in sheet points to correct location
ls -lh /path/from/TRACK_PATH

# Use absolute paths or ensure relative paths are correct
```

### Issue: "Unknown track type"

**Problem:** File extension not recognized

**Solution:**
- Check file has correct extension (`.bw` not `.bigwig.txt`)
- See supported extensions above
- Add new track type support if needed (see script comments)

### Issue: "Color group too small"

**Problem:** More tracks in group than colors available

**Solution:**
```bash
# See color group suggestions
python3 generate_tracks_from_sheet.py --suggest-colors 15

# Use a larger color group in sheet:
## rainbow: Large Group Name
```

### Issue: "Track exists but not visible"

**Problem:** Track created but doesn't appear in JBrowse2

**Solution:**
```bash
# Regenerate configs manually
cd /data/moop
php tools/jbrowse/generate-jbrowse-configs.php

# Check metadata file exists
ls -lh metadata/jbrowse2-configs/tracks/YOUR_TRACK_ID.json
```

### Issue: "Combo track not working"

**Problem:** Multi-BigWig track doesn't display

**Solution:**
- Verify all individual tracks exist first
- Check combo track markers in sheet (`#`, `##`, `###`)
- Ensure all files are BigWig format (combo only works with BigWig)

---

## Best Practices

### 1. Use Descriptive track_ids
```
✓ Good: nvec-rnaseq-bodywall-s1-pos
✗ Bad: track1
```

### 2. Organize with Categories
```
Gene Expression
- RNA-seq
- Small RNA

Epigenetics
- ChIP-seq
- ATAC-seq

Alignments
- DNA-seq reads
- RNA-seq reads
```

### 3. Test with Dry Run First
Always use `--dry-run` before making changes to catch errors early.

### 4. Use Version Control for Sheets
Make copies of your Google Sheet before major changes:
`File → Make a copy → Name with date`

### 5. Document Custom Columns
Add notes in your sheet about what custom columns mean.

---

## Script Reference

### Basic Usage
```bash
python3 generate_tracks_from_sheet.py SHEET_ID \
    --organism ORGANISM_NAME \
    --assembly ASSEMBLY_ID \
    [OPTIONS]
```

### Options

| Option | Description |
|--------|-------------|
| `--gid GID` | Sheet GID (default: 0) |
| `--moop-root PATH` | MOOP root directory (default: /data/moop) |
| `--dry-run` | Show what would be created without doing it |
| `--regenerate` | Run config generator after creating tracks |
| `--list-colors` | Show all available color groups |
| `--suggest-colors N` | Suggest color groups for N files |

### Examples

```bash
# List color options
python3 generate_tracks_from_sheet.py --list-colors

# Get color suggestions for 12 tracks
python3 generate_tracks_from_sheet.py --suggest-colors 12

# Dry run
python3 generate_tracks_from_sheet.py "SHEET_ID" \
    --organism Mouse --assembly mm10 --dry-run

# Full run with config regeneration
python3 generate_tracks_from_sheet.py "SHEET_ID" \
    --organism Mouse --assembly mm10 --regenerate

# Use specific sheet tab (GID)
python3 generate_tracks_from_sheet.py "SHEET_ID" \
    --gid 123456789 \
    --organism Human --assembly GRCh38 --regenerate
```

---

## Next Steps

1. **Create your own Google Sheet** using the example as template
2. **Add your track metadata** with proper paths
3. **Test with dry run** to verify everything is correct
4. **Generate tracks** and view in JBrowse2
5. **Iterate** - update sheet and re-run as needed

For more information:
- See `tools/jbrowse/generate_tracks_from_sheet.py` source code
- Read inline comments for extension points
- Check `docs/JBrowse2/` for other walkthroughs

---

**Questions or Issues?**

The script has extensive comments explaining how to add new track types or customize behavior. Check the source code for details!
