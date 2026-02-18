# Complete Google Sheets to JBrowse2 Workflow

**Last Updated:** February 11, 2026  
**Purpose:** End-to-end guide for setting up organisms and tracks from Google Sheets

---

## Overview

The `generate_tracks_from_sheet.py` script now provides a **complete automated workflow** that:

1. ✅ **Checks if organism/assembly exists** in JBrowse2
2. ✅ **Automatically sets up new organisms** if needed
3. ✅ **Configures reference genome and annotations** automatically
4. ✅ **Loads all tracks** from the Google Sheet
5. ✅ **Handles combo tracks** (multi-BigWig)

---

## What Gets Configured Automatically

### Phase 1: Assembly Setup (if new)

When you run the script with a new organism/assembly, it automatically:

1. **Runs `setup_jbrowse_assembly.sh`**
   - Creates `/data/moop/data/genomes/{organism}/{assembly}/`
   - Symlinks `genome.fa` → `reference.fasta`
   - Indexes genome with `samtools faidx`
   - Symlinks `genomic.gff` → `annotations.gff3`
   - Compresses and indexes GFF with `bgzip` and `tabix`

2. **Runs `add_assembly_to_jbrowse.sh`**
   - Creates assembly metadata JSON
   - Configures reference sequence track (automatically)
   - Sets default access level

3. **Annotations Track** (if GFF exists)
   - Automatically added by `assembly.php` API
   - No manual track creation needed

### Phase 2: Track Loading

After assembly is configured, the script processes tracks from the sheet:

- **Regular tracks** (BigWig, BAM, VCF, etc.)
- **Combo tracks** (multi-BigWig for comparing samples)
- **Synteny tracks** (whole genome and MCScan)

---

## Google Sheet Format

### Required Columns

- `track_id` - Unique identifier
- `name` - Display name
- `category` - Track category
- `TRACK_PATH` - File path or AUTO

### Special Track Types

#### AUTO Tracks (Reference & Annotations)

These are **automatically configured** by the assembly setup scripts:

```tsv
track_id          name                 category           TRACK_PATH
reference_seq     Reference sequence   Genome Assembly    AUTO
NV2g_genes        NV2g_genes           Gene Models        AUTO
```

**What happens:**
- `reference_seq` → Configured by `add_assembly_to_jbrowse.sh`
- `NV2g_genes` → Auto-added by `assembly.php` if `annotations.gff3.gz` exists
- Script **skips** these tracks (not an error!)

#### Regular Tracks

```tsv
track_id                           name              category        TRACK_PATH
MOLNG-2707_S1-body-wall.pos.bw    S1 body_wall +    Gene Expression /data/tracks/sample.bw
```

#### Combo Tracks

```tsv
# SIMR:Four_Adult_Tissues
## blues: Body Wall
label	key	category	filename
S1+	sample1_pos	Gene Expression	MOLNG-2707_S1-body-wall.pos.bw
S1-	sample1_neg	Gene Expression	MOLNG-2707_S1-body-wall.neg.bw
### end
```

---

## Usage Examples

### New Organism (Full Setup)

```bash
# 1. Place genome files in organisms directory
mkdir -p /data/moop/organisms/New_organism/Assembly1
cp genome.fa /data/moop/organisms/New_organism/Assembly1/
cp genomic.gff /data/moop/organisms/New_organism/Assembly1/

# 2. Place track files
mkdir -p /data/moop/data/tracks/New_organism/Assembly1/bigwig
cp *.bw /data/moop/data/tracks/New_organism/Assembly1/bigwig/

# 3. Create Google Sheet with tracks (including AUTO for reference/annotations)

# 4. Run script - it will setup assembly AND load tracks
cd /data/moop
php tools/jbrowse/generate_tracks_from_sheet.php \
    "SHEET_ID" \
    --gid 0 \
    --organism New_organism \
    --assembly Assembly1

# 5. Generate configs
php tools/jbrowse/generate-jbrowse-configs.php
```

**Output:**
```
======================================================================
ASSEMBLY SETUP
======================================================================
⚠ Assembly not found: New_organism/Assembly1
→ Running assembly setup scripts...

Phase 1: Preparing genome files...
✓ Genome files prepared

Phase 2: Registering assembly in JBrowse2...
✓ Assembly registered

✓ Assembly setup complete!
======================================================================

Processing regular tracks...
→ Skipping reference_seq: AUTO tracks are configured by assembly setup
→ Skipping NV2g_genes: AUTO tracks are configured by assembly setup
→ Creating bigwig track: Sample 1
✓ Track created: sample1.bw
...
```

### Existing Organism (Track Updates)

