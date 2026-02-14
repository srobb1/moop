# MAF Track Configuration Review

**Date:** 2026-02-14  
**Reviewer:** GitHub Copilot CLI  
**Files Reviewed:**
- `/data/moop/tools/jbrowse/generate_tracks_from_sheet.php`
- `/data/moop/lib/JBrowse/TrackTypes/MAFTrack.php`
- `/data/moop/metadata/jbrowse2-configs/tracks/Nematostella_vectensis/GCA_033964005.1/maf/test_maf.json`
- JBrowse2 plugin configuration

---

## Summary of Issues Found

### ✅ CORRECT Implementation

1. **Plugin is installed correctly** - MafViewer plugin is configured in `/data/moop/jbrowse2/config.json`
2. **Track type is registered** - MAF is properly registered in TrackGenerator.php
3. **File paths are correct** - Using proper web URIs through config.php
4. **Access control working** - JWT tokens added for non-whitelisted users

### ❌ CRITICAL ISSUES PREVENTING MAF DISPLAY

#### Issue 1: **Incorrect Adapter Configuration**

**Problem:** The generated config uses `MafTabixAdapter` with `bedGzLocation`, but according to jbrowse-plugin-mafviewer documentation:

**Current (WRONG):**
```json
{
  "type": "MafTrack",
  "adapter": {
    "type": "MafTabixAdapter",
    "bedGzLocation": {
      "uri": "/moop/data/tracks/.../test.maf.gz",
      "locationType": "UriLocation"
    },
    "index": {
      "indexType": "TBI",
      "location": {
        "uri": "/moop/data/tracks/.../test.maf.gz.tbi",
        "locationType": "UriLocation"
      }
    }
  }
}
```

**The jbrowse-plugin-mafviewer supports THREE adapter types:**

1. **MafTabixAdapter** - For BED format with MAF data
   - Property: `bedGzLocation` ✅ (This is correct for BED format)
   - Requires: `.bed.gz` + `.bed.gz.tbi`

2. **BgzipTaffyAdapter** - For TAF format (newer format)
   - Property: `tafGzLocation`
   - Requires: `.taf.gz` + `.taf.gz.tai`

3. **BigMafAdapter** - For BigBed format
   - Property: `bigBedLocation`
   - Requires: `.bb` file (self-indexed)

**Your test file is actually BED format** (not true MAF), so `MafTabixAdapter` with `bedGzLocation` is CORRECT!

#### Issue 2: **Missing `samples` Configuration**

**Problem:** The adapter is missing the critical `samples` array that tells JBrowse2 what genomes are in the alignment.

**Current output:**
```json
{
  "adapter": {
    "type": "MafTabixAdapter",
    "bedGzLocation": {...},
    "index": {...}
  }
}
```

**Should be:**
```json
{
  "adapter": {
    "type": "MafTabixAdapter",
    "bedGzLocation": {...},
    "index": {...},
    "samples": [
      {
        "id": "Nematostella_vectensis.GCA_033964005.1",
        "label": "Nematostella vectensis",
        "color": "rgba(230,25,75,0.7)"
      },
      {
        "id": "Hydra_vulgaris.GCA_000004535.1",
        "label": "Hydra vulgaris",
        "color": "rgba(60,180,75,0.7)"
      },
      {
        "id": "Acropora_digitifera.GCA_000222465.1",
        "label": "Acropora digitifera",
        "color": "rgba(255,225,25,0.7)"
      }
    ]
  }
}
```

