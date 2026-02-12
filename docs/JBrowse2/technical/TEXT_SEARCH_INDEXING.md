# Text Search Indexing for JBrowse2

**Date:** 2026-02-11  
**Feature:** Full text search for gene IDs and names in annotation tracks

---

## Overview

JBrowse2 now supports searching for gene IDs (e.g., "NV2g001324000.1") and gene names in annotation tracks using the Trix text search adapter.

---

## Quick Start

### Index Default Annotations

```bash
# Index a specific assembly
cd /var/www/html/moop
bash tools/jbrowse/index_default_annotations.sh Nematostella_vectensis GCA_033964005.1

# Or index all assemblies at once
bash tools/jbrowse/index_default_annotations.sh --all

# Regenerate configs to include text search
php tools/jbrowse/generate-jbrowse-configs.php
```

### Test in JBrowse2

1. Open JBrowse2 for your assembly
2. Click the search icon or use Ctrl+K
3. Type a gene ID: `NV2g001324000.1`
4. Results should appear instantly

---

## What Gets Indexed

The text search indexes the following GFF attributes:
- **ID** - Gene/transcript IDs (e.g., NV2g001324000.1)
- **Name** - Gene names (e.g., HOX1)
- **gene** - Gene attribute
- **product** - Product description
- **description** - Full description

---

## File Structure

```
/var/www/html/moop/data/tracks/trix/
├── Nematostella_vectensis_GCA_033964005.1-genes.ix      ← Index file (10MB)
├── Nematostella_vectensis_GCA_033964005.1-genes.ixx     ← Index index (2KB)
└── Nematostella_vectensis_GCA_033964005.1-genes_meta.json  ← Metadata
```

**Note:** These files are automatically detected by `generate-jbrowse-configs.php` and added to track configs.

---

## Workflow

### Initial Setup (One Time Per Assembly)

```bash
# 1. Setup assembly (creates annotations.gff3.gz)
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Nematostella_vectensis/GCA_033964005.1

# 2. Register assembly
./tools/jbrowse/add_assembly_to_jbrowse.sh Nematostella_vectensis GCA_033964005.1

# 3. Create text search index
bash ./tools/jbrowse/index_default_annotations.sh Nematostella_vectensis GCA_033964005.1

# 4. Generate configs (includes text search)
php ./tools/jbrowse/generate-jbrowse-configs.php
```

### After Updating GFF File

If you update the GFF3 file:

```bash
# 1. Re-index
bash ./tools/jbrowse/index_default_annotations.sh Organism Assembly

# 2. Regenerate configs
php ./tools/jbrowse/generate-jbrowse-configs.php
```

---

## Manual GFF Tracks

The `add_gff_track.sh` script **automatically** creates text search indexes:

```bash
./tools/jbrowse/add_gff_track.sh genes_v2.gff3 Organism Assembly \
    --name "Gene Models v2" \
    --access ADMIN
    
# Text index is created automatically!
# To skip: add --skip-index flag
```

---

## Troubleshooting

### Search Not Working

**Check 1: Index files exist?**
```bash
ls -la /var/www/html/moop/data/tracks/trix/
```

**Check 2: Config includes textSearching?**
```bash
cat /var/www/html/moop/jbrowse2/configs/Organism_Assembly/PUBLIC.json | jq '.tracks[0].textSearching'
```

**Check 3: Files accessible via web?**
```bash
curl -I http://localhost:8000/moop/data/tracks/trix/Nematostella_vectensis_GCA_033964005.1-genes.ix
```

### Re-index if Needed

```bash
# Delete old index
rm -f /var/www/html/moop/data/tracks/trix/Organism_Assembly-genes.*

# Create new index
bash ./tools/jbrowse/index_default_annotations.sh Organism Assembly

# Regenerate configs
php ./tools/jbrowse/generate-jbrowse-configs.php
```

---

## Customizing Indexed Attributes

Edit `tools/jbrowse/index_default_annotations.sh` line ~91:

```bash
jbrowse text-index \
    --attributes "ID,Name,gene,product,description,note,Alias" \  # Add more here
    --tracks="${assembly_name}-genes"
```

Common GFF attributes to index:
- `ID` - Feature ID
- `Name` - Display name
- `gene` - Gene symbol
- `product` - Product name
- `description` - Description
- `note` - Notes
- `Alias` - Alternative names
- `Dbxref` - Database references

---

## Performance

### Index Size
- **Nematostella (27K genes):** ~10 MB index
- **Search speed:** Instant (< 100ms)
- **Indexing time:** ~30 seconds per assembly

### Disk Space
Budget ~10-15 MB per assembly for text indexes.

---

## Batch Indexing

```bash
# Index all assemblies
bash ./tools/jbrowse/index_default_annotations.sh --all

# Example output:
# ℹ Indexing: Nematostella_vectensis_GCA_033964005.1
# ✓   Indexed: Nematostella_vectensis_GCA_033964005.1
# ℹ Indexing: Anoura_caudifer_GCA_004027475.1
# ✓   Indexed: Anoura_caudifer_GCA_004027475.1
# 
# Summary:
#   Indexed: 2 assemblies
```

---

## Integration with Workflow

### Recommended: Index During Assembly Setup

Modify your assembly setup workflow to include indexing:

```bash
#!/bin/bash
# setup_new_organism.sh

ORGANISM=$1
ASSEMBLY=$2

# Setup files
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/$ORGANISM/$ASSEMBLY

# Register
./tools/jbrowse/add_assembly_to_jbrowse.sh $ORGANISM $ASSEMBLY

# Index for search
bash ./tools/jbrowse/index_default_annotations.sh $ORGANISM $ASSEMBLY

# Generate configs
php ./tools/jbrowse/generate-jbrowse-configs.php

echo "✓ Assembly ready with searchable annotations!"
```

---

## Requirements

### Essential
- `@jbrowse/cli` (jbrowse command)
  ```bash
  sudo npm install -g @jbrowse/cli
  ```

### Check Version
```bash
jbrowse --version
# Should be 2.x.x
```

---

## Config Format

The text search configuration added to tracks:

```json
{
  "trackId": "Nematostella_vectensis_GCA_033964005.1-genes",
  "name": "Gene Annotations",
  "type": "FeatureTrack",
  "adapter": { ... },
  "textSearching": {
    "textSearchAdapter": {
      "type": "TrixTextSearchAdapter",
      "textSearchAdapterId": "...-index",
      "ixFilePath": {
        "uri": "/moop/data/tracks/trix/....ix",
        "locationType": "UriLocation"
      },
      "ixxFilePath": {
        "uri": "/moop/data/tracks/trix/....ixx",
        "locationType": "UriLocation"
      },
      "metaFilePath": {
        "uri": "/moop/data/tracks/trix/...._meta.json",
        "locationType": "UriLocation"
      },
      "assemblyNames": ["..."]
    }
  }
}
```

---

## Summary

✅ **Automatic indexing** - One command per assembly  
✅ **Fast search** - Gene IDs and names searchable instantly  
✅ **Auto-detected** - Configs automatically include text search if index exists  
✅ **Manual track support** - `add_gff_track.sh` includes indexing  

**Next:** Refresh JBrowse2 and try searching for "NV2g001324000.1"!
