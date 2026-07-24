<?php
/**
 * Extract/Search Common Helper Functions
 * 
 * Consolidates repeated logic from:
 * - tools/*.php (retrieve_sequences.php, retrieve_selected_sequences.php)
 * - tools/*.php (multi_organism_search.php, annotation_search_ajax.php)
 * 
 * Provides unified handling for:
 * - Multi-organism parameter parsing (multiple input formats)
 * - Context parameter extraction
 * - Sequence extraction and formatting
 * - File download orchestration
 * - Source list organization
 */

/**
 * Parse organism parameter from various sources and formats
 * 
 * Handles multiple input formats:
 * - Array from multi-search context (organisms[])
 * - Single organism from context parameters
 * - Comma-separated string
 * 
 * @param string|array $organisms_param - Raw parameter value
 * @param string $context_organism - Optional fallback organism
 * @return array - ['organisms' => [], 'string' => 'comma,separated,list']
 */
function parseOrganismParameter($organisms_param, $context_organism = '') {
    $filter_organisms = [];
    $filter_organisms_string = '';
    
    // First check for array (highest priority - from multi-search)
    if (is_array($organisms_param)) {
        $filter_organisms = array_filter($organisms_param);
        $filter_organisms_string = implode(',', $filter_organisms);
    } 
    // Then check for single organism context
    elseif (!empty($context_organism)) {
        $filter_organisms = [$context_organism];
        $filter_organisms_string = $context_organism;
    }
    // Finally try comma-separated string format
    else {
        $filter_organisms_string = trim($organisms_param);
        if (!empty($filter_organisms_string)) {
            $filter_organisms = array_map('trim', explode(',', $filter_organisms_string));
            $filter_organisms = array_filter($filter_organisms);
        }
    }
    
    return [
        'organisms' => $filter_organisms,
        'string' => $filter_organisms_string
    ];
}

/**
 * Extract context parameters from request
 * 
 * Checks explicit context_* fields first (highest priority), then regular fields as fallback
 * 
 * @return array - ['organism' => '', 'assembly' => '', 'group' => '', 'display_name' => '', 'context_page' => '']
 */
function parseContextParameters() {
    return [
        'organism'     => trim($_GET['context_organism'] ?? $_POST['context_organism'] ?? $_GET['organism']  ?? $_POST['organism']  ?? ''),
        'assembly'     => trim($_GET['context_assembly'] ?? $_POST['context_assembly'] ?? $_GET['assembly']  ?? $_POST['assembly']  ?? ''),
        'gene_set'     => trim($_GET['context_gene_set'] ?? $_POST['context_gene_set'] ?? $_GET['gene_set'] ?? $_POST['gene_set']  ?? ''),
        'group'        => trim($_GET['context_group']    ?? $_POST['context_group']    ?? $_GET['group']     ?? $_POST['group']     ?? ''),
        'display_name' => trim($_GET['display_name'] ?? $_POST['display_name'] ?? ''),
        'context_page' => trim($_GET['context_page']  ?? $_POST['context_page']  ?? ''),
    ];
}

/**
 * Parse and validate feature IDs from user input
 * 
 * Handles both comma and newline separated formats
 * Detects range patterns (ID:1..10, ID:1-10, ID 1..10, ID 1-10) and returns them separately
 * 
 * @param string $uniquenames_string - Comma or newline separated IDs with optional ranges
 * @return array - ['valid' => bool, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => bool, 'error' => '']
 */
