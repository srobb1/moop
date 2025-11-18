<?php
/**
 * CONFIGURATION SCHEMA & DOCUMENTATION
 * 
 * This file documents the complete configuration system for admins and developers.
 * Reference this when adding, modifying, or debugging configuration.
 */

return [
    'documentation' => [
        'title' => 'Configuration System Guide for Admins',
        'version' => '1.0',
        
        'quick_start' => [
            'step_1' => 'Config files are in /data/moop/config/',
            'step_2' => 'site_config.php = application settings & paths',
            'step_3' => 'tools_config.php = available tools registry',
            'step_4' => 'Edit values in site_config.php, restart web server',
            'step_5' => 'ConfigManager handles the rest automatically',
        ],
        
        'required_config_keys' => [
            'root_path' => 'Server root directory (usually /var/www/html)',
            'site' => 'Site subdirectory name (e.g., moop, easy_gdb) - CHANGE THIS for different sites',
            'site_path' => 'Full path to site (root_path + site)',
            'organism_data' => 'Path to organism database files',
            'metadata_path' => 'Path to metadata files',
            'siteTitle' => 'Display name of your site',
            'admin_email' => 'Contact email for admins',
        ],
        
        'optional_config_keys' => [
            'images_dir' => 'Directory name for images (usually "images")',
            'images_path' => 'Web path for images (e.g., moop/images)',
            'absolute_images_path' => 'Filesystem path for images',
            'header_img' => 'Header image filename',
            'favicon_path' => 'URL path to favicon',
            'custom_css_path' => 'Path to custom CSS file',
            'users_file' => 'Path to users.json file',
            'sequence_types' => 'Array of available sequence file types',
        ],
        
        'paths_explained' => [
            'root_path' => 'Server filesystem root (usually /var/www/html for web hosting)',
            'site' => 'Your application\'s directory name within root_path. Change this when deploying a different site instance (e.g., "easy_gdb" instead of "moop")',
            'site_path' => 'Calculated as root_path + "/" + site. All app files are here.',
            'organism_data' => 'Where organism database files (.sqlite) and metadata live',
            'metadata_path' => 'Where JSON config files live (organism_assembly_groups.json, etc.)',
            'images_path' => 'Web-accessible path for images (used in <img src="/images_path/file.png">)',
            'absolute_images_path' => 'Filesystem path to same images (used when reading files)',
        ],
        
        'adding_new_tools' => [
            'step_1' => 'Create your tool PHP file in /data/moop/tools/ (or subdirectory)',
            'step_2' => 'Edit /data/moop/config/tools_config.php and add new entry following template',
            'step_3' => 'Test the tool link appears in the UI on specified pages',
            'step_4' => 'Done! No other code changes needed',
            'example' => 'See template in tools_config.php "HOW TO ADD A NEW TOOL" section',
        ],
        
        'adding_new_config_values' => [
            'step_1' => 'Add key-value pair to site_config.php return array',
            'step_2' => 'Add to config_schema.php optional_config_keys section (if optional)',
            'step_3' => 'In code: use ConfigManager::getInstance()->get("your_key")',
            'step_4' => 'Use type-specific getter: ->getString(), ->getPath(), ->getArray(), etc.',
            'step_5' => 'Validate: $config->validate() checks all required keys on boot',
        ],
        
        'deploying_to_different_site' => [
            'step_1' => 'Copy /data/moop/ to /var/www/html/YOUR_SITE_NAME/',
            'step_2' => 'Edit /var/www/html/YOUR_SITE_NAME/config/site_config.php',
            'step_3' => 'Change: $site = "YOUR_SITE_NAME"; (at top of file)',
            'step_4' => 'All paths and URLs automatically update',
            'step_5' => 'Restart web server',
            'why' => 'site_config.php calculates all paths from the $site variable, so different deployments just change one value',
        ],
        
        'troubleshooting' => [
            'config_not_loading' => 'Check if /data/moop/config/site_config.php exists and is readable',
            'config_not_loading_2' => 'Check web server error logs for PHP errors',
            'tool_not_showing' => 'Check tools_config.php "pages" setting - does it include the current page?',
            'tool_not_showing_2' => 'Check access_control.php - user may not have permission',
            'site_won\'t_load' => 'Check required paths in site_config.php exist on server',
            'site_won\'t_load_2' => 'Run: php -d display_errors=1 -r "include \'config/site_config.php\'; var_dump(1);"',
            'undefined_config_key' => 'Add key to site_config.php return array, then access via ConfigManager',
            'need_to_debug' => 'Add to your page: $c = ConfigManager::getInstance(); var_dump($c->getAllConfig());',
        ],
        
        'security_notes' => [
            'access_control_separate' => 'User permissions are in includes/access_control.php, NOT in config',
            'config_is_data_only' => 'ConfigManager only handles settings and paths, never touches user sessions',
            'assembly_access_untouched' => 'Assembly-level access is still determined by has_assembly_access() function',
            'clean_separation' => 'Config layer (ConfigManager) and Security layer (access_control) are completely separate',
        ],
    ],
];

?>
