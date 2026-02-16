# Implementation Plan: MAF and BigBed Support for Cactus Alignments

## Current Status

✅ **MAFTrack.php exists** - `/data/moop/lib/JBrowse/TrackTypes/MAFTrack.php`
- Handles .maf.gz files
- Uses MafAdapter
- Requires .gzi index
- Has sample metadata support

⚠️ **MAF NOT registered in TrackGenerator.php**
- Currently commented out as "dual-assembly"
- Needs to be added to single-assembly track types

❌ **BigBedTrack.php does not exist**
- Would handle .bb/.bigbed files (including BigMaf)
- Uses BigBedAdapter (core JBrowse2)
- Optional - only needed if users want BigMaf format

## Implementation Steps

### Step 1: Register MAF Track Type (Required)

**File:** `/data/moop/lib/JBrowse/TrackGenerator.php`

```php
private function registerTrackTypes()
{
    $trackTypeClasses = [
        'bigwig' => 'BigWigTrack',
        'bam' => 'BamTrack',
        'combo' => 'ComboTrack',
        'auto' => 'AutoTrack',
        'vcf' => 'VCFTrack',
        'bed' => 'BEDTrack',
        'gtf' => 'GTFTrack',
        'gff' => 'GFFTrack',
        'cram' => 'CRAMTrack',
        'maf' => 'MAFTrack',  // ← ADD THIS
    ];
}
```

**Status:** ✅ Done (just updated)

### Step 2: Enhance MAFTrack.php Sample Parsing (Recommended)

**File:** `/data/moop/lib/JBrowse/TrackTypes/MAFTrack.php`

Add support for three sample metadata formats:

```php
public function buildMetadata(string $filePath, array $options): array
{
    // ... existing code ...
    
    // Parse samples - three options:
    $samples = [];
    
    // Option 1: JSON array in 'samples' field
    if (isset($options['samples']) && is_string($options['samples'])) {
        $samples = json_decode($options['samples'], true) ?? [];
    }
    elseif (isset($options['samples']) && is_array($options['samples'])) {
        $samples = $options['samples'];
    }
    
    // Option 2: Separate columns (sample_ids, sample_labels, sample_colors)
    elseif (isset($options['sample_ids'])) {
        $ids = array_map('trim', explode(',', $options['sample_ids']));
        $labels = isset($options['sample_labels']) 
            ? array_map('trim', explode(',', $options['sample_labels']))
            : $ids;
        $colors = isset($options['sample_colors'])
            ? array_map('trim', explode(';', $options['sample_colors']))
            : [];
            
        foreach ($ids as $i => $id) {
            $samples[] = [
                'id' => $id,
                'label' => $labels[$i] ?? $id,
                'color' => $colors[$i] ?? $this->getDefaultColor($i)
            ];
        }
    }
    
    // Option 3: Auto-detect from local MAF file
    elseif (!preg_match('/^https?:\/\//i', $filePath)) {
        $samples = $this->parseSamplesFromMAF($filePath);
    }
    
    // Add samples to adapter config
    if (!empty($samples)) {
        $adapterConfig['samples'] = $samples;
    }
    
    // ... rest of existing code ...
}

private function parseSamplesFromMAF(string $filePath): array
{
    $sampleIds = [];
    
    try {
        $handle = gzopen($filePath, 'r');
        if (!$handle) {
            return [];
        }
        
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
                'label' => $id,
                'color' => $this->getDefaultColor($i++)
            ];
        }
        
        return $samples;
    } catch (Exception $e) {
        error_log("Failed to parse MAF samples: " . $e->getMessage());
        return [];
    }
}

private function getDefaultColor(int $index): string
{
    $palette = [
        'rgba(255,255,255,0.7)', 'rgba(255,200,200,0.7)',
        'rgba(200,200,255,0.7)', 'rgba(255,255,200,0.7)',
        'rgba(200,255,200,0.7)', 'rgba(255,200,255,0.7)',
        'rgba(200,255,255,0.7)', 'rgba(255,220,200,0.7)',
        'rgba(220,255,200,0.7)', 'rgba(200,220,255,0.7)',
        'rgba(255,180,180,0.7)', 'rgba(180,180,255,0.7)',
        'rgba(255,255,180,0.7)', 'rgba(180,255,180,0.7)',
        'rgba(255,180,255,0.7)', 'rgba(180,255,255,0.7)',
    ];
    return $palette[$index % count($palette)];
}
```

