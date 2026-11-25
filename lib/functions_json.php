<?php
/**
 * MOOP JSON Functions
 * JSON file loading, parsing, and data manipulation
 */

// PHP 8.1+ polyfill
if (!function_exists('array_is_list')) {
    function array_is_list(array $arr): bool {
        if ($arr === []) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Is this a time zone identifier PHP knows ('America/Chicago', 'UTC', ...)?
 */
function moop_is_valid_timezone($timezone): bool {
    return is_string($timezone) && $timezone !== ''
        && in_array($timezone, timezone_identifiers_list(), true);
}

/**
 * Set PHP's default time zone from the site setting.
 *
 * Empty or unrecognised leaves PHP's own setting alone and returns false: a typo in a config
 * file must not take the site down or silently move it to some other zone.
 */
function moop_apply_timezone($timezone): bool {
    if (!moop_is_valid_timezone($timezone)) return false;
    return date_default_timezone_set($timezone);
}

/**
 * Load JSON file safely with error handling
 * 
 * @param string $path Path to JSON file
 * @param mixed $default Default value if file doesn't exist (default: [])
 * @return mixed Decoded JSON data or default value
 */
function loadJsonFile($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }
    
    $json_content = file_get_contents($path);
    if ($json_content === false) {
        return $default;
    }
    
    $data = json_decode($json_content, true);
    return $data !== null ? $data : $default;
}

/**
 * Load JSON file and require it to exist
 * 
 * @param string $path Path to JSON file
 * @param string $errorMsg Error message to log if file missing
 * @param bool $exitOnError Whether to exit if file not found (default: true)
 * @return mixed Decoded JSON data or empty array if error
 */
function loadJsonFileRequired($path, $errorMsg = '', $exitOnError = false) {
    if (!file_exists($path)) {
        if ($errorMsg) {
            error_log($errorMsg);
        }
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Required data file not found");
        }
        return [];
    }
    
    $json_content = file_get_contents($path);
    if ($json_content === false) {
        $msg = $errorMsg ?: "Failed to read file: $path";
        error_log($msg);
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Failed to read required data");
        }
        return [];
    }
    
    $data = json_decode($json_content, true);
    if ($data === null) {
        $msg = $errorMsg ?: "Invalid JSON in file: $path";
        error_log($msg);
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Invalid data format");
        }
        return [];
    }
    
    return $data;
}

/**
 * Load existing JSON file and merge with new data
 * Handles wrapped JSON automatically, preserves existing fields not in merge data
 * 
 * @param string $file_path Path to JSON file to load
 * @param array $new_data New data to merge in (overwrites matching keys)
 * @return array Merged data (or just new_data if file doesn't exist)
 */
function loadAndMergeJson($file_path, $new_data = []) {
    // If file doesn't exist, just return new data
    if (!file_exists($file_path)) {
        return $new_data;
    }
    
    // Load existing data
    $existing = loadJsonFile($file_path);
    
    if (!is_array($existing)) {
        return $new_data;
    }
    
    // Handle wrapped JSON (extra outer braces)
    if (!isset($existing['genus']) && !isset($existing['common_name'])) {
        $keys = array_keys($existing);
        if (count($keys) > 0 && is_array($existing[$keys[0]])) {
            $existing = $existing[$keys[0]];
        }
    }
    
    // Merge: existing fields are preserved, new data overwrites matching keys
    return array_merge($existing, $new_data);
}

/**
 * Decode JSON string safely with type checking
 * 
 * @param string $json_string JSON string to decode
 * @param bool $as_array Return as array (default: true)
 * @return array|null Decoded data or null if invalid
 */
function decodeJsonString($json_string, $as_array = true) {
    if (empty($json_string)) {
        return $as_array ? [] : null;
    }
    
    $decoded = json_decode($json_string, $as_array);
    
    // Validate it decoded properly
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return $as_array ? [] : null;
    }
    
    return $decoded;
}

/**
 * Save data to JSON file with pretty printing
 * Writes JSON data with readable formatting
 * 
 * @param string $path Path to JSON file to save
 * @param array $data Data to encode and save
 * @return int|false Number of bytes written, or false on failure
 */
function saveJsonFile($path, $data) {
    // An encode failure (bad UTF-8, a NaN) used to go straight to file_put_contents as false,
    // which wrote an EMPTY file and returned 0 -- not false, so even a caller that checked the
    // result reported the wipe as a save (found 2026-11-25). Refuse instead, and say why.
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log('saveJsonFile: not written, JSON encode failed for ' . $path . ': ' . json_last_error_msg());
        return false;
    }
    $bytes = @file_put_contents($path, $json, LOCK_EX);
    if ($bytes === false) error_log('saveJsonFile: could not write ' . $path);
    return $bytes;
}

