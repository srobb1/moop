<?php
/**
 * Parent Feature Display Functions
 * Functions for displaying parent feature data, hierarchies, and annotations
 * Used by parent_display.php to render feature information
 */

/**
 * Get hierarchy of features (ancestors)
 * Traverses up the feature hierarchy from a given feature to its parents/grandparents
 * Optionally filters by genome_ids for permission-based access
 *
 * @param string $feature_uniquename - The feature uniquename to start from
 * @param string $dbFile - Path to SQLite database
 * @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
 * @return array - Array of features: [self, parent, grandparent, ...]
 */
function getAncestors($feature_uniquename, $dbFile, $genome_ids = []) {
    $feature = getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids);
    
    if (empty($feature)) {
        return [];
    }
    
    $ancestors = [$feature];
    
    if ($feature['parent_feature_id']) {
        $parent_ancestors = getAncestorsByFeatureId($feature['parent_feature_id'], $dbFile, $genome_ids);
        $ancestors = array_merge($ancestors, $parent_ancestors);
    }
    
    return $ancestors;
}

/**
 * Helper function for recursive ancestor traversal
 * Fetches ancestors by feature_id (used internally by getAncestors)
 * Optionally filters by genome_ids for permission-based access
 *
 * @param int $feature_id - The feature ID to start from
 * @param string $dbFile - Path to SQLite database
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Array of ancestor features
 */
function getAncestorsByFeatureId($feature_id, $dbFile, $genome_ids = []) {
    $feature = getParentFeature($feature_id, $dbFile, $genome_ids);
    
    if (empty($feature)) {
        return [];
    }
    
    $ancestors = [$feature];
    
    if ($feature['parent_feature_id']) {
        $parent_ancestors = getAncestorsByFeatureId($feature['parent_feature_id'], $dbFile, $genome_ids);
        $ancestors = array_merge($ancestors, $parent_ancestors);
    }
    
    return $ancestors;
}

/**
 * Get all children and descendants of a feature
 * Recursively fetches all child features at any depth
 * Optionally filters by genome_ids for permission-based access
 *
 * @param int $feature_id - The parent feature ID
 * @param string $dbFile - Path to SQLite database
 * @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
 * @return array - Flat array of all children and descendants
 */
function getChildren($feature_id, $dbFile, $genome_ids = []) {
    $children = [];
    
    $results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);
    
    foreach ($results as $row) {
        $children[] = $row;
        $child_descendants = getChildren($row['feature_id'], $dbFile, $genome_ids);
        $children = array_merge($children, $child_descendants);
    }
    return $children;
}

/**
 * Get children with hierarchical structure (preserves parent-child relationships)
 * Unlike getChildren() which returns flat array, this preserves nesting
 * Each child has a 'grandchildren' key containing its own children
 * Enables proper display of parent -> child -> grandchild hierarchies
 *
 * @param int $feature_id - The parent feature ID
 * @param string $dbFile - Path to SQLite database
 * @param array $genome_ids - Optional: Array of genome IDs to filter results
 * @return array - Array of children, each with 'grandchildren' key
 */
function getChildrenHierarchical($feature_id, $dbFile, $genome_ids = []) {
    $results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);
    
    foreach ($results as &$child) {
        $child['grandchildren'] = getChildrenHierarchical($child['feature_id'], $dbFile, $genome_ids);
    }
    
    return $results;
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
 * @return string - HTML for the annotation table section
 */
