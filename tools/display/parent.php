<?php
// Include access control and configuration
include_once __DIR__ . '/../../access_control.php';
include_once realpath(__DIR__ . '/../../site_config.php');
include_once realpath(__DIR__ . '/../common_functions.php');
include_once __DIR__ . '/display_functions.php';

// Get parameters - support both old and new parameter formats
// Old format: ?name=GENE123
// New format: ?organism=Organism_name&uniquename=GENE123
$organism_name = $_GET['organism'] ?? '';
$uniquename = test_input($_GET['uniquename'] ?? $_GET['name'] ?? '');

if (empty($uniquename)) {
    die("Error: No feature identifier provided. Please provide a uniquename or name parameter.");
}

// Determine database path
$db = null;
$organism_info = null;

if (!empty($organism_name)) {
    // New format: organism is specified
    $organism_json_path = "$organism_data/$organism_name/organism.json";
    if (file_exists($organism_json_path)) {
        $organism_info = json_decode(file_get_contents($organism_json_path), true);
    }
    
    // Try organism-specific database first
    $db_path = "$organism_data/$organism_name/$organism_name.genes.sqlite";
    if (!file_exists($db_path)) {
        // Fallback to genes.sqlite
        $db_path = "$organism_data/$organism_name/genes.sqlite";
    }
    
    if (file_exists($db_path)) {
        $db = $db_path;
    }
} else {
    // Old format: search for the feature across all organism databases
    $organisms = glob("$organism_data/*", GLOB_ONLYDIR);
    foreach ($organisms as $org_dir) {
        $org_name = basename($org_dir);
        $db_paths = [
            "$org_dir/$org_name.genes.sqlite",
            "$org_dir/genes.sqlite"
        ];
        
        foreach ($db_paths as $test_db) {
            if (file_exists($test_db)) {
                // Check if the feature exists in this database
                $query = "SELECT feature_uniquename FROM feature WHERE feature_uniquename = ? LIMIT 1";
                try {
                    $results = fetchData($query, [$uniquename], $test_db);
                    if (!empty($results)) {
                        $db = $test_db;
                        $organism_name = $org_name;
                        $organism_json_path = "$org_dir/organism.json";
                        if (file_exists($organism_json_path)) {
                            $organism_info = json_decode(file_get_contents($organism_json_path), true);
                        }
                        break 2;
                    }
                } catch (Exception $e) {
                    // Continue searching
                    continue;
                }
            }
        }
    }
}

if (!$db || !file_exists($db)) {
    die("Error: Could not find database for the specified feature.");
}

// Check access control
if (!empty($organism_name)) {
    $is_public = is_public_organism($organism_name);
    $has_organism_access = has_access('Collaborator', $organism_name);
    
    if (!$has_organism_access && !$is_public) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}

// Load annotation configuration
$annotation_config_file = "$organism_data/annotation_config.json";
$analysis_order = [];
$analysis_desc = [];
$annotation_colors = [];

