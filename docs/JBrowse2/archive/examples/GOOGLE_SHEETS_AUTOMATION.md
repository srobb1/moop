# Google Sheets to JBrowse2 Track Automation

## Overview

This system allows you to manage all your JBrowse2 tracks through a Google Sheet and automatically generate track configurations. This approach provides:

- **Single Source of Truth**: All track metadata in one Google Sheet
- **Batch Operations**: Add/update multiple tracks at once
- **Version Control**: Track configuration history via sheets
- **Collaboration**: Share and edit metadata with your team
- **Consistency**: Standardized metadata across all tracks
- **Multi-BigWig Support**: Group related BigWig tracks together with custom color schemes

## Setup

### 1. Create Your Google Sheet

Your Google Sheet should have these **required** columns:

| Column | Required | Description |
|--------|----------|-------------|
| `track_id` | ✅ Yes | Unique identifier for the track |
| `name` | ✅ Yes | Display name in JBrowse2 |
| `TRACK_PATH` | ✅ Yes | Full path to track file (local or http:// for remote) |
| `category` | ❌ No | Track category/grouping (e.g., "RNA-seq", "ChIP-seq") |
| `access_level` | ❌ No | Access control: `public`, `collaborator`, `admin` |

### 2. Optional Metadata Columns

Add any of these for richer track metadata:

- `description` - Track description
- `technique` - Experimental technique
- `institute` - Source institution
- `tissue` - Tissue type
- `condition` - Experimental condition
- `developmental_stage` - Developmental stage
- `experiment` - Experiment ID
- `source` - Data source
- `summary` - Brief summary
- `citation` - Reference citation
- `project` - Project name
- `accession` - Database accession
- `date` - Date
- `analyst` - Analyst name

**Note**: Columns starting with `#` are ignored (use for notes/comments)

## Supported Track Types

Track type is automatically detected from file extension:

### Single Track Types
- **BigWig** (`.bw`, `.bigwig`) - Quantitative data (RNA-seq, ChIP-seq coverage)
- **BAM** (`.bam`) - Aligned reads
- **CRAM** (`.cram`) - Compressed aligned reads
- **VCF** (`.vcf`, `.vcf.gz`) - Variant calls
- **GFF/GFF3** (`.gff`, `.gff3`, `.gff.gz`, `.gff3.gz`) - Gene annotations
- **GTF** (`.gtf`, `.gtf.gz`) - Gene annotations
- **PAF** (`.paf`, `.paf.gz`) - Pairwise alignment format

### Multi-Track Types

#### Multi-BigWig Tracks
Group related BigWig files into a single overlay track. Perfect for:
- Biological replicates
- Plus/minus strand data
- Multiple conditions
- Time series data

**Format in Google Sheet:**
```
# Multi-BigWig: Track Display Name
## color_group: Group Label
track_id    name          TRACK_PATH                category    ...
sample1_pos S1 Forward    /path/to/s1.pos.bw       RNA-seq     ...
sample1_neg S1 Reverse    /path/to/s1.neg.bw       RNA-seq     ...
## blues: Another Group
sample2_pos S2 Forward    /path/to/s2.pos.bw       RNA-seq     ...
### end
```

**Color Groups Available:**
- `reds`, `blues`, `greens`, `purples`, `oranges`, `pinks`, `cyans`, `yellows`
- `browns`, `grays`, `warm`, `cool`, `earth`, `pastels`, `vibrant`
- `rainbow`, `ocean`, `forest`, `sunset`, `night`, `spring`
- `exact=ColorName` - Use exact color (e.g., `exact=DarkRed`)
- `blues3` - Use specific color index from group

#### Synteny Tracks
For whole-genome alignments between two assemblies:

**PIF.GZ format** (minimap2 output):
```
track_id           name                      TRACK_PATH              ASSEMBLY1    ASSEMBLY2    ...
genome1_genome2    Genome Synteny           /path/to/align.pif.gz    assembly1    assembly2    ...
```

**MCScan format** (anchor/bed files):
```
track_id              name              TRACK_PATH           ASSEMBLY1  ASSEMBLY2  BED1_PATH       BED2_PATH       ...
genome1_genome2.anchors Orthologs      /path/anchors        assembly1  assembly2  /path/bed1     /path/bed2      ...
```

#### MAF Tracks
Multiple alignment format (requires jbrowse-plugin-mafviewer):
```
track_id    name              TRACK_PATH           SAMPLES                    ...
alignment   Multi-Alignment   /path/to/data.maf.gz hg38,panTro4,gorGor3      ...
```

## Usage

### Basic Usage

```bash
python3 tools/jbrowse/generate_tracks_from_sheet.py SHEET_ID \
    --gid GID \
    --organism ORGANISM_NAME \
    --assembly ASSEMBLY_ID
```

### With Options

```bash
# Dry run (preview without changes)
python3 tools/jbrowse/generate_tracks_from_sheet.py "1Md23wI..." \
    --gid 1977809640 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --dry-run

# Verbose output
python3 tools/jbrowse/generate_tracks_from_sheet.py "1Md23wI..." \
    --gid 1977809640 \
    --organism Nematostella_vectensis \
    --assembly GCA_033964005.1 \
    --verbose
```

### Getting Sheet ID and GID

From your Google Sheets URL:
```
https://docs.google.com/spreadsheets/d/SHEET_ID/edit?gid=GID#gid=GID
                                          ^^^^^^^^^           ^^^
```

## Example Google Sheet

See the example sheet structure:
https://docs.google.com/spreadsheets/d/1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo/edit?gid=1977809640

## File Organization

The system organizes files automatically:

```
/data/moop/
├── data/
│   ├── genomes/
│   │   └── {organism}/
│   │       └── {assembly}/
│   │           ├── reference.fasta
│   │           └── annotations.gff3.gz
│   └── tracks/
│       ├── bigwig/
│       │   └── {organism}_{assembly}_{filename}
│       ├── bam/
│       ├── vcf/
│       └── ...
└── metadata/
    └── jbrowse2-configs/
        └── tracks/
            └── {track_id}.json
```

## Tips

### Color Group Selection

When you have more tracks than colors in a group, the script will:
1. Show you which groups have enough colors
2. Suggest appropriate alternatives
3. Show examples for each group type

Example error message:
```
⚠ COLOR GROUP TOO SMALL
Group 'blues' only has 11 colors but you need 15 colors.

Suitable alternatives with enough colors:
  rainbow (46 colors) - Maximum variety
  all (147 colors) - All available colors
```

### Updating Tracks

Simply re-run the script - it will:
- Skip existing tracks (shown as `✓ Track exists`)
- Only create new/missing tracks
- Update metadata if changed

### Track Management

- **Delete a track**: Remove from sheet and manually delete metadata JSON
- **Rename a track**: Change `track_id` (will create new track)
- **Update metadata**: Modify sheet values and re-run script

### Remote Files

Use full URLs for remote files:
```
http://data.example.com/tracks/sample1.bw
https://ftp.ncbi.nlm.nih.gov/genomes/data.bam
```

### Access Levels

Control who can see tracks:
- `public` - Everyone
- `collaborator` - Logged-in users with collaborator role
- `admin` - Administrators only

## Troubleshooting

### Script hangs
- Check file paths are correct
- Ensure files are readable
- Use `--verbose` for detailed output

### Tracks not appearing
- Check metadata files in `/data/moop/metadata/jbrowse2-configs/tracks/`
- Verify file permissions
- Check JBrowse2 console for errors

### Color group errors
- Choose a group with enough colors
- Use `rainbow` or `all` for large track groups
- Use `exact=ColorName` for specific colors

## Advanced: Adding New Track Types

To add support for new track types, edit:
1. `tools/jbrowse/generate_tracks_from_sheet.py` - Add file extension detection
2. Create corresponding `add_TRACKTYPE_track.sh` script
3. Update this documentation

Key locations in the Python script:
- Line ~600: `get_track_type()` function for extension detection
- Line ~670: Track command generation switch statement
- Add new case with appropriate bash script call

## See Also

- [JBrowse2 Track Types Documentation](https://jbrowse.org/jb2/docs/config_guide/)
- [Individual Track Addition Scripts](../tools/jbrowse/)
- [JBrowse2 Plugin System](https://jbrowse.org/jb2/docs/plugin_store/)