function generateAnnotationTableHTML($results, $uniquename, $type, $count, $annotation_type, $desc, $color = 'warning', $organism = '') {
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
    $section_id = "annot_section_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $uniquename . '_' . $annotation_type);
    
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
    $html .= '<a href="#sequencesSection" class="btn btn-sm btn-info" title="Jump to sequences section"><i class="fas fa-dna"></i> Jump to Sequences</a>';
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
    $html .= "<table id=\"" . htmlspecialchars($table_id) . "\" class=\"table table-sm table-striped table-hover\" style=\"width:100%;\">";
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
        $hit_id = htmlspecialchars($row['annotation_accession']);
        $hit_description = htmlspecialchars($row['annotation_description']);
        $hit_score = htmlspecialchars($row['score']);
        $annotation_source = htmlspecialchars($row['annotation_source_name']);
        $annotation_accession_url = htmlspecialchars($row['annotation_accession_url']);
        $hit_id_link = $annotation_accession_url . urlencode($row['annotation_accession']);
        
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
 * @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
 * @return array - Organized as [$feature_id => [$annotation_type => [results]]]
 */
function getAllAnnotationsForFeatures($feature_ids, $dbFile, $genome_ids = []) {
    if (empty($feature_ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($feature_ids), '?'));
    
    // Build WHERE clause with optional genome filtering
    $where_clause = "f.feature_id IN ($placeholders)";
    $params = $feature_ids;
    
    if (!empty($genome_ids)) {
        $genome_placeholders = implode(',', array_fill(0, count($genome_ids), '?'));
        $where_clause .= " AND f.genome_id IN ($genome_placeholders)";
        $params = array_merge($params, $genome_ids);
    }
    
    $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, 
              a.annotation_accession, a.annotation_description, 
              fa.score, fa.date, 
              ans.annotation_source_name, ans.annotation_accession_url, ans.annotation_type
        FROM annotation a, feature f, feature_annotation fa, annotation_source ans, genome g, organism o
        WHERE f.organism_id = o.organism_id
          AND f.genome_id = g.genome_id
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
 * @param int $feature_id - The parent feature ID
 * @param string $dbFile - Path to SQLite database
 * @param string $prefix - Internal use for recursion
 * @param bool $is_last - Internal use for recursion
 * @return string - HTML string with nested ul/li tree structure
 */
function generateTreeHTML($feature_id, $dbFile, $prefix = '', $is_last = true, $genome_ids = []) {
    $results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);

    if (empty($results)) {
        return '';
    }
    
    $html = "<ul>";
    $total = count($results);
    
    foreach ($results as $index => $row) {
        $is_last_child = ($index === $total - 1);
        
        $feature_type = htmlspecialchars($row['feature_type']);
        $feature_name = htmlspecialchars($row['feature_uniquename']);
        
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
        
        // Tree character - └── for last child, ├── for others
        $tree_char = $is_last_child ? '└── ' : '├── ';
        
        $html .= "<li>";
        $html .= "<span class=\"tree-char\">$tree_char</span>";
        $html .= "<span class=\"text-dark\">$feature_name</span> ";
        $html .= "<span class=\"badge $badge_class $text_color\">$feature_type</span>";
        
        // Recursive call for nested children
        $html .= generateTreeHTML($row['feature_id'], $dbFile, $prefix, $is_last_child, $genome_ids);
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
function generateChildAnnotationCards($child, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, &$count, $is_grandchild = false) {
    $child_feature_id = $child['feature_id'];
    $child_uniquename = $child['feature_uniquename'];
    $child_type = $child['feature_type'];
    
    // Count annotations for this child
    $child_annotation_count = 0;
    $child_annotation_types = [];
    foreach ($analysis_order as $annotation_type) {
        $annot_results = $all_annotations[$child_feature_id][$annotation_type] ?? [];
        if (!empty($annot_results)) {
            $child_annotation_count += count($annot_results);
            $child_annotation_types[$annotation_type] = count($annot_results);
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
    $html .= "    <span class=\"collapse-section\" data-bs-toggle=\"collapse\" data-bs-target=\"#child_$child_feature_id\" aria-expanded=\"true\">";
    $html .= "      <i class=\"fas fa-minus toggle-icon text-info\"></i>";
    $html .= "    </span>";
    $html .= "    <strong class=\"ms-2 text-dark\"><span class=\"text-white px-2 py-1 rounded child-feature-badge $badge_class badge-xlg\">$child_uniquename ($child_type)</span></strong>";
    
    // Show colored annotation type badges
    if ($child_annotation_count > 0) {
        foreach ($child_annotation_types as $type_name => $type_count) {
            $badge_color = $annotation_colors[$type_name] ?? 'warning';
            $text_color = in_array($badge_color, ['warning', 'info', 'secondary']) ? 'text-dark' : 'text-white';
            $display_label = $annotation_labels[$type_name] ?? $type_name;
            $section_id = "annot_section_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $child_uniquename . '_' . $type_name);
            $html .= " <a href=\"#$section_id\" class=\"badge bg-$badge_color $text_color ms-1 text-decoration-none badge-s\" style=\"cursor: pointer;\">$display_label</a>";
        }
    } else {
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
            $html .= generateAnnotationTableHTML($annot_results, $child_uniquename, $child_type, $count, $display_label, $analysis_desc[$annotation_type] ?? '', $color, $organism_name);
        }
    }
    
    if (!$child_has_annotations) {
        $type_label = ($child_type === 'mRNA') ? 'transcript' : strtolower($child_type);
        $html .= "<p class=\"text-muted\"><i class=\"fas fa-info-circle\"></i> No annotations loaded for this $type_label.</p>";
    }
    
    // Render grandchildren (recursively)
    if (!empty($child['grandchildren'])) {
        foreach ($child['grandchildren'] as $grandchild) {
            $html .= generateChildAnnotationCards($grandchild, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, $count, true);
        }
    }
    
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';
    
    return $html;
}
?>
