<?php
/**
 * BaseTrack - Base class for all track types
 * 
 * Provides common functionality that all track types need:
 * - writeMetadata() - Write track JSON config files
 * - Common utilities
 * 
 * All track types should extend this class to avoid code duplication.
 * 
 * @package MOOP\JBrowse\TrackTypes
 */

abstract class BaseTrack
{
    /**
     * @var PathResolver
     */
    protected $pathResolver;
    
    /**
     * @var ConfigManager
     */
    protected $config;
    
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
     * Write track metadata to JSON file
     * 
     * Uses management_track_id for readable filename, but the JSON contains
     * the hash-based browser trackId for security.
     * 
     * Example:
     *   Filename: MOLNG-2707_S3-body-wall.bam.json  (readable)
     *   JSON trackId: track_cf878b97db  (secure hash)
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param array $metadata Track metadata (with trackId and management_track_id)
     * @return string Path to written file
     * @throws Exception If write fails
     */
    public function writeMetadata(string $organism, string $assembly, array $metadata): string
    {
        // Use management_track_id for readable filename
        // Fall back to trackId if management_track_id not set (for backwards compatibility)
        $filenameId = $metadata['metadata']['management_track_id'] ?? $metadata['trackId'];
        
        $trackType = $this->getType();
        
        // Get metadata directory from ConfigManager
        $metadataBase = $this->config->getPath('metadata_path');
        $trackDir = "$metadataBase/jbrowse2-configs/tracks/$organism/$assembly/$trackType";
        
        // Create directory if needed
        if (!is_dir($trackDir)) {
            if (!mkdir($trackDir, 0755, true)) {
                throw new Exception("Failed to create metadata directory: $trackDir");
            }
        }
        
        // Write JSON file (use management ID for readable filename)
        $metadataFile = $trackDir . '/' . $filenameId . '.json';
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($metadataFile, $json) === false) {
            throw new Exception("Failed to write metadata file: $metadataFile");
        }
        
        return $metadataFile;
    }
    
    /**
     * Get track type identifier
     * Must be implemented by each track type
     * 
     * @return string Track type (e.g., 'bam', 'bigwig', 'vcf')
     */
    abstract public function getType(): string;
}
