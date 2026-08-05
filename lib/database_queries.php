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

/*
 * REMOVED 2026-07-27: getAnnotationsByFeature() and getOrganismInfo().
 *
 * Both were dead (no callers anywhere; only referenced in the generated
 * docs/function_registry.json) AND both were broken, so neither could have been called
 * without taking the request down:
 *
 *   getAnnotationsByFeature()  selected fa.additional_info -- a column that exists in no
 *                             version of create_schema_sqlite.sql, past or present. The
 *                             query throws, and fetchData() turns that into die().
 *
 *   getOrganismInfo()          called fetchData($query, [$organism_name, $organism_name],
 *                             $dbFile) -- the signature is (sql, dbFile, params), so it
 *                             passed an array where the database path belongs. PDO would
 *                             be handed "Array" as a filename.
 *
 * Deleted rather than repaired: nothing wants them, and a broken helper that looks usable
 * is worse than no helper. Annotations for a feature are served by
 * getAllAnnotationsForFeatures() in parent_functions.php, which batches by feature id.
 */

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
 * is also case/accent-insensitive with English stemming (binding ~ binds ~ bind).
 *
 * NOT "~ bound". Porter is pure SUFFIX-STRIPPING with no dictionary and no irregular
 * forms, verified against the tokenizer: `bound` matches only `bound`, `ran` only `ran`,
 * `mice` only `mice`. Worth stating because the old example implied otherwise, and because
 * the guarantee it gives is useful — every collapse porter performs preserves a common
 * prefix, which is what lets the results table locate a stem match by trimming the typed
 * term rather than re-implementing porter in JavaScript (js/modules/search-terms.js).
 *
 * Returns '' when nothing searchable remains — the caller treats that as no results.
 */
/**
 * Is this search input worth running? Mirrors js/modules/search-terms.js.
 *
 * THE RULE: at least one term of 2+ characters. Per-term, NOT total input length.
 *
 * Terms are AND-ed into a prefix query below, so a short term alongside others is
 * constrained by them; a short term alone is not. Measured on Myotis_myotis:
 *
 *   "1"*                                      1,855,918 rows   <- alone: useless
 *   "histone"* AND "deacetylase"*                 9,258 rows
 *   "histone"* AND "deacetylase"* AND "1"*        4,254 rows   <- keeping it HELPS
 *
 * So short terms are kept, not dropped -- dropping them was costing precision. What must
 * be refused is a query with nothing selective in it at all: `a` matches 1,164,973 rows
 * against a 2,500 cap, so the user gets 2,500 arbitrary rows.
 *
 * The previous gate tested total length >= 3 in TWO JavaScript files and nothing here.
 * That blocked real two-character gene symbols outright -- AR, C3, F8 (haemophilia A), CS,
 * PC, SI -- with no search run and no explanation, while `histone deacetylase 1` passed
 * only because the whole string is long.
 *
 * This copy is the load-bearing one. The JS gives a fast, specific message, but a
 * hand-made request bypasses it entirely, and a lone `1` fanned out across selected
 * organisms is a 1.86M-row scan per database against a 32 ms disk.
 */
function moop_search_input_is_usable($search_term, $is_quoted_search) {
    $trimmed = trim((string)$search_term);
    if ($trimmed === '') return false;

    // Quoted input is ONE phrase, so its length is the phrase's length.
    $terms = $is_quoted_search
        ? [$trimmed]
        : preg_split('/\s+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($terms as $term) {
        if (!preg_match('/[\p{L}\p{N}]/u', $term)) continue;
        if (mb_strlen($term) >= 2) return true;
    }
    return false;
}

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
 * The porter stem of one term, as SQLite's OWN tokenizer computes it.
 *
 * Asked of SQLite rather than reimplemented in PHP on purpose: the stem has to agree with
 * what the index actually stored, and a hand-written porter would have to be kept in sync
 * with SQLite's forever (js/modules/search-terms.js declines the same job for the same
 * reason). An in-memory database, so it touches no organism file and needs no write access.
 *
 * Returns '' when the term is not a single word, when stemming is unavailable, or when the
 * stem is not a prefix of the term. Every caller treats '' as "skip the stem tier", so a
 * failure here degrades to the previous ranking rather than breaking search.
 *
 * THE PREFIX GUARANTEE. SQLite's porter only ever TRUNCATES -- verified against the
 * tokenizer across the suffix-replacing cases the textbook algorithm rewrites:
 *
 *   relational -> relat   (not "relate")     electricity -> electr
 *   digitizer  -> digit                      vietnamization -> vietnam
 *   transposases -> transposas               transposons -> transposon
 *
 * so the stem is always a leading substring of the typed word. The check below enforces
 * that rather than trusting it, because the whole point of the stem tier is that
 * '%stem%' is a LOOSER pattern than '%term%' -- if some future tokenizer returned a stem
 * that were not a prefix, the tier would silently rank on an unrelated string.
 */
