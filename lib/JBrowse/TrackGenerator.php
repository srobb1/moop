<?php
/**
 * TrackGenerator - Main orchestrator for JBrowse track generation
 * 
 * This class coordinates all track generation activities:
 * - Loads track data from Google Sheets
 * - Validates track data
 * - Generates tracks by delegating to track type handlers
 * - Manages track type registry
 * - Handles errors and reporting
 * 
 * Used by both CLI and Web UI - all business logic is here.
 * 
 * Usage:
 *   $config = ConfigManager::getInstance();
 *   $generator = new TrackGenerator($config);
 *   
 *   // Load tracks from Google Sheet
 *   $tracks = $generator->loadFromSheet($sheetId, $gid, $organism, $assembly);
 *   
 *   // Generate all tracks
 *   $results = $generator->generateTracks($tracks, ['force' => [], 'dry_run' => false]);
 * 
 * @package MOOP\JBrowse
 */

require_once __DIR__ . '/PathResolver.php';
require_once __DIR__ . '/TrackTypes/TrackTypeInterface.php';
require_once __DIR__ . '/GoogleSheetsParser.php';

class TrackGenerator
{
    /**
     * @var ConfigManager
     */
    private $config;
    
    /**
     * @var PathResolver
     */
    private $pathResolver;
    
    /**
     * @var array Track type handlers registry
     */
    private $trackTypes = [];
    
    /**
     * @var GoogleSheetsParser
     */
    private $sheetsParser;
    
    /**
     * Constructor
     * 
     * Initializes components and registers track type handlers.
     * 
     * @param ConfigManager $config Configuration manager
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->pathResolver = new PathResolver($config);
        $this->sheetsParser = new GoogleSheetsParser();
        
        // Register track type handlers
        $this->registerTrackTypes();
    }
    
    /**
     * Register all track type handlers
     * 
     * Track type classes are loaded dynamically as needed.
     * To add a new track type:
     * 1. Create TrackTypes/{TypeName}Track.php implementing TrackTypeInterface
     * 2. Add entry to $trackTypeClasses array below
     * 3. Class will be auto-loaded when needed
     */
    private function registerTrackTypes()
    {
        // Map track type identifier to class name
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
            // Add more as implemented:
            // 'paf' => 'PAFTrack',
            // 'maf' => 'MAFTrack',
        ];
        