function parseFeatureIds($uniquenames_string) {
    $uniquenames = [];
    $ranges = [];
    
    if (empty($uniquenames_string)) {
        return ['valid' => false, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => false, 'error' => 'No feature IDs provided'];
    }
    
    // Handle both comma and newline separated formats
    $entries = array_filter(array_map('trim', 
        preg_split('/[\n,]+/', $uniquenames_string)
    ));
    
    if (empty($entries)) {
        return ['valid' => false, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => false, 'error' => 'No valid feature IDs found'];
    }
    
    // Process each entry to detect range patterns
    // Patterns: "ID:1..10", "ID:1-10", "ID 1..10", "ID 1-10"
    foreach ($entries as $entry) {
        // Check for range patterns
        if (preg_match('/^(.+?)[\s:]+(\d+)[.\-]\.?(\d+)$/', $entry, $matches)) {
            $id = trim($matches[1]);
            $start = $matches[2];
            $end = $matches[3];
            
            // Validate range (start should be <= end)
            if ((int)$start <= (int)$end) {
                // Store in format expected by blastdbcmd: "ID:start-end"
                $ranges[] = "$id:$start-$end";
                $uniquenames[] = $id;
            } else {
                // Invalid range, skip or treat as error
                continue;
            }
        } else {
            // Regular ID without range
            $uniquenames[] = $entry;
        }
    }
    
    $uniquenames = array_unique($uniquenames);
    
    if (empty($uniquenames)) {
        return ['valid' => false, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => false, 'error' => 'No valid feature IDs found'];
    }
    
    return [
        'valid' => true, 
        'uniquenames' => array_values($uniquenames), 
        'ranges' => $ranges,
        'has_ranges' => !empty($ranges),
        'error' => ''
    ];
}

/**
 * Map a DB feature_type to the sequence_types config key used for FASTA routing.
 * Returns null for types that have no dedicated FASTA (gene, exon, pseudogene, etc.).
 */
function _fasta_key_for_type(string $type): ?string {
    static $map = [
        'mRNA'        => 'transcript',
        'transcript'  => 'transcript',
        'CDS'         => 'cds',
        'cds'         => 'cds',
        'protein'     => 'protein',
        'polypeptide' => 'protein',
    ];
    return $map[$type] ?? null;
}

/**
 * Batch-look up feature types from a SQLite DB for a list of uniquenames.
 *
 * @param array  $uniquenames  Feature uniquenames to resolve
 * @param string $db_path      Path to organism.sqlite
 * @return array [$uniquename => $feature_type|null]  null = not found in DB
 */
/**
 * Map feature uniquenames to their feature_type.
 *
 * $assembly and $gene_set are optional but SHOULD be passed whenever the caller knows
 * them. Without them this looks a uniquename up across the organism's entire database,
 * and a uniquename is only unique within a gene set — an organism carrying two gene sets
 * (or two assemblies) can return several rows for one name, whereupon the last row
 * silently wins and a feature can be typed from the wrong gene set. The sibling
 * buildTypedIdsForGenes() below has always scoped its lookups with `f.gene_set_id = ?`;
 * this one did not, which is the inconsistency that made the bug easy to miss.
 */
function buildTypedIds(array $uniquenames, string $db_path, string $assembly = '', string $gene_set = ''): array {
    $result = array_fill_keys($uniquenames, null);
    if (empty($uniquenames) || !file_exists($db_path)) {
        return $result;
    }
    $placeholders = implode(',', array_fill(0, count($uniquenames), '?'));
    $scoped = ($assembly !== '' || $gene_set !== '');

    $sql = "SELECT f.feature_uniquename, f.feature_type FROM feature f";
    if ($scoped) {
        $sql .= " JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id"
              . " JOIN genome   g  ON g.genome_id    = gs.genome_id";
    }
    $sql .= " WHERE f.feature_uniquename IN ($placeholders)";

    $params = array_values($uniquenames);
    if ($gene_set !== '') {
        $sql .= " AND gs.gene_set_name = ?";
        $params[] = $gene_set;
    }
    if ($assembly !== '') {
        // Accept either the accession or the human-readable genome name, as the rest of
        // the app does when it resolves an assembly.
        $sql .= " AND (g.genome_accession = ? OR g.genome_name = ?)";
        $params[] = $assembly;
        $params[] = $assembly;
    }

    try {
        $dbh  = getDbConnection($db_path);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['feature_uniquename']] = $row['feature_type'];
        }
    } catch (Exception $e) {
        // Return nulls on error — callers fall back to try-all behavior
    }
    return $result;
}

/**
 * Expand gene uniquenames to their extractable descendants (mRNA, CDS, protein).
 *
 * Moopmart queries at gene level but FASTAs are indexed by child IDs.
 * Two-level walk: genes → mRNA children → CDS/protein grandchildren.
 * Features already at an extractable type pass through unchanged.
 *
 * @param array  $gene_uniquenames  Gene-level uniquenames to expand
 * @param int    $gene_set_id       Scope expansion to this gene set
 * @param string $db_path           Path to organism.sqlite
 * @return array [$uniquename => $feature_type]
 */
