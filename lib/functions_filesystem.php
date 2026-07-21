<?php
/**
 * MOOP Filesystem Functions
 * Directory validation, file operations, and assembly directory management
 */

/**
 * Does this directory hold a gene set?
 *
 * Answered by "does it contain any configured sequence FASTA", because the FASTA is the
 * payload — a gene-set directory without one has nothing to serve. Verified against the
 * live tree 2026-07-16: 94 dirs have both a FASTA and geneset.json, 0 have only one, so
 * either marker works and this one needs no extra metadata file.
 *
 * Do NOT go back to testing for the GFF. That is what this replaced, and it was circular:
 * a gene set with no genes.gff was declared "not a real gene_set dir" and vanished from
 * the checks — 23 of the 94 on this deployment have no GFF (protein-only sets legitimately
 * have none; JBrowse annotations simply are not available for them). Using the GFF to
 * decide what IS a gene set means the ones missing it can never be reported.
 *
 * 'genome' is excluded: genome.fa lives at the ASSEMBLY level, so including it would make
 * every assembly directory look like a gene set.
 */
function is_gene_set_dir(string $dir): bool {
    if (!is_dir($dir)) return false;
    $types = ConfigManager::getInstance()->getSequenceTypes();
    unset($types['genome']);
    foreach ($types as $seq) {
        $pattern = $seq['pattern'] ?? '';
        if ($pattern !== '' && file_exists("$dir/$pattern")) return true;
    }
    return false;
}

/**
 * Validate directory name for security
 * 
 * Prevents path traversal attacks by checking for invalid characters
 * 
 * @param string $name - Directory name to validate
 * @return bool - True if valid, false if contains path separators or traversal attempts
 */
function validateDirectoryName($name) {
    return strpos($name, '/') === false && 
           strpos($name, '..') === false &&
           $name !== '.' && 
           $name !== '..' &&
           $name !== 'organism.json';
}

/**
 * Build standardized directory operation result
 * 
 * Factory function for consistent result array structure across directory operations
 * 
 * @param bool $success - Operation success status
 * @param string $message - Result message
 * @param string $command - Optional manual command if operation failed (for admin execution)
 * @return array - Result array with success, message, and command
 */
