<?php
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/parent_functions.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();

// Validate required parameters
$organism_name = validateOrganismParam($_GET['organism'] ?? '', null);
$uniquename = validateAssemblyParam($_GET['uniquename'] ?? '', null);
if ($organism_name === null || $uniquename === null) {
    die("Error: Missing required parameters. Please provide both 'organism' and 'uniquename' parameters.");
}

// Setup organism context (loads info, checks access)
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$organism_info = $organism_context['info'];

// Verify and get database path
$db = verifyOrganismDatabase($organism_name, $organism_data);

// Get accessible assemblies and convert to genome IDs for permission-based filtering
$group_data = getGroupData();
$accessible_assemblies = [];
foreach ($group_data as $data) {
    if ($data['organism'] === $organism_name && has_assembly_access($organism_name, $data['assembly'])) {
        $accessible_assemblies[] = $data['assembly'];
    }
}
$accessible_genome_ids = getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db);

// Security: Verify user has access to at least one assembly
if (empty($accessible_genome_ids)) {
    die("Error: No accessible assemblies found for this organism.");
}

// Load annotation configuration using helper
$annotation_config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFileRequired($annotation_config_file, "Missing annotation_config.json");

$analysis_order = [];
$analysis_desc = [];
$annotation_colors = [];

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

// Define parent types (typically genes are parent features)
// TO DO: not all organisms in the future will have gene as a parent, this should go in a config somewhere
$parents = ['gene', 'pseudogene'];

// Get ancestors for the feature
$ancestors = getAncestors($uniquename, $db, $accessible_genome_ids);

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
$row = getFeatureById($ancestor_feature_id, $db, $accessible_genome_ids);

// Get all info about Highest Parent
if (empty($row)) { 
    die("The gene $uniquename was not found in the database. Please, check the spelling carefully or try to find it in the search tool.");
}

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
$children = getChildren($feature_id, $db, $accessible_genome_ids);

// Optimize: Get ALL annotations for parent and all children in ONE query
$all_feature_ids = [$feature_id];
foreach ($children as $child) {
    $all_feature_ids[] = $child['feature_id'];
}
$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title><?= htmlspecialchars($feature_uniquename) ?> - <?= $siteTitle ?></title>
<?php include_once __DIR__ . '/../includes/head.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
<link rel="stylesheet" href="/<?= $site ?>/css/display.css">
<link rel="stylesheet" href="/<?= $site ?>/css/parent.css">
</head>
<body>

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="page_container">

<!-- Navigation -->
<div class="margin-20">
</div>

    <!-- Feature Header and Tools Row -->
    <div class="row mb-4">
      <!-- Feature Header Column -->
      <div class="col-lg-8">
        <div class="feature-header shadow h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div class="flex-grow-1">
                    <h1 class="mb-3">
                        <?php if (!empty($description)): ?>
                            <?= htmlspecialchars($description) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($feature_uniquename) ?>
                        <?php endif; ?>
                    </h1>
                    <div class="feature-info-item">
			<span class="badge bg-feature-gene text-white ms-2 badge-accent badge-lg">
                           <?= htmlspecialchars($feature_uniquename) ?>
                        </span>
                        <span class="badge bg-feature-gene text-white ms-2 badge-accent badge-lg">
                            <?= htmlspecialchars($type) ?>
                        </span>
                        <?php if (!empty($children) && count($children) > 0):
                            $first_child_type = $children[0]['feature_type'] ?? 'mRNA';
                            $child_class = strtoupper($first_child_type) === 'MRNA' ? 'bg-feature-mrna' : 'bg-feature-gene';
                        ?>
                            <span class="badge text-white ms-2 badge-lg <?= $child_class ?>">
                                <?= count($children) ?> <?= htmlspecialchars($first_child_type) ?> child<?= count($children) > 1 ? 'ren' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="feature-info-item">
                    <strong>Organism:</strong> <span class="feature-value"><a href="/<?= $site ?>/tools/organism_display.php?organism=<?= urlencode($organism_name) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em></a><?php if ($common_name): ?> ( <?= htmlspecialchars($common_name) ?> )<?php endif; ?></span>
                </div>
                <div class="feature-info-item">
                    <strong>Assembly:</strong> <span class="feature-value"><a href="/<?= $site ?>/tools/assembly_display.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><?= htmlspecialchars($genome_name) ?> (<?= htmlspecialchars($genome_accession) ?>)</a></span>
                </div>
            </div>
        </div>
      </div>

      <!-- Tools Column -->
      <div class="col-lg-4">
        <?php 
        $context = createFeatureToolContext($organism_name, $genome_accession, $feature_uniquename);
        include_once TOOL_SECTION_PATH;
        ?>
      </div>
    </div>


    <!-- Feature Hierarchy Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#hierarchySection" aria-expanded="true">
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
                                <span class="badge bg-feature-gene text-white badge-sm"><?= htmlspecialchars($type) ?></span>
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
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#annotationsSection" aria-expanded="true">
                    <i class="fas fa-minus toggle-icon text-primary"></i>
                </span>
                <strong class="ms-2">Annotations</strong>
            </div>
            <a href="#" class="btn btn-sm btn-outline-secondary" title="Back to top">
                <i class="fas fa-arrow-up"></i> Back to Top
            </a>
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
                        echo '  <div class="card-header d-flex align-items-center child-feature-header">';
                        echo "    <span class=\"collapse-section\" data-bs-toggle=\"collapse\" data-bs-target=\"#child_$child_feature_id\" aria-expanded=\"true\">";
                        echo "      <i class=\"fas fa-minus toggle-icon text-info\"></i>";
                        echo "    </span>";
                        echo "    <strong class=\"ms-2 text-dark\"><span class=\"text-white px-2 py-1 rounded child-feature-badge badge-xlg\">$child_uniquename ($child_type)</span></strong>";
                        
                        // Show colored annotation type badges as clickable links
                        if ($child_annotation_count > 0) {
                            foreach ($child_annotation_types as $type_name => $type_count) {
                                $badge_color = $annotation_colors[$type_name] ?? 'warning';
                                $text_color = in_array($badge_color, ['warning', 'info', 'secondary']) ? 'text-dark' : 'text-white';
                                $section_id = "annot_section_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $child_uniquename . '_' . $type_name);
                                echo " <a href=\"#$section_id\" class=\"badge bg-$badge_color $text_color ms-1 text-decoration-none badge-s\" style=\"cursor: pointer;\">$type_name</a>";
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
    
    // Set up variables for sequences_display.php with download support
    $enable_downloads = true;
    $assembly_name = $genome_accession;
    // organism_name is already set above
    
    // Include sequences display component
    $sequences_file = __DIR__ . '/sequences_display.php';
    if (file_exists($sequences_file)) {
        include_once $sequences_file;
    }
    ?>

</div>
</div><!-- End page_container -->

<!-- DataTables JS -->
<script src="/<?= $site ?>/js/features/datatable-config.js"></script>
<script src="/<?= $site ?>/js/features/parent-tools.js"></script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