/**
 * Expand selected features to every sequence type they have — mRNA, CDS and protein.
 *
 * extractSequencesForAllTypes() buckets each id by ITS OWN feature_type, so handing it a
 * list of mRNAs yields mRNA sequences and nothing else. The search-results FASTA button
 * did exactly that, which is why it returned only transcripts while the gene page and
 * Retrieve Sequences return all three.
 *
 * Everything is resolved through the mRNA layer, because that is where MOOP's hierarchy
 * branches: gene -> mRNA -> CDS -> protein. Whatever the user picked, we find the mRNA(s)
 * it belongs to and then walk down. Selecting a gene therefore yields all of its isoforms;
 * selecting one isoform yields only that isoform's CDS and protein, which is what someone
 * who deliberately picked a single transcript expects.
 *
 * Scoped by assembly / gene set when given — a feature_uniquename is unique only within a
 * gene set (see buildTypedIds()).
 *
 * @return array uniquename => feature_type, ready for extractSequencesForAllTypes()
 */
function expandFeaturesToAllSequenceTypes(
    array  $uniquenames,
    string $db_path,
    string $assembly = '',
    string $gene_set = ''
): array {
    if (empty($uniquenames) || !file_exists($db_path)) {
        return [];
    }

    try {
        $dbh = getDbConnection($db_path);

        // --- Selected features, scoped ------------------------------------------------
        $ph     = implode(',', array_fill(0, count($uniquenames), '?'));
        $sql    = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, f.parent_feature_id
                     FROM feature f";
        $params = array_values($uniquenames);
        if ($assembly !== '' || $gene_set !== '') {
            $sql .= " JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                      JOIN genome   g  ON g.genome_id    = gs.genome_id";
        }
        $sql .= " WHERE f.feature_uniquename IN ($ph)";
        if ($gene_set !== '') { $sql .= " AND gs.gene_set_name = ?"; $params[] = $gene_set; }
        if ($assembly !== '') {
            $sql .= " AND (g.genome_accession = ? OR g.genome_name = ?)";
            $params[] = $assembly; $params[] = $assembly;
        }
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $selected = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$selected) return [];

        $typed     = [];   // uniquename => feature_type (the answer)
        $mrna_ids  = [];   // feature_id of every mRNA we must walk down from

        // Fetch rows by feature_id, used to climb the tree.
        $byId = function (array $ids) use ($dbh) {
            if (!$ids) return [];
            $p = implode(',', array_fill(0, count($ids), '?'));
            $s = $dbh->prepare("SELECT feature_id, feature_uniquename, feature_type, parent_feature_id
                                  FROM feature WHERE feature_id IN ($p)");
            $s->execute(array_values($ids));
            return $s->fetchAll(PDO::FETCH_ASSOC);
        };
        // Fetch children of the given feature_ids, optionally restricted by type.
        $childrenOf = function (array $ids, array $types) use ($dbh) {
            if (!$ids) return [];
            $p  = implode(',', array_fill(0, count($ids), '?'));
            $tp = implode(',', array_fill(0, count($types), '?'));
            $s  = $dbh->prepare("SELECT feature_id, feature_uniquename, feature_type, parent_feature_id
                                   FROM feature
                                  WHERE parent_feature_id IN ($p) AND feature_type IN ($tp)");
            $s->execute(array_merge(array_values($ids), array_values($types)));
            return $s->fetchAll(PDO::FETCH_ASSOC);
        };

        $MRNA = ['mRNA', 'transcript'];
        $CDS  = ['cds', 'CDS'];
        $PROT = ['protein', 'polypeptide'];

        // --- Resolve everything selected to the mRNA layer ----------------------------
        $gene_ids = $cds_ids = $prot_ids = [];
        foreach ($selected as $row) {
            $type = $row['feature_type'];
            if (in_array($type, $MRNA, true)) {
                $mrna_ids[] = $row['feature_id'];
            } elseif (in_array($type, $CDS, true)) {
                $cds_ids[] = $row;
            } elseif (in_array($type, $PROT, true)) {
                $prot_ids[] = $row;
            } else {
                // gene (or anything else with mRNA children)
                $gene_ids[] = $row['feature_id'];
            }
        }
        // gene -> mRNA
        foreach ($childrenOf($gene_ids, $MRNA) as $m) {
            $mrna_ids[] = $m['feature_id'];
        }
        // CDS -> parent mRNA
        foreach ($cds_ids as $c) {
            if ($c['parent_feature_id']) $mrna_ids[] = $c['parent_feature_id'];
        }
        // protein -> parent CDS -> parent mRNA
        $prot_parent_ids = array_filter(array_column($prot_ids, 'parent_feature_id'));
        foreach ($byId($prot_parent_ids) as $c) {
            if ($c['parent_feature_id']) $mrna_ids[] = $c['parent_feature_id'];
        }

        $mrna_ids = array_values(array_unique(array_filter($mrna_ids)));
        if (!$mrna_ids) {
            // Nothing resolved to an mRNA — fall back to the plain typed lookup so the
            // caller still gets whatever the selected features are themselves.
            return buildTypedIds($uniquenames, $db_path, $assembly, $gene_set);
        }

        // --- Walk down: mRNA -> CDS -> protein -----------------------------------------
        foreach ($byId($mrna_ids) as $m) {
            $typed[$m['feature_uniquename']] = $m['feature_type'];
        }
        $cds_rows = $childrenOf($mrna_ids, $CDS);
        foreach ($cds_rows as $c) {
            $typed[$c['feature_uniquename']] = $c['feature_type'];
        }
        foreach ($childrenOf(array_column($cds_rows, 'feature_id'), $PROT) as $p) {
            $typed[$p['feature_uniquename']] = $p['feature_type'];
        }

        return $typed;

    } catch (Exception $e) {
        // Fall back to the unexpanded behaviour rather than returning nothing.
        return buildTypedIds($uniquenames, $db_path, $assembly, $gene_set);
    }
}

function buildTypedIdsForGenes(array $gene_uniquenames, int $gene_set_id, string $db_path): array {
    $result = [];
    if (empty($gene_uniquenames) || !file_exists($db_path)) return $result;

    $gp = implode(',', array_fill(0, count($gene_uniquenames), '?'));
    try {
        $dbh = getDbConnection($db_path);

        // Level 1: direct mRNA/transcript children of the genes
        $stmt = $dbh->prepare(
            "SELECT f.feature_uniquename, f.feature_type
               FROM feature f
               JOIN feature g ON f.parent_feature_id = g.feature_id
              WHERE g.feature_uniquename IN ($gp)
                AND f.gene_set_id = ?
                AND f.feature_type IN ('mRNA', 'transcript')"
        );
        $stmt->execute(array_merge(array_values($gene_uniquenames), [$gene_set_id]));
        $mrna_ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['feature_uniquename']] = $row['feature_type'];
            $mrna_ids[] = $row['feature_uniquename'];
        }

        // Level 2: CDS children of mRNAs
        $cds_ids = [];
        if (!empty($mrna_ids)) {
            $mp   = implode(',', array_fill(0, count($mrna_ids), '?'));
            $stmt = $dbh->prepare(
                "SELECT f.feature_uniquename, f.feature_type
                   FROM feature f
                   JOIN feature m ON f.parent_feature_id = m.feature_id
                  WHERE m.feature_uniquename IN ($mp)
                    AND f.gene_set_id = ?
                    AND f.feature_type IN ('cds', 'CDS')"
            );
            $stmt->execute(array_merge(array_values($mrna_ids), [$gene_set_id]));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[$row['feature_uniquename']] = $row['feature_type'];
                $cds_ids[] = $row['feature_uniquename'];
            }
        }

        // Level 3: protein children of CDS (gene → mRNA → cds → protein)
        if (!empty($cds_ids)) {
            $cp   = implode(',', array_fill(0, count($cds_ids), '?'));
            $stmt = $dbh->prepare(
                "SELECT f.feature_uniquename, f.feature_type
                   FROM feature f
                   JOIN feature c ON f.parent_feature_id = c.feature_id
                  WHERE c.feature_uniquename IN ($cp)
                    AND f.gene_set_id = ?
                    AND f.feature_type IN ('protein', 'polypeptide')"
            );
            $stmt->execute(array_merge(array_values($cds_ids), [$gene_set_id]));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[$row['feature_uniquename']] = $row['feature_type'];
            }
        }
    } catch (Exception $e) {
        // Return whatever we found; extractSequencesForAllTypes handles empty
    }
    return $result;
}

