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
 * Resolve source selection from organism and assembly parameters
 * Handles both genome_name and genome_accession formats
 * Returns the correct selected_source string for pre-selecting radio buttons
 * 
 * @param string $organism Organism name
 * @param string $assembly Assembly (could be genome_name or genome_accession)
 * @param array $accessible_sources Flattened list of sources with genome_id, genome_name, etc.
 * @return string Selected source in format "organism|assembly_dir" or empty string if not found
 */
function resolveSourceSelection($organism, $assembly, $accessible_sources) {
    // Try direct matches first: assembly dir, genome_name, or genome_id
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $organism) {
            if ($source['assembly'] === $assembly || 
                $source['genome_name'] === $assembly ||
                $source['genome_id'] === $assembly) {
                return $organism . '|' . $source['assembly'];
            }
        }
    }
    
    // If not found by direct match, try via database lookup
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    
    try {
        $db_path = "$organism_data/$organism/organism.sqlite";
        [$genome_id_param, $genome_name_param, $genome_accession_param] = getAssemblyInfo($assembly, $db_path);
        
        // Try matching by resolved genome_name
        if (!empty($genome_name_param)) {
            foreach ($accessible_sources as $source) {
                if ($source['organism'] === $organism && $source['genome_name'] === $genome_name_param) {
                    return $organism . '|' . $source['assembly'];
                }
            }
        }
    } catch (Exception $e) {
        // If lookup fails, return empty
    }
    
    return '';
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
        
        if (has_access('ADMIN') || has_access('IP_IN_RANGE')) {
            $access_granted = true;
        } elseif (is_public_assembly($org, $assembly)) {
            $access_granted = true;
        } elseif (has_access('COLLABORATOR')) {
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
                    $genome_accession = null;
                    $actual_assembly_dir = $assembly;  // Default to the directory name as-is
                    
                    // Find matching genome in validation results
                    if ($assembly_validation && !empty($assembly_validation['genomes'])) {
                        foreach ($assembly_validation['genomes'] as $genome) {
                            // Match by either genome_name or genome_accession
                            if ($genome['genome_name'] === $assembly || $genome['genome_accession'] === $assembly) {
                                $genome_id = $genome['genome_id'];
                                $genome_name = $genome['genome_name'];
                                $genome_accession = $genome['genome_accession'];
                                $actual_assembly_dir = $genome['directory_found'] ?? $assembly;
                                break;
                            }
                        }
                    }
                    
                    $accessible_sources[] = [
                        'organism' => $org,
                        'assembly' => $actual_assembly_dir,  // Use the actual directory name
                        'genome_name' => $genome_name,
                        'genome_accession' => $genome_accession,
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
    
    // Sort groups (PUBLIC first, then alphabetically)
    uksort($organized, function($a, $b) {
        if ($a === 'PUBLIC') return -1;
        if ($b === 'PUBLIC') return 1;
        return strcasecmp($a, $b);
    });
    
    // Sort organisms within each group alphabetically
    foreach ($organized as &$group_data) {
        ksort($group_data);
    }
    
    return $organized;
}

/**
 * Get taxonomy tree user access for display
 * Returns organisms accessible to current user for taxonomy tree display
 * 
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Array of accessible organisms with true value
 */
function getTaxonomyTreeUserAccess($group_data) {
    $taxonomy_user_access = [];
    
    if (get_access_level() === 'ADMIN' || get_access_level() === 'IP_IN_RANGE') {
        // Admin gets access to all organisms
        foreach ($group_data as $data) {
            $organism = $data['organism'];
            if (!isset($taxonomy_user_access[$organism])) {
                $taxonomy_user_access[$organism] = true;
            }
        }
    } elseif (is_logged_in()) {
        // Logged-in users get their specific access
        $taxonomy_user_access = get_user_access();
    } else {
        // Public users: get organisms in PUBLIC group
        foreach ($group_data as $data) {
            if (in_array('PUBLIC', $data['groups'])) {
                $organism = $data['organism'];
                if (!isset($taxonomy_user_access[$organism])) {
                    $taxonomy_user_access[$organism] = true;
                }
            }
        }
    }
    
    return $taxonomy_user_access;
}

/**
 * Count organisms at a specific taxonomy rank that user has access to
 * Traverses the taxonomy tree and counts all organisms under the given rank
 * 
 * @param array $tree_node Tree node to search in
 * @param string $rank_name Name of the rank to count (e.g., 'Chordata', 'Mammalia')
 * @param array $user_access User access array (organism_name => true)
 * @return int Count of accessible organisms under this rank
 */
function countOrganismsAtTaxonomyRank($tree_node, $rank_name, $user_access) {
    if (empty($tree_node) || empty($rank_name) || empty($user_access)) {
        return 0;
    }
    
    $count = 0;
    
    $traverse = function($node) use ($rank_name, $user_access, &$count, &$traverse) {
        // Check if this is the node we're looking for
        if ($node['name'] === $rank_name) {
            // Count all organisms under this rank
            $countOrganisms = function($n) use (&$countOrganisms, $user_access, &$count) {
                if (isset($n['organism']) && isset($user_access[$n['organism']])) {
                    $count++;
                }
                if (isset($n['children']) && is_array($n['children'])) {
                    foreach ($n['children'] as $child) {
                        $countOrganisms($child);
                    }
                }
            };
            $countOrganisms($node);
            return;
        }
        
        // Continue traversing down
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $traverse($child);
            }
        }
    };
    
    $traverse($tree_node);
    return $count;
}

/**
 * Get taxonomy lineage with organism counts at each rank
 * 
 * @param array $lineage Array of lineage items with 'rank' and 'name' keys
 * @param array $tree_node Root of taxonomy tree
 * @param array $user_access User access array (organism_name => true)
 * @return array Lineage with added 'count' key for each item
 */
function getTaxonomyLineageWithCounts($lineage, $tree_node, $user_access) {
    if (empty($lineage) || empty($tree_node) || empty($user_access)) {
        return $lineage;
    }
    
    return array_map(function($item) use ($tree_node, $user_access) {
        $item['count'] = countOrganismsAtTaxonomyRank($tree_node, $item['name'], $user_access);
        return $item;
    }, $lineage);
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
