# Walkthrough: Adding Nematostella vectensis to JBrowse2

**Date:** February 10, 2026  
**Organism:** Nematostella vectensis (Starlet Sea Anemone)  
**Assembly:** GCA_033964005.1  
**Status:** Step-by-step integration guide

---

## Overview

This document walks through the complete process of integrating a new organism (Nematostella vectensis) into the MOOP JBrowse2 system, including:

1. ✅ Genome reference track (FASTA)
2. ✅ Gene annotations track (GFF)
3. ✅ BAM alignment tracks
4. ✅ BigWig coverage tracks

### Files Available

**Genome Data Location:** `/data/moop/organisms/Nematostella_vectensis/GCA_033964005.1/`
- `genome.fa` - Reference genome sequence
- `genomic.gff` - Gene annotations

**Track Data Location:** `/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/`
- `bam/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S3-body-wall.bam`
- `bam/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S3-body-wall.bam.bai`
- `bigwig/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.pos.bw`
- `bigwig/Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.neg.bw`

---

## Prerequisites

### Required Tools

```bash
# Verify tools are installed
samtools --version    # For FASTA indexing
bgzip --version       # For GFF compression
tabix --version       # For GFF indexing
jq --version          # For JSON validation
```

### Directory Structure

```
/data/moop/
├── organisms/Nematostella_vectensis/GCA_033964005.1/  ← Source files
├── data/
│   ├── genomes/Nematostella_vectensis/GCA_033964005.1/  ← Will be created
│   └── tracks/Nematostella_vectensis/GCA_033964005.1/   ← Already exists
├── metadata/jbrowse2-configs/
│   ├── assemblies/  ← Assembly definition will be created
│   └── tracks/      ← Track definitions will be created
└── tools/jbrowse/   ← Setup scripts
```

---

## Integration Plan

### Phase 1: Prepare Genome Files (5-10 minutes)

This creates symlinks, indexes FASTA, and compresses/indexes GFF.

**Script:** `setup_jbrowse_assembly.sh`

**What it does:**
1. Creates `/data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/`
2. Creates symlinks to original genome.fa and genomic.gff
3. Indexes FASTA with `samtools faidx` → creates `.fai`
4. Compresses GFF with `bgzip` → creates `.gff3.gz`
5. Indexes GFF with `tabix` → creates `.gff3.gz.tbi`

**Command:**
```bash
cd /data/moop
./tools/jbrowse/setup_jbrowse_assembly.sh \
    /data/moop/organisms/Nematostella_vectensis/GCA_033964005.1
```

**Expected Output:**
```
✓ Created directory: /data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1
✓ Created symlink: reference.fasta → genome.fa
✓ Created symlink: annotations.gff3 → genomic.gff
✓ Created: reference.fasta.fai
✓ Compressed: annotations.gff3.gz
✓ Indexed: annotations.gff3.gz.tbi
```

---

### Phase 2: Register Assembly in JBrowse2 (30 seconds)

This creates the assembly metadata JSON file.

**Script:** `add_assembly_to_jbrowse.sh`

**What it does:**
1. Extracts organism aliases from organism.sqlite (if available)
2. Creates `/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json`
3. Defines assembly name, display name, reference track adapter
4. Sets default access level (PUBLIC, COLLABORATOR, or ALL)

**Command:**
```bash
cd /data/moop
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --display-name "Nematostella vectensis (GCA_033964005.1)" \
    --access-level PUBLIC
```

**Optional Parameters:**
- `--display-name` - Pretty name shown in UI
- `--access-level` - PUBLIC (default), COLLABORATOR, or ALL
- `--alias` - Additional aliases (can specify multiple)

**Expected Output:**
```
✓ Assembly definition created!
  File: /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json
```

**Result:** Assembly with genome reference and annotations will now appear in JBrowse2!

---

### Phase 3: Add BAM Track (2 minutes)

Add the RNA-seq alignment track (**ADMIN only**).

**Script:** `add_bam_track.sh`

**What it does:**
1. Validates BAM file exists and has index (.bai)
2. Creates track metadata JSON
3. Configures track display properties
4. Links track to assembly
5. **Sets access level to ADMIN (admin only)**

**Command:**
```bash
cd /data/moop
./tools/jbrowse/add_bam_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bam/MOLNG-2707_S3-body-wall.bam \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Alignments" \
    --category "RNA-seq" \
    --tissue "body wall" \
    --experiment "MOLNG-2707" \
    --access ADMIN \
    --description "RNA-seq alignments from body wall tissue sample S3 (Admin only)"
```

**Note:** `--access ADMIN` restricts this track to admin users and IP whitelist only.

**Expected Output:**
```
✓ BAM track metadata created
  File: /data/moop/metadata/jbrowse2-configs/tracks/body_wall_rna_seq_alignments.json
```

---

### Phase 4: Add BigWig Tracks (2 minutes each)

Add coverage tracks for both positive and negative strands (**PUBLIC access**).

**Script:** `add_bigwig_track.sh`

