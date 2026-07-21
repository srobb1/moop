<?php
/**
 * Database Query Builder Functions
 * Consolidates common SQL queries used across display and search tools
 * 
 * Purpose: DRY (Don't Repeat Yourself) database access layer
 * - Centralizes query patterns used in multiple files
 * - Makes it easier to update queries in one place
 * - Ensures consistent parameter handling and error checking
 * 
 * Includes:
 * - Feature queries (by ID, by uniquename, ancestors, children)
 * - Organism queries (info, features by organism)
 * - Assembly queries (info, statistics, FASTA files)
 * - Annotation queries (by feature, by organism)
 * - Search queries (annotation search, feature search)
 */

/**
 * Get feature data by feature_id
 * Returns complete feature information including organism and genome data
 * 
 * @param int $feature_id - Feature ID to retrieve
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results
 * @return array - Feature row with organism and genome info, or empty array
 */
function getFeatureById($feature_id, $dbFile, $gene_set_ids = []) {
    if (!empty($gene_set_ids)) {
        $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.parent_feature_id, f.gene_set_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name, gs.gene_set_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN gene_set gs ON f.gene_set_id = gs.gene_set_id
                  JOIN genome g ON gs.genome_id = g.genome_id
                  WHERE f.feature_id = ? AND f.gene_set_id IN ($placeholders)";
        $params = array_merge([$feature_id], $gene_set_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.parent_feature_id, f.gene_set_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name, gs.gene_set_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN gene_set gs ON f.gene_set_id = gs.gene_set_id
                  JOIN genome g ON gs.genome_id = g.genome_id
                  WHERE f.feature_id = ?";
        $params = [$feature_id];
    }
    
    $results = fetchData($query, $dbFile, $params);
    return !empty($results) ? $results[0] : [];
}

/**
 * Get feature data by feature_uniquename
 * Returns complete feature information including organism and genome data
 * 
 * @param string $feature_uniquename - Feature uniquename to retrieve
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results
 * @return array - Feature row with organism and genome info, or empty array
 */
function getFeatureByUniquename($feature_uniquename, $dbFile, $gene_set_ids = []) {
    if (!empty($gene_set_ids)) {
        $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.parent_feature_id, f.gene_set_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name, gs.gene_set_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN gene_set gs ON f.gene_set_id = gs.gene_set_id
                  JOIN genome g ON gs.genome_id = g.genome_id
                  WHERE f.feature_uniquename = ? AND f.gene_set_id IN ($placeholders)";
        $params = array_merge([$feature_uniquename], $gene_set_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.parent_feature_id, f.gene_set_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name, gs.gene_set_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN gene_set gs ON f.gene_set_id = gs.gene_set_id
                  JOIN genome g ON gs.genome_id = g.genome_id
                  WHERE f.feature_uniquename = ?";
        $params = [$feature_uniquename];
    }
    
    $results = fetchData($query, $dbFile, $params);
    return !empty($results) ? $results[0] : [];
}

/**
 * Get immediate children of a feature (not recursive)
 * Returns direct children only
 * 
 * @param int $parent_feature_id - Parent feature ID
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results
 * @return array - Array of child feature rows
 */
function getChildrenByFeatureId($parent_feature_id, $dbFile, $gene_set_ids = []) {
    if (!empty($gene_set_ids)) {
        $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.parent_feature_id = ? AND f.gene_set_id IN ($placeholders)";
        $params = array_merge([$parent_feature_id], $gene_set_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.parent_feature_id = ?";
        $params = [$parent_feature_id];
    }
    
    return fetchData($query, $dbFile, $params);
}

/**
 * Get immediate parent of a feature by ID
 * Returns minimal parent info for hierarchy traversal
 * 
 * @param int $feature_id - Feature ID to get parent of
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results
 * @return array - Parent feature row (minimal fields), or empty array
 */
function getParentFeature($feature_id, $dbFile, $gene_set_ids = []) {
    if (!empty($gene_set_ids)) {
        $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.feature_id = ? AND f.gene_set_id IN ($placeholders)";
        $params = array_merge([$feature_id], $gene_set_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.feature_id = ?";
        $params = [$feature_id];
    }
    
    $results = fetchData($query, $dbFile, $params);
    return !empty($results) ? $results[0] : [];
}

/**
 * Get all features of specific types in a genome
 * Useful for getting genes, mRNAs, or other feature types
 * 
 * @param string $feature_type - Feature type to retrieve (e.g., 'gene', 'mRNA')
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results
 * @return array - Array of features with specified type
 */
function getFeaturesByType($feature_type, $dbFile, $gene_set_ids = []) {
    if (!empty($gene_set_ids)) {
        $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.gene_set_id
                  FROM feature f
                  WHERE f.feature_type = ? AND f.gene_set_id IN ($placeholders)
                  ORDER BY f.feature_uniquename";
        $params = array_merge([$feature_type], $gene_set_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description,
                         f.feature_type, f.gene_set_id
                  FROM feature f
                  WHERE f.feature_type = ?
                  ORDER BY f.feature_uniquename";
        $params = [$feature_type];
    }
    
    return fetchData($query, $dbFile, $params);
}

/**
 * Search features by uniquename with optional organism filter
 * Used for quick feature lookup and search suggestions
 * 
 * @param string $search_term - Search term for feature uniquename (supports wildcards)
 * @param string $dbFile - Path to SQLite database
 * @param string $organism_name - Optional: Filter by organism name
 * @return array - Array of matching features
 */
function searchFeaturesByUniquename($search_term, $dbFile, $organism_name = '') {
    if ($organism_name) {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.organism_id, o.genus, o.species
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  WHERE f.feature_uniquename LIKE ? AND o.genus || ' ' || o.species LIKE ?
                  ORDER BY f.feature_uniquename
                  LIMIT 50";
        $params = ["%$search_term%", "%$organism_name%"];
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.organism_id, o.genus, o.species
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  WHERE f.feature_uniquename LIKE ?
                  ORDER BY f.feature_uniquename
                  LIMIT 50";
        $params = ["%$search_term%"];
    }
    
    return fetchData($query, $dbFile, $params);
}

/**
 * Get all annotations for a feature
 * Returns annotations with their sources and metadata
 * 
 * @param int $feature_id - Feature ID to get annotations for
 * @param string $dbFile - Path to SQLite database
 * @return array - Array of annotation records
 */
function getAnnotationsByFeature($feature_id, $dbFile) {
    $query = "SELECT a.annotation_id, a.annotation_accession, a.annotation_description, 
                     ans.annotation_source_name, ans.annotation_source_id,
                     fa.score, fa.date, fa.additional_info
              FROM annotation a
              JOIN feature_annotation fa ON a.annotation_id = fa.annotation_id
              JOIN annotation_source ans ON a.annotation_source_id = ans.annotation_source_id
              WHERE fa.feature_id = ?
              ORDER BY fa.date DESC";
    
    return fetchData($query, $dbFile, [$feature_id]);
}

/**
 * Get organism information
 * Returns complete organism record with taxonomic data
 * 
 * @param string $organism_name - Organism name (genus + species)
 * @param string $dbFile - Path to SQLite database
 * @return array - Organism record, or empty array if not found
 */
function getOrganismInfo($organism_name, $dbFile) {
    $query = "SELECT organism_id, genus, species, common_name, subtype, taxon_id
              FROM organism
              WHERE (genus || ' ' || species = ? OR common_name = ?)
              LIMIT 1";
    
    $results = fetchData($query, [$organism_name, $organism_name], $dbFile);
    return !empty($results) ? $results[0] : [];
}

/**
 * Get assembly/genome statistics
 * Returns feature counts and metadata for an assembly
 * 
 * @param string $genome_accession - Genome/assembly accession
 * @param string $dbFile - Path to SQLite database
 * @return array - Genome record with feature counts, or empty array
 */
function getAssemblyStats($genome_id_param, $dbFile) {
    $query = "SELECT g.genome_id, g.genome_accession, g.genome_name,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id END) as gene_count,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) as mrna_count,
                     COUNT(DISTINCT f.feature_id) as total_features
              FROM genome g
              LEFT JOIN gene_set gs ON gs.genome_id = g.genome_id
              LEFT JOIN feature f ON f.gene_set_id = gs.gene_set_id
              WHERE g.genome_accession = ? OR g.genome_name = ?
              GROUP BY g.genome_id";
    
    $results = fetchData($query, $dbFile, [$genome_id_param, $genome_id_param]);
    return !empty($results) ? $results[0] : [];
}

/**
 * Get stats for a single gene set (gene/mRNA counts)
 *
 * @param string $assembly      - genome_accession or genome_name
 * @param string $gene_set_name - gene_set_name
 * @param string $dbFile        - path to organism.sqlite
 * @return array - gene_set record with feature counts, or empty array
 */
function getGeneSetStats($assembly, $gene_set_name, $dbFile) {
    $query = "SELECT gs.gene_set_id, gs.gene_set_name, gs.gene_set_description,
                     g.genome_id, g.genome_accession, g.genome_name,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id END) as gene_count,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) as mrna_count,
                     COUNT(DISTINCT f.feature_id) as total_features
              FROM gene_set gs
              JOIN genome g ON gs.genome_id = g.genome_id
              LEFT JOIN feature f ON f.gene_set_id = gs.gene_set_id
              WHERE (g.genome_accession = ? OR g.genome_name = ?)
              AND gs.gene_set_name = ?
              GROUP BY gs.gene_set_id";
    $results = fetchData($query, $dbFile, [$assembly, $assembly, $gene_set_name]);
    return !empty($results) ? $results[0] : [];
}

/**
 * Get all gene sets for an assembly with per-gene-set feature counts
 *
 * @param string $assembly - genome_accession or genome_name
 * @param string $dbFile   - path to organism.sqlite
 * @return array - array of gene_set rows, each with gene_count and mrna_count
 */
function getAssemblyGeneSets($assembly, $dbFile) {
    $query = "SELECT gs.gene_set_id, gs.gene_set_name, gs.gene_set_description,
                     g.genome_accession, g.genome_name,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id END) as gene_count,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) as mrna_count
              FROM gene_set gs
              JOIN genome g ON gs.genome_id = g.genome_id
              LEFT JOIN feature f ON f.gene_set_id = gs.gene_set_id
              WHERE (g.genome_accession = ? OR g.genome_name = ?)
              GROUP BY gs.gene_set_id
              ORDER BY gs.gene_set_name";
    return fetchData($query, $dbFile, [$assembly, $assembly]) ?: [];
}

/**
 * Build a safe SQLite FTS5 MATCH expression from already-sanitized search input.
 *
 * Keyword mode: every whitespace-separated term becomes a quoted prefix token
 *   ("term"*) and the terms are AND-ed, so all must appear somewhere in the
 *   feature's indexed text (any order). Prefix matching lets a query like "wnt"
 *   reach the gene "wnt8b"; it is NOT substring matching ("inase" never matches
 *   "kinase"). Quoting each term neutralises FTS5 operators (AND, OR, NEAR, the
 *   prefix star, column filters) that a user might type, so they stay literal text.
 * Quoted mode: the whole input is one exact phrase query ("zinc finger").
 *
 * The index tokenizer is 'porter unicode61' (see build_fts_index.sql), so matching
 * is also case/accent-insensitive with English stemming (binding ~ binds ~ bound).
 *
 * Returns '' when nothing searchable remains — the caller treats that as no results.
 */
function buildFtsMatchExpr($search_term, $is_quoted_search) {
    // Wrap a token/phrase as an FTS5 string literal ("" escapes an embedded quote).
    $as_fts_string = function ($s) { return '"' . str_replace('"', '""', $s) . '"'; };

    if ($is_quoted_search) {
        if (!preg_match('/[\p{L}\p{N}]/u', $search_term)) return '';
        return $as_fts_string($search_term);
    }

    $exprs = [];
    foreach (preg_split('/\s+/', trim($search_term)) as $term) {
        if ($term === '' || !preg_match('/[\p{L}\p{N}]/u', $term)) continue;
        $exprs[] = $as_fts_string($term) . '*';   // prefix query, e.g. "wnt8b"*
    }
    return implode(' AND ', $exprs);
}

/**
 * The first search term, used for the "gene named with the term" ranking tier.
 * (A gene whose feature_name contains this term is floated to the top of results.)
 */
function ftsPrimaryTerm($search_term, $is_quoted_search) {
    if ($is_quoted_search) return trim($search_term);
    foreach (preg_split('/\s+/', trim($search_term)) as $term) {
        if ($term !== '') return $term;
    }
    return '';
}

/**
 * Append the assembly / gene-set scope filters shared by both FTS search paths.
 * scope_pairs (list of {assembly, gene_set}) overrides the single assembly/gene_set.
 */
function appendScopeFilters(&$sql, &$params, $assembly_accession, $gene_set_name, $scope_pairs) {
    if (!empty($scope_pairs)) {
        $clauses = array_fill(0, count($scope_pairs), '(g.genome_accession = ? AND gs.gene_set_name = ?)');
        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
        foreach ($scope_pairs as $pair) {
            $params[] = $pair['assembly'];
            $params[] = $pair['gene_set'];
        }
    } else {
        if (!empty($assembly_accession)) { $sql .= ' AND g.genome_accession = ?'; $params[] = $assembly_accession; }
        if (!empty($gene_set_name))      { $sql .= ' AND gs.gene_set_name = ?';   $params[] = $gene_set_name; }
    }
}

/**
 * Execute a prepared FTS search and apply the shared 2,500-row cap + warning.
 *
 * The SQL is expected to SELECT one extra row than the cap (LIMIT 2501) so we can
 * detect "more results exist". Unlike the old LIKE path, DB errors are surfaced
 * (and logged) instead of being silently swallowed — a missing FTS index (an
 * organism.sqlite built without build_fts_index.sql) is reported clearly rather
 * than crashing the whole cross-organism search.
 */
function runFtsSearch($dbFile, $sql, $params) {
    $max_display = 2500;
    try {
        $dbh  = getDbConnection($dbFile);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbh  = null;
    } catch (PDOException $e) {
        error_log('FTS search failed for ' . $dbFile . ': ' . $e->getMessage());
        $missing_index = stripos($e->getMessage(), 'no such table') !== false;
        return [
            'results' => [],
            'capped'  => false,
            'warning' => $missing_index
                ? 'Search index not built for this organism yet.'
                : 'Search error.',
        ];
    }

    if (count($rows) > $max_display) {
        return [
            'results' => array_slice($rows, 0, $max_display),
            'capped'  => true,
            'warning' => '2,500+ results found. Use Advanced Filter or add more search terms to refine.',
        ];
    }
    return ['results' => $rows, 'capped' => false, 'warning' => null];
}

/**
 * Search features by name and description only (gene-centric) — no annotation join.
 * Used when the user has explicitly deselected all annotation sources. Backed by the
 * feature_search FTS index, which covers EVERY feature (including unannotated ones).
 * Returns rows in the same column shape as searchFeaturesAndAnnotations (annotation
 * columns NULL) so the result-formatting code in the AJAX endpoint is unchanged.
 */
function searchFeaturesByNameDescription($search_term, $is_quoted_search, $dbFile, $assembly_accession = '', $gene_set_name = '', $scope_pairs = []) {
    $match = buildFtsMatchExpr($search_term, $is_quoted_search);
    if ($match === '') return ['results' => [], 'capped' => false, 'warning' => null];

    $name_like = '%' . ftsPrimaryTerm($search_term, $is_quoted_search) . '%';

    $sql = "SELECT f.feature_uniquename, f.feature_name, f.feature_description,
                   NULL AS annotation_accession, NULL AS annotation_description,
                   NULL AS score, NULL AS date, NULL AS annotation_source_name,
                   o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                   g.genome_accession,
                   (f.feature_name LIKE ?) AS name_match
            FROM feature_search fs
            JOIN feature   f  ON f.feature_id   = fs.rowid
            JOIN gene_set  gs ON gs.gene_set_id = f.gene_set_id
            JOIN genome    g  ON g.genome_id    = gs.genome_id
            JOIN organism  o  ON o.organism_id  = f.organism_id
            WHERE feature_search MATCH ?";
    $params = [$name_like, $match];

    appendScopeFilters($sql, $params, $assembly_accession, $gene_set_name, $scope_pairs);

    // Named genes first (hard tier), then bm25 relevance (name col weighted 10, desc 5),
    // then a stable id tiebreak. bm25 must stay in ORDER BY only (it errors if projected).
    $sql .= " ORDER BY name_match DESC,
                       bm25(feature_search, 10.0, 5.0),
                       f.feature_uniquename
              LIMIT 2501";

    return runFtsSearch($dbFile, $sql, $params);
}

/**
 * Search features and annotations by keyword or quoted phrase (the main search).
 * Used by annotation_search_ajax.php. Backed by the feature_annotation_search FTS
 * index (one row per feature×annotation pair). Returns feature×annotation rows; the
 * frontend groups them per gene client-side, so the row shape must not change.
 *
 * @param string $search_term        Search term or phrase (already sanitized)
 * @param bool   $is_quoted_search   Treat input as one exact phrase
 * @param string $dbFile             Path to organism.sqlite
 * @param array  $source_names       Optional annotation_source_name filter (IN list)
 * @param string $assembly_accession Optional single-assembly scope
 * @param string $gene_set_name      Optional single-gene-set scope
 * @param array  $scope_pairs        Optional [{assembly, gene_set}] scope (overrides above)
 * @return array ['results' => rows, 'capped' => bool, 'warning' => string|null]
 */
function searchFeaturesAndAnnotations($search_term, $is_quoted_search, $dbFile, $source_names = [], $assembly_accession = '', $gene_set_name = '', $scope_pairs = []) {
    $match = buildFtsMatchExpr($search_term, $is_quoted_search);
    if ($match === '') return ['results' => [], 'capped' => false, 'warning' => null];

    $name_like = '%' . ftsPrimaryTerm($search_term, $is_quoted_search) . '%';

    $sql = "SELECT f.feature_uniquename, f.feature_name, f.feature_description,
                   a.annotation_accession, a.annotation_description,
                   fa.score, fa.date, ans.annotation_source_name,
                   o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                   g.genome_accession,
                   (f.feature_name LIKE ?) AS name_match
            FROM feature_annotation_search fas
            JOIN feature_annotation  fa  ON fa.feature_annotation_id = fas.rowid
            JOIN feature             f   ON f.feature_id             = fa.feature_id
            JOIN annotation          a   ON a.annotation_id          = fa.annotation_id
            JOIN annotation_source   ans ON ans.annotation_source_id = a.annotation_source_id
            JOIN organism            o   ON o.organism_id            = f.organism_id
            JOIN gene_set            gs  ON gs.gene_set_id           = f.gene_set_id
            JOIN genome              g   ON g.genome_id              = gs.genome_id
            WHERE feature_annotation_search MATCH ?";
    $params = [$name_like, $match];

    appendScopeFilters($sql, $params, $assembly_accession, $gene_set_name, $scope_pairs);

    if (!empty($source_names)) {
        $placeholders = implode(',', array_fill(0, count($source_names), '?'));
        $sql .= " AND ans.annotation_source_name IN ($placeholders)";
        foreach ($source_names as $s) { $params[] = $s; }
    }

    // Named genes first (hard tier); then bm25 relevance weighting name:10, feature_desc:5,
    // annotation_desc:2, annotation_accession:3; then a stable id tiebreak. bm25 must stay
    // in ORDER BY only (it errors if projected as a column or wrapped in an aggregate).
    $sql .= " ORDER BY name_match DESC,
                       bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0),
                       f.feature_uniquename
              LIMIT 2501";

    return runFtsSearch($dbFile, $sql, $params);
}

