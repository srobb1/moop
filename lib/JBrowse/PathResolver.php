<?php
/**
 * PathResolver - Portable Path Resolution for JBrowse Tracks
 * 
 * Handles conversion between filesystem paths and web URIs for both:
 * - Local tracks (on MOOP server)
 * - Remote tracks (on separate tracks server)
 * 
 * This class is THE foundation for portable deployment - all paths flow through here.
 * 
 * Usage:
 *   $resolver = new PathResolver($config);
 *   
 *   // Convert filesystem to web URI
 *   $uri = $resolver->toWebUri('/data/moop/data/tracks/organism/assembly/bigwig/file.bw');
 *   // Result: /moop/data/tracks/organism/assembly/bigwig/file.bw (local)
 *   // OR: https://tracks.example.com/data/tracks/organism/assembly/bigwig/file.bw (remote)
 *   
 *   // Resolve track path (AUTO, relative, absolute, URL)
 *   [$path, $isRemote] = $resolver->resolveTrackPath('AUTO', $organism, $assembly, 'fasta');
 * 
 * @package MOOP\JBrowse
 */

class PathResolver
{
    /**
     * @var ConfigManager
     */
    private $config;
    
    /**
     * Cache for tracks server config
     * @var array|null
     */
    private $tracksServerConfig = null;
    
    /**
     * Constructor
     * 
     * @param ConfigManager $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->tracksServerConfig = $config->get('tracks_server', []);
    }
    
    /**
     * Convert filesystem path to web URI
     * 
     * Handles both local and remote tracks server configurations.
     * 
     * Local examples:
     *   /data/moop/data/tracks/... -> /moop/data/tracks/...
     *   /var/www/html/moop/data/tracks/... -> /moop/data/tracks/...
     * 
     * Remote examples (if tracks_server enabled):
     *   /data/moop/data/tracks/... -> https://tracks.example.com/data/tracks/...
     * 
     * Reference genomes ALWAYS stay local:
     *   /data/moop/data/genomes/... -> /moop/data/genomes/... (never remote)
     * 
     * @param string $filesystemPath Absolute filesystem path
     * @return string Web-accessible URI
     * @throws InvalidArgumentException If path format is invalid
     */
    public function toWebUri($filesystemPath)
    {
        if (empty($filesystemPath)) {
            throw new InvalidArgumentException("Filesystem path cannot be empty");
        }
        
        // Check if this is a reference genome (always local)
        $isReferenceGenome = strpos($filesystemPath, '/data/genomes/') !== false || 
                            strpos($filesystemPath, '/genomes/') !== false;
        
        // Get site configuration
        $sitePath = $this->config->getPath('site_path');
        $site = $this->config->getString('site');
        
        // Determine if we should use remote tracks server
        $useRemoteServer = !$isReferenceGenome && 
                          !empty($this->tracksServerConfig['enabled']) &&
                          !empty($this->tracksServerConfig['url']);
        
        if ($useRemoteServer) {
            // Remote tracks server
            // Extract the path relative to site_path
            $relativePath = str_replace($sitePath, '', $filesystemPath);
            
            // Build remote URL
            $remoteUrl = rtrim($this->tracksServerConfig['url'], '/');
            return $remoteUrl . $relativePath;
            
        } else {
            // Local server
            // Extract the path after the site directory name
            // Works for any deployment:
            //   /data/moop/data/tracks/... -> /moop/data/tracks/...
            //   /var/www/html/moop/data/tracks/... -> /moop/data/tracks/...
            //   /opt/simrbase/data/tracks/... -> /simrbase/data/tracks/...
            
            // Find the site directory in the path
            $parts = explode('/', $filesystemPath);
            $siteIndex = array_search($site, $parts);
            
            if ($siteIndex === false) {
                throw new InvalidArgumentException(
                    "Cannot determine web URI: site directory '$site' not found in path '$filesystemPath'"
                );
            }
            
            // Build URI from site directory onward
            $uriParts = array_slice($parts, $siteIndex);
            return '/' . implode('/', $uriParts);
        }
    }
    
    /**
     * Convert web URI to filesystem path
     * 
     * Examples:
     *   /moop/data/tracks/... -> /data/moop/data/tracks/...
     *   https://tracks.example.com/data/tracks/... -> [remains URL]
     * 
     * @param string $webUri Web URI or URL
     * @return string Filesystem path (or original URL if remote)
     */
    public function toFilesystemPath($webUri)
    {
        if (empty($webUri)) {
            throw new InvalidArgumentException("Web URI cannot be empty");
        }
        
        // If it's a full URL (http:// or https://), return as-is
        if ($this->isRemote($webUri)) {
            return $webUri;
        }
        
        // Local URI - convert to filesystem path
        $sitePath = $this->config->getPath('site_path');
        $site = $this->config->getString('site');
        
        // Remove leading slash and site name from URI
        $webUri = ltrim($webUri, '/');
        if (strpos($webUri, $site . '/') === 0) {
            $webUri = substr($webUri, strlen($site) + 1);
        }
        
        // Build filesystem path
        return $sitePath . '/' . $webUri;
    }
    
