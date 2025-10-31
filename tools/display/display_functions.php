<?php
/**
 * Display Functions
 * Functions specific to displaying data in the web interface
 */

/**
 * Generate modern annotation table with export buttons (like search results)
 * 
 * @param array $results - Annotation results
 * @param string $uniquename - Feature uniquename
 * @param string $type - Feature type
 * @param int $count - Table counter for unique IDs
 * @param string $annotation_type - Type of annotation
 * @param string $desc - Description of annotation type
 * @param string $color - Bootstrap color class for badge
 * @param string $organism - Organism name (genus_species)
 * @return string HTML for the table
 */
function generateModernAnnotationTableHTML($results, $uniquename, $type, $count, $annotation_type, $desc, $color = 'warning', $organism = '') {
    if (empty($results)) {
        return '';
    }
    
    $table_id = "annotTable_$count";
    $result_count = count($results);
    
    // Determine text color based on background color
    $text_color = in_array($color, ['warning', 'info', 'secondary']) ? 'text-dark' : 'text-white';
    
    // Border color matches badge color
    $border_class = "border-$color";
    
    // Create unique ID for this annotation section
    $section_id = "annot_section_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $uniquename . '_' . $annotation_type);
    
    $html = '<div class="annotation-section mb-3 ' . $border_class . '" id="' . $section_id . '">';
    $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
    $html .= "<h5 class=\"mb-0\"><span class=\"badge bg-$color $text_color\" style=\"font-size: 1rem; padding: 0.5rem 0.75rem;\">$annotation_type</span> ";
    $html .= "<span class=\"badge bg-secondary\" style=\"font-size: 1rem; padding: 0.5rem 0.75rem;\">$result_count result" . ($result_count > 1 ? 's' : '') . "</span></h5>";
    
    if ($desc) {
        $html .= "<i class=\"fas fa-info-circle text-info\" data-toggle=\"tooltip\" title=\"" . htmlspecialchars($desc) . "\" style=\"font-size: 1.1rem;\"></i>";
    }
    
    $html .= '</div>';
    
    // Table with DataTables
    $html .= "<div class=\"table-responsive\">";
    $html .= "<table id=\"$table_id\" class=\"table table-sm table-striped table-hover\" style=\"width:100%; font-size: 13px;\">";
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
        $annotation_accession_url = $row['annotation_accession_url'];
        $hit_id_link = str_replace(' ', '', $annotation_accession_url . $row['annotation_accession']);
        
        $html .= "<tr>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($organism) . "</td>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($uniquename) . "</td>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($type) . "</td>";
        $html .= "<td class=\"export-only\">" . htmlspecialchars($annotation_type) . "</td>";
        $html .= "<td><a href=\"$hit_id_link\" target=\"_blank\">$hit_id</a></td>";
        $html .= "<td>$hit_description</td>";
        $html .= "<td>$hit_score</td>";
        $html .= "<td>$annotation_source</td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table>";
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * Get all annotations for multiple features at once (optimized)
 * 
 * @param array $feature_ids - Array of feature IDs
 * @param string $dbFile - Path to the SQLite database
 * @return array - Associative array keyed by feature_id, then annotation_type
 */
function getAllAnnotationsForFeatures($feature_ids, $dbFile) {
    if (empty($feature_ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($feature_ids), '?'));
    
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
          AND f.feature_id IN ($placeholders)
        ORDER BY f.feature_id, ans.annotation_type";
    
    $results = fetchData($query, $feature_ids, $dbFile);
    
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
 * Generate a bash tree-style HTML for feature hierarchy
 * Uses box-drawing characters like the Unix 'tree' command
 * 
 * @param int $feature_id - The parent feature ID
 * @param string $dbFile - Path to the SQLite database
 * @param string $prefix - Internal use for recursion, tracks the tree prefix
 * @param bool $is_last - Internal use for recursion, indicates if this is the last child
 * @return string HTML string with tree structure
 */
function generateBashStyleTreeHTML($feature_id, $dbFile, $prefix = '', $is_last = true) {
    $query = "SELECT feature_id, feature_uniquename, feature_type, parent_feature_id 
              FROM feature WHERE parent_feature_id = ?";
    $results = fetchData($query, [$feature_id], $dbFile);

    if (empty($results)) {
        return ''; // No children, stop recursion
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
        }
        
        // Tree character - └── for last child, ├── for others
        $tree_char = $is_last_child ? '└── ' : '├── ';
        
        $html .= "<li>";
        $html .= "<span class=\"tree-char\" style=\"color: #495057; font-weight: bold;\">$tree_char</span>";
        $html .= "<span class=\"text-dark\">$feature_name</span> ";
        $html .= "<span class=\"badge $badge_class $text_color\" style=\"font-size: 0.85em;\">$feature_type</span>";
        
        // Recursive call for nested children
        $html .= generateBashStyleTreeHTML($row['feature_id'], $dbFile, $prefix, $is_last_child);
        $html .= "</li>";
    }
    $html .= "</ul>";

    return $html;
}

?>
