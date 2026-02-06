# JBrowse2 Assembly & Genome Setup

## Current Status

✅ JBrowse2 frontend created at `/data/moop/jbrowse2/`
✅ APIs ready at `/api/jbrowse2/`
✅ Track configs ready at `/metadata/jbrowse2-configs/tracks/`
⏳ **Next:** Set up reference genomes for JBrowse2

## Available Data

**Location:** `/data/moop/organisms/Anoura_caudifer/GCA_004027475.1/`

**Genome FASTA:**
- `genome.fa` (2.2 GB) - Main reference genome
- Already has BLAST indices (.nhr, .nin, etc.)

**Annotations:**
- `genomic.gff` - Gene annotations (can rename to `annotations.gff` or keep as-is)

**Other sequences:**
- `cds.nt.fa` - Coding sequences
- `transcript.nt.fa` - Transcripts
- `protein.aa.fa` - Protein sequences

## Plan Overview

### Step 1: Create Genome Directory for JBrowse2

JBrowse2 needs genomes in a web-accessible location following our architecture:

```
/data/moop/data/genomes/
├── Anoura_caudifer/
│   └── GCA_004027475.1/
│       ├── reference.fasta          ← Symlink or copy from organisms/
│       ├── reference.fasta.fai      ← Index (created by samtools)
│       ├── annotations.gff3.gz      ← Compressed annotations
│       └── annotations.gff3.gz.tbi  ← Tabix index
```

### Step 2: Index the Genome

```bash
# Create directory
mkdir -p /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1

# Create symlink to reference (saves space, no duplication)
ln -s /data/moop/organisms/Anoura_caudifer/GCA_004027475.1/genome.fa \
      /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta

# Index with samtools
samtools faidx /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta
```

This creates: `reference.fasta.fai` (FASTA index)

### Step 4: Prepare Annotations

```bash
# Sort GFF file (required by JBrowse2 for tabix indexing)
(grep "^#" /data/moop/organisms/Anoura_caudifer/GCA_004027475.1/genomic.gff; \
 grep -v "^#" /data/moop/organisms/Anoura_caudifer/GCA_004027475.1/genomic.gff | \
 sort -t"$(printf '\t')" -k1,1 -k4,4n) | \
bgzip -c > /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/annotations.gff3.gz

# Create Tabix index
tabix -p gff /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/annotations.gff3.gz
```

**Note:** GFF files must be sorted by chromosome (column 1) then position (column 4, numeric) before tabix indexing. The `setup_jbrowse_assembly.sh` script handles this automatically.

This creates:
- `annotations.gff3.gz` (compressed GFF)
- `annotations.gff3.gz.tbi` (Tabix index)

### Step 4: Add Assembly to JBrowse2 Config

Edit `/data/moop/jbrowse2/config.json`:

```json
{
  "assemblies": [
    {
      "name": "GCA_004027475.1",
      "displayName": "Anoura caudifer (GCA_004027475.1)",
      "sequence": {
        "type": "ReferenceSequenceTrack",
        "trackId": "reference",
        "adapter": {
          "type": "IndexedFastaAdapter",
          "fastaLocation": {
            "uri": "/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta"
          },
          "faiLocation": {
            "uri": "/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai"
          }
        }
      },
      "refNameAliases": {
        "adapter": {
          "type": "RefNameAliasAdapter",
          "location": {
            "uri": "/data/genomes/Anoura_caudifer/GCA_004027475.1/refNameAliases.txt"
          }
        }
      }
    }
  ],
  "tracks": [],
  "plugins": [
    {
      "name": "Web",
      "url": "http://127.0.0.1:8888/api/jbrowse2/assembly.php"
    }
  ]
}
```

### Step 5: Add Annotations Track

The annotations track is added dynamically by our API if it exists:
- See `/data/moop/api/jbrowse2/assembly.php` (line 177+)
- Automatically includes `annotations.gff3.gz` if present

### Step 6: Test with JBrowse2 CLI (Optional)

JBrowse2 provides utilities, but we can also use our API:

**Option A: Manual (what we'll do)**
1. Index genome with samtools ✓
2. Compress/index annotations with bgzip/tabix ✓
3. Update JBrowse2 config.json ✓
4. Test via browser

**Option B: Using jbrowse CLI**
```bash
# Add assembly
jbrowse add-assembly \
  /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta \
  --load copy \
  --out /data/moop/jbrowse2/

# Add track
jbrowse add-track \
  /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/annotations.gff.gz \
  --load copy \
  --out /data/moop/jbrowse2/
```

We'll use **Option A** (manual) because:
- More control over paths
- Integrates with our API-driven approach
- No duplication (use symlinks)
- Clearer for understanding

## Implementation Steps

### Step 1: Create Directory Structure

```bash
mkdir -p /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1
cd /data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1
```

### Step 2: Create Symlink to Reference Genome

```bash
ln -s /data/moop/organisms/Anoura_caudifer/GCA_004027475.1/genome.fa \
      reference.fasta
```

### Step 3: Index Genome

```bash
samtools faidx reference.fasta
```

Verify:
```bash
ls -la reference.fasta*
# Should see: reference.fasta and reference.fasta.fai
```

### Step 4: Prepare Annotations

```bash
# Copy and compress
bgzip -c /data/moop/organisms/Anoura_caudifer/GCA_004027475.1/genomic.gff \
      > annotations.gff3.gz

# Index
tabix -p gff annotations.gff3.gz
```

Verify:
```bash
ls -la annotations.gff3*
# Should see: annotations.gff3.gz and annotations.gff3.gz.tbi
```

### Step 5: Update JBrowse2 Config

```bash
# Edit /data/moop/jbrowse2/config.json
# Add assembly configuration (see example above)
```

### Step 6: Test

```bash
# Open browser to: http://127.0.0.1:8888/jbrowse2/
# Should see:
# - Assembly selector showing "GCA_004027475.1"
# - Reference sequence visible
# - Annotations track available
```

## Git Considerations

### .gitignore Updates

Add to `/data/moop/.gitignore` if not already there:

```gitignore
# Reference genomes (symlinks only)
/data/genomes/**/*.fa
/data/genomes/**/*.fasta
/data/genomes/**/*.fai
/data/genomes/**/*.gz
/data/genomes/**/*.tbi
```

### What to Track

- `/data/moop/jbrowse2/config.json` ✓ (TRACK - has API endpoint config)
- `/data/genomes/` - Only track symlinks if desired, ignore actual files

## Dependencies Required

```bash
# Install samtools (for FASTA indexing)
sudo apt-get install samtools

# Install tabix (for GFF indexing)
sudo apt-get install tabix
# or
sudo apt-get install htslib
```

## File Sizes

Current Anoura caudifer data:
- `genome.fa`: 2.2 GB
- `genomic.gff`: Not checked (typically < 500 MB)
- Indices: ~50 MB total

**Note:** Keep in `/organisms/` for MOOP. Create symlinks in `/data/genomes/` for JBrowse2.

## Next: Adding More Organisms

When you want to add another organism (e.g., Montipora_capitata):

1. Create directory: `/data/moop/data/genomes/Montipora_capitata/HIv3/`
2. Symlink genome: `ln -s /data/moop/organisms/.../genome.fa reference.fasta`
3. Index: `samtools faidx reference.fasta`
4. Prepare annotations if available
5. Update JBrowse2 config with new assembly
6. Test

This can be scripted for automated setup.

## References

- **Samtools documentation:** http://www.htslib.org/
- **JBrowse2 Genome Setup:** https://jbrowse.org/jb2/docs/faq/
- **Our integration plan:** `/data/moop/notes/jbrowse2_integration_plan.md`