function moop_porter_stem($term) {
    static $cache = [];
    static $pdo   = null;

    $term = trim((string) $term);
    if ($term === '') return '';
    if (array_key_exists($term, $cache)) return $cache[$term];

    // One word only. A term the tokenizer would split (punctuation, hyphens) has no single
    // stem, and concatenating the pieces would invent a string that is in no document.
    if (!preg_match('/^[\p{L}\p{N}]+$/u', $term)) return $cache[$term] = '';

    try {
        if ($pdo === null) {
            $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE VIRTUAL TABLE stemmer USING fts5(w, tokenize='porter unicode61')");
            $pdo->exec("CREATE VIRTUAL TABLE stemvocab USING fts5vocab(stemmer, row)");
        }
        $pdo->exec('DELETE FROM stemmer');
        $ins = $pdo->prepare('INSERT INTO stemmer(w) VALUES (?)');
        $ins->execute([$term]);
        $stem = $pdo->query('SELECT term FROM stemvocab')->fetchColumn();
    } catch (Throwable $e) {
        // No fts5, or the tokenizer changed shape. Ranking falls back; search still works.
        return $cache[$term] = '';
    }

    if (!is_string($stem) || $stem === '') return $cache[$term] = '';

    $term_lc = mb_strtolower($term);
    if (strpos($term_lc, $stem) !== 0) return $cache[$term] = '';   // see prefix guarantee

    return $cache[$term] = $stem;
}

/**
 * The LIKE pattern for the stem-match ranking tier, or '' when that tier does not apply.
 *
 * WHY THIS TIER EXISTS. FTS5 matched on the stem, but the literal tier above it tests the
 * word the user TYPED, so a plural search credits only the rows carrying that same plural
 * -- and in this data the singular is far more common. Measured on
 * Craseonycteris_thonglongyai, "transposons": 234 matching rows, of which just 2 contain
 * "transposons" but 125 contain "transposon". Two rows therefore decided the whole top of
 * the list and everything below them fell back to bm25, which put six annotations of one
 * unnamed gene -- "Homeodomain-like", "consensus disorder prediction", "-" -- above MAEL,
 * whose description reads "maelstrom spermatogenic transposon silencer". Same shape for
 * "transposases" (27 of 517 -> 336) and "receptors" (7,155 of 146,173 -> 57,673).
 *
 * WHY IT IS ADDED BENEATH THE LITERAL TIER RATHER THAN REPLACING IT. The literal tier is
 * load-bearing: it is what fixed the porter precision bug, where "transpos" stems to
 * "transpo" and so prefix-matches TRANSPORT. Widening that tier to the stem would hand
 * that back. Keeping the exact tier first cannot: for "transpos" the 1,326 rows containing
 * it still sort above the 64,725 the stem reaches, so the top 100 is untouched --
 * confirmed 100/100 relevant before and after. "kinase" is unaffected by construction
 * (36,434 rows match '%kinase%' and '%kinas%' alike).
 *
 * Quoted phrases are deliberately excluded: a phrase has no single stem, and phrase search
 * is the tool a user reaches for when they want exactness.
 */
function ftsStemLikePattern($search_term, $is_quoted_search) {
    if ($is_quoted_search) return '';

    $primary = ftsPrimaryTerm($search_term, $is_quoted_search);
    if ($primary === '') return '';

    $stem = moop_porter_stem($primary);
    // Nothing to add when the typed word IS its own stem -- the tier would duplicate the
    // literal one above it and cost a second LIKE per row for an identical answer.
    if ($stem === '' || $stem === mb_strtolower($primary)) return '';

    return '%' . $stem . '%';
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
 * Execute a prepared FTS search and apply the shared row cap + warning.
 *
 * The SQL is expected to SELECT one extra row than the cap (moop_search_query_limit())
 * so we can detect "more results exist". Unlike the old LIKE path, DB errors are
 * surfaced (and logged) instead of being silently swallowed — a missing FTS index (an
 * organism.sqlite built without build_fts_index.sql) is reported clearly rather
 * than crashing the whole cross-organism search.
 */
function runFtsSearch($dbFile, $sql, $params) {
    $max_display = moop_search_results_limit();
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
            'warning' => number_format($max_display) . '+ results found. Use Advanced Filter or add more search terms to refine.',
        ];
    }
    return ['results' => $rows, 'capped' => false, 'warning' => null];
}

/**
 * The feature levels that actually carry annotations in ONE database.
 *
 * Returns e.g. ['mRNA'], or ['gene'] for Bradyrhizobium, or ['transcript'] for the two
 * Schmidtea that use it. Falls back to ['mRNA'] only when the query cannot run at all.
 *
 * ⚠️ DO NOT replace this with the literal 'mRNA'. That is the whole point of the function.
 * Measured across all 85 databases 2026-08-05:
 *     mRNA        2,980,598 annotated features   79 organisms
 *     transcript     48,562                       2 organisms
 *     gene            7,812                       9 organisms (7,708 = Bradyrhizobium)
 * A hardcoded 'mRNA' silently returns ZERO annotation results for Bradyrhizobium — a
 * bacterium, whose annotations are gene-level by nature — and drops 48,562 rows across the
 * two Schmidtea. Correct for 79 of 85 is not correct.
 *
 * The 1% cutoff drops noise rather than structure: Petromyzon_marinus carries 83,343
 * mRNA-level annotations and 13 stray gene-level ones. Those 13 are a data artifact, and
 * admitting 'gene' on their account would re-introduce the gene+mRNA duplication for that
 * organism's entire name search. A level has to be genuinely used to count.
 *
 * See notes/SEARCH_FEATURE_LEVEL_DECISION.md.
 *
 * @return string[] Non-empty list of feature_type values.
 */