/**
 * Search features by uniquename (primary search)
 * Returns only features, not annotations
 * Used as fast path before annotation search
 * 
 * @param string $search_term - Search term for uniquename
 * @param string $dbFile - Path to SQLite database
 * @param string $organism_name - Optional: Filter by organism
 * @return array - Array of matching features
 */
function searchFeaturesByUniquenameForSearch($search_term, $dbFile, $organism_name = '', $assembly_accession = '', $gene_set_name = '', $scope_pairs = []) {
    if ($organism_name) {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM feature f, organism o, gene_set gs, genome g
                  WHERE f.organism_id = o.organism_id
                    AND f.gene_set_id = gs.gene_set_id
                    AND gs.genome_id = g.genome_id
                    AND f.feature_uniquename LIKE ? 
                    AND (o.genus || ' ' || o.species = ?)";
        $params = ["%$search_term%", $organism_name];
        
        // scope_pairs overrides individual assembly/gene_set filters
        if (!empty($scope_pairs)) {
            $clauses = array_fill(0, count($scope_pairs), '(g.genome_accession = ? AND gs.gene_set_name = ?)');
            $query .= " AND (" . implode(' OR ', $clauses) . ")";
            foreach ($scope_pairs as $pair) {
                $params[] = $pair['assembly'];
                $params[] = $pair['gene_set'];
            }
        } else {
            if (!empty($assembly_accession)) {
                $query .= " AND g.genome_accession = ?";
                $params[] = $assembly_accession;
            }
            if (!empty($gene_set_name)) {
                $query .= " AND gs.gene_set_name = ?";
                $params[] = $gene_set_name;
            }
        }

        $query .= " ORDER BY f.feature_uniquename";
    } else {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description,
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM feature f, organism o, gene_set gs, genome g
                  WHERE f.organism_id = o.organism_id
                    AND f.gene_set_id = gs.gene_set_id
                    AND gs.genome_id = g.genome_id
                    AND f.feature_uniquename LIKE ?";
        $params = ["%$search_term%"];

        // scope_pairs overrides individual assembly/gene_set filters
        if (!empty($scope_pairs)) {
            $clauses = array_fill(0, count($scope_pairs), '(g.genome_accession = ? AND gs.gene_set_name = ?)');
            $query .= " AND (" . implode(' OR ', $clauses) . ")";
            foreach ($scope_pairs as $pair) {
                $params[] = $pair['assembly'];
                $params[] = $pair['gene_set'];
            }
        } else {
            if (!empty($assembly_accession)) {
                $query .= " AND g.genome_accession = ?";
                $params[] = $assembly_accession;
            }
            if (!empty($gene_set_name)) {
                $query .= " AND gs.gene_set_name = ?";
                $params[] = $gene_set_name;
            }
        }

        $query .= " ORDER BY f.feature_uniquename";
    }

    return fetchData($query, $dbFile, $params);
}


