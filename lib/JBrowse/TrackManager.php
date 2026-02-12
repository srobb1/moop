<?php
/**
 * JBrowse Track Manager
 * 
 * Manages track operations: removal, listing, cleanup, statistics.
 * Replaces remove_jbrowse_data.sh with pure PHP implementation.
 * 
 * @package MOOP
 * @subpackage JBrowse
 */

require_once __DIR__ . '/../../includes/ConfigManager.php';
require_once __DIR__ . '/PathResolver.php';

class TrackManager
{
    /** @var ConfigManager Configuration manager */
    private $config;
    
    /** @var PathResolver Path resolver */
    private $pathResolver;
    
    /**
     * Constructor
     * 
     * @param ConfigManager $config Configuration manager
     * @param PathResolver $pathResolver Path resolver
     */
    public function __construct($config, $pathResolver)
    {
        $this->config = $config;
        $this->pathResolver = $pathResolver;
    }
    
    /**
     * Remove a single track
     * 
     * @param string $trackId Track identifier
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param array $options Optional: dry_run, remove_data
     * @return array Result with success status and details
     */
    public function removeTrack($trackId, $organism, $assembly, $options = [])
    {
        $dryRun = !empty($options['dry_run']);
        $removeData = !empty($options['remove_data']);
        
        $result = [
            'success' => false,
            'items_removed' => [],
            'errors' => []
        ];
        
        try {
            $sitePath = $this->config->getPath('site_path');
            $metadataDir = "{$sitePath}/metadata/jbrowse2-configs/tracks";
            
            // Find track metadata file (check all track type subdirectories)
            $trackTypes = ['bigwig', 'bam', 'vcf', 'gff', 'gtf', 'paf', 'bed', 'cram', 'combo'];
            $trackFile = null;
            
            foreach ($trackTypes as $type) {
                $path = "{$metadataDir}/{$organism}/{$assembly}/{$type}/{$trackId}.json";
                if (file_exists($path)) {
                    $trackFile = $path;
                    break;
                }
            }
            
            if (!$trackFile) {
                $result['errors'][] = "Track not found: {$trackId}";
                return $result;
            }
            
            // Remove track metadata
            if (!$dryRun) {
                if (unlink($trackFile)) {
                    $result['items_removed'][] = basename($trackFile);
                } else {
                    $result['errors'][] = "Failed to remove: " . basename($trackFile);
                    return $result;
                }
            } else {
                $result['items_removed'][] = basename($trackFile) . " [DRY RUN]";
            }
            
            // Remove data file if requested
            if ($removeData) {
                // Parse track JSON to get file path
                $trackData = json_decode(file_get_contents($trackFile), true);
                if (isset($trackData['metadata']['file_path'])) {
                    $filePath = $trackData['metadata']['file_path'];
                    $fsPath = $this->pathResolver->toFilesystemPath($filePath, $organism, $assembly);
                    
                    if (file_exists($fsPath)) {
                        if (!$dryRun) {
                            if (unlink($fsPath)) {
                                $result['items_removed'][] = basename($fsPath) . " [DATA]";
                            }
                        } else {
                            $result['items_removed'][] = basename($fsPath) . " [DATA] [DRY RUN]";
                        }
                    }
                }
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Remove all tracks for an assembly
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param array $options Optional: dry_run, remove_data
     * @return array Result with success status and details
     */
    public function removeAssembly($organism, $assembly, $options = [])
    {
        $dryRun = !empty($options['dry_run']);
        $removeData = !empty($options['remove_data']);
        
        $result = [
            'success' => false,
            'items_removed' => [],
            'errors' => []
        ];
        
        try {
            $sitePath = $this->config->getPath('site_path');
            
            // Remove track metadata
            $metadataTrackDir = "{$sitePath}/metadata/jbrowse2-configs/tracks/{$organism}/{$assembly}";
            if (is_dir($metadataTrackDir)) {
                $count = $this->removeDirectory($metadataTrackDir, $dryRun);
                $result['items_removed'][] = "Track metadata ({$count} files)" . ($dryRun ? " [DRY RUN]" : "");
            }
            
            // Remove assembly metadata
            $assemblyFile = "{$sitePath}/metadata/jbrowse2-configs/assemblies/{$organism}_{$assembly}.json";
            if (file_exists($assemblyFile)) {
                if (!$dryRun) {
                    if (unlink($assemblyFile)) {
                        $result['items_removed'][] = "Assembly metadata";
                    }
                } else {
                    $result['items_removed'][] = "Assembly metadata [DRY RUN]";
                }
            }
            
            // Remove cached configs
            $configDir = "{$sitePath}/jbrowse2/configs/{$organism}_{$assembly}";
            if (is_dir($configDir)) {
                $count = $this->removeDirectory($configDir, $dryRun);
                $result['items_removed'][] = "Cached configs ({$count} files)" . ($dryRun ? " [DRY RUN]" : "");
            }
            
            // Remove data files if requested
            if ($removeData) {
                $genomeDir = "{$sitePath}/data/genomes/{$organism}/{$assembly}";
                if (is_dir($genomeDir)) {
                    $count = $this->removeDirectory($genomeDir, $dryRun);
                    $result['items_removed'][] = "Genome data ({$count} files) [DATA]" . ($dryRun ? " [DRY RUN]" : "");
                }
                
                $tracksDir = "{$sitePath}/data/tracks/{$organism}/{$assembly}";
                if (is_dir($tracksDir)) {
                    $count = $this->removeDirectory($tracksDir, $dryRun);
                    $result['items_removed'][] = "Track data ({$count} files) [DATA]" . ($dryRun ? " [DRY RUN]" : "");
                }
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Remove all assemblies for an organism
     * 
     * @param string $organism Organism name
     * @param array $options Optional: dry_run, remove_data
     * @return array Result with success status and details
     */
    public function removeOrganism($organism, $options = [])
    {
        $dryRun = !empty($options['dry_run']);
        $removeData = !empty($options['remove_data']);
        
        $result = [
            'success' => false,
            'assemblies_removed' => [],
            'items_removed' => [],
            'errors' => []
        ];
        
        try {
            // Find all assemblies for this organism
            $assemblies = $this->listAssemblies($organism);
            
            if (empty($assemblies)) {
                $result['errors'][] = "No assemblies found for organism: {$organism}";
                return $result;
            }
            
            // Remove each assembly
            foreach ($assemblies as $assembly) {
                $assemblyResult = $this->removeAssembly($organism, $assembly, $options);
                
                if ($assemblyResult['success']) {
                    $result['assemblies_removed'][] = $assembly;
                    $result['items_removed'] = array_merge(
                        $result['items_removed'], 
                        $assemblyResult['items_removed']
                    );
                } else {
                    $result['errors'] = array_merge(
                        $result['errors'],
                        $assemblyResult['errors']
                    );
                }
            }
            
            $result['success'] = empty($result['errors']);
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Clean orphaned tracks (tracks not in the provided list)
     * 
     * @param array $validTrackIds List of valid track IDs
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param array $options Optional: dry_run
     * @return array Result with success status and details
     */
    public function cleanOrphanedTracks($validTrackIds, $organism, $assembly, $options = [])
    {
        $dryRun = !empty($options['dry_run']);
        
        $result = [
            'success' => false,
            'orphaned_tracks' => [],
            'items_removed' => [],
            'errors' => []
        ];
        
        try {
            // Get all existing tracks
            $existingTracks = $this->listTracks($organism, $assembly);
            
            // Find orphaned tracks (exist but not in valid list)
            $orphaned = array_diff($existingTracks, $validTrackIds);
            
            if (empty($orphaned)) {
                $result['success'] = true;
                return $result;
            }
            
            // Remove each orphaned track
            foreach ($orphaned as $trackId) {
                $removeResult = $this->removeTrack($trackId, $organism, $assembly, ['dry_run' => $dryRun]);
                
                if ($removeResult['success']) {
                    $result['orphaned_tracks'][] = $trackId;
                    $result['items_removed'] = array_merge(
                        $result['items_removed'],
                        $removeResult['items_removed']
                    );
                } else {
                    $result['errors'] = array_merge(
                        $result['errors'],
                        $removeResult['errors']
                    );
                }
            }
            
            $result['success'] = empty($result['errors']);
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * List all tracks for an organism/assembly
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array List of track IDs
     */
    public function listTracks($organism, $assembly)
    {
        $tracks = [];
        
        try {
            $sitePath = $this->config->getPath('site_path');
            $metadataDir = "{$sitePath}/metadata/jbrowse2-configs/tracks/{$organism}/{$assembly}";
            
            if (!is_dir($metadataDir)) {
                return $tracks;
            }
            
            // Scan all track type subdirectories
            $trackTypes = glob("{$metadataDir}/*", GLOB_ONLYDIR);
            
            foreach ($trackTypes as $typeDir) {
                $jsonFiles = glob("{$typeDir}/*.json");
                foreach ($jsonFiles as $file) {
                    $tracks[] = basename($file, '.json');
                }
            }
            
        } catch (Exception $e) {
            error_log("Error listing tracks: " . $e->getMessage());
        }
        
        return $tracks;
    }
    
    /**
     * List all assemblies for an organism
     * 
     * @param string $organism Organism name
     * @return array List of assembly IDs
     */
    public function listAssemblies($organism)
    {
        $assemblies = [];
        
        try {
            $sitePath = $this->config->getPath('site_path');
            $assemblyDir = "{$sitePath}/metadata/jbrowse2-configs/assemblies";
            
            if (!is_dir($assemblyDir)) {
                return $assemblies;
            }
            
            $pattern = "{$assemblyDir}/{$organism}_*.json";
            $files = glob($pattern);
            
            foreach ($files as $file) {
                $basename = basename($file, '.json');
                // Remove organism prefix
                $assembly = substr($basename, strlen($organism) + 1);
                $assemblies[] = $assembly;
            }
            
        } catch (Exception $e) {
            error_log("Error listing assemblies: " . $e->getMessage());
        }
        
        return $assemblies;
    }
    
    /**
     * Get track statistics for an organism/assembly
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array Statistics about tracks
     */
    public function getTrackStatistics($organism, $assembly)
    {
        $stats = [
            'total_tracks' => 0,
            'by_type' => [],
            'total_size' => 0
        ];
        
        try {
            $sitePath = $this->config->getPath('site_path');
            $metadataDir = "{$sitePath}/metadata/jbrowse2-configs/tracks/{$organism}/{$assembly}";
            
            if (!is_dir($metadataDir)) {
                return $stats;
            }
            
            // Scan track type subdirectories
            $trackTypes = glob("{$metadataDir}/*", GLOB_ONLYDIR);
            
            foreach ($trackTypes as $typeDir) {
                $type = basename($typeDir);
                $jsonFiles = glob("{$typeDir}/*.json");
                $count = count($jsonFiles);
                
                $stats['by_type'][$type] = $count;
                $stats['total_tracks'] += $count;
                
                // Calculate total data size
                foreach ($jsonFiles as $file) {
                    $data = json_decode(file_get_contents($file), true);
                    if (isset($data['metadata']['file_size'])) {
                        $stats['total_size'] += $data['metadata']['file_size'];
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error getting statistics: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Recursively remove a directory
     * 
     * @param string $dir Directory path
     * @param bool $dryRun If true, don't actually remove
     * @return int Number of files that would be/were removed
     */
    private function removeDirectory($dir, $dryRun = false)
    {
        $count = 0;
        
        if (!is_dir($dir)) {
            return $count;
        }
        
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($items as $item) {
            $count++;
            if (!$dryRun) {
                if ($item->isDir()) {
                    rmdir($item->getRealPath());
                } else {
                    unlink($item->getRealPath());
                }
            }
        }
        
        if (!$dryRun) {
            rmdir($dir);
        }
        
        return $count;
    }
}