/**
 * Extract sequences using type-based FASTA routing.
 *
 * Each ID is routed to exactly one FASTA file based on its feature_type:
 *   mRNA/transcript → transcript.nt.fa
 *   CDS             → cds.nt.fa
 *   protein         → protein.aa.fa
 *   gene/exon/etc.  → skipped (no per-feature FASTA for these types)
 *
 * IDs with a null type (not found in DB) fall back to the old behavior:
 * they are tried against every applicable FASTA.
 *
 * @param string $assembly_dir       Path to the gene-set directory
 * @param array  $typed_ids          [$uniquename => $feature_type|null]
 * @param array  $sequence_types     Sequence type config from ConfigManager::getSequenceTypes()
 * @param string $organism           Passed through to blastdbcmd helper
 * @param string $assembly           Passed through to blastdbcmd helper
 * @param array  $ranges             Subsequence ranges ["ID:start-end", ...]
 * @param array  $original_input_ids Original IDs before expansion (for not-found reporting)
 * @param array  $parent_to_children Parent→children map for output grouping
 * @return array ['success' => bool, 'content' => [type => fasta_string], 'errors' => []]
 */
function extractSequencesForAllTypes(
    string $assembly_dir,
    array  $typed_ids,
    array  $sequence_types,
    string $organism = '',
    string $assembly = '',
    array  $ranges = [],
    array  $original_input_ids = [],
    array  $parent_to_children = []
): array {
    $displayed_content = [];
    $tool_errors       = [];

    // Sort IDs into per-FASTA buckets; unknowns collected separately
    $buckets = [];
    $untyped = [];
    foreach ($typed_ids as $uniquename => $feature_type) {
        if ($feature_type === null) {
            $untyped[] = $uniquename;
            continue;
        }
        $key = _fasta_key_for_type($feature_type);
        if ($key === null) continue; // gene, exon, etc. — no per-feature FASTA
        $buckets[$key][] = $uniquename;
    }

    $run_extract = function (string $seq_type, string $fasta_file, array $ids)
        use (&$displayed_content, &$tool_errors, $organism, $assembly, $ranges, $original_input_ids, $parent_to_children)
    {
        $result = extractSequencesFromBlastDb(
            $fasta_file, $ids, $organism, $assembly,
            $ranges, $original_input_ids, $parent_to_children
        );
        if ($result['success']) {
            $lines   = array_filter(explode("\n", $result['content']), fn($l) => trim($l) !== '');
            $content = implode("\n", $lines);
            if ($content !== '') {
                $displayed_content[$seq_type] = isset($displayed_content[$seq_type])
                    ? $displayed_content[$seq_type] . "\n" . $content
                    : $content;
            }
        } elseif (!empty($result['error'])) {
            $tool_errors[] = $result['error'];
        }
    };

    // Typed IDs → single FASTA each
    foreach ($sequence_types as $seq_type => $config) {
        if ($seq_type === 'genome') continue;
        if (empty($buckets[$seq_type])) continue;
        $files = glob("$assembly_dir/{$config['pattern']}");
        if (!empty($files)) {
            $run_extract($seq_type, $files[0], $buckets[$seq_type]);
        }
    }

    // Untyped IDs → try every FASTA (safe fallback for IDs not in DB)
    if (!empty($untyped)) {
        foreach ($sequence_types as $seq_type => $config) {
            if ($seq_type === 'genome') continue;
            $files = glob("$assembly_dir/{$config['pattern']}");
            if (!empty($files)) {
                $run_extract($seq_type, $files[0], $untyped);
            }
        }
    }

    $errors = empty($displayed_content) ? $tool_errors : [];
    return [
        'success' => !empty($displayed_content),
        'content' => $displayed_content,
        'errors'  => $errors,
    ];
}

