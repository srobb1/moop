<?php
/**
 * BigWigTrack - Handler for BigWig track generation
 * 
 * BigWig format is used for continuous-valued data (e.g., RNA-seq coverage,
 * ChIP-seq signal). Commonly used for visualization in genome browsers.
 * 
 * Supported extensions: .bw, .bigwig, .bigWig
 * 
 * @package MOOP\JBrowse\TrackTypes
 */

require_once __DIR__ . '/TrackTypeInterface.php';
require_once __DIR__ . '/../PathResolver.php';

class BigWigTrack implements TrackTypeInterface
{
    /**
     * @var PathResolver
     */
    private $pathResolver;
    
    /**
     * @var ConfigManager
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param PathResolver $pathResolver Path resolution handler
     * @param ConfigManager $config Configuration manager
     */
    public function __construct($pathResolver, $config)
    {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    /**
     * Validate BigWig track data
     * 
     * @param array $trackData Track data from Google Sheet
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate($trackData)
    {
        $errors = [];
        
        // Check required fields
        foreach ($this->getRequiredFields() as $field) {
            if (empty($trackData[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // If missing required fields, return early
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate file extension
        $path = $trackData['TRACK_PATH'];
        $validExtension = false;
        foreach ($this->getValidExtensions() as $ext) {
            if (preg_match('/' . preg_quote($ext, '/') . '$/i', $path)) {
                $validExtension = true;
                break;
            }
        }
        
        if (!$validExtension) {
            $errors[] = "Invalid file extension. Expected: " . implode(', ', $this->getValidExtensions());
        }
        
        // Check if file exists (for local files)
        if (!$this->pathResolver->isRemote($path)) {
            if (!$this->pathResolver->fileExists($path)) {
                $errors[] = "File not found: $path";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate BigWig track metadata JSON directly
     * 
     * No longer calls shell script - generates JSON in PHP for better
     * control, portability, and integration.
     * 
     * @param array $trackData Track data from Google Sheet
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param array $options Optional: force, dry_run, etc.
     * @return bool True if track created successfully
     */
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        try {
            // Get file path
            $filePath = $trackData['TRACK_PATH'];
            $trackId = $trackData['track_id'];
            $name = $trackData['name'];
            
            // Build metadata JSON structure
            $metadata = $this->buildMetadata($trackData, $organism, $assembly);
            
            // Dry run - just show what would be created
            if (!empty($options['dry_run'])) {
                echo "  [DRY RUN] Would create track: {$trackId}\n";
                echo "  [DRY RUN] Metadata: " . json_encode($metadata, JSON_PRETTY_PRINT) . "\n";
                return true;
            }
            
            // Write metadata to file
            $this->writeMetadata($metadata, $organism, $assembly, $trackId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("BigWigTrack generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build track metadata JSON structure
     * 
     * @param array $trackData Track information
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array Metadata structure
     */
    private function buildMetadata($trackData, $organism, $assembly)
    {
        $trackId = $trackData['track_id'];
        $name = $trackData['name'];
        $filePath = $trackData['TRACK_PATH'];
        
        // Resolve file path to web URI
        $fileUri = $this->pathResolver->toWebUri($filePath, $organism, $assembly);
        
        // Check if remote
        $isRemote = $this->pathResolver->isRemote($filePath);
        
        // Get file size (for local files)
        $fileSize = null;
        if (!$isRemote) {
            $fsPath = $this->pathResolver->toFilesystemPath($filePath, $organism, $assembly);
            if (file_exists($fsPath)) {
                $fileSize = filesize($fsPath);
            }
        }
        
        // Build color (from track data or default)
        $color = isset($trackData['color']) && !empty($trackData['color']) 
            ? $trackData['color'] 
            : '#1f77b4';
        
        // Build category array
        $categories = [];
        if (isset($trackData['category']) && !empty($trackData['category'])) {
            $categories = [$trackData['category']];
        } else {
            $categories = ['Quantitative'];
        }
        
        // Build access level
        $accessLevel = isset($trackData['access_level']) && !empty($trackData['access_level'])
            ? $trackData['access_level']
            : 'Public';
        
        // Build metadata structure matching shell script output
        $metadata = [
            'trackId' => $trackId,
            'name' => $name,
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'category' => $categories,
            'type' => 'QuantitativeTrack',
            'adapter' => [
                'type' => 'BigWigAdapter',
                'bigWigLocation' => [
                    'uri' => $fileUri,
                    'locationType' => 'UriLocation'
                ]
            ],
            'displays' => [
                [
                    'type' => 'LinearWiggleDisplay',
                    'displayId' => "{$trackId}-LinearWiggleDisplay",
                    'renderer' => [
                        'type' => 'XYPlotRenderer',
                        'color' => $color
                    ]
                ]
            ],
            'metadata' => [
                'description' => isset($trackData['description']) ? $trackData['description'] : '',
                'access_level' => $accessLevel,
                'file_path' => $filePath,
                'is_remote' => $isRemote,
                'added_date' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ];
        
        // Add file size if available
        if ($fileSize !== null) {
            $metadata['metadata']['file_size'] = $fileSize;
        }
        
        // Add Google Sheets metadata if present
        $sheetFields = [
            'technique', 'institute', 'source', 'experiment',
            'developmental_stage', 'tissue', 'condition',
            'summary', 'citation', 'project', 'accession',
            'date', 'analyst'
        ];
        
        $googleMetadata = [];
        foreach ($sheetFields as $field) {
            if (isset($trackData[$field]) && !empty($trackData[$field])) {
                $googleMetadata[$field] = $trackData[$field];
            }
        }
        
        if (!empty($googleMetadata)) {
            $metadata['metadata']['google_sheets_metadata'] = $googleMetadata;
        }
        
        // Add any custom fields from metadata column
        if (isset($trackData['metadata']) && !empty($trackData['metadata'])) {
            $metadata['metadata']['custom_fields'] = $trackData['metadata'];
        }
        
        return $metadata;
    }
    
    /**
     * Write metadata to JSON file
     * 
     * @param array $metadata Metadata structure
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param string $trackId Track identifier
     * @throws Exception If write fails
     */
    private function writeMetadata($metadata, $organism, $assembly, $trackId)
    {
        // Get metadata directory from ConfigManager
        // NOTE: Metadata is ALWAYS local, even if track files are remote
        $metadataBase = $this->config->getPath('metadata_path');
        $trackDir = "$metadataBase/jbrowse2-configs/tracks/$organism/$assembly/bigwig";
        
        // Ensure directory exists
        if (!is_dir($trackDir)) {
            if (!mkdir($trackDir, 0755, true)) {
                throw new Exception("Failed to create directory: $trackDir");
            }
        }
        
        // Build file path
        $metadataFile = "$trackDir/$trackId.json";
        
        // Write JSON with pretty print
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception("Failed to encode JSON: " . json_last_error_msg());
        }
        
        if (file_put_contents($metadataFile, $json) === false) {
            throw new Exception("Failed to write metadata file: {$metadataFile}");
        }
    }
    
    /**
     * Get required fields for BigWig tracks
     * 
     * @return array List of required field names
     */
    public function getRequiredFields()
    {
        return [
            'track_id',
            'name',
            'TRACK_PATH',
        ];
    }
    
    /**
     * Get track type identifier
     * 
     * @return string Track type identifier
     */
    public function getType()
    {
        return 'bigwig';
    }
    
    /**
     * Get valid file extensions for BigWig files
     * 
     * @return array List of valid extensions
     */
    public function getValidExtensions()
    {
        return ['.bw', '.bigwig', '.bigWig'];
    }
    
    /**
     * Check if BigWig tracks require index files
     * 
     * @return bool False - BigWig files are self-indexed
     */
    public function requiresIndex()
    {
        return false;
    }
    
    /**
     * Get expected index file extensions
     * 
     * @return array Empty array - BigWig files don't need separate index
     */
    public function getIndexExtensions()
    {
        return [];
    }
}
