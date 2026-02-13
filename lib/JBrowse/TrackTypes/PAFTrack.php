<?php

/**
 * PAF Track Type Handler
 * 
 * Handles PAF (Pairwise mApping Format) tracks for long-read alignments
 * PAF is minimap2's output format for pairwise sequence alignments
 */

require_once __DIR__ . '/TrackTypeInterface.php';
require_once __DIR__ . '/../PathResolver.php';

class PAFTrack implements TrackTypeInterface
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
        return 'paf';
    }
    
    /**
     * Get valid file extensions for this track type
     */
    public function getValidExtensions()
    {
        return ['.paf', '.paf.gz'];
    }
    
    /**
     * Check if this track type requires index files
     */
    public function requiresIndex()
    {
        return false; // PAF files don't require index
    }
    
    /**
     * Get expected index file extension(s)
     */
    public function getIndexExtensions()
    {
        return []; // No index files
    }
    
    /**
     * Get required fields
     */
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'track_path'];
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
    
    /**
     * Generate track
     */
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        try {
            // Build metadata
            $metadata = $this->buildMetadata($trackData['track_path'], array_merge([
                'organism' => $organism,
                'assembly' => $assembly,
            ], $trackData, $options));
            
            // Write metadata
            if (empty($options['dry_run'])) {
                $this->writeMetadata($organism, $assembly, $metadata);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("PAF track generation failed: " . $e->getMessage());
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
        $trackName = $options['name'] ?? $this->generateTrackName($filePath);
        $category = $options['category'] ?? 'Long-read Alignments';
        $description = $options['description'] ?? '';
        $accessLevel = isset($options['access_level']) && !empty($options['access_level'])
            ? $options['access_level']
            : 'Public';
        
        // Determine if remote or local
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        // Get URIs for web access
        if ($isRemote) {
            $pafUri = $filePath;
        } else {
            // Convert absolute path to web URI
            $pafUri = $this->pathResolver->toWebUri($filePath);
        }
        
        // Build metadata structure
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'organism' => $organism,
            'assembly' => $assembly,
            'category' => [$category],
            'description' => $description,
            'metadata' => [
                'access_level' => $accessLevel,
                'track_type' => 'paf',
                'file_path' => $filePath,
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
            'type' => 'AlignmentsTrack',
            'trackId' => $trackId,
            'name' => $trackName,
            'category' => [$category],
            'assemblyNames' => [$assembly],
            'adapter' => [
                'type' => 'PAFAdapter',
                'pafLocation' => [
                    'uri' => $pafUri,
                    'locationType' => 'UriLocation'
                ],
                'assemblyNames' => [$assembly]
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
        $trackDir = "$metadataDir/$organism/$assembly/paf";
        
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
        $trackId = preg_replace('/\.(paf|paf\.gz)$/i', '', $filename);
        return $trackId;
    }
    
    /**
     * Generate track name from filename
     */
    private function generateTrackName(string $filePath): string
    {
        $filename = basename($filePath);
        // Remove extension and make readable
        $name = preg_replace('/\.(paf|paf\.gz)$/i', '', $filename);
        $name = str_replace(['_', '-'], ' ', $name);
        return ucwords($name);
    }
}
