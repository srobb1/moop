<?php
/**
 * MOOP Access Control Functions
 * User access checking, assembly accessibility, and permission-based filtering
 */

/**
 * Get assembly information from database
 * Queries the organism.sqlite database to get genome_id and genome_name for an assembly
 * 
 * @param string $assembly - Assembly accession
 * @param string $db_path - Path to organism.sqlite database
 * @return array - [genome_id, genome_name, genome_accession] or [null, null, $assembly] on error
 */
function getAssemblyInfo($assembly, $db_path) {
    $genome_id = null;
    $genome_name = null;
    $genome_accession = $assembly;
    
    if (!file_exists($db_path)) {
        return [$genome_id, $genome_name, $genome_accession];
    }
    
    try {
        $query = "SELECT genome_id, genome_name, genome_accession FROM genome WHERE genome_accession = ? OR genome_name = ?";
        $results = fetchData($query, $db_path, [$assembly, $assembly]);
        if (!empty($results)) {
            $genome_id = $results[0]['genome_id'];
            $genome_name = $results[0]['genome_name'];
            $genome_accession = $results[0]['genome_accession'];
        }
    } catch (Exception $e) {
        // If query fails, return defaults
    }
    
    return [$genome_id, $genome_name, $genome_accession];
}

/**
 * Get assemblies accessible to current user
 * Filters assemblies based on user access level and group membership
 * 
 * @param string $specific_organism Optional organism to filter by
 * @param string $specific_assembly Optional assembly to filter by
 * @return array Organized by group -> organism, or assemblies for specific organism/assembly
 */
function getAccessibleAssemblies($specific_organism = null, $specific_assembly = null) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    $metadata_path = $config->getPath('metadata_path');
    
    // Load groups data
    $groups_data = [];
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true) ?: [];
    }
    
    $accessible_sources = [];
    
    // Filter entries based on referrer (specific org/assembly or all)
    $entries_to_process = $groups_data;
    
    if (!empty($specific_organism)) {
        $entries_to_process = array_filter($groups_data, function($entry) use ($specific_organism) {
            return $entry['organism'] === $specific_organism;
        });
    }
    
    if (!empty($specific_assembly)) {
        $entries_to_process = array_filter($entries_to_process, function($entry) use ($specific_assembly) {
            return $entry['assembly'] === $specific_assembly;
        });
    }
    
    // Build list of accessible sources using assembly-based permissions
    foreach ($entries_to_process as $entry) {
        $org = $entry['organism'];
        $assembly = $entry['assembly'];
        $entry_groups = $entry['groups'] ?? [];
        
        // Check if user has access to this specific assembly
        // 1. ALL/Admin users have access to everything
        // 2. Public assemblies are accessible to everyone
        // 3. Collaborators can access assemblies in their $_SESSION['access'] list
        $access_granted = false;
        
        if (has_access('ALL')) {
            $access_granted = true;
        } elseif (is_public_assembly($org, $assembly)) {
            $access_granted = true;
        } elseif (has_access('Collaborator')) {
            // Check if user has access to this specific assembly
            $user_access = get_user_access();
            if (isset($user_access[$org]) && is_array($user_access[$org]) && in_array($assembly, $user_access[$org])) {
                $access_granted = true;
            }
        }
        
        if ($access_granted) {
            $assembly_path = "$organism_data/$org/$assembly";
            
            // Only include assembly if directory exists AND has FASTA files
            if (is_dir($assembly_path)) {
                // Check if assembly has any FASTA files (protein, transcript, cds, or genome)
                $has_fasta = false;
                foreach (['.fa', '.fasta', '.faa', '.nt.fa', '.aa.fa'] as $ext) {
                    if (glob("$assembly_path/*$ext")) {
                        $has_fasta = true;
                        break;
                    }
                }
                
                if ($has_fasta) {
                    $db_path = "$organism_data/$org/organism.sqlite";
                    
                    // Use validateAssemblyDirectories to get the actual directory mapping
                    $assembly_validation = validateAssemblyDirectories($db_path, "$organism_data/$org");
                    
                    $genome_id = null;
                    $genome_name = null;
                    $actual_assembly_dir = $assembly;  // Default to the directory name as-is
                    
                    // Find matching genome in validation results
                    if ($assembly_validation && !empty($assembly_validation['genomes'])) {
                        foreach ($assembly_validation['genomes'] as $genome) {
                            // Match by either genome_name or genome_accession
                            if ($genome['genome_name'] === $assembly || $genome['genome_accession'] === $assembly) {
                                $genome_id = $genome['genome_id'];
                                $genome_name = $genome['genome_name'];
                                $actual_assembly_dir = $genome['directory_found'] ?? $assembly;
                                break;
                            }
                        }
                    }
                    
                    $accessible_sources[] = [
                        'organism' => $org,
                        'assembly' => $actual_assembly_dir,  // Use the actual directory name
                        'genome_name' => $genome_name,
                        'path' => "$organism_data/$org/$actual_assembly_dir",
                        'groups' => $entry_groups,
                        'genome_id' => $genome_id
                    ];
                }
            }
        }
    }
    
    // Organize by group -> organism
    $organized = [];
    foreach ($accessible_sources as $source) {
        foreach ($source['groups'] as $group) {
            if (!isset($organized[$group])) {
                $organized[$group] = [];
            }
            $org = $source['organism'];
            if (!isset($organized[$group][$org])) {
                $organized[$group][$org] = [];
            }
            $organized[$group][$org][] = $source;
        }
    }
    
    // Sort groups (Public first, then alphabetically)
    uksort($organized, function($a, $b) {
        if ($a === 'Public') return -1;
        if ($b === 'Public') return 1;
        return strcasecmp($a, $b);
    });
    
    // Sort organisms within each group alphabetically
    foreach ($organized as &$group_data) {
        ksort($group_data);
    }
    
    return $organized;
}

/**
 * Get phylogenetic tree user access for display
 * Returns organisms accessible to current user for phylo tree display
 * 
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Array of accessible organisms with true value
 */
function getPhyloTreeUserAccess($group_data) {
    $phylo_user_access = [];
    
    if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {
        // Admin gets access to all organisms
        foreach ($group_data as $data) {
            $organism = $data['organism'];
            if (!isset($phylo_user_access[$organism])) {
                $phylo_user_access[$organism] = true;
            }
        }
    } elseif (is_logged_in()) {
        // Logged-in users get their specific access
        $phylo_user_access = get_user_access();
    } else {
        // Public users: get organisms in Public group
        foreach ($group_data as $data) {
            if (in_array('Public', $data['groups'])) {
                $organism = $data['organism'];
                if (!isset($phylo_user_access[$organism])) {
                    $phylo_user_access[$organism] = true;
                }
            }
        }
    }
    
    return $phylo_user_access;
}

/**
 * Require user to have specific access level or redirect to access denied
 * 
 * @param string $level Required access level (e.g., 'Collaborator', 'Admin')
 * @param string $resource Resource name (e.g., group name or organism name)
 * @param array $options Options array with keys:
 *   - redirect_on_deny (bool, default: true) - Redirect to deny page if no access
 *   - deny_page (string, default: /$site/access_denied.php) - URL to redirect to
 * @return bool True if user has access, false otherwise
 */
function requireAccess($level, $resource, $options = []) {
    global $site;
    
    $redirect_on_deny = $options['redirect_on_deny'] ?? true;
    $deny_page = $options['deny_page'] ?? "/$site/access_denied.php";
    
    $has_access = has_access($level, $resource);
    
    if (!$has_access && $redirect_on_deny) {
        header("Location: $deny_page");
        exit;
    }
    
    return $has_access;
}
