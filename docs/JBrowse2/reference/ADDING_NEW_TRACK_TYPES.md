# Quick Guide: Adding New Track Types

This guide shows how to add support for new track types (CRAM, BED, GTF, etc.) to the Google Sheets automation system.

---

## Overview

Adding a new track type requires modifying **2 locations** in `generate_tracks_from_sheet.py` and creating **1 bash script**.

---

## Step 1: Add Extension Detection

**File:** `tools/jbrowse/generate_tracks_from_sheet.py`  
**Function:** `determine_track_type()` (around line 373-430)

Add a new `elif` branch for your track type:

```python
def determine_track_type(row):
    """Determine track type from file extension only"""
    track_path = row.get('TRACK_PATH', '')
    ext = Path(track_path).suffix.lower()
    
    # ... existing types (bigwig, bam, vcf, gff, fasta) ...
    
    # === ADD YOUR NEW TYPE HERE ===
    elif ext in ['.cram']:  # Your file extension(s)
        return 'cram'       # Your type identifier
    
    return None
```

**Common extensions:**
- CRAM: `.cram`
- BED: `.bed`
- GTF: `.gtf`
- BedGraph: `.bedgraph`, `.bg`
- HiC: `.hic`

---

## Step 2: Add Track Handler

**File:** `tools/jbrowse/generate_tracks_from_sheet.py`  
**Function:** `generate_single_track()` (around line 477-600)

Add a new `elif` branch to handle your track type:

```python
def generate_single_track(row, organism, assembly, moop_root, default_color='DodgerBlue', dry_run=False):
    # ... validation and setup code ...
    
    # ... existing track types (bigwig, bam, vcf, gff) ...
    
    # === ADD YOUR TRACK HANDLER HERE ===
    elif track_type == 'cram':  # Match the identifier from Step 1
        cmd = [
            'bash', str(script_dir / 'add_cram_track.sh'),  # Your bash script
            resolved_path, organism, assembly,
            '--name', name,
            '--track-id', track_id,
            '--category', category,
            '--access', access
        ]
        # Add optional arguments
        if description:
            cmd.extend(['--description', description])
        if technique:
            cmd.extend(['--technique', technique])
    
    # ... rest of function ...
```

---

## Step 3: Create Bash Script

**File:** `tools/jbrowse/add_cram_track.sh` (new file)

Copy an existing script as a template:

```bash
cp tools/jbrowse/add_bam_track.sh tools/jbrowse/add_cram_track.sh
```

Then modify it for your track type. Key sections to change:

1. **Header comments** - Update description
2. **Track type** - Change from "BAM" to your type
3. **Adapter type** - Change JBrowse2 adapter (e.g., `CramAdapter`)
4. **File extensions** - Update validation

**Example modifications for CRAM:**

```bash
#!/bin/bash
# Add CRAM Track to JBrowse2
# CRAM files are compressed sequence alignments

# ... validation and setup ...

# Generate track metadata JSON
cat > "$METADATA_FILE" << EOF
{
  "trackId": "$TRACK_ID",
  "name": "$TRACK_NAME",
  "assemblyNames": ["$ASSEMBLY"],
  "category": ["$CATEGORY"],
  "adapter": {
    "type": "CramAdapter",  # CHANGED: Use CRAM adapter
    "cramLocation": {
      "uri": "/moop/data/tracks/cram/$TARGET_FILENAME",
      "locationType": "UriLocation"
    },
    "craiLocation": {  # CHANGED: Index file for CRAM
      "uri": "/moop/data/tracks/cram/$TARGET_FILENAME.crai",
      "locationType": "UriLocation"
    }
  },
  "displays": [
    {
      "type": "LinearAlignmentsDisplay",
      "displayId": "$TRACK_ID-LinearAlignmentsDisplay"
    }
  ],
  "access_level": "$ACCESS_LEVEL"
}
EOF
```

---

## Complete Example: Adding CRAM Support

### 1. Update `determine_track_type()`

```python
elif ext in ['.cram']:
    return 'cram'
```

### 2. Update `generate_single_track()`

```python
elif track_type == 'cram':
    cmd = [
        'bash', str(script_dir / 'add_cram_track.sh'),
        resolved_path, organism, assembly,
        '--name', name,
        '--track-id', track_id,
        '--category', category,
        '--access', access
    ]
    if description:
        cmd.extend(['--description', description])
```

### 3. Create `add_cram_track.sh`

```bash
#!/bin/bash
# Add CRAM Track to JBrowse2

# ... (copy structure from add_bam_track.sh) ...

# Key changes:
# - Adapter type: "CramAdapter"
# - Location key: "cramLocation" 
# - Index key: "craiLocation" (CRAM index)
# - Target directory: cram/ instead of bam/
```

### 4. Test

```bash
# Add test row to Google Sheet
track_id: test_cram
name: Test CRAM Track
category: Alignments
TRACK_PATH: /data/moop/data/tracks/test.cram

# Run generator with dry-run
python3 tools/jbrowse/generate_tracks_from_sheet.py \
    "YOUR_SHEET_ID" \
    --organism Organism \
    --assembly Assembly \
    --dry-run
```

---

## Common Track Types Reference

### Alignment Tracks

| Type | Extension | Adapter | Index |
|------|-----------|---------|-------|
| BAM | `.bam` | `BamAdapter` | `.bai` |
| CRAM | `.cram` | `CramAdapter` | `.crai` |

### Variant Tracks

| Type | Extension | Adapter | Index |
|------|-----------|---------|-------|
| VCF | `.vcf.gz` | `VcfTabixAdapter` | `.tbi` |

### Quantitative Tracks

| Type | Extension | Adapter | Index |
|------|-----------|---------|-------|
| BigWig | `.bw`, `.bigwig` | `BigWigAdapter` | None |
| BedGraph | `.bedgraph` | `BedAdapter` | None |

### Feature/Annotation Tracks

| Type | Extension | Adapter | Index |
|------|-----------|---------|-------|
| GFF | `.gff`, `.gff3` | `Gff3TabixAdapter` | `.tbi` |
| GTF | `.gtf` | `Gff3TabixAdapter` | `.tbi` |
| BED | `.bed` | `BedTabixAdapter` | `.tbi` |

### Reference Tracks

| Type | Extension | Adapter | Index |
|------|-----------|---------|-------|
| FASTA | `.fa`, `.fasta` | `IndexedFastaAdapter` | `.fai` |

---

## Troubleshooting

### Track not detected
- Check file extension in `determine_track_type()`
- Ensure extension is lowercase in comparison

### Script not found
- Verify bash script exists in `tools/jbrowse/`
- Check script has execute permissions: `chmod +x add_xxx_track.sh`

### Track not appearing in JBrowse2
- Check adapter type matches JBrowse2 documentation
- Verify JSON syntax in generated metadata file
- Check file paths and URIs are correct
- Ensure index files exist (`.bai`, `.tbi`, etc.)

---

## Resources

- **JBrowse2 Adapters:** https://jbrowse.org/jb2/docs/config_guide/#adapter-types
- **Track Configuration:** https://jbrowse.org/jb2/docs/config_guide/#track-configuration
- **Example Scripts:** `tools/jbrowse/add_*_track.sh`

---

## Summary

Adding new track types is straightforward:

1. ✅ Add extension → type mapping (1 line)
2. ✅ Add track handler (10-15 lines)
3. ✅ Create bash script (copy + modify existing)
4. ✅ Test with dry-run

**Total time: ~30-60 minutes per track type**
