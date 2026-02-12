# Quick Reference: JBrowse2 Track Types in MOOP

## ✅ Supported Track Types

| Type | Extension | Index Required | Use Case |
|------|-----------|----------------|----------|
| **BigWig** | `.bw`, `.bigwig` | No | RNA-seq, ChIP-seq coverage |
| **BAM** | `.bam` | `.bai` | Aligned sequencing reads |
| **CRAM** | `.cram` | `.crai` | Compressed alignments (30-60% smaller than BAM) |
| **VCF** | `.vcf.gz` | `.tbi` | Genetic variants (SNPs, indels) |
| **GFF** | `.gff`, `.gff3.gz` | `.tbi` (if gzipped) | Gene annotations |
| **GTF** | `.gtf` | No | Gene annotations (Ensembl format) |
| **BED** | `.bed.gz` | `.tbi` | Generic genomic features |
| **PAF** | `.paf` | No | Long-read alignments (PacBio, Nanopore) |
| **PIF.GZ** | `.pif.gz` | `.tbi` | Whole genome synteny (2 assemblies) |
| **Anchors** | `.anchors` | 2 `.bed` files | Orthologous gene pairs (2 assemblies) |

---

## 📋 Google Sheet Columns

### Required for All Tracks
```
track_id    | Unique identifier
name        | Display name
category    | Organizational category
TRACK_PATH  | File path or URL
```

### Optional
```
ACCESS_LEVEL    | public, ip_range, or admin
description     | Track description
technique       | Technique used (RNA-seq, etc.)
condition       | Experimental condition
tissue          | Tissue/organ type
```

### Required for Synteny Tracks
```
ASSEMBLY1   | First assembly name
ASSEMBLY2   | Second assembly name
BED1_PATH   | (MCScan only) BED for assembly 1
BED2_PATH   | (MCScan only) BED for assembly 2
```

---

## 🎯 File Path Formats

```bash
# Absolute path
TRACK_PATH=/data/moop/data/tracks/sample.bw

# Relative path (prepends MOOP_ROOT)
TRACK_PATH=data/tracks/sample.bw

# Remote URL
TRACK_PATH=http://server.edu/tracks/sample.bw
TRACK_PATH=https://server.edu/tracks/sample.bw
```

---

## 🔨 Quick Commands

### Generate Tracks from Google Sheet
```bash
python3 /data/moop/tools/jbrowse/generate_tracks_from_sheet.py \
  --sheet-url "https://docs.google.com/spreadsheets/d/YOUR_ID/edit" \
  --organism Nematostella_vectensis \
  --assembly GCA_033964005.1
```

### Add Individual Track
```bash
# BigWig
/data/moop/tools/jbrowse/add_bigwig_track.sh FILE ORG ASM --name NAME --track-id ID

# CRAM
/data/moop/tools/jbrowse/add_cram_track.sh -a ASM -t ID -n NAME -f FILE

# PAF
/data/moop/tools/jbrowse/add_paf_track.sh -a ASM -t ID -n NAME -f FILE

# GTF
/data/moop/tools/jbrowse/add_gtf_track.sh -a ASM -t ID -n NAME -f FILE -i

# BED
/data/moop/tools/jbrowse/add_bed_track.sh -a ASM -t ID -n NAME -f FILE

# Synteny (PIF.GZ)
/data/moop/tools/jbrowse/add_synteny_track.sh -1 ASM1 -2 ASM2 -t ID -n NAME -f FILE

# Synteny (MCScan)
/data/moop/tools/jbrowse/add_mcscan_track.sh \
  -1 ASM1 -2 ASM2 -t ID -n NAME \
  -f ANCHORS -b BED1 -b BED2
```

---

## 🔧 Index Files Required

| Track Type | Command to Create Index |
|------------|-------------------------|
| BAM | `samtools index file.bam` |
| CRAM | `samtools index file.cram` |
| VCF | `tabix -p vcf file.vcf.gz` |
| GFF (gzipped) | `tabix -p gff file.gff.gz` |
| BED | `tabix -p bed file.bed.gz` |
| PIF.GZ | `tabix -p bed file.pif.gz` |

---

## 🎨 Multi-BigWig (Combo) Tracks

### Google Sheet Format
```
# Combo Track Name
## blues: Group 1
track_id,name,TRACK_PATH,...
sample1,Sample 1,/data/tracks/sample1.bw,...
sample2,Sample 2,/data/tracks/sample2.bw,...
## reds: Group 2
sample3,Sample 3,/data/tracks/sample3.bw,...
sample4,Sample 4,/data/tracks/sample4.bw,...
### end
```