if (file_exists($annotation_config_file)) {
    $annotation_config = json_decode(file_get_contents($annotation_config_file), true);
    
    // New format with annotation_types
    if (isset($annotation_config['annotation_types'])) {
        $types = $annotation_config['annotation_types'];
        // Sort by order
        uasort($types, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        foreach ($types as $key => $config) {
            if ($config['enabled'] ?? true) {
                $analysis_order[] = $key;
                $analysis_desc[$key] = $config['description'] ?? '';
                $annotation_colors[$key] = $config['color'] ?? 'secondary';
            }
        }
    }
    // Legacy format fallback
    else {
        $analysis_order = $annotation_config['analysis_order'] ?? [];
        $analysis_desc = $annotation_config['analysis_descriptions'] ?? [];
        // Default colors for legacy
        foreach ($analysis_order as $type) {
            $annotation_colors[$type] = 'warning';
        }
    }
}

// Define parent types (typically genes are parent features)
$parents = ['gene', 'pseudogene'];

// Get ancestors for the feature
$ancestors = getAncestors($uniquename, $db);

// Save the highest ancestor with type in $parents in these variables
[$ancestor_feature_id, $ancestor_feature_uniquename, $ancestor_feature_type] = ['', '', ''];

if (count($ancestors) == 1) {
    // self only, no parents
    $ancestor = $ancestors[0];
    $ancestor_feature_id = $ancestor['feature_id'];
    $ancestor_feature_type = $ancestor['feature_type'];
    $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
    $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
} elseif (count($ancestors) > 1) {
    // self, plus at least one ancestor
    foreach ($ancestors as $ancestor) {
        $ancestor_feature_id = $ancestor['feature_id'];
        $ancestor_feature_type = $ancestor['feature_type'];
        $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
        $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
        if (in_array($ancestor_feature_type, $parents)) {
            // Stop: we reached our valid parent type for a page
            break;
        }
    }
}

// Performing SQL query to get info associated with found Parent ID
$query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, f.feature_type, f.parent_feature_id, 
          o.genus, o.species, o.subtype, o.common_name, o.taxon_id, g.genome_accession, g.genome_name
  FROM genome g, organism o, feature f
  WHERE f.organism_id = o.organism_id  
    AND f.genome_id = g.genome_id
    AND f.feature_id = ?";

$params = [$ancestor_feature_id];
$results = fetchData($query, $params, $db);

// Get all info about Highest Parent
if (count($results) == 0) { 
    die("The gene $uniquename was not found in the database. Please, check the spelling carefully or try to find it in the search tool.");
} elseif (count($results) != 1) {
    die("Multiple results found for $uniquename. Please contact the administrator.");
}

$row = array_shift($results);
$feature_id = $row['feature_id'];
$feature_uniquename = $row['feature_uniquename'];
$parent_id = $row['parent_feature_id'];
$name = $row['feature_name'];
$description = $row['feature_description'];      
$genus = $row['genus'];
$species = $row['species'];
$species_subtype = $row['subtype'];
$type = $row['feature_type'];
$common_name = $row['common_name'];
$genome_accession = $row['genome_accession'];
$genome_name = $row['genome_name'];

$family_feature_ids = [$feature_id];
$retrieve_these_seqs = [$feature_uniquename];

// Get children
$children = getChildren($feature_id, $db);

// Optimize: Get ALL annotations for parent and all children in ONE query
$all_feature_ids = [$feature_id];
foreach ($children as $child) {
    $all_feature_ids[] = $child['feature_id'];
}
$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);

include_once realpath(__DIR__ . '/../../header.php');
include_once realpath(__DIR__ . '/../../toolbar.php');
?>

<title><?= htmlspecialchars($feature_uniquename) ?> - <?= $siteTitle ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

