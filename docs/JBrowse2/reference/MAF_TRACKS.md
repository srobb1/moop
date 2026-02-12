# MAF (Multiple Alignment Format) Tracks in JBrowse2

## Overview

MAF tracks display multiple sequence alignments across different species/assemblies. This requires the `jbrowse-plugin-mafviewer` plugin.

## Prerequisites

1. **Install the MAF Viewer Plugin**:
   ```bash
   jbrowse add-plugin https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js \
       --out /data/moop/jbrowse2
   ```

2. **Prepare your MAF file**:
   - Must be compressed with bgzip: `bgzip alignment.maf`
   - Must be indexed: `bgzip alignment.maf.gz` (creates .maf.gz.gzi)

## Auto-Detection of Samples

The `generate_tracks_from_sheet.py` script will **automatically parse your MAF file** to extract sample/species IDs. The script looks for lines starting with `s` in the MAF format and extracts the species name (text before the first dot).

### MAF File Format Example:
```
a score=12345
s hg38.chr1 1000 100 + 248956422 ATCGATCG...
s panTro4.chr1 2000 100 + 239850000 ATCGATCG...
s gorGor3.chr1 1500 100 + 201709000 ATCGATCG...
```

From this, the script extracts: `hg38`, `panTro4`, `gorGor3`

## Google Sheet Format

### Simple Format (Auto-detection)

For **local files**, the script auto-detects samples:

| track_id | name | category | TRACK_PATH | access_level |
|----------|------|----------|------------|--------------|
| primate-alignment | Primate Alignment | Conservation | /data/moop/data/alignments/primates.maf.gz | PUBLIC |

The script will:
1. Parse the MAF file
2. Extract all unique species/sample IDs
3. Assign default colors from the rainbow palette
4. Create the track configuration

### Manual Format (With Custom Labels/Colors)

If you want to customize sample labels or colors, or if using **remote files**, you can specify samples in additional columns:

| track_id | name | category | TRACK_PATH | access_level | SAMPLES |
|----------|------|----------|------------|--------------|---------|
| primate-alignment | Primate Alignment | Conservation | http://server.edu/primates.maf.gz | PUBLIC | hg38,panTro4,gorGor3 |

The `SAMPLES` column is **required for remote files** since the script cannot download and parse them.

## Validation

The script performs validation:

### For Local Files:
- ✓ Parses MAF file to extract sample IDs
- ✓ Reports all samples found
- ✓ Assigns default colors automatically

### Sample Output:
```
  → Processing MAF file...
  → Analyzing MAF file for sample IDs...
     Found 3 samples: gorGor3, hg38, panTro4
  ⚠ Note: Requires jbrowse-plugin-mafviewer plugin to be installed
  ✓ Created: Primate Alignment
```

## Generated Configuration

The script generates a configuration like:

```json
{
  "type": "MafTrack",
  "trackId": "primate-alignment",
  "name": "Primate Alignment",
  "adapter": {
    "type": "MafAdapter",
    "mafLocation": {
      "uri": "data/alignments/primates.maf.gz",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "data/alignments/primates.maf.gz.gzi",
        "locationType": "UriLocation"
      }
    }
  },
  "assemblyNames": ["hg38"],
  "category": ["Conservation"],
  "samples": [
    {
      "id": "gorGor3",
      "label": "gorGor3",
      "color": "rgba(Blue,0.7)"
    },
    {
      "id": "hg38",
      "label": "hg38",
      "color": "rgba(Red,0.7)"
    },
    {
      "id": "panTro4",
      "label": "panTro4",
      "color": "rgba(Green,0.7)"
    }
  ]
}
```

## Future Enhancement: Custom Sample Metadata

In the future, we could support a more detailed format in the Google Sheet for customizing sample display:

```
# MAF: Primate Alignment
/data/moop/data/alignments/primates.maf.gz
## assemblyNames: hg38
## samples
hg38,Human,rgba(255,255,255,0.7)
panTro4,Chimp,rgba(255,0,0,0.7)
gorGor3,Gorilla,rgba(0,0,255,0.7)
```

This would allow:
- Custom labels (e.g., "Human" instead of "hg38")
- Custom colors with transparency
- Documentation of which assembly is the reference

But for now, the **auto-detection with default colors** is sufficient for most use cases.

## Troubleshooting

### Plugin not found error
```bash
# Install the plugin
jbrowse add-plugin https://unpkg.com/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js \
    --out /data/moop/jbrowse2
```

### Cannot parse MAF file
- Ensure file is readable and properly formatted
- Check that file is compressed with bgzip (not regular gzip)
- Verify MAF format (lines starting with 's' for sequence records)

### Missing samples for remote files
- Add a `SAMPLES` column with comma-separated sample IDs
- Example: `hg38,panTro4,gorGor3,ponAbe2`

## References

- JBrowse MAF Plugin: https://github.com/GMOD/jbrowse-plugin-mafviewer
- MAF Format Specification: https://genome.ucsc.edu/FAQ/FAQformat.html#format5
