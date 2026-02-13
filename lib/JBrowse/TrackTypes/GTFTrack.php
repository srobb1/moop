<?php
/**
 * GTF Track Type for JBrowse2
 * 
 * Handles GTF gene annotation tracks with tabix indexing.
 * Common format for Ensembl transcripts and gene models.
 * 
 * @package MOOP\JBrowse
 * @subpackage TrackTypes
 */

require_once __DIR__ . '/TrackTypeInterface.php';

class GTFTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    public function __construct($pathResolver)
    {
        $this->pathResolver = $pathResolver;
        $this->config = ConfigManager::getInstance();
    }
    
    /**
     * Get the track type identifier
     */
    public function getType(): string
    {
        return 'gtf';
    }
    
    /**
     * Validate track data specific to GTF track type
     */
    public function validate($trackData)
    {
        $errors = [];
        
        // Check required fields
        $requiredFields = $this->getRequiredFields();
        foreach ($requiredFields as $field) {
            if (empty($trackData[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        $filePath = $trackData['TRACK_PATH'];
        
        // Check file exists
        if (!file_exists($filePath)) {
            $errors[] = "GTF file not found: $filePath";
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check extension
        $validExtensions = $this->getValidExtensions();
        $hasValidExt = false;
        foreach ($validExtensions as $ext) {
            if (preg_match('/' . preg_quote($ext, '/') . '$/i', $filePath)) {
                $hasValidExt = true;
                break;
            }
        }
        if (!$hasValidExt) {
            $errors[] = "Invalid file extension. Expected: " . implode(', ', $validExtensions);
        }
        
        // Check for TBI index
        if ($this->requiresIndex()) {
            $tbiPath = $this->findTbiIndex($filePath);
            if (!$tbiPath) {
                $errors[] = "Tabix index not found. Create with: tabix -p gff $filePath";
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
            $metadata = $this->buildMetadata($trackData['TRACK_PATH'], array_merge([
                'organism' => $organism,
                'assembly' => $assembly,
            ], $trackData, $options));
            
            if (empty($options['dry_run'])) {
                $this->writeMetadata($organism, $assembly, $metadata);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("GTF track generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get required fields
     */
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'TRACK_PATH'];
    }
    
    /**
     * Get valid file extensions
     */
    public function getValidExtensions()
    {
        return ['.gtf.gz', '.gtf'];
    }
    
    /**
     * Check if index is required
     */
    public function requiresIndex()
    {
        return true;
    }
    
    /**
     * Get index extensions
     */
    public function getIndexExtensions()
    {
        return ['.tbi'];
    }
    
    /**
     * Find TBI index file
     */
    private function findTbiIndex(string $gtfPath): ?string
    {
        $tbiPath = $gtfPath . '.tbi';
        if (file_exists($tbiPath)) {
            return $tbiPath;
        }
        
        $tbiPath = preg_replace('/\.gtf(\.gz)?$/i', '.tbi', $gtfPath);
        if (file_exists($tbiPath)) {
            return $tbiPath;
        }
        
        return null;
    }
    
    /**
     * Check if tabix is available
     */
    private function isTabixAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec("which tabix 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Build track metadata structure
     */
    public function buildMetadata(string $filePath, array $options): array
    {
        $organism = $options['organism'];
        $assembly = $options['assembly'];
        $trackId = $options['track_id'] ?? $this->generateTrackId($filePath, $organism, $assembly);
        $trackName = $options['name'] ?? $this->generateTrackName($filePath);
        $category = $options['category'] ?? 'Annotations';
        $description = $options['description'] ?? '';
        $accessLevel = isset($options['access_level']) && !empty($options['access_level'])
            ? $options['access_level']
            : 'Public';
        
        $tbiPath = $this->findTbiIndex($filePath);
        if (!$tbiPath) {
            throw new Exception("TBI index not found for $filePath");
        }
        
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        if ($isRemote) {
            $gtfUri = $filePath;
            $tbiUri = $filePath . '.tbi';
        } else {
            $gtfUri = $this->pathResolver->toWebUri($filePath);
            $tbiUri = $this->pathResolver->toWebUri($tbiPath);
        }
        
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'category' => [$category],
            'type' => 'FeatureTrack',
            'adapter' => [
                'type' => 'GtfAdapter',
                'gtfLocation' => [
                    'uri' => $gtfUri,
                    'locationType' => 'UriLocation'
                ]
            ],
            'displays' => [
                [
                    'type' => 'LinearBasicDisplay',
                    'displayId' => "{$trackId}-LinearBasicDisplay"
                ]
            ],
            'metadata' => [
                'description' => $description,
                'access_level' => $accessLevel,
                'file_path' => $filePath,
                'file_size' => $isRemote ? 'remote' : filesize($filePath),
                'is_remote' => $isRemote,
                'added_date' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ];
        
        $sheetsMetadata = $this->extractSheetsMetadata($options);
        if (!empty($sheetsMetadata)) {
            $metadata['metadata']['google_sheets_metadata'] = $sheetsMetadata;
        }
        
        if (isset($options['custom_metadata'])) {
            $metadata['metadata']['custom_fields'] = $options['custom_metadata'];
        }
        
        return $metadata;
    }
    
    /**
     * Extract Google Sheets metadata fields
     */
    private function extractSheetsMetadata(array $options): array
    {
        $fields = [
            'technique', 'institute', 'source', 'experiment',
            'developmental_stage', 'tissue', 'condition',
            'summary', 'citation', 'project', 'accession',
            'date', 'analyst'
        ];
        
        $metadata = [];
        foreach ($fields as $field) {
            if (isset($options[$field]) && $options[$field] !== '') {
                $metadata[$field] = $options[$field];
            }
        }
        
        return $metadata;
    }
    
    /**
     * Generate track ID from filename
     */
    private function generateTrackId(string $filePath, string $organism, string $assembly): string
    {
        $basename = basename($filePath);
        $basename = preg_replace('/\.gtf(\.gz)?$/i', '', $basename);
        return strtolower("{$organism}_{$assembly}_{$basename}");
    }
    
    /**
     * Generate track name from filename
     */
    private function generateTrackName(string $filePath): string
    {
        $basename = basename($filePath);
        $basename = preg_replace('/\.gtf(\.gz)?$/i', '', $basename);
        return str_replace('_', ' ', $basename);
    }
    
    /**
     * Write track metadata to JSON file
     */
    public function writeMetadata(string $organism, string $assembly, array $metadata): string
    {
        $trackId = $metadata['trackId'];
        $trackType = $this->getType();
        
        $metadataBase = $this->config->getPath('metadata_path');
        $trackDir = "$metadataBase/jbrowse2-configs/tracks/$organism/$assembly/$trackType";
        
        if (!is_dir($trackDir)) {
            if (!mkdir($trackDir, 0755, true)) {
                throw new Exception("Failed to create metadata directory: $trackDir");
            }
        }
        
        $metadataFile = $trackDir . '/' . $trackId . '.json';
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($metadataFile, $json) === false) {
            throw new Exception("Failed to write metadata file: $metadataFile");
        }
        
        return $metadataFile;
    }
}
