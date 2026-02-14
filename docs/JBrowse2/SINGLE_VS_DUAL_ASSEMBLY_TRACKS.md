# Single vs Dual-Assembly Track Generation

## Overview

MOOP now has TWO separate track generation systems:

1. **Single-Assembly Tracks** - Traditional tracks (BigWig, BAM, VCF, etc.)
2. **Dual-Assembly Synteny Tracks** - Comparative genomics tracks (PAF, PIF, MAF, MCScan)

## Why Separate Systems?

Synteny tracks fundamentally differ from regular tracks:
- Reference **TWO** organisms/assemblies
- Need different Google Sheet columns
- Stored in special `synteny/` directory
- Generate dual-assembly config directories

## Single-Assembly Tracks

### Track Types
- BigWig (`.bw`, `.bigwig`) - Signal/coverage
- BAM (`.bam`) - Alignments
- CRAM (`.cram`) - Compressed alignments
- VCF (`.vcf.gz`) - Variants
- BED (`.bed.gz`) - Features
- GFF (`.gff.gz`, `.gff3.gz`) - Annotations
- GTF (`.gtf`) - Transcripts
- MAF (`.maf.gz`) - Multiple alignments (with BigBed index)
- Combo - Multi-track groups
- Auto - Reference sequences

### Google Sheet Format
```
track_id | name | category | track_path | access_level | organism | assembly
```

### CLI Command
```bash
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
  --gid GID \
  --organism Organism_name \
  --assembly AssemblyID
```

### Storage
```
metadata/jbrowse2-configs/tracks/
└── Organism/
    └── AssemblyID/
        ├── bigwig/
        ├── bam/
        ├── vcf/
        └── ...
```

### Config Generator
- Uses: `lib/JBrowse/TrackGenerator.php`
- Registered types: 10 types (bigwig, bam, cram, vcf, bed, gff, gtf, maf, combo, auto)

## Dual-Assembly Synteny Tracks

### Track Types
- PAF (`.paf`, `.paf.gz`) - Pairwise alignments
- PIF (`.pif.gz`) - Whole genome synteny (minimap2)
- MCScan (`.anchors`) - Ortholog synteny (with BED files)

### Google Sheet Format
```
track_id | name | category | track_path | access_level | organism1 | assembly1 | organism2 | assembly2 | bed1_path | bed2_path
```

**Key difference**: Two organism/assembly pairs!

### CLI Command
```bash
php tools/jbrowse/generate_synteny_tracks_from_sheet.php SHEET_ID --gid GID
```

**Note**: No need to specify organisms/assemblies - they're in the sheet!

### Storage
```
metadata/jbrowse2-configs/tracks/
└── synteny/
    └── Anoura_caudifer_GCA_004027475.1_Nematostella_vectensis_GCA_033964005.1/
        ├── pif/
        ├── mcscan/
        ├── paf/
        └── maf/
```

Assembly pair names are alphabetically sorted for consistency.

### Config Generator
- Uses: `lib/JBrowse/SyntenyTrackGenerator.php`
- Registered types: 3 types (paf, pif, mcscan)

## Comparison Table

| Aspect | Single-Assembly | Dual-Assembly |
|--------|----------------|---------------|
| **Sheet Columns** | organism, assembly | organism1, assembly1, organism2, assembly2 |
| **Track Types** | 10 types | 3 types |
| **Generator Class** | TrackGenerator.php | SyntenyTrackGenerator.php |
| **CLI Script** | generate_tracks_from_sheet.php | generate_synteny_tracks_from_sheet.php |
| **Storage** | tracks/Org/Asm/type/ | tracks/synteny/Asm1_Asm2/type/ |
| **Config Structure** | Single assembly dir | Dual assembly dir with nested COLLABORATORs |

## Config Directory Structures

### Single-Assembly
```
jbrowse2/configs/
└── Organism_Assembly/
    ├── config.json
    ├── PUBLIC.json
    ├── COLLABORATOR.json
    ├── IP_IN_RANGE.json
    └── ADMIN.json
```

### Dual-Assembly
```
jbrowse2/configs/
└── Assembly1_Assembly2/
    ├── config.json
    ├── PUBLIC.json
    ├── IP_IN_RANGE.json
    ├── ADMIN.json
    ├── Assembly1/
    │   └── COLLABORATOR.json
    └── Assembly2/
        └── COLLABORATOR.json
```

**Key difference**: Dual configs have assembly-specific COLLABORATOR permissions!

## Workflow

### Single-Assembly Tracks
1. Create Google Sheet with single-assembly columns
2. Run `generate_tracks_from_sheet.php`
3. Run `generate-jbrowse-configs.php`
4. Tracks appear in single-assembly JBrowse views

### Dual-Assembly Tracks
1. Create separate Google Sheet with dual-assembly columns
2. Run `generate_synteny_tracks_from_sheet.php`
3. Run `generate-jbrowse-configs.php`
4. Tracks appear in dual-assembly synteny views

## Important Rules

### ❌ Don't Mix Track Types
- **Never** put PAF/PIF/MCScan in single-assembly sheet
- **Never** put BigWig/BAM/VCF/MAF in dual-assembly sheet

### ✅ Use Correct Script
- Single assembly → `generate_tracks_from_sheet.php`
- Dual assembly → `generate_synteny_tracks_from_sheet.php`

### ✅ Assembly Naming
- Single: Just the assembly ID
- Dual: Full organism_assembly names, alphabetically sorted

## Examples

### Single-Assembly Track
```tsv
track_id            name              category  track_path              organism              assembly
coverage_brain      Brain Coverage    RNA-seq   /data/brain.bw         Nematostella_vectensis GCA_033964005.1
```

### Dual-Assembly Track
```tsv
track_id         name           category  track_path         organism1              assembly1        organism2          assembly2        
nvec_anoura_syn  Nvec-Anoura    Synteny   /data/synteny.pif  Nematostella_vectensis GCA_033964005.1  Anoura_caudifer    GCA_004027475.1
```

## Migration Notes

If you have existing synteny tracks in single-assembly sheets:
1. Create new dual-assembly Google Sheet
2. Copy synteny tracks to new sheet
3. Add organism2/assembly2 columns
4. Remove from single-assembly sheet
5. Regenerate using synteny script

This ensures proper organization and config generation.