/**
 * Format extracted sequences for display component
 * 
 * Converts extracted content into format expected by sequences_display.php
 * 
 * @param array $displayed_content - Extracted sequences by type
 * @param array $sequence_types - Type configurations (from site_config)
 * @return array - Formatted for sequences_display.php inclusion
 */
function formatSequenceResults($displayed_content, $sequence_types) {
    $available_sequences = [];
    
    foreach ($displayed_content as $seq_type => $content) {
        
        // Parse FASTA content into individual sequences by ID
        $sequences = [];
        if (!empty($content)) {
            $current_id = null;
            $current_seq = [];
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (strpos($line, '>') === 0) {
                    // Header line
                    if (!is_null($current_id)) {
                        // Store previous sequence with full FASTA format (including >)
                        $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
                    }
                    // Extract ID from header (remove leading '>')
                    $current_id = substr($line, 1);
                    $current_seq = [];
                } else if (!empty($line)) {
                    // Sequence line (skip empty lines)
                    $current_seq[] = $line;
                }
            }
            
            // Store last sequence with full FASTA format
            if (!is_null($current_id)) {
                $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
            }
        }
        
        foreach ($sequences as $id => $seq_content) {
            $first_line = explode("\n", $seq_content)[0];
        }
        
        $available_sequences[$seq_type] = [
            'label' => $sequence_types[$seq_type]['label'] ?? ucfirst($seq_type),
            'sequences' => $sequences
        ];
    }
    
    return $available_sequences;
}