    /**
     * Resolve track path from various formats
     * 
     * Handles:
     * - AUTO: Auto-resolve reference/annotation paths
     * - Absolute paths: /data/moop/data/tracks/...
     * - Relative paths: data/tracks/... (prepends site_path)
     * - HTTP/HTTPS URLs: https://server.com/track.bw
     * 
     * @param string $path Track path from Google Sheet
     * @param string $organism Organism name (required for AUTO)
     * @param string $assembly Assembly ID (required for AUTO)
     * @param string $type Track type (required for AUTO)
     * @return array [resolved_path, is_remote]
     * @throws InvalidArgumentException If AUTO used without organism/assembly
     */
    public function resolveTrackPath($path, $organism = null, $assembly = null, $type = null)
    {
        if (empty($path)) {
            throw new InvalidArgumentException("Track path cannot be empty");
        }
        
        // Handle AUTO keyword (reference genome and annotations)
        if (strtoupper($path) === 'AUTO') {
            return $this->resolveAutoPath($organism, $assembly, $type);
        }
        
        // Remote URL - return as-is
        if ($this->isRemote($path)) {
            return [$path, true];
        }
        
        // Absolute path - return as-is
        if ($path[0] === '/') {
            return [$path, false];
        }
        
        // Relative path - prepend site_path
        $sitePath = $this->config->getPath('site_path');
        $absolutePath = $sitePath . '/' . ltrim($path, '/');
        return [$absolutePath, false];
    }
    
    /**
     * Resolve AUTO paths for reference genomes and annotations
     * 
     * AUTO paths resolve to symlinked files in JBrowse genomes directory.
     * Source files are in /organisms/{organism}/{assembly}/ and follow patterns
     * defined in site_config.php (sequence_types['genome']['pattern'] and annotation_file).
     * 
     * Resolved paths:
     *   - fasta: {genomes_directory}/{organism}/{assembly}/reference.fasta
     *   - gff: {genomes_directory}/{organism}/{assembly}/annotations.gff3.gz
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param string $type Track type (fasta or gff)
     * @return array [resolved_path, is_remote]
     * @throws InvalidArgumentException If organism/assembly/type missing
     * @throws RuntimeException If config missing
     */
    private function resolveAutoPath($organism, $assembly, $type)
    {
        if (empty($organism) || empty($assembly)) {
            throw new InvalidArgumentException(
                "AUTO paths require --organism and --assembly parameters"
            );
        }
        
        // Get genomes directory from config (required)
        $jbrowse2Config = $this->config->get('jbrowse2');
        if (empty($jbrowse2Config['genomes_directory'])) {
            throw new RuntimeException(
                "Configuration error: jbrowse2.genomes_directory not defined in site_config.php"
            );
        }
        
        $genomesDir = rtrim($jbrowse2Config['genomes_directory'], '/');
        
        switch ($type) {
            case 'fasta':
                // Symlinked from /organisms/{organism}/{assembly}/{genome_pattern}
                // to {genomes_directory}/{organism}/{assembly}/reference.fasta
                $path = "$genomesDir/$organism/$assembly/reference.fasta";
                break;
                
            case 'gff':
                // Compressed from /organisms/{organism}/{assembly}/{annotation_pattern}
                // to {genomes_directory}/{organism}/{assembly}/annotations.gff3.gz
                $path = "$genomesDir/$organism/$assembly/annotations.gff3.gz";
                break;
                
            default:
                throw new InvalidArgumentException(
                    "AUTO only supported for fasta and gff track types, not '$type'"
                );
        }
        
        return [$path, false];
    }
    
    /**
     * Check if path is a remote URL
     * 
     * @param string $path Path to check
     * @return bool True if HTTP/HTTPS URL
     */
    public function isRemote($path)
    {
        return preg_match('#^https?://#i', $path) === 1;
    }
    
    /**
     * Check if tracks server is enabled
     * 
     * @return bool
     */
    public function isTracksServerEnabled()
    {
        return !empty($this->tracksServerConfig['enabled']);
    }
    
    /**
     * Get tracks server URL
     * 
     * @return string|null
     */
    public function getTracksServerUrl()
    {
        return $this->tracksServerConfig['url'] ?? null;
    }
    
    /**
     * Validate that a local file exists
     * 
     * @param string $path Filesystem path
     * @return bool
     */
    public function fileExists($path)
    {
        // Can't check remote URLs
        if ($this->isRemote($path)) {
            return true; // Assume remote URLs are valid
        }
        
        return file_exists($path);
    }
    
    /**
     * Get track directory for organism/assembly
     * 
     * Creates directory if it doesn't exist.
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param string $trackType Track type (bigwig, bam, etc.)
     * @return string Absolute filesystem path
     * @throws RuntimeException If config missing or directory cannot be created
     */
    public function getTrackDirectory($organism, $assembly, $trackType)
    {
        $jbrowse2Config = $this->config->get('jbrowse2');
        if (empty($jbrowse2Config['tracks_directory'])) {
            throw new RuntimeException(
                "Configuration error: jbrowse2.tracks_directory not defined in site_config.php"
            );
        }
        
        $tracksDir = rtrim($jbrowse2Config['tracks_directory'], '/');
        $fullPath = "$tracksDir/$organism/$assembly/$trackType";
        
        // Ensure directory exists
        $this->ensureDirectoryExists($fullPath);
        
        return $fullPath;
    }
    
    /**
     * Get metadata directory for organism/assembly
     * 
     * Creates directory if it doesn't exist.
     * 
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param string $trackType Track type (bigwig, bam, combo, etc.)
     * @return string Absolute filesystem path
     * @throws RuntimeException If directory cannot be created
     */
    public function getMetadataDirectory($organism, $assembly, $trackType)
    {
        $metadataDir = $this->config->getPath('metadata_path');
        $fullPath = "$metadataDir/jbrowse2-configs/tracks/$organism/$assembly/$trackType";
        
        // Ensure directory exists
        $this->ensureDirectoryExists($fullPath);
        
        return $fullPath;
    }
    
    /**
     * Ensure directory exists, create if necessary
     * 
     * @param string $path Directory path
     * @throws RuntimeException If directory cannot be created
     */
    private function ensureDirectoryExists($path)
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0775, true)) {
                throw new RuntimeException(
                    "Failed to create directory: $path"
                );
            }
        }
    }
}
