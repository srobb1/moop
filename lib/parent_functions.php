<?php
/**
 * Parent Feature Display Functions
 * Functions for displaying parent feature data, hierarchies, and annotations
 * Used by parent_display.php to render feature information
 */

/**
 * Maximum depth any feature-hierarchy walk will follow.
 *
 * MOOP's real hierarchy is gene -> mRNA -> CDS -> protein: four levels. Twenty is
 * far past anything legitimate, so on correct data this limit never binds and the
 * walks return exactly what they always did.
 *
 * It exists because a cycle in parent_feature_id makes an unguarded recursive CTE
 * run forever -- not slowly, but literally without end, pinning a php-fpm worker
 * at 100% CPU until something kills it. That is not hypothetical: as of
 * 2026-07-27 three shipped organisms carry 66,596 features whose
 * parent_feature_id equals their own feature_id (Bipalium_kewense 39,065,
 * Schmidtea_lugubris 14,313, Schmidtea_nova 13,218), every one of them reachable
 * from a gene-page URL.
 *
 * The database loader now refuses to write a feature as its own parent, and the
 * reload will clear the existing rows -- but a loader fix only applies to data
 * that has been reloaded, and it only tests for DIRECT self-reference. A two-row
 * cycle (A's parent is B, B's parent is A) still loads clean, passes the loader's
 * integrity checks, and hangs exactly the same way. This depth cap is the only
 * thing that catches that shape, so it stays after the reload.
 *
 * SQLite 3.42+ has a built-in CYCLE clause; this deployment is on 3.34.1, which
 * does not, hence the manual counter.
 *
 * @see config/build_and_load_db/data_loaders/load_genes_sqlite.pl
 */
if (!defined('MOOP_HIERARCHY_MAX_DEPTH')) {
    define('MOOP_HIERARCHY_MAX_DEPTH', 20);
}

/**
 * Get hierarchy of features (ancestors)
 * Traverses up the feature hierarchy from a given feature to its parents/grandparents
 * Optionally filters by genome_ids for permission-based access
 *
 * @param string $feature_uniquename - The feature uniquename to start from
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
 * @return array - Array of features: [self, parent, grandparent, ...]
 */
/**
 * Get the ancestor chain for a feature as a flat array [self, parent, grandparent, ...].
 * Uses a single upward recursive CTE instead of one query per level.
 *
 * @param string $feature_uniquename  The feature to start from
 * @param string $dbFile              Path to organism.sqlite
 * @param array  $gene_set_ids        Optional: accessible gene_set_ids for access control
 * @return array [self, parent, grandparent, ...] — empty if feature not found
 */
function getAncestors($feature_uniquename, $dbFile, $gene_set_ids = []) {
    $params = [$feature_uniquename];

    $gs_clause = '';
    if (!empty($gene_set_ids)) {
        $ph        = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $gs_clause = "AND f.gene_set_id IN ($ph)";
        array_push($params, ...$gene_set_ids);
    }

    // Cycle-guarded: never step onto the row we are already on (a self-parent,
    // which is what exists in the data today), and never climb deeper than a real
    // hierarchy goes (which also catches multi-row cycles). See
    // MOOP_HIERARCHY_MAX_DEPTH above. 'depth' is bookkeeping and is dropped by the
    // outer SELECT, so callers see the same six columns as before.
    $query = "WITH RECURSIVE ancestors AS (
        SELECT f.feature_id, f.feature_uniquename, f.feature_name,
               f.feature_description, f.feature_type, f.parent_feature_id,
               0 AS depth
        FROM   feature f
        WHERE  f.feature_uniquename = ? $gs_clause
        UNION ALL
        SELECT f.feature_id, f.feature_uniquename, f.feature_name,
               f.feature_description, f.feature_type, f.parent_feature_id,
               a.depth + 1
        FROM   feature f
        JOIN   ancestors a ON f.feature_id = a.parent_feature_id
        WHERE  a.depth < " . MOOP_HIERARCHY_MAX_DEPTH . "
          AND  f.feature_id <> a.feature_id
    )
    SELECT feature_id, feature_uniquename, feature_name,
           feature_description, feature_type, parent_feature_id
    FROM   ancestors";

    return fetchData($query, $dbFile, $params);
}

