# Cactus Multiple Genome Alignments in JBrowse2

## Overview

Cactus whole-genome alignments produce MAF (Multiple Alignment Format) files containing multiple species/assemblies aligned to a reference genome. This document describes how to load these alignments into MOOP/JBrowse2.

## MAF vs BigBed/BigMaf: Two Approaches

### Approach 1: MAF with MafAdapter (Recommended)

**Pros:**
- No conversion needed
- Direct from Cactus output
- Uses jbrowse-plugin-mafviewer for specialized alignment view

**Cons:**
- Slower for very large files
- Requires .gzi index

**Files needed:**
- `alignment.maf.gz` (bgzipped MAF)
- `alignment.maf.gz.gzi` (index)

**Adapter:** `MafAdapter`
**Track Type:** `MafTrack`
**Plugin Required:** `jbrowse-plugin-mafviewer`

### Approach 2: BigBed/BigMaf (For Large Files)

**Pros:**
- Fast random access
- Smaller file size
- No plugin needed (uses core BigBedAdapter)

**Cons:**
- Requires conversion: `mafToBigMaf` tool
- Extra processing step
- Different visualization (feature-based, not alignment-specific)

**Files needed:**
- `alignment.bb` or `alignment.bigbed`

**Adapter:** `BigBedAdapter`
**Track Type:** `FeatureTrack`
**Plugin Required:** None (core JBrowse2)

### Which to Use?

| Use Case | Recommendation |
|----------|----------------|
| File < 500 MB | MAF (MafAdapter) |
| File > 500 MB | Consider BigMaf |
| Need alignment visualization | MAF (MafAdapter) - required for proper display |
| Just need feature positions | BigMaf works |
| Cactus direct output | MAF (MafAdapter) - no conversion |

**Our recommendation: Use MAF (MafAdapter)** unless file size becomes prohibitive.

## How to Detect Format

Look at file extension in Google Sheet:

```php
if (preg_match('/\.maf\.gz$/i', $track_path)) {
    // Use MafAdapter - requires .gzi index
    $adapter = 'MafAdapter';
    $track_type = 'MafTrack';
    $index_required = '.gzi';
} 
elseif (preg_match('/\.(bb|bigbed)$/i', $track_path)) {
    // Use BigBedAdapter - no separate index needed
    $adapter = 'BigBedAdapter';
    $track_type = 'FeatureTrack';
    $index_required = false;
}
```

## Cactus Alignment Metadata Format

### The Challenge

Cactus alignments have:
- **One reference assembly** (e.g., hg38)
- **Multiple aligned genomes** (e.g., panTro6, gorGor6, ponAbe3, rheMac10)

Each aligned genome needs:
- `id` - Assembly identifier (e.g., "hg38", "panTro6")
- `label` - Human-readable name (e.g., "Human", "Chimp")  
- `color` - RGBA color for visualization (optional - uses rainbow palette by default)

### Google Sheet Format

#### Option 1: Auto-Detection (Recommended for Local Files)

```tsv
track_id | name | category | track_path | access_level | organism | assembly
primate_maf | Primate Alignment | Conservation | /data/cactus/primates.maf.gz | PUBLIC | Homo_sapiens | hg38
```

**Behavior:**
- Script parses the MAF file
- Extracts all unique genome IDs from sequence lines (e.g., `s hg38.chr1 ...`)
- Uses ID as label (e.g., "hg38" → "hg38")
- Assigns colors from rainbow palette automatically

**Pros:** Simple, no manual metadata needed  
**Cons:** Labels match IDs, can't customize colors

#### Option 2: MAF Column with Custom Metadata (Recommended for Custom Labels/Colors)

```tsv
track_id | name | category | track_path | access_level | organism | assembly | maf
primate_maf | Primate Alignment | Conservation | /data/cactus/primates.maf.gz | PUBLIC | Homo_sapiens | hg38 | hg38,Human;panTro6,Chimp;gorGor6,Gorilla
```

**Format:** `id,label[,color];id,label[,color];...`

**Examples:**

1. **Custom labels only** (colors from rainbow palette):
   ```
   hg38,Human;panTro6,Chimpanzee;gorGor6,Gorilla;ponAbe3,Orangutan
   ```

2. **Custom labels and colors**:
   ```
   hg38,Human,rgba(255,255,255,0.7);panTro6,Chimp,rgba(255,200,200,0.7);gorGor6,Gorilla,rgba(200,200,255,0.7)
   ```