**WHY THIS IS CRITICAL:**
Without the `samples` array, JBrowse2 cannot:
- Parse the BED file correctly (doesn't know which columns contain which genomes)
- Display the alignment tracks
- Show genome labels
- Apply colors to different genomes

---

## Root Cause Analysis

### In MAFTrack.php line ~280-320

The code attempts to get samples:

```php
private function getSamples(array $options): array
{
    // Parse samples from 'maf' column if provided
    if (isset($options['maf']) && !empty($options['maf'])) {
        return $this->parseMafColumn($options['maf']);
    }
    
    // Auto-detect from local MAF file if not remote
    if (isset($options['file_path']) && !preg_match('/^https?:\/\//i', $options['file_path'])) {
        return $this->parseSamplesFromMAF($options['file_path']);
    }
    
    return [];
}
```

**Problems:**

1. **Key name mismatch** - Looking for `$options['file_path']` but the actual key is `$options['TRACK_PATH']`
2. **parseSamplesFromMAF() is for true MAF format** - Looks for lines starting with `s` (MAF sequence lines)
3. **BED format needs different parser** - Your test file is BED12+13 format with encoded MAF data

### Your Test File Format

```bash
$ zcat test.maf.gz | head -1
scaffold_1	1000	1050	block1	100	+	Nematostella_vectensis.GCA_033964005.1.scaffold_1:1000:50:+:100000:ATGC...,Hydra_vulgaris.GCA_000004535.1.scaffold_1:2000:50:+:200000:ATGC...,Acropora_digitifera.GCA_000222465.1.scaffold_1:3000:50:+:300000:ATGC...
```

This is **BED12+13** format where:
- Columns 1-6: Standard BED fields
- Column 7: Comma-separated alignment blocks in format: `genome.scaffold:start:length:strand:chromSize:sequence`

---

## Required Fixes

### Fix 1: Update MAFTrack.php buildAdapterConfig()

**File:** `/data/moop/lib/JBrowse/TrackTypes/MAFTrack.php`

**Line ~235-270** - In buildAdapterConfig(), the adapter config needs to include samples:

```php
private function buildAdapterConfig(string $filePath, array $options, bool $isRemote): array
{
    // Get URIs for web access
    if ($isRemote) {
        $fileUri = $filePath;
    } else {
        $fileUri = $this->pathResolver->toWebUri($filePath);
    }
    
    // Detect adapter type based on file extension
    if (preg_match('/\.bb$/i', $filePath)) {
        // BigBed format (BigMaf)
        return $this->buildBigMafAdapter($fileUri, $options);
        
    } elseif (preg_match('/\.taf\.gz$/i', $filePath)) {
        // TAF format (BgzipTaffy)
        return $this->buildBgzipTaffyAdapter($filePath, $fileUri, $options, $isRemote);
        
    } elseif (preg_match('/\.(bed\.gz|maf\.gz)$/i', $filePath)) {
        // BED or MAF with tabix (MafTabix)
        return $this->buildMafTabixAdapter($filePath, $fileUri, $options, $isRemote);
        
    } else {
        // Uncompressed MAF (not commonly used)
        return $this->buildMafTabixAdapter($filePath, $fileUri, $options, $isRemote);
    }
}
```

The adapter builders are already calling `$this->getSamples($options)`, but samples are not being returned!

### Fix 2: Fix getSamples() key name

**Line ~360-380** - Change `file_path` to `TRACK_PATH`:

```php
private function getSamples(array $options): array
{
    // Parse samples from 'maf' column if provided
    if (isset($options['maf']) && !empty($options['maf'])) {
        return $this->parseMafColumn($options['maf']);
    }
    
    // Auto-detect from local file if not remote
    // FIX: Use TRACK_PATH instead of file_path
    if (isset($options['TRACK_PATH']) && !preg_match('/^https?:\/\//i', $options['TRACK_PATH'])) {
        return $this->parseSamplesFromFile($options['TRACK_PATH']);
    }
    
    return [];
}
```

### Fix 3: Add parseSamplesFromBedMAF() method

BED format MAF files need a different parser:

```php
/**
 * Parse samples from BED format MAF file
 * BED12+13 format: chrom, start, end, name, score, strand, ...encoded_alignments...
 * Column 13 format: genome.scaffold:start:len:strand:size:seq,genome2.scaffold:...
 */
private function parseSamplesFromBedMAF(string $filePath): array
{
    $sampleIds = [];
    
    try {
        if (!file_exists($filePath)) {
            error_log("BED MAF file not found: $filePath");
            return [];
        }
        
        $handle = gzopen($filePath, 'r');
        if (!$handle) {
            error_log("Failed to open BED MAF file: $filePath");
            return [];
        }
        
        // Parse first 100 lines to find all sample IDs
        $lineCount = 0;
        while (!gzeof($handle) && $lineCount < 100) {
            $line = trim(gzgets($handle));
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            $fields = explode("\t", $line);
            if (count($fields) < 7) {
                continue;
            }
            
            // Column 7 (index 6) contains alignment data
            // Format: genome1.scaffold:start:len:strand:size:seq,genome2.scaffold:...
            $alignmentData = $fields[6];
            $blocks = explode(',', $alignmentData);
            
            foreach ($blocks as $block) {
                // Extract genome ID (text before first dot)
                if (preg_match('/^([^\.]+)\./', $block, $matches)) {
                    $sampleIds[$matches[1]] = true;
                }
            }
            
            $lineCount++;
        }
        gzclose($handle);
        
        // Build samples array with default colors
        $samples = [];
        $i = 0;
        foreach (array_keys($sampleIds) as $id) {
            $samples[] = [
                'id' => $id,
                'label' => str_replace('_', ' ', $id), // Make readable
                'color' => $this->getDefaultColor($i++)
            ];
        }
        
        return $samples;
        
    } catch (Exception $e) {
        error_log("Failed to parse BED MAF samples: " . $e->getMessage());
        return [];
    }
}

/**
 * Parse samples from true MAF format file
 * MAF format: Lines starting with 's' like "s genome.chr start len strand size sequence"
 */
private function parseSamplesFromMAF(string $filePath): array
{
    $sampleIds = [];
    
    try {
        if (!file_exists($filePath)) {
            error_log("MAF file not found: $filePath");
            return [];
        }
        
        $handle = gzopen($filePath, 'r');
        if (!$handle) {
            error_log("Failed to open MAF file: $filePath");
            return [];
        }
        
        // Parse first 10000 lines to find sample IDs
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
        
        // Build samples array with defaults
        $samples = [];
        $i = 0;
        foreach (array_keys($sampleIds) as $id) {
            $samples[] = [
                'id' => $id,
                'label' => str_replace('_', ' ', $id),
                'color' => $this->getDefaultColor($i++)
            ];
        }
        
        return $samples;
        
    } catch (Exception $e) {
        error_log("Failed to parse MAF samples: " . $e->getMessage());
        return [];
    }
}

/**
 * Auto-detect format and parse samples
 */
private function parseSamplesFromFile(string $filePath): array
{
    // Read first non-comment line to detect format
    try {
        $handle = gzopen($filePath, 'r');
        if (!$handle) {
            return [];
        }
        
        $firstLine = '';
        while (!gzeof($handle)) {
            $line = trim(gzgets($handle));
            if (!empty($line) && $line[0] !== '#') {
                $firstLine = $line;
                break;
            }
        }
        gzclose($handle);
        
        // Detect format
        if (preg_match('/^s\s+/', $firstLine)) {
            // True MAF format (starts with 's' for sequence)
            return $this->parseSamplesFromMAF($filePath);
        } else {
            // BED format with encoded MAF
            return $this->parseSamplesFromBedMAF($filePath);
        }
        
    } catch (Exception $e) {
        error_log("Failed to detect MAF format: " . $e->getMessage());
        return [];
    }
}
```

---

## Testing the Fix

After applying the fixes:

```bash
# 1. Regenerate the test MAF track
cd /data/moop
php tools/jbrowse/generate_tracks_from_sheet.php SHEET_ID \
  --gid GID \
  --organism Nematostella_vectensis \
  --assembly GCA_033964005.1 \
  --force test_maf

# 2. Check the generated config
cat metadata/jbrowse2-configs/tracks/Nematostella_vectensis/GCA_033964005.1/maf/test_maf.json | jq .

# 3. Verify samples are present
cat metadata/jbrowse2-configs/tracks/Nematostella_vectensis/GCA_033964005.1/maf/test_maf.json | jq '.adapter.samples'

# Expected output:
# [
#   {
#     "id": "Nematostella_vectensis",
#     "label": "Nematostella vectensis",
#     "color": "rgba(230,25,75,0.7)"
#   },
#   {
#     "id": "Hydra_vulgaris",
#     "label": "Hydra vulgaris",
#     "color": "rgba(60,180,75,0.7)"
#   },
#   {
#     "id": "Acropora_digitifera",
#     "label": "Acropora digitifera",
#     "color": "rgba(255,225,25,0.7)"
#   }
# ]
```

---

## Manual Override Option

Users can also manually specify samples in the Google Sheet using the `maf` column:

**Format:** `id,label[,color];id,label[,color];...`

**Example:**
```
maf: Nematostella_vectensis,Nematostella vectensis;Hydra_vulgaris,Hydra vulgaris;Acropora_digitifera,Acropora digitifera
```

This will override auto-detection and use the provided metadata.

---

## Summary

**The MAF track system is 90% correct, but has ONE critical bug:**

1. ✅ Plugin installed correctly
2. ✅ Track type registered correctly  
3. ✅ Adapter configuration structure correct
4. ❌ **MISSING: samples array in adapter config**
5. ❌ **BUG: getSamples() uses wrong key name (file_path vs TRACK_PATH)**
6. ❌ **BUG: parseSamplesFromMAF() only works for true MAF format, not BED format**

**Once the samples parsing is fixed, MAF tracks should display correctly in JBrowse2.**

The fixes are minimal and surgical - just fixing the key name and adding BED format detection.
