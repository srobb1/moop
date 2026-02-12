# Multi-BigWig Tracks in JBrowse2

**Feature:** Display multiple BigWig files as a single track (like JBrowse1 MultiBigWig plugin)

---

## Overview

JBrowse2's **MultiQuantitativeTrack** replaces JBrowse1's MultiBigWig plugin. It displays multiple BigWig files as subplots within a single track, perfect for:

- **Strand-specific data** (pos/neg strands)
- **Sample comparisons** (wild type vs treatment)
- **Replicates** (biological or technical)
- **Time series** (multiple time points)

---

## Migration from JBrowse1

### JBrowse1 Config (MultiBigWig Plugin)

```json
{
  "storeClass": "MultiBigWig/Store/SeqFeature/MultiBigWig",
  "urlTemplates": [
    {"url": "../files/bulk_align/MOLNG-1901.2.pos.bw", "color": "OrangeRed", "name": "2h_a_amanitin.pos"},
    {"url": "../files/bulk_align/MOLNG-1901.2.neg.bw", "color": "OrangeRed", "name": "2h_a_amanitin.neg"},
    {"url": "../files/bulk_align/MOLNG-1901.1.pos.bw", "color": "Maroon", "name": "2h_wild_type.pos"},
    {"url": "../files/bulk_align/MOLNG-1901.1.neg.bw", "color": "Maroon", "name": "2h_wild_type.neg"}
  ],
  "type": "MultiBigWig/View/Track/MultiWiggle/MultiXYPlot",
  "autoscale": "local",
  "label": "2hrs_amanitin",
  "key": "2hrs_amanitin"
}
```

### JBrowse2 Command (Using Our Script)

```bash
cd /data/moop

# Copy your BigWig files to the tracks directory first
cp /path/to/MOLNG-1901.*.bw data/tracks/bigwig/

# Add the multi-track
./tools/jbrowse/add_multi_bigwig_track.sh \
    Nematostella_vectensis GCA_033964005.1 \
    --name "2hrs Alpha-Amanitin vs Wild Type" \
    --track-id "2hr_amanitin_comparison" \
    --bigwig "MOLNG-1901.2.pos.bw:2h α-amanitin (+):OrangeRed" \
    --bigwig "MOLNG-1901.2.neg.bw:2h α-amanitin (-):OrangeRed" \
    --bigwig "MOLNG-1901.1.pos.bw:2h Wild Type (+):Maroon" \
    --bigwig "MOLNG-1901.1.neg.bw:2h Wild Type (-):Maroon" \
    --category "RNA-seq/Time Series" \
    --autoscale local \
    --access PUBLIC \
    --description "RNA-seq comparison of 2hr α-amanitin treatment vs control"
```

---

## Quick Examples

### Example 1: Strand-Specific RNA-seq

```bash
./tools/jbrowse/add_multi_bigwig_track.sh \
    Organism Assembly \
    --name "Sample RNA-seq (Stranded)" \
    --bigwig "sample.pos.bw:Positive Strand:#1f77b4" \
    --bigwig "sample.neg.bw:Negative Strand:#ff7f0e" \
    --category "RNA-seq" \
    --autoscale local
```

### Example 2: Multiple Conditions

```bash
./tools/jbrowse/add_multi_bigwig_track.sh \
    Organism Assembly \
    --name "Treatment Time Course" \
    --bigwig "0hr.bw:0 hours:#808080" \
    --bigwig "2hr.bw:2 hours:#00ff00" \
    --bigwig "4hr.bw:4 hours:#0000ff" \
    --bigwig "8hr.bw:8 hours:#ff0000" \
    --category "RNA-seq/Time Series"
```

### Example 3: Biological Replicates

```bash
./tools/jbrowse/add_multi_bigwig_track.sh \
    Organism Assembly \
    --name "Treatment (3 Replicates)" \
    --bigwig "rep1.bw:Replicate 1:#e74c3c" \
    --bigwig "rep2.bw:Replicate 2:#e74c3c" \
    --bigwig "rep3.bw:Replicate 3:#e74c3c" \
    --category "RNA-seq/Replicates"
```

---

## Color Options

