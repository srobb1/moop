<?php
/**
 * MOOP Data Functions
 * Data retrieval, group management, and assembly information
 */

// getCachedOrganismsInfo() pre-computes BLAST-index validation per assembly via
// validateBlastIndexFiles(), which lives in blast_functions.php. Most callers
// (dashboard, organism checklist) already load that file, but leaner entry points
// — e.g. admin/api/archive_gene_set.php — do not, and the missing function turned
// a successful archive into an uncaught fatal. Require it here so every caller of
// this file's functions has the dependency, regardless of include order.
require_once __DIR__ . '/blast_functions.php';
require_once __DIR__ . '/wikipedia_functions.php';   // Wikipedia enrichment helpers (split out 2026-07-07)

/**
 * cURL GET with connect + total timeouts — avoids D-state hangs from file_get_contents.
 * Returns the response body string, or false on error.
 */
function moop_curl_get(string $url, int $connect_timeout = 5, int $total_timeout = 10) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $connect_timeout,
        CURLOPT_TIMEOUT        => $total_timeout,
        CURLOPT_USERAGENT      => 'MOOP/1.0 (github.com)',
    ]);
    $result = curl_exec($ch);
    $err    = curl_errno($ch);
    curl_close($ch);
    return ($result !== false && !$err) ? $result : false;
}

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
    $group_organisms = [];
    foreach ($group_data as $data) {
        foreach ($data['groups'] as $group) {
            if (!isset($cards[$group])) {
                $cards[$group] = [
                    'title' => $group,
                    'text' => "Explore $group Data",
                    'link' => 'tools/groups.php?group=' . urlencode($group)
                ];
                $group_organisms[$group] = [];
            }
            $group_organisms[$group][$data['organism']] = true;
        }
    }
    foreach ($cards as $group => &$card) {
        $card['organism_count'] = count($group_organisms[$group]);
    }
    ksort($cards);
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
    $group_organisms = [];

    // Find all groups that contain at least one public assembly
    foreach ($group_data as $data) {
        if (in_array('PUBLIC', $data['groups'])) {
            foreach ($data['groups'] as $group) {
                if (!isset($public_groups[$group])) {
                    $public_groups[$group] = [
                        'title' => $group,
                        'text' => "Explore $group Data",
                        'link' => 'tools/groups.php?group=' . urlencode($group)
                    ];
                    $group_organisms[$group] = [];
                }
                $group_organisms[$group][$data['organism']] = true;
            }
        }
    }
    foreach ($public_groups as $group => &$card) {
        $card['organism_count'] = count($group_organisms[$group]);
    }
    ksort($public_groups);
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
 * Get all groups that contain a specific organism
 * Returns group info including accessible organism count for each group
 * 
 * @param string $organism_name The organism to find groups for
 * @param array $group_data Array of organism/assembly/groups data (optional, will load if not provided)
 * @return array Array of [group_name => ['count' => num_accessible_organisms, 'link' => url]]
 */
function getGroupsForOrganism($organism_name, $group_data = null) {
    if ($group_data === null) {
        $group_data = getGroupData();
    }
    
    $organism_groups = [];
    
    // Find all groups containing this organism
    foreach ($group_data as $data) {
        if ($data['organism'] === $organism_name) {
            foreach ($data['groups'] as $group) {
                if (!isset($organism_groups[$group])) {
                    $organism_groups[$group] = [
                        'count' => 0,
                        'link' => 'tools/groups.php?group=' . urlencode($group)
                    ];
                }
            }
        }
    }
    
    // Count accessible organisms in each group
    foreach ($organism_groups as $group => &$info) {
        $accessible_organisms = getAccessibleOrganismsInGroup($group, $group_data);
        $info['count'] = count($accessible_organisms);
    }
    
    // Sort by group name
    ksort($organism_groups);
    
    return $organism_groups;
}

/**
 * Get organisms at a specific taxonomy rank that user has access to
 * Traverses the taxonomy tree to find all organisms under a given taxonomic rank
 * 
 * @param string $rank_name Name of the taxonomy rank (e.g., 'Primates', 'Mammalia')
 * @param array $tree_node Root of taxonomy tree
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Array of [organism_name => [assemblies]] filtered by user access
 */