/**
 * getAncestorsByFeatureId — kept for backwards compatibility.
 * Delegates to getAncestors() via a uniquename lookup.
 */
function getAncestorsByFeatureId($feature_id, $dbFile, $gene_set_ids = []) {
    $rows = fetchData(
        "SELECT feature_uniquename FROM feature WHERE feature_id = ?",
        $dbFile,
        [$feature_id]
    );
    if (empty($rows)) return [];
    $chain = getAncestors($rows[0]['feature_uniquename'], $dbFile, $gene_set_ids);
    // Original callers expect the chain WITHOUT the seed feature itself
    return array_slice($chain, 1);
}

/**
 * Get all children and descendants of a feature
 * Recursively fetches all child features at any depth
 * Optionally filters by genome_ids for permission-based access
 *
 * @param int $feature_id - The parent feature ID
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
 * @return array - Flat array of all children and descendants
 */
/**
 * Get all descendants of a feature as a flat array — single recursive CTE query.
 * Replaces the previous recursive implementation that made one DB query per node.
 *
 * @param int    $feature_id   The root feature ID
 * @param string $dbFile       Path to organism.sqlite
 * @param array  $gene_set_ids Optional: accessible gene_set_ids for access control
 * @return array Flat array of all descendant rows
 */
function getChildren($feature_id, $dbFile, $gene_set_ids = []) {
    $params = [$feature_id];

    $gs_clause = '';
    if (!empty($gene_set_ids)) {
        $ph        = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $gs_clause = "AND f.gene_set_id IN ($ph)";
        array_push($params, ...$gene_set_ids);
    }

    // Cycle-guarded, same reasoning as getAncestors() -- walking DOWN loops just as
    // readily as walking up. The seed also excludes a self-parented row, which
    // would otherwise be returned as its own child: a feature that is its own
    // parent is not a child of anything.
    $query = "WITH RECURSIVE descendants AS (
        SELECT f.feature_id, f.feature_uniquename, f.feature_name,
               f.feature_description, f.feature_type, f.parent_feature_id,
               0 AS depth
        FROM   feature f
        WHERE  f.parent_feature_id = ? $gs_clause
          AND  f.feature_id <> f.parent_feature_id
        UNION ALL
        SELECT f.feature_id, f.feature_uniquename, f.feature_name,
               f.feature_description, f.feature_type, f.parent_feature_id,
               d.depth + 1
        FROM   feature f
        JOIN   descendants d ON f.parent_feature_id = d.feature_id
        WHERE  d.depth < " . MOOP_HIERARCHY_MAX_DEPTH . "
          AND  f.feature_id <> d.feature_id
    )
    SELECT feature_id, feature_uniquename, feature_name,
           feature_description, feature_type, parent_feature_id
    FROM   descendants";

    return fetchData($query, $dbFile, $params);
}

/**
 * Get all descendants as a nested tree (each row has a 'grandchildren' key).
 * Uses a single CTE query then rebuilds the hierarchy in PHP — O(n) total.
 *
 * @param int    $feature_id   The root feature ID
 * @param string $dbFile       Path to organism.sqlite
 * @param array  $gene_set_ids Optional: accessible gene_set_ids for access control
 * @return array Direct children, each with 'grandchildren' recursively populated
 */
function getChildrenHierarchical($feature_id, $dbFile, $gene_set_ids = []) {
    $flat = getChildren($feature_id, $dbFile, $gene_set_ids);
    if (empty($flat)) return [];

    // Group all nodes by their parent_feature_id for O(1) child lookup
    $by_parent = [];
    foreach ($flat as $row) {
        $by_parent[$row['parent_feature_id']][] = $row;
    }

    // Recursively attach grandchildren starting from direct children of $feature_id
    return _buildChildTree($feature_id, $by_parent);
}