**Status:** 📝 Needs implementation

### Step 3: Create BigBedTrack.php (Optional)

**File:** `/data/moop/lib/JBrowse/TrackTypes/BigBedTrack.php`

```php
<?php

/**
 * BigBed Track Type Handler
 * 
 * Handles BigBed format tracks, including BigMaf (MAF converted to BigBed)
 * Uses core JBrowse2 BigBedAdapter - no plugin required
 */

require_once __DIR__ . '/TrackTypeInterface.php';
require_once __DIR__ . '/../PathResolver.php';

class BigBedTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    public function __construct(PathResolver $pathResolver, $config)
    {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    public function getType()
    {
        return 'bigbed';
    }
    
    public function getValidExtensions()
    {
        return ['.bb', '.bigbed'];
    }
    
    public function requiresIndex()
    {
        return false; // BigBed has built-in index
    }
    
    public function getIndexExtensions()
    {
        return [];
    }
    
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'track_path'];
    }
    
    public function validate($trackData)
    {
        $errors = [];
        
        // Check required fields
        foreach ($this->getRequiredFields() as $field) {
            if (!isset($trackData[$field]) || empty($trackData[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate file extension
        $path = $trackData['track_path'];
        $validExt = false;
        foreach ($this->getValidExtensions() as $ext) {
            if (preg_match('/' . preg_quote($ext, '/') . '$/i', $path)) {
                $validExt = true;
                break;
            }
        }
        
        if (!$validExt) {
            $errors[] = "Invalid file extension. Expected: " . implode(', ', $this->getValidExtensions());
        }
        
        // Check if file exists (for local files)
        if (!preg_match('/^https?:\/\//i', $path)) {
            if (!file_exists($path)) {
                $errors[] = "File not found: $path";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        try {
            $metadata = $this->buildMetadata($trackData['track_path'], array_merge([
                'organism' => $organism,
                'assembly' => $assembly,
            ], $trackData, $options));
            
            if (empty($options['dry_run'])) {
                $this->writeMetadata($organism, $assembly, $metadata);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("BigBed track generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function buildMetadata(string $filePath, array $options): array
    {
        $organism = $options['organism'];
        $assembly = $options['assembly'];
        $trackId = $options['track_id'] ?? $this->generateTrackId($filePath);
        $trackName = $options['name'] ?? $this->generateTrackName($filePath);
        $category = $options['category'] ?? 'Annotation';
        $description = $options['description'] ?? '';
        $accessLevel = $options['access_level'] ?? 'Public';
        
        // Determine if remote or local
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        // Get URI for web access
        $bigBedUri = $isRemote 
            ? $filePath 
            : $this->pathResolver->toWebUri($filePath);
        
        // Build metadata
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'organism' => $organism,
            'assembly' => $assembly,
            'category' => [$category],
            'description' => $description,
            'metadata' => [
                'access_level' => $accessLevel,
                'track_type' => 'bigbed',
                'file_path' => $filePath,
                'is_remote' => $isRemote,
                'date_created' => date('Y-m-d H:i:s'),
            ]
        ];
        
        // Add optional metadata fields
        $optionalFields = [
            'technique', 'institute', 'source', 'experiment',
            'developmental_stage', 'tissue', 'condition',
            'summary', 'citation', 'project', 'accession',
            'date', 'analyst', 'sciprj', 'biosample', 'ngs_file', 'mlong'
        ];
        
        foreach ($optionalFields as $field) {
            if (isset($options[$field]) && !empty($options[$field])) {
                $metadata['metadata'][$field] = $options[$field];
            }
        }
        
        // Build JBrowse2 config
        $metadata['config'] = [
            'type' => 'FeatureTrack',
            'trackId' => $trackId,
            'name' => $trackName,
            'category' => [$category],
            'assemblyNames' => [$assembly],
            'adapter' => [
                'type' => 'BigBedAdapter',
                'bigBedLocation' => [
                    'uri' => $bigBedUri,
                    'locationType' => 'UriLocation'
                ]
            ],
            'metadata' => [
                'access_level' => $accessLevel,
            ]
        ];
        
        // Add optional metadata to config
        foreach ($optionalFields as $field) {
            if (isset($options[$field]) && !empty($options[$field])) {
                $metadata['config']['metadata'][$field] = $options[$field];
            }
        }
        
        return $metadata;
    }
    
    private function writeMetadata(string $organism, string $assembly, array $metadata): void
    {
        $metadataDir = $this->config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
        $trackDir = "$metadataDir/$organism/$assembly/bigbed";
        
        if (!is_dir($trackDir)) {
            mkdir($trackDir, 0775, true);
        }
        
        $outputFile = "$trackDir/{$metadata['trackId']}.json";
        
        $success = file_put_contents(
            $outputFile,
            json_encode($metadata['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        if ($success === false) {
            throw new Exception("Failed to write metadata file: $outputFile");
        }
        
        chmod($outputFile, 0664);
    }
    
    private function generateTrackId(string $filePath): string
    {
        $filename = basename($filePath);
        return preg_replace('/\.(bb|bigbed)$/i', '', $filename);
    }
    
    private function generateTrackName(string $filePath): string
    {
        $filename = basename($filePath);
        $name = preg_replace('/\.(bb|bigbed)$/i', '', $filename);
        $name = str_replace(['_', '-'], ' ', $name);
        return ucwords($name);
    }
}
```

