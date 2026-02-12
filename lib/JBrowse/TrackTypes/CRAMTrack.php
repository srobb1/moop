<?php
/**
 * CRAM Track Type for JBrowse2
 * 
 * Handles CRAM alignment tracks with paired-end support and indexing.
 * 
 * @package MOOP\JBrowse
 * @subpackage TrackTypes
 */

require_once __DIR__ . '/TrackTypeInterface.php';

class CramTrack implements TrackTypeInterface
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
        return 'cram';
    }
    
    /**
     * Validate track data specific to CRAM track type
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
            $errors[] = "CRAM file not found: $filePath";
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
        
        // Check for CRAI index
        if ($this->requiresIndex()) {
            $baiPath = $this->findBaiIndex($filePath);
            if (!$baiPath) {
                $errors[] = "CRAI index not found. Create with: samtools index $filePath";
            }
        }
        
        // Validate CRAM file with samtools if available
        if (empty($errors) && $this->isSamtoolsAvailable()) {
            $output = [];
            $returnCode = 0;
            exec("samtools quickcheck " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                $errors[] = "Invalid CRAM file format";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate track (new PHP approach - no bash scripts)
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
            error_log("CRAM track generation failed: " . $e->getMessage());
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
        return ['.cram'];
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
        return ['.crai'];
    }
    
    /**
     * Find CRAI index file
     */
    private function findBaiIndex(string $cramPath): ?string
    {
        // Try .cram.crai
        $baiPath = $cramPath . '.crai';
        if (file_exists($baiPath)) {
            return $baiPath;
        }
        
        // Try .crai (replace .cram)
        $baiPath = preg_replace('/\.cram$/i', '.crai', $cramPath);
        if (file_exists($baiPath)) {
            return $baiPath;
        }
        
        return null;
    }
    
    /**
     * Check if samtools is available
     */
    private function isSamtoolsAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec("which samtools 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Get CRAM statistics
     */
    private function getCramStats(string $filePath, bool $skipStats = false): array
    {
        if ($skipStats || !$this->isSamtoolsAvailable()) {
            return [
                'total_reads' => 'not_calculated',
                'mapped_reads' => 'not_calculated'
            ];
        }
        
        // Get total reads
        $output = [];
        exec("samtools view -c " . escapeshellarg($filePath) . " 2>/dev/null", $output);
        $totalReads = isset($output[0]) ? (int)$output[0] : 'unknown';
        
        // Get mapped reads (exclude unmapped flag 0x4)
        $output = [];
        exec("samtools view -c -F 4 " . escapeshellarg($filePath) . " 2>/dev/null", $output);
        $mappedReads = isset($output[0]) ? (int)$output[0] : 'unknown';
        
        return [
            'total_reads' => $totalReads,
            'mapped_reads' => $mappedReads
        ];
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
        $category = $options['category'] ?? 'Alignments';
        $description = $options['description'] ?? '';
        $accessLevel = $options['access'] ?? 'Public';
        $skipStats = $options['skip_stats'] ?? false;
        
        // Find CRAI index
        $baiPath = $this->findBaiIndex($filePath);
        if (!$baiPath) {
            throw new Exception("CRAI index not found for $filePath");
        }
        
        // Determine if remote or local
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        // Get URIs for web access
        if ($isRemote) {
            $cramUri = $filePath;
            $baiUri = $filePath . '.crai';
        } else {
            $cramUri = $this->pathResolver->toWebUri($filePath);
            $baiUri = $this->pathResolver->toWebUri($baiPath);
        }
        
        // Get CRAM statistics
        $stats = $this->getCramStats($filePath, $skipStats);
        
        // Build metadata structure
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'category' => [$category],
            'type' => 'AlignmentsTrack',
            'adapter' => [
                'type' => 'CramAdapter',
                'cramLocation' => [
                    'uri' => $cramUri,
                    'locationType' => 'UriLocation'
                ],
                'index' => [
                    'location' => [
                        'uri' => $baiUri,
                        'locationType' => 'UriLocation'
                    ]
                ]
            ],
            'displays' => [
                [
                    'type' => 'LinearAlignmentsDisplay',
                    'displayId' => "{$trackId}-LinearAlignmentsDisplay"
                ],
                [
                    'type' => 'LinearPileupDisplay',
                    'displayId' => "{$trackId}-LinearPileupDisplay"
                ]
            ],
            'metadata' => [
                'description' => $description,
                'access_level' => $accessLevel,
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
                'total_reads' => $stats['total_reads'],
                'mapped_reads' => $stats['mapped_reads'],
                'is_remote' => $isRemote,
                'added_date' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ];
        
        // Add Google Sheets metadata if provided
        $sheetsMetadata = $this->extractSheetsMetadata($options);
        if (!empty($sheetsMetadata)) {
            $metadata['metadata']['google_sheets_metadata'] = $sheetsMetadata;
        }
        
        // Add custom fields if provided
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
        $basename = basename($filePath, '.cram');
        return strtolower("{$organism}_{$assembly}_{$basename}");
    }
    
    /**
     * Generate track name from filename
     */
    private function generateTrackName(string $filePath): string
    {
        $basename = basename($filePath, '.cram');
        return str_replace('_', ' ', $basename);
    }
    
    /**
     * Write track metadata to JSON file
     */
    public function writeMetadata(string $organism, string $assembly, array $metadata): string
    {
        $trackId = $metadata['trackId'];
        $trackType = $this->getType();
        
        // Get metadata directory from ConfigManager
        // NOTE: Metadata is ALWAYS local, even if track files (CRAM) are remote
        $metadataBase = $this->config->getPath('metadata_path');
        $trackDir = "$metadataBase/jbrowse2-configs/tracks/$organism/$assembly/$trackType";
        
        // Create directory if needed
        if (!is_dir($trackDir)) {
            if (!mkdir($trackDir, 0755, true)) {
                throw new Exception("Failed to create metadata directory: $trackDir");
            }
        }
        
        // Write JSON file
        $metadataFile = $trackDir . '/' . $trackId . '.json';
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($metadataFile, $json) === false) {
            throw new Exception("Failed to write metadata file: $metadataFile");
        }
        
        return $metadataFile;
    }
}