function moop_annotation_levels($dbFile, $organism = '') {
    static $memo = [];
    if (isset($memo[$dbFile])) return $memo[$dbFile];

    // Cache keyed by organism when we know it; the answer only changes on a DB rebuild.
    $cache_file = '';
    if ($organism !== '' && function_exists('moop_annotation_levels_cache_file')) {
        $cache_file = moop_annotation_levels_cache_file($organism);
        if ($cache_file !== '' && file_exists($cache_file) && file_exists($dbFile)
            && filemtime($cache_file) >= filemtime($dbFile)) {
            $cached = loadJsonFile($cache_file, null);
            if (is_array($cached) && !empty($cached['levels']) && is_array($cached['levels'])) {
                return $memo[$dbFile] = $cached['levels'];
            }
        }
    }

    $levels = [];
    try {
        $dbh  = getDbConnection($dbFile);
        $stmt = $dbh->query(
            'SELECT f.feature_type AS t, COUNT(DISTINCT fa.feature_id) AS n
               FROM feature f
               JOIN feature_annotation fa ON fa.feature_id = f.feature_id
              GROUP BY f.feature_type'
        );
        $rows  = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $total = 0;
        foreach ($rows as $r) $total += (int)$r['n'];
        foreach ($rows as $r) {
            if ($total > 0 && ((int)$r['n'] / $total) >= 0.01) $levels[] = $r['t'];
        }
    } catch (Exception $e) {
        error_log('moop_annotation_levels: ' . $e->getMessage());
    }

    // An unannotated database legitimately yields nothing; mRNA is the right shape to
    // assume for the name/description path there, and no annotation row can contradict it.
    if (empty($levels)) $levels = ['mRNA'];

    if ($cache_file !== '') {
        @file_put_contents($cache_file, json_encode(['levels' => $levels, 'built' => time()]));
        @chmod($cache_file, 0664);
    }
    return $memo[$dbFile] = $levels;
}

/**
 * Search features by name and description only (gene-centric) — no annotation join.
 * Used when the user has explicitly deselected all annotation sources. Backed by the
 * feature_search FTS index, which covers EVERY feature (including unannotated ones).
 * Returns rows in the same column shape as searchFeaturesAndAnnotations (annotation
 * columns NULL) so the result-formatting code in the AJAX endpoint is unchanged.
 */
function searchFeaturesByNameDescription($search_term, $is_quoted_search, $dbFile, $assembly_accession = '', $gene_set_name = '', $scope_pairs = [], $organism = '') {
    $match = buildFtsMatchExpr($search_term, $is_quoted_search);
    if ($match === '') return ['results' => [], 'capped' => false, 'warning' => null];

    $name_like = '%' . ftsPrimaryTerm($search_term, $is_quoted_search) . '%';

    // Collapse the gene model to ONE level, so a name search returns one row per gene
    // instead of four (gene + mRNA + cds + protein). The FTS index deliberately covers
    // every level -- a user searching a protein ID must still find something -- so this
    // filters RESULTS, not the index.
    //
    // The level is derived per database, never assumed: see moop_annotation_levels().
    // Filtering to the annotation-bearing level makes this path agree with the annotation
    // path, which returns that level already (the loader floats annotations up to it).
    //
    // Safe because a filtered-out row carries nothing findable here: of 2,232,940 genes,
    // the 3,331 with no child are all in Petromyzon_marinus and every one has no name AND
    // no annotations, so no name/description query can match them. They stay reachable by
    // exact ID, which is a different path and is not filtered.
    //
    // ⚠️ FALLS BACK TO UNFILTERED, and that is not belt-and-braces — it is load-bearing.
    // The annotation-bearing level and the TEXT-bearing level are not the same level in
    // every database. Schmidtea_lugubris and Schmidtea_nova annotate at `transcript`, and
    // their transcript rows carry NO name and NO description at all (0 of 66,510); the
    // searchable text sits on `gene`. Filtering those to `transcript` turns 719 hits for
    // "kinase" into 0. They also happen to need no de-duplication, because only `gene`
    // carries description there — so the unfiltered answer is already one row per gene.
    // Retrying unfiltered costs a second query ONLY when the filtered one found nothing.
    $levels = moop_annotation_levels($dbFile, $organism);

    $sql = "SELECT f.feature_uniquename, f.feature_name, f.feature_description,
                   NULL AS annotation_accession, NULL AS annotation_description,
                   NULL AS score, NULL AS date, NULL AS annotation_source_name,
                   o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                   g.genome_accession, g.genome_name, gs.gene_set_name,
                   (f.feature_name LIKE ?) AS name_match
            FROM feature_search fs
            JOIN feature   f  ON f.feature_id   = fs.rowid
            JOIN gene_set  gs ON gs.gene_set_id = f.gene_set_id
            JOIN genome    g  ON g.genome_id    = gs.genome_id
            JOIN organism  o  ON o.organism_id  = f.organism_id
            WHERE feature_search MATCH ?";
    $params = [$name_like, $match];

    // Keep an unfiltered twin for the fallback, built BEFORE the scope filters so both
    // carry identical scoping — the fallback drops the LEVEL filter, nothing else.
    $sql_unfiltered    = $sql;
    $params_unfiltered = $params;

    $sql .= ' AND f.feature_type IN (' . implode(',', array_fill(0, count($levels), '?')) . ')';
    array_push($params, ...$levels);

    appendScopeFilters($sql, $params, $assembly_accession, $gene_set_name, $scope_pairs);
    appendScopeFilters($sql_unfiltered, $params_unfiltered, $assembly_accession, $gene_set_name, $scope_pairs);

    // Named genes first (hard tier), then -- among rows bm25 cannot tell apart -- the ones
    // that carry a name at all, then bm25 relevance (name col weighted 10, desc 5), then a
    // stable id tiebreak. bm25 must stay in ORDER BY only (it errors if projected).
    //
    // The has-a-name tie-break is the same rule the annotation search applies, and it is
    // here so the two cannot disagree: this path runs when the user deselects every
    // annotation source, which is a toggle on the same screen, not a different feature.
    // Without it "transposases" put an unnamed MARINER MOS1 TRANSPOSASE-LIKE PROTEIN above
    // the named mariner transposases carrying the same description.
    $order = " ORDER BY name_match DESC,
                        (COALESCE(f.feature_name, '') <> '') DESC,
                        bm25(feature_search, 10.0, 5.0),
                        f.feature_uniquename
               LIMIT " . moop_search_query_limit();

    $out = runFtsSearch($dbFile, $sql . $order, $params);
    if (!empty($out['results'])) return $out;

    // Filtered to a level that holds no matching text — see the comment above. Retry with
    // the level filter dropped rather than reporting "no results" for a gene that is there.
    return runFtsSearch($dbFile, $sql_unfiltered . $order, $params_unfiltered);
}

