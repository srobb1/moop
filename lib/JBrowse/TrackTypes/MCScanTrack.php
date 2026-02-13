<?php

/**
 * MCScan Track Type Handler
 * 
 * Handles MCScan .anchors files for ortholog-based synteny visualization
 * Requires .bed files for both assemblies
 */

require_once __DIR__ . '/TrackTypeInterface.php';
require_once __DIR__ . '/../PathResolver.php';

class MCScanTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    public function __construct(PathResolver $pathResolver, $config)
    {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    /**
     * Get track type identifier
     */
    public function getType()
    {
        return 'mcscan';
    }
    
    /**
     * Get valid file extensions for this track type
     */
    public function getValidExtensions()
    {
        return ['.anchors'];
    }
    
    /**
     * Check if this track type requires index files
     */
    public function requiresIndex()
    {
        return true; // MCScan tracks require .bed files for both assemblies
    }
    
    /**
     * Get expected index file extension(s)
     */
    public function getIndexExtensions()
    {
        return ['.bed']; // BED files for gene positions
    }
    
    /**
     * Get required fields
     */
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'track_path', 'assembly1', 'assembly2', 'bed1_path', 'bed2_path'];
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
        
        // Check if files exist (for local files)
        if (!preg_match('/^https?:\/\//i', $path)) {
            if (!file_exists($path)) {
                $errors[] = "Anchors file not found: $path";
            }
            
            // Check BED files
            if (isset($trackData['bed1_path']) && !file_exists($trackData['bed1_path'])) {
                $errors[] = "BED1 file not found: {$trackData['bed1_path']}";
            }
            if (isset($trackData['bed2_path']) && !file_exists($trackData['bed2_path'])) {
                $errors[] = "BED2 file not found: {$trackData['bed2_path']}";
            }
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
            // Get both assemblies
            $assembly1 = $trackData['assembly1'];
            $assembly2 = $trackData['assembly2'];
            
            // Build metadata
            $metadata = $this->buildMetadata($trackData['track_path'], array_merge([
                'organism' => $organism,
                'assembly1' => $assembly1,
                'assembly2' => $assembly2,
            ], $trackData, $options));
            
            // Write metadata (stored under assembly1)
            if (empty($options['dry_run'])) {
                $this->writeMetadata($organism, $assembly1, $metadata);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("MCScan track generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build track metadata
     */
    public function buildMetadata(string $filePath, array $options): array
    {
        $organism = $options['organism'];
        $assembly1 = $options['assembly1'];
        $assembly2 = $options['assembly2'];
        $bed1Path = $options['bed1_path'];
        $bed2Path = $options['bed2_path'];
        $trackId = $options['track_id'] ?? $this->generateTrackId($filePath, $organism, $assembly1);
        $trackName = $options['name'] ?? $this->generateTrackName($filePath);
        $category = $options['category'] ?? 'Synteny';
        $description = $options['description'] ?? '';
        $accessLevel = isset($options['access_level']) && !empty($options['access_level'])
            ? $options['access_level']
            : 'Public';
        
        // Determine if remote or local
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        // Get URIs for web access
        if ($isRemote) {
            $anchorsUri = $filePath;
            $bed1Uri = $bed1Path;
            $bed2Uri = $bed2Path;
        } else {
            // Convert absolute paths to web URIs
            $anchorsUri = $this->pathResolver->toWebUri($filePath);
            $bed1Uri = $this->pathResolver->toWebUri($bed1Path);
            $bed2Uri = $this->pathResolver->toWebUri($bed2Path);
        }
        
        // Build metadata structure
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'organism' => $organism,
            'assembly1' => $assembly1,
            'assembly2' => $assembly2,
            'category' => [$category],
            'description' => $description,
            'metadata' => [
                'access_level' => $accessLevel,
                'track_type' => 'mcscan',
                'file_path' => $filePath,
                'bed1_path' => $bed1Path,
                'bed2_path' => $bed2Path,
                'is_remote' => $isRemote,
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
            'type' => 'SyntenyTrack',
            'trackId' => $trackId,
            'name' => $trackName,
            'category' => [$category],
            'assemblyNames' => [$assembly1, $assembly2],
            'adapter' => [
                'type' => 'MCScanAnchorsAdapter',
                'mcscanAnchorsLocation' => [
                    'uri' => $anchorsUri,
                    'locationType' => 'UriLocation'
                ],
                'bed1Location' => [
                    'uri' => $bed1Uri,
                    'locationType' => 'UriLocation'
                ],
                'bed2Location' => [
                    'uri' => $bed2Uri,
                    'locationType' => 'UriLocation'
                ],
                'assemblyNames' => [$assembly1, $assembly2]
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
    
    /**
     * Write metadata to file
     */
    private function writeMetadata(string $organism, string $assembly, array $metadata): void
    {
        $metadataDir = $this->config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
        $trackDir = "$metadataDir/$organism/$assembly/mcscan";
        
        if (!is_dir($trackDir)) {
            mkdir($trackDir, 0775, true);
        }
        
        $outputFile = "$trackDir/{$metadata['trackId']}.json";
        
        // Write JBrowse2 config only
        $success = file_put_contents(
            $outputFile,
            json_encode($metadata['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        if ($success === false) {
            throw new Exception("Failed to write metadata file: $outputFile");
        }
        
        chmod($outputFile, 0664);
    }
    
    /**
     * Generate track ID from filename
     */
    private function generateTrackId(string $filePath, string $organism, string $assembly): string
    {
        $filename = basename($filePath);
        // Remove extension
        $trackId = preg_replace('/\.anchors$/i', '', $filename);
        return $trackId;
    }
    
    /**
     * Generate track name from filename
     */
    private function generateTrackName(string $filePath): string
    {
        $filename = basename($filePath);
        // Remove extension and make readable
        $name = preg_replace('/\.anchors$/i', '', $filename);
        $name = str_replace(['_', '-'], ' ', $name);
        return ucwords($name);
    }
}