3. **Mix of custom and default colors**:
   ```
   hg38,Human,rgba(255,255,255,0.7);panTro6,Chimp;gorGor6,Gorilla,rgba(200,200,255,0.7)
   ```
   (panTro6 gets next color from rainbow palette)

**Pros:** Full control over labels and colors  
**Cons:** More manual work

#### Option 3: Remote Files (MAF Column Required)

For remote files (HTTP/HTTPS), auto-detection isn't possible, so the `maf` column is required:

```tsv
track_id | name | category | track_path | access_level | organism | assembly | maf
cactus_remote | Remote MAF | Conservation | https://data.org/primates.maf.gz | PUBLIC | Homo_sapiens | hg38 | hg38,Human;panTro6,Chimp;gorGor6,Gorilla
```

### MAF Column Format Details

**Syntax:** `id,label[,color];id,label[,color];...`

- **`;` separates entries** (semicolon)
- **`,` separates fields** (comma: id, label, optional color)
- **Color is optional** - omit to use rainbow palette

**Color format (optional):** 
- `rgba(R,G,B,A)` - e.g., `rgba(255,255,255,0.7)`
- Hex colors: `#e6194b`
- Named colors: `OrangeRed`, `DodgerBlue`

**Note:** Colors are automatically converted to rgba with 0.7 opacity if hex is provided.

### Sample Metadata Fields

Each sample in the JSON array should have:

```json
{
  "id": "hg38",           // Required: Assembly identifier (matches MAF file)
  "label": "Human",       // Required: Display name
  "color": "rgba(255,255,255,0.7)"  // Required: RGBA color with transparency
}
```

**Color format:** `rgba(R, G, B, A)`
- R, G, B: 0-255
- A: 0.0-1.0 (transparency)
- Example: `rgba(255,255,255,0.7)` = white at 70% opacity

### Example: 20 Mammal Cactus Alignment

**Google Sheet Entry (Auto-detection):**
```tsv
track_id | name | category | track_path | organism | assembly
cactus_20mammals | 20 Mammal Alignment | Conservation | /data/cactus/mammals20.maf.gz | Homo_sapiens | hg38
```

**Or with custom labels:**
```tsv
track_id | name | category | track_path | organism | assembly | maf
cactus_20mammals | 20 Mammal Alignment | Conservation | /data/cactus/mammals20.maf.gz | Homo_sapiens | hg38 | hg38,Human;panTro6,Chimpanzee;gorGor6,Gorilla;ponAbe3,Orangutan;nomLeu3,Gibbon;rheMac10,Rhesus macaque;macFas5,Crab-eating macaque;papAnu4,Baboon;chlSab2,Green monkey;calJac4,Marmoset;saiBol1,Squirrel monkey;mm39,Mouse;rn7,Rat;cavPor3,Guinea pig;bosTau9,Cow;canFam6,Dog;felCat9,Cat;galGal6,Chicken;xenTro10,Frog;danRer11,Zebrafish
```

