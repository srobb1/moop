<?php
/**
 * MOOP Data Functions
 * Data retrieval, group management, and assembly information
 */

/**
 * Get group metadata from organism_assembly_groups.json
 * 
 * @return array Array of organism/assembly/groups data
 */
function getGroupData() {
    $config = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    $groups_data = [];
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true);
    }
    return $groups_data;
}

/**
 * Get all group cards from metadata
 * Returns card objects for every group in the system
 * 
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Associative array of group_name => card_info
 */
function getAllGroupCards($group_data) {
    $cards = [];
    foreach ($group_data as $data) {
        foreach ($data['groups'] as $group) {
            if (!isset($cards[$group])) {
                $cards[$group] = [
                    'title' => $group,
                    'text' => "Explore $group Data",
                    'link' => 'tools/groups.php?group=' . urlencode($group)
                ];
            }
        }
    }
    return $cards;
}

/**
 * Get group cards that have at least one public assembly
 * Returns card objects only for groups containing assemblies in the "Public" group
 * 
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Associative array of group_name => card_info for public groups only
 */
function getPublicGroupCards($group_data) {
    $public_groups = [];
    
    // Find all groups that contain at least one public assembly
    foreach ($group_data as $data) {
        if (in_array('Public', $data['groups'])) {
            foreach ($data['groups'] as $group) {
                if (!isset($public_groups[$group])) {
                    $public_groups[$group] = [
                        'title' => $group,
                        'text' => "Explore $group Data",
                        'link' => 'tools/groups.php?group=' . urlencode($group)
                    ];
                }
            }
        }
    }
    return $public_groups;
}

/**
 * Filter organisms in a group to only those with at least one accessible assembly
 * Respects user permissions for assembly access
 * 
 * @param string $group_name The group name to filter
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Filtered array of organism => [accessible_assemblies]
 */
function getAccessibleOrganismsInGroup($group_name, $group_data) {
    $group_organisms = [];
    
    // Find all organisms/assemblies in this group
    foreach ($group_data as $data) {
        if (in_array($group_name, $data['groups'])) {
            $organism = $data['organism'];
            $assembly = $data['assembly'];
            
            if (!isset($group_organisms[$organism])) {
                $group_organisms[$organism] = [];
            }
            $group_organisms[$organism][] = $assembly;
        }
    }
    
    // Filter: only keep organisms with at least one accessible assembly
    $accessible_organisms = [];
    foreach ($group_organisms as $organism => $assemblies) {
        $has_accessible_assembly = false;
        
        foreach ($assemblies as $assembly) {
            // Check if user has access to this specific assembly
            if (has_assembly_access($organism, $assembly)) {
                $has_accessible_assembly = true;
                break;
            }
        }
        
        if ($has_accessible_assembly) {
            $accessible_organisms[$organism] = $assemblies;
        }
    }
    
    // Sort organisms alphabetically
    ksort($accessible_organisms);
    
    return $accessible_organisms;
}

/**
 * Get FASTA files for an assembly
 * 
 * Scans the assembly directory for FASTA files matching configured sequence types.
 * Uses patterns from $sequence_types global to identify file types (genome, protein, transcript, cds).
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_name The assembly name (accession)
 * @return array Associative array of type => ['path' => relative_path, 'label' => label]
 */
function getAssemblyFastaFiles($organism_name, $assembly_name) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    $sequence_types = $config->getSequenceTypes();
    $fasta_files = [];
    $assembly_dir = "$organism_data/$organism_name/$assembly_name";
    
    if (is_dir($assembly_dir)) {
        $fasta_files_found = glob($assembly_dir . '/*.fa');
        foreach ($fasta_files_found as $fasta_file) {
            $filename = basename($fasta_file);
            $relative_path = "$organism_name/$assembly_name/$filename";
            
            foreach ($sequence_types as $type => $config) {
                if (strpos($filename, $config['pattern']) !== false) {
                    $fasta_files[$type] = [
                        'path' => $relative_path,
                        'label' => $config['label'],
                        'color' => $config['color']
                    ];
                    break;
                }
            }
        }
    }
    return $fasta_files;
}

/**
 * Get cards to display on index page based on user access level
 * 
 * @param array $group_data Array of group data from getGroupData()
 * @return array Cards to display with title, text, and link
 */