function buildDirectoryResult($success, $message, $command = '') {
    return [
        'success' => $success,
        'message' => $message,
        'command' => $command
    ];
}

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
        $dbh = getDbConnection($dbFile);

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
        
        // Get gene_set records grouped by genome_id
        $gene_sets_by_genome = [];
        try {
            $gs_stmt = $dbh->query("SELECT gene_set_id, genome_id, gene_set_name FROM gene_set ORDER BY gene_set_name");
            foreach ($gs_stmt->fetchAll(PDO::FETCH_ASSOC) as $gs) {
                $gene_sets_by_genome[$gs['genome_id']][] = $gs;
            }
        } catch (PDOException $e) {
            // gene_set table may not exist in older DBs — treat as no gene sets
        }

        // Check each genome record
        foreach ($genomes as $genome) {
            $name      = $genome['genome_name'];
            $accession = $genome['genome_accession'];
            $genome_id = $genome['genome_id'];

            // Check if either name or accession matches an assembly directory
            $found_dir = null;
            if (in_array($name, $dir_names)) {
                $found_dir = $name;
            } elseif (in_array($accession, $dir_names)) {
                $found_dir = $accession;
            }

            // Check gene_set subdirs within the assembly directory
            $gene_set_checks = [];
            if ($found_dir !== null) {
                $asm_path = "$organism_data_dir/$found_dir";
                foreach ($gene_sets_by_genome[$genome_id] ?? [] as $gs) {
                    $gs_exists = is_dir("$asm_path/{$gs['gene_set_name']}");
                    $gene_set_checks[] = [
                        'gene_set_id'   => $gs['gene_set_id'],
                        'gene_set_name' => $gs['gene_set_name'],
                        'dir_exists'    => $gs_exists,
                    ];
                    if (!$gs_exists) {
                        $result['valid'] = false;
                        $result['mismatches'][] = [
                            'type'           => 'missing_gene_set_directory',
                            'genome_name'    => $name,
                            'genome_accession' => $accession,
                            'assembly_dir'   => $found_dir,
                            'gene_set_name'  => $gs['gene_set_name'],
                            'message'        => "No directory found for gene_set '{$gs['gene_set_name']}' inside assembly '$found_dir'"
                        ];
                    }
                }

                // Reverse direction: gene_set directories on disk with no matching DB
                // row. Happens when a gene_set is dropped from the database (DB rebuilt
                // elsewhere, rows deleted) but its directory was never removed here — the
                // DB-driven checks above can't see this since they only walk DB rows.
                $known_gene_set_names = array_column($gene_sets_by_genome[$genome_id] ?? [], 'gene_set_name');
                foreach (glob("$asm_path/*", GLOB_ONLYDIR) ?: [] as $gs_dir) {
                    $gs_name = basename($gs_dir);
                    if (in_array($gs_name, $known_gene_set_names, true)) continue;
                    // Identify a gene set by its FASTA payload, NOT by genes.gff. The GFF
                    // test was circular: a gene set missing its GFF was dismissed as "not a
                    // real gene_set dir", so an orphaned one could never be reported — the
                    // exact case most worth reporting. 23 of 94 gene sets here have no GFF.
                    if (!is_gene_set_dir($gs_dir)) continue; // not a gene_set dir
                    $result['valid'] = false;
                    $result['mismatches'][] = [
                        'type'             => 'orphaned_gene_set_directory',
                        'genome_name'      => $name,
                        'genome_accession' => $accession,
                        'assembly_dir'     => $found_dir,
                        'gene_set_name'    => $gs_name,
                        'message'          => "Directory '{$gs_name}' exists on disk but has no matching gene_set row in the database — likely deleted upstream",
                    ];
                }
            }

            $result['genomes'][] = [
                'genome_id'        => $genome_id,
                'genome_name'      => $name,
                'genome_accession' => $accession,
                'directory_found'  => $found_dir,
                'exists'           => $found_dir !== null,
                'gene_set_checks'  => $gene_set_checks,
            ];

            if ($found_dir === null) {
                $result['valid'] = false;
                $result['mismatches'][] = [
                    'type'             => 'missing_directory',
                    'genome_name'      => $name,
                    'genome_accession' => $accession,
                    'message'          => "No directory found matching genome_name '$name' or genome_accession '$accession'"
                ];
            } elseif ($found_dir !== $name && $found_dir !== $accession) {
                $result['mismatches'][] = [
                    'type'             => 'name_mismatch',
                    'genome_name'      => $name,
                    'genome_accession' => $accession,
                    'found_directory'  => $found_dir,
                    'message'          => "Directory '$found_dir' found, but doesn't match genome_name '$name' or genome_accession '$accession'"
                ];
            }
        }

        // Reverse direction at the ASSEMBLY level: directories on disk that are shaped
        // like an assembly (they carry a genome.json) but match no genome row by name or
        // accession. The DB-driven loop above only walks rows outward to disk, so a whole
        // assembly dir dropped from the DB (rebuilt/removed upstream, or superseded by a
        // rename) but left on disk stays invisible to every other check. genome.json is
        // the marker for "this dir was meant to be an assembly" — same idea as requiring
        // a genes gff to treat a subdir as a real gene_set above.
        $known_assembly_dirs = [];
        foreach ($genomes as $genome) {
            if ($genome['genome_name'] !== '')      $known_assembly_dirs[$genome['genome_name']] = true;
            if ($genome['genome_accession'] !== '') $known_assembly_dirs[$genome['genome_accession']] = true;
        }
        foreach ($dir_names as $dir) {
            if (isset($known_assembly_dirs[$dir])) continue;
            if (!file_exists("$organism_data_dir/$dir/genome.json")) continue; // not an assembly dir
            $result['valid'] = false;
            $result['mismatches'][] = [
                'type'         => 'orphaned_assembly_directory',
                'assembly_dir' => $dir,
                'message'      => "Directory '$dir' exists on disk with a genome.json but has no matching genome row in the database — likely a stale leftover from a rename or reload",
            ];
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
        
        // Collect gene_set subdirs within this assembly dir
        $gene_set_dirs = array_values(array_filter(
            array_diff(scandir($full_path), ['.', '..']),
            fn($f) => is_dir("$full_path/$f")
        ));

        // Check for each sequence type pattern — assembly-level first, then gene_set subdirs
        foreach ($sequence_types as $type => $config) {
            $pattern = $config['pattern'];
            $found_file = null;

            // Check directly in the assembly dir (e.g. genome.fa lives here)
            $direct = glob("$full_path/*$pattern");
            if (!empty($direct)) {
                $found_file = basename($direct[0]);
            }

            // If not found at assembly level, check inside each gene_set subdir
            if ($found_file === null) {
                foreach ($gene_set_dirs as $gs) {
                    $files = glob("$full_path/$gs/*$pattern");
                    if (!empty($files)) {
                        $found_file = basename($files[0]);
                        break;
                    }
                }
            }

            if ($found_file !== null) {
                $assembly_info['fasta_files'][$type] = [
                    'found' => true,
                    'pattern' => $pattern,
                    'file' => $found_file
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
    if (!is_dir($organism_dir)) {
        return buildDirectoryResult(false, 'Organism directory not found');
    }
    
    // Validate both names for security
    if (!validateDirectoryName($old_name) || !validateDirectoryName($new_name)) {
        return buildDirectoryResult(false, 'Invalid directory name (contains path separators)');
    }
    
    $old_path = "$organism_dir/$old_name";
    $new_path = "$organism_dir/$new_name";
    
    // Validate old directory exists
    if (!is_dir($old_path)) {
        return buildDirectoryResult(false, "Directory '$old_name' not found");
    }
    
    // Check new name doesn't already exist
    if (is_dir($new_path) || file_exists($new_path)) {
        return buildDirectoryResult(false, "Directory '$new_name' already exists");
    }
    
    // Build command for admin to run if automatic rename fails
    $command = "cd " . escapeshellarg($organism_dir) . " && mv " . escapeshellarg($old_name) . " " . escapeshellarg($new_name);
    
    // Try to rename
    if (@rename($old_path, $new_path)) {
        return buildDirectoryResult(true, "Successfully renamed '$old_name' to '$new_name'", $command);
    } else {
        return buildDirectoryResult(false, 'Web server lacks permission to rename directory.', $command);
    }
}

/**
 * Rename a gene set directory within an assembly directory
 *
 * @param string $organism_dir - Path to organism directory
 * @param string $assembly - Assembly directory name
 * @param string $old_name - Current gene set directory name
 * @param string $new_name - Target gene set directory name
 * @return array - ['success' => bool, 'message' => string, 'command' => string]
 */
function renameGeneSetDirectory($organism_dir, $assembly, $old_name, $new_name) {
    if (!validateDirectoryName($assembly) || !validateDirectoryName($old_name) || !validateDirectoryName($new_name)) {
        return buildDirectoryResult(false, 'Invalid directory name (contains path separators)');
    }

    $assembly_path = "$organism_dir/$assembly";

    if (!is_dir($assembly_path)) {
        return buildDirectoryResult(false, "Assembly directory '$assembly' not found");
    }

    $old_path = "$assembly_path/$old_name";
    $new_path = "$assembly_path/$new_name";

    if (!is_dir($old_path)) {
        return buildDirectoryResult(false, "Gene set directory '$old_name' not found in '$assembly'");
    }

    if (is_dir($new_path) || file_exists($new_path)) {
        return buildDirectoryResult(false, "Directory '$new_name' already exists in '$assembly'");
    }

    $command = "cd " . escapeshellarg($assembly_path) . " && mv " . escapeshellarg($old_name) . " " . escapeshellarg($new_name);

    if (@rename($old_path, $new_path)) {
        return buildDirectoryResult(true, "Successfully renamed '$old_name' to '$new_name'", $command);
    } else {
        return buildDirectoryResult(false, 'Web server lacks permission to rename directory.', $command);
    }
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
    if (!is_dir($organism_dir)) {
        return buildDirectoryResult(false, 'Organism directory not found');
    }
    
    // Validate directory name for security
    if (!validateDirectoryName($dir_name)) {
        return buildDirectoryResult(false, 'Invalid directory name (security check failed)');
    }
    
    $dir_path = "$organism_dir/$dir_name";
    
    // Validate directory exists
    if (!is_dir($dir_path)) {
        return buildDirectoryResult(false, "Directory '$dir_name' not found");
    }
    
    // Build command for admin to run if automatic delete fails
    $command = "rm -rf " . escapeshellarg($dir_path);
    
    // Try to delete recursively
    if (rrmdir($dir_path)) {
        return buildDirectoryResult(true, "Successfully deleted directory '$dir_name'", $command);
    } else {
        return buildDirectoryResult(false, 'Web server lacks permission to delete directory.', $command);
    }
}

/**
 * Recursively remove directory
 *
 * Helper function to delete a directory and all its contents
 *
 * Symlinks are unlinked, never followed. This matters: MOOP's derived trees are built
 * from symlinks into the real organism data (data/genomes/*​/reference.fasta →
 * organisms/.../genome.fa, and the JBrowse GFF links), and is_dir() follows symlinks.
 * Without the is_link() check first, removing a derived directory that happened to hold
 * a symlink to a DIRECTORY would recurse through it and delete the source data.
 *
 * @param string $dir - Directory path
 * @return bool - True if successful
 */
function rrmdir($dir) {
    if (!is_dir($dir) || is_link($dir)) {
        return false;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_link($path)) {
            // Remove the link itself — never traverse into its target.
            if (!@unlink($path)) {
                return false;
            }
        } elseif (is_dir($path)) {
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
    $current_group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($filepath))['name'] ?? 'unknown' : 'unknown';
    $perms_full = substr(sprintf('%o', fileperms($filepath)), -4);
    $current_perms = ltrim($perms_full, '0') ?: '0';
    
    // Command keeps original owner but changes group to webserver and sets perms to 664
    $fix_command = "sudo chgrp " . escapeshellarg($web_group) . " " . escapeshellarg($filepath) . " && sudo chmod 664 " . escapeshellarg($filepath);
    
    return [
        'owner' => $current_owner,
        'group' => $current_group,
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
    
    $moop_owner = getMoopOwner();
    
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
    $perms_full = substr(sprintf('%o', fileperms($dirpath)), -4);
    $current_perms = ltrim($perms_full, '0') ?: '0';
    
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

/**
 * Get the last update time from registry files
 * 
 * Attempts to extract "Generated:" timestamp from HTML file first,
 * then falls back to file modification time. Also checks if any PHP files
 * in the codebase are newer than the registry, indicating it needs updating.
 * 
 * @param string $htmlFile - Path to HTML registry file
 * @param string $mdFile - Path to Markdown registry file (fallback)
 * @param string $scanDirBase - Base path for scanning PHP files (defaults to parent of lib/)
 * @return array - Array with keys:
 *                 'timestamp' => Last update timestamp in format 'Y-m-d H:i:s' or 'Never'
 *                 'isStale' => Boolean indicating if registry needs updating
 *                 'status' => String message ('Up to date' or 'You should update')
 */
function getRegistryLastUpdate($htmlFile, $mdFile, $scanDirBase = null) {
    $lastUpdate = 'Never';
    $lastUpdateTime = 0;
    $isStale = false;
    
    // Try to get from HTML file first (has "Generated:" timestamp)
    if (file_exists($htmlFile) && is_readable($htmlFile)) {
        $content = file_get_contents($htmlFile);
        // Look for "Generated: YYYY-MM-DD HH:MM:SS" in the HTML
        if (preg_match('/Generated:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $content, $matches)) {
            $lastUpdate = $matches[1];
            $lastUpdateTime = strtotime($lastUpdate);
        }
    }
    
    // Fallback to file modification time
    if ($lastUpdateTime === 0) {
        if (file_exists($htmlFile)) {
            $lastUpdateTime = filemtime($htmlFile);
            $lastUpdate = date('Y-m-d H:i:s', $lastUpdateTime);
        } elseif (file_exists($mdFile)) {
            $lastUpdateTime = filemtime($mdFile);
            $lastUpdate = date('Y-m-d H:i:s', $lastUpdateTime);
        }
    }
    
    // Check if any PHP files are newer than the registry (only if registry exists)
    if ($lastUpdateTime > 0) {
        if ($scanDirBase === null) {
            // Default to parent directory of lib/
            $scanDirBase = dirname(dirname(__FILE__));
        }
        
        $scanDirs = [
            $scanDirBase . '/lib',
            $scanDirBase . '/tools',
            $scanDirBase . '/admin',
            $scanDirBase
        ];
        
        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) continue;
            
            try {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($files as $file) {
                    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                    
                    $filePath = $file->getRealPath();
                    
                    // Skip excluded files and directories
                    $excludePatterns = ['docs/', 'logs/', 'notes/', 'not_used/', '.git/', 'generate_registry'];
                    $skip = false;
                    foreach ($excludePatterns as $pattern) {
                        if (strpos($filePath, $pattern) !== false) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;
                    
                    $fileTime = filemtime($filePath);
                    if ($fileTime > $lastUpdateTime) {
                        $isStale = true;
                        break 2;
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking PHP file timestamps: " . $e->getMessage());
            }
        }
    }
    
    return [
        'timestamp' => $lastUpdate,
        'isStale' => $isStale,
        'status' => $isStale ? 'You should update' : 'Up to date'
    ];
}

/**
 * Get the newest SQLite database modification timestamp
 * 
 * Scans all SQLite files in organism subdirectories and returns the most recent modification time
 * Each organism has a structure: organisms/OrganismName/organism.sqlite
 * 
 * @param string $organisms_path - Path to organisms directory
 * @return array - Array with 'timestamp' (Y-m-d H:i:s), 'unix_time' (timestamp), and 'iso8601'
 *                 Returns null if no SQLite files found
 */
function getNewestSqliteModTime($organisms_path) {
    if (!is_dir($organisms_path)) {
        return null;
    }
    
    $newest_time = 0;
    $found = false;
    
    try {
        // Scan organism directories one level deep
        $organisms = scandir($organisms_path);
        foreach ($organisms as $organism) {
            if ($organism === '.' || $organism === '..') {
                continue;
            }
            
            $organism_dir = $organisms_path . '/' . $organism;
            if (!is_dir($organism_dir)) {
                continue;
            }
            
            $sqlite_file = $organism_dir . '/organism.sqlite';
            if (file_exists($sqlite_file)) {
                $mtime = filemtime($sqlite_file);
                if ($mtime > $newest_time) {
                    $newest_time = $mtime;
                    $found = true;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error scanning SQLite files: " . $e->getMessage());
        return null;
    }
    
    if (!$found) {
        return null;
    }
    
    return [
        'unix_time' => $newest_time,
        'timestamp' => date('Y-m-d H:i:s', $newest_time),
        'iso8601' => date('c', $newest_time)
    ];
}
