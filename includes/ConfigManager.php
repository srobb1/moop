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

        // Load editable configuration (overrides defaults from site_config.php)
        $editable_config_path = dirname($site_config_path) . '/config_editable.json';
        if (file_exists($editable_config_path)) {
            $editable_config = json_decode(file_get_contents($editable_config_path), true);
            if (is_array($editable_config)) {
                // Only merge allowed keys to prevent overriding structural config
                $allowed_editable_keys = ['siteTitle', 'admin_email', 'sequence_types', 'header_img', 'favicon_filename', 'auto_login_ip_ranges'];
                foreach ($allowed_editable_keys as $key) {
                    // Only override if value is set AND non-empty (preserve site_config defaults for empty values)
                    if (isset($editable_config[$key]) && ($editable_config[$key] !== '' && $editable_config[$key] !== null)) {
                        $this->config[$key] = $editable_config[$key];
                    }
                }
            }
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

        // Special handling: construct favicon_path from favicon_filename
        if ($key === 'favicon_path') {
            $site = $this->config['site'] ?? '';
            $images_dir = $this->config['images_dir'] ?? 'images';
            $favicon_filename = $this->config['favicon_filename'] ?? 'favicon.ico';
            return "/$site/$images_dir/$favicon_filename";
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
            'protein' => ['pattern' => 'protein.aa.fa', 'label' => 'Protein', 'color' => 'bg-info'],
            'transcript' => ['pattern' => 'transcript.nt.fa', 'label' => 'mRNA', 'color' => 'bg-feature-mrna'],
            'cds' => ['pattern' => 'cds.nt.fa', 'label' => 'CDS', 'color' => 'bg-success'],
            'genome' => ['pattern' => 'genome.fa', 'label' => 'GENOME', 'color' => 'bg-warning text-dark'],
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

    /**
     * Save editable configuration to config_editable.json
     * Only allows specific keys to be edited (whitelist approach)
     * 
     * @param array $data Key-value pairs to save
     * @param string $config_dir Directory containing config_editable.json
     * @return array ['success' => bool, 'message' => string]
     */
    public function saveEditableConfig($data, $config_dir)
    {
        // Whitelist of allowed editable keys
        $allowed_keys = ['siteTitle', 'admin_email', 'sequence_types', 'header_img', 'favicon_filename', 'auto_login_ip_ranges'];
        
        // Filter to only allowed keys
        $editable_data = [];
        foreach ($allowed_keys as $key) {
            if (isset($data[$key])) {
                // Validate email
                if ($key === 'admin_email' && !empty($data[$key])) {
                    if (!filter_var($data[$key], FILTER_VALIDATE_EMAIL)) {
                        return [
                            'success' => false,
                            'message' => 'Invalid email address'
                        ];
                    }
                }
                
                // Validate sequence_types
                if ($key === 'sequence_types') {
                    if (!is_array($data[$key])) {
                        return [
                            'success' => false,
                            'message' => 'Sequence types must be an array'
                        ];
                    }
                    // Validate each sequence type has required fields
                    foreach ($data[$key] as $seq_type => $seq_config) {
                        if (!is_array($seq_config) || !isset($seq_config['label']) || !isset($seq_config['pattern'])) {
                            return [
                                'success' => false,
                                'message' => "Sequence type '$seq_type' is missing required fields (label, pattern)"
                            ];
                        }
                    }
                }
                
                // Validate IP ranges
                if ($key === 'auto_login_ip_ranges') {
                    if (!is_array($data[$key])) {
                        return [
                            'success' => false,
                            'message' => 'IP ranges must be an array'
                        ];
                    }
                    foreach ($data[$key] as $range) {
                        if (!isset($range['start']) || !isset($range['end'])) {
                            return [
                                'success' => false,
                                'message' => 'Each IP range must have start and end addresses'
                            ];
                        }
                        // Validate IP format
                        if (!filter_var($range['start'], FILTER_VALIDATE_IP) || !filter_var($range['end'], FILTER_VALIDATE_IP)) {
                            return [
                                'success' => false,
                                'message' => 'Invalid IP address format'
                            ];
                        }
                    }
                }
                
                if ($key === 'sequence_types' || $key === 'auto_login_ip_ranges') {
                    $editable_data[$key] = $data[$key];
                } else {
                    $editable_data[$key] = trim($data[$key]);
                }
            }
        }
        
        $config_file = $config_dir . '/config_editable.json';
        
        // Add metadata
        $editable_data['_metadata'] = [
            'last_updated' => date('c'),
            'last_updated_by' => get_username() ?? 'unknown'
        ];
        
        $json = json_encode($editable_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Failed to encode config: ' . json_last_error_msg()
            ];
        }
        
        if (file_put_contents($config_file, $json) === false) {
            return [
                'success' => false,
                'message' => "Failed to write config file. Check file permissions on: $config_file"
            ];
        }
        
        // Update in-memory config
        foreach ($editable_data as $key => $value) {
            if ($key !== '_metadata') {
                $this->config[$key] = $value;
            }
        }
        
        // Log the change
        $this->logConfigChange($data, $config_dir);
        
        return [
            'success' => true,
            'message' => 'Configuration saved successfully'
        ];
    }

    /**
     * Log configuration changes to change_log file
     * 
     * @param array $data The configuration data that was changed
     * @param string $config_dir Directory containing change_log
     * @return void
     */
    private function logConfigChange($data, $config_dir)
    {
        $log_dir = dirname($config_dir) . '/change_log';
        
        // Ensure log directory exists
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0775, true);
        }
        
        $log_file = $log_dir . '/site_config.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $username = get_username() ?? 'unknown';
        
        // Build changes description
        $changes = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // For sequence types, show count
                $changes[] = "$key (types: " . count($value) . ")";
            } else {
                $changes[] = "$key";
            }
        }
        $changes_str = implode(', ', $changes);
        
        $log_entry = "[$timestamp] UPDATE by $username | Changed: $changes_str\n";
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Get editable configuration keys and their current values
     * 
     * @return array Array of editable config items with metadata
     */
    public function getEditableConfigMetadata()
    {
        return [
            'siteTitle' => [
                'label' => 'Site Title',
                'description' => 'The name displayed on the website (header, browser tab, etc.)',
                'type' => 'text',
                'current_value' => $this->getString('siteTitle', ''),
                'max_length' => 100,
            ],
            'admin_email' => [
                'label' => 'Administrator Email',
                'description' => 'Contact email for site administrators',
                'type' => 'email',
                'current_value' => $this->getString('admin_email', ''),
                'max_length' => 255,
            ],
            'sequence_types' => [
                'label' => 'Sequence File Types',
                'description' => 'Available sequence file types for searches. Customize labels and badge colors as needed.',
                'type' => 'sequence_types',
                'current_value' => $this->getSequenceTypes(),
                'note' => 'File patterns are read-only and match files in organism directories. Badge colors use Bootstrap CSS classes (e.g., bg-info, bg-success, bg-warning)',
            ],
            'header_img' => [
                'label' => 'Header Banner Image',
                'description' => 'Main banner image displayed at top of pages',
                'type' => 'file_upload',
                'current_value' => $this->getString('header_img', ''),
                'upload_info' => [
                    'destination' => 'images/banners/',
                    'recommended_dimensions' => '1920 x 300 px',
                    'min_width' => 1200,
                    'max_width' => 4000,
                    'min_height' => 200,
                    'max_height' => 500,
                    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    'max_size_mb' => 5,
                ],
            ],
            'favicon_filename' => [
                'label' => 'Favicon Image',
                'description' => 'Browser tab icon (32x32 px recommended)',
                'type' => 'file_upload',
                'current_value' => $this->getString('favicon_filename', 'favicon.ico'),
                'upload_info' => [
                    'destination' => 'images/',
                    'recommended_dimensions' => '32 x 32 px',
                    'min_width' => 16,
                    'max_width' => 256,
                    'min_height' => 16,
                    'max_height' => 256,
                    'allowed_types' => ['ico', 'png', 'jpg', 'jpeg', 'gif', 'webp'],
                    'max_size_mb' => 1,
                ],
            ],
            'auto_login_ip_ranges' => [
                'label' => 'Auto-Login IP Ranges',
                'description' => 'IP ranges for automatic admin login (development/testing only)',
                'type' => 'ip_ranges',
                'current_value' => $this->getArray('auto_login_ip_ranges', []),
                'note' => 'WARNING: Only use for development. Provides full access without login.',
            ],
        ];
    }
}

?>