function getIndexDisplayCards($group_data) {
    $cards_to_display = [];
    $all_cards = getAllGroupCards($group_data);
    
    if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {
        $cards_to_display = $all_cards;
    } elseif (is_logged_in()) {
        // Logged-in users see: public groups + their permitted organisms
        $cards_to_display = getPublicGroupCards($group_data);
        
        foreach (get_user_access() as $organism => $assemblies) {
            if (!isset($cards_to_display[$organism])) {
                $formatted_name = formatIndexOrganismName($organism);
                $cards_to_display[$organism] = [
                    'title' => $formatted_name,
                    'text'  => "Explore " . strip_tags($formatted_name) . " Data",
                    'link'  => 'tools/organism.php?organism=' . urlencode($organism)
                ];
            }
        }
    } else {
        // Visitors see only groups with public assemblies
        $cards_to_display = getPublicGroupCards($group_data);
    }
    
    return $cards_to_display;
}

/**
 * Format organism name for index page display with italics
 * 
 * @param string $organism Organism name with underscores
 * @return string Formatted name with proper capitalization and italics
 */
function formatIndexOrganismName($organism) {
    $parts = explode('_', $organism);
    $formatted_name = ucfirst(strtolower($parts[0]));
    for ($i = 1; $i < count($parts); $i++) {
        $formatted_name .= ' ' . strtolower($parts[$i]);
    }
    return '<i>' . $formatted_name . '</i>';
}

/**
 * Load all organisms' JSON metadata from organism_data directory
 * Central function used by manage_organisms.php and manage_taxonomy_tree.php
 * 
 * @param string $organism_data_dir Path to organism data directory
 * @return array Associative array of organism_name => metadata
 */
function loadAllOrganismsMetadata($organism_data_dir) {
    $organisms = [];
    
    if (!is_dir($organism_data_dir)) {
        return $organisms;
    }
    
    $entries = scandir($organism_data_dir);
    foreach ($entries as $organism) {
        // Skip hidden directories and non-directories
        if ($organism[0] === '.' || !is_dir("$organism_data_dir/$organism")) {
            continue;
        }
        
        // Load organism.json using loadJsonFile (from functions_json.php)
        $organism_json_path = "$organism_data_dir/$organism/organism.json";
        $organism_info = loadJsonFile($organism_json_path);
        
        if (!$organism_info) {
            continue;
        }
        
        // Handle improperly wrapped JSON (extra outer braces)
        if (!isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
            $keys = array_keys($organism_info);
            if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
                $organism_info = $organism_info[$keys[0]];
            }
        }
        
        // Store organism metadata keyed by organism name
        $organisms[$organism] = [
            'genus' => $organism_info['genus'] ?? '',
            'species' => $organism_info['species'] ?? '',
            'common_name' => $organism_info['common_name'] ?? '',
            'taxon_id' => $organism_info['taxon_id'] ?? '',
            'images' => $organism_info['images'] ?? [],
            'html_p' => $organism_info['html_p'] ?? []
        ];
    }
    
    return $organisms;
}

/**
 * Get all organisms with their assemblies from filesystem
 * 
 * Scans the organism data directory and returns a map of organisms to their assemblies.
 * Used for user permission management and group configuration.
 * Note: Database may have different/cached info - use this for filesystem truth.
 * 
 * @param string $organism_data_path Path to organism data directory
 * @return array Associative array of organism_name => array of assembly names
 */
function getOrganismsWithAssemblies($organism_data_path) {
    $orgs = [];
    
    if (!is_dir($organism_data_path)) {
        return $orgs;
    }
    
    $organisms = scandir($organism_data_path);
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data_path/$organism")) {
            continue;
        }
        
        $assemblies = [];
        $assemblyPath = "$organism_data_path/$organism";
        $files = scandir($assemblyPath);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$assemblyPath/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        $orgs[$organism] = $assemblies;
    }
    return $orgs;
}

/**
 * Get all existing groups from group data
 * 
 * Extracts unique group names from organism_assembly_groups.json data
 * and returns a sorted list
 * 
 * @param array $groups_data Array of organism/assembly/groups data
 * @return array Sorted list of unique group names
 */
function getAllExistingGroups($groups_data) {
    $all_groups = [];
    foreach ($groups_data as $data) {
        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $group) {
                $all_groups[$group] = true;
            }
        }
    }
    $group_list = array_keys($all_groups);
    sort($group_list);
    return $group_list;
}

