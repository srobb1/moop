<?php
/**
 * VCF Track Type for JBrowse2
 * 
 * Handles VCF variant tracks with tabix indexing.
 * Supports SNPs, indels, and structural variants.
 * 
 * @package MOOP\JBrowse
 * @subpackage TrackTypes
 */

require_once __DIR__ . '/TrackTypeInterface.php';

class VCFTrack implements TrackTypeInterface
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
        return 'vcf';
    }
    
    /**
     * Validate track data specific to VCF track type
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
            $errors[] = "VCF file not found: $filePath";
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
                $errors[] = "Tabix index not found. Create with: tabix -p vcf $filePath";
            }
        }
        
        // Validate VCF file with tabix if available
        if (empty($errors) && $this->isTabixAvailable()) {
            $output = [];
            $returnCode = 0;
            exec("tabix -H " . escapeshellarg($filePath) . " 2>&1 | head -1", $output, $returnCode);
            if ($returnCode !== 0) {
                $errors[] = "Invalid VCF file format or not bgzip compressed";
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
            error_log("VCF track generation failed: " . $e->getMessage());
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
        return ['.vcf.gz'];
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
    private function findTbiIndex(string $vcfPath): ?string
    {
        // Try .vcf.gz.tbi
        $tbiPath = $vcfPath . '.tbi';
        if (file_exists($tbiPath)) {
            return $tbiPath;
        }
        
        // Try .tbi (replace .vcf.gz)
        $tbiPath = preg_replace('/\.vcf\.gz$/i', '.tbi', $vcfPath);
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
     * Get VCF statistics
     */
    private function getVcfStats(string $filePath, bool $skipStats = false): array
    {
        if ($skipStats || !$this->isTabixAvailable()) {
            return [
                'variant_count' => 'not_calculated',
                'sample_count' => 'not_calculated'
            ];
        }
        
        // Get variant count
        $output = [];
        exec("zcat " . escapeshellarg($filePath) . " | grep -v '^#' | wc -l 2>/dev/null", $output);
        $variantCount = isset($output[0]) ? (int)trim($output[0]) : 'unknown';
        
        // Get sample count from header
        $output = [];
        exec("zcat " . escapeshellarg($filePath) . " | grep '^#CHROM' | head -1 | awk '{print NF-9}' 2>/dev/null", $output);
        $sampleCount = isset($output[0]) ? (int)trim($output[0]) : 'unknown';
        
        return [
            'variant_count' => $variantCount,
            'sample_count' => $sampleCount
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
        $category = $options['category'] ?? 'Variants';
        $description = $options['description'] ?? '';
        $accessLevel = isset($options['access_level']) && !empty($options['access_level'])
            ? $options['access_level']
            : 'Public';
        $skipStats = $options['skip_stats'] ?? false;
        
        // Find TBI index
        $tbiPath = $this->findTbiIndex($filePath);
        if (!$tbiPath) {
            throw new Exception("TBI index not found for $filePath");
        }
        
        // Determine if remote or local
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        // Get URIs for web access
        if ($isRemote) {
            $vcfUri = $filePath;
            $tbiUri = $filePath . '.tbi';
        } else {
            $vcfUri = $this->pathResolver->toWebUri($filePath);
            $tbiUri = $this->pathResolver->toWebUri($tbiPath);
        }
        
        // Get VCF statistics
        $stats = $this->getVcfStats($filePath, $skipStats);
        
        // Build metadata structure
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'category' => [$category],
            'type' => 'VariantTrack',
            'adapter' => [
                'type' => 'VcfTabixAdapter',
                'vcfGzLocation' => [
                    'uri' => $vcfUri,
                    'locationType' => 'UriLocation'
                ],
                'index' => [
                    'location' => [
                        'uri' => $tbiUri,
                        'locationType' => 'UriLocation'
                    ]
                ]
            ],
            'displays' => [
                [
                    'type' => 'LinearVariantDisplay',
                    'displayId' => "{$trackId}-LinearVariantDisplay",
                    'renderer' => [
                        'type' => 'SvgFeatureRenderer'
                    ]
                ]
            ],
            'metadata' => [
                'description' => $description,
                'access_level' => $accessLevel,
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
                'variant_count' => $stats['variant_count'],
                'sample_count' => $stats['sample_count'],
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
        $basename = basename($filePath, '.vcf.gz');
        return strtolower("{$organism}_{$assembly}_{$basename}");
    }
    
    /**
     * Generate track name from filename
     */
    private function generateTrackName(string $filePath): string
    {
        $basename = basename($filePath, '.vcf.gz');
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
        // NOTE: Metadata is ALWAYS local, even if track files (VCF) are remote
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