/**
 * Get all annotation sources for an organism with counts
 * Used to populate search help/tutorial
 * 
 * @param string $dbFile - Path to SQLite database
 * @return array - Array of sources with name and count
 */
function getAnnotationSources($dbFile) {
    try {
        $query = "SELECT DISTINCT 
                         ans.annotation_source_name as name,
                         COUNT(a.annotation_id) as count
                  FROM annotation_source ans
                  LEFT JOIN annotation a ON ans.annotation_source_id = a.annotation_source_id
                  GROUP BY ans.annotation_source_id
                  ORDER BY count DESC";
        
        return fetchData($query, $dbFile, []);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get annotation sources grouped by type
 * Used to populate advanced search filter modal
 * 
 * @param string $dbFile - Path to SQLite database
 * @return array - Grouped sources: {type: [{name, count}, ...], ...}
 */
function getAnnotationSourcesByType($dbFile) {
    try {
        // Get all sources with their annotation types from the database
        $query = "SELECT 
                    ans.annotation_source_name as name,
                    ans.annotation_type as type,
                    COUNT(a.annotation_id) as count
                  FROM annotation_source ans
                  LEFT JOIN annotation a ON ans.annotation_source_id = a.annotation_source_id
                  GROUP BY ans.annotation_source_id, ans.annotation_type
                  ORDER BY ans.annotation_type, COUNT(a.annotation_id) DESC";
        
        $sources_with_types = fetchData($query, $dbFile, []);
        
        // Group by annotation_type
        $grouped = [];
        foreach ($sources_with_types as $source) {
            $type = $source['type'];
            
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            
            $grouped[$type][] = [
                'name' => $source['name'],
                'count' => $source['count']
            ];
        }
        
        // Load annotation config to get proper ordering
        global $config;
        $metadata_path = $config->getPath('metadata_path');
        $config_file = "$metadata_path/annotation_config.json";
        $annotation_config = loadJsonFile($config_file, []);
        
        // Use annotation_type_order from config if available
        $sorted = [];
        if (!empty($annotation_config['annotation_type_order'])) {
            // Add types in the order defined in config
            foreach ($annotation_config['annotation_type_order'] as $type) {
                if (isset($grouped[$type])) {
                    $sorted[$type] = $grouped[$type];
                }
            }
        }
        
        // Add any remaining types not in the config order (in case of dynamic types)
        foreach ($grouped as $type => $sources) {
            if (!isset($sorted[$type])) {
                $sorted[$type] = $sources;
            }
        }
        
        return $sorted;
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all annotation types from database with their counts and feature counts
 * Queries annotation_source and feature_annotation tables for:
 *   - Distinct annotation_type values
 *   - Count of annotations per type
 *   - Count of distinct features per type
 * 
 * @param string $dbFile - Path to SQLite database
 * @return array - [annotation_type => ['annotation_count' => N, 'feature_count' => M]]
 *                  ordered by feature_count DESC
 */
function getAnnotationTypesFromDB($dbFile) {
    try {
        // TRIM the type. It is a GROUPING KEY — whatever distinct values come back here
        // become the annotation types the whole site knows about — so a stray space in one
        // loaded row silently invents a whole extra type. That happened: one source row in
        // Chamaeleo_calyptratus stored "Gene Ontology " and Manage Annotations grew a second,
        // near-empty Gene Ontology card beside the real one.
        //
        // Trimming and grouping on the trimmed value fixes it for every organism at once and
        // makes the class impossible, rather than relying on every future load being careful.
        // A sweep at the time found 1,207 rows across 71 of 85 organisms already carrying
        // leading/trailing whitespace in annotation_source_name, so that care demonstrably
        // is not reliable; it only escaped notice there because a display name does not group
        // anything. COUNTs are summed across rows that differ only by whitespace.
        $query = "SELECT TRIM(ans.annotation_type) AS annotation_type,
                         COUNT(DISTINCT a.annotation_id) as annotation_count,
                         COUNT(DISTINCT fa.feature_id) as feature_count
                  FROM annotation_source ans
                  LEFT JOIN annotation a ON ans.annotation_source_id = a.annotation_source_id
                  LEFT JOIN feature_annotation fa ON a.annotation_id = fa.annotation_id
                  WHERE ans.annotation_type IS NOT NULL AND TRIM(ans.annotation_type) != ''
                  GROUP BY TRIM(ans.annotation_type)
                  ORDER BY feature_count DESC, TRIM(ans.annotation_type) ASC";

        $results = fetchData($query, $dbFile, []);

        $types = [];
        foreach ($results as $row) {
            // Defensive: trim again in PHP so a database without TRIM() support, or a value
            // carrying a non-space whitespace character, still cannot create a phantom type.
            $type = trim((string)$row['annotation_type']);
            if ($type === '') continue;
            $types[$type] = [
                'annotation_count' => (int)$row['annotation_count'],
                'feature_count' => (int)$row['feature_count']
            ];
        }
        
        return $types;
    } catch (Exception $e) {
        error_log("Error getting annotation types from DB: " . $e->getMessage());
        return [];
    }
}
?>