```bash
# Assembly already exists, just load new tracks
php tools/jbrowse/generate_tracks_from_sheet.php \
    "SHEET_ID" \
    --gid 0 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1
```

**Output:**
```
======================================================================
ASSEMBLY SETUP
======================================================================
✓ Assembly already configured: Nematostella_vectensis/GCA_033964005.1

Processing regular tracks...
→ Skipping reference_seq: AUTO tracks are configured by assembly setup
→ Skipping NV2g_genes: AUTO tracks are configured by assembly setup
...
```

---

## Prerequisites

### Required Files (New Organism)

**Source data location:**
```
/data/moop/organisms/{Organism_name}/{Assembly_ID}/
├── genome.fa          ← Reference genome (REQUIRED)
└── genomic.gff        ← Annotations (OPTIONAL but recommended)
```

**Track data location:**
```
/data/moop/data/tracks/{Organism_name}/{Assembly_ID}/
├── bam/
│   ├── sample1.bam
│   └── sample1.bam.bai
└── bigwig/
    ├── sample1.pos.bw
    └── sample1.neg.bw
```

### System Requirements

```bash
# Install tools
sudo apt-get install samtools tabix bgzip jq

# Verify
samtools --version
tabix --version
bgzip --version
```

---

## What Gets Created

### Assembly Files (First Run Only)

```
/data/moop/data/genomes/{Organism}/{Assembly}/
├── reference.fasta          ← Symlink to genome.fa
├── reference.fasta.fai      ← Generated by samtools
├── annotations.gff3         ← Symlink to genomic.gff
├── annotations.gff3.gz      ← Generated by bgzip
└── annotations.gff3.gz.tbi  ← Generated by tabix
```

### Metadata

```
/data/moop/metadata/jbrowse2-configs/
├── assemblies/
│   └── {Organism}_{Assembly}.json     ← Assembly definition
└── tracks/
    ├── {Organism}_{Assembly}_track1.json
    ├── {Organism}_{Assembly}_track2.json
    └── ...
```

### Cached Configs

```
/data/moop/jbrowse2/configs/{Organism}_{Assembly}/
├── PUBLIC.json         ← Generated by generate-jbrowse-configs.php
├── COLLABORATOR.json
├── ADMIN.json
└── config.json
```

---

## Workflow Diagram

```
User creates Google Sheet
         ↓
    [Contains:]
    - reference_seq (AUTO)
    - NV2g_genes (AUTO)
    - 25 BigWig tracks
    - 1 combo track
         ↓
python3 generate_tracks_from_sheet.py
         ↓
    [Check Assembly]
         ↓
    Assembly exists? ──NO──→ [Auto Setup]
         │                        ↓
         │                   Run setup_jbrowse_assembly.sh
         │                        ↓
         │                   Run add_assembly_to_jbrowse.sh
         │                        ↓
         │                   [Creates:]
         │                   - Reference sequence track
         │                   - Annotations track (if GFF exists)
         │                        ↓
         YES ←────────────────────┘
         ↓
    [Process Tracks]
         ↓
    Skip AUTO tracks (already configured)
         ↓
    Load 25 BigWig tracks
         ↓
    Load 1 combo track
         ↓
php generate-jbrowse-configs.php
         ↓
    [Generates 4 configs per assembly]
    - PUBLIC.json
    - COLLABORATOR.json
    - ADMIN.json
    - config.json
         ↓
    ✓ Ready in browser!
```

---

## Script Output Explained

### Assembly Already Exists

```
======================================================================
ASSEMBLY SETUP
======================================================================
✓ Assembly already configured: Nematostella_vectensis/GCA_033964005.1
```

This means:
- Assembly metadata exists
- Reference genome configured
- Annotations configured (if GFF exists)
- Ready to load tracks

### New Assembly Setup

```
======================================================================
ASSEMBLY SETUP
======================================================================
⚠ Assembly not found: New_organism/Assembly1
→ Running assembly setup scripts...

Phase 1: Preparing genome files...
[output from setup_jbrowse_assembly.sh]
✓ Genome files prepared

Phase 2: Registering assembly in JBrowse2...
[output from add_assembly_to_jbrowse.sh]
✓ Assembly registered

✓ Assembly setup complete!
```

This means:
- Scripts detected new organism
- Ran full setup automatically
- Reference genome configured
- Annotations configured
- Ready to load tracks

### AUTO Tracks Skipped

```
→ Skipping reference_seq (Reference sequence): AUTO tracks are configured by assembly setup
→ Skipping NV2g_genes (NV2g_genes): AUTO tracks are configured by assembly setup
```

This is **NORMAL** and **CORRECT**:
- Reference sequence added by `add_assembly_to_jbrowse.sh`
- Annotations auto-added by `assembly.php`
- No action needed

### Track Loading