### Available Color Groups
```
blues, reds, greens, purples, oranges, cyans, pinks, browns, grays,
magentas, olives, teals, lavenders, corals, salmons, aquas, limes,
violets, indigos, maroons, diffs (mixed colors)
```

### Special Color Syntax
```
exact=Red          # Use exact color
blues3             # Use 4th blue (0-indexed)
```

---

## 🧪 File Preparation

### CRAM
```bash
samtools view -C -T reference.fa input.bam -o output.cram
samtools index output.cram
```

### PAF
```bash
minimap2 -x map-ont reference.fa reads.fastq > alignments.paf
```

### BED
```bash
sort -k1,1 -k2,2n features.bed | bgzip > features.bed.gz
tabix -p bed features.bed.gz
```

### PIF.GZ (Whole Genome Synteny)
```bash
# Align genomes
minimap2 -x asm5 target.fa query.fa > alignment.paf

# Sort, compress, index
sort -k6,6 -k8,8n alignment.paf | bgzip > target_query.pif.gz
tabix -p bed target_query.pif.gz
```

### MCScan (Ortholog Synteny)
```bash
# Install jcvi
pip install jcvi

# Run ortholog detection
python -m jcvi.compara.catalog ortholog genome1 genome2

# Outputs:
# - genome1.genome2.anchors
# - genome1.bed
# - genome2.bed
```

---

## 📊 Google Sheet Examples

### Regular Tracks
```csv
track_id,name,category,TRACK_PATH,ACCESS_LEVEL
rnaseq_bw,RNA-seq Coverage,Gene Expression,/data/tracks/rnaseq.bw,public
aligned_cram,Aligned Reads,Alignments,/data/tracks/aligned.cram,public
genes_gtf,Gene Models,Annotations,/data/tracks/genes.gtf,public
peaks_bed,ChIP Peaks,Features,/data/tracks/peaks.bed.gz,public
variants,SNPs,Variants,/data/tracks/variants.vcf.gz,public
nanopore,Nanopore Reads,Long-read,/data/tracks/nanopore.paf,public
```

### Synteny Tracks
```csv
track_id,name,category,ASSEMBLY1,ASSEMBLY2,TRACK_PATH,BED1_PATH,BED2_PATH,ACCESS_LEVEL
syn_wgs,Genome Synteny,Synteny,Nvec200,Nvec100,/data/syn/genomes.pif.gz,,,public
syn_ortho,Orthologs,Synteny,Nvec200,Nvec100,/data/syn/genes.anchors,/data/syn/n200.bed,/data/syn/n100.bed,public
```

---

## ✅ Validation Checks

Script automatically validates:

- ✓ Required columns present
- ✓ Track files exist (local paths)
- ✓ Index files exist
- ✓ Both assemblies loaded (synteny tracks)
- ✓ BED files exist (MCScan tracks)
- ✓ No duplicate track IDs

---

## 📚 Documentation

- **Full Format List:** `SUPPORTED_FORMATS.md`
- **Synteny Guide:** `SYNTENY_TRACKS_GUIDE.md`
- **Automation Guide:** `GOOGLE_SHEETS_AUTOMATION.md`
- **Code Review:** `CODE_REVIEW_generate_tracks_from_sheet.md`
- **Extension Guide:** `ADDING_NEW_TRACK_TYPES.md`
- **Summary:** `NEW_TRACK_TYPES_SUMMARY.md`

---

## 🐛 Troubleshooting

### Track Not Appearing
```bash
# Check if track added to config
grep "track_id_here" /data/moop/jbrowse2/config.json

# Check file exists
ls -lh /path/to/track/file

# Check index exists
ls -lh /path/to/track/file.*
```

### Synteny Track Issues
```bash
# Verify both assemblies loaded
grep '"name"' /data/moop/jbrowse2/config.json | grep -E "Assembly1|Assembly2"

# Check assembly names match exactly
# Use exact names from JBrowse2 config
```

### Index Missing
```bash
# BAM/CRAM
samtools index file.bam

# VCF/GFF/BED/PIF
tabix -p bed file.bed.gz  # or gff, vcf
```

---

**Quick Tip:** Use `--dry-run` flag to test without creating tracks:
```bash
python3 tools/jbrowse/generate_tracks_from_sheet.py \
  --sheet-url "URL" --organism ORG --assembly ASM --dry-run
```