**Note:** These tracks are set to PUBLIC, allowing all users to view coverage data while keeping raw alignments (BAM) restricted to admins.

#### Positive Strand Coverage

```bash
cd /data/moop
./tools/jbrowse/add_bigwig_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.pos.bw \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Coverage (Positive Strand)" \
    --category "RNA-seq Coverage" \
    --tissue "body wall" \
    --experiment "MOLNG-2707" \
    --access PUBLIC \
    --color "#1f77b4" \
    --description "RNA-seq coverage on positive strand from body wall tissue sample S1"
```

#### Negative Strand Coverage

```bash
cd /data/moop
./tools/jbrowse/add_bigwig_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.neg.bw \
    Nematostella_vectensis \
    GCA_033964005.1 \
    --name "Body Wall RNA-seq Coverage (Negative Strand)" \
    --category "RNA-seq Coverage" \
    --tissue "body wall" \
    --experiment "MOLNG-2707" \
    --access PUBLIC \
    --color "#ff7f0e" \
    --description "RNA-seq coverage on negative strand from body wall tissue sample S1"
```

**Expected Output (each):**
```
✓ BigWig track metadata created
  File: /data/moop/metadata/jbrowse2-configs/tracks/body_wall_rna_seq_coverage_*.json
```

---

## Verification & Testing

### 1. Check Metadata Files

```bash
# Verify assembly definition
ls -lh /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json

# Verify track definitions
ls -lh /data/moop/metadata/jbrowse2-configs/tracks/*.json

# Validate JSON syntax
jq . /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json
```

### 2. Test API Endpoint

```bash
# Test assembly API
curl -s "http://localhost:8888/api/jbrowse2/test-assembly.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1" | jq .

# Test get-config API
curl -s "http://localhost:8888/api/jbrowse2/get-config.php" | jq '.assemblies[] | select(.organism == "Nematostella_vectensis")'
```

### 3. Open in Browser

Navigate to:
```
http://localhost:8888/moop/jbrowse2.php
```

**Expected:**
- "Nematostella vectensis (GCA_033964005.1)" appears in assembly list
- Clicking opens genome browser with:
  - Reference sequence track
  - Annotations track (genes from GFF)
  - RNA-seq BAM track
  - RNA-seq coverage tracks (pos/neg)

---

## Troubleshooting

### Issue: Assembly Not Showing

**Possible Causes:**
1. Metadata file not created or invalid JSON
2. Access level doesn't match user permissions
3. Browser cache

**Solutions:**
```bash
# Validate JSON
jq . /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json

# Check access level in JSON
jq .defaultAccessLevel /data/moop/metadata/jbrowse2-configs/assemblies/Nematostella_vectensis_GCA_033964005.1.json

# Clear browser cache or use incognito mode
```

### Issue: Annotations Track Not Loading

**Possible Causes:**
1. GFF not compressed/indexed
2. Files not accessible via web
3. Incorrect path in assembly.php

**Solutions:**
```bash
# Check files exist
ls -lh /data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/annotations.gff3.gz*

# Verify index
tabix -l /data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/annotations.gff3.gz

# Check file permissions
chmod 644 /data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/annotations.gff3.gz*
```

### Issue: BAM/BigWig Tracks Not Loading

**Possible Causes:**
1. Track metadata not created
2. JWT token issues
3. File path incorrect
4. File permissions

**Solutions:**
```bash
# Check track metadata exists
ls -lh /data/moop/metadata/jbrowse2-configs/tracks/

# Verify BAM index
samtools quickcheck /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bam/*.bam

# Check JWT keys exist
ls -lh /data/moop/certs/jwt_*.pem

# Check file permissions
chmod 644 /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bam/*
chmod 644 /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/*
```

---

## Access Control

### User Access Levels

| User Type | Access Level | Sees |
|-----------|--------------|------|
| Anonymous (not logged in) | PUBLIC | PUBLIC assemblies & tracks only |
| Logged in user | COLLABORATOR | PUBLIC + COLLABORATOR assemblies & tracks |
| Admin user | ADMIN | All assemblies & tracks |
| IP Whitelist | IP_IN_RANGE | All assemblies & tracks (like ADMIN) |

### Track Access Levels

Tracks can have different access levels than their assembly:

| Access Level | Who Can See It |
|--------------|----------------|
| **PUBLIC** | Everyone (anonymous, logged in, admin) |
| **COLLABORATOR** | Logged in users and admins only |
| **ADMIN** | Admin users and IP whitelist only |

**Example for this integration:**
- Assembly: `PUBLIC` (everyone can see the organism)
- Annotations (GFF): `PUBLIC` (auto-loaded, visible to all)
- BigWig tracks: `PUBLIC` (coverage visible to all)
- BAM track: `ADMIN` (raw alignments restricted to admins)

### Setting Assembly Access

Edit the assembly JSON:
```json
{
  "defaultAccessLevel": "PUBLIC"  // or "COLLABORATOR" or "ADMIN"
}
```

