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
                    'link' => 'tools/groups_display.php?group=' . urlencode($group)
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
                        'link' => 'tools/groups_display.php?group=' . urlencode($group)
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
                        'label' => $config['label']
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
                    'link'  => 'tools/organism_display.php?organism=' . urlencode($organism)
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
 * Central function used by manage_organisms.php and manage_phylo_tree.php
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
            'taxon_id' => $organism_info['taxon_id'] ?? ''
        ];
    }
    
    return $organisms;
}
