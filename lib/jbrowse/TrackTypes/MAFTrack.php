<?php

/**
 * MAF Track Type Handler
 * 
 * Handles MAF (Multiple Alignment Format) tracks for multiple sequence alignments
 * Requires jbrowse-plugin-mafviewer plugin
 */

require_once __DIR__ . '/TrackTypeInterface.php';
require_once __DIR__ . '/BaseTrack.php';
require_once __DIR__ . '/../PathResolver.php';
require_once __DIR__ . '/../ColorSchemes.php';

class MAFTrack extends BaseTrack implements TrackTypeInterface
{
    
    public function __construct(PathResolver $pathResolver, $config)
    {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    /**
     * Get track type identifier
     */
    public function getType(): string
    {
        return 'maf';
    }
    
    /**
     * Get valid file extensions for this track type
     */
    public function getValidExtensions()
    {
        return ['.maf', '.maf.gz', '.bed.gz', '.taf.gz', '.bb'];
    }
    
    /**
     * Check if this track type requires index files
     */
    public function requiresIndex()
    {
        return true; // Compressed MAF files require index
    }
    
    /**
     * Get expected index file extension(s)
     */
    public function getIndexExtensions()
    {
        return ['.tbi', '.csi', '.tai']; // Tabix index (.tbi/.csi) or TAF index (.tai)
    }
    
    /**
     * Get required fields
     */
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'TRACK_PATH'];
    }
    
    /**
     * Validate track data
     */
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
        $path = $trackData['TRACK_PATH'];
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
            
            // Check for index files if compressed or BigBed
            if (preg_match('/\.(bed\.gz|maf\.gz)$/i', $path)) {
                // BED/MAF tabix format - needs .tbi or .csi
                $tbiPath = $path . '.tbi';
                $csiPath = $path . '.csi';
                if (!file_exists($tbiPath) && !file_exists($csiPath)) {
                    $errors[] = "Tabix index not found: Need either $tbiPath or $csiPath";
                }
            } elseif (preg_match('/\.taf\.gz$/i', $path)) {
                // TAF format - needs .tai
                $taiPath = $path . '.tai';
                if (!file_exists($taiPath)) {
                    $errors[] = "TAF index not found: $taiPath";
                }
            }
            // BigBed (.bb) files don't need separate index
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate track
     */
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        try {
            // Build metadata
            $metadata = $this->buildMetadata($trackData['TRACK_PATH'], array_merge([
                'organism' => $organism,
                'assembly' => $assembly,
            ], $trackData, $options));
            
            // Write metadata
            if (empty($options['dry_run'])) {
                $this->writeMetadata($organism, $assembly, $metadata);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("MAF track generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build track metadata
     */
    public function buildMetadata(string $filePath, array $options): array
    {
        $organism = $options['organism'];
        $assembly = $options['assembly'];
        $trackId = $options['track_id'] ?? $this->generateTrackId($filePath, $organism, $assembly);
        $browserTrackId = $options['browser_track_id'] ?? $trackId;
        $trackName = $options['name'] ?? $this->generateTrackName($filePath);
        $category = $options['category'] ?? 'Alignment';
        $description = $options['description'] ?? '';
        $accessLevel = isset($options['access_level']) && !empty($options['access_level'])
            ? $options['access_level']
            : 'Public';
        
        // Determine if remote or local
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        // Determine adapter type and configuration based on file extension
        $adapterConfig = $this->buildAdapterConfig($filePath, $options, $isRemote);
        
        // Build metadata structure
        $metadata = [
            'trackId' => $browserTrackId,
            'name' => $trackName,
            'organism' => $organism,
            'assembly' => $assembly,
            'category' => [$category],
            'description' => $description,
            'metadata' => [
                'management_track_id' => $trackId,
                'access_level' => $accessLevel,
                'track_type' => 'maf',
                'file_path' => $filePath,
                'is_remote' => $isRemote,
                'adapter_type' => $adapterConfig['type'],
                'date_created' => date('Y-m-d H:i:s'),
            ]
        ];
        
        // Add all optional metadata fields
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
        
        // Build JBrowse2 track configuration
        $metadata['config'] = [
            'type' => 'MafTrack',
            'trackId' => $trackId,
            'name' => $trackName,
            'category' => [$category],
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'adapter' => $adapterConfig,
            'metadata' => [
                'management_track_id' => $trackId,
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
    
    /**
     * Build adapter configuration based on file type
     */
    private function buildAdapterConfig(string $filePath, array $options, bool $isRemote): array
    {
        // Get URIs for web access
        if ($isRemote) {
            $fileUri = $filePath;
        } else {
            $fileUri = $this->pathResolver->toWebUri($filePath);
        }
        
        // Detect adapter type based on file extension and available indices
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
            // Uncompressed MAF (not commonly used, but fall back to MafTabix)
            return $this->buildMafTabixAdapter($filePath, $fileUri, $options, $isRemote);
        }
    }
    
    /**
     * Build BigMafAdapter configuration
     */
    private function buildBigMafAdapter(string $fileUri, array $options): array
    {
        $config = [
            'type' => 'BigMafAdapter',
            'bigBedLocation' => [
                'uri' => $fileUri,
                'locationType' => 'UriLocation'
            ]
        ];
        
        // Add samples if provided
        $samples = $this->getSamples($options);
        if (!empty($samples)) {
            $config['samples'] = $samples;
        }
        
        return $config;
    }
    
    /**
     * Build MafTabixAdapter configuration
     */
    private function buildMafTabixAdapter(string $filePath, string $fileUri, array $options, bool $isRemote): array
    {
        $config = [
            'type' => 'MafTabixAdapter',
            'bedGzLocation' => [
                'uri' => $fileUri,
                'locationType' => 'UriLocation'
            ]
        ];
        
        // Determine index type and location
        $indexType = 'TBI'; // Default
        $indexUri = null;
        
        if ($isRemote) {
            // Try .tbi first, then .csi
            $indexUri = $fileUri . '.tbi';
            // Could check if .csi exists, but default to .tbi for remote
        } else {
            // Check local filesystem
            $tbiPath = $filePath . '.tbi';
            $csiPath = $filePath . '.csi';
            
            if (file_exists($csiPath)) {
                $indexType = 'CSI';
                $indexUri = $this->pathResolver->toWebUri($csiPath);
            } elseif (file_exists($tbiPath)) {
                $indexType = 'TBI';
                $indexUri = $this->pathResolver->toWebUri($tbiPath);
            } else {
                // Default to .tbi even if not found (will error later)
                $indexUri = $this->pathResolver->toWebUri($tbiPath);
            }
        }
        
        $config['index'] = [
            'indexType' => $indexType,
            'location' => [
                'uri' => $indexUri,
                'locationType' => 'UriLocation'
            ]
        ];
        
        // Add samples if provided
        $samples = $this->getSamples($options);
        if (!empty($samples)) {
            $config['samples'] = $samples;
        }
        
        return $config;
    }
    
    /**
     * Build BgzipTaffyAdapter configuration
     */
    private function buildBgzipTaffyAdapter(string $filePath, string $fileUri, array $options, bool $isRemote): array
    {
        $config = [
            'type' => 'BgzipTaffyAdapter',
            'tafGzLocation' => [
                'uri' => $fileUri,
                'locationType' => 'UriLocation'
            ]
        ];
        
        // Add .tai index
        if ($isRemote) {
            $taiUri = $fileUri . '.tai';
        } else {
            $taiPath = $filePath . '.tai';
            $taiUri = $this->pathResolver->toWebUri($taiPath);
        }
        
        $config['taiLocation'] = [
            'uri' => $taiUri,
            'locationType' => 'UriLocation'
        ];
        
        // Add samples if provided
        $samples = $this->getSamples($options);
        if (!empty($samples)) {
            $config['samples'] = $samples;
        }
        
        return $config;
    }
    
    /**
     * Get samples array from options
     */
    private function getSamples(array $options): array
    {
        // Parse samples from 'maf' column if provided
        if (isset($options['maf']) && !empty($options['maf'])) {
            return $this->parseMafColumn($options['maf']);
        }
        
        // Auto-detect from local file if not remote
        // Use TRACK_PATH (the actual key from Google Sheets)
        $filePath = $options['TRACK_PATH'] ?? null;
        if ($filePath && !preg_match('/^https?:\/\//i', $filePath)) {
            return $this->parseSamplesFromFile($filePath);
        }
        
        return [];
    }
    
    /**
     * Write metadata to file
     */
    
    /**
     * Generate track ID from filename
     */
    private function generateTrackId(string $filePath, string $organism, string $assembly): string
    {
        $filename = basename($filePath);
        // Remove extension
        $trackId = preg_replace('/\.(maf|maf\.gz)$/i', '', $filename);
        return $trackId;
    }
    
    /**
     * Generate track name from filename
     */
    private function generateTrackName(string $filePath): string
    {
        $filename = basename($filePath);
        // Remove extension and make readable
        $name = preg_replace('/\.(maf|maf\.gz)$/i', '', $filename);
        $name = str_replace(['_', '-'], ' ', $name);
        return ucwords($name);
    }
    
    /**
     * Parse MAF column format: id,label[,color];id,label[,color];...
     * 
     * Examples:
     *   hg38,Human;panTro6,Chimp;gorGor6,Gorilla
     *   hg38,Human,rgba(255,255,255,0.7);panTro6,Chimp,rgba(255,200,200,0.7)
     *   hg38,Human,rgba(255,255,255,0.7);panTro6,Chimp
     */
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
                    : $this->getDefaultColor($i)
            ];
            
            $samples[] = $sample;
        }
        
        return $samples;
    }
    
    /**
     * Auto-detect file format and parse samples accordingly
     * Supports both true MAF format and BED format with encoded MAF data
     */
    private function parseSamplesFromFile(string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                error_log("File not found for sample parsing: $filePath");
                return [];
            }
            
            $handle = gzopen($filePath, 'r');
            if (!$handle) {
                error_log("Failed to open file: $filePath");
                return [];
            }
            
            // Read first non-comment line to detect format
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
                // BED format with encoded MAF data
                return $this->parseSamplesFromBedMAF($filePath);
            }
            
        } catch (Exception $e) {
            error_log("Failed to detect and parse file format: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Parse samples from BED format MAF file
     * BED12+13 format: chrom, start, end, name, score, strand, ...encoded_alignments...
     * Column 7 format: genome.scaffold:start:len:strand:size:seq,genome2.scaffold:...
     */
    private function parseSamplesFromBedMAF(string $filePath): array
    {
        $sampleIds = [];
        
        try {
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
     * Parse true MAF file to extract sample IDs and build default samples array
     * True MAF format has lines starting with 's' for sequence records
     */
    private function parseSamplesFromMAF(string $filePath): array
    {
        $sampleIds = [];
        
        try {
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
                // Example: "s hg38.chr1 1000 100 + 248956422 ATCG..."
                if (preg_match('/^s\s+(\S+?)\./', $line, $matches)) {
                    $sampleIds[$matches[1]] = true;
                }
                $lineCount++;
            }
            gzclose($handle);
            
            // Build samples array with id as label and default colors
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
            error_log("Failed to parse MAF samples: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get default color from rainbow palette for given index
     * Uses the ColorSchemes 'rainbow' palette for maximum variety
     */
    private function getDefaultColor(int $index): string
    {
        // Get rainbow palette from ColorSchemes
        $palette = ColorSchemes::getScheme('rainbow');
        
        if (!$palette) {
            // Fallback palette if ColorSchemes fails
            $palette = [
                '#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4',
                '#46f0f0', '#f032e6', '#bcf60c', '#fabebe', '#008080', '#e6beff',
                '#9a6324', '#fffac8', '#800000', '#aaffc3', '#808000', '#ffd8b1',
                '#000075', '#808080'
            ];
        }
        
        // Convert hex to rgba with 0.7 opacity
        $color = $palette[$index % count($palette)];
        
        // If already in rgba format, return as-is
        if (strpos($color, 'rgba') === 0) {
            return $color;
        }
        
        // Convert hex to rgba
        if (strpos($color, '#') === 0) {
            $hex = ltrim($color, '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return "rgba($r,$g,$b,0.7)";
        }
        
        // Named color - return with opacity note (JBrowse may handle differently)
        return $color;
    }
}