Or use the `--access-level` flag during creation:
```bash
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    Nematostella_vectensis GCA_033964005.1 \
    --access-level PUBLIC
```

### Setting Track Access

Use the `--access` flag when adding tracks:
```bash
# Public track (everyone)
--access PUBLIC

# Collaborator track (logged in users)
--access COLLABORATOR

# Admin track (admins only)
--access ADMIN
```

---

## File Reference

### Created Files

After completing all steps, these files will exist:

**Genome Data:**
```
/data/moop/data/genomes/Nematostella_vectensis/GCA_033964005.1/
├── reference.fasta → /organisms/.../genome.fa
├── reference.fasta.fai
├── annotations.gff3 → /organisms/.../genomic.gff
├── annotations.gff3.gz
└── annotations.gff3.gz.tbi
```

**Metadata:**
```
/data/moop/metadata/jbrowse2-configs/assemblies/
└── Nematostella_vectensis_GCA_033964005.1.json

/data/moop/metadata/jbrowse2-configs/tracks/
├── nematostella_body_wall_bam.json
├── nematostella_body_wall_pos_coverage.json
└── nematostella_body_wall_neg_coverage.json
```

**Track Data (already exists):**
```
/data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/
├── bam/
│   ├── Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S3-body-wall.bam
│   └── Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S3-body-wall.bam.bai
└── bigwig/
    ├── Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.pos.bw
    └── Nematostella_vectensis_GCA_033964005.1_MOLNG-2707_S1-body-wall.neg.bw
```

---

## Code Changes Needed

Based on the current implementation, **no code changes are required** if:

1. ✅ Assembly API (`/api/jbrowse2/assembly.php`) already checks for annotations
2. ✅ Track scripts exist for BAM and BigWig
3. ✅ JWT authentication is set up

### Potential Enhancements

If you want to improve the system, consider:

1. **Automatic track discovery** - Scan tracks directory and auto-create metadata
2. **Strand-aware display** - Group pos/neg BigWig tracks together
3. **Track categories** - Better organization of many tracks
4. **Track search/filter** - UI to find tracks by tissue, experiment, etc.

---

## Quick Reference Commands

### Complete Integration (All Steps)

```bash
cd /data/moop

# Step 1: Setup genome files
./tools/jbrowse/setup_jbrowse_assembly.sh \
    /data/moop/organisms/Nematostella_vectensis/GCA_033964005.1

# Step 2: Register assembly
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    Nematostella_vectensis GCA_033964005.1 \
    --display-name "Nematostella vectensis (GCA_033964005.1)" \
    --access-level PUBLIC

# Step 3: Add BAM track (ADMIN only)
./tools/jbrowse/add_bam_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bam/MOLNG-2707_S3-body-wall.bam \
    Nematostella_vectensis GCA_033964005.1 \
    --name "Body Wall RNA-seq" --category "RNA-seq" --access ADMIN

# Step 4: Add BigWig tracks (PUBLIC)
./tools/jbrowse/add_bigwig_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.pos.bw \
    Nematostella_vectensis GCA_033964005.1 \
    --name "Body Wall RNA-seq (+ strand)" --category "RNA-seq Coverage" --access PUBLIC --color "#1f77b4"

./tools/jbrowse/add_bigwig_track.sh \
    /data/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/MOLNG-2707_S1-body-wall.neg.bw \
    Nematostella_vectensis GCA_033964005.1 \
    --name "Body Wall RNA-seq (- strand)" --category "RNA-seq Coverage" --access PUBLIC --color "#ff7f0e"

# Test
curl -s "http://localhost:8888/api/jbrowse2/test-assembly.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1" | jq .
```

---

## Next Steps

After completing this walkthrough:

1. **Generate cached configs** - Run this after adding all tracks:
   ```bash
   cd /data/moop
   php tools/jbrowse/generate-jbrowse-configs.php
   ```
   This creates the cached config files (PUBLIC.json, COLLABORATOR.json, ADMIN.json, IP_IN_RANGE.json) for optimal performance.

2. ✅ Verify assembly appears in JBrowse2 UI
3. ✅ Test genome browsing functionality
4. ✅ Verify all tracks load correctly
5. ✅ Test with different user access levels
6. 📋 Document any additional tracks to add
7. 📋 Add more assemblies using same pattern

**Important:** Run `php tools/jbrowse/generate-jbrowse-configs.php` whenever you:
- Add new tracks
- Remove tracks
- Change track access levels
- Add new assemblies

---

## Support

- **Main Documentation:** [README.md](README.md)
- **Admin Guide:** [ADMIN_GUIDE.md](ADMIN_GUIDE.md)
- **API Reference:** [API_REFERENCE.md](API_REFERENCE.md)
- **Scripts Documentation:** [/tools/jbrowse/README.md](../../tools/jbrowse/README.md)

---

**Status:** Ready to execute  
**Estimated Time:** 15-20 minutes total  
**Difficulty:** Beginner-friendly with scripts