function _buildChildTree($parent_id, array &$by_parent, array $seen = []) {
    // getChildren()'s CTE rejects a row that is its own parent, and caps depth, but a
    // longer loop (A parents B, B parents A) still arrives here as two rows that point
    // at each other -- and this walk would then recurse until PHP ran out of memory.
    // Cheap insurance, and the loaders are known to have produced cyclic parentage.
    if (isset($seen[$parent_id])) {
        return [];
    }
    $seen[$parent_id] = true;

    $children = $by_parent[$parent_id] ?? [];
    foreach ($children as &$child) {
        $child['grandchildren'] = _buildChildTree($child['feature_id'], $by_parent, $seen);
    }
    unset($child);
    return $children;
}

/**
 * Generate annotation table with export buttons
 * Creates a responsive HTML table displaying annotations with sorting/filtering
 *
 * @param array $results - Annotation results from database
 * @param string $uniquename - Feature uniquename (for export)
 * @param string $type - Feature type (for export)
 * @param int $count - Table counter (ensures unique IDs)
 * @param string $annotation_type - Type of annotation (e.g., "InterPro")
 * @param string $desc - Description/definition of annotation type
 * @param string $color - Bootstrap color class for badge
 * @param string $organism - Organism name (for export)
 * @param string $annotation_type_key - Database key for annotation type (used for anchor ID)
 * @return string - HTML for the annotation table section
 */
