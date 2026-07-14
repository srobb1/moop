<?php
/**
 * Organism cache — the .organism_cache.json read/write layer + change fingerprints.
 * Extracted from functions_data.php (2026-07-07) as part of the code-review Phase 3
 * file split.
 *
 * getCachedOrganismsInfo() serves organism info from organisms/.organism_cache.json
 * when the per-organism + config fingerprints still match, else rescans and rewrites.
 * organism_cache_write_atomic() does the temp-file+rename write; buildPerOrganismFingerprints()
 * and buildConfigFingerprint() compute the mtime+size fingerprints used to detect change.
 *
 * Loaded via a require_once at the top of functions_data.php, so every existing include
 * of functions_data.php continues to expose these unchanged. getCachedOrganismsInfo()
 * calls scan/validation helpers that remain in functions_data.php; those resolve at call
 * time (both files are always loaded together).
 */

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
// v4: validateAssemblyDirectories() now also detects whole assembly directories on disk
// with no matching genome row ('orphaned_assembly_directory' mismatch type).
define('ORGANISM_CACHE_SCHEMA_VERSION', 4);

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
    $cache_file = moop_organism_cache_file();

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
        $cached = loadJsonFile($cache_file, []);
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
