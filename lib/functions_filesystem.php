<?php
/**
 * MOOP Filesystem Functions
 * Directory validation, file operations, and assembly directory management
 */

/**
 * Validate assembly directories match database records
 * 
 * Checks that for each genome in the database, there is a corresponding directory
 * named either genome_name or genome_accession
 * 
 * @param string $dbFile - Path to SQLite database file
 * @param string $organism_data_dir - Path to organism data directory
 * @return array - Validation results with genomes list and mismatches
 */
function validateAssemblyDirectories($dbFile, $organism_data_dir) {
    $result = [
        'valid' => true,
        'genomes' => [],
        'mismatches' => [],
        'errors' => []
    ];
    
    if (!file_exists($dbFile) || !is_readable($dbFile)) {
        $result['valid'] = false;
        $result['errors'][] = 'Database not readable';
        return $result;
    }
    
    if (!is_dir($organism_data_dir)) {
        $result['valid'] = false;
        $result['errors'][] = 'Organism directory not found';
        return $result;
    }
    
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get all genome records
        $stmt = $dbh->query("SELECT genome_id, genome_name, genome_accession FROM genome ORDER BY genome_name");
        $genomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get list of directories in organism folder
        $dirs = array_diff(scandir($organism_data_dir), ['.', '..']);
        $dir_names = [];
        foreach ($dirs as $dir) {
            $full_path = "$organism_data_dir/$dir";
            if (is_dir($full_path)) {
                $dir_names[] = $dir;
            }
        }
        
        // Check each genome record
        foreach ($genomes as $genome) {
            $name = $genome['genome_name'];
            $accession = $genome['genome_accession'];
            $genome_id = $genome['genome_id'];
            
            // Check if either name or accession matches a directory
            $found_dir = null;
            if (in_array($name, $dir_names)) {
                $found_dir = $name;
            } elseif (in_array($accession, $dir_names)) {
                $found_dir = $accession;
            }
            
            $result['genomes'][] = [
                'genome_id' => $genome_id,
                'genome_name' => $name,
                'genome_accession' => $accession,
                'directory_found' => $found_dir,
                'exists' => $found_dir !== null
            ];
            
            if ($found_dir === null) {
                $result['valid'] = false;
                $result['mismatches'][] = [
                    'type' => 'missing_directory',
                    'genome_name' => $name,
                    'genome_accession' => $accession,
                    'message' => "No directory found matching genome_name '$name' or genome_accession '$accession'"
                ];
            } elseif ($found_dir !== $name && $found_dir !== $accession) {
                // Directory exists but doesn't match expected names
                $result['mismatches'][] = [
                    'type' => 'name_mismatch',
                    'genome_name' => $name,
                    'genome_accession' => $accession,
                    'found_directory' => $found_dir,
                    'message' => "Directory '$found_dir' found, but doesn't match genome_name '$name' or genome_accession '$accession'"
                ];
            }
        }
        
        $dbh = null;
    } catch (PDOException $e) {
        $result['valid'] = false;
        $result['errors'][] = 'Database query failed: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Validate assembly FASTA files exist
 * 
 * Checks if each assembly directory contains the required FASTA files
 * based on sequence_types patterns from site config
 * 
 * @param string $organism_dir - Path to organism directory
 * @param array $sequence_types - Sequence type patterns from site_config
 * @return array - Validation results for each assembly
 */
function validateAssemblyFastaFiles($organism_dir, $sequence_types) {
    $result = [
        'assemblies' => [],
        'missing_files' => []
    ];
    
    if (!is_dir($organism_dir)) {
        return $result;
    }
    
    // Get all directories in organism folder
    $dirs = array_diff(scandir($organism_dir), ['.', '..']);
    
    foreach ($dirs as $dir) {
        $full_path = "$organism_dir/$dir";
        if (!is_dir($full_path)) {
            continue;
        }
        
        $assembly_info = [
            'name' => $dir,
            'fasta_files' => [],
            'missing_patterns' => []
        ];
        
        // Check for each sequence type pattern
        foreach ($sequence_types as $type => $config) {
            $pattern = $config['pattern'];
            $files = glob("$full_path/*$pattern");
            
            if (!empty($files)) {
                $assembly_info['fasta_files'][$type] = [
                    'found' => true,
                    'pattern' => $pattern,
                    'file' => basename($files[0])
                ];
            } else {
                $assembly_info['fasta_files'][$type] = [
                    'found' => false,
                    'pattern' => $pattern
                ];
                $assembly_info['missing_patterns'][] = $pattern;
            }
        }
        
        $result['assemblies'][$dir] = $assembly_info;
        
        if (!empty($assembly_info['missing_patterns'])) {
            $result['missing_files'][$dir] = $assembly_info['missing_patterns'];
        }
    }
    
    return $result;
}

/**
 * Rename an assembly directory
 * 
 * Renames a directory within an organism folder from old_name to new_name
 * Used to align directory names with genome_name or genome_accession
 * Returns manual command if automatic rename fails
 * 
 * @param string $organism_dir - Path to organism directory
 * @param string $old_name - Current directory name
 * @param string $new_name - New directory name
 * @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
 */
function renameAssemblyDirectory($organism_dir, $old_name, $new_name) {
    $result = [
        'success' => false,
        'message' => '',
        'command' => ''
    ];
    
    if (!is_dir($organism_dir)) {
        $result['message'] = 'Organism directory not found';
        return $result;
    }
    
    $old_path = "$organism_dir/$old_name";
    $new_path = "$organism_dir/$new_name";
    
    // Validate old directory exists
    if (!is_dir($old_path)) {
        $result['message'] = "Directory '$old_name' not found";
        return $result;
    }
    
    // Check new name doesn't already exist
    if (is_dir($new_path) || file_exists($new_path)) {
        $result['message'] = "Directory '$new_name' already exists";
        return $result;
    }
    
    // Sanitize names to prevent path traversal
    if (strpos($old_name, '/') !== false || strpos($new_name, '/') !== false ||
        strpos($old_name, '..') !== false || strpos($new_name, '..') !== false) {
        $result['message'] = 'Invalid directory name (contains path separators)';
        return $result;
    }
    
    // Build command for admin to run if automatic rename fails
    $result['command'] = "cd " . escapeshellarg($organism_dir) . " && mv " . escapeshellarg($old_name) . " " . escapeshellarg($new_name);
    
    // Try to rename
    if (@rename($old_path, $new_path)) {
        $result['success'] = true;
        $result['message'] = "Successfully renamed '$old_name' to '$new_name'";
    } else {
        $result['message'] = 'Web server lacks permission to rename directory.';
    }
    
    return $result;
}

/**
 * Delete an assembly directory
 * 
 * Recursively deletes a directory within an organism folder
 * Used to remove incorrectly named or unused assembly directories
 * Returns manual command if automatic delete fails
 * 
 * @param string $organism_dir - Path to organism directory
 * @param string $dir_name - Directory name to delete
 * @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
 */
function deleteAssemblyDirectory($organism_dir, $dir_name) {
    $result = [
        'success' => false,
        'message' => '',
        'command' => ''
    ];
    
    if (!is_dir($organism_dir)) {
        $result['message'] = 'Organism directory not found';
        return $result;
    }
    
    $dir_path = "$organism_dir/$dir_name";
    
    // Validate directory exists
    if (!is_dir($dir_path)) {
        $result['message'] = "Directory '$dir_name' not found";
        return $result;
    }
    
    // Prevent deletion of non-assembly directories
    if ($dir_name === '.' || $dir_name === '..' || strpos($dir_name, '/') !== false || 
        strpos($dir_name, '..') !== false || $dir_name === 'organism.json') {
        $result['message'] = 'Invalid directory name (security check failed)';
        return $result;
    }
    
    // Build command for admin to run if automatic delete fails
    $result['command'] = "rm -rf " . escapeshellarg($dir_path);
    
    // Try to delete recursively
    if (rrmdir($dir_path)) {
        $result['success'] = true;
        $result['message'] = "Successfully deleted directory '$dir_name'";
    } else {
        $result['message'] = 'Web server lacks permission to delete directory.';
    }
    
    return $result;
}

/**
 * Recursively remove directory
 * 
 * Helper function to delete a directory and all its contents
 * 
 * @param string $dir - Directory path
 * @return bool - True if successful
 */
function rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (!rrmdir($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }
    
    return @rmdir($dir);
}

/**
 * Check file writeability and return error info if file is not writable
 * Uses web server group and keeps original owner
 * 
 * @param string $filepath - Path to file to check
 * @return array|null - Array with error details if not writable, null if ok
 */
function getFileWriteError($filepath) {
    if (!file_exists($filepath) || is_writable($filepath)) {
        return null;
    }
    
    $webserver = getWebServerUser();
    $web_user = $webserver['user'];
    $web_group = $webserver['group'];
    
    $current_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($filepath))['name'] ?? 'unknown' : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($filepath)), -4);
    
    // Command keeps original owner but changes group to webserver and sets perms to 664
    $fix_command = "sudo chgrp " . escapeshellarg($web_group) . " " . escapeshellarg($filepath) . " && sudo chmod 664 " . escapeshellarg($filepath);
    
    return [
        'owner' => $current_owner,
        'perms' => $current_perms,
        'web_user' => $web_user,
        'web_group' => $web_group,
        'command' => $fix_command,
        'file' => $filepath
    ];
}

