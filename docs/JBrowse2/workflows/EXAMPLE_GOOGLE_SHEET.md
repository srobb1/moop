# Example Google Sheet for JBrowse2 Track Generation

This example shows the complete format including regular tracks, combo tracks, and all color options.

---

## Complete Example Sheet

```tsv
track_id	name	category	filename	access_level	description	condition	tissue	#notes
# Regular single tracks (not in combo)
genome_wide_cov	Genome Coverage	DNA-seq Coverage	genome_coverage.bw	PUBLIC	Whole genome sequencing coverage	control	whole_body	High quality run
control_baseline	Control Baseline	RNA-seq	control.bw	PUBLIC	Untreated control sample	control	whole_body	

# Combo Track 1: Treatment vs Control (4 files total, 5 tracks created)
# 2hr Alpha-Amanitin Treatment
## oranges: Treatment
treat_2h_pos	Treatment 2h (+)	RNA-seq	MOLNG-1901.2.pos.bw	PUBLIC	2hr treatment positive strand	treated	whole_body	
treat_2h_neg	Treatment 2h (-)	RNA-seq	MOLNG-1901.2.neg.bw	PUBLIC	2hr treatment negative strand	treated	whole_body	
## blues: Control
ctrl_2h_pos	Control 2h (+)	RNA-seq	MOLNG-1901.1.pos.bw	PUBLIC	2hr control positive strand	control	whole_body	
ctrl_2h_neg	Control 2h (-)	RNA-seq	MOLNG-1901.1.neg.bw	PUBLIC	2hr control negative strand	control	whole_body	
### end

# More regular tracks
histone_h3k4me3	H3K4me3 ChIP	ChIP-seq	h3k4me3.bw	COLLABORATOR	Histone mark H3K4me3	control	whole_body	Published 2024

# Combo Track 2: Time Series (10 files, 11 tracks total)
# Developmental Time Course
## sunset: Developmental Stages
stage_0h	0 hours	Time Series	dev_0h.bw	PUBLIC			embryo	
stage_2h	2 hours	Time Series	dev_2h.bw	PUBLIC			embryo	
stage_4h	4 hours	Time Series	dev_4h.bw	PUBLIC			embryo	
stage_6h	6 hours	Time Series	dev_6h.bw	PUBLIC			larva	
stage_8h	8 hours	Time Series	dev_8h.bw	PUBLIC			larva	
stage_10h	10 hours	Time Series	dev_10h.bw	PUBLIC			larva	
stage_12h	12 hours	Time Series	dev_12h.bw	PUBLIC			larva	
stage_14h	14 hours	Time Series	dev_14h.bw	PUBLIC			juvenile	
stage_16h	16 hours	Time Series	dev_16h.bw	PUBLIC			juvenile	
stage_18h	18 hours	Time Series	dev_18h.bw	PUBLIC			juvenile	
### end

# BAM files (alignment tracks - auto-detected from .bam extension)
dna_align	DNA Alignments	DNA-seq	sample.bam	COLLABORATOR	DNA sequencing alignments	control	whole_body	#old_id	prev_sample_123
rna_align	RNA Alignments	RNA-seq	rnaseq.bam	PUBLIC	RNA sequencing alignments	control	whole_body	

# VCF file (variants - auto-detected from .vcf.gz extension)
variants	SNP Calls	Variants	variants.vcf.gz	ADMIN	Called SNPs and indels	control	whole_body	Filtered, high confidence

# Additional GFF annotation (auto-detected from .gff3 extension)
alternative_genes	Alternative Gene Models	Annotation	alt_genes.gff3	ADMIN	Alternative gene predictions			Re-annotated 2025
```

**Important Notes:**
- Required columns: `track_id`, `name`, `category`, `filename`
- Optional columns: `access_level`, `description`, `condition`, `tissue`, or any others you want
- Columns starting with `#` (like `#notes`, `#old_id`) are completely ignored
- Track type is auto-detected from file extension, NOT from category
- You can add any custom metadata columns for your own tracking!

---

## What This Creates

### Total Tracks Created: 36

#### Regular Tracks (6)
1. Genome Coverage (single)
2. Control Baseline (single)
3. H3K4me3 ChIP (single)
4. DNA Alignments (BAM)
5. RNA Alignments (BAM)
6. SNP Calls (VCF)

#### From Combo Track 1 - "2hr Alpha-Amanitin Treatment" (5 tracks)
- Individual: Treatment 2h (+)
- Individual: Treatment 2h (-)
- Individual: Control 2h (+)
- Individual: Control 2h (-)
- **Combo:** 2hr Alpha-Amanitin Treatment (all 4 together)