/**
 * Sync group descriptions with existing groups
 * 
 * Marks groups as in_use=true, marks unused groups as in_use=false,
 * and creates default structure for new groups
 * 
 * @param array $existing_groups List of group names that exist
 * @param array $descriptions_data Current group descriptions
 * @return array Updated descriptions with synced in_use status
 */
function syncGroupDescriptions($existing_groups, $descriptions_data) {
    $desc_map = [];
    foreach ($descriptions_data as $desc) {
        $desc_map[$desc['group_name']] = $desc;
    }
    
    $updated_descriptions = [];
    
    // Update existing groups to in_use = true
    foreach ($existing_groups as $group) {
        if (isset($desc_map[$group])) {
            $desc_map[$group]['in_use'] = true;
            $updated_descriptions[] = $desc_map[$group];
        } else {
            // New group - add with default structure
            $updated_descriptions[] = [
                'group_name' => $group,
                'images' => [
                    [
                        'file' => '',
                        'caption' => ''
                    ]
                ],
                'html_p' => [
                    [
                        'text' => '',
                        'style' => '',
                        'class' => ''
                    ]
                ],
                'in_use' => true
            ];
        }
        unset($desc_map[$group]);
    }
    
    // Mark any remaining groups (not in existing groups) as in_use = false
    foreach ($desc_map as $group_name => $desc) {
        $desc['in_use'] = false;
        $updated_descriptions[] = $desc;
    }
    
    return $updated_descriptions;
}

/**
 * Fetch taxonomic lineage from NCBI using XML parsing
 * 
 * Retrieves the full taxonomic classification for an organism using NCBI's API
 * and returns it as an array of rank => name pairs
 * 
 * @param int $taxon_id NCBI Taxonomy ID
 * @return array|null Array of ['rank' => x, 'name' => y] entries, or null if failed
 */
function fetch_taxonomy_lineage($taxon_id) {
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=taxonomy&id={$taxon_id}&retmode=xml";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'MOOP Taxonomy Tree Generator'
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    // Parse XML using regex since SimpleXML isn't always available
    $lineage = [];
    
    // Extract Lineage text (semicolon-separated)
    if (preg_match('/<Lineage>(.+?)<\/Lineage>/s', $response, $matches)) {
        $lineage_text = trim($matches[1]);
        $lineage_parts = array_filter(array_map('trim', explode(';', $lineage_text)));
        
        // Extract ranks from LineageEx
        $rank_map = [];
        if (preg_match_all('/<Taxon>.*?<ScientificName>(.+?)<\/ScientificName>.*?<Rank>(.+?)<\/Rank>.*?<\/Taxon>/s', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sci_name = trim($match[1]);
                $rank = trim($match[2]);
                $rank_map[$sci_name] = $rank;
            }
        }
        
        // Build lineage array with matched ranks
        $valid_ranks = ['superkingdom', 'kingdom', 'phylum', 'class', 'order', 'family', 'genus'];
        foreach ($lineage_parts as $name) {
            $rank = $rank_map[$name] ?? null;
            
            // Map domain to superkingdom
            if ($rank === 'domain') {
                $rank = 'superkingdom';
            }
            
            // Only include standard taxonomic ranks (skip intermediate ranks like 'clade')
            if ($rank && in_array($rank, $valid_ranks)) {
                $lineage[] = [
                    'rank' => $rank,
                    'name' => $name
                ];
            }
        }
    }
    
    // Add the species itself
    if (preg_match('/<ScientificName>(.+?)<\/ScientificName>/', $response, $matches)) {
        $sci_name = trim($matches[1]);
        // Only add if it's not already in lineage
        if (empty($lineage) || $lineage[count($lineage)-1]['name'] !== $sci_name) {
            $lineage[] = [
                'rank' => 'species',
                'name' => $sci_name
            ];
        }
    }
    
    return !empty($lineage) ? $lineage : null;
}

/**
 * Build taxonomy tree from organisms
 * 
 * Creates a hierarchical tree structure from a list of organisms by fetching
 * their taxonomic lineage from NCBI and organizing by taxonomic ranks
 * 
 * @param array $organisms Array of organism_name => ['taxon_id' => x, 'common_name' => y, ...]
 * @return array Tree structure: ['tree' => [...]]
 */