```
→ Creating bigwig track (local): S1 body_wall +
✓ Track created: MOLNG-2707_S1-body-wall.pos.bw
```

Or if already exists:
```
→ Creating bigwig track (local): S1 body_wall +
✓ Track exists: MOLNG-2707_S1-body-wall.pos.bw
```

---

## Troubleshooting

### "Organism directory not found"

```
✗ Organism directory not found: /data/moop/organisms/New_organism/Assembly1
  Please ensure genome files are in place:
  - /data/moop/organisms/New_organism/Assembly1/genome.fa
  - /data/moop/organisms/New_organism/Assembly1/genomic.gff (optional)
```

**Solution:**
```bash
mkdir -p /data/moop/organisms/New_organism/Assembly1
cp genome.fa /data/moop/organisms/New_organism/Assembly1/
cp genomic.gff /data/moop/organisms/New_organism/Assembly1/  # optional
```

### "Failed to setup genome files"

Check:
```bash
# Verify samtools is installed
samtools --version

# Verify bgzip/tabix
bgzip --version
tabix --version

# Check genome file
ls -lh /data/moop/organisms/New_organism/Assembly1/genome.fa
```

### Annotations Not Showing

The annotations track is **automatically added** if:
1. `genomic.gff` exists in organisms directory
2. `setup_jbrowse_assembly.sh` successfully creates `annotations.gff3.gz`

**Check:**
```bash
# Verify GFF was processed
ls -lh /data/moop/data/genomes/New_organism/Assembly1/annotations.gff3.gz*

# Should see:
# annotations.gff3.gz
# annotations.gff3.gz.tbi
```

---

## Complete Example

```bash
# Setup new organism: Amphimedon queenslandica
cd /data/moop

# 1. Prepare files
mkdir -p organisms/Amphimedon_queenslandica/Aqu1
cp ~/downloads/genome.fa organisms/Amphimedon_queenslandica/Aqu1/
cp ~/downloads/genes.gff organisms/Amphimedon_queenslandica/Aqu1/genomic.gff

mkdir -p data/tracks/Amphimedon_queenslandica/Aqu1/bigwig
cp ~/downloads/*.bw data/tracks/Amphimedon_queenslandica/Aqu1/bigwig/

# 2. Create Google Sheet with:
#    - track_id: reference_seq, TRACK_PATH: AUTO
#    - track_id: genes, TRACK_PATH: AUTO
#    - All BigWig tracks

# 3. Run integrated workflow
php tools/jbrowse/generate_tracks_from_sheet.php \
    "YOUR_SHEET_ID" \
    --gid 0 \
    --organism Amphimedon_queenslandica \
    --assembly Aqu1

# Output shows:
# - Assembly setup (first time)
# - AUTO tracks skipped (normal)
# - All BigWig tracks loaded

# 4. Generate configs
php tools/jbrowse/generate-jbrowse-configs.php

# 5. Test
curl -s "http://localhost:8888/api/jbrowse2/test-assembly.php?organism=Amphimedon_queenslandica&assembly=Aqu1" | jq .

# Done! Browser shows:
# - Reference genome
# - Gene annotations
# - All tracks
```

---

## Benefits of Integrated Workflow

### Before (Manual)
```bash
# Step 1: Setup genome
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Org/Asm

# Step 2: Register assembly
./tools/jbrowse/add_assembly_to_jbrowse.sh Org Asm

# Step 3: Add each track manually
./tools/jbrowse/add_bigwig_track.sh file1.bw Org Asm ...
./tools/jbrowse/add_bigwig_track.sh file2.bw Org Asm ...
# ... repeat 25 times ...

# Step 4: Generate configs
php tools/jbrowse/generate-jbrowse-configs.php
```

### Now (Automated)
```bash
# One command does everything!
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
    --organism Org --assembly Asm

php tools/jbrowse/generate-jbrowse-configs.php
```

✅ **Automatic assembly setup**  
✅ **Reference genome configured**  
✅ **Annotations configured**  
✅ **All tracks loaded**  
✅ **Combo tracks created**  

---

## Related Documentation

- [SETUP_NEW_ORGANISM.md](SETUP_NEW_ORGANISM.md) - Manual setup guide
- [GOOGLE_SHEETS_AUTOMATION.md](GOOGLE_SHEETS_AUTOMATION.md) - Google Sheets format
- [AUTO_CONFIG_GENERATION.md](AUTO_CONFIG_GENERATION.md) - Config generation
- [tools/jbrowse/README.md](../../tools/jbrowse/README.md) - Script reference

---

**Status:** ✅ Production Ready  
**Last Updated:** February 11, 2026  
**Tested With:** Nematostella vectensis GCA_033964005.1