Then register in TrackGenerator.php:

```php
$trackTypeClasses = [
    // ... existing types ...
    'bigbed' => 'BigBedTrack',
];
```

**Status:** 📝 Optional - only if users need BigMaf support

### Step 4: Update Google Sheet Column Documentation

**Add to track generation documentation:**

For MAF tracks, you can specify sample metadata in three ways:

1. **JSON in samples column:**
```
samples: [{"id":"hg38","label":"Human","color":"rgba(255,255,255,0.7)"},{"id":"panTro6","label":"Chimp","color":"rgba(255,200,200,0.7)"}]
```

2. **Separate columns:**
```
sample_ids: hg38,panTro6,gorGor6
sample_labels: Human,Chimp,Gorilla
sample_colors: rgba(255,255,255,0.7);rgba(255,200,200,0.7);rgba(200,200,255,0.7)
```

3. **Auto-detect (local files only):**
```
(leave samples columns empty - will parse MAF file)
```

## Testing Plan

### Test 1: Simple MAF with Auto-Detection

```tsv
track_id | name | category | track_path | organism | assembly
test_maf | Test MAF | Alignment | /data/test.maf.gz | Homo_sapiens | hg38
```

**Expected:** Parse MAF, extract sample IDs, assign default colors

### Test 2: MAF with JSON Samples

```tsv
track_id | name | category | track_path | organism | assembly | samples
cactus_primates | Primate Alignment | Conservation | /data/primates.maf.gz | Homo_sapiens | hg38 | [{"id":"hg38","label":"Human","color":"rgba(255,255,255,0.7)"},{"id":"panTro6","label":"Chimp","color":"rgba(255,200,200,0.7)"}]
```

**Expected:** Use provided samples with custom labels and colors

### Test 3: MAF with Separate Columns

```tsv
track_id | name | category | track_path | organism | assembly | sample_ids | sample_labels | sample_colors
cactus_20mammals | 20 Mammals | Conservation | /data/mammals.maf.gz | Homo_sapiens | hg38 | hg38,panTro6 | Human,Chimp | rgba(255,255,255,0.7);rgba(255,200,200,0.7)
```

**Expected:** Parse columns, build samples array

### Test 4: BigBed (Optional)

```tsv
track_id | name | category | track_path | organism | assembly
test_bigbed | Test BigBed | Alignment | /data/test.bb | Homo_sapiens | hg38
```

**Expected:** Generate FeatureTrack with BigBedAdapter

## Priority

1. **High:** Register MAF in TrackGenerator.php ✅ DONE
2. **Medium:** Enhance MAFTrack.php with sample parsing (Options 1-3)
3. **Low:** Create BigBedTrack.php (only if users request BigMaf)

## Notes

- MAF is now correctly classified as single-assembly track
- BigMaf is just MAF data in BigBed format - uses same BigBedAdapter
- Sample metadata is critical for multi-genome alignments
- Reference assembly goes in organism/assembly columns
- All aligned genomes (including reference) go in samples array