/**
 * Build mapping from DB types to canonical config names
 * Uses synonyms to map all aliases to their canonical entry
 * 
 * @param array $annotation_config - Loaded annotation_config.json
 * @return array - [db_type => canonical_name] mapping
 */
function getAnnotationTypeMapping($annotation_config) {
    $mapping = [];
    
    if (!isset($annotation_config['annotation_types'])) {
        return $mapping;
    }
    
    foreach ($annotation_config['annotation_types'] as $canonical_name => $config) {
        // Map the canonical name to itself
        $mapping[$canonical_name] = $canonical_name;
        
        // Map each synonym to this canonical name
        if (isset($config['synonyms']) && is_array($config['synonyms'])) {
            foreach ($config['synonyms'] as $synonym) {
                $mapping[$synonym] = $canonical_name;
            }
        }
    }
    
    return $mapping;
}

/**
 * Synchronize annotation types between config and database
 * Creates entries for unmapped DB types, marks unused entries
 * Populates annotation_count and feature_count for each type
 * 
 * @param array $annotation_config - Current annotation_config.json
 * @param array $db_types - [annotation_type => ['annotation_count' => N, 'feature_count' => M]]
 * @return array - Updated config with sync metadata
 */
function syncAnnotationTypes($annotation_config, $db_types) {
    if (!isset($annotation_config['annotation_types'])) {
        $annotation_config['annotation_types'] = [];
    }
    
    // Get current mapping (canonical + synonyms)
    $mapping = getAnnotationTypeMapping($annotation_config);
    
    // Mark all existing entries as not in database initially
    foreach ($annotation_config['annotation_types'] as &$config) {
        $config['in_database'] = false;
        $config['annotation_count'] = 0;
        $config['feature_count'] = 0;
    }
    unset($config);
    
    // Check which DB types are covered by existing config
    $mapped_db_types = [];
    foreach ($db_types as $db_type => $counts) {
        if (isset($mapping[$db_type])) {
            // This DB type maps to an existing config entry
            $canonical = $mapping[$db_type];
            $annotation_config['annotation_types'][$canonical]['in_database'] = true;
            $annotation_config['annotation_types'][$canonical]['annotation_count'] += $counts['annotation_count'];
            $annotation_config['annotation_types'][$canonical]['feature_count'] += $counts['feature_count'];
            $mapped_db_types[$db_type] = true;
        }
    }
    
    // Add unmapped DB types as new entries
    $next_order = 1;
    if (!empty($annotation_config['annotation_types'])) {
        $orders = array_map(function($c) { return $c['order'] ?? 0; }, 
                          $annotation_config['annotation_types']);
        $next_order = max($orders) + 1;
    }
    
    foreach ($db_types as $db_type => $counts) {
        if (!isset($mapped_db_types[$db_type])) {
            // New type not in config - add it
            $annotation_config['annotation_types'][$db_type] = [
                'display_name' => $db_type,
                'display_label' => $db_type,
                'color' => 'secondary',
                'order' => $next_order,
                'description' => '',
                'enabled' => true,
                'synonyms' => [],
                'in_database' => true,
                'annotation_count' => $counts['annotation_count'],
                'feature_count' => $counts['feature_count'],
                'new' => true
            ];
            $next_order++;
        }
    }
    
    return $annotation_config;
}

/**
 * What pages call an annotation type: its display_label in annotation_config.json (Admin ->
 * Manage Annotations -> the type -> "Name shown on pages"), else the name as stored in the
 * database. The database name stays the KEY everywhere (filters, URLs, data-type, the API);
 * only the visible text goes through here. display_name is deliberately NOT a fallback: on
 * some sites it differs from the key and was never shown, so using it would rename types
 * nobody asked to rename. A synonym resolves to its canonical type's label.
 */
function moop_annotation_type_label(string $type): string {
    static $labels = null;
    if ($labels === null) {
        $labels = [];
        $file = ConfigManager::getInstance()->getPath('metadata_path') . '/annotation_config.json';
        $cfg  = is_file($file) ? loadJsonFile($file, []) : [];
        foreach (getAnnotationTypeMapping($cfg) as $name => $canonical) {
            $label = trim((string) ($cfg['annotation_types'][$canonical]['display_label'] ?? ''));
            if ($label !== '' && $label !== $name) $labels[$name] = $label;
        }
    }
    return t_plain($labels[$type] ?? $type);
}