#### From Combo Track 2 - "Developmental Time Course" (11 tracks)
- Individual: 0 hours
- Individual: 2 hours
- ... (8 more individual tracks)
- **Combo:** Developmental Time Course (all 10 together)

#### From Combo Track 3 - "Specific Color Comparison" (7 tracks)
- Individual: WT Replicate 1 (DarkRed)
- Individual: WT Replicate 2 (DarkRed)
- Individual: WT Replicate 3 (DarkRed)
- Individual: Mutant Replicate 1 (DarkBlue)
- Individual: Mutant Replicate 2 (DarkBlue)
- Individual: Mutant Replicate 3 (DarkBlue)
- **Combo:** Specific Color Comparison (all 6 together)

#### From Combo Track 4 - "Tissue Expression" (6 tracks)
- Individual: Brain
- Individual: Heart
- Individual: Liver
- Individual: Muscle
- Individual: Kidney
- **Combo:** Tissue Expression (all 5 together)

#### Additional (1)
- Alternative Gene Models (GFF)

---

## Color Behavior Examples

### Example 1: Color Group Iteration (`blues`)
```
## blues: Sample Group
file1    # Navy (1st color in blues)
file2    # Blue (2nd color in blues)
file3    # RoyalBlue (3rd color in blues)
```

### Example 2: Exact Color (`exact=ColorName`)
```
## exact=OrangeRed: All Same
file1    # OrangeRed
file2    # OrangeRed
file3    # OrangeRed (all the same!)
```

### Example 3: Specific Color from Group (`blues3`)
```
## blues3: Specific Blue
file1    # SteelBlue (4th in blues, 0-indexed)
file2    # SteelBlue
file3    # SteelBlue (all the same!)
```

### Example 4: Hex Color (`exact=#FF5733`)
```
## exact=#FF5733: Custom Hex
file1    # #FF5733
file2    # #FF5733
file3    # #FF5733
```

---

## Running the Generator

```bash
# Dry run first to preview
python3 tools/jbrowse/generate_tracks_from_sheet.py \
    "YOUR_SHEET_ID" \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --dry-run

# Actually generate tracks
python3 tools/jbrowse/generate_tracks_from_sheet.py \
    "YOUR_SHEET_ID" \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --regenerate
```

Expected output:
```
Found 12 regular tracks  # 6 standalone + 6 from combo groups (duplicated)
Found 4 combo tracks

Processing regular tracks...
→ Creating bigwig track: Genome Coverage
  ✓ Created: Genome Coverage
→ Creating bigwig track: Treatment 2h (+)
  ✓ Created: Treatment 2h (+)
...

Processing combo tracks...
→ Creating multi-BigWig track: 2hr Alpha-Amanitin Treatment
  ✓ Created combo track: 2hr Alpha-Amanitin Treatment
...
```

---

## Template for Your Use

Copy this to your Google Sheet:

```tsv
label	key	technique	category	filename	access_level	description

# Your regular tracks here
track1	Track 1	Technique	Category	file.bw	PUBLIC	Description

# Your combo track here
# Combo Track Name
## colorgroup: Group 1
combo1	Combo 1	Technique	Category	file1.bw	PUBLIC	
## colorgroup: Group 2  
combo2	Combo 2	Technique	Category	file2.bw	PUBLIC	
### end
```

---

## Tips

1. **Regular tracks** - Just list them normally
2. **Combo tracks** - Each file gets created individually AND in the combo
3. **Color syntax** - Use `blues` for iteration, `exact=Color` for same color on all
4. **Access levels** - PUBLIC, COLLABORATOR, or ADMIN
5. **Dry run** - Always test with `--dry-run` first!

---

## Common Patterns

### Pattern 1: Strand-specific with exact colors
```
# Sample Name
## exact=DarkBlue: Positive Strand
sample_pos	Sample (+)	...	file.pos.bw	...
## exact=DarkRed: Negative Strand
sample_neg	Sample (-)	...	file.neg.bw	...
### end
```
Creates: 2 individual tracks + 1 combo (3 total)

### Pattern 2: Multiple conditions with color groups
```
# Conditions Comparison
## blues: Control
ctrl_a	Control A	...	ctrl_a.bw	...
ctrl_b	Control B	...	ctrl_b.bw	...
## reds: Treatment
treat_a	Treatment A	...	treat_a.bw	...
treat_b	Treatment B	...	treat_b.bw	...
### end
```
Creates: 4 individual tracks + 1 combo (5 total)

### Pattern 3: Time series with gradient
```
# Time Course
## sunset: Timepoints
t0	0h	...	t0.bw	...
t2	2h	...	t2.bw	...
t4	4h	...	t4.bw	...
### end
```
Creates: 3 individual tracks + 1 combo (4 total)
Colors: Gradient from dark to light (sunset palette)