### Named Colors (HTML/CSS Names)
Common colors that work well:
- **Reds:** `red`, `maroon`, `crimson`, `OrangeRed`, `tomato`
- **Blues:** `blue`, `navy`, `DodgerBlue`, `SteelBlue`
- **Greens:** `green`, `ForestGreen`, `LimeGreen`, `SeaGreen`
- **Purples:** `purple`, `indigo`, `DarkViolet`, `MediumPurple`
- **Grays:** `gray`, `dimgray`, `darkgray`, `silver`

### Hex Colors
- Red: `#ff0000`, `#8b0000` (dark red), `#dc143c` (crimson)
- Blue: `#0000ff`, `#000080` (navy), `#1e90ff` (dodger blue)
- Green: `#00ff00`, `#228b22` (forest green), `#32cd32` (lime green)

### RGB Colors
- `rgb(255, 0, 0)` - Red
- `rgb(139, 0, 0)` - Dark red / Maroon
- `rgb(255, 69, 0)` - Orange red

---

## Autoscale Options

- **`local`** - Scale each view independently (default, recommended)
- **`global`** - Use same Y-axis scale across all files
- **`globalsd`** - Global scale based on standard deviation

```bash
--autoscale local     # Each subplot scales independently
--autoscale global    # All subplots use same scale
```

---

## File Organization

Your BigWig files should be in:
```
/data/moop/data/tracks/bigwig/
├── MOLNG-1901.1.pos.bw
├── MOLNG-1901.1.neg.bw
├── MOLNG-1901.2.pos.bw
└── MOLNG-1901.2.neg.bw
```

Track metadata will be created in:
```
/data/moop/metadata/jbrowse2-configs/tracks/
└── {track_id}.json
```

---

## Generated Config Format

The script creates a **MultiQuantitativeTrack** config:

```json
{
  "trackId": "...",
  "name": "2hrs Alpha-Amanitin vs Wild Type",
  "type": "MultiQuantitativeTrack",
  "assemblyNames": ["Nematostella_vectensis_GCA_033964005.1"],
  "category": ["RNA-seq/Time Series"],
  "adapter": {
    "type": "MultiWiggleAdapter",
    "subadapters": [
      {
        "type": "QuantitativeTrack",
        "trackId": "..._sub0",
        "name": "2h α-amanitin (+)",
        "adapter": {
          "type": "BigWigAdapter",
          "bigWigLocation": {
            "uri": "/moop/data/tracks/bigwig/MOLNG-1901.2.pos.bw",
            "locationType": "UriLocation"
          }
        },
        "displays": [{
          "type": "LinearWiggleDisplay",
          "renderer": {
            "type": "XYPlotRenderer",
            "color": "OrangeRed"
          }
        }]
      },
      // ... more subadapters
    ]
  },
  "displays": [{
    "type": "MultiLinearWiggleDisplay",
    "displayId": "...-MultiLinearWiggleDisplay",
    "autoscale": "local"
  }]
}
```

---

## Common Patterns

### Pattern 1: Treatment vs Control (Stranded)

```bash
./tools/jbrowse/add_multi_bigwig_track.sh Organism Assembly \
    --name "Treatment vs Control (Stranded)" \
    --bigwig "control.pos.bw:Control (+):#4682b4" \
    --bigwig "control.neg.bw:Control (-):#4682b4" \
    --bigwig "treatment.pos.bw:Treatment (+):#dc143c" \
    --bigwig "treatment.neg.bw:Treatment (-):#dc143c"
```

### Pattern 2: Multiple Tissues

```bash
./tools/jbrowse/add_multi_bigwig_track.sh Organism Assembly \
    --name "Tissue Expression Profile" \
    --bigwig "brain.bw:Brain:#8b008b" \
    --bigwig "heart.bw:Heart:#ff0000" \
    --bigwig "liver.bw:Liver:#8b4513" \
    --bigwig "muscle.bw:Muscle:#ff69b4"
```

### Pattern 3: Developmental Time Series

```bash
./tools/jbrowse/add_multi_bigwig_track.sh Organism Assembly \
    --name "Embryonic Development" \
    --bigwig "stage1.bw:Zygote:#d3d3d3" \
    --bigwig "stage2.bw:2-cell:#b0c4de" \
    --bigwig "stage3.bw:4-cell:#87ceeb" \
    --bigwig "stage4.bw:8-cell:#4682b4" \
    --bigwig "stage5.bw:Morula:#000080"
```