/**
 * Send file download response and exit
 * 
 * Sets appropriate headers and outputs file content
 * Should be called before any HTML output
 * 
 * @param string $content - File content to download
 * @param string $sequence_type - Type of sequence (for filename)
 * @param string $file_format - Format (fasta or txt)
 */
function sendFileDownload($content, $sequence_type, $file_format = 'fasta') {
    $ext = ($file_format === 'txt') ? 'txt' : 'fasta';
    $filename = "sequences_{$sequence_type}_" . date("Y-m-d_His") . ".{$ext}";
    
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

/**
 * Build organism-filtered list of accessible assembly sources
 * 
 * Filters nested sources array by organism list
 * 
 * @param array $sources_by_group - Nested array from getAccessibleAssemblies()
 * @param array $filter_organisms - Optional organism filter list
 * @return array - Nested array [group][organism][...assemblies]
 */
function buildFilteredSourcesList($sources_by_group, $filter_organisms = []) {
    $filtered = [];
    
    foreach ($sources_by_group as $group_name => $organisms) {
        foreach ($organisms as $organism => $assemblies) {
            // Skip if organism filter is set and this organism is not in it
            if (!empty($filter_organisms) && !in_array($organism, $filter_organisms)) {
                continue;
            }
            
            if (!isset($filtered[$group_name])) {
                $filtered[$group_name] = [];
            }
            $filtered[$group_name][$organism] = $assemblies;
        }
    }
    
    return $filtered;
}

/**
 * Flatten nested sources array for sequential processing
 * 
 * Converts nested [group][organism][...sources] structure to flat list
 * Useful for iterating all sources without nested loops
 * 
 * @param array $sources_by_group - Nested array from getAccessibleAssemblies()
 * @return array - Flat list of all sources
 */
function flattenSourcesList($sources_by_group) {
    $accessible_sources = [];
    
    foreach ($sources_by_group as $group => $organisms) {
        foreach ($organisms as $org => $assemblies) {
            $accessible_sources = array_merge($accessible_sources, $assemblies);
        }
    }
    
    return $accessible_sources;
}

/**
 * Assign Bootstrap colors to groups for consistent UI display
 * 
 * Uses Bootstrap color palette cyclically across groups
 * Same group always gets same color (idempotent)
 * 
 * @param array $sources_by_group - Groups to assign colors to
 * @return array - [group_name => bootstrap_color]
 */
function assignGroupColors($sources_by_group) {
    $group_colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
    $group_color_map = [];
    
    foreach ($sources_by_group as $group_name => $organisms) {
        if (!isset($group_color_map[$group_name])) {
            $group_color_map[$group_name] = $group_colors[count($group_color_map) % count($group_colors)];
        }
    }
    
    return $group_color_map;
}

/**
 * Get available sequence types from all accessible sources
 * 
 * Scans assembly directories to determine which sequence types are available
 * Useful for populating UI dropdowns/display options
 * 
 * @param array $accessible_sources - Flattened list of sources
 * @param array $sequence_types - Type configurations (from site_config)
 * @return array - [type => label] for types that have available files
 */
function getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types) {
    $available_types = [];
    
    foreach ($accessible_sources as $source) {
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob($source['path'] . "/{$config['pattern']}");
            if (!empty($files)) {
                $available_types[$seq_type] = $config['label'];
            }
        }
    }
    
    return $available_types;
}