<style>
    .feature-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
    }
    .info-table th {
        width: 200px;
        background-color: #f8f9fa;
    }
    .collapse-section {
        cursor: pointer;
        user-select: none;
    }
    .card-header {
        background-color: #f8f9fa;
    }
    .annotation-card {
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    .annotation-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.1);
        transform: translateX(5px);
    }
    .page_container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    .badge {
        vertical-align: middle;
    }
    .tree-container {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        font-size: 14px;
        line-height: 1.8;
    }
    .tree-container .tree-char {
        font-family: 'Courier New', Courier, monospace;
        display: inline-block;
        vertical-align: middle;
        line-height: 1;
    }
    /* Override global tree.css */
    .tree-container .tree ul {
        list-style-type: none !important;
        margin: 0 !important;
        padding: 0 0 0 20px !important;
        border: none !important;
        background: none !important;
    }
    .tree-container .tree li {
        margin: 0 !important;
        padding: 2px 0 !important;
        border: none !important;
        background: none !important;
        position: static !important;
    }
    /* Feature type colors */
    .feature-color-gene {
        color: #764ba2 !important;
    }
    .bg-feature-gene {
        background-color: #764ba2 !important;
    }
    .feature-color-mrna {
        color: #17a2b8 !important;
    }
    .bg-feature-mrna {
        background-color: #17a2b8 !important;
    }
    .tree-container .tree li::before,
    .tree-container .tree li::after {
        display: none !important;
        content: none !important;
        border: none !important;
    }
    .tree-container .tree .indicator {
        display: none !important;
    }
    .tree-container .tree .branch {
        border: none !important;
        background: none !important;
        padding: 0 !important;
    }
    .tree-container .tree a {
        text-decoration: none !important;
    }
    /* Modern annotation tables */
    .annotation-section {
        border-left: 3px solid;
        padding-left: 1rem;
        margin-bottom: 1.5rem;
    }
    .annotation-section.border-primary { border-left-color: #007bff; }
    .annotation-section.border-secondary { border-left-color: #6c757d; }
    .annotation-section.border-success { border-left-color: #28a745; }
    .annotation-section.border-danger { border-left-color: #dc3545; }
    .annotation-section.border-warning { border-left-color: #ffc107; }
    .annotation-section.border-info { border-left-color: #17a2b8; }
    .annotation-section.border-purple { border-left-color: #6f42c1; }
    .annotation-section.border-dark { border-left-color: #343a40; }
    .annotation-section .table {
        margin-bottom: 0;
    }
    .annotation-section h6 {
        font-weight: 600;
    }
    /* Compact DataTables buttons */
    .dt-buttons {
        margin-bottom: 0.5rem;
    }
    .dt-button {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.875rem !important;
        margin-right: 0.25rem !important;
    }
    /* Clean table styling */
    table.dataTable thead th {
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 0.875rem;
    }
    table.dataTable tbody td {
        padding: 0.5rem !important;
    }
    /* Purple badge support */
    .bg-purple {
        background-color: #6f42c1 !important;
    }
    .badge.bg-purple {
        color: #fff;
    }
    /* Hide export-only columns in display */
    th.export-only,
    td.export-only {
        display: none;
    }
    /* Smooth scrolling for anchor links */
    html {
        scroll-behavior: smooth;
    }
    /* Hover effect for clickable annotation badges */
    a.badge:hover {
        opacity: 0.85;
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
    /* Highlight animation for scrolled-to annotation sections */
    .annotation-section:target {
        animation: highlight-fade 2s ease-in-out;
    }
    @keyframes highlight-fade {
        0% { background-color: rgba(255, 193, 7, 0.3); }
        100% { background-color: transparent; }
    }
</style>

<div class="page_container">

<!-- Navigation -->
<div class="margin-20">
    <div class="mb-3">
        <?php if (!empty($organism_name)): ?>
            <a href="/<?= $site ?>/tools/display/organism_display.php?organism=<?= urlencode($organism_name) ?>" 
               class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to <em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em>
            </a>
        <?php endif; ?>
        <a href="/<?= $site ?>/index.php" class="btn btn-secondary">
            <i class="fa fa-home"></i> Home
        </a>
    </div>

    <!-- Feature Header -->
    <div class="feature-header shadow">
        <h1 class="mb-3">
            <?= htmlspecialchars($feature_uniquename) ?>
            <?php if (!empty($children) && count($children) > 0): ?>
                <span class="badge text-white ms-2" style="font-size: 0.5em; background-color: #17a2b8;">
                    <?= count($children) ?> child<?= count($children) > 1 ? 'ren' : '' ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="mb-2"><?= htmlspecialchars($description) ?></p>
        <p class="mb-2"><strong>Type:</strong> <?= htmlspecialchars($type) ?></p>
        <p class="mb-2"><strong>Organism:</strong> <em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em></p>
        <?php if ($common_name): ?>
            <p class="mb-0"><strong>Common Name:</strong> <?= htmlspecialchars($common_name) ?></p>
        <?php endif; ?>
        <p class="mb-2"><strong>Assembly:</strong> <?= htmlspecialchars($genome_name) ?></p>
    </div>


    <!-- Feature Hierarchy Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <div class="collapse-section" data-toggle="collapse" data-target="#hierarchySection" aria-expanded="true">
                <i class="fas fa-sitemap toggle-icon text-primary"></i>
                <strong class="ms-2">Feature Hierarchy</strong>
                <?php if (!empty($children)): ?>
                    <span class="badge bg-info ms-2"><?= count($children) ?> child feature<?= count($children) > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div id="hierarchySection" class="collapse show">
            <div class="card-body">
                <div class="tree-container">
                    <div class="tree">
                        <ul id="tree1">
                            <li>
                                <span class="feature-color-gene"><strong><?= htmlspecialchars($feature_uniquename) ?></strong></span> 
                                <span class="badge bg-feature-gene text-white" style="font-size: 0.85em;"><?= htmlspecialchars($type) ?></span>
                                <?= generateBashStyleTreeHTML($feature_id, $db) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Annotations Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <div class="collapse-section" data-toggle="collapse" data-target="#annotationsSection" aria-expanded="true">
                <i class="fas fa-minus toggle-icon text-primary"></i>
                <strong class="ms-2">Annotations</strong>
            </div>
        </div>
        <div id="annotationsSection" class="collapse show">
            <div class="card-body">
                <?php
                // Parent annotations - using cached results
                $count = 0;
                $has_annotations = false;
                
                foreach ($analysis_order as $annotation_type) {
                    $count++;
                    $annot_results = $all_annotations[$feature_id][$annotation_type] ?? [];
                    if (!empty($annot_results)) {
                        $has_annotations = true;
                        $color = $annotation_colors[$annotation_type] ?? 'warning';
                        echo generateModernAnnotationTableHTML($annot_results, $feature_uniquename, $type, $count, $annotation_type, $analysis_desc[$annotation_type] ?? '', $color, $organism_name);
                    }
                }
                
                // Children annotations
                if (!empty($children)) {
                    // Add summary if multiple children
                    if (count($children) > 1) {
                        echo '<div class="alert alert-info mt-3">';
                        echo '  <i class="fas fa-info-circle"></i> ';
                        echo '  <strong>Multiple Children:</strong> This gene has ' . count($children) . ' alternative transcripts/isoforms (mRNA). ';
                        echo '  Each may have different annotations.';
                        echo '</div>';
                    }
                    
                    foreach ($children as $child_index => $child) {
                        $child_feature_id = $child['feature_id'];
                        $child_uniquename = $child['feature_uniquename'];
                        $child_type = $child['feature_type'];
                        
                        // Count annotations for this child - using cached results
                        $child_annotation_count = 0;
                        $child_annotation_types = [];
                        foreach ($analysis_order as $annotation_type) {
                            $annot_results = $all_annotations[$child_feature_id][$annotation_type] ?? [];
                            if (!empty($annot_results)) {
                                $child_annotation_count += count($annot_results);
                                $child_annotation_types[$annotation_type] = count($annot_results);
                            }
                        }
                        
                        echo '<div class="card annotation-card border-info">';
                        echo '  <div class="card-header" style="background-color: rgba(23, 162, 184, 0.1);">';
                        echo "    <div class=\"collapse-section\" data-bs-toggle=\"collapse\" data-target=\"#child_$child_feature_id\" aria-expanded=\"true\">";
                        echo "      <i class=\"fas fa-minus toggle-icon text-info\"></i>";
                        echo "      <strong class=\"ms-2 text-dark\"><span class=\"text-white px-2 py-1 rounded\" style=\"background-color: #17a2b8;\">$child_uniquename</span> ($child_type)</strong>";
                        
                        // Show colored annotation type badges as clickable links
                        if ($child_annotation_count > 0) {
                            foreach ($child_annotation_types as $type_name => $type_count) {
                                $badge_color = $annotation_colors[$type_name] ?? 'warning';
                                $text_color = in_array($badge_color, ['warning', 'info', 'secondary']) ? 'text-dark' : 'text-white';
                                $section_id = "annot_section_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $child_uniquename . '_' . $type_name);
                                echo " <a href=\"#$section_id\" class=\"badge bg-$badge_color $text_color ms-1 text-decoration-none\" style=\"font-size: 0.75rem; cursor: pointer;\">$type_name</a>";
                            }
                        } else {
                            echo " <span class=\"badge bg-secondary ms-2\">No annotations</span>";
                        }
                        
                        echo '    </div>';
                        echo '  </div>';
                        echo "  <div id=\"child_$child_feature_id\" class=\"collapse show\">";
                        echo '    <div class="card-body">';
                        
                        $child_has_annotations = false;
                        foreach ($analysis_order as $annotation_type) {
                            $count++;
                            $annot_results = $all_annotations[$child_feature_id][$annotation_type] ?? [];
                            if (!empty($annot_results)) {
                                $child_has_annotations = true;
                                $has_annotations = true;
                                $color = $annotation_colors[$annotation_type] ?? 'warning';
                                echo generateModernAnnotationTableHTML($annot_results, $child_uniquename, $child_type, $count, $annotation_type, $analysis_desc[$annotation_type] ?? '', $color, $organism_name);
                            }
                        }
                        
                        if (!$child_has_annotations) {
                            echo "<p class=\"text-muted\"><i class=\"fas fa-info-circle\"></i> No annotations loaded for this transcript.</p>";
                        }
                        
                        echo '    </div>';
                        echo '  </div>';
                        echo '</div>';
                        
                        $retrieve_these_seqs[] = $child_uniquename;
                    }
                }
                
                if (!$has_annotations) {
                    echo '<p class="text-muted">No annotations available for this feature.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Sequences Section -->
    <?php
    $retrieve_these_seqs = array_unique($retrieve_these_seqs);
    sort($retrieve_these_seqs);
    $gene_name = implode(",", $retrieve_these_seqs);
    
    // Check if display_sequences.php exists
    $sequences_file = __DIR__ . '/../../display_sequences.php';
    if (file_exists($sequences_file)) {
        include_once $sequences_file;
    }
    ?>

</div>
</div><!-- End page_container -->

<!-- DataTables JS -->
<script src="/<?= $site ?>/js/datatable.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables for annotation tables with export buttons
    $('table[id^="annotTable_"]').each(function() {
        var tableId = '#' + $(this).attr('id');
        
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }
        
        $(tableId).DataTable({
            dom: 'Bfrtlip',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            buttons: [
                {
                    extend: 'copy',
                    text: '<i class="far fa-copy"></i> Copy',
                    className: 'btn btn-sm btn-secondary',
                    exportOptions: {
                        columns: ':visible, .export-only'
                    }
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-sm btn-secondary',
                    exportOptions: {
                        columns: ':visible, .export-only'
                    }
                },
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-sm btn-secondary',
                    exportOptions: {
                        columns: ':visible, .export-only'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-sm btn-secondary',
                    exportOptions: {
                        columns: ':visible, .export-only'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-sm btn-secondary',
                    exportOptions: {
                        columns: ':visible, .export-only'
                    }
                }
            ],
            order: [[4, 'asc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries"
            },
            columnDefs: [
                { targets: '_all', className: 'dt-body-left' },
                { targets: [0, 1, 2, 3], visible: false, className: 'export-only' }
            ]
        });
    });

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip({ html: true });

    // Tree functionality
    $.fn.extend({
        treed: function(o) {
            var openedClass = 'fa-minus';
            var closedClass = 'fa-plus';

            if (typeof o != 'undefined') {
                if (typeof o.openedClass != 'undefined') {
                    openedClass = o.openedClass;
                }
                if (typeof o.closedClass != 'undefined') {
                    closedClass = o.closedClass;
                }
            }

            var tree = $(this);
            tree.addClass("tree");
            tree.find('li').has("ul").each(function() {
                var branch = $(this);
                branch.prepend("<i class='indicator fa " + openedClass + "'></i>");
                branch.addClass('branch');
                branch.children("ul").show();

                branch.on('click', function(e) {
                    if (this == e.target) {
                        var icon = $(this).children('i:first');
                        icon.toggleClass(openedClass + " " + closedClass);
                        $(this).children("ul").toggle();
                    }
                });
            });

            tree.find('.branch .indicator').each(function() {
                $(this).on('click', function() {
                    $(this).closest('li').click();
                });
            });

            tree.find('.branch>a, .branch>button').each(function() {
                $(this).on('click', function(e) {
                    $(this).closest('li').click();
                    e.preventDefault();
                });
            });
        }
    });

    // Initialize tree - DISABLED: Using bash-style tree instead
    // $('#tree1').treed();

    // Toggle icons on collapse
    $('.collapse').on('show.bs.collapse', function(e) {
        if (e.target !== this) return;
        $('[data-target="#' + this.id + '"] .toggle-icon')
            .removeClass('fa-plus')
            .addClass('fa-minus');
    });

    $('.collapse').on('hide.bs.collapse', function(e) {
        if (e.target !== this) return;
        $('[data-target="#' + this.id + '"] .toggle-icon')
            .removeClass('fa-minus')
            .addClass('fa-plus');
    });
});
</script>

</body>
</html>

<?php
include_once __DIR__ . '/../../footer.php';
?>