/**
 * A source name as a visitor reads it: the OMA reference species' UniProt codes written out --
 * "OMA HOG orthologs (NEMVE)" -> "OMA HOG orthologs (Nematostella vectensis, NEMVE)". Visible text
 * only; the stored name stays the value everywhere. The 10 species are OMA's fixed reference set
 * (config/oma/OMA_SET_AND_NAMING.md); a code not listed here is left as it is.
 */
function moop_annotation_source_label(string $source): string {
    static $species = [
        'BRAFL' => 'Branchiostoma floridae', 'CALMI' => 'Callorhinchus milii',
        'CAPTE' => 'Capitella teleta',       'DROME' => 'Drosophila melanogaster',
        'HUMAN' => 'Homo sapiens',           'LEPOC' => 'Lepisosteus oculatus',
        'LOTGI' => 'Lottia gigantea',        'MONBE' => 'Monosiga brevicollis',
        'MOUSE' => 'Mus musculus',           'NEMVE' => 'Nematostella vectensis',
    ];
    return preg_replace_callback('/\(([A-Z]{5})\)\s*$/', fn($m) => isset($species[$m[1]])
        ? '(' . $species[$m[1]] . ', ' . $m[1] . ')' : $m[0], $source);
}

/**
 * Update annotation config using per-organism tracking.
 * Only re-queries SQLite databases for organisms whose mtime changed.
 * Replaces the old all-or-nothing shouldUpdateAnnotationCounts approach.
 *
 * @param array    $annotation_config   Current config array (from annotation_config.json)
 * @param string   $organism_data_path  Path to organisms directory
 * @param bool     $force               Re-query all organisms regardless of mtime
 * @param callable $progress            Optional: function($org_name, $current, $total)
 * @return array   [$updated_config, $updated_count]
 */
function update_annotation_config_modular(array $annotation_config, string $organism_data_path, bool $force = false, callable $progress = null): array {
    $per_org = $annotation_config['per_organism_counts'] ?? [];
    $orgs    = getOrganismsWithAssemblies($organism_data_path);

    // Determine which organisms need re-querying
    $to_query = [];
    foreach ($orgs as $org_name => $_) {
        $db_file = "$organism_data_path/$org_name/organism.sqlite";
        if (!file_exists($db_file)) continue;
        $mtime = filemtime($db_file);
        if ($force || ($per_org[$org_name]['sqlite_mod_time'] ?? null) !== $mtime) {
            $to_query[$org_name] = ['db' => $db_file, 'mtime' => $mtime];
        }
    }

    // Re-query only changed organisms
    $updated_count = count($to_query);
    $current = 0;
    foreach ($to_query as $org_name => $info) {
        $current++;
        if ($progress) ($progress)($org_name, $current, $updated_count);
        $per_org[$org_name] = [
            'sqlite_mod_time' => $info['mtime'],
            'types'           => getAnnotationTypesFromDB($info['db']),
        ];
    }

    // Remove organisms that no longer exist on disk
    foreach (array_keys($per_org) as $org_name) {
        if (!isset($orgs[$org_name])) unset($per_org[$org_name]);
    }

    // Rebuild aggregates if anything changed
    if ($updated_count > 0 || $force || !isset($annotation_config['annotation_types'])) {
        $aggregated = [];
        foreach ($per_org as $org_data) {
            foreach (($org_data['types'] ?? []) as $type => $counts) {
                if (!isset($aggregated[$type])) {
                    $aggregated[$type] = $counts;
                } else {
                    $aggregated[$type]['annotation_count'] += $counts['annotation_count'];
                    $aggregated[$type]['feature_count']    += $counts['feature_count'];
                }
            }
        }

        if (isset($annotation_config['annotation_types'])) {
            $annotation_config = syncAnnotationTypes($annotation_config, $aggregated);
            $type_order = [];
            foreach ($annotation_config['annotation_types'] as $type_name => $cfg) {
                $type_order[] = ['name' => $type_name, 'order' => $cfg['order'] ?? 999];
            }
            usort($type_order, fn($a, $b) => $a['order'] - $b['order']);
            $annotation_config['annotation_type_order'] = array_column($type_order, 'name');
        }
    }

    $annotation_config['per_organism_counts'] = $per_org;

    // Keep legacy sqlite_mod_time updated (used by older code paths as a quick dirty-check)
    $newest = 0;
    foreach ($per_org as $d) { $newest = max($newest, $d['sqlite_mod_time'] ?? 0); }
    if ($newest > 0) $annotation_config['sqlite_mod_time'] = $newest;

    return [$annotation_config, $updated_count];
}