**Generated samples array** (colors from rainbow palette):
```json
[
  {"id": "hg38", "label": "Human", "color": "rgba(230,25,75,0.7)"},
  {"id": "panTro6", "label": "Chimpanzee", "color": "rgba(60,180,75,0.7)"},
  {"id": "gorGor6", "label": "Gorilla", "color": "rgba(255,225,25,0.7)"},
  {"id": "ponAbe3", "label": "Orangutan", "color": "rgba(67,99,216,0.7)"},
  {"id": "nomLeu3", "label": "Gibbon", "color": "rgba(245,130,49,0.7)"},
  {"id": "rheMac10", "label": "Rhesus macaque", "color": "rgba(145,30,180,0.7)"},
  {"id": "macFas5", "label": "Crab-eating macaque", "color": "rgba(70,240,240,0.7)"},
  {"id": "papAnu4", "label": "Baboon", "color": "rgba(240,50,230,0.7)"},
  {"id": "chlSab2", "label": "Green monkey", "color": "rgba(188,246,12,0.7)"},
  {"id": "calJac4", "label": "Marmoset", "color": "rgba(250,190,190,0.7)"},
  {"id": "saiBol1", "label": "Squirrel monkey", "color": "rgba(0,128,128,0.7)"},
  {"id": "mm39", "label": "Mouse", "color": "rgba(230,190,255,0.7)"},
  {"id": "rn7", "label": "Rat", "color": "rgba(154,99,36,0.7)"},
  {"id": "cavPor3", "label": "Guinea pig", "color": "rgba(255,250,200,0.7)"},
  {"id": "bosTau9", "label": "Cow", "color": "rgba(128,0,0,0.7)"},
  {"id": "canFam6", "label": "Dog", "color": "rgba(170,255,195,0.7)"},
  {"id": "felCat9", "label": "Cat", "color": "rgba(128,128,0,0.7)"},
  {"id": "galGal6", "label": "Chicken", "color": "rgba(255,216,177,0.7)"},
  {"id": "xenTro10", "label": "Frog", "color": "rgba(0,0,117,0.7)"},
  {"id": "danRer11", "label": "Zebrafish", "color": "rgba(128,128,128,0.7)"}
]
```
```

## Generated JBrowse2 Configuration

### For MAF (MafAdapter)

```json
{
  "type": "MafTrack",
  "trackId": "cactus_20mammals",
  "name": "20 Mammal Alignment",
  "category": ["Conservation"],
  "assemblyNames": ["hg38"],
  "adapter": {
    "type": "MafAdapter",
    "mafLocation": {
      "uri": "data/cactus/mammals20.maf.gz",
      "locationType": "UriLocation"
    },
    "index": {
      "location": {
        "uri": "data/cactus/mammals20.maf.gz.gzi",
        "locationType": "UriLocation"
      }
    },
    "samples": [
      {"id": "hg38", "label": "Human", "color": "rgba(255,255,255,0.7)"},
      {"id": "panTro6", "label": "Chimpanzee", "color": "rgba(255,200,200,0.7)"}
      // ... rest of samples
    ]
  },
  "metadata": {
    "access_level": "PUBLIC",
    "track_type": "maf"
  }
}
```

### For BigBed (BigBedAdapter)

```json
{
  "type": "FeatureTrack",
  "trackId": "cactus_20mammals_bb",
  "name": "20 Mammal Alignment (BigBed)",
  "category": ["Conservation"],
  "assemblyNames": ["hg38"],
  "adapter": {
    "type": "BigBedAdapter",
    "bigBedLocation": {
      "uri": "data/cactus/mammals20.bb",
      "locationType": "UriLocation"
    }
  },
  "metadata": {
    "access_level": "PUBLIC",
    "track_type": "bigbed",
    "samples": [
      {"id": "hg38", "label": "Human"},
      {"id": "panTro6", "label": "Chimpanzee"}
      // ... metadata only, not used by adapter
    ]
  }
}
```

**Note:** BigBedAdapter doesn't use sample metadata for display - this is stored for documentation only.

## PHP Implementation

### Parsing Samples from Google Sheet

The MAFTrack.php implementation uses this logic:

```php
// In buildMetadata() method:

// Parse samples from 'maf' column or auto-detect from file
$samples = [];
if (isset($options['maf']) && !empty($options['maf'])) {
    // Parse MAF column: id,label[,color];id,label[,color];...
    $samples = $this->parseMafColumn($options['maf']);
} elseif (!$isRemote) {
    // Auto-detect from local MAF file
    $samples = $this->parseSamplesFromMAF($filePath);
}
```

### parseMafColumn() Method

Parses the `maf` column format: `id,label[,color];id,label[,color];...`

```php
private function parseMafColumn(string $mafColumn): array
{
    $samples = [];
    $entries = explode(';', trim($mafColumn));
    
    foreach ($entries as $i => $entry) {
        $parts = array_map('trim', explode(',', $entry));
        
        if (count($parts) < 2) {
            continue; // Skip invalid entries
        }
        
        $sample = [
            'id' => $parts[0],
            'label' => $parts[1],
            'color' => isset($parts[2]) && !empty($parts[2]) 
                ? $parts[2] 
                : $this->getDefaultColor($i)  // Use rainbow palette
        ];
        
        $samples[] = $sample;
    }
    
    return $samples;
}
```

### parseSamplesFromMAF() Method

Auto-detects sample IDs from MAF file:

```php
private function parseSamplesFromMAF(string $filePath): array
{
    $sampleIds = [];
    
    $handle = gzopen($filePath, 'r');
    $lineCount = 0;
    
    while (!gzeof($handle) && $lineCount < 10000) {
        $line = gzgets($handle);
        // MAF sequence lines: "s <genome>.<chr> ..."
        if (preg_match('/^s\s+(\S+?)\./', $line, $matches)) {
            $sampleIds[$matches[1]] = true;
        }
        $lineCount++;
    }
    gzclose($handle);
    
    // Build samples array with id as label and rainbow colors
    $samples = [];
    $i = 0;
    foreach (array_keys($sampleIds) as $id) {
        $samples[] = [
            'id' => $id,
            'label' => $id,
            'color' => $this->getDefaultColor($i++)
        ];
    }
    
    return $samples;
}
```

### getDefaultColor() Method

Uses the ColorSchemes 'rainbow' palette:

```php
private function getDefaultColor(int $index): string
{
    // Get rainbow palette from ColorSchemes (20 colors)
    $palette = ColorSchemes::getScheme('rainbow');
    
    // Convert hex to rgba with 0.7 opacity
    $color = $palette[$index % count($palette)];
    
    if (strpos($color, '#') === 0) {
        $hex = ltrim($color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r,$g,$b,0.7)";
    }
    
    return $color;
}
```

**Why rainbow palette?**
- Has 20 distinct colors - perfect for multi-species alignments
- Maximum variety for distinguishing species
- Already used throughout MOOP for combo tracks

### Creating BigBed Track Type

Since BigBed uses `BigBedAdapter` (core JBrowse2), you need to create:

**`lib/JBrowse/TrackTypes/BigBedTrack.php`:**

```php
<?php

