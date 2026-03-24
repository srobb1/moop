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

$moop_owner = getMoopOwner();

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
        'required_group' => $web_group,
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
        'required_group' => $web_group,
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit (shown as \'s\' in permissions) ensures new files automatically get ' . $web_group . ' as group',
        'why_write' => 'Web server needs to create/write files here. SGID ensures group is always ' . $web_group . ' without manual fixes',
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new files automatically get ' . $web_group . ' as group',
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
        'required_group' => $web_group,
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
        'required_group' => $web_group,
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new log files automatically get ' . $web_group . ' as group',
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new image files automatically get ' . $web_group . ' as group',
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures downloaded taxonomy images automatically get ' . $web_group . ' as group',
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new banner files automatically get ' . $web_group . ' as group',
        'why_write' => 'Web server uploads new banners and deletes old ones through admin interface. Existing files also need 664 permissions.',
        'sgid_bit' => true,
    ],
    
    // Genome Data Directory
    [
        'name' => 'Genome Data Directory',
        'description' => 'Reference genomes and annotations per organism/assembly',
        'type' => 'directory',
        'paths' => [$config->getPath('genomes_directory')],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new genome files automatically get ' . $web_group . ' as group',
        'why_write' => 'Web server reads genome files for JBrowse2 and BLAST; admin may upload new assemblies',
        'sgid_bit' => true,
    ],

    // Track Data Directory
    [
        'name' => 'Track Data Directory',
        'description' => 'Additional track files (BigWig, BAM, VCF, etc.) served via JWT authentication',
        'type' => 'directory',
        'paths' => [$config->getPath('tracks_directory')],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new track files automatically get ' . $web_group . ' as group',
        'why_write' => 'Web server reads track files through api/jbrowse2/tracks.php; admin may add new tracks',
        'sgid_bit' => true,
    ],

    // JWT Certificates Directory
    [
        'name' => 'JWT Certificates Directory',
        'description' => 'Private and public keys for JBrowse2 track authentication',
        'type' => 'directory',
        'paths' => [$config->getPath('certs_directory')],
        'required_perms' => '2750',
        'required_owner' => $moop_owner,
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new certificate files automatically get ' . $web_group . ' as group',
        'why_write' => 'Web server reads keys to sign/verify JWT tokens for track access',
        'sgid_bit' => true,
    ],

    // JWT Key Files
    [
        'name' => 'JWT Key Files',
        'description' => 'RSA private and public keys used to sign JBrowse2 track tokens',
        'type' => 'file',
        'paths' => [
            $config->getPath('jwt_private_key'),
            $config->getPath('jwt_public_key'),
        ],
        'required_perms' => '640',
        'required_owner' => $moop_owner,
        'required_group' => $web_group,
        'reason' => 'Private key must never be world-readable; web server needs read access to sign tokens',
        'why_write' => 'Keys are generated once during setup and only read thereafter',
    ],

    // Documentation Directory
    [
        'name' => 'Documentation Directory',
        'description' => 'README files and documentation for the system',
        'type' => 'directory',
        'paths' => [$docs_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures new documentation files automatically get ' . $web_group . ' as group',
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures backup files automatically get ' . $web_group . ' as group',
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
        'required_group' => $web_group,
        'reason' => 'SGID (Set-Group-ID) bit ensures change log files automatically get ' . $web_group . ' as group',
        'why_write' => 'Web server logs all admin actions for auditing',
        'sgid_bit' => true,
    ],
];

// Check permissions for each item
$checks = [];
foreach ($permission_items as $item) {
    if ($item['type'] === 'directory' || ($item['type'] === 'file' && !isset($item['pattern']))) {
        foreach ($item['paths'] ?? [] as $path) {
            $checks[] = performPermissionCheck($path, $item, $web_group);
        }
    }
}