/**
 * Check directory existence and writeability, return error info if issues found
 * Uses owner of /moop directory and web server group
 * Automatically detects if sudo is needed for the commands
 * 
 * Usage:
 *   $dir_error = getDirectoryError('/path/to/directory');
 *   if ($dir_error) {
 *       // Display error alert with fix instructions
 *   }
 * 
 * Can be used in any admin page that needs to ensure a directory exists and is writable.
 * Common use cases:
 *   - Image cache directories (ncbi_taxonomy, organisms, etc)
 *   - Log directories
 *   - Upload/temp directories
 *   - Any other required filesystem paths
 * 
 * @param string $dirpath - Path to directory to check
 * @return array|null - Array with error details if directory missing/not writable, null if ok
 */
function getDirectoryError($dirpath) {
    if (is_dir($dirpath) && is_writable($dirpath)) {
        return null;
    }
    
    $webserver = getWebServerUser();
    $web_group = $webserver['group'];
    
    // Get owner from /moop directory
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
    
    // Detect if sudo is needed
    $current_uid = function_exists('posix_getuid') ? posix_getuid() : null;
    $need_sudo = false;
    
    if ($current_uid !== null && $current_uid !== 0) {
        // Not running as root, check if we're the moop owner
        $current_user = function_exists('posix_getpwuid') ? posix_getpwuid($current_uid)['name'] ?? null : null;
        if ($current_user !== $moop_owner) {
            $need_sudo = true;
        }
    }
    
    // Helper function to add sudo if needed
    $cmd_prefix = $need_sudo ? 'sudo ' : '';
    
    if (!is_dir($dirpath)) {
        // Directory doesn't exist
        return [
            'type' => 'missing',
            'dir' => $dirpath,
            'owner' => $moop_owner,
            'group' => $web_group,
            'need_sudo' => $need_sudo,
            'commands' => [
                "{$cmd_prefix}mkdir -p " . escapeshellarg($dirpath),
                "{$cmd_prefix}chown {$moop_owner}:{$web_group} " . escapeshellarg($dirpath),
                "{$cmd_prefix}chmod 775 " . escapeshellarg($dirpath)
            ]
        ];
    }
    
    // Directory exists but not writable
    $current_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dirpath))['name'] ?? 'unknown' : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($dirpath)), -4);
    
    return [
        'type' => 'not_writable',
        'dir' => $dirpath,
        'owner' => $current_owner,
        'perms' => $current_perms,
        'target_owner' => $moop_owner,
        'target_group' => $web_group,
        'need_sudo' => $need_sudo,
        'commands' => [
            "{$cmd_prefix}chown {$moop_owner}:{$web_group} " . escapeshellarg($dirpath),
            "{$cmd_prefix}chmod 775 " . escapeshellarg($dirpath)
        ]
    ];
}