function getOrganismsAtTaxonomyLevel($rank_name, $tree_node, $group_data) {
    if (empty($rank_name) || empty($tree_node) || empty($group_data)) {
        return [];
    }
    
    $organisms_at_level = [];
    
    // Find the rank in the tree and collect all organisms under it
    $findOrganisms = function($node) use ($rank_name, &$organisms_at_level, &$findOrganisms) {
        // Check if this is the target rank
        if ($node['name'] === $rank_name) {
            // Collect all organisms under this rank
            $collectOrganisms = function($n) use (&$collectOrganisms, &$organisms_at_level) {
                if (isset($n['organism'])) {
                    $organisms_at_level[$n['organism']] = true;
                }
                if (isset($n['children']) && is_array($n['children'])) {
                    foreach ($n['children'] as $child) {
                        $collectOrganisms($child);
                    }
                }
            };
            $collectOrganisms($node);
            return;
        }
        
        // Continue traversing
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $findOrganisms($child);
            }
        }
    };
    
    $findOrganisms($tree_node);
    
    // Now filter by user access and get assemblies
    $accessible_organisms = [];
    foreach ($organisms_at_level as $organism => $dummy) {
        // Find all assemblies for this organism
        $organism_assemblies = [];
        foreach ($group_data as $data) {
            if ($data['organism'] === $organism) {
                $organism_assemblies[] = $data['assembly'];
            }
        }
        
        // Check if user has access to at least one assembly
        $has_accessible_assembly = false;
        foreach ($organism_assemblies as $assembly) {
            if (has_assembly_access($organism, $assembly)) {
                $has_accessible_assembly = true;
                break;
            }
        }
        
        if ($has_accessible_assembly) {
            $accessible_organisms[$organism] = $organism_assemblies;
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

    if (!is_dir($assembly_dir)) {
        return $fasta_files;
    }

    // genome.fa stays at assembly level (not in a gene_set subdir)
    foreach ($sequence_types as $type => $seq_config) {
        if (strpos($seq_config['pattern'], 'genome') !== false) {
            if (file_exists("$assembly_dir/genome.fa")) {
                $fasta_files[$type] = [
                    'path'     => "$organism_name/$assembly_name/genome.fa",
                    'label'    => $seq_config['label'],
                    'color'    => $seq_config['color'],
                    'gene_set' => '',
                    'seq_type' => $type,
                ];
            }
            break;
        }
    }

    // protein/transcript/cds live in gene_set subdirs
    $gene_set_dirs = glob("$assembly_dir/*", GLOB_ONLYDIR) ?: [];
    $multi_gs = count($gene_set_dirs) > 1;
    foreach ($gene_set_dirs as $gs_dir) {
        $gene_set = basename($gs_dir);
        foreach ($sequence_types as $type => $seq_config) {
            if (strpos($seq_config['pattern'], 'genome') !== false) continue;
            $matches = glob("$gs_dir/" . $seq_config['pattern']);
            if (!empty($matches)) {
                $key = $type . '.' . $gene_set;
                $label = $seq_config['label'];
                if ($multi_gs) {
                    $label .= ' (' . $gene_set . ')';
                }
                $fasta_files[$key] = [
                    'path'     => "$organism_name/$assembly_name/$gene_set/" . basename($matches[0]),
                    'label'    => $label,
                    'color'    => $seq_config['color'],
                    'gene_set' => $gene_set,
                    'seq_type' => $type,
                ];
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
    
    if (get_access_level() === 'ADMIN' || get_access_level() === 'IP_IN_RANGE') {
        $cards_to_display = $all_cards;
    } elseif (is_logged_in()) {
        // Logged-in users see: public groups + any groups containing their permitted organisms
        $cards_to_display = getPublicGroupCards($group_data);

        $user_organisms = array_keys(get_user_access());
        $group_organisms = [];
        foreach ($group_data as $entry) {
            if (!in_array($entry['organism'], $user_organisms, true)) continue;
            foreach ($entry['groups'] as $group) {
                if (isset($cards_to_display[$group])) continue;
                if (!isset($group_organisms[$group])) $group_organisms[$group] = [];
                $group_organisms[$group][$entry['organism']] = true;
            }
        }
        foreach ($group_organisms as $group => $orgs) {
            $cards_to_display[$group] = [
                'title'          => $group,
                'text'           => "Explore $group Data",
                'link'           => 'tools/groups.php?group=' . urlencode($group),
                'organism_count' => count($orgs),
            ];
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
    if (!is_dir($organism_data_path)) {
        return [];
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $cache_key = 'organisms_with_assemblies_cache';
        $dir_mtime = filemtime($organism_data_path);
        if (isset($_SESSION[$cache_key]) && $_SESSION[$cache_key]['mtime'] === $dir_mtime) {
            return $_SESSION[$cache_key]['data'];
        }
    }

    $orgs = [];
    foreach (scandir($organism_data_path) as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data_path/$organism")) {
            continue;
        }
        $assemblies = [];
        $assemblyPath = "$organism_data_path/$organism";
        foreach (scandir($assemblyPath) as $file) {
            if ($file[0] === '.' || !is_dir("$assemblyPath/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        $orgs[$organism] = $assemblies;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[$cache_key] = ['mtime' => $dir_mtime, 'data' => $orgs];
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
 * @param int $max_retries Maximum number of retry attempts (default 3)
 * @return array|null Array of ['rank' => x, 'name' => y] entries, or null if failed
 */
function fetch_taxonomy_lineage($taxon_id, $max_retries = 3) {
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=taxonomy&id={$taxon_id}&retmode=xml";

    $attempt  = 0;
    $response = false;

    while ($attempt < $max_retries && $response === false) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'MOOP/1.0 Taxonomy Tree Generator',
        ]);
        $result = curl_exec($ch);
        $err    = curl_errno($ch);
        curl_close($ch);

        if ($result !== false && !$err) {
            $response = $result;
        } else {
            $attempt++;
            if ($attempt < $max_retries) {
                usleep(1000000 * (int)pow(2, $attempt - 1));
            }
        }
    }

    if ($response === false) {
        error_log("NCBI taxonomy fetch failed for taxon_id {$taxon_id} after {$max_retries} attempts");
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

// ── Local NCBI dump helpers ───────────────────────────────────────────────────

function ncbi_load_local_dump_meta($metadata_path) {
    $f = "$metadata_path/.ncbi_taxonomy_meta.json";
    if (!file_exists($f)) return [];
    return json_decode(file_get_contents($f), true) ?: [];
}

function ncbi_save_local_dump_meta($metadata_path, array $meta) {
    $f = "$metadata_path/.ncbi_taxonomy_meta.json";
    @file_put_contents($f, json_encode($meta, JSON_PRETTY_PRINT));
    @chmod($f, 0664);
}

/**
 * Fetch the 50-byte MD5 file from NCBI. Returns the hex hash string, or null on failure.
 * Uses a short timeout — callers must handle null gracefully.
 */
function ncbi_fetch_remote_md5($md5_url) {
    $ch = curl_init($md5_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_USERAGENT      => 'MOOP/1.0 Taxonomy Sync',
    ]);
    $body = curl_exec($ch);
    $err  = curl_errno($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $http !== 200 || !$body) return null;
    // File format: "<md5hash>  new_taxdump.tar.gz\n"
    $parts = preg_split('/\s+/', trim($body));
    return (!empty($parts[0]) && strlen($parts[0]) === 32) ? $parts[0] : null;
}

/**
 * Scan the local ncbi_rankedlineage.dmp.gz for a set of taxon IDs.
 *
 * Returns array keyed by taxon_id => ['lineage'=>[...], 'image'=>null, 'fetched'=>'...', 'source'=>'ncbi_dump'].
 * Stops early once all requested IDs are found.
 *
 * @param array  $taxid_set   taxon_id (string) => any truthy value
 * @param string $stored_gz   path to ncbi_rankedlineage.dmp.gz
 */
function ncbi_scan_local_dump(array $taxid_set, string $stored_gz): array {
    if (!file_exists($stored_gz) || empty($taxid_set)) return [];

    $rank_fields = [
        'superkingdom' => 9, 'kingdom' => 8, 'phylum' => 7,
        'class'        => 6, 'order'   => 5, 'family' => 4,
        'genus'        => 3, 'species' => 1,
    ];

    $results = [];
    $needed  = $taxid_set;

    $gz = gzopen($stored_gz, 'r');
    if (!$gz) return [];

    while (!empty($needed) && ($line = gzgets($gz)) !== false) {
        $tab = strpos($line, "\t");
        if ($tab === false) continue;
        $tid = substr($line, 0, $tab);
        if (!isset($needed[$tid])) continue;

        $stripped = rtrim($line, "\r\n");
        if (substr($stripped, -2) === "\t|") $stripped = substr($stripped, 0, -2);
        $parts   = explode("\t|\t", $stripped);
        $lineage = [];
        foreach ($rank_fields as $rank => $idx) {
            $name = isset($parts[$idx]) ? trim($parts[$idx]) : '';
            if ($name !== '') $lineage[] = ['rank' => $rank, 'name' => $name];
        }
        if (!empty($lineage)) {
            $results[$tid] = [
                'lineage' => $lineage,
                'image'   => null,
                'fetched' => date('Y-m-d'),
                'source'  => 'ncbi_dump',
            ];
        }
        unset($needed[$tid]);
    }
    gzclose($gz);

    return $results;
}

/**
 * Load the taxonomy lineage cache from disk.
 * Returns array keyed by taxon_id (string). The 'generated' metadata key is stripped.
 */
function load_lineage_cache($metadata_path) {
    $cache_file = "$metadata_path/taxonomy_lineage_cache.json";
    if (!file_exists($cache_file)) return [];
    $data = json_decode(file_get_contents($cache_file), true);
    if (!is_array($data)) return [];
    unset($data['generated']);
    return $data;
}

/**
 * Persist the taxonomy lineage cache to disk.
 */
function save_lineage_cache($lineage_cache, $metadata_path) {
    $cache_file = "$metadata_path/taxonomy_lineage_cache.json";
    $data = $lineage_cache;
    ksort($data);
    $data['generated'] = date('Y-m-d H:i:s');
    if (@file_put_contents($cache_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        @chmod($cache_file, 0664);
        return true;
    }
    return false;
}

/**
 * Populate lineage cache for any organisms whose taxon_id is absent, using ONLY
 * the local NCBI dump. Never makes live NCBI API calls — those can block indefinitely
 * (D-state) and are too slow for a synchronous cache refresh.
 *
 * If the local dump is missing, logs a warning and returns the cache unchanged.
 * Callers should ensure sync_ncbi_taxonomy_dump.php has been run at least once.
 *
 * @param array    $organisms        organism_name => ['taxon_id', 'genus', 'species', 'common_name', ...]
 * @param array    $lineage_cache    Current cache keyed by taxon_id
 * @param callable $progress_cb      Optional: function($organism_name, $current, $total)
 * @return array   Updated $lineage_cache
 */
function refresh_lineage_cache($organisms, $lineage_cache, $progress_cb = null) {
    $to_fetch = [];
    foreach ($organisms as $org_name => $data) {
        if (empty($data['taxon_id'])) continue;
        $tid = (string)$data['taxon_id'];
        if (!isset($lineage_cache[$tid])) {
            $to_fetch[$org_name] = $data;
        }
    }

    if (empty($to_fetch)) return $lineage_cache;

    $config    = ConfigManager::getInstance();
    $stored_gz = $config->getPath('metadata_path') . '/ncbi_rankedlineage.dmp.gz';

    if (!file_exists($stored_gz)) {
        $missing = implode(', ', array_keys($to_fetch));
        error_log("refresh_lineage_cache: local NCBI dump not found at $stored_gz. "
            . "Run scripts/sync_ncbi_taxonomy_dump.php to download it. "
            . "Skipped organisms: $missing");
        return $lineage_cache;
    }

    $taxid_set = [];
    foreach ($to_fetch as $data) {
        if (!empty($data['taxon_id'])) $taxid_set[(string)$data['taxon_id']] = true;
    }

    $from_dump = ncbi_scan_local_dump($taxid_set, $stored_gz);
    foreach ($from_dump as $tid => $entry) {
        $lineage_cache[$tid] = $entry;
    }

    // Report any taxon_ids that weren't found in the dump (dump may be stale)
    $still_missing = [];
    foreach ($to_fetch as $org_name => $data) {
        if (!isset($lineage_cache[(string)$data['taxon_id']])) {
            $still_missing[] = "$org_name (taxon_id={$data['taxon_id']})";
        }
    }
    if (!empty($still_missing)) {
        error_log("refresh_lineage_cache: " . count($still_missing) . " taxon_id(s) not found in local dump — "
            . "dump may be stale. Re-run scripts/sync_ncbi_taxonomy_dump.php. "
            . "Missing: " . implode(', ', $still_missing));
    }

    return $lineage_cache;
}

/**
 * Build the taxonomy tree from the lineage cache. No network calls — pure data.
 *
 * @param array $organisms     organism_name => ['taxon_id', 'common_name', ...]
 * @param array $lineage_cache Keyed by taxon_id
 * @return array Tree structure ['tree' => [...]]
 */
function build_tree_from_lineage_cache($organisms, $lineage_cache) {
    $tree = ['name' => 'Life', 'children' => []];

    foreach ($organisms as $org_name => $data) {
        if (empty($data['taxon_id'])) continue;
        $tid = (string)$data['taxon_id'];
        if (!isset($lineage_cache[$tid])) continue;

        $cached  = $lineage_cache[$tid];
        $current = &$tree;

        foreach ($cached['lineage'] as $level) {
            $name = $level['name'];
            $rank = $level['rank'];

            $found = false;
            foreach ($current['children'] as &$child) {
                if ($child['name'] === $name) {
                    $current = &$child;
                    $found = true;
                    break;
                }
            }
            unset($child);

            if (!$found) {
                $node = ['name' => $name];
                if ($rank === 'species') {
                    $node['organism']    = $org_name;
                    $node['common_name'] = $data['common_name'] ?? '';
                    if (!empty($cached['image'])) $node['image'] = $cached['image'];
                } else {
                    $node['children'] = [];
                }
                $current['children'][] = $node;
                $current = &$current['children'][count($current['children']) - 1];
            }
        }
        unset($current);
    }

    return ['tree' => $tree];
}

/**
 * Build taxonomy tree from organisms
 *
 * Uses the lineage cache (metadata/taxonomy_lineage_cache.json) so only new
 * organisms require NCBI calls. Pass $force_refetch = true to re-fetch every
 * organism from NCBI (e.g., after a bulk taxonomy correction).
 *
 * @param array $organisms      organism_name => ['taxon_id', 'common_name', ...]
 * @param bool  $force_refetch  Clear cache and re-fetch all lineages from NCBI
 * @return array Tree structure ['tree' => [...]]
 */
function build_tree_from_organisms($organisms, $force_refetch = false) {
    $config       = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');

    $lineage_cache = $force_refetch ? [] : load_lineage_cache($metadata_path);
    $lineage_cache = refresh_lineage_cache($organisms, $lineage_cache);

    save_lineage_cache($lineage_cache, $metadata_path);

    return build_tree_from_lineage_cache($organisms, $lineage_cache);
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
function getDetailedOrganismsInfo($organism_data_path, $sequence_types = [], $progress_callback = null) {
    $organisms_info = [];

    if (!is_dir($organism_data_path)) {
        return $organisms_info;
    }

    // Load all organisms' JSON metadata using consolidated function
    $organisms_metadata = loadAllOrganismsMetadata($organism_data_path);

    // Count organism directories for progress reporting
    $organisms = scandir($organism_data_path);
    $org_count = 0;
    $org_total = 0;
    if ($progress_callback) {
        foreach ($organisms as $o) {
            if ($o[0] !== '.' && is_dir("$organism_data_path/$o")) {
                $org_total++;
            }
        }
    }

    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data_path/$organism")) {
            continue;
        }

        $org_count++;
        if ($progress_callback) {
            $progress_callback($organism, $org_count, $org_total);
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
        'has_fai_index' => false,
        'has_database' => false,
        'database_valid' => false,
        'directories_match_db' => false,
        'assemblies_in_groups' => false,
        'in_taxonomy_tree' => false,
        'metadata_complete' => false,
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
    
    // 3. Are BLAST indexes present for ALL FASTA files?
    // This check fails if there are FASTA files but any are missing BLAST indexes
    if ($checks['has_fasta']) {
        $all_have_indexes = true;
        $has_any_fasta = false;
        
        foreach ($data['assemblies'] as $assembly) {
            // Use pre-computed blast validation if available (from cache)
            $blast_validation = $data['blast_validation'][$assembly] ?? null;
            if (!$blast_validation) {
                $assembly_path = $data['path'] . '/' . $assembly;
                // Aggregate across gene_set subdirs
                $blast_validation = ['databases' => [], 'missing_count' => 0, 'total_count' => 0];
                foreach (glob($assembly_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
                    $bv = validateBlastIndexFiles($gs_dir, $sequence_types);
                    $blast_validation['databases']     = array_merge($blast_validation['databases'], $bv['databases']);
                    $blast_validation['missing_count'] += $bv['missing_count'];
                    $blast_validation['total_count']   += $bv['total_count'];
                }
            }

            if (!empty($blast_validation['databases'])) {
                foreach ($blast_validation['databases'] as $db) {
                    $has_any_fasta = true;
                    // If any FASTA file doesn't have indexes, fail the check
                    if (!$db['has_indexes']) {
                        $all_have_indexes = false;
                        break 2;
                    }
                }
            }
        }
        
        // Set to true only if we found FASTA files AND all have indexes
        $checks['has_blast_indexes'] = ($has_any_fasta && $all_have_indexes);
    }

    // 4. Does genome.fa have a .fai index for every assembly that has one?
    // Passes if no assembly has genome.fa (not applicable); fails if any genome.fa is missing .fai.
    if ($checks['has_assemblies']) {
        $any_genome_fa  = false;
        $all_have_fai   = true;
        foreach ($data['assemblies'] as $assembly) {
            $fai_info = $data['fai_validation'][$assembly] ?? null;
            if (!$fai_info) {
                $genome_fa = $data['path'] . '/' . $assembly . '/genome.fa';
                $fai_info  = [
                    'genome_fa_exists' => file_exists($genome_fa),
                    'fai_exists'       => file_exists($genome_fa . '.fai'),
                ];
            }
            if ($fai_info['genome_fa_exists']) {
                $any_genome_fa = true;
                if (!$fai_info['fai_exists']) {
                    $all_have_fai = false;
                    break;
                }
            }
        }
        $checks['has_fai_index'] = !$any_genome_fa || $all_have_fai;
    }

    // 5. Is there a database file?
    $checks['has_database'] = $data['has_db'];
    
    // 6. Is the database valid? (readable, correct schema, all tables present, no data issues)
    if ($checks['has_database'] && !empty($data['db_validation'])) {
        $checks['database_valid'] = $data['db_validation']['valid'] ?? $data['db_validation']['readable'];
    }

    // 7. Do assembly and gene_set directories on disk match the DB records?
    if ($checks['has_database'] && !empty($data['assembly_validation'])) {
        $checks['directories_match_db'] = $data['assembly_validation']['valid'] ?? false;
    } elseif ($checks['has_database'] && !empty($data['db_file']) && !empty($data['path'])) {
        $live = validateAssemblyDirectories($data['db_file'], $data['path']);
        $checks['directories_match_db'] = $live['valid'];
    }
    
    // 8. Is each assembly a member of at least one group?
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
    
    // 9. Is the organism found in the tree? (use pre-computed value from cache if available)
    $checks['in_taxonomy_tree'] = $data['in_taxonomy_tree'] ?? isAssemblyInTaxonomyTree($organism, '', $taxonomy_tree_file);

    // 10. Is metadata complete?
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


/**
 * Read organisms/.organism_cache.json and pull out every gene_set directory that
 * exists on disk but has no matching row in that organism's database — see
 * validateAssemblyDirectories()'s 'orphaned_gene_set_directory' mismatch type.
 *
 * Deliberately reads only the cache (no DB queries here) so this stays cheap enough
 * to call on every admin dashboard / Manage Groups page load. The cache itself is
 * kept fresh automatically by housekeeping_refresh_organism_cache_if_stale() (at
 * most ~12h old), so this can't silently go stale the way a one-off manual check
 * would if nobody remembered to re-run it.
 *
 * @param string $organism_data_path Path to organisms directory
 * @return array List of ['organism'=>, 'assembly'=>, 'gene_set'=>]
 */
function getOrphanedGeneSetTuples(string $organism_data_path): array {
    $cache_file = "$organism_data_path/.organism_cache.json";
    if (!file_exists($cache_file)) return [];

    $raw = json_decode(file_get_contents($cache_file), true);
    $tuples = [];
    foreach ($raw['data'] ?? [] as $organism => $org_data) {
        foreach ($org_data['assembly_validation']['mismatches'] ?? [] as $mm) {
            if (($mm['type'] ?? '') === 'orphaned_gene_set_directory') {
                $tuples[] = [
                    'organism' => $organism,
                    'assembly' => $mm['assembly_dir'] ?? '',
                    'gene_set' => $mm['gene_set_name'] ?? '',
                ];
            }
        }
    }
    return $tuples;
}

/**
 * Compute the "data health" alerts shown on BOTH the admin dashboard and the manage
 * organisms page. Single source of truth so the two pages never drift. Reads the
 * organism cache (.organism_cache.json) for taxonomy membership + which assemblies
 * exist, and the LIVE groups file for grouping checks (so it stays accurate right
 * after a group edit without waiting for a cache refresh). Cheap (~8ms).
 *
 * @param string $organism_data_path
 * @return array{
 *   health_alerts: array{ungrouped:int,not_in_tree:int,stale_groups:int,new_gene_sets:int,orphaned_gene_sets:int},
 *   orphaned_gene_set_tuples: array,
 *   new_gene_set_tuples: array
 * }
 */
function computeDataHealthAlerts(string $organism_data_path): array {
    $config        = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');
    $cache_file    = "$organism_data_path/.organism_cache.json";
    $groups_file   = "$metadata_path/organism_assembly_groups.json";

    $health_alerts = ['ungrouped' => 0, 'not_in_tree' => 0, 'stale_groups' => 0, 'new_gene_sets' => 0, 'orphaned_gene_sets' => 0];

    // Cache-driven: taxonomy-tree membership + the list of assemblies per organism.
    $cache_data = [];
    if (file_exists($cache_file)) {
        $raw = json_decode(file_get_contents($cache_file), true);
        $cache_data = $raw['data'] ?? [];
    }
    foreach ($cache_data as $org_data) {
        $checks = $org_data['overall_status']['checks'] ?? [];
        if (isset($checks['in_taxonomy_tree']) && !$checks['in_taxonomy_tree']) {
            $health_alerts['not_in_tree']++;
        }
    }

    // Gene-set dirs on disk with no matching DB row (dropped in a rebuild, not cleaned up).
    $orphaned_gene_set_tuples = getOrphanedGeneSetTuples($organism_data_path);
    $health_alerts['orphaned_gene_sets'] = count($orphaned_gene_set_tuples);

    // LIVE groups file — keeps grouping checks accurate right after a group edit.
    $gd = file_exists($groups_file) ? (json_decode(file_get_contents($groups_file), true) ?? []) : [];
    $grouped_pairs = [];
    foreach ($gd as $ge) {
        if (!empty($ge['groups'])) $grouped_pairs[$ge['organism'] . '/' . $ge['assembly']] = true;
    }
    // Organisms where any cached assembly has no group entry (invisible to users).
    foreach ($cache_data as $org_name => $org_data) {
        foreach ($org_data['assemblies'] ?? [] as $asm) {
            if (!isset($grouped_pairs["$org_name/$asm"])) { $health_alerts['ungrouped']++; break; }
        }
    }
    // Group entries whose gene-set directory no longer exists on disk.
    foreach ($gd as $ge) {
        $gs = $ge['gene_set'] ?? 'v1';
        if (!is_dir("$organism_data_path/{$ge['organism']}/{$ge['assembly']}/$gs")) $health_alerts['stale_groups']++;
    }
    // Gene-set dirs on disk with no groups.json entry at all (checked per gene set).
    $new_gene_set_tuples = getUnrepresentedGeneSetTuples(getOrganismsWithAssemblies($organism_data_path), $organism_data_path, $gd);
    $health_alerts['new_gene_sets'] = count($new_gene_set_tuples);

    return [
        'health_alerts'            => $health_alerts,
        'orphaned_gene_set_tuples' => $orphaned_gene_set_tuples,
        'new_gene_set_tuples'      => $new_gene_set_tuples,
    ];
}

/**
 * Get organism info with caching.
 *
 * Returns cached data from organisms/.organism_cache.json if the fingerprint
 * matches. Otherwise performs a full scan, pre-computes blast validation and
 * overall status for each organism, and writes the cache.
 *
 * @param string $organism_data_path Path to organisms directory
 * @param array $sequence_types Sequence type config
 * @param string $taxonomy_tree_file Path to taxonomy_tree_config.json
 * @param array $groups_data Groups data array
 * @param string $groups_file Path to organism_assembly_groups.json
 * @param bool $force_refresh Force a full rescan ignoring cache
 * @return array Same structure as getDetailedOrganismsInfo, plus 'blast_validation'
 *               and 'overall_status' keys per organism
 */
// Increment this when the cache structure or computed fields change, to force a full rescan.
// v3: validateAssemblyDirectories() now also detects gene_set directories on disk with
// no matching DB row ('orphaned_gene_set_directory' mismatch type).
define('ORGANISM_CACHE_SCHEMA_VERSION', 3);

/**
 * Write organism cache atomically: write to a temp file then rename().
 * rename() is atomic on Linux — readers always see a complete file, never a partial write.
 */
function organism_cache_write_atomic($cache_file, array $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $cache_file . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json) === false) return false;
    @chmod($tmp, 0664);
    if (!@rename($tmp, $cache_file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function getCachedOrganismsInfo($organism_data_path, $sequence_types, $taxonomy_tree_file, $groups_data, $groups_file, $force_refresh = false, $progress_callback = null, $force_organisms = []) {
    $cache_file = "$organism_data_path/.organism_cache.json";

    // Build per-organism fingerprints for all current organisms
    $current_fingerprints = buildPerOrganismFingerprints($organism_data_path);

    // Config fingerprint used only for the DECISION of what to rescan.
    // We recompute it at the end before writing so the stored value reflects
    // the final state of config files, not the state at scan start. This prevents
    // the cache from being born stale when a config file changes during a long scan.
    $decision_config_fp = buildConfigFingerprint($taxonomy_tree_file, $groups_file);

    // Try to load existing cache
    $cached = null;
    $cached_fingerprints = [];
    $cached_config = null;
    $cached_data = [];

    if (!$force_refresh && file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached && isset($cached['org_fingerprints']) && isset($cached['config_fingerprint']) && isset($cached['data'])
            && ($cached['schema_version'] ?? 0) === ORGANISM_CACHE_SCHEMA_VERSION) {
            $cached_fingerprints = $cached['org_fingerprints'];
            $cached_config = $cached['config_fingerprint'];
            $cached_data = $cached['data'];
        }
    }

    // Determine what needs updating
    $config_changed = ($cached_config !== $decision_config_fp);
    $organisms_to_scan = [];
    $organisms_to_keep = [];
    // Organisms whose files are unchanged but config changed — reuse expensive validation,
    // only recalculate status fields (group membership, tree placement, overall_status).
    $organisms_config_only = [];

    foreach ($current_fingerprints as $org_name => $fingerprint) {
        // Specific organisms can be force-rescanned regardless of fingerprint.
        if (!empty($force_organisms) && in_array($org_name, $force_organisms, true)) {
            $organisms_to_scan[] = $org_name;
            continue;
        }
        $cached_fingerprint = $cached_fingerprints[$org_name] ?? null;
        if ($cached_fingerprint === $fingerprint) {
            if (!$config_changed) {
                // Organism and config both unchanged — fully reuse
                if (isset($cached_data[$org_name])) {
                    $organisms_to_keep[$org_name] = $cached_data[$org_name];
                } else {
                    $organisms_to_scan[] = $org_name;
                }
            } else {
                // Config changed but organism files unchanged — lightweight status refresh
                if (isset($cached_data[$org_name])) {
                    $organisms_config_only[$org_name] = $cached_data[$org_name];
                } else {
                    $organisms_to_scan[] = $org_name;
                }
            }
        } else {
            // Organism files changed — full rescan
            $organisms_to_scan[] = $org_name;
        }
    }

    // Check for removed organisms (in cache but not in current directory scan)
    foreach ($cached_fingerprints as $org_name => $fingerprint) {
        if (!isset($current_fingerprints[$org_name])) {
            unset($organisms_to_keep[$org_name]);
            unset($organisms_config_only[$org_name]);
        }
    }

    // For config-only changes: recalculate overall_status and in_taxonomy_tree
    // without re-running the expensive DB/FASTA/BLAST validation.
    foreach ($organisms_config_only as $org_name => $org_data) {
        $org_data['in_taxonomy_tree'] = isAssemblyInTaxonomyTree($org_name, '', $taxonomy_tree_file);
        $org_data['overall_status']   = getOrganismOverallStatus($org_name, $org_data, $groups_data, $taxonomy_tree_file, $sequence_types);
        $organisms_to_keep[$org_name] = $org_data;
    }
    
    $total_scan_count = count($organisms_to_scan);
    $removed_count = count($cached_fingerprints) - count($current_fingerprints);

    if ($total_scan_count === 0) {
        // Nothing to scan, but the on-disk cache can still be stale in two ways:
        //   - organisms were deleted (removed entries must not persist), or
        //   - only the groups/taxonomy config changed. In that case we recomputed the
        //     config-only organisms above and must persist the new config_fingerprint;
        //     otherwise a non-force "Update Cache" click never clears the stale banner
        //     (the reload recomputes the new fingerprint and it won't match the old one).
        if ($removed_count > 0 || $config_changed) {
            $cache_data = [
                'generated'          => date('Y-m-d H:i:s'),
                'schema_version'     => ORGANISM_CACHE_SCHEMA_VERSION,
                'config_fingerprint' => buildConfigFingerprint($taxonomy_tree_file, $groups_file),
                'org_fingerprints'   => $current_fingerprints,
                'data'               => $organisms_to_keep,
            ];
            organism_cache_write_atomic($cache_file, $cache_data);
        }
        return $organisms_to_keep;
    }
    
    // Scan only the organisms that need updating
    $scanned_organisms = [];
    $current = 0;
    
    // Load metadata for organisms we're about to scan
    $organisms_metadata = loadAllOrganismsMetadata($organism_data_path);
    
    foreach ($organisms_to_scan as $org_name) {
        $current++;
        if ($progress_callback) {
            $progress_callback($org_name, $current, $total_scan_count, 'scanning');
        }
        
        $org_path = "$organism_data_path/$org_name";
        if (!is_dir($org_path)) {
            continue;
        }
        
        // Build organism info (inline from getDetailedOrganismsInfo logic)
        $info = $organisms_metadata[$org_name] ?? [];
        
        // Get assemblies
        $assemblies = [];
        $files = scandir($org_path);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$org_path/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        
        // Check for database file
        $db_file = null;
        if (file_exists("$org_path/organism.sqlite")) {
            $db_file = "$org_path/organism.sqlite";
        }
        
        $has_db = !is_null($db_file);
        
        // Validate database integrity if exists
        $db_validation = null;
        $assembly_validation = null;
        $fasta_validation = null;
        if ($has_db) {
            if ($progress_callback) {
                $progress_callback($org_name, $current, $total_scan_count, 'checking database');
            }
            $db_validation = validateDatabaseIntegrity($db_file);
            if ($progress_callback) {
                $progress_callback($org_name, $current, $total_scan_count, 'checking assembly directories');
            }
            $assembly_validation = validateAssemblyDirectories($db_file, $org_path);
        }
        // Validate FASTA files in assembly directories
        if ($progress_callback) {
            $progress_callback($org_name, $current, $total_scan_count, 'checking FASTA files');
        }
        $fasta_validation = validateAssemblyFastaFiles($org_path, $sequence_types);
        
        $org_info = [
            'path' => $org_path,
            'info' => $info,
            'assemblies' => $assemblies,
            'has_db' => $has_db,
            'db_file' => $db_file,
            'db_validation' => $db_validation,
            'assembly_validation' => $assembly_validation,
            'fasta_validation' => $fasta_validation,
            'json_validation' => validateOrganismJson("$org_path/organism.json")
        ];
        
        // Pre-compute blast validation per assembly — aggregate across gene_set subdirs
        if ($progress_callback) {
            $progress_callback($org_name, $current, $total_scan_count, 'checking BLAST indexes');
        }
        $blast_by_assembly = [];
        foreach ($org_info['assemblies'] as $assembly) {
            $assembly_path = $org_path . '/' . $assembly;
            $aggregated = ['databases' => [], 'missing_count' => 0, 'total_count' => 0];
            foreach (glob($assembly_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
                $bv = validateBlastIndexFiles($gs_dir, $sequence_types);
                $aggregated['databases']     = array_merge($aggregated['databases'], $bv['databases']);
                $aggregated['missing_count'] += $bv['missing_count'];
                $aggregated['total_count']   += $bv['total_count'];
            }
            $blast_by_assembly[$assembly] = $aggregated;
        }
        $org_info['blast_validation'] = $blast_by_assembly;

        // Pre-compute FAI validation per assembly
        $fai_by_assembly = [];
        foreach ($org_info['assemblies'] as $assembly) {
            $genome_fa = $org_path . '/' . $assembly . '/genome.fa';
            $fai_by_assembly[$assembly] = [
                'genome_fa_exists' => file_exists($genome_fa),
                'fai_exists'       => file_exists($genome_fa . '.fai'),
            ];
        }
        $org_info['fai_validation'] = $fai_by_assembly;
        
        // Pre-compute taxonomy tree membership
        $org_info['in_taxonomy_tree'] = isAssemblyInTaxonomyTree($org_name, '', $taxonomy_tree_file);
        
        // Pre-compute overall status
        $org_info['overall_status'] = getOrganismOverallStatus($org_name, $org_info, $groups_data, $taxonomy_tree_file, $sequence_types);
        
        $scanned_organisms[$org_name] = $org_info;
    }
    
    // Merge: kept organisms + newly scanned organisms
    $all_organisms = array_merge($organisms_to_keep, $scanned_organisms);
    
    // Sort by organism name
    ksort($all_organisms);
    
    // Recompute config fingerprint at END of scan so the stored value reflects
    // the final state of config files, not the state when scanning started.
    $cache_data = [
        'generated'          => date('Y-m-d H:i:s'),
        'schema_version'     => ORGANISM_CACHE_SCHEMA_VERSION,
        'config_fingerprint' => buildConfigFingerprint($taxonomy_tree_file, $groups_file),
        'org_fingerprints'   => $current_fingerprints,
        'data'               => $all_organisms,
    ];

    organism_cache_write_atomic($cache_file, $cache_data);

    return $all_organisms;
}

/**
 * Build fingerprint for config files (tree, groups)
 */
function buildConfigFingerprint($taxonomy_tree_file, $groups_file) {
    $parts = [];
    // Note: taxonomy_tree_config.json is intentionally excluded — it is an output
    // of the organism scan, so including its mtime would create a circular invalidation.
    if (file_exists($groups_file)) {
        // mtime + size — a re-synced groups file can preserve its mtime, so size makes
        // an actual content change detectable (same reasoning as buildPerOrganismFingerprints).
        $parts[] = 'groups:' . filemtime($groups_file) . ':' . filesize($groups_file);
    }
    return md5(implode('|', $parts));
}

/**
 * Build per-organism fingerprints
 * 
 * Returns array of organism_name => fingerprint
 * Fingerprint includes: sqlite mtime+size, organism.json mtime+size, assembly count,
 * and per-assembly / per-gene-set directory mtimes. Size is mixed in alongside mtime so
 * timestamp-preserving copies (rsync -a, cp -p, tar, restore) don't slip past undetected.
 */
function buildPerOrganismFingerprints($organism_data_path) {
    $fingerprints = [];
    
    if (!is_dir($organism_data_path)) {
        return $fingerprints;
    }
    
    $organisms = scandir($organism_data_path);
    
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data_path/$organism")) {
            continue;
        }
        
        $org_path = "$organism_data_path/$organism";
        $parts = [];
        
        // SQLite file: mtime + size. Size catches timestamp-preserving replacements
        // (rsync -a, cp -p, tar extract, restore-from-backup all keep the old mtime) —
        // a rebuilt or re-synced DB is virtually always a different byte size.
        $sqlite_file = "$org_path/organism.sqlite";
        if (file_exists($sqlite_file)) {
            $parts[] = 'db:' . filemtime($sqlite_file) . ':' . filesize($sqlite_file);
        }
        
        // organism.json: mtime + size (same timestamp-lies reasoning as the DB above)
        $json_file = "$org_path/organism.json";
        if (file_exists($json_file)) {
            $parts[] = 'json:' . filemtime($json_file) . ':' . filesize($json_file);
        }
        
        // Assembly directories — include each dir's mtime so that adding/removing
        // files inside an assembly (FASTA, FAI, BLAST indexes) triggers a rescan.
        $assemblies = array_filter(scandir($org_path), function($f) use ($org_path) {
            return $f[0] !== '.' && is_dir("$org_path/$f");
        });
        $parts[] = 'asm:' . count($assemblies);
        foreach ($assemblies as $asm) {
            $parts[] = 'asm_mtime:' . filemtime("$org_path/$asm");
            // Gene_set subdirs sit one level below the assembly dir. On Linux, only direct
            // children affect a directory's mtime, so files changed inside a gene_set subdir
            // (e.g. BLAST indexes rebuilt) must be tracked here explicitly.
            foreach (glob("$org_path/$asm/*", GLOB_ONLYDIR) ?: [] as $gs_dir) {
                $parts[] = 'gs_mtime:' . basename($gs_dir) . ':' . filemtime($gs_dir);
            }
        }
        
        $fingerprints[$organism] = md5(implode('|', $parts));
    }
    
    return $fingerprints;
}

/**
 * Build a one-line description from lineage cache data.
 * Used as a fallback when Wikipedia has no article for an organism.
 * e.g. "Schmidtea nova is a species of flatworm in the family Dugesiidae."
 *
 * @param string $scientific_name  e.g. "Schmidtea nova"
 * @param array  $lineage          Array of ['rank'=>..., 'name'=>...] from lineage cache
 * @return string  One sentence, or empty string if not enough data.
 */
function buildAutoDescription(string $scientific_name, array $lineage): string {
    if (empty($scientific_name) || empty($lineage)) return '';

    $ranks = [];
    foreach ($lineage as $entry) {
        $ranks[$entry['rank']] = $entry['name'];
    }

    // Class-level names (more specific than phylum)
    $class_map = [
        'Mammalia'       => 'mammal',
        'Aves'           => 'bird',
        'Reptilia'       => 'reptile',
        'Amphibia'       => 'amphibian',
        'Actinopterygii' => 'ray-finned fish',
        'Chondrichthyes' => 'cartilaginous fish',
        'Insecta'        => 'insect',
        'Arachnida'      => 'arachnid',
        'Malacostraca'   => 'crustacean',
        'Magnoliopsida'  => 'flowering plant',
        'Liliopsida'     => 'monocot plant',
        'Pinopsida'      => 'conifer',
    ];

    // Phylum-level fallbacks
    $phylum_map = [
        'Platyhelminthes' => 'flatworm',
        'Nematoda'        => 'nematode',
        'Annelida'        => 'annelid worm',
        'Arthropoda'      => 'arthropod',
        'Mollusca'        => 'mollusc',
        'Echinodermata'   => 'echinoderm',
        'Chordata'        => 'chordate',
        'Porifera'        => 'sponge',
        'Cnidaria'        => 'cnidarian',
        'Streptophyta'    => 'plant',
        'Ascomycota'      => 'fungus',
        'Basidiomycota'   => 'fungus',
        'Apicomplexa'     => 'apicomplexan parasite',
        'Euglenozoa'      => 'euglenozoan',
        'Amoebozoa'       => 'amoeba',
        'Ciliophora'      => 'ciliate',
        'Rhodophyta'      => 'red alga',
        'Chlorophyta'     => 'green alga',
        'Bacillariophyta' => 'diatom',
    ];

    $type = $class_map[$ranks['class'] ?? ''] ?? $phylum_map[$ranks['phylum'] ?? ''] ?? null;

    $sentence = $type
        ? "$scientific_name is a species of $type"
        : "$scientific_name is a species";

    if (!empty($ranks['family'])) {
        $sentence .= " in the family {$ranks['family']}";
    } elseif (!empty($ranks['order'])) {
        $sentence .= " in the order {$ranks['order']}";
    }

    return $sentence . '.';
}

/**
 * Fetch organism info from NCBI using genus and species
 * Gets taxon_id and common name for a given genus/species
 * 
 * @param string $genus Organism genus
 * @param string $species Organism species
 * @return array Array with 'taxon_id', 'common_name', 'scientific_name', 'error'
 */
function fetchOrganismInfoFromNCBI($genus, $species) {
    $result = [
        'taxon_id' => '',
        'common_name' => '',
        'scientific_name' => "$genus $species",
        'error' => ''
    ];
    
    if (empty($genus) || empty($species)) {
        $result['error'] = 'Genus and species are required';
        return $result;
    }
    
    // Search for the organism on NCBI
    $search_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . http_build_query([
        'db' => 'taxonomy',
        'term' => "$genus $species",
        'retmode' => 'json'
    ]);
    
    $response = moop_curl_get($search_url);

    if ($response === false) {
        $result['error'] = 'Failed to connect to NCBI';
        return $result;
    }

    $data = json_decode($response, true);

    if (empty($data['esearchresult']['idlist'])) {
        $result['error'] = 'Organism not found on NCBI';
        return $result;
    }

    $taxon_id = $data['esearchresult']['idlist'][0];
    $result['taxon_id'] = $taxon_id;

    // Fetch full details
    $fetch_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query([
        'db' => 'taxonomy',
        'id' => $taxon_id,
        'retmode' => 'json'
    ]);

    $response = moop_curl_get($fetch_url);
    
    if ($response === false) {
        return $result;
    }
    
    $data = json_decode($response, true);
    
    if (!empty($data['result'][$taxon_id]['commonname'])) {
        $result['common_name'] = $data['result'][$taxon_id]['commonname'];
    }
    
    return $result;
}
