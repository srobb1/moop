<?php
/**
 * MANAGE FILESYSTEM PERMISSIONS - Wrapper
 * 
 * Handles admin access verification and renders filesystem permissions
 * management using clean architecture layout system.
 */

ob_start();
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Get paths from config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$absolute_images_path = $config->getPath('absolute_images_path');
$site_path = $config->getPath('site_path');
$root_path = $config->getPath('root_path');
$docs_path = $config->getPath('docs_path');

// Get web server user/group info from system
$webserver = getWebServerUser();
$web_user = $webserver['user'];
$web_group = $webserver['group'];

// Get moop owner from /moop directory (where the actual files are owned)
$moop_owner = 'ubuntu';  // Default fallback
if (function_exists('posix_getpwuid')) {
    $moop_info = @stat(__DIR__ . '/..');  // Get stat of /moop parent directory
    if ($moop_info) {
        $moop_pwd = posix_getpwuid($moop_info['uid']);
        if ($moop_pwd) {
            $moop_owner = $moop_pwd['name'];
        }
    }
}

// Define all required permissions
$permission_items = [
    // Site Configuration Files - Require Write
    [
        'name' => 'Site Configuration Files',
        'description' => 'Site configuration files edited through admin interface',
        'type' => 'file',
        'paths' => [
            $config->getPath('root_path') . '/' . $config->getString('site') . '/config/config_editable.json',
        ],
        'required_perms' => '664',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Site configuration is edited by admins through the web interface',
        'why_write' => 'Admin interface needs to save changed site settings (title, email, etc.)',
    ],
    
    // Metadata Configuration Files - Require Write
    [
        'name' => 'Metadata Configuration Files',
        'description' => 'JSON configuration files for annotations, taxonomy, and groups',
        'type' => 'file',
        'paths' => [
            $metadata_path . '/annotation_config.json',
            $metadata_path . '/taxonomy_tree_config.json',
            $metadata_path . '/group_descriptions.json',
            $metadata_path . '/organism_assembly_groups.json',
        ],
        'required_perms' => '664',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Configuration files are edited by admins and read by the web server',
        'why_write' => 'Admin interface needs to modify these files when you change settings',
    ],
    
    // Metadata Directory - SGID for Group Assignment
    [
        'name' => 'Metadata Directory',
        'description' => 'Parent directory for all configuration files',
        'type' => 'directory',
        'paths' => [$metadata_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit (shown as \'s\' in permissions) ensures new files automatically get www-data as group',
        'why_write' => 'Web server needs to create/write files here. SGID ensures group is always www-data without manual fixes',
        'sgid_bit' => true,
    ],
    
    // Organism Directories
    [
        'name' => 'Organism Data Directories',
        'description' => 'Parent directory and subdirectories for all organisms',
        'type' => 'directory',
        'paths' => [
            $organism_data,
        ],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new files automatically get www-data as group',
        'why_write' => 'Web server needs to read databases, organism.json files, and RENAME/MOVE assembly subdirectories during admin operations',
        'sgid_bit' => true,
    ],
    
    // Organism.json Files - Require Write
    [
        'name' => 'Organism Metadata Files',
        'description' => 'JSON files describing each organism (genus, species, images, etc.)',
        'type' => 'file_pattern',
        'pattern' => 'organisms/*/organism.json',
        'required_perms' => '664',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Edited by admin interface, read by web server',
        'why_write' => 'Admin can update organism metadata (descriptions, images, feature types)',
    ],
    
    // Database Files - Read Only
    [
        'name' => 'SQLite Database Files',
        'description' => 'Database files containing feature, annotation, and genome data',
        'type' => 'file_pattern',
        'pattern' => 'organisms/*/organism.sqlite',
        'required_perms' => '644',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Web server reads data; files are pre-built and not modified',
        'why_write' => 'Database files must be readable by web server but typically not written to',
    ],
    
    // Logs Directory - Write Required
    [
        'name' => 'Logs Directory',
        'description' => 'Application log files for debugging and monitoring',
        'type' => 'directory',
        'paths' => [$site_path . '/logs'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new log files automatically get www-data as group',
        'why_write' => 'Web server writes error and debug logs here',
        'sgid_bit' => true,
    ],
    
    // Images Directory - Write for Uploads
    [
        'name' => 'Images Directory',
        'description' => 'Organism images and banners displayed on web pages',
        'type' => 'directory',
        'paths' => [$absolute_images_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new image files automatically get www-data as group',
        'why_write' => 'Admin may upload new organism images via web interface',
        'sgid_bit' => true,
    ],
    
    // NCBI Taxonomy Images Cache - Write for Downloaded Images
    [
        'name' => 'NCBI Taxonomy Images Cache',
        'description' => 'Cached images downloaded from NCBI taxonomy database',
        'type' => 'directory',
        'paths' => [$absolute_images_path . '/ncbi_taxonomy'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures downloaded taxonomy images automatically get www-data as group',
        'why_write' => 'Web server downloads and caches organism images from NCBI when generating taxonomy tree',
        'sgid_bit' => true,
    ],
    
    // Banner Images Directory - Write for Upload/Delete
    [
        'name' => 'Banner Images Directory',
        'description' => 'Banner images managed through site configuration interface',
        'type' => 'directory',
        'paths' => [$absolute_images_path . '/banners'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new banner files automatically get www-data as group',
        'why_write' => 'Web server uploads new banners and deletes old ones through admin interface. Existing files also need 664 permissions.',
        'sgid_bit' => true,
    ],
    
    // Documentation Directory
    [
        'name' => 'Documentation Directory',
        'description' => 'README files and documentation for the system',
        'type' => 'directory',
        'paths' => [$docs_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new documentation files automatically get www-data as group',
        'why_write' => 'Docs may be updated through admin interface',
        'sgid_bit' => true,
    ],
    
    // Backups Directory
    [
        'name' => 'Metadata Backups Directory',
        'description' => 'Automatic backups of configuration files',
        'type' => 'directory',
        'paths' => [$metadata_path . '/backups'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures backup files automatically get www-data as group',
        'why_write' => 'Web server creates backup files when configs are updated',
        'sgid_bit' => true,
    ],
    
    // Change Log Directory
    [
        'name' => 'Change Log Directory',
        'description' => 'Records of changes made through admin interface',
        'type' => 'directory',
        'paths' => [$metadata_path . '/change_log'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures change log files automatically get www-data as group',
        'why_write' => 'Web server logs all admin actions for auditing',
        'sgid_bit' => true,
    ],
];

// Check permissions for each item
$checks = [];
foreach ($permission_items as $item) {
    if ($item['type'] === 'directory' || ($item['type'] === 'file' && !isset($item['pattern']))) {
        foreach ($item['paths'] ?? [] as $path) {
            $checks[] = performPermissionCheck($path, $item);
        }
    }
}

// Check assembly subdirectories for rename/move permission issues
$assembly_subdir_issues = [];
if (is_dir($organism_data)) {
    foreach (scandir($organism_data) as $organism) {
        if ($organism !== '.' && $organism !== '..' && is_dir($organism_data . '/' . $organism)) {
            $subdir_path = $organism_data . '/' . $organism;
            $check = performPermissionCheck($subdir_path, [
                'name' => 'Assembly Subdirectory',
                'type' => 'directory',
                'required_perms' => '2775',
                'required_group' => 'www-data',
                'reason' => 'SGID required for assembly rename operations',
                'why_write' => 'Web server needs to rename/move assembly subdirectories',
            ]);
            
            // Only include in report if there are issues
            if (!empty($check['issues'])) {
                $assembly_subdir_issues[] = $check;
            }
        }
    }
}

function performPermissionCheck($path, $item) {
    $result = [
        'name' => $item['name'],
        'path' => $path,
        'exists' => file_exists($path),
        'type' => $item['type'],
        'required_perms' => $item['required_perms'],
        'required_group' => $item['required_group'] ?? 'www-data',
        'reason' => $item['reason'] ?? '',
        'why_write' => $item['why_write'] ?? '',
        'sticky_bit' => $item['sticky_bit'] ?? false,
        'issues' => [],
    ];
    
    if (!$result['exists']) {
        $result['issues'][] = 'Path does not exist';
        return $result;
    }
    
    $perms_full = substr(sprintf('%o', fileperms($path)), -4);
    // Remove leading zero for comparison (0664 -> 664, 02775 -> 2775)
    $perms = ltrim($perms_full, '0') ?: '0';
    $owner = posix_getpwuid(fileowner($path))['name'] ?? 'unknown';
    $group = posix_getgrgid(filegroup($path))['name'] ?? 'unknown';
    
    $result['current_perms'] = $perms;
    $result['current_owner'] = $owner;
    $result['current_group'] = $group;
    $result['is_readable'] = is_readable($path);
    $result['is_writable'] = is_writable($path);
    
    // Check permissions
    if ($perms !== $item['required_perms']) {
        $result['issues'][] = "Permissions are $perms, should be " . $item['required_perms'];
    }
    
    // Check group
    if (isset($item['required_group']) && $group !== $item['required_group']) {
        $result['issues'][] = "Group is $group, should be " . $item['required_group'];
    }
    
    return $result;
}

// Prepare data for display
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $config->getString('site'),
    'checks' => $checks,
    'assembly_subdir_issues' => $assembly_subdir_issues,
    'organism_data' => $organism_data,
    'moop_owner' => $moop_owner,
    'web_user' => $web_user,
    'web_group' => $web_group,
    'config' => $config,
    'page_styles' => ['/moop/css/manage-filesystem-permissions.css'],
];

$display_config = [
    'content_file' => __DIR__ . '/pages/manage_filesystem_permissions.php',
    'title' => 'Filesystem Permissions'
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