/**
 * Rows per annotation source in each interleave block. 25 keeps the biggest source to a
 * quarter of the first screen while still letting a genuinely dominant source lead it.
 */
const MOOP_SEARCH_SOURCE_BLOCK = 25;

/**
 * Stop one annotation source from owning the first screen. REORDERS, never drops.
 *
 * The quota pool balances annotation TYPES, which is the curated concept -- but a user
 * sees SOURCES, and one type can be served by very few of them. Gene Ontology is a single
 * type fed mainly by EggNOG2GO and InterPro2GO, so giving it a fair type share handed it a
 * larger source share than bm25 did: measured on Rhinolophus, "binding" went from 37 of
 * the top 100 from its biggest source to 56, and "ubiquitin" 20 to 27. Better on types,
 * worse on the thing displayed.
 *
 * So: take the first $cap rows of each source, then the next $cap of each, and so on.
 * Rank order is preserved WITHIN each block, and every row is kept -- rows past the cap
 * move down the list, they do not disappear. With ~7-24 sources and a cap of 25, block
 * zero alone covers far more than one screen.
 */
function moop_interleave_by_source(array $rows, $cap) {
    if ($cap < 1 || count($rows) <= $cap) return $rows;
    $seen = $blocks = [];
    foreach ($rows as $row) {
        $src = (string)($row['annotation_source_name'] ?? '');
        $n   = $seen[$src] = ($seen[$src] ?? 0) + 1;
        $blocks[intdiv($n - 1, $cap)][] = $row;
    }
    ksort($blocks);
    return array_merge(...array_values($blocks));
}

/**
 * The FTS token identifying one annotation type inside feature_annotation_search.
 *
 * MUST mirror the expression in build_fts_index.sql exactly, or the pool silently
 * selects nothing for that type and its quota is lost. strtolower(), not mb_strtolower():
 * SQLite's lower() is ASCII-only, and a type with a non-ASCII capital must produce the
 * same token on both sides. Separators are stripped rather than replaced because
 * unicode61 splits on '_' and porter then stems the pieces -- "RBBH_Homolog" and
 * "Homologs" both index "homolog", which made the Homologs filter return 870,674 rows
 * where 69,398 qualify. One token per type has no shared stem with any other.
 */
function moop_fts_type_code($annotation_type) {
    return 'atype' . strtolower(str_replace([' ', '-', '_'], '', (string)$annotation_type)) . 'z';
}

/**
 * Does this organism's index carry annotation_type_code (i.e. has it been rebuilt)?
 *
 * Quota pooling needs that column. Databases indexed before it existed must keep working
 * unchanged, so every caller falls back to the bm25 pool when this is false -- which is
 * what makes the re-index a rolling operation rather than a flag day.
 */
function moop_fts_has_type_column($dbFile) {
    static $cache = [];
    if (array_key_exists($dbFile, $cache)) return $cache[$dbFile];
    try {
        $dbh  = getDbConnection($dbFile);
        $stmt = $dbh->query("SELECT sql FROM sqlite_master WHERE name = 'feature_annotation_search'");
        $ddl  = $stmt ? (string)$stmt->fetchColumn() : '';
        $cache[$dbFile] = stripos($ddl, 'annotation_type_code') !== false;
    } catch (PDOException $e) {
        $cache[$dbFile] = false;
    }
    return $cache[$dbFile];
}

/**
 * Annotation types present in this organism, in the order curated in annotation_config.json.
 *
 * Types the config does not mention are appended rather than dropped: an organism can load
 * a source whose type nobody has curated yet, and silently excluding it from the pool would
 * make those annotations unfindable. Same precedence rule getAnnotationSourcesGrouped() uses.
 */
