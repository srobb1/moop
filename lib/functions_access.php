<?php
/**
 * MOOP Access Control Functions
 * User access checking, assembly accessibility, and permission-based filtering
 */

// Include filesystem functions for assembly validation
require_once __DIR__ . '/functions_filesystem.php';

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
 * Get gene_set information from database
 *
 * @param string $assembly - genome_accession or genome_name
 * @param string $gene_set - gene_set_name
 * @param string $db_path  - path to organism.sqlite
 * @return array - [gene_set_id, genome_id] or [null, null] on error
 */
function getGeneSetInfo($assembly, $gene_set, $db_path) {
    if (!file_exists($db_path)) {
        return [null, null];
    }

    try {
        $query = "SELECT gs.gene_set_id, gs.genome_id
                  FROM gene_set gs
                  JOIN genome g ON gs.genome_id = g.genome_id
                  WHERE (g.genome_accession = ? OR g.genome_name = ?)
                  AND gs.gene_set_name = ?";
        $results = fetchData($query, $db_path, [$assembly, $assembly, $gene_set]);
        if (!empty($results)) {
            return [(int)$results[0]['gene_set_id'], (int)$results[0]['genome_id']];
        }
    } catch (Exception $e) {
        // return defaults
    }

    return [null, null];
}

/**
 * Resolve source selection from organism, assembly, and gene_set parameters
 * Handles both genome_name and genome_accession formats
 * Returns the correct selected_source string for pre-selecting radio buttons
 *
 * @param string $organism Organism name
 * @param string $assembly Assembly (could be genome_name or genome_accession)
 * @param array $accessible_sources Flattened list of sources with genome_id, genome_name, gene_set, etc.
 * @param string|null $gene_set Optional gene_set name to match against
 * @return string Selected source in format "organism|assembly_dir|gene_set" or empty string if not found
 */
function resolveSourceSelection($organism, $assembly, $accessible_sources, $gene_set = null) {
    // Try direct matches first: assembly dir, genome_name, or genome_id
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $organism) {
            $assembly_match = ($source['assembly'] === $assembly ||
                               $source['genome_name'] === $assembly ||
                               $source['genome_id'] === $assembly);
            $gene_set_match = ($gene_set === null || ($source['gene_set'] ?? '') === $gene_set);
            if ($assembly_match && $gene_set_match) {
                return $organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
            }
        }
    }

    // If not found by direct match, try via database lookup
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');

    try {
        $db_path = "$organism_data/$organism/organism.sqlite";
        [$genome_id_param, $genome_name_param, $genome_accession_param] = getAssemblyInfo($assembly, $db_path);

        if (!empty($genome_name_param)) {
            foreach ($accessible_sources as $source) {
                $gene_set_match = ($gene_set === null || ($source['gene_set'] ?? '') === $gene_set);
                if ($source['organism'] === $organism &&
                    $source['genome_name'] === $genome_name_param &&
                    $gene_set_match) {
                    return $organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                }
            }
        }
    } catch (Exception $e) {
        // If lookup fails, return empty
    }

    return '';
}

/**
 * Get gene_sets accessible to current user
 * Filters gene_sets based on user access level and group membership
 *
 * Each entry in the returned structure represents one (organism, assembly, gene_set) tuple.
 * The return shape is: array<group_name, array<organism_name, array<source>>>
 * where each source includes: organism, assembly, gene_set, genome_name,
 * genome_accession, path, groups, genome_id, gene_set_id.
 *
 * @param string|null $specific_organism Optional organism to filter by
 * @param string|null $specific_assembly Optional assembly to filter by
 * @param string|null $specific_gene_set Optional gene_set to filter by
 * @return array Organized by group -> organism -> [sources]
 */
