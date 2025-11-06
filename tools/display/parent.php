<?php
// Include access control and configuration
include_once __DIR__ . '/../../access_control.php';
include_once realpath(__DIR__ . '/../../site_config.php');
include_once realpath(__DIR__ . '/../moop_functions.php');
include_once __DIR__ . '/parent_functions.php';

// Get parameters - require new format
// Format: ?organism=Organism_name&uniquename=GENE123
$organism_name = $_GET['organism'] ?? '';
$uniquename = $_GET['uniquename'] ?? '';

if (empty($organism_name) || empty($uniquename)) {
    die("Error: Missing required parameters. Please provide both 'organism' and 'uniquename' parameters.");
}

// Determine database path
$db = null;
$organism_info = null;

// Load organism info
$organism_json_path = "$organism_data/$organism_name/organism.json";
if (file_exists($organism_json_path)) {
    $organism_info = json_decode(file_get_contents($organism_json_path), true);
}

// Use standardized database naming
$db_path = "$organism_data/$organism_name/$organism_name.genes.sqlite";

// TO DO: we are building a reports page to list all organisms that do not have a sqlite file. add that info to the message.
// TO DO: also add this error to MOOP system logging. 
// TO DO: we should find all Errors and warning messages and write them to the error log also. We need and error function that prints to the screen
// and logs the message in the log
if (!file_exists($db_path)) {
    die("Error: Database not found for organism '$organism_name'. Please ensure the organism is properly configured.");
}

$db = $db_path;

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
    
    // Require new format with annotation_types
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
    } else {
        die("Error: annotation_config.json must use the new 'annotation_types' format. Legacy format is no longer supported.");
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
<link rel="stylesheet" href="/<?= $site ?>/css/parent.css">

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
        <div class="d-flex align-items-start justify-content-between">
            <div class="flex-grow-1">
                <h1 class="mb-3">
                    <?= htmlspecialchars($feature_uniquename) ?>
                    <span class="badge text-white ms-2" style="font-size: 0.6em; background-color: rgba(255,255,255,0.3);">
                        <?= htmlspecialchars($type) ?>
                    </span>
                    <?php if (!empty($children) && count($children) > 0): 
                        $first_child_type = $children[0]['feature_type'] ?? 'mRNA';
                        $child_color_map = ['mRNA' => '#17a2b8', 'gene' => '#764ba2'];
                        $child_bg_color = $child_color_map[strtoupper($first_child_type)] ?? '#17a2b8';
                    ?>
                        <span class="badge text-white ms-2" style="font-size: 0.6em; background-color: <?= $child_bg_color ?>;">
                            <?= count($children) ?> <?= htmlspecialchars($first_child_type) ?> child<?= count($children) > 1 ? 'ren' : '' ?>
                        </span>
                    <?php endif; ?>
                </h1>
                <?php if (!empty($description)): ?>
                    <p class="mb-4 feature-description"><?= htmlspecialchars($description) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div>
            <div class="feature-info-item">
                <strong>Organism:</strong> <span class="feature-value"><a href="/<?= $site ?>/tools/display/organism_display.php?organism=<?= urlencode($organism_name) ?>&parent=<?= urlencode($feature_uniquename) ?>" style="color: inherit; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.3);"><em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em></a></span>
            </div>
            <?php if ($common_name): ?>
                <div class="feature-info-item">
                    <strong>Common Name:</strong> <span class="feature-value"><?= htmlspecialchars($common_name) ?></span>
                </div>
            <?php endif; ?>
            <div class="feature-info-item">
                <strong>Assembly:</strong> <span class="feature-value"><a href="/<?= $site ?>/tools/display/assembly_display.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&parent=<?= urlencode($feature_uniquename) ?>" style="color: inherit; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.3);"><?= htmlspecialchars($genome_name) ?> (<?= htmlspecialchars($genome_accession) ?>)</a></span>
            </div>
        </div>
    </div>


    <!-- Feature Hierarchy Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#hierarchySection" aria-expanded="true">
                <i class="fas fa-sitemap toggle-icon text-primary"></i>
            </span>
            <strong class="ms-2">Feature Hierarchy</strong>
        </div>
        <div id="hierarchySection" class="collapse show">
            <div class="card-body">
                <div class="tree-container">
                    <div class="tree">
                        <ul id="tree1">
                            <li>
                                <span class="feature-color-gene"><strong><?= htmlspecialchars($feature_uniquename) ?></strong></span> 
                                <span class="badge bg-feature-gene text-white" style="font-size: 0.85em;"><?= htmlspecialchars($type) ?></span>
                                <?= generateTreeHTML($feature_id, $db) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Annotations Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#annotationsSection" aria-expanded="true">
                <i class="fas fa-minus toggle-icon text-primary"></i>
            </span>
            <strong class="ms-2">Annotations</strong>
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
			// color is defined and configurable in the annotation_config.json. example "gene ontology" : "#ffc107" 
                        $color = $annotation_colors[$annotation_type] ?? 'warning';
                        echo generateAnnotationTableHTML($annot_results, $feature_uniquename, $type, $count, $annotation_type, $analysis_desc[$annotation_type] ?? '', $color, $organism_name);
                    }
                }
                
                // Children annotations
                if (!empty($children)) {
                    // Add summary if multiple children
		    // TO DO: have the 'alternative transcripts/isoforms (mRNA)' statment only be generated for gene-mRNA parent-child relations, and have something more general for other relationships
		    // TO DO: also find every hardcoded 'gene' and 'mRNA' (transcript,isoform) and replace with code generated from the types we pull from the db
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
                        echo '  <div class="card-header d-flex align-items-center" style="background-color: rgba(23, 162, 184, 0.1);">';
                        echo "    <span class=\"collapse-section\" style=\"cursor: pointer;\" data-bs-toggle=\"collapse\" data-bs-target=\"#child_$child_feature_id\" aria-expanded=\"true\">";
                        echo "      <i class=\"fas fa-minus toggle-icon text-info\"></i>";
                        echo "    </span>";
                        echo "    <strong class=\"ms-2 text-dark\"><span class=\"text-white px-2 py-1 rounded\" style=\"background-color: #17a2b8;\">$child_uniquename ($child_type)</span></strong>";
                        
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
                                echo generateAnnotationTableHTML($annot_results, $child_uniquename, $child_type, $count, $annotation_type, $analysis_desc[$annotation_type] ?? '', $color, $organism_name);
                            }
                        }
                        
                        if (!$child_has_annotations) {
		            // TO DO: transcript is fine for mRNA but not fine if the child is something else. But the customization is nice, it is better than going generic for everything
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
    
    // Include sequences display component
    $sequences_file = __DIR__ . '/sequences_display.php';
    if (file_exists($sequences_file)) {
        include_once $sequences_file;
    }
    ?>

</div>
</div><!-- End page_container -->

<!-- DataTables JS -->
<script src="/<?= $site ?>/js/datatable.js"></script>
<script src="/<?= $site ?>/js/datatable-config.js"></script>
<script src="/<?= $site ?>/js/parent.js"></script>

<?php include_once __DIR__ . '/../../footer.php'; ?>

</body>
</html>