function moop_curated_annotation_types($dbFile) {
    static $cache = [];
    if (array_key_exists($dbFile, $cache)) return $cache[$dbFile];

    $present = [];
    try {
        $dbh = getDbConnection($dbFile);
        foreach ($dbh->query("SELECT DISTINCT annotation_type FROM annotation_source") as $row) {
            $type = trim((string)$row['annotation_type']);
            if ($type !== '') $present[$type] = true;
        }
    } catch (PDOException $e) {
        return $cache[$dbFile] = [];
    }

    global $config;
    $cfg = loadJsonFile($config->getPath('metadata_path') . '/annotation_config.json', []);
    $ordered = [];
    foreach (($cfg['annotation_type_order'] ?? []) as $type) {
        if (isset($present[$type])) { $ordered[] = $type; unset($present[$type]); }
    }
    foreach (array_keys($present) as $type) $ordered[] = $type;
    return $cache[$dbFile] = $ordered;
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
    $date_expr = moop_annotation_date_expr($dbFile);

    $columns = "f.feature_uniquename, f.feature_name, f.feature_description,
                   a.annotation_accession, a.annotation_description,
                   fa.score, $date_expr AS date, ans.annotation_source_name,
                   o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                   g.genome_accession, g.genome_name, gs.gene_set_name,
                   (f.feature_name LIKE ?) AS name_match";

    $joins = "JOIN feature_annotation  fa  ON fa.feature_annotation_id = %ROWID%
            JOIN feature             f   ON f.feature_id             = fa.feature_id
            JOIN annotation          a   ON a.annotation_id          = fa.annotation_id
            JOIN annotation_source   ans ON ans.annotation_source_id = a.annotation_source_id
            JOIN organism            o   ON o.organism_id            = f.organism_id
            JOIN gene_set            gs  ON gs.gene_set_id           = f.gene_set_id
            JOIN genome              g   ON g.genome_id              = gs.genome_id";

    $filtered = ($assembly_accession !== '' || $gene_set_name !== ''
                 || !empty($scope_pairs) || !empty($source_names));

    $quota_pool = false;

    if (!$filtered) {
        // FAST PATH — choose the pool inside the FTS index, then fetch only the survivors.
        //
        // The general shape below joins EVERY matched row and sorts the lot, so
        // "binding" reads 121,780 rows to display 2,500 of them. Measured on
        // Nematostella, cold: 179 MB read and 3.4 s. Choosing the pool from the index
        // first cut that to 53 MB and 2.7 s, and dropped the cross-organism working set
        // from ~13.4 GB to ~3.6 GB -- from larger than the page cache to inside it.
        //
        // ONLY when nothing is filtered. Scope and source filters live on tables the
        // pool cannot see, so they would apply AFTER the pool was chosen -- a search
        // limited to one annotation source could come back near-empty because the pool
        // filled up with rows from other sources. Filtered searches take the general
        // shape, where the filter narrows before ranking.
        $types = moop_fts_has_type_column($dbFile) ? moop_curated_annotation_types($dbFile) : [];

        if (!empty($types)) {
            // QUOTA POOL. Take a slice per annotation type, in the curated order, each slice
            // ordered by rowid -- which is free -- instead of ranking the whole match set
            // with bm25(), which is not. Measured on Rhinolophus: bm25 was 24.7 MB of
            // helicase's 40.1 and 51.1 of binding's 73.5, because it reads a scattered
            // docsize entry per MATCHED document. The quota pool reads none of that, and
            // cost stops scaling with term frequency: binding matches 322,361 documents and
            // now costs about what ubiquitin's 75,512 do. 1.6-4.5x less I/O, precision
            // unchanged at 100/100. See notes/SEARCH_COST_MODEL_2026-07-31.md.
            //
            // Pool is 1.5x the cap, not 2x: measured better on BOTH axes than 2x (binding
            // 23.9 -> 19.7 MB with the same six types on page one). Below 1.5x the type
            // count starts dropping, which is the thing this exists to protect.
            $pool_size = max(1, (int) round(moop_search_results_limit() * 1.5));
            $per_type  = (int) ceil($pool_size / count($types));
            $text_cols = '{feature_name feature_description annotation_description annotation_accession}';

            $arms = [];
            $params = [];
            foreach (array_values($types) as $i => $type) {
                // LIMIT is interpolated, not bound: PDO can hand a bound value to SQLite as
                // a string, and only the MATCH expression here comes from user input. Both
                // $i and $per_type are integers derived from config.
                $arms[] = "SELECT rid, arm FROM (SELECT rowid AS rid, $i AS arm
                             FROM feature_annotation_search
                             WHERE feature_annotation_search MATCH ?
                             ORDER BY rowid LIMIT $per_type)";
                $params[] = '{annotation_type_code} : ' . moop_fts_type_code($type)
                          . ' AND ' . $text_cols . ' : (' . $match . ')';
            }
            // Top-up arm, deliberately last. Without it a term whose matches all sit in one
            // type would come back with only pool_size/count(types) rows -- a per-type quota
            // starves a single-type result. MIN(arm) below keeps the balanced arms ahead of
            // it, so the top-up only ever fills space the quotas could not.
            $arms[] = "SELECT rid, arm FROM (SELECT rowid AS rid, 9999 AS arm
                         FROM feature_annotation_search
                         WHERE feature_annotation_search MATCH ?
                         ORDER BY rowid LIMIT $pool_size)";
            $params[] = $text_cols . ' : (' . $match . ')';

            $sql = "WITH cand(rid, arm) AS (\n" . implode("\n UNION ALL\n", $arms) . "\n),
                    pool AS (SELECT rid FROM cand GROUP BY rid ORDER BY MIN(arm) LIMIT $pool_size)
                    SELECT $columns
                    FROM pool
                    " . str_replace('%ROWID%', 'pool.rid', $joins) . "
                    WHERE 1=1";
            $params[] = $name_like;
            $quota_pool = true;
        } else {
            // Pre-rebuild databases keep the bm25 pool. Pool is TWICE the cap here, not
            // the cap: at 1x the top 100 already diverged for "transpos", because bm25
            // orders stem-noise arbitrarily and the finer tiers below re-rank within
            // whatever it hands over. (The quota pool above wants 1.5x, not 2x -- it is
            // not ordering by relevance, so it needs breadth rather than depth.)
            $pool_size = (int) (moop_search_results_limit() * 2);
            $sql = "WITH pool AS (
                        SELECT rowid AS rid,
                               bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0, 0.0) AS rank
                        FROM feature_annotation_search
                        WHERE feature_annotation_search MATCH ?
                        ORDER BY rank
                        LIMIT $pool_size
                    )
                    SELECT $columns
                    FROM pool
                    " . str_replace('%ROWID%', 'pool.rid', $joins) . "
                    WHERE 1=1";
            $params = [$match, $name_like];
        }
    } else {
        $sql = "SELECT $columns
                FROM feature_annotation_search fas
                " . str_replace('%ROWID%', 'fas.rowid', $joins) . "
                WHERE feature_annotation_search MATCH ?";
        $params = [$name_like, $match];

        appendScopeFilters($sql, $params, $assembly_accession, $gene_set_name, $scope_pairs);

        if (!empty($source_names)) {
            $placeholders = implode(',', array_fill(0, count($source_names), '?'));
            $sql .= " AND ans.annotation_source_name IN ($placeholders)";
            foreach ($source_names as $s) { $params[] = $s; }
        }
    }

    // Named genes first (hard tier); then rows that literally contain the term; then bm25
    // relevance weighting name:10, feature_desc:5, annotation_desc:2, annotation_accession:3;
    // then a stable id tiebreak. bm25 must stay in ORDER BY only (it errors if projected as
    // a column or wrapped in an aggregate).
    //
    // The literal tier exists because the porter tokenizer stems the QUERY term as well as
    // the indexed text, and we append '*' to every term, so a stemmed query then
    // prefix-matches far more than the user typed. Measured on Nematostella: "transpos"
    // matches 79,142 rows though only 4,028 contain that string -- it stems toward
    // "transpo" and reaches TRANSPORT. Those stem-only rows were OUTRANKING the real hits,
    // burying 3,893 of them below the result cap; the top 100 was 19% relevant.
    //
    // Sorting literal matches above stem-only ones makes that top 100 100% relevant while
    // keeping the stem matches as a harmless tail. Deliberately NOT fixed by changing the
    // tokenizer: 'unicode61' (no stemming) would destroy plural search, which is constant
    // in this domain -- "proteins" 716,560 -> 38,658 rows, "transposons" 3,082 -> 1.
    // Costs nothing measurable (cold 1516 vs 1524 ms, warm 47 vs 44 ms).
    // On the bm25 fast path the score is already computed, as pool.rank -- and it CANNOT be
    // called again here, because the FTS table is no longer in the FROM clause.
    //
    // The quota pool computes NO score, deliberately: bm25 was 60% of a cold search's I/O
    // while sitting fifth in this ORDER BY, below three tiers that do the real work. It
    // was also actively harmful here -- its document-length normalisation favours short
    // documents, which are ProtNLM's terse AI-generated protein names, so it filled 74 of
    // the top 100 for "helicase" with the annotation type annotation_config.json ranks
    // 8th of 10. With no score the tiers below decide alone, which is what they were
    // written to do. Trailing comma included/omitted here so the ORDER BY stays valid
    // either way -- an empty $rank_expr must not leave a dangling comma.
    $rank_expr = $filtered
        ? 'bm25(feature_annotation_search, 10.0, 5.0, 2.0, 3.0, 0.0),'
        : ($quota_pool ? '' : 'pool.rank,');

    // Beneath the literal tier, the same test against the STEM of the typed word, so a
    // plural search still credits the singular records that FTS5 actually matched. See
    // ftsStemLikePattern() for the measurements and for why it goes below, not instead of.
    $stem_like = ftsStemLikePattern($search_term, $is_quoted_search);
    $stem_tier = $stem_like === '' ? '' : "(a.annotation_description LIKE ?) DESC,\n                       ";

    // Then, AMONG ROWS THAT MATCHED EQUALLY WELL, the ones whose gene carries a name.
    //
    // This is a tie-break, not a preference for named genes, and the difference is the
    // whole point. It is read only after the tiers above have already separated exact
    // matches from stem matches, so an unnamed feature NEVER falls below a weaker match --
    // it falls below an EQUAL one that a reader can identify. Searching "transposases"
    // returned ten identical unnamed rows carrying one shared EggNOG domain
    // ("Putative DNA-binding domain in centromere protein B, mouse jerky and
    // transposases.") above THAP9, HMGXB3, POGK and TIGD1 -- which hold that same domain,
    // and a name. Nothing distinguished those ten to bm25, so it ordered them arbitrarily
    // and they filled the screen.
    //
    // Deliberately NOT a filter and not a higher tier: an unnamed feature with a good
    // annotation is often exactly the target -- gene naming is incomplete, which is why
    // annotations are searchable in the first place.
    // THE GENE'S OWN DESCRIPTION, above anything an annotation says about it.
    //
    // Searching "nexin" in Bats returned CYTIP -- cytohesin 1 interacting protein -- ABOVE
    // SNX17, "sorting nexin 17". CYTIP matched on a single Drosophila ortholog call
    // ("Snx27: Sorting nexin 27"); SNX17 matched 63 times and has the word in its own
    // description. Every tier tied, so the alphabetical uniquename tiebreak decided it, and
    // ACA1_..._013 sorts before ACA1_..._043.
    //
    // This signal used to exist twice and was lost twice. The pre-FTS ladder graded
    // feature_description across three levels (d691848^); replacing it with FTS kept only
    // the name and annotation tiers. bm25 then carried it implicitly -- its column weights
    // were name 10, feature_description 5, annotation 2 -- until the quota pool removed
    // bm25 from this path. Stating it explicitly is cheaper than either: the column is
    // already selected and already joined, so the tier costs no additional read.
    //
    // Above the annotation tier, because what a gene IS beats what something else says it
    // resembles. Below name_match, which stays the hard tier.
    $sql .= " ORDER BY name_match DESC,
                       (f.feature_description LIKE ?) DESC,
                       (a.annotation_description LIKE ?) DESC,
                       $stem_tier(COALESCE(f.feature_name, '') <> '') DESC,
                       $rank_expr
                       f.feature_uniquename
              LIMIT " . moop_search_query_limit();
    // These must be appended LAST, and in this order: all three placeholders sit in the
    // ORDER BY, which follows every scope/source filter added to the WHERE clause above.
    // The first two are the same pattern against different columns -- feature_description
    // then annotation_description -- and PDO binds positionally, so they cannot be merged.
    $params[] = $name_like;
    $params[] = $name_like;
    if ($stem_like !== '') $params[] = $stem_like;

    $out = runFtsSearch($dbFile, $sql, $params);

    // Only on the quota path: the bm25 pool does its own (accidental) source spreading,
    // and the filtered path is already narrowed to what the user asked for, so neither
    // wants this. Applied after the display cap so it reorders only what is shown.
    if ($quota_pool && !empty($out['results'])) {
        $out['results'] = moop_interleave_by_source($out['results'], MOOP_SEARCH_SOURCE_BLOCK);
    }
    return $out;
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
                         g.genome_accession, g.genome_name, gs.gene_set_name,
                         f.feature_id, f.parent_feature_id
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
                         g.genome_accession, g.genome_name, gs.gene_set_name,
                         f.feature_id, f.parent_feature_id
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

    return moop_resolve_hits_to_level(fetchData($query, $dbFile, $params), $dbFile, $organism_name);
}

