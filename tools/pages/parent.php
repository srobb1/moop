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
    <!-- Feature Header and Tools Row -->
    <div class="row mb-3">
      <!-- Feature Header Column -->
      <div class="col-lg-8">
        <div class="feature-header shadow h-100">
            <h1>
                <?php if (!empty($description)): ?>
                    <?= htmlspecialchars(decodeAnnotationText($description)) ?>
                <?php else: ?>
                    <?= htmlspecialchars($feature_uniquename) ?>
                <?php endif; ?>
            </h1>
            <div class="feature-overview-body">
                <div class="mb-2">
                    <span class="badge bg-feature-gene text-white badge-sm"><?= htmlspecialchars($feature_uniquename) ?></span>
                    <span class="badge bg-feature-gene text-white ms-1 badge-sm"><?= htmlspecialchars($type) ?></span>
                    <?php if (!empty($children_hierarchical)):
                        $first_child_type = $children_hierarchical[0]['feature_type'] ?? 'mRNA';
                        $child_class = strtoupper($first_child_type) === 'MRNA' ? 'bg-feature-mrna' : 'bg-feature-gene';
                        $direct_child_count = count($children_hierarchical);
                    ?>
                        <span class="badge text-white ms-1 badge-sm <?= $child_class ?>">
                            <?= $direct_child_count ?> <?= htmlspecialchars($first_child_type) ?> child<?= $direct_child_count > 1 ? 'ren' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php
                $jbrowse_assembly_file = $config->getPath('metadata_path')
                    . '/jbrowse2-configs/assemblies/'
                    . $organism_name . '_' . $genome_accession . '.json';
                $jbrowse_loc = file_exists($jbrowse_assembly_file)
                    ? (!empty($feature_loc) ? $feature_loc['loc_string'] : $feature_uniquename)
                    : null;
                ?>
                <dl class="feature-info-grid mb-0">
                    <dt>Organism</dt>
                    <dd><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><em><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em><?php if ($common_name): ?> (<?= htmlspecialchars($common_name) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <dt>Assembly</dt>
                    <dd><a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><?= htmlspecialchars($genome_name) ?> (<?= htmlspecialchars($genome_accession) ?>)<i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <dt>Gene Set</dt>
                    <dd><a href="/<?= $site ?>/tools/gene_set.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>" class="link-light-bordered"><?= htmlspecialchars($gene_set_name) ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <?php if (!empty($feature_loc)): ?>
                    <dt>Location</dt>
                    <dd><?php
                        $loc_text = htmlspecialchars($feature_loc['seqname'])
                            . ':' . number_format($feature_loc['start'])
                            . '&ndash;' . number_format($feature_loc['end']);
                        if ($feature_loc['strand'] === '+' || $feature_loc['strand'] === '-') {
                            $loc_text .= ' (' . ($feature_loc['strand'] === '+' ? '+' : '&minus;') . ')';
                        }
                        if ($jbrowse_loc) {
                            $browser_url = '/' . $site . '/jbrowse2.php?organism=' . urlencode($organism_name)
                                . '&assembly=' . urlencode($genome_accession)
                                . '&loc=' . urlencode($feature_loc['loc_string']);
                            echo '<a href="' . $browser_url . '" target="_blank" class="link-light-bordered">' . $loc_text . ' <i class="fa fa-external-link-alt link-icon"></i></a>';
                        } else {
                            echo $loc_text;
                        }
                    ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
      </div>

      <!-- Tools Column -->
      <div class="col-lg-4">
        <?php
        $context = createToolContext('parent', [
            'organism'     => $organism_name,
            'assembly'     => $genome_accession,
            'gene_set'     => $gene_set_name,
            'display_name' => $feature_uniquename,
            'loc'          => $jbrowse_loc,
        ]);
        include_once TOOL_SECTION_PATH;
        ?>
      </div>
    </div>


    <?php if (!empty($gene_model)): ?>
    <!-- Gene Structure Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#geneModelSection" aria-expanded="true" role="button">
                <i class="fas fa-minus toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Gene Structure</span>
            <span class="ms-2 text-muted small">
                <?= count($gene_model['isoforms']) ?> isoform<?= count($gene_model['isoforms']) !== 1 ? 's' : '' ?>
            </span>
            <button class="btn btn-sm btn-link p-0 ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#geneModelInfo" aria-expanded="false" title="Diagram legend">
                <i class="fas fa-info-circle"></i>
            </button>
            <div class="ms-auto d-flex gap-1">
                <?php if (!empty($genome_seq_available)): ?>
                <button class="btn btn-sm btn-outline-secondary" id="gene-model-fmt-btn" title="Format sequence by feature type with custom highlighting"><i class="fas fa-palette me-1"></i>Sequence</button>
                <button class="btn btn-sm btn-outline-primary" id="gene-model-seq-btn" title="Fetch full genomic sequence — gene locus + each isoform span"><i class="fas fa-download me-1"></i>Genomic</button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-success" id="gene-model-gff-btn" title="Fetch GFF3 — gene, mRNA, exon, CDS, UTR and all sub-features"><i class="fas fa-download me-1"></i>GFF</button>
            </div>
        </div>
        <div id="geneModelSection" class="collapse show">
            <div class="card-body p-3">
                <div class="collapse mb-3" id="geneModelInfo">
                    <div class="alert alert-info mb-0 font-size-xsmall">
                        <div class="mb-2">
                            The diagram is always drawn 5&prime;&rarr;3&prime; left to right. Reverse-strand genes are flipped accordingly.
                        </div>
                        <?php if (!empty($genome_seq_available)): ?>
                        <div class="mb-2">
                            <strong>Click any feature to view its sequence</strong> &mdash; exons, CDS blocks, introns, and flanking upstream/downstream regions are all clickable. The sequence appears in a popup; adjust flanking region size inside the modal.
                        </div>
                        <?php endif; ?>
                        <div class="mb-0">
                            For bulk sequence downloads &mdash; full mRNA, CDS, protein, genomic, or upstream/downstream regions across multiple isoforms &mdash; use <a href="/<?= htmlspecialchars($config->getString('site', 'moop')) ?>/tools/moopmart.php?organism=<?= urlencode($organism_name) ?>" class="alert-link">MOOPmart</a>.
                        </div>
                    </div>
                </div>

                <svg id="gene-model-svg" width="100%" style="display:block; overflow:visible;"></svg>

                <div class="mt-2 d-flex flex-wrap gap-3" style="font-size:0.78rem; font-weight:600; letter-spacing:0.02em;">
                    <span style="color:#e8833a;">UTR</span>
                    <span style="color:#2171b5;">CDS</span>
                    <span style="color:#17becf;">Exon</span>
                    <span style="color:#888;">Intron</span>
                    <?php if (!empty($genome_seq_available)): ?>
                    <span style="color:#31a354;">Upstream</span>
                    <span style="color:#756bb1;">Downstream</span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feature Hierarchy Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#hierarchySection" aria-expanded="true" role="button">
                <i class="fas fa-sitemap toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Feature Hierarchy</span>
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
                <span class="ms-2 text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Annotations</span>
                <button class="btn btn-sm btn-link p-0 ms-2 annotation-info-btn" type="button" data-bs-toggle="collapse" data-bs-target="#annotationsInfo" aria-expanded="false" title="What is an annotation?">
                    <i class="fas fa-info-circle"></i>
                </button>
            </div>
            <div class="d-flex gap-2">
                <a href="/<?= htmlspecialchars($config->getString('site', 'moop')) ?>/api/download_annotations.php?organism=<?= urlencode($organism_name) ?>&uniquename=<?= urlencode($feature_uniquename) ?>"
                   class="btn btn-sm btn-outline-success" title="Download all annotations as CSV">
                    <i class="fas fa-download"></i> Download All Annotations
                </a>
                <a href="#" class="btn btn-sm btn-outline-secondary" title="Back to top">
                    <i class="fas fa-arrow-up"></i> Back to Top
                </a>
            </div>
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
                        <p class="mb-0 mt-2"><strong>Download buttons:</strong> <strong>Download All Annotations</strong> exports every annotation for this feature as a single CSV file. Individual annotation tables also have their own <i class="fas fa-download fa-xs"></i> download button to export just that result set.</p>
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
                        $rendered = generateChildAnnotationCards($child, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, $count, false, $annotated_child_types ?? []);
                        if ($rendered !== '') {
                            $has_annotations = true;
                            echo $rendered;
                        }
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
    // $gene_name is built in the controller and passed via $data
    $enable_downloads = true;
    $assembly_name    = $genome_accession;
    $organism_data    = $config->getPath('organism_data');

    $sequences_file = __DIR__ . '/../sequences_display.php';
    if (file_exists($sequences_file)) {
        include_once $sequences_file;
    }
    ?>

</div>
</div><!-- End page_container -->
