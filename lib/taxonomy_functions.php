<?php
/**
 * NCBI taxonomy & lineage helpers — extracted from functions_data.php (2026-07-07)
 * as part of the code-review Phase 3 file split.
 *
 * Fetch taxonomic lineage from NCBI, maintain the local lineage cache
 * (metadata/taxonomy_lineage_cache.json) including the optional NCBI dump path,
 * and build the taxonomy tree from it. Loaded via a require_once at the top of
 * functions_data.php, so every existing include of functions_data.php continues
 * to expose these unchanged.
 */

// ── Local NCBI dump helpers ───────────────────────────────────────────────────

function ncbi_load_local_dump_meta($metadata_path) {
    $f = "$metadata_path/.ncbi_taxonomy_meta.json";
    if (!file_exists($f)) return [];
    return loadJsonFile($f, []);
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
    $data = loadJsonFile($cache_file, []);
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
        if (empty($lineage_cache[$tid]['lineage'])) continue;

        // The LAST entry is the taxon itself (rankedlineage's tax_name column), whatever its
        // rank -- ncbi_scan_local_dump() labels it 'species' even for a genus. An unnamed
        // species ("Girardia sp.") carries its GENUS id; until 2026-10-30 its last step matched
        // the genus node a named species had made, so no leaf was ever added and the organism
        // vanished from the tree (and the homepage selector) with only "Not in tree" to say so.
        $levels = $lineage_cache[$tid]['lineage'];
        $own    = array_pop($levels);
        $genus_level = strpos(trim($own['name']), ' ') === false;   // not a binomial: a genus id
        if ($genus_level) {
            $levels[] = ['rank' => 'genus', 'name' => $own['name']];
            $sp = trim((string)($data['species'] ?? ''));
            $leaf_name = trim(($data['genus'] ?? $own['name']) . ' ' . ($sp === '' || $sp === 'sp' ? 'sp.' : $sp));
        } else {
            $leaf_name = $own['name'];
        }

        $current = &$tree;
        foreach ($levels as $level) {
            $found = false;
            foreach ($current['children'] as &$child) {
                // Only an inner node can be walked into; a leaf of the same name is an organism.
                if ($child['name'] === $level['name'] && isset($child['children'])) {
                    $current = &$child;
                    $found = true;
                    break;
                }
            }
            unset($child);
            if (!$found) {
                $current['children'][] = ['name' => $level['name'], 'children' => []];
                $current = &$current['children'][count($current['children']) - 1];
            }
        }
        // Every organism gets its own leaf -- never merged into a node that happens to share
        // its name.
        $node = ['name' => $leaf_name, 'organism' => $org_name, 'common_name' => $data['common_name'] ?? ''];
        if (!empty($lineage_cache[$tid]['image'])) $node['image'] = $lineage_cache[$tid]['image'];
        $current['children'][] = $node;
        unset($current);
    }

    return ['tree' => $tree];
}