// Required outright rather than relied on transitively: moop_resolve_hits_to_level()'s
// climb uses MOOP_HIERARCHY_MAX_DEPTH, and an undefined constant is a fatal Error in PHP 8.
// tools/annotation_search_ajax.php does NOT include parent_functions.php, so without this
// the ID search would fatal the moment a hit needed lifting. Same reasoning, and the same
// idempotent require_once, as lib/moopmart_functions.php:20.
require_once __DIR__ . '/parent_functions.php';

/**
 * Collapse ID-search hits onto ONE level per gene, by RESOLVING rather than filtering.
 *
 * An ID search returns several rows for one gene whenever the child IDs happen to nest inside
 * the parent's, and `LIKE '%term%'` then matches all of them. "NV2t021704001" returns 3 rows.
 *
 * ⚠️ That nesting is NOT a universal MOOP rule, and assuming it is would be wrong. Depositor
 * IDs are preserved (see [[feedback_original_data_stays_original]]); `…:cds`/`…:pep` is
 * specifically the transcript2gene path suffixing MOOP's OWN copies to disambiguate them.
 * Other sources nest differently or not at all:
 *     T2G      NV2t021704001.1        :cds / :pep   -> children CONTAIN the parent id
 *     RefSeq   WP_011083461.1         cds-…         -> child contains it via a PREFIX
 *     Ensembl  FBtr0070000 / FBpp…    independent   -> no nesting, no duplication at all
 * Which is exactly why this resolves through the hierarchy rather than pattern-matching
 * IDs: a string rule that fits one convention silently misses the others.
 *
 * ⚠️ FILTERING (what the name/description path does) IS WRONG HERE. Dropping every row that
 * is not at the annotation-bearing level means a user pasting a protein accession
 * (`…​.1:pep`) gets NOTHING — a gene that exists reported as missing. That is a worse and
 * quieter failure than the duplication. So a child hit is replaced by its parent instead of
 * being discarded, and the row that survives carries `matched_uniquename` so the UI can say
 * "matched …:pep" rather than silently showing an ID the user never typed.
 *
 * ⚠️ BOUNDED WALK, never an unbounded recursive one. `parent_feature_id` has held self-loops
 * in the wild — Schmidtea_lugubris carried 14,313 before its 2026-08-05 reload, and the other
 * databases are not reloaded yet. An unbounded walk pins a php-fpm worker (SQLite 3.34.1 here
 * has no CYCLE clause). This climbs at most MOOP_RESOLVE_MAX_DEPTH levels, in batched
 * queries, and stops early when nothing moved.
 *
 * ⚠️ A SINGLE step is NOT enough, which is easy to assume and wrong. The chain is
 * mRNA → cds → protein: `:cds` hangs off the transcript but `:pep` hangs off the CDS, so a
 * protein is TWO steps from the annotation-bearing level. Resolving one step turned a
 * protein hit into a CDS hit and the duplicate simply reappeared one row down.
 *
 * ⚠️ NEVER DROPS A ROW. A hit whose parent cannot be resolved — missing, self-referential,
 * or one of the 'NULL'/'' text values the old loaders wrote — is kept exactly as it was.
 * Losing a result to a failed lookup is the "a miss deletes" shape that has bitten this
 * codebase before.
 *
 * See notes/SEARCH_FEATURE_LEVEL_DECISION.md.
 */