function generateAnnotationTableHTML($results, $uniquename, $type, $count, $annotation_type, $desc, $color = 'warning', $organism = '', $annotation_type_key = '') {
    if (empty($results)) {
        return '';
    }
    
    $table_id = "annotTable_$count";
    $result_count = count($results);
    $desc_id = "annotDesc_$count";
    
    // Determine text color based on background color
    $text_color = in_array($color, ['warning', 'info', 'secondary']) ? 'text-dark' : 'text-white';
    
    // Border color matches badge color
    $border_class = "border-$color";
    
    // Create unique ID for this annotation section
    // Use annotation_type_key if provided, otherwise fall back to annotation_type
    $key_for_anchor = $annotation_type_key ?: $annotation_type;
    $section_id = "annot_section_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $uniquename . '_' . $key_for_anchor);
    
    $html = '<div class="annotation-section mb-3 ' . htmlspecialchars($border_class) . '" id="' . htmlspecialchars($section_id) . '">';
    $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
    $html .= "<h5 class=\"mb-0\"><span class=\"badge bg-" . htmlspecialchars($color) . " $text_color badge-lg\">" . htmlspecialchars($annotation_type) . "</span>";
    $html .= " <span class=\"badge bg-secondary badge-lg\">" . htmlspecialchars($result_count) . " result" . ($result_count > 1 ? 's' : '') . "</span>";
    
    if ($desc) {
        $html .= "&nbsp;<button class=\"btn btn-sm btn-link p-0 annotation-info-btn\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#" . htmlspecialchars($desc_id) . "\" aria-expanded=\"false\">";
        $html .= "<i class=\"fas fa-info-circle\"></i>";
        $html .= "</button>";
    }
    
    $html .= "</h5>";
    $html .= '<div class="d-flex gap-2 align-items-center">';
    $html .= '<div id="' . htmlspecialchars($table_id) . '_filter" class="dataTables_filter"></div>';
    $html .= '</div>';
    $html .= '</div>';
    
    if ($desc) {
        $html .= '<div class="collapse mb-3" id="' . htmlspecialchars($desc_id) . '">';
        $html .= '<div class="alert alert-info mb-0 font-size-xsmall">';
        $html .= $desc;
        $html .= '</div>';
        $html .= '</div>';
    }
    
    // Table with DataTables
    $html .= "<div class=\"table-responsive\">";
    $html .= "<table id=\"" . htmlspecialchars($table_id) . "\" class=\"table table-sm table-striped table-hover substring-search\" style=\"width:100%;\">";
    $html .= "<thead><tr>";
    $html .= "<th class=\"export-only\">Organism</th>";
    $html .= "<th class=\"export-only\">Feature ID</th>";
    $html .= "<th class=\"export-only\">Feature Type</th>";
    $html .= "<th class=\"export-only\">Annotation Type</th>";
    $html .= "<th>Annotation ID</th>";
    $html .= "<th>Description</th>";
    $html .= "<th>Score</th>";
    $html .= "<th>Source</th>";
    $html .= "</tr></thead>";
    $html .= "<tbody>";
    
    foreach ($results as $row) {
        $hit_id = htmlspecialchars(trim($row['annotation_accession']));
        $hit_description = htmlspecialchars($row['annotation_description']);
        // feature_annotation.score is REAL and NULLABLE since the 2026-07-24 schema change
        // (df088cb). It used to be TEXT NOT NULL, where annotation types carrying no score
        // stored the literal string "-" -- 43% of rows in the sampled organism. Those load
        // as NULL now, so without this the column renders as a blank cell where it used to
        // read "-", and the reader cannot tell "no score" from "we failed to show it".
        // Also keeps NULL out of htmlspecialchars(), which is deprecated in PHP 8.1+.
        $hit_score = ($row['score'] === null || $row['score'] === '')
            ? '<span class="text-muted">—</span>'
            : htmlspecialchars((string) $row['score']);
        $annotation_source = htmlspecialchars($row['annotation_source_name']);
        $annotation_accession_url = htmlspecialchars(trim($row['annotation_accession_url']));
        $hit_id_link = $annotation_accession_url . urlencode(trim($row['annotation_accession']));
        
        $html .= "<tr>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($organism) . "</td>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($uniquename) . "</td>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($type) . "</td>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($annotation_type) . "</td>";
        $html .= "<td><a href=\"" . htmlspecialchars($hit_id_link) . "\" target=\"_blank\">" . $hit_id . "</a></td>";
        $html .= "<td>" . $hit_description . "</td>";
        $html .= "<td>" . $hit_score . "</td>";
        $html .= "<td>" . $annotation_source . "</td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table>";
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * Get all annotations for multiple features at once (optimized)
 * Fetches annotations for multiple features in a single query
 * Optionally filters by genome_ids for permission-based access
 *
 * @param array $feature_ids - Array of feature IDs to fetch annotations for
 * @param string $dbFile - Path to SQLite database
 * @param array $gene_set_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
 * @return array - Organized as [$feature_id => [$annotation_type => [results]]]
 */
function getAllAnnotationsForFeatures($feature_ids, $dbFile, $gene_set_ids = []) {
    if (empty($feature_ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($feature_ids), '?'));
    
    // Build WHERE clause with optional genome filtering
    $where_clause = "f.feature_id IN ($placeholders)";
    $params = $feature_ids;
    
    if (!empty($gene_set_ids)) {
        $gene_set_placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
        $where_clause .= " AND f.gene_set_id IN ($gene_set_placeholders)";
        $params = array_merge($params, $gene_set_ids);
    }

    $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type,
              a.annotation_accession, a.annotation_description,
              fa.score, " . moop_annotation_date_expr($dbFile) . " AS date,
              ans.annotation_source_name, ans.annotation_accession_url, ans.annotation_type
        FROM annotation a, feature f, feature_annotation fa, annotation_source ans, gene_set gs, genome g, organism o
        WHERE f.organism_id = o.organism_id
          AND f.gene_set_id = gs.gene_set_id
          AND gs.genome_id = g.genome_id
          AND ans.annotation_source_id = a.annotation_source_id
          AND f.feature_id = fa.feature_id
          AND fa.annotation_id = a.annotation_id
          AND $where_clause
        ORDER BY f.feature_id, ans.annotation_type";
    
    $results = fetchData($query, $dbFile, $params);
    
    // Organize by feature_id and annotation_type
    $organized = [];
    foreach ($results as $row) {
        $feature_id = $row['feature_id'];
        $annotation_type = $row['annotation_type'];
        
        if (!isset($organized[$feature_id])) {
            $organized[$feature_id] = [];
        }
        if (!isset($organized[$feature_id][$annotation_type])) {
            $organized[$feature_id][$annotation_type] = [];
        }
        
        $organized[$feature_id][$annotation_type][] = $row;
    }
    
    return $organized;
}

/**
 * Generate tree-style HTML for feature hierarchy
 * Creates a hierarchical list with box-drawing characters (like Unix 'tree' command)
 *
 * Renders the tree ALREADY BUILT by getChildrenHierarchical() — it does not fetch
 * anything. It used to take a feature_id and re-walk the hierarchy itself, one query
 * per node, which meant the gene page built the same tree twice by two different
 * routes. Worse, the two routes had different security postures: the page called this
 * with four arguments, so $gene_set_ids defaulted to [], and getChildrenByFeatureId()
 * drops its "gene_set_id IN (...)" clause entirely when that array is empty. The
 * second walk was therefore NOT access-filtered.
 *
 * Taking the built array also retires this function's own cycle guard (added in
 * 8ef74d4 after self-parented features recursed until PHP ran out of memory — a 500
 * on the gene page for every one of Schmidtea_nova's 13,218 self-parented genes).
 * A finite array cannot recurse forever; the cycle handling now lives in one place,
 * getChildren()'s CTE and _buildChildTree()'s visited set.
 *
 * @param array $children        Nodes from getChildrenHierarchical() (each may carry
 *                               a 'grandchildren' array)
 * @param array $all_annotations Annotation counts keyed by feature_id
 * @param array $analysis_order  Annotation-type order, for the anchor link
 * @param int   $depth           Internal use for recursion
 * @return string - HTML string with nested ul/li tree structure
 */
function generateTreeHTML(array $children, $all_annotations = [], $analysis_order = [], $depth = 0) {
    if ($depth >= MOOP_HIERARCHY_MAX_DEPTH || empty($children)) {
        return '';
    }

    $results = array_values($children);

    $html = "<ul>";
    $total = count($results);

    foreach ($results as $index => $row) {
        $is_last_child = ($index === $total - 1);

        $feature_type = htmlspecialchars($row['feature_type']);
        $feature_name = htmlspecialchars($row['feature_uniquename']);
        $child_feature_id = $row['feature_id'];
        
        // Color code badges by feature type
        $badge_class = 'bg-secondary';
        $text_color = 'text-white';
        
        if ($feature_type == 'mRNA') {
            $badge_class = 'bg-feature-mrna';
            $text_color = 'text-white';
        } elseif ($feature_type == 'CDS') {
            $badge_class = 'bg-info';
            $text_color = 'text-white';
        } elseif ($feature_type == 'exon') {
            $badge_class = 'bg-warning';
            $text_color = 'text-dark';
        } elseif ($feature_type == 'gene') {
            $badge_class = 'bg-feature-gene';
            $text_color = 'text-white';
        } elseif ($feature_type == 'protein' || $feature_type == 'polypeptide') {
            $badge_class = 'bg-feature-protein';
            $text_color = 'text-white';
        }
        
        // Calculate annotation count for this child
        $child_annot_count = 0;
        if (isset($all_annotations[$child_feature_id])) {
            foreach ($all_annotations[$child_feature_id] as $annotation_type => $annots) {
                $child_annot_count += count($annots);
            }
        }
        
        // Generate anchor for first annotation type (or use first one available)
        $child_annot_anchor = preg_replace('/[^a-zA-Z0-9_]/', '_', $row['feature_uniquename'] . '_' . ($analysis_order[0] ?? 'annotation'));
        
        // Tree character - └── for last child, ├── for others
        $tree_char = $is_last_child ? '└── ' : '├── ';
        
        $html .= "<li>";
        $html .= "<span class=\"tree-char\">$tree_char</span>";
        $html .= "<a href=\"#annot_section_$child_annot_anchor\" class=\"link-light-bordered text-decoration-none\"><span class=\"text-dark\">$feature_name</span></a> ";
        $html .= "<span class=\"badge $badge_class $text_color\">$feature_type</span>";
        
        // Add annotation count badge if there are annotations
        if ($child_annot_count > 0) {
            $html .= " <span class=\"badge bg-success text-white badge-sm\">$child_annot_count annotation" . ($child_annot_count > 1 ? 's' : '') . "</span>";
        }
        
        // Nested children were fetched with the parent, in the same CTE.
        $html .= generateTreeHTML($row['grandchildren'] ?? [], $all_annotations, $analysis_order, $depth + 1);
        $html .= "</li>";
    }
    $html .= "</ul>";

    return $html;
}

/**
 * Generate nested child annotation cards (recursive)
 * Renders child and grandchild features with their annotations in nested card structure
 * Each level has its own collapsible card with unique color based on feature type
 *
 * @param array $child - Child feature array from hierarchical structure
 * @param array $all_annotations - Cached annotations organized by feature_id
 * @param array $analysis_order - Annotation types in order
 * @param array $annotation_colors - Color mapping for annotation types
 * @param array $annotation_labels - Display labels for annotation types
 * @param array $analysis_desc - Descriptions for annotation types
 * @param string $organism_name - Organism name for export
 * @param int &$count - Counter for unique table IDs (passed by reference)
 * @param bool $is_grandchild - Internal flag for styling grandchild level
 * @return string - HTML for child/grandchild annotation cards
 */
function generateChildAnnotationCards($child, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, &$count, $is_grandchild = false, $annotated_child_types = []) {
    $child_feature_id = $child['feature_id'];
    $child_uniquename = $child['feature_uniquename'];
    $child_type = $child['feature_type'];

    // Skip feature types that never carry annotations in this gene set
    if (!empty($annotated_child_types) && !in_array($child_type, $annotated_child_types)) {
        return '';
    }
    
    // Count annotations for this child
    $child_annotation_count = 0;
    foreach ($analysis_order as $annotation_type) {
        $annot_results = $all_annotations[$child_feature_id][$annotation_type] ?? [];
        if (!empty($annot_results)) {
            $child_annotation_count += count($annot_results);
        }
    }
    
    // Determine header styling based on feature type and nesting level
    $header_class = 'child-feature-header';
    $badge_class = 'bg-feature-mrna';
    
    if ($is_grandchild) {
        $header_class = 'child-feature-header grandchild-feature-header';
        if ($child_type == 'protein' || $child_type == 'polypeptide') {
            $badge_class = 'bg-feature-protein';
        } else {
            $badge_class = 'bg-secondary';
        }
    }
    
    $html = '<div class="card annotation-card border-info">';
    $html .= "  <div class=\"card-header d-flex align-items-center $header_class\">";
    $html .= "    <span class=\"collapse-section\" data-bs-toggle=\"collapse\" data-bs-target=\"#child_$child_feature_id\" aria-expanded=\"true\" role=\"button\">";
    $html .= "      <i class=\"fas fa-minus toggle-icon text-info\"></i>";
    $html .= "    </span>";
    $html .= "    <span class=\"ms-2 text-white px-2 py-1 rounded child-feature-badge $badge_class badge-md\">$child_uniquename ($child_type)</span>";
    
    // Annotation-type navigation now lives in the section sidebar (parent-nav.js),
    // so per-type chips are no longer shown next to the child name. Children with
    // no annotations still get a clear indicator here.
    if ($child_annotation_count === 0) {
        $html .= " <span class=\"badge bg-secondary ms-2\">No annotations</span>";
    }
    
    $html .= '  </div>';
    $html .= "  <div id=\"child_$child_feature_id\" class=\"collapse show\">";
    $html .= '    <div class="card-body">';
    
    $child_has_annotations = false;
    foreach ($analysis_order as $annotation_type) {
        $count++;
        $annot_results = $all_annotations[$child_feature_id][$annotation_type] ?? [];
        if (!empty($annot_results)) {
            $child_has_annotations = true;
            $color = $annotation_colors[$annotation_type] ?? 'warning';
            $display_label = $annotation_labels[$annotation_type] ?? $annotation_type;
            $html .= generateAnnotationTableHTML($annot_results, $child_uniquename, $child_type, $count, $display_label, $analysis_desc[$annotation_type] ?? '', $color, $organism_name, $annotation_type);
        }
    }
    
    if (!$child_has_annotations) {
        $type_label = ($child_type === 'mRNA') ? 'transcript' : strtolower($child_type);
        $html .= "<p class=\"text-muted\"><i class=\"fas fa-info-circle\"></i> No annotations loaded for this $type_label.</p>";
    }
    
    // Render grandchildren (recursively)
    if (!empty($child['grandchildren'])) {
        foreach ($child['grandchildren'] as $grandchild) {
            $html .= generateChildAnnotationCards($grandchild, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, $count, true, $annotated_child_types);
        }
    }
    
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';
    
    return $html;
}
?>
