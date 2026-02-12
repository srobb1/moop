# MAF File Support in MOOP JBrowse2

## Overview

MAF (Multiple Alignment Format) files contain multiple sequence alignments across species or samples. MOOP's Google Sheets automation can automatically detect and extract sample names from MAF files.

## MAF File Format

MAF files have a simple text format:

```
##maf version=1
a score=12345
s human.chr1     100 50 + 1000000 ATCGATCGATCGATCG...
s mouse.chr5     200 50 + 900000  ATCGATCGATCGAT--...
s rat.chr3       150 50 - 850000  ATCGATCGATCGA---...

a score=67890
s human.chr1     200 60 + 1000000 GCTAGCTAGCTAGCTA...
s mouse.chr5     300 60 + 900000  GCTAGCTAGCTAGCTA...
...
```

### Format Details

- `a` lines: Start a new alignment block with score
- `s` lines: Sequence data
  - Format: `s species.chromosome start size strand total_size sequence`
  - Species/sample name is before the first dot

## Auto-Extraction of Sample Names

The `parse_maf_samples()` function automatically extracts unique sample/species names:

```python
samples = parse_maf_samples('/path/to/file.maf.gz')
# Returns: ['human', 'mouse', 'rat']
```

### How It Works

1. Opens MAF file (handles both `.maf` and `.maf.gz`)
2. Scans for lines starting with `s `
3. Extracts the part before the first dot (species/sample name)
4. Stops after finding 20 unique samples (reasonable limit)
5. Returns sorted list of unique names

### Example Output

```
→ Analyzing MAF file for sample names...
   Found 3 samples in MAF: human, mouse, rat
```

## Google Sheet Configuration

### Option 1: Auto-Detection (Recommended)

For local MAF files, just provide the path:

| track_id | name | category | TRACK_PATH |
|----------|------|----------|------------|
| vertebrate_align | Vertebrate Alignment | Conservation | /data/moop/data/conservation/vertebrates.maf.gz |

The script will automatically extract sample names.

### Option 2: Manual Specification

For remote files or to override auto-detection, add a `SAMPLES` column:

| track_id | name | TRACK_PATH | SAMPLES |
|----------|------|------------|---------|
| vertebrate_align | Vertebrate Alignment | http://server.edu/vertebrates.maf.gz | human,mouse,rat,chicken,dog |

**Note**: Comma-separated, no spaces

## JBrowse2 MAF Plugin

MAF tracks require the MAF viewer plugin to be installed.

### Installation

```bash
# Navigate to your JBrowse2 directory
cd /data/moop/jbrowse2

# Add the MAF plugin
jbrowse add-plugin \
    https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js \
    -n MAFViewer
```

### Verify Installation

Check `config.json` for the plugin entry:

```json
{
  "plugins": [
    {
      "name": "MAFViewer",
      "url": "https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js"
    }
  ]
}
```

## Current Status

⚠️ **Partial Implementation**: The automation script currently:

- ✅ Detects MAF files by extension
- ✅ Auto-extracts sample names
- ✅ Validates SAMPLES column
- ❌ Does not automatically create track configs (manual step required)

### Manual Configuration Required

After the script identifies your MAF file and samples, you'll need to manually create the track configuration following the plugin documentation:

https://github.com/GMOD/jbrowse-plugin-mafviewer

Example MAF track config:

```json
{
  "type": "MafTrack",
  "trackId": "vertebrate_align",
  "name": "Vertebrate Alignment",
  "assemblyNames": ["human", "mouse", "rat"],
  "adapter": {
    "type": "MafAdapter",
    "mafLocation": {
      "uri": "/data/conservation/vertebrates.maf.gz",
      "locationType": "UriLocation"
    }
  }
}
```

## Future Enhancements

Planned improvements:

- [ ] Create `add_maf_track.sh` script for automation
- [ ] Auto-generate complete MAF track configs
- [ ] Support for MAF index files (.mai)
- [ ] Auto-detect reference/target species
- [ ] Integration with Google Sheets workflow

## Testing

To test MAF parsing without creating tracks:

```python
from generate_tracks_from_sheet import parse_maf_samples

# Test with your file
samples = parse_maf_samples('/data/moop/data/conservation/test.maf.gz')
print(f"Found samples: {samples}")
```

## Troubleshooting

### "Could not parse MAF file"

Possible causes:
- File doesn't exist or is unreadable
- File is not valid MAF format
- Compressed file but gzip module not available

### No Samples Found

Check that:
- MAF file has `s` lines with proper format
- Species/sample names are before the first dot
- File is not empty

### Wrong Samples Detected

If auto-detection finds incorrect samples:
- Add manual `SAMPLES` column in Google Sheet
- Check MAF file format (may have non-standard naming)

## References

- **JBrowse2 MAF Plugin**: https://github.com/GMOD/jbrowse-plugin-mafviewer
- **MAF Format Spec**: https://genome.ucsc.edu/FAQ/FAQformat.html#format5
- **MOOP Documentation**: `/data/moop/docs/JBrowse2/`