function build_tree_from_organisms($organisms) {
    $all_lineages = [];
    
    foreach ($organisms as $organism_name => $data) {
        if (empty($data['taxon_id'])) {
            continue;
        }
        
        $lineage = fetch_taxonomy_lineage($data['taxon_id']);
        $image = fetch_organism_image($data['taxon_id'], $organism_name);
        if ($lineage) {
            $all_lineages[$organism_name] = [
                'lineage' => $lineage,
                'common_name' => $data['common_name'],
                'image' => $image
            ];
        }
        
        // Be nice to NCBI - rate limit
        usleep(350000); // 350ms = ~3 requests per second
    }
    
    // Build tree structure
    $tree = ['name' => 'Life', 'children' => []];
    
    foreach ($all_lineages as $organism_name => $info) {
        $current = &$tree;
        
        foreach ($info['lineage'] as $level) {
            $name = $level['name'];
            $rank = $level['rank'];
            
            // Find or create child node
            $found = false;
            foreach ($current['children'] as &$child) {
                if ($child['name'] === $name) {
                    $current = &$child;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $new_node = ['name' => $name];
                
                // If this is the species level, add organism info
                if ($rank === 'species') {
                    $new_node['organism'] = $organism_name;
                    $new_node['common_name'] = $info['common_name'];
                    if ($info['image']) {
                        $new_node['image'] = $info['image'];
                    }
                } else {
                    $new_node['children'] = [];
                }
                
                $current['children'][] = $new_node;
                $current = &$current['children'][count($current['children']) - 1];
            }
        }
    }
    
    return ['tree' => $tree];
}

/**
 * Get detailed information about all organisms
 * 
 * Aggregates organism metadata, assemblies, database info, and validation results
 * for all organisms in the system. Used for admin management and reporting.
 * 
 * @param string $organism_data_path Path to organism data directory
 * @param array $sequence_types List of valid sequence types (e.g., ['cds', 'protein', 'genome'])
 * @return array Associative array of organism_name => array with metadata, assemblies, validations
 */
function getDetailedOrganismsInfo($organism_data_path, $sequence_types = []) {
    $organisms_info = [];
    
    if (!is_dir($organism_data_path)) {
        return $organisms_info;
    }
    
    // Load all organisms' JSON metadata using consolidated function
    $organisms_metadata = loadAllOrganismsMetadata($organism_data_path);
    
    $organisms = scandir($organism_data_path);
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data_path/$organism")) {
            continue;
        }
        
        // Get organism.json info (already loaded from consolidated function)
        $organism_json = "$organism_data_path/$organism/organism.json";
        $json_validation = validateOrganismJson($organism_json);
        $info = $organisms_metadata[$organism] ?? [];
        
        // Get assemblies
        $assemblies = [];
        $assembly_path = "$organism_data_path/$organism";
        $files = scandir($assembly_path);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$assembly_path/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        
        // Check for database file
        $db_file = null;
        if (file_exists("$organism_data_path/$organism/organism.sqlite")) {
            $db_file = "$organism_data_path/$organism/organism.sqlite";
        }
        
        $has_db = !is_null($db_file);
        
        // Validate database integrity if database exists
        $db_validation = null;
        $assembly_validation = null;
        $fasta_validation = null;
        if ($has_db) {
            $db_validation = validateDatabaseIntegrity($db_file);
            // Also validate assembly directories
            $assembly_validation = validateAssemblyDirectories($db_file, "$organism_data_path/$organism");
        }
        // Validate FASTA files in assembly directories
        $fasta_validation = validateAssemblyFastaFiles("$organism_data_path/$organism", $sequence_types);
        
        $organisms_info[$organism] = [
            'info' => $info,
            'assemblies' => $assemblies,
            'has_db' => $has_db,
            'db_file' => $db_file,
            'db_validation' => $db_validation,
            'assembly_validation' => $assembly_validation,
            'fasta_validation' => $fasta_validation,
            'json_validation' => $json_validation,
            'path' => "$organism_data_path/$organism"
        ];
    }
    
    return $organisms_info;
}

/**
 * Check if an assembly is in any groups
 * @param string $organism Organism name
 * @param string $assembly Assembly name
 * @param array $groups_data Groups data array
 * @return array Array of group names containing this assembly, empty if none
 */
