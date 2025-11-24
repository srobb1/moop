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
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Feature row with organism and genome info, or empty array
 */
function getFeatureById($feature_id, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(',', array_fill(0, count($genome_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
                  WHERE f.feature_id = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$feature_id], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
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
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Feature row with organism and genome info, or empty array
 */
function getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(',', array_fill(0, count($genome_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
                  WHERE f.feature_uniquename = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$feature_uniquename], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
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
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Array of child feature rows
 */
function getChildrenByFeatureId($parent_feature_id, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(',', array_fill(0, count($genome_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.parent_feature_id = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$parent_feature_id], $genome_ids);
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
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Parent feature row (minimal fields), or empty array
 */
function getParentFeature($feature_id, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(',', array_fill(0, count($genome_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.feature_id = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$feature_id], $genome_ids);
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
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Array of features with specified type
 */
function getFeaturesByType($feature_type, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(',', array_fill(0, count($genome_ids), '?'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.genome_id
                  FROM feature f
                  WHERE f.feature_type = ? AND f.genome_id IN ($placeholders)
                  ORDER BY f.feature_uniquename";
        $params = array_merge([$feature_type], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.genome_id
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
function getAssemblyStats($genome_accession, $dbFile) {
    $query = "SELECT g.genome_id, g.genome_accession, g.genome_name,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id END) as gene_count,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) as mrna_count,
                     COUNT(DISTINCT f.feature_id) as total_features
              FROM genome g
              LEFT JOIN feature f ON g.genome_id = f.genome_id
              WHERE g.genome_accession = ?
              GROUP BY g.genome_id";
    
    $results = fetchData($query, $dbFile, [$genome_accession]);
    return !empty($results) ? $results[0] : [];
}

/**
 * Search features and annotations by keyword
 * Supports both keyword and quoted phrase searches
 * Used by annotation_search_ajax.php
 * 
 * @param string $search_term - Search term or phrase
 * @param bool $is_quoted_search - Whether this is a quoted phrase search
 * @param string $dbFile - Path to SQLite database
 * @return array - Array of matching features with annotations
 */
function searchFeaturesAndAnnotations($search_term, $is_quoted_search, $dbFile, $source_names = []) {
    // Use provided source names filter, or empty array if not provided
    $source_filter = !empty($source_names) ? $source_names : [];
    $search_term_clean = $search_term;
    
    // Build the WHERE clause for annotations with REGEXP ranking
    if ($is_quoted_search) {
        // Exact phrase match
        $like_pattern = "%$search_term_clean%";
        $regex_exact = '\b' . preg_quote($search_term_clean, '/') . '\b';
        $regex_start = '\b' . preg_quote($search_term_clean, '/');
        
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         a.annotation_accession, a.annotation_description, 
                         fa.score, fa.date, ans.annotation_source_name, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o, genome g
                  WHERE ans.annotation_source_id = a.annotation_source_id 
                    AND f.feature_id = fa.feature_id 
                    AND fa.annotation_id = a.annotation_id 
                    AND f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND (a.annotation_description LIKE ? 
                       OR f.feature_name LIKE ? 
                       OR f.feature_description LIKE ?
                       OR a.annotation_accession LIKE ?)";
        
        $params = [$like_pattern, $like_pattern, $like_pattern, $like_pattern];
        
        // Add source filter if specified (exact match with IN)
        if (!empty($source_filter)) {
            $placeholders = implode(',', array_fill(0, count($source_filter), '?'));
            $query .= " AND ans.annotation_source_name IN ($placeholders)";
            $params = array_merge($params, $source_filter);
        }
        
        $query .= " ORDER BY 
                     CASE 
                       WHEN f.feature_name REGEXP ? THEN 1
                       WHEN f.feature_name REGEXP ? THEN 2
                       WHEN f.feature_description REGEXP ? THEN 3
                       WHEN a.annotation_description REGEXP ? THEN 4
                       ELSE 5
                     END,
                     f.feature_uniquename";
        
        $params[] = $regex_exact;
        $params[] = $regex_start;
        $params[] = $regex_start;
        $params[] = $regex_exact;
        
    } else {
        // Multi-term keyword search (all terms must appear somewhere)
        $terms = array_filter(array_map('trim', preg_split('/\s+/', $search_term_clean)));
        if (empty($terms)) {
            return ['results' => [], 'capped' => false, 'warning' => null];
        }
        
        // Extract primary term for relevance scoring (first word of search)
        $primary_term = $terms[0];
        $primary_pattern = "%$primary_term%";
        $regex_exact = '\b' . preg_quote($primary_term, '/') . '\b';
        $regex_start = '\b' . preg_quote($primary_term, '/');
        
        // Build conditions: (col1 LIKE term1 OR col2 LIKE term1 OR ...) AND (col1 LIKE term2 OR ...)
        $conditions = [];
        $params = [];
        $columns = ['a.annotation_description', 'f.feature_name', 'f.feature_description', 'a.annotation_accession'];
        
        foreach ($terms as $term) {
            $term_conditions = implode(' OR ', array_map(function($col) { return "$col LIKE ?"; }, $columns));
            $conditions[] = "($term_conditions)";
            for ($i = 0; $i < count($columns); $i++) {
                $params[] = "%$term%";
            }
        }
        
        $where_clause = implode(' AND ', $conditions);
        
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         a.annotation_accession, a.annotation_description, 
                         fa.score, fa.date, ans.annotation_source_name, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o, genome g
                  WHERE ans.annotation_source_id = a.annotation_source_id 
                    AND f.feature_id = fa.feature_id 
                    AND fa.annotation_id = a.annotation_id 
                    AND f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND $where_clause";
        
        // Add source filter if specified (exact match with IN)
        if (!empty($source_filter)) {
            $placeholders = implode(',', array_fill(0, count($source_filter), '?'));
            $query .= " AND ans.annotation_source_name IN ($placeholders)";
            $params = array_merge($params, $source_filter);
        }
        
        $query .= " ORDER BY 
                    CASE 
                      WHEN f.feature_name REGEXP ? THEN 1
                      WHEN f.feature_name REGEXP ? THEN 2
                      WHEN f.feature_description REGEXP ? THEN 3
                      WHEN a.annotation_description REGEXP ? THEN 4
                      ELSE 5
                    END,
                    f.feature_uniquename";
        
        // Add primary term patterns for CASE statement to params
        $params[] = $regex_exact;
        $params[] = $regex_start;
        $params[] = $regex_start;
        $params[] = $regex_exact;
    }
    
    // Use LIMIT+check approach: query for 2501 results to detect if there are more
    $max_display = 2500;
    $query .= " LIMIT " . ($max_display + 1);
    
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Register REGEXP function
        $dbh->sqliteCreateFunction('REGEXP', function($pattern, $text) {
            return preg_match('/' . $pattern . '/i', $text) ? 1 : 0;
        }, 2);
        
        $stmt = $dbh->prepare($query);
        $stmt->execute($params);
        $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dbh = null;
        
        // Check if results were capped
        $capped = count($all_results) > $max_display;
        $warning = null;
        
        if ($capped) {
            // We got 2501+ results, so we know it's "2500+"
            $results = array_slice($all_results, 0, $max_display);
            $warning = "2,500+ results found. Use Advanced Filter or add more search terms to refine.";
        } else {
            // We got fewer than 2501, so all results are displayed
            $results = $all_results;
        }
        
        return [
            'results' => $results,
            'capped' => $capped,
            'warning' => $warning
        ];
        
    } catch (PDOException $e) {
        return [
            'results' => [],
            'capped' => false,
            'warning' => 'Search error: ' . $e->getMessage()
        ];
    }
}

function searchFeaturesAndAnnotationsLike($search_term, $is_quoted_search, $dbFile) {
    if ($is_quoted_search) {
        $like_pattern = "%$search_term%";
        $params = [$like_pattern, $like_pattern, $like_pattern, $like_pattern];
    } else {
        $terms = array_filter(array_map('trim', preg_split('/\s+/', $search_term)));
        if (empty($terms)) {
            return [];
        }
        
        $conditions = [];
        $params = [];
        $columns = ['a.annotation_description', 'f.feature_name', 'f.feature_description', 'a.annotation_accession'];
        
        foreach ($terms as $term) {
            $term_conditions = implode(' OR ', array_map(function($col) { return "$col LIKE ?"; }, $columns));
            $conditions[] = "($term_conditions)";
            for ($i = 0; $i < count($columns); $i++) {
                $params[] = "%$term%";
            }
        }
        $where_clause = implode(' AND ', $conditions);
    }
    
    if ($is_quoted_search) {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         a.annotation_accession, a.annotation_description, 
                         fa.score, fa.date, ans.annotation_source_name, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o, genome g
                  WHERE ans.annotation_source_id = a.annotation_source_id 
                    AND f.feature_id = fa.feature_id 
                    AND fa.annotation_id = a.annotation_id 
                    AND f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND (a.annotation_description LIKE ? 
                       OR f.feature_name LIKE ? 
                       OR f.feature_description LIKE ?
                       OR a.annotation_accession LIKE ?)
                  ORDER BY f.feature_uniquename";
    } else {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         a.annotation_accession, a.annotation_description, 
                         fa.score, fa.date, ans.annotation_source_name, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o, genome g
                  WHERE ans.annotation_source_id = a.annotation_source_id 
                    AND f.feature_id = fa.feature_id 
                    AND fa.annotation_id = a.annotation_id 
                    AND f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND $where_clause
                  ORDER BY f.feature_uniquename";
    }
    
    return fetchData($query, $dbFile, $params);
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
function searchFeaturesByUniquenameForSearch($search_term, $dbFile, $organism_name = '') {
    if ($organism_name) {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM feature f, organism o, genome g
                  WHERE f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND f.feature_uniquename LIKE ? 
                    AND (o.genus || ' ' || o.species = ?)
                  ORDER BY f.feature_uniquename
                  ";
        $params = ["%$search_term%", $organism_name];
    } else {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM feature f, organism o, genome g
                  WHERE f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND f.feature_uniquename LIKE ?
                  ORDER BY f.feature_uniquename
                  ";
        $params = ["%$search_term%"];
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
        
        // Sort types in display order
        $order = ['Gene Ontology', 'Gene Families', 'Domains', 'Orthologs', 'Homologs', 'AI Annotations'];
        $sorted = [];
        foreach ($order as $type) {
            if (isset($grouped[$type])) {
                $sorted[$type] = $grouped[$type];
            }
        }
        
        return $sorted;
        
    } catch (Exception $e) {
        return [];
    }
}
?>