// Check assembly subdirectories and FASTA files
$assembly_subdir_issues = [];
$fasta_file_issues = [];
if (is_dir($organism_data)) {
    foreach (scandir($organism_data) as $organism) {
        if ($organism !== '.' && $organism !== '..' && is_dir($organism_data . '/' . $organism)) {
            $org_path = $organism_data . '/' . $organism;
            
            // Check organism subdirectory itself
            $check = performPermissionCheck($org_path, [
                'name' => 'Organism Directory',
                'type' => 'directory',
                'required_perms' => '2775',
                'required_group' => $web_group,
                'reason' => 'SGID required for assembly rename operations',
                'why_write' => 'Web server needs to rename/move assembly subdirectories',
            ], $web_group);
            
            if (!empty($check['issues'])) {
                $assembly_subdir_issues[] = $check;
            }
            
            // Check assembly subdirectories and FASTA files
            foreach (scandir($org_path) as $item) {
                $item_path = $org_path . '/' . $item;
                
                // Skip dots and files
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                if (is_dir($item_path)) {
                    // Check assembly subdirectory
                    $check = performPermissionCheck($item_path, [
                        'name' => 'Assembly Subdirectory: ' . $organism . '/' . $item,
                        'type' => 'directory',
                        'required_perms' => '2775',
                        'required_group' => $web_group,
                        'reason' => 'Web server needs to write BLAST index files here',
                        'why_write' => 'BLAST indexes (.nhr, .nin, .nsq, .phr, .pin, .psq) must be writable by web server',
                    ], $web_group);
                    
                    if (!empty($check['issues'])) {
                        $assembly_subdir_issues[] = $check;
                    }
                    
                    // Check FASTA files in assembly directory based on configured patterns
                    $sequence_types = $config->getSequenceTypes();
                    foreach ($sequence_types as $seq_type => $seq_config) {
                        $pattern = $seq_config['pattern'] ?? '';
                        if (empty($pattern)) {
                            continue;
                        }
                        
                        // Build the expected filename from the pattern
                        $expected_file = basename($pattern);
                        $fasta_path = $item_path . '/' . $expected_file;
                        
                        // Only check if file exists
                        if (file_exists($fasta_path)) {
                            $check = performPermissionCheck($fasta_path, [
                                'name' => 'FASTA File: ' . $organism . '/' . $item . '/' . $expected_file,
                                'type' => 'file',
                                'required_perms' => '644',
                                'required_group' => $web_group,
                                'reason' => ucfirst($seq_type) . ' file must be readable by web server for BLAST',
                                'why_write' => 'Web server reads ' . $seq_type . ' files to run BLAST searches',
                            ], $web_group);
                            
                            if (!empty($check['issues'])) {
                                $fasta_file_issues[] = $check;
                            }
                        }
                    }
                }
            }
        }
    }
}

function performPermissionCheck($path, $item, $web_group = 'www-data') {
    $result = [
        'name' => $item['name'],
        'path' => $path,
        'exists' => file_exists($path),
        'type' => $item['type'],
        'required_perms' => $item['required_perms'],
        'required_group' => $item['required_group'] ?? $web_group,
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
    $file_uid = fileowner($path);
    $file_gid = filegroup($path);
    $owner = 'unknown';
    $group = 'unknown';
    if (function_exists('posix_getpwuid')) {
        $pw = posix_getpwuid($file_uid);
        if ($pw) { $owner = $pw['name']; }
    }
    if (function_exists('posix_getgrgid')) {
        $gr = posix_getgrgid($file_gid);
        if ($gr) { $group = $gr['name']; }
    }
    // Fallback: use stat command if posix not available
    if ($owner === 'unknown' || $group === 'unknown') {
        $stat_out = [];
        @exec("stat -c '%U:%G' " . escapeshellarg($path) . " 2>/dev/null", $stat_out);
        if (!empty($stat_out[0])) {
            $parts = explode(':', $stat_out[0]);
            if ($owner === 'unknown' && !empty($parts[0])) { $owner = $parts[0]; }
            if ($group === 'unknown' && !empty($parts[1])) { $group = $parts[1]; }
        }
    }
    
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
$site = $config->getString('site');
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'checks' => $checks,
    'assembly_subdir_issues' => $assembly_subdir_issues,
    'fasta_file_issues' => $fasta_file_issues,
    'organism_data' => $organism_data,
    'moop_owner' => $moop_owner,
    'web_user' => $web_user,
    'web_group' => $web_group,
    'config' => $config,
    'page_styles' => ['/moop/css/manage-filesystem-permissions.css'],
    'page_script' => [
        '/' . $site . '/js/admin-utilities.js',
    ],
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