class BigBedTrack implements TrackTypeInterface
{
    public function getType() {
        return 'bigbed';
    }
    
    public function getValidExtensions() {
        return ['.bb', '.bigbed'];
    }
    
    public function requiresIndex() {
        return false; // BigBed has built-in index
    }
    
    public function getIndexExtensions() {
        return [];
    }
    
    public function buildMetadata(string $filePath, array $options): array {
        // Similar to MAFTrack, but using BigBedAdapter
        $adapterConfig = [
            'type' => 'BigBedAdapter',
            'bigBedLocation' => [
                'uri' => $bigBedUri,
                'locationType' => 'UriLocation'
            ]
        ];
        
        $metadata['config'] = [
            'type' => 'FeatureTrack', // Or specialized track type
            'trackId' => $trackId,
            'name' => $trackName,
            'adapter' => $adapterConfig
            // Note: samples metadata stored but not used by adapter
        ];
        
        return $metadata;
    }
}
```

## Preparing Cactus MAF Files

### 1. From Cactus Output

Cactus produces: `alignment.maf`

### 2. For MafAdapter (Recommended)

```bash
# Compress with bgzip
bgzip alignment.maf
# This creates: alignment.maf.gz and alignment.maf.gz.gzi
```

### 3. For BigBed (Optional)

```bash
# Install UCSC tools
wget http://hgdownload.soe.ucsc.edu/admin/exe/linux.x86_64/mafToBigMaf
chmod +x mafToBigMaf

# Convert
./mafToBigMaf alignment.maf chrom.sizes alignment.bb
```

## Testing

### Validate MAF Track

```bash
cd /data/moop
php tests/jbrowse/track_types/test_validation.php
```

### Test in JBrowse2

1. Generate track from Google Sheet
2. Load JBrowse2 view
3. Open track - should see multi-species alignment
4. Check sample colors and labels
5. Verify reference assembly highlighting

## Summary

**For Cactus alignments:**

1. **Use MAF format (.maf.gz)** with MafAdapter - this is the most compatible
2. **Auto-detection works for local files** - just provide track path, organism, and assembly
3. **Use `maf` column for custom labels/colors** - format: `id,label[,color];id,label[,color];...`
4. **Colors use rainbow palette by default** - 20 distinct colors, perfect for multi-species
5. **BigBed is optional** - only convert if MAF files are too large (>500MB)
6. **Reference assembly** goes in `organism` and `assembly` columns
7. **All aligned genomes** can be auto-detected or specified in `maf` column

### Quick Reference

| Use Case | Columns Needed | Example |
|----------|----------------|---------|
| Auto-detect (local file) | track_id, name, track_path, organism, assembly | Uses IDs as labels, rainbow colors |
| Custom labels | Add `maf` column | `hg38,Human;panTro6,Chimp` |
| Custom labels + colors | Add `maf` column with colors | `hg38,Human,rgba(...);panTro6,Chimp,#e6194b` |
| Remote file | Must use `maf` column | Can't auto-detect from remote files |

### What the `maf` Column Does

- **Overrides auto-detection** - if present, uses this instead of parsing file
- **Provides human-readable labels** - "Human" instead of "hg38"
- **Allows custom colors** - specify per-species or use rainbow defaults
- **Required for remote files** - can't parse remote MAF files

The `maf` column tells JBrowse2:
- Which genomes are in the alignment
- How to label them (e.g., "Human" not "hg38")
- What color to use for each species (optional)
- Which one is the reference (first in list, also matches organism/assembly)
