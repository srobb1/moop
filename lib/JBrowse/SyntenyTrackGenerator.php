<?php
/**
 * SyntenyTrackGenerator - Generator for dual-assembly synteny tracks
 * 
 * Handles track types that reference TWO assemblies:
 * - PAF (Pairwise Alignment Format) - long-read alignments
 * - PIF (Pairwise Indexed PAF) - whole genome synteny
 * - MAF (Multiple Alignment Format) - multiple sequence alignments  
 * - MCScan - ortholog-based synteny
 * 
 * These tracks are separate from single-assembly tracks because they:
 * - Reference two organism/assembly pairs
 * - Need special config directory structure
 * - Have different Google Sheet column requirements
 */

require_once __DIR__ . '/PathResolver.php';
require_once __DIR__ . '/TrackTypes/TrackTypeInterface.php';

class SyntenyTrackGenerator
{
    private $config;
    private $pathResolver;
    private $trackTypes = [];
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->pathResolver = new PathResolver($config);
        
        // Register synteny track types
        $this->registerTrackTypes();
    }
    
    /**
     * Register synteny track type handlers
     */
    private function registerTrackTypes()
    {
        $trackTypeClasses = [
            'paf' => 'PAFTrack',
            'pif' => 'PIFTrack',
            'maf' => 'MAFTrack',
            'mcscan' => 'MCScanTrack',
        ];
        
        foreach ($trackTypeClasses as $type => $className) {
            $classFile = __DIR__ . "/TrackTypes/$className.php";
            
            if (file_exists($classFile)) {
                require_once $classFile;
                
                if (class_exists($className)) {
                    $this->trackTypes[$type] = new $className($this->pathResolver, $this->config);
                } else {
                    error_log("SyntenyTrackGenerator: Class $className not found");
                }
            } else {
                error_log("SyntenyTrackGenerator: Track type file not found: $classFile");
            }
        }
    }
    
    /**
     * Get track type handler
     */
    public function getTrackTypeHandler($type)
    {
        return $this->trackTypes[$type] ?? null;
    }
    
    /**
     * Get list of registered track types
     */
    public function getRegisteredTrackTypes()
    {
        return array_keys($this->trackTypes);
    }
    
    /**
     * Determine track type from file extension
     */
    public function determineTrackType($path)
    {
        // Check against each registered track type
        foreach ($this->trackTypes as $type => $handler) {
            foreach ($handler->getValidExtensions() as $ext) {
                if (preg_match('/' . preg_quote($ext, '/') . '$/i', $path)) {
                    return $type;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Generate synteny track
     * 
     * @param array $trackData Track data from Google Sheet with organism1/assembly1/organism2/assembly2
     * @param array $options Options (force, dry_run, etc.)
     * @return bool Success
     */
    public function generateTrack($trackData, $options = [])
    {
        // Determine track type
        $trackType = $this->determineTrackType($trackData['track_path']);
        
        if (!$trackType) {
            error_log("Unknown track type for: " . $trackData['track_path']);
            return false;
        }
        
        // Get handler
        $handler = $this->getTrackTypeHandler($trackType);
        
        if (!$handler) {
            error_log("No handler for track type: $trackType");
            return false;
        }
        
        // Validate
        $validation = $handler->validate($trackData);
        
        if (!$validation['valid']) {
            error_log("Validation failed: " . implode(', ', $validation['errors']));
            return false;
        }
        
        // Generate
        return $handler->generate(
            $trackData,
            $trackData['organism1'],
            $trackData['assembly1'],
            $options
        );
    }
}
