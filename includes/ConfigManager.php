<?php
/**
 * ConfigManager - Centralized Configuration Management
 * 
 * Provides type-safe, cached access to all application configuration.
 * Manages both application settings and tool registry.
 * 
 * Usage:
 *   include_once __DIR__ . '/config_init.php';
 *   $config = ConfigManager::getInstance();
 *   $site_path = $config->getPath('site_path');
 *   $admin_email = $config->getString('admin_email');
 *   $tools = $config->getAllTools();
 * 
 * SECURITY NOTE:
 * This class handles CONFIGURATION DATA ONLY. It does not:
 *   - Touch user sessions ($_SESSION)
 *   - Perform authentication or authorization
 *   - Validate user permissions
 * Access control remains in includes/access_control.php and is unaffected.
 */

class ConfigManager
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Loaded configuration data
     */
    private $config = [];

    /**
     * Loaded tool registry
     */
    private $tools = [];

    /**
     * Configuration loading state
     */
    private $loaded = false;

    /**
     * Validation errors
     */
    private $errors = [];

    /**
     * Required configuration keys
     */
    private $requiredKeys = [
        'root_path',
        'site',
        'site_path',
        'organism_data',
        'metadata_path',
        'siteTitle',
        'admin_email',
    ];

    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct() {}

    /**
     * Get singleton instance
     * 
     * @return ConfigManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize configuration from files
     * 
     * @param string $site_config_path Path to site_config.php
     * @param string $tools_config_path Path to tools_config.php
     * @return bool True if initialization successful
     */
    public function initialize($site_config_path, $tools_config_path)
    {
        if ($this->loaded) {
            return true; // Already loaded
        }

        // Load site configuration
        if (!file_exists($site_config_path)) {
            $this->errors[] = "Site config not found: $site_config_path";
            return false;
        }

        $this->config = include $site_config_path;
        if (!is_array($this->config)) {
            $this->errors[] = "Site config must return an array";
            return false;
        }

        // Load tool configuration
        if (!file_exists($tools_config_path)) {
            $this->errors[] = "Tools config not found: $tools_config_path";
            return false;
        }

        $this->tools = include $tools_config_path;
        if (!is_array($this->tools)) {
            $this->errors[] = "Tools config must return an array";
            return false;
        }

        $this->loaded = true;
        return true;
    }

    /**
     * Ensure configuration is loaded
     * 
     * @return bool True if loaded
     */
    private function ensureLoaded()
    {
        if (!$this->loaded) {
            trigger_error('ConfigManager not initialized. Call initialize() first.', E_USER_WARNING);
            return false;
        }
        return true;
    }

    /**
     * Get configuration value with type coercion
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     */
    public function get($key, $default = null)
    {
        if (!$this->ensureLoaded()) {
            return $default;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * Get configuration value as path (must be string)
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string Path value
     */
    public function getPath($key, $default = '')
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get configuration value as URL (must be string)
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string URL value
     */
    public function getUrl($key, $default = '')
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get configuration value as string
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string String value
     */
    public function getString($key, $default = '')
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get configuration value as integer
     * 
     * @param string $key Configuration key
     * @param int $default Default value
     * @return int Integer value
     */
    public function getInt($key, $default = 0)
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Get configuration value as array
     * 
     * @param string $key Configuration key
     * @param array $default Default value
     * @return array Array value
     */
    public function getArray($key, $default = [])
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Get sequence types configuration
     * 
     * @return array Sequence types definition
     */
    public function getSequenceTypes()
    {
        return $this->getArray('sequence_types', [
            'protein' => ['pattern' => 'protein.aa.fa', 'label' => 'Protein'],
            'transcript' => ['pattern' => 'transcript.nt.fa', 'label' => 'mRNA'],
            'cds' => ['pattern' => 'cds.nt.fa', 'label' => 'CDS'],
            'genome' => ['pattern' => 'genome.fa', 'label' => 'GENOME'],
        ]);
    }

    /**
     * Get specific tool configuration
     * 
     * @param string $tool_id Tool identifier
     * @return array|null Tool configuration or null if not found
     */
    public function getTool($tool_id)
    {
        if (!$this->ensureLoaded()) {
            return null;
        }

        return $this->tools[$tool_id] ?? null;
    }

    /**
     * Get all available tools
     * 
     * @return array Array of all tool configurations
     */
    public function getAllTools()
    {
        if (!$this->ensureLoaded()) {
            return [];
        }

        return $this->tools;
    }

    /**
     * Build tool URL with context parameters
     * 
     * @param string $tool_id Tool identifier
     * @param array $context Context array with organism, assembly, group, display_name
     * @return string|null Built URL or null if tool not found
     */
    public function buildToolUrl($tool_id, $context = [])
    {
        $tool = $this->getTool($tool_id);
        if (!$tool) {
            return null;
        }

        $site = $this->getString('site', 'moop');
        $url = "/$site" . $tool['url_path'];
        $params = [];

        // Build query parameters from context
        if (isset($tool['context_params']) && is_array($tool['context_params'])) {
            foreach ($tool['context_params'] as $param) {
                if (!empty($context[$param])) {
                    $params[$param] = $context[$param];
                }
            }
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Check if a tool should be visible on a specific page
     * 
     * @param string $tool_id Tool identifier
     * @param string $page Page identifier (index, organism, group, assembly, parent, multi_organism_search)
     * @return bool True if tool should be visible on this page
     */
    public function isToolVisibleOnPage($tool_id, $page)
    {
        $tool = $this->getTool($tool_id);
        if (!$tool) {
            return false;
        }

        // If 'pages' key not defined, default to 'all'
        $pages = $tool['pages'] ?? 'all';

        // 'all' means show on all pages
        if ($pages === 'all') {
            return true;
        }

        // If pages is an array, check if current page is in it
        if (is_array($pages)) {
            return in_array($page, $pages);
        }

        // If pages is a string (and not 'all'), treat as single page name
        return $page === $pages;
    }

    /**
     * Add or register a tool at runtime
     * 
     * @param string $tool_id Tool identifier
     * @param array $config Tool configuration
     * @return bool True if added successfully
     */
    public function addTool($tool_id, $config)
    {
        if (!is_array($config) || empty($config['id'])) {
            return false;
        }

        $this->tools[$tool_id] = $config;
        return true;
    }

    /**
     * Validate that all required configuration keys exist
     * 
     * @return bool True if all required configs are present
     */
    public function validate()
    {
        if (!$this->ensureLoaded()) {
            return false;
        }

        $this->errors = [];

        foreach ($this->requiredKeys as $key) {
            if (!isset($this->config[$key])) {
                $this->errors[] = "Missing required config: $key";
            }
        }

        return empty($this->errors);
    }

    /**
     * Get list of required configuration keys
     * 
     * @return array List of required keys
     */
    public function getRequiredKeys()
    {
        return $this->requiredKeys;
    }

    /**
     * Get list of missing required configuration keys
     * 
     * @return array List of missing keys
     */
    public function getMissingKeys()
    {
        if (!$this->ensureLoaded()) {
            return $this->requiredKeys;
        }

        $missing = [];
        foreach ($this->requiredKeys as $key) {
            if (!isset($this->config[$key])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Get all validation errors
     * 
     * @return array List of error messages
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Reload configuration from files
     * Force fresh load from disk
     * 
     * @param string $site_config_path Path to site_config.php
     * @param string $tools_config_path Path to tools_config.php
     * @return bool True if reload successful
     */
    public function reload($site_config_path, $tools_config_path)
    {
        $this->config = [];
        $this->tools = [];
        $this->loaded = false;
        $this->errors = [];

        return $this->initialize($site_config_path, $tools_config_path);
    }

    /**
     * Get all loaded configuration (for debugging)
     * 
     * @param bool $include_tools Include tool registry in output
     * @return array All loaded configuration
     */
    public function getAllConfig($include_tools = true)
    {
        $result = [
            'loaded' => $this->loaded,
            'config' => $this->config,
            'errors' => $this->errors,
        ];

        if ($include_tools) {
            $result['tools'] = $this->tools;
        }

        return $result;
    }

    /**
     * Check if configuration is loaded
     * 
     * @return bool True if loaded
     */
    public function isLoaded()
    {
        return $this->loaded;
    }
}

?>
