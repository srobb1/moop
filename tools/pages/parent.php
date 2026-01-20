<?php
/**
 * PARENT DISPLAY PAGE - Content Only
 * 
 * This file contains ONLY the page content.
 * All HTML structure (<!DOCTYPE>, <html>, <head>, <body>, <footer>) 
 * is handled by layout.php
 * 
 * Available Variables (passed from parent.php main controller):
 * - $organism_name: Current organism
 * - $uniquename: Feature uniquename to display
 * - $config: ConfigManager instance
 * - All other variables extracted from $data array
 * 
 * This is the core display logic for parent features.
 */

// NOTE: All necessary data loaded and validated in parent.php (main controller file)
// This file assumes all variables are already set and valid
?>

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
                    <strong>Organism:</strong> <span class="feature-value"><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em></a><?php if ($common_name): ?> ( <?= htmlspecialchars($common_name) ?> )<?php endif; ?></span>
                </div>
                <div class="feature-info-item">
                    <strong>Assembly:</strong> <span class="feature-value"><a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><?= htmlspecialchars($genome_name) ?> (<?= htmlspecialchars($genome_accession) ?>)</a></span>
                </div>
            </div>
        </div>
      </div>

      <!-- Tools Column -->
      <div class="col-lg-4">
        <?php 
        $context = createToolContext('parent', [
            'organism' => $organism_name,
            'assembly' => $genome_accession,
            'display_name' => $feature_uniquename
        ]);
        include_once TOOL_SECTION_PATH;
        ?>
      </div>
    </div>


    <!-- Feature Hierarchy Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#hierarchySection" aria-expanded="true" role="button">
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
                                <?php
                                // Calculate annotation count for parent feature
                                $parent_annot_count = 0;
                                if (isset($all_annotations[$feature_id])) {
                                    foreach ($all_annotations[$feature_id] as $annotation_type => $annots) {
                                        $parent_annot_count += count($annots);
                                    }
                                }
                                $parent_annot_anchor = preg_replace('/[^a-zA-Z0-9_]/', '_', $feature_uniquename . '_' . ($analysis_order[0] ?? 'annotation'));
                                ?>
                                <span class="feature-color-gene"><strong><a href="#annot_section_<?= htmlspecialchars($parent_annot_anchor) ?>" class="link-light-bordered text-decoration-none"><?= htmlspecialchars($feature_uniquename) ?></a></strong></span> 
                                <span class="badge bg-feature-gene text-white badge-sm"><?= htmlspecialchars($type) ?></span>
                                <?php if ($parent_annot_count > 0): ?>
                                    <span class="badge bg-success text-white badge-sm"><?= $parent_annot_count ?> annotation<?= $parent_annot_count > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                                <?= generateTreeHTML($feature_id, $db, $all_annotations, $analysis_order) ?>
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
                <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#annotationsSection" aria-expanded="true" role="button">
                    <i class="fas fa-minus toggle-icon text-primary"></i>
                </span>
                <strong class="ms-2">Annotations</strong>
                <button class="btn btn-sm btn-link p-0 ms-2 annotation-info-btn" type="button" data-bs-toggle="collapse" data-bs-target="#annotationsInfo" aria-expanded="false" title="What is an annotation?">
                    <i class="fas fa-info-circle"></i>
                </button>
            </div>
            <a href="#" class="btn btn-sm btn-outline-secondary" title="Back to top">
                <i class="fas fa-arrow-up"></i> Back to Top
            </a>
        </div>
        <div id="annotationsSection" class="collapse show">
            <div class="card-body">
                <div class="collapse mb-3" id="annotationsInfo">
                    <div class="alert alert-info mb-3 font-size-xsmall">
                        <strong>What is an annotation?</strong> An annotation is a functional or comparative hit that this sequence obtains through computational analysis. Examples include:
                        <ul class="mb-0 mt-2">
                            <li><strong>Homologs:</strong> Homologous sequences found in other organisms using tools like BLAST</li>
                            <li><strong>Orthologs:</strong> Orthologous sequences found in other organisms using tools like OMA or EggNOG</li>
                            <li><strong>Protein Domains:</strong> Conserved structural domains identified using InterProScan or similar tools</li>
                        </ul>
                        <p class="mb-0 mt-2"><small><strong>Note:</strong> Annotation sections are only displayed if the sequence has results for that type. If a sequence has no annotations of a specific type, that section will not appear.</small></p>
                    </div>
                </div>
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
                        $display_label = $annotation_labels[$annotation_type] ?? $annotation_type;
                        echo generateAnnotationTableHTML($annot_results, $feature_uniquename, $type, $count, $display_label, $analysis_desc[$annotation_type] ?? '', $color, $organism_name, $annotation_type);
                    }
                }
                
                // Children annotations (with hierarchical support for grandchildren)
                if (!empty($children_hierarchical)) {
                    // Add summary if multiple children
                    // TO DO: have the 'alternative transcripts/isoforms (mRNA)' statment only be generated for gene-mRNA parent-child relations, and have something more general for other relationships
                    // TO DO: also find every hardcoded 'gene' and 'mRNA' (transcript,isoform) and replace with code generated from the types we pull from the db
                    if (count($children_hierarchical) > 1) {
                        echo '<div class="alert alert-info mt-3">';
                        echo '  <i class="fas fa-info-circle"></i> ';
                        echo '  <strong>Multiple Children:</strong> This gene has ' . count($children_hierarchical) . ' alternative transcripts/isoforms (mRNA). ';
                        echo '  Each may have different annotations.';
                        echo '</div>';
                    }
                    
                    // Render children with hierarchical nesting support
                    foreach ($children_hierarchical as $child) {
                        $has_annotations = true;
                        echo generateChildAnnotationCards($child, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, $count);
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
    // Add all descendants to sequence retrieval (getChildren returns all descendants recursively)
    foreach ($children as $child) {
        $retrieve_these_seqs[] = $child['feature_uniquename'];
    }
    
    $retrieve_these_seqs = array_unique($retrieve_these_seqs);
    sort($retrieve_these_seqs);
    $gene_name = implode(",", $retrieve_these_seqs);
    
    // Set up variables for sequences_display.php with download support
    $enable_downloads = true;
    $assembly_name = $genome_accession;

    $organism_data = $config->getPath('organism_data');

    // organism_name is already set above
    
    // Include sequences display component
    $sequences_file = __DIR__ . '/../sequences_display.php';
    if (file_exists($sequences_file)) {
        include_once $sequences_file;
    }
    ?>

</div>
</div><!-- End page_container -->