function moop_resolve_hits_to_level(array $rows, $dbFile, $organism_name = '') {
    if (count($rows) < 2) return $rows;

    $levels = moop_annotation_levels($dbFile, $organism_name);
    $at_level = array_flip(array_map('strtolower', $levels));

    // Which hits need lifting? Anything not already at the target level.
    $need = [];
    foreach ($rows as $r) {
        $u = (string)($r['feature_uniquename'] ?? '');
        if ($u !== '' && !isset($at_level[strtolower((string)($r['feature_type'] ?? ''))])) $need[$u] = true;
    }
    if (empty($need)) return $rows;

    // ONE batched climb for every hit at once -- the same shape as
    // moopmartResolveInputIds() and getAncestors(), including their cycle guard:
    // f.feature_id <> c.feature_id stops a self-parent, and the depth cap stops a
    // multi-row cycle. SQLite 3.34.1 here has no CYCLE clause, so both are required.
    // The only difference from MOOPmart is where the climb STOPS: MOOPmart wants the
    // root (a gene), this wants the annotation-bearing level, so that ID search agrees
    // with the annotation and name paths rather than with a fourth answer.
    $ids    = array_keys($need);
    $ph_ids = implode(',', array_fill(0, count($ids), '?'));
    $ph_lv  = implode(',', array_fill(0, count($levels), '?'));

    $sql = "WITH RECURSIVE chain AS (
                SELECT f.feature_uniquename AS input_name, f.feature_id,
                       f.feature_uniquename AS node_name, f.feature_type,
                       f.parent_feature_id, 0 AS depth
                FROM   feature f
                WHERE  f.feature_uniquename IN ($ph_ids)
                UNION ALL
                SELECT c.input_name, f.feature_id,
                       f.feature_uniquename, f.feature_type,
                       f.parent_feature_id, c.depth + 1
                FROM   feature f
                JOIN   chain c ON f.feature_id = c.parent_feature_id
                WHERE  c.depth < " . MOOP_HIERARCHY_MAX_DEPTH . "
                  AND  f.feature_id <> c.feature_id
            )
            SELECT input_name, node_name, MIN(depth) AS depth
            FROM   chain
            WHERE  LOWER(feature_type) IN ($ph_lv)
            GROUP  BY input_name";

    $lift = [];
    foreach (fetchData($sql, $dbFile, array_merge($ids, array_map('strtolower', $levels))) as $row) {
        $lift[(string)$row['input_name']] = (string)$row['node_name'];
    }

    // Fetch the rows we lifted TO that are not already in the result set.
    $missing = [];
    $have    = [];
    foreach ($rows as $r) $have[(string)($r['feature_uniquename'] ?? '')] = true;
    foreach ($lift as $target) if (!isset($have[$target])) $missing[$target] = true;

    $extra = [];
    if (!empty($missing)) {
        $mids = array_keys($missing);
        $ph_m = implode(',', array_fill(0, count($mids), '?'));
        $msql = "SELECT f.feature_uniquename, f.feature_name, f.feature_description,
                        o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                        g.genome_accession, g.genome_name, gs.gene_set_name,
                        f.feature_id, f.parent_feature_id
                 FROM feature f, organism o, gene_set gs, genome g
                 WHERE f.organism_id = o.organism_id
                   AND f.gene_set_id = gs.gene_set_id
                   AND gs.genome_id  = g.genome_id
                   AND f.feature_uniquename IN ($ph_m)";
        foreach (fetchData($msql, $dbFile, $mids) as $m) {
            $extra[(string)$m['feature_uniquename']] = $m;
        }
    }

    // Rebuild in the original order, de-duplicating on the surviving uniquename.
    // A hit whose climb found nothing is KEPT AS IT WAS -- losing a result to a failed
    // lookup is the "a miss deletes" shape that has bitten this codebase before.
    $out = [];
    foreach ($rows as $r) {
        $matched = (string)($r['feature_uniquename'] ?? '');
        $row     = $r;
        if (isset($lift[$matched])) {
            $t = $lift[$matched];
            if ($t !== $matched) {
                if (isset($extra[$t]))        $row = $extra[$t];
                else {
                    foreach ($rows as $cand) { if (($cand['feature_uniquename'] ?? '') === $t) { $row = $cand; break; } }
                }
            }
        }
        $key = (string)($row['feature_uniquename'] ?? $matched);
        if (!isset($out[$key])) {
            $row['matched_uniquename'] = ($matched === $key) ? '' : $matched;
            $out[$key] = $row;
        } elseif ($matched === $key) {
            $out[$key]['matched_uniquename'] = '';
        }
    }
    return array_values($out);
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