function getAssemblyGroups($organism, $assembly, $groups_data) {
    $assembly_groups = [];
    
    foreach ($groups_data as $data) {
        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $group) {
                // Check if this organism and assembly match
                if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                    $assembly_groups[] = $group;
                }
            }
        }
    }
    
    return $assembly_groups;
}

/**
 * Check if an organism is in the taxonomy tree
 * @param string $organism Organism name
 * @param string $assembly Assembly name (optional, can be empty)
 * @param string $tree_file Path to taxonomy_tree_config.json file
 * @return bool True if organism is in tree, false otherwise
 */
function isAssemblyInTaxonomyTree($organism, $assembly, $tree_file) {
    if (!file_exists($tree_file)) {
        return false;
    }
    
    $tree_content = file_get_contents($tree_file);
    if (!$tree_content) {
        return false;
    }
    
    // Simply search for the organism name in the tree file
    return strpos($tree_content, '"organism": "' . $organism . '"') !== false;
}


/**
 * Get comprehensive status of an organism across all checks
 * @param string $organism Organism name
 * @param array $data Organism data from getDetailedOrganismsInfo
 * @param array $groups_data Groups data
 * @param string $taxonomy_tree_file Path to taxonomy_tree_config.json
 * @param array $sequence_types Configured sequence types
 * @return array Status with checks and overall status
 */
function getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types) {
    $checks = [
        'has_assemblies' => false,
        'has_fasta' => false,
        'has_blast_indexes' => false,
        'has_database' => false,
        'database_readable' => false,
        'assemblies_in_groups' => false,
        'in_taxonomy_tree' => false
    ];
    
    // 1. Does it have at least one assembly?
    $checks['has_assemblies'] = !empty($data['assemblies']);
    
    // 2. Is there at least one FASTA file in an assembly?
    if ($checks['has_assemblies'] && !empty($data['fasta_validation']['assemblies'])) {
        foreach ($data['fasta_validation']['assemblies'] as $asm_fasta) {
            if (!empty($asm_fasta['fasta_files'])) {
                foreach ($asm_fasta['fasta_files'] as $file_info) {
                    if ($file_info['found']) {
                        $checks['has_fasta'] = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    // 3. Is there a BLAST index file for existing FASTA files?
    if ($checks['has_fasta']) {
        foreach ($data['assemblies'] as $assembly) {
            $assembly_path = $data['path'] . '/' . $assembly;
            $blast_validation = validateBlastIndexFiles($assembly_path, $sequence_types);
            if (!empty($blast_validation['databases'])) {
                foreach ($blast_validation['databases'] as $db) {
                    if ($db['has_indexes']) {
                        $checks['has_blast_indexes'] = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    // 4. Is there a database file?
    $checks['has_database'] = $data['has_db'];
    
    // 5. Is the database readable?
    if ($checks['has_database'] && !empty($data['db_validation'])) {
        $checks['database_readable'] = $data['db_validation']['readable'];
    }
    
    // 6. Is each assembly a member of at least one group?
    if ($checks['has_assemblies']) {
        $all_in_groups = true;
        foreach ($data['assemblies'] as $assembly) {
            $assembly_groups = getAssemblyGroups($organism, $assembly, $groups_data);
            if (empty($assembly_groups)) {
                $all_in_groups = false;
                break;
            }
        }
        $checks['assemblies_in_groups'] = $all_in_groups;
    }
    
    // 7. Is the organism found in the tree?
    $checks['in_taxonomy_tree'] = isAssemblyInTaxonomyTree($organism, '', $taxonomy_tree_file);
    
    // 8. Is metadata complete?
    if (!empty($data['json_validation'])) {
        $json_val = $data['json_validation'];
        $checks['metadata_complete'] = ($json_val['exists'] && $json_val['readable'] && $json_val['valid_json'] && $json_val['has_required_fields'] && $json_val['writable']);
    }
    
    // Calculate overall status
    $all_pass = array_reduce($checks, function($carry, $item) {
        return $carry && $item;
    }, true);
    
    $pass_count = count(array_filter($checks));
    $total_count = count($checks);
    
    return [
        'checks' => $checks,
        'all_pass' => $all_pass,
        'pass_count' => $pass_count,
        'total_count' => $total_count
    ];
}