        // Load and instantiate each track type
        foreach ($trackTypeClasses as $type => $className) {
            $classFile = __DIR__ . "/TrackTypes/$className.php";
            
            if (file_exists($classFile)) {
                require_once $classFile;
                
                if (class_exists($className)) {
                    $this->trackTypes[$type] = new $className($this->pathResolver, $this->config);
                } else {
                    error_log("TrackGenerator: Class $className not found in $classFile");
                }
            } else {
                error_log("TrackGenerator: Track type file not found: $classFile");
            }
        }
    }
    
    /**
     * Get track type handler
     * 
     * @param string $type Track type identifier
     * @return TrackTypeInterface|null Track handler or null if not found
     */
    public function getTrackTypeHandler($type)
    {
        return $this->trackTypes[$type] ?? null;
    }
    
    /**
     * Get list of registered track types
     * 
     * @return array List of track type identifiers
     */
    public function getRegisteredTrackTypes()
    {
        return array_keys($this->trackTypes);
    }
    
    /**
     * Determine track type from file extension
     * 
     * @param string $path File path
     * @return string|null Track type or null if unknown
     */
    public function determineTrackType($path)
    {
        // Check for AUTO keyword - reference sequences and annotations
        // These are handled by assembly setup, not as separate tracks
        if (strtoupper(trim($path)) === 'AUTO') {
            return 'auto';
        }
        
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
     * Load tracks from Google Sheet
     * 
     * Downloads sheet, parses content, and extracts track data.
     * 
     * @param string $sheetId Google Sheet ID
     * @param string $gid Sheet GID
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array ['regular' => [...], 'combo' => [...]]
     * @throws RuntimeException If sheet download or parsing fails
     */
    public function loadFromSheet($sheetId, $gid, $organism, $assembly)
    {
        // Download sheet content
        $content = $this->sheetsParser->download($sheetId, $gid);
        
        // Parse tracks
        $tracks = $this->sheetsParser->parseTracks($content, $organism, $assembly);
        
        // Validate columns
        $parsed = $this->sheetsParser->parseTSV($content);
        $validation = $this->sheetsParser->validateColumns($parsed);
        
        if (!$validation['valid']) {
            throw new RuntimeException(
                "Sheet missing required columns: " . implode(', ', $validation['missing'])
            );
        }
        
        return $tracks;
    }
    
    /**
     * Generate tracks
     * 
     * Processes array of tracks and generates each one.
     * 
     * @param array $tracks Tracks to generate ['regular' => [...], 'combo' => [...]]
     * @param array $options Options: force, dry_run, clean, regenerate
     * @return array Results with statistics
     */
    public function generateTracks($tracks, $options = [])
    {
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => [],
            'stats' => [
                'total' => 0,
                'created' => 0,
                'failed' => 0,
                'skipped' => 0
            ]
        ];
        
        // Get force list (empty array means force all)
        $forceIds = $options['force'] ?? null;
        $dryRun = !empty($options['dry_run']);
        
        // Process regular tracks
        foreach ($tracks['regular'] as $track) {
            $results['stats']['total']++;
            
            // Determine if we should force regenerate this track
            $shouldForce = false;
            if ($forceIds !== null) {
                if (empty($forceIds)) {
                    // Empty array = force all
                    $shouldForce = true;
                } elseif (in_array($track['track_id'], $forceIds)) {
                    // Specific track ID in force list
                    $shouldForce = true;
                }
            }
            
            // Check if track exists
            $trackExists = $this->trackExists($track['track_id'], $track['organism'], $track['assembly']);
            
            if ($trackExists && !$shouldForce) {
                $results['skipped'][] = $track['track_id'];
                $results['stats']['skipped']++;
                continue;
            }
            
            // Generate track
            $success = $this->generateSingleTrack($track, ['force' => $shouldForce, 'dry_run' => $dryRun]);
            
            if ($success) {
                $results['success'][] = $track['track_id'];
                $results['stats']['created']++;
            } else {
                $results['failed'][] = [
                    'track_id' => $track['track_id'],
                    'name' => $track['name'],
                    'error' => 'Generation failed'
                ];
                $results['stats']['failed']++;
            }
        }
        
        // Process combo tracks
        if (!empty($tracks['combo'])) {
            echo "\nProcessing combo tracks...\n";
            echo str_repeat('-', 60) . "\n";
            
            foreach ($tracks['combo'] as $combo) {
                // Determine if we should force regenerate this track
                $shouldForce = false;
                if ($forceIds !== null) {
                    if (empty($forceIds)) {
                        // Empty array = force all
                        $shouldForce = true;
                    } elseif (in_array($combo['track_id'], $forceIds)) {
                        // Specific track ID in force list
                        $shouldForce = true;
                    }
                }
                
                // Check if track exists and should skip
                $trackFile = $this->config->getPath('site_path') . '/metadata/jbrowse2-configs/tracks/' . 
                            $combo['organism'] . '/' . $combo['assembly'] . '/combo/' . 
                            strtolower($combo['track_id']) . '.json';
                
                if (!$shouldForce && file_exists($trackFile)) {
                    echo "  ⊘ Skipping " . $combo['track_id'] . " (already exists, use --force to regenerate)\n";
                    $results['skipped'][] = $combo['track_id'];
                    $results['stats']['skipped']++;
                    continue;
                }
                
                $results['stats']['total']++;
                
                // Generate combo track
                if (isset($this->trackTypes['combo'])) {
                    $success = $this->trackTypes['combo']->generate(
                        $combo,
                        $combo['organism'],
                        $combo['assembly'],
                        ['dry_run' => $dryRun]
                    );
                    
                    if ($success) {
                        $results['success'][] = $combo['track_id'];
                        $results['stats']['created']++;
                    } else {
                        $results['failed'][] = [
                            'track_id' => $combo['track_id'],
                            'name' => $combo['name'],
                            'error' => 'Combo track generation failed'
                        ];
                        $results['stats']['failed']++;
                    }
                } else {
                    echo "    ✗ Combo track type not registered\n";
                    $results['failed'][] = [
                        'track_id' => $combo['track_id'],
                        'name' => $combo['name'],
                        'error' => 'Combo track type not available'
                    ];
                    $results['stats']['failed']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Generate a single track
     * 
     * @param array $trackData Track data
     * @param array $options Options: force, dry_run
     * @return bool|string True if successful, 'skipped' if skipped, false if failed
     */
    private function generateSingleTrack($trackData, $options = [])
    {
        // Determine track type
        $trackType = $this->determineTrackType($trackData['TRACK_PATH']);
        
        if (!$trackType) {
            error_log("Unknown track type for: " . $trackData['TRACK_PATH']);
            return false;
        }
        
        // Get track handler
        $handler = $this->getTrackTypeHandler($trackType);
        
        if (!$handler) {
            error_log("No handler for track type: $trackType");
            return false;
        }
        
        // Validate track
        $validation = $handler->validate($trackData);
        if (!$validation['valid']) {
            error_log("Validation failed for " . $trackData['track_id'] . ": " . implode(', ', $validation['errors']));
            return false;
        }
        
        // Generate track
        return $handler->generate(
            $trackData,
            $trackData['organism'],
            $trackData['assembly'],
            $options
        );
    }
    
    /**
     * Check if track exists
     * 
     * @param string $trackId Track ID
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return bool True if track exists
     */
    public function trackExists($trackId, $organism, $assembly)
    {
        // Check all track type directories
        $metadataDir = $this->config->getPath('metadata_path');
        $baseDir = "$metadataDir/jbrowse2-configs/tracks/$organism/$assembly";
        
        if (!is_dir($baseDir)) {
            return false;
        }
        
        // Check each track type directory
        $trackTypeDirs = ['bigwig', 'bam', 'vcf', 'gff', 'gtf', 'cram', 'paf', 'maf', 'combo'];
        
        foreach ($trackTypeDirs as $typeDir) {
            $trackFile = "$baseDir/$typeDir/$trackId.json";
            if (file_exists($trackFile)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get track status for organism/assembly
     * 
     * Returns list of existing tracks.
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array List of track IDs and metadata
     */
    public function getTrackStatus($organism, $assembly)
    {
        $tracks = [];
        
        $metadataDir = $this->config->getPath('metadata_path');
        $baseDir = "$metadataDir/jbrowse2-configs/tracks/$organism/$assembly";
        
        if (!is_dir($baseDir)) {
            return $tracks;
        }
        
        // Scan each track type directory
        $trackTypeDirs = ['bigwig', 'bam', 'vcf', 'bed', 'gtf', 'gff', 'cram', 'paf', 'maf', 'combo'];
        
        foreach ($trackTypeDirs as $typeDir) {
            $fullPath = "$baseDir/$typeDir";
            if (!is_dir($fullPath)) {
                continue;
            }
            
            $files = glob("$fullPath/*.json");
            foreach ($files as $file) {
                $trackId = basename($file, '.json');
                $metadata = json_decode(file_get_contents($file), true);
                
                $tracks[] = [
                    'track_id' => $trackId,
                    'type' => $typeDir,
                    'name' => $metadata['name'] ?? $trackId,
                    'category' => $metadata['category'] ?? 'Unknown',
                    'file' => $file
                ];
            }
        }
        
        return $tracks;
    }
    
    /**
     * Regenerate specific tracks
     * 
     * @param array $trackIds Track IDs to regenerate
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array Results
     */
    public function regenerateTracks($trackIds, $organism, $assembly)
    {
        // TODO: Implement track regeneration
        // This requires loading existing track metadata and regenerating
        return [
            'success' => [],
            'failed' => []
        ];
    }
    
    /**
     * Clean orphaned tracks
     * 
     * Removes tracks that are not in the current sheet.
     * 
     * @param array $sheetTrackIds Track IDs from sheet
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return int Number of tracks removed
     */
    public function cleanOrphanedTracks($sheetTrackIds, $organism, $assembly)
    {
        $existingTracks = $this->getTrackStatus($organism, $assembly);
        $removed = 0;
        
        foreach ($existingTracks as $track) {
            if (!in_array($track['track_id'], $sheetTrackIds)) {
                // Remove orphaned track
                if (unlink($track['file'])) {
                    $removed++;
                }
            }
        }
        
        return $removed;
    }
    
    /**
     * Validate track data
     * 
     * @param array $trackData Track data to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateTrack($trackData)
    {
        // Determine track type
        $trackType = $this->determineTrackType($trackData['TRACK_PATH']);
        
        if (!$trackType) {
            return [
                'valid' => false,
                'errors' => ['Unknown track type for: ' . $trackData['TRACK_PATH']]
            ];
        }
        
        // Get handler and validate
        $handler = $this->getTrackTypeHandler($trackType);
        if (!$handler) {
            return [
                'valid' => false,
                'errors' => ['No handler for track type: ' . $trackType]
            ];
        }
        
        return $handler->validate($trackData);
    }
}
