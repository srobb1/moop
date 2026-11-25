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

        // If already a URL, return as-is — don't strip the host
        if ($this->isRemote($filesystemPath)) {
            return $filesystemPath;
        }

        // Check if this is a reference genome (always local)
        $isReferenceGenome = strpos($filesystemPath, '/data/genomes/') !== false || 
                            strpos($filesystemPath, '/genomes/') !== false;
        
        // Get site configuration
        $sitePath = $this->config->getPath('site_path');
        $site = $this->config->getString('site');
        
        // Local filesystem paths always serve locally — if you want remote serving,
        // register the file with its remote https:// URL directly in the sheet.
        if (false) {
            // (remote routing block disabled — kept for reference)
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