/**
 * Handle sequence download request
 * 
 * Checks for download flag and sends file if conditions are met.
 * Works with both array-based sequences (from extractSequencesFromFasta)
 * and string-based sequences (from extractSequencesForAllTypes).
 * 
 * @param bool $download_flag - Whether download was requested
 * @param string $sequence_type - The sequence type to download
 * @param array|string $sequence_data - Either array of sequences or a string
 * @return bool - True if download was sent and script exited, false otherwise
 */
function handleSequenceDownload($download_flag, $sequence_type, $sequence_data) {
    if (!$download_flag || empty($sequence_type)) {
        return false;
    }
    
    // Handle both formats: array (from extractSequencesFromFasta) and string (from extractSequencesForAllTypes)
    $fasta_content = '';
    if (is_array($sequence_data)) {
        // Array format: feature_id => content
        if (!empty($sequence_data)) {
            $fasta_content = implode("\n", $sequence_data);
        }
    } else if (is_string($sequence_data)) {
        // String format: already combined FASTA content
        $fasta_content = $sequence_data;
    }
    
    if (!empty($fasta_content)) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        sendFileDownload($fasta_content, $sequence_type, $file_format);
        exit;
    }
    
    return false;
}

/**
 * Determine selected source (organism/assembly) based on URL/POST parameters
 * 
 * Selection priority (highest to lowest):
 * 1. Explicit assembly parameter
 * 2. Explicit organism parameter
 * 3. Group parameter (select first organism from group)
 * 4. Organisms filter list (select first organism)
 * 
 * @param array $context - Context parameters [organism, assembly, group, display_name, context_page]
 * @param array $filter_organisms - Pre-filtered list of organisms (from organisms[] or group)
 * @param array $accessible_sources - Flat list of all accessible sources
 * @param string $selected_organism - Optional pre-selected organism (input/output)
 * @param string $selected_assembly_accession - Optional pre-selected assembly (input/output)
 * @return array - ['selected_source' => 'org|assembly', 'selected_organism' => 'org', 'selected_assembly_accession' => 'accession', 'selected_assembly_name' => 'name']
 */
function determineSelectedSource($context, $filter_organisms, $accessible_sources, $selected_organism = '', $selected_assembly_accession = '') {
    $result = [
        'selected_source' => '',
        'selected_organism' => $selected_organism,
        'selected_assembly_accession' => $selected_assembly_accession,
        'selected_assembly_name' => ''
    ];
    
    // Case 1: Both organism and assembly explicitly specified — find first matching gene_set
    if (!empty($selected_organism) && !empty($selected_assembly_accession)) {
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $selected_organism &&
                ($source['assembly'] === $selected_assembly_accession ||
                 $source['genome_accession'] === $selected_assembly_accession)) {
                $result['selected_source'] = $selected_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                return $result;
            }
        }
        // Fallback if source not found (no gene_set info available)
        $result['selected_source'] = $selected_organism . '|' . $selected_assembly_accession . '|';
        return $result;
    }

    // Case 2: Only organism specified (select its first assembly + gene_set)
    if (!empty($selected_organism)) {
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $selected_organism) {
                $result['selected_source'] = $selected_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }

    // Case 3: Group specified (select first organism from group, then its first assembly + gene_set)
    if (!empty($context['group']) && !empty($filter_organisms)) {
        $first_organism = reset($filter_organisms);
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $first_organism && in_array($context['group'], $source['groups'] ?? [])) {
                $result['selected_source'] = $first_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                $result['selected_organism'] = $first_organism;
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }

    // Case 4: Organisms filter list specified (select first organism, then its first assembly + gene_set)
    if (!empty($filter_organisms)) {
        $first_organism = reset($filter_organisms);
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $first_organism) {
                $result['selected_source'] = $first_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                $result['selected_organism'] = $first_organism;
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }
    
    // No selection could be determined
    return $result;
}

?>
