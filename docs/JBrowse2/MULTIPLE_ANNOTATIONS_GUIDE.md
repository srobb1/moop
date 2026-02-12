# Multiple GFF Annotations with Access Control

**Date:** 2026-02-11  
**Feature:** Support for multiple annotation tracks per assembly with different access levels

---

## Overview

The MOOP JBrowse2 system now supports **two ways** to add gene annotation tracks:

### 1. Automatic Default Annotations (PUBLIC)
- **File:** `data/genomes/{organism}/{assembly}/annotations.gff3.gz`
- **Access:** Always PUBLIC (everyone can see)
- **Track ID:** `{organism}_{assembly}-genes`
- **Name:** "Gene Annotations"
- **Added:** Automatically when you run `generate-jbrowse-configs.php`

### 2. Additional Annotation Tracks (Custom Access)
- **Script:** `tools/jbrowse/add_gff_track.sh`
- **Access:** PUBLIC, COLLABORATOR, or ADMIN (your choice)
- **Track ID:** Custom (you define)
- **Name:** Custom (you define)
- **Features:** Text search indexing, genometools sorting

---

## Use Cases

### Use Case 1: Everyone Sees the Same Genes
**Situation:** You have one official gene set that all users should see.

**Solution:** Use automatic annotations only.
```bash
# Setup assembly (includes GFF processing)
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Organism/Assembly

# Register assembly
./tools/jbrowse/add_assembly_to_jbrowse.sh Organism Assembly

# Generate configs (adds gene track automatically)
php tools/jbrowse/generate-jbrowse-configs.php
```

**Result:** One gene track, PUBLIC access.

---

### Use Case 2: Multiple Annotation Versions
**Situation:** You have:
- Official v1.0 genes (PUBLIC)
- Experimental v2.0 genes (ADMIN only)
- Alternative annotation from collaborator (COLLABORATOR)

**Solution:** Use automatic + manual tracks.

```bash
# Step 1: Rename official genes to be the default
cp genes_v1.gff3 /organisms/Organism/Assembly/genomic.gff
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Organism/Assembly
./tools/jbrowse/add_assembly_to_jbrowse.sh Organism Assembly

# Step 2: Add experimental genes (ADMIN only)
./tools/jbrowse/add_gff_track.sh genes_v2.gff3 Organism Assembly \
    --name "Gene Models v2.0 (Experimental)" \
    --access ADMIN \
    --category "Annotation/Experimental" \
    --description "New gene models under evaluation"

# Step 3: Add collaborator genes (COLLABORATOR access)
./tools/jbrowse/add_gff_track.sh collab_genes.gff3 Organism Assembly \
    --name "Alternative Gene Set (Smith Lab)" \
    --access COLLABORATOR \
    --category "Annotation/Alternative"

# Step 4: Generate configs
php tools/jbrowse/generate-jbrowse-configs.php
```

**Result:**
- Anonymous users: See only v1.0 genes
- Collaborators: See v1.0 + alternative genes
- Admins: See all three tracks

---

### Use Case 3: Private Annotation Development
**Situation:** You're curating a new gene set and don't want users to see it yet.

**Solution:** Use ADMIN-only track during development.

```bash
# Add as ADMIN-only
./tools/jbrowse/add_gff_track.sh new_genes.gff3 Organism Assembly \
    --name "Gene Set (Draft - Do Not Use)" \
    --access ADMIN \
    --category "Annotation/Draft"

# Later, when ready to release, change access level:
# Edit: metadata/jbrowse2-configs/tracks/{track_id}.json
# Change: "access_level": "ADMIN" → "access_level": "PUBLIC"

# Regenerate configs
php tools/jbrowse/generate-jbrowse-configs.php
```

---

## Script Features: add_gff_track.sh

### Text Search Indexing
The script automatically creates text search indexes using JBrowse CLI (if installed):
- Users can search for gene IDs and names
- Fast autocomplete in JBrowse2
- Indexed with Trix format

### GFF Processing Pipeline
Matches your other system's workflow:
1. **Sort and tidy** with genometools: `gt gff3 -sortlines -tidy -retainids`
2. **Compress** with bgzip: `bgzip`
3. **Index** with tabix: `tabix -p gff`
4. **Text index** with JBrowse CLI: `jbrowse text-index`

### Skip Options
```bash
# Already sorted? Skip the sort step
./tools/jbrowse/add_gff_track.sh genes.gff3 Org Asm --skip-sort

# Don't need text search?
./tools/jbrowse/add_gff_track.sh genes.gff3 Org Asm --skip-index
```

---

## File Organization

```
/data/moop/
├── data/
│   ├── genomes/
│   │   └── {organism}/{assembly}/
│   │       └── annotations.gff3.gz          ← Automatic (PUBLIC)
│   │       └── annotations.gff3.gz.tbi
│   └── tracks/
│       ├── gff/
│       │   └── {track_id}.sorted.gff3.gz    ← Manual tracks
│       │   └── {track_id}.sorted.gff3.gz.tbi
│       └── trix/
│           └── {track_id}.ix                ← Text search indexes
│           └── {track_id}.ixx
│           └── {track_id}_meta.json
│
└── metadata/jbrowse2-configs/tracks/
    └── {track_id}.json                      ← Track definitions
```

---

## Access Level Matrix