---

## Troubleshooting

### Track Not Showing Multiple Files

**Check:** Make sure you specified multiple `--bigwig` arguments
```bash
# Wrong - only one file
--bigwig "file1.bw:Name:Color"

# Correct - multiple files
--bigwig "file1.bw:Name1:Color1" \
--bigwig "file2.bw:Name2:Color2"
```

### Colors Not Appearing

**Check:** Color format is correct
```bash
# Valid formats:
--bigwig "file.bw:Name:red"              # Named
--bigwig "file.bw:Name:#ff0000"          # Hex
--bigwig "file.bw:Name:rgb(255,0,0)"     # RGB
```

### Files Not Found

**Check:** Files are in the right location
```bash
ls -la /data/moop/data/tracks/bigwig/

# Or use absolute paths:
--bigwig "/full/path/to/file.bw:Name:Color"
```

---

## Comparison: JBrowse1 vs JBrowse2

| Feature | JBrowse1 MultiBigWig | JBrowse2 MultiQuantitative |
|---------|---------------------|---------------------------|
| **Multiple files** | ✅ Yes | ✅ Yes |
| **Colors per file** | ✅ Yes | ✅ Yes |
| **Autoscale** | ✅ local/global | ✅ local/global/globalsd |
| **Track type** | MultiXYPlot | MultiLinearWiggleDisplay |
| **Config format** | Plugin-specific | Native JBrowse2 |
| **Setup** | Manual config edit | Script or CLI |

---

## Complete Workflow Example

### 1. Copy Your JBrowse1 Files

```bash
# Assuming you have JBrowse1 files in /old/jbrowse1/files/bulk_align/
cp /old/jbrowse1/files/bulk_align/MOLNG-1901.*.bw /data/moop/data/tracks/bigwig/
```

### 2. Create Multi-Track

```bash
cd /data/moop

./tools/jbrowse/add_multi_bigwig_track.sh \
    Nematostella_vectensis GCA_033964005.1 \
    --name "2hr α-Amanitin Treatment" \
    --track-id "2hr_amanitin" \
    --bigwig "MOLNG-1901.2.pos.bw:2h α-amanitin (+):OrangeRed" \
    --bigwig "MOLNG-1901.2.neg.bw:2h α-amanitin (-):OrangeRed" \
    --bigwig "MOLNG-1901.1.pos.bw:2h WT (+):Maroon" \
    --bigwig "MOLNG-1901.1.neg.bw:2h WT (-):Maroon" \
    --category "RNA-seq/Treatments" \
    --description "Comparison of 2-hour α-amanitin treatment vs wild type control" \
    --access PUBLIC
```

### 3. Done!

The script automatically regenerates configs. Refresh JBrowse2 and you'll see your multi-track!

---

## Advanced: Manual Config Creation

If you prefer to write configs manually:

```json
{
  "trackId": "custom_multi_track",
  "name": "My Multi-BigWig Track",
  "type": "MultiQuantitativeTrack",
  "assemblyNames": ["Organism_Assembly"],
  "category": ["Category"],
  "adapter": {
    "type": "MultiWiggleAdapter",
    "subadapters": [
      {
        "type": "QuantitativeTrack",
        "adapter": {
          "type": "BigWigAdapter",
          "bigWigLocation": {
            "uri": "/moop/data/tracks/bigwig/file1.bw",
            "locationType": "UriLocation"
          }
        },
        "displays": [{
          "type": "LinearWiggleDisplay",
          "renderer": { "color": "#ff0000" }
        }]
      }
    ]
  }
}
```

Save to `/data/moop/metadata/jbrowse2-configs/tracks/{trackId}.json` and regenerate.

---

## Summary

✅ **Multi-BigWig support** - Like JBrowse1 MultiBigWig plugin  
✅ **Easy script** - One command to create multi-track  
✅ **Flexible colors** - Hex, named, or RGB  
✅ **Autoscale options** - local, global, globalsd  
✅ **Access control** - PUBLIC, COLLABORATOR, ADMIN  

**Next:** Try converting your JBrowse1 multi-tracks to JBrowse2!