function getAccessibleAssemblies($specific_organism = null, $specific_assembly = null, $specific_gene_set = null) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    $metadata_path = $config->getPath('metadata_path');

    $groups_data = [];
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true) ?: [];
    }

    // Session-based caching for full (unfiltered) requests only
    $use_cache = ($specific_organism === null && $specific_assembly === null && $specific_gene_set === null
                  && session_status() === PHP_SESSION_ACTIVE);
    if ($use_cache) {
        $cache_key = 'accessible_assemblies_cache';
        $groups_mtime = file_exists($groups_file) ? filemtime($groups_file) : 0;
        $access_level = get_access_level();

        if (isset($_SESSION[$cache_key]) &&
            $_SESSION[$cache_key]['groups_mtime'] === $groups_mtime &&
            $_SESSION[$cache_key]['access_level'] === $access_level) {
            return $_SESSION[$cache_key]['data'];
        }
    }

    $accessible_sources = [];

    // Filter entries to process
    $entries_to_process = $groups_data;

    if (!empty($specific_organism)) {
        $entries_to_process = array_filter($entries_to_process, function($entry) use ($specific_organism) {
            return $entry['organism'] === $specific_organism;
        });
    }
    if (!empty($specific_assembly)) {
        $entries_to_process = array_filter($entries_to_process, function($entry) use ($specific_assembly) {
            return $entry['assembly'] === $specific_assembly;
        });
    }
    if (!empty($specific_gene_set)) {
        $entries_to_process = array_filter($entries_to_process, function($entry) use ($specific_gene_set) {
            return ($entry['gene_set'] ?? '') === $specific_gene_set;
        });
    }

    // Per-organism DB validation cache so we only call validateAssemblyDirectories once per organism
    $assembly_validation_cache = [];

    foreach ($entries_to_process as $entry) {
        $org      = $entry['organism'];
        $assembly = $entry['assembly'];
        $gene_set = $entry['gene_set'] ?? 'v1';
        $entry_groups = $entry['groups'] ?? [];

        // Access check — use has_gene_set_access which handles all levels
        if (!has_gene_set_access($org, $assembly, $gene_set)) {
            continue;
        }

        $assembly_path  = "$organism_data/$org/$assembly";
        $gene_set_path  = "$assembly_path/$gene_set";

        if (!is_dir($assembly_path) || !is_dir($gene_set_path)) {
            continue;
        }

        // Gene_set directory must contain at least one sequence FASTA
        $has_fasta = false;
        foreach (['.fa', '.fasta', '.faa', '.nt.fa', '.aa.fa'] as $ext) {
            if (glob("$gene_set_path/*$ext")) {
                $has_fasta = true;
                break;
            }
        }
        if (!$has_fasta) {
            continue;
        }

        $db_path = "$organism_data/$org/organism.sqlite";

        // Resolve actual assembly directory name via DB (genome_name vs genome_accession)
        if (!isset($assembly_validation_cache[$org])) {
            $assembly_validation_cache[$org] = validateAssemblyDirectories($db_path, "$organism_data/$org");
        }
        $assembly_validation = $assembly_validation_cache[$org];

        $genome_id           = null;
        $genome_name         = null;
        $genome_accession    = null;
        $actual_assembly_dir = $assembly;

        if ($assembly_validation && !empty($assembly_validation['genomes'])) {
            foreach ($assembly_validation['genomes'] as $genome) {
                if ($genome['genome_name'] === $assembly || $genome['genome_accession'] === $assembly) {
                    $genome_id           = $genome['genome_id'];
                    $genome_name         = $genome['genome_name'];
                    $genome_accession    = $genome['genome_accession'];
                    $actual_assembly_dir = $genome['directory_found'] ?? $assembly;
                    break;
                }
            }
        }

        // Resolve gene_set_id from DB
        [$gene_set_id] = getGeneSetInfo($assembly, $gene_set, $db_path);

        $accessible_sources[] = [
            'organism'         => $org,
            'assembly'         => $actual_assembly_dir,
            'gene_set'         => $gene_set,
            'genome_name'      => $genome_name,
            'genome_accession' => $genome_accession,
            'path'             => "$organism_data/$org/$actual_assembly_dir/$gene_set",
            'groups'           => $entry_groups,
            'genome_id'        => $genome_id,
            'gene_set_id'      => $gene_set_id,
        ];
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

    if ($use_cache) {
        $_SESSION[$cache_key] = [
            'groups_mtime' => $groups_mtime,
            'access_level' => $access_level,
            'data'         => $organized,
        ];
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
        // Logged-in users get their specific access PLUS public organisms
        $taxonomy_user_access = get_user_access();
        
        // Add public organisms
        foreach ($group_data as $data) {
            if (in_array('PUBLIC', $data['groups'])) {
                $organism = $data['organism'];
                if (!isset($taxonomy_user_access[$organism])) {
                    $taxonomy_user_access[$organism] = true;
                }
            }
        }
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
 * @param string $level Required access level (e.g., 'COLLABORATOR', 'ADMIN')
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