| Track Type | Location | Access | Who Sees It |
|------------|----------|--------|-------------|
| Automatic default | `data/genomes/.../annotations.gff3.gz` | PUBLIC | Everyone |
| Manual track | `data/tracks/gff/{id}.sorted.gff3.gz` | PUBLIC | Everyone |
| Manual track | `data/tracks/gff/{id}.sorted.gff3.gz` | COLLABORATOR | Logged in + admins |
| Manual track | `data/tracks/gff/{id}.sorted.gff3.gz` | ADMIN | Admins + IP whitelist only |

---

## Complete Example: Nematostella with Multiple Annotations

```bash
# Starting point: You have 3 GFF files
# - official_genes.gff3 (release quality, everyone should see)
# - draft_genes.gff3 (preliminary, admins only)
# - rna_genes.gff3 (RNA-seq derived, collaborators)

# Step 1: Setup assembly with official genes as default
cp official_genes.gff3 /organisms/Nematostella_vectensis/GCA_033964005.1/genomic.gff
./tools/jbrowse/setup_jbrowse_assembly.sh \
    /organisms/Nematostella_vectensis/GCA_033964005.1
./tools/jbrowse/add_assembly_to_jbrowse.sh \
    Nematostella_vectensis GCA_033964005.1 \
    --display-name "Nematostella vectensis (GCA_033964005.1)" \
    --access-level PUBLIC

# Step 2: Add draft genes (admin only)
./tools/jbrowse/add_gff_track.sh draft_genes.gff3 \
    Nematostella_vectensis GCA_033964005.1 \
    --name "Draft Gene Models (Under Review)" \
    --track-id "nve_gca033964005_draft_genes" \
    --access ADMIN \
    --category "Annotation/Draft" \
    --description "Preliminary gene models - do not cite"

# Step 3: Add RNA-seq genes (collaborator access)
./tools/jbrowse/add_gff_track.sh rna_genes.gff3 \
    Nematostella_vectensis GCA_033964005.1 \
    --name "RNA-seq Gene Models" \
    --track-id "nve_gca033964005_rnaseq_genes" \
    --access COLLABORATOR \
    --category "Annotation/RNA-seq" \
    --description "Gene models derived from RNA-seq data"

# Step 4: Generate configs
php tools/jbrowse/generate-jbrowse-configs.php
```

**Result:**
- Public users: See "Gene Annotations" (official)
- Collaborators: See "Gene Annotations" + "RNA-seq Gene Models"
- Admins: See all three tracks

---

## Migration: Replacing ConfigManager with Globals

### Old Approach (Globals)
```php
$PROJECT_ROOT = __DIR__ . '/../../';
$METADATA_DIR = $PROJECT_ROOT . 'metadata/jbrowse2-configs';
```

### New Approach (ConfigManager)
```php
require_once __DIR__ . '/../../includes/config_init.php';
$config = ConfigManager::getInstance();

$SITE_PATH = $config->getPath('site_path');
$METADATA_DIR = $config->getPath('metadata_path') . '/jbrowse2-configs';
```

### Benefits
- ✅ Centralized configuration
- ✅ Type-safe access
- ✅ Works across different deployments
- ✅ Easier to update paths
- ✅ No hardcoded paths

---

## Tools Required

### Essential
- `bgzip` - GFF compression
- `tabix` - GFF indexing
- `php` - Config generation

### Optional
- `genometools` (gt) - GFF sorting and validation
- `@jbrowse/cli` (jbrowse) - Text search indexing
- `jq` - JSON validation

### Installation
```bash
# Essential tools
sudo apt-get install tabix

# Optional tools
sudo apt-get install genometools
sudo npm install -g @jbrowse/cli
sudo apt-get install jq
```

---

## Troubleshooting

### Text Index Not Working
**Symptom:** Search doesn't work in JBrowse2

**Check:**
```bash
ls -la /data/moop/data/tracks/trix/
# Should see: {track_id}.ix, {track_id}.ixx, {track_id}_meta.json
```

**Fix:**
```bash
# Rerun with JBrowse CLI installed
./tools/jbrowse/add_gff_track.sh yourfile.gff3 Org Asm --name "Track"
```

### Track Not Appearing
**Symptom:** Added track doesn't show in browser

**Check:**
```bash
# 1. Metadata exists?
ls -la /data/moop/metadata/jbrowse2-configs/tracks/*.json

# 2. Configs regenerated?
php tools/jbrowse/generate-jbrowse-configs.php

# 3. In cached config?
cat /var/www/html/moop/jbrowse2/configs/{organism}_{assembly}/PUBLIC.json | jq '.tracks'
```

### Access Level Not Working
**Symptom:** Track visible to wrong users

**Check metadata:**
```bash
cat /data/moop/metadata/jbrowse2-configs/tracks/{track_id}.json | jq '.metadata.access_level'
```

**Fix:**
```bash
# Edit the JSON file
nano /data/moop/metadata/jbrowse2-configs/tracks/{track_id}.json
# Change "access_level": "ADMIN" to desired level

# Regenerate
php tools/jbrowse/generate-jbrowse-configs.php
```

---

## Summary

✅ **Two annotation systems:**
- Automatic: One default GFF per assembly (PUBLIC)
- Manual: Unlimited additional GFFs with custom access

✅ **Features:**
- Access control (PUBLIC, COLLABORATOR, ADMIN)
- Text search indexing
- Genometools integration
- ConfigManager (no globals)

✅ **Use cases covered:**
- Single official gene set
- Multiple annotation versions
- Draft annotations
- Collaborator-specific tracks

**Next Steps:**
- Try adding a second GFF track to Nematostella
- Test access levels with different user types
- Set up text search indexing
