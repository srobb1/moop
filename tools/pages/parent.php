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

    <!-- Section navigation — sidebar (wide) / jump-to bar (narrow). TOC is
         built at runtime by js/modules/parent-nav.js from the sections below. -->
    <div class="pnav-jumpbar">
      <button class="pnav-jb-btn" id="pnavJbBtn" type="button" aria-expanded="false" aria-controls="pnavJbDd">
        <svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 4h12v1.6H2zM2 7.2h12v1.6H2zM2 10.4h8V12H2z"/></svg>
        Jump to
        <svg class="pnav-jb-chev" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
      </button>
      <?php /* Matches the first sidebar entry, which is the feature's own ID — see the
               note on #pnav-overview. The JS overwrites this on scroll; it only has to be
               right before the first scrollspy tick. */ ?>
      <span class="pnav-jb-current" id="pnavJbCurrent"><?= htmlspecialchars($feature_uniquename) ?></span>
      <div class="pnav-jb-dd" id="pnavJbDd"></div>
    </div>

    <div class="pnav-layout">
      <aside class="pnav-side" aria-label="Jump to">
        <div class="pnav-side-head">
          <span class="pnav-side-title">Jump to</span>
          <button class="pnav-toggle" id="pnavToggle" type="button" aria-label="Collapse navigation" aria-expanded="true" title="Collapse (content goes full width)">
            <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
          </button>
        </div>
        <div class="pnav-scroll" id="pnavToc"></div>
      </aside>

      <div class="pnav-main">

    <!-- Feature Header and Tools Row -->
    <?php /* The nav lists this row by the feature's own ID and type, exactly as it lists
             the child transcripts below it, rather than by the generic word "Overview".
             The sidebar is a map of the page's features; naming the first entry after the
             section instead of the feature made the parent the one row that did not say
             what it was. */ ?>
    <div class="row gy-3 mb-3" id="pnav-overview"
         data-nav-label="<?= htmlspecialchars($feature_uniquename) ?>"
         data-nav-tag="<?= htmlspecialchars($type) ?>">
      <!-- Feature Header Column -->
      <div class="col-lg-8">
        <div class="feature-header shadow h-100">
            <?php
            // Computed here, above both the copy payload and the heading below, so the two
            // cannot disagree about what this gene is called.
            $overview_title = !empty($description)
                ? decodeAnnotationText($description)
                : (!empty($name) ? $name : '');

            // Plain-text summary for pasting into notes. Built here rather than scraped
            // from the DOM: the overview is a <dl> of labels and links, so a hand
            // selection drags in blank lines and layout whitespace. Same fields, same
            // order as the box, one per line, no headings — the user asked for exactly
            // what they would have highlighted, minus the mess.
            $copy_lines = [$feature_uniquename];
            if ($overview_title !== '') $copy_lines[] = $overview_title;
            $badges = htmlspecialchars_decode(strip_tags($type));
            if (!empty($children_hierarchical)) {
                $n = count($children_hierarchical);
                $badges .= ' ' . $n . ' ' . ($children_hierarchical[0]['feature_type'] ?? 'mRNA')
                         . ' child' . ($n > 1 ? 'ren' : '');
            }
            $copy_lines[] = $badges;
            $copy_lines[] = trim($genus . ' ' . $species . ($common_name ? " ($common_name)" : ''));
            // Assembly and gene set are LABELLED; the rest are not.
            //
            // The other lines say what they are from their content — an accession, a
            // species name, a genomic range. These two are bare identifiers whose meaning
            // comes entirely from their position, and on 19 of 92 gene sets they are near
            // identical: "bvag.kc1 (bvag.kc1.ref)" sits directly above "bvag.kc1". Pasted
            // into notes unlabelled, those are indistinguishable later. It is the whole
            // in-house .kc1 family, not an edge case.
            $copy_lines[] = 'Assembly: ' . assembly_label($genome_name, $genome_accession);
            $copy_lines[] = 'Gene set: ' . $gene_set_name;
            if (!empty($feature_loc)) {
                $copy_lines[] = $feature_loc['seqname'] . ':' . number_format($feature_loc['start'])
                              . '-' . number_format($feature_loc['end'])
                              . (in_array($feature_loc['strand'], ['+','-'], true) ? ' (' . $feature_loc['strand'] . ')' : '');
            }
            $copy_text = implode("\n", array_filter($copy_lines, fn($l) => trim((string)$l) !== ''));
            ?>
            <div class="feature-header-id">
                <span><?= htmlspecialchars($feature_uniquename) ?></span>
                <?php /* The page-level (i) sits in the title bar, matching every other
                         section on this page — Gene Structure and Annotations each carry
                         theirs in their own card header. */ ?>
                <span class="ms-auto d-flex align-items-center gap-2">
                    <button type="button" class="feature-header-copy"
                            data-copy-text="<?= htmlspecialchars($copy_text, ENT_QUOTES) ?>"
                            title="Copy this summary as plain text, ready to paste into notes">
                        <i class="fa fa-copy"></i> <span class="copy-label">Copy</span>
                    </button>
                    <?= help_modal_trigger('gene-page-help', '', 'Help: what this page shows') ?>
                </span>
            </div>
            <div class="feature-overview-body">
                <h1 class="feature-title">
                    <?php if ($overview_title !== ''): ?>
                        <?= htmlspecialchars($overview_title) ?>
                    <?php else: ?>
                        <span class="feature-title-empty">No description available</span>
                    <?php endif; ?>
                </h1>
                <div class="mb-2">
                    <span class="badge bg-feature-gene text-white badge-sm"><?= htmlspecialchars($type) ?></span>
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
                    <dd><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><em class="sci-name"><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em><?php if ($common_name): ?> (<?= htmlspecialchars($common_name) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <dt>Assembly</dt>
                    <dd><a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><?= htmlspecialchars(assembly_label($genome_name, $genome_accession)) ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
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
            // The gene's own id, separate from display_name: Primer Maker uses it
            // to list the gene's transcripts and prefill one. Passing it as
            // display_name would have worked by accident today and broken the
            // moment either meaning changed.
            'feature'      => $feature_uniquename,
            'loc'          => $jbrowse_loc,
        ]);
        include_once TOOL_SECTION_PATH;
        ?>
      </div>
    </div>


    <?php if (!empty($gene_model)): ?>
    <!-- Gene Structure Section -->
    <div class="card shadow-sm mb-4" id="pnav-gene-structure" data-nav-label="Gene Structure">
        <div class="card-header d-flex align-items-center flex-wrap">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#geneModelSection" aria-expanded="true" role="button">
                <i class="fas fa-minus toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow">Gene Structure</span>
            <span class="ms-2 text-muted small">
                <?= count($gene_model['isoforms']) ?> isoform<?= count($gene_model['isoforms']) !== 1 ? 's' : '' ?>
            </span>
            <?= help_modal_trigger('gene-model-help', '', 'Help: reading the gene structure diagram') ?>
            <?php /* One treatment for all of these: they are all "get this gene's data out".
                     They used to be outline-secondary, outline-primary and outline-success,
                     so the colours implied three different kinds of thing and signalled
                     nothing. The VERB is carried by the icon instead — palette opens a
                     viewer, download downloads — and the two downloads are grouped because
                     they are the same action in two formats. */ ?>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <?php if (!empty($genome_seq_available)): ?>
                <button class="btn btn-sm moop-data-btn" id="gene-model-fmt-btn"
                        title="Open the sequence with each feature type highlighted"><i class="fas fa-palette me-1"></i>Format sequence</button>
                <?php endif; ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="Download this gene">
                    <?php if (!empty($genome_seq_available)): ?>
                    <button class="btn moop-data-btn" id="gene-model-seq-btn"
                            title="Download the genomic sequence — gene locus plus each isoform span"><i class="fas fa-download me-1"></i>FASTA</button>
                    <?php endif; ?>
                    <button class="btn moop-data-btn" id="gene-model-gff-btn"
                            title="Download GFF3 — gene, mRNA, exon, CDS, UTR and all sub-features"><i class="fas fa-download me-1"></i>GFF</button>
                </div>
            </div>
        </div>
        <div id="geneModelSection" class="collapse show">
            <div class="card-body p-3">
                <svg id="gene-model-svg" width="100%" style="display:block; overflow:visible;"></svg>

                <?php
                // ONE definition of the diagram's colour key, shared by this legend and the
                // help modal at the foot of the page. Hexes mirror the COLOR_* constants in
                // js/modules/gene-model-viewer.js, which is what actually paints the SVG —
                // the modal previously approximated them with Bootstrap colour names and got
                // Downstream (purple) rendering as grey.
                $gene_model_legend = [
                    ['label' => 'UTR',    'hex' => '#e8833a', 'text' => 'Untranslated region — transcribed but not coding. Drawn as the thin part of an exon.'],
                    ['label' => 'CDS',    'hex' => '#2171b5', 'text' => 'The coding stretch within the exons — what becomes protein. Drawn thick.'],
                    ['label' => 'Exon',   'hex' => '#17becf', 'text' => 'A transcribed block on an isoform with no CDS, so nothing here is known to code.'],
                    ['label' => 'Intron', 'hex' => '#888888', 'text' => 'The thin line joining exons. Spliced out of the mature transcript.'],
                ];
                if (!empty($genome_seq_available)) {
                    $gene_model_legend[] = ['label' => 'Upstream',   'hex' => '#31a354', 'text' => 'Flanking sequence before the transcript start.'];
                    $gene_model_legend[] = ['label' => 'Downstream', 'hex' => '#756bb1', 'text' => 'Flanking sequence after the transcript end.'];
                }
                ?>
                <div class="mt-2 d-flex flex-wrap gap-3" style="font-size:0.78rem; font-weight:600; letter-spacing:0.02em;">
                    <?php foreach ($gene_model_legend as $__l): ?>
                    <span style="color:<?= htmlspecialchars($__l['hex']) ?>;"><?= htmlspecialchars($__l['label']) ?></span>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feature Hierarchy Section -->
    <div class="card shadow-sm mb-4" id="pnav-hierarchy" data-nav-label="Feature Hierarchy">
        <div class="card-header d-flex align-items-center">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#hierarchySection" aria-expanded="true" role="button">
                <i class="fas fa-sitemap toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow">Feature Hierarchy</span>
            <?= help_modal_trigger('hierarchy-help', '', 'Help: reading the feature hierarchy') ?>
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
                                // The gene links to its OWN annotation sections, which are only
                                // rendered when the gene itself carries annotations — usually it
                                // does not, since annotations attach to the transcript. This used
                                // to point at "$analysis_order[0]" unconditionally and was dead on
                                // every gene without that one type.
                                // Point at the first type the gene ACTUALLY has, not at
                                // $analysis_order[0] — sections are rendered per type that has
                                // results, so naming a type the gene lacks is a dead link.
                                $parent_annot_anchor = '';
                                foreach ($analysis_order as $__t) {
                                    if (!empty($all_annotations[$feature_id][$__t])) {
                                        $parent_annot_anchor = 'annot_section_' . preg_replace(
                                            '/[^a-zA-Z0-9_]/', '_', $feature_uniquename . '_' . $__t);
                                        break;
                                    }
                                }
                                ?>
                                <span class="feature-color-gene"><strong><?php if ($parent_annot_anchor !== ''): ?><a href="#<?= htmlspecialchars($parent_annot_anchor) ?>" class="link-light-bordered text-decoration-none"><?= htmlspecialchars($feature_uniquename) ?></a><?php else: ?><?= htmlspecialchars($feature_uniquename) ?><?php endif; ?></strong></span> 
                                <span class="badge bg-feature-gene text-white badge-sm"><?= htmlspecialchars($type) ?></span>
                                <?php if ($parent_annot_count > 0): ?>
                                    <span class="badge bg-success text-white badge-sm"><?= $parent_annot_count ?> annotation<?= $parent_annot_count > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                                <?= generateTreeHTML($children_hierarchical, $all_annotations, $analysis_order) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Annotations Section -->
    <div class="card shadow-sm mb-4" id="pnav-annotations" data-nav-label="Annotations">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#annotationsSection" aria-expanded="true" role="button">
                    <i class="fas fa-minus toggle-icon text-primary"></i>
                </span>
                <span class="ms-2 text-uppercase fw-semibold section-eyebrow">Annotations</span>
                <?php if (!empty($isoforms_share_annotations)): ?>
                    <?php /* Stated, not acted on. The tables stay per transcript — an annotation
                             belongs to the transcript, and grouping only the genes where the sets
                             happen to match would make the page structure depend on the data.
                             This is the one thing the per-transcript layout cannot show: that a
                             reader comparing N isoforms would find them identical. */ ?>
                    <span class="badge rounded-pill ms-2 fw-normal"
                          style="background-color:#e0f2f1; color:#0f5132; font-size:0.72rem;"
                          title="Every transcript of this gene carries the same set of annotation types and accessions. Scores and descriptions may still differ.">
                        <?php /* "identical", not "annotated the same": the point is that the
                                 N sets below are the same set, which is the exception. About
                                 a third of multi-isoform genes are like this, so the normal
                                 expectation on arriving is that they differ and must be
                                 compared. "Annotated the same" read as a neutral description
                                 of how the data was produced rather than as a finding. */ ?>
                        <i class="fa fa-equals me-1" aria-hidden="true"></i>identical on all <?= (int)$annotated_isoform_count ?> transcripts
                    </span>
                <?php endif; ?>
                <?= help_modal_trigger('annotations-help', '', 'Help: what is an annotation?') ?>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php /* Collapse-all: on a gene with 17 transcripts, getting from the first
                         to the last means scrolling past every annotation table in between.
                         There was no way to fold them and no keyboard shortcut either. Only
                         shown when there is more than one transcript to fold. */ ?>
                <?php if (count($children_hierarchical) > 1): ?>
                <button type="button" class="btn btn-sm moop-data-btn" id="toggle-all-transcripts"
                        data-state="expanded"
                        title="Collapse every transcript so the list fits on one screen">
                    <i class="fas fa-compress-alt me-1"></i><span class="label">Collapse all</span>
                </button>
                <?php endif; ?>
                <a href="/<?= htmlspecialchars($config->getString('site', 'moop')) ?>/api/download_annotations.php?organism=<?= urlencode($organism_name) ?>&uniquename=<?= urlencode($feature_uniquename) ?>"
                   class="btn btn-sm moop-data-btn" title="Download every annotation on this page as one CSV">
                    <i class="fas fa-download me-1"></i>Download all
                </a>
                <?php /* "Back to Top" removed: the section nav on the left already returns
                         you there from anywhere, and it duplicated that in one spot only. */ ?>
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
			// color is defined and configurable in the annotation_config.json. example "gene ontology" : "#ffc107" 
                        $color = $annotation_colors[$annotation_type] ?? 'warning';
                        $display_label = $annotation_labels[$annotation_type] ?? $annotation_type;
                        echo generateAnnotationTableHTML($annot_results, $feature_uniquename, $type, $count, $display_label, $analysis_desc[$annotation_type] ?? '', $color, $organism_name, $annotation_type);
                    }
                }
                
                // Children annotations (with hierarchical support for grandchildren)
                if (!empty($children_hierarchical)) {
                    // Summary when there is more than one child.
                    //
                    // This used to say "Each may have different annotations" unconditionally.
                    // We now compute whether they actually do, so "may" was both vaguer than
                    // necessary and — on a gene where the sets are identical — a flat
                    // contradiction of the badge in the section header above.
                    //
                    // The parent and child TYPE now come from the data too, rather than the
                    // hardcoded "gene"/"mRNA" this carried a TO DO about. A gene with mRNAs
                    // reads the same as before; a hierarchy of any other shape now describes
                    // itself correctly instead of calling its children isoforms.
                    if (count($children_hierarchical) > 1) {
                        $n_children  = count($children_hierarchical);
                        $child_type  = $children_hierarchical[0]['feature_type'] ?? 'child';
                        $is_transcript = in_array(strtolower($child_type), ['mrna', 'transcript'], true);
                        $child_word  = $is_transcript ? 'transcripts' : (htmlspecialchars($child_type) . ' children');

                        // Both states are ORIENTATION, not alerts. A full-width coloured
                        // alert block is the page's loudest device, and nothing here is a
                        // warning: one says "you need not compare these", the other says
                        // "you should". Using alert-success/alert-info made a routine fact
                        // about the data shout louder than the annotation tables it
                        // introduces -- and the differing case, which is roughly two thirds
                        // of multi-isoform genes, was the louder of the two. A quiet line
                        // with a left rule says the same thing without competing.
                        if (!empty($isoforms_share_annotations)) {
                            echo '<div class="isoform-note isoform-note-same">';
                            echo '  <i class="fas fa-equals" aria-hidden="true"></i> ';
                            echo '  <strong>Identical on all ' . $n_children . ' ' . $child_word . '.</strong> ';
                            echo '  Listed separately because an annotation belongs to the '
                               . htmlspecialchars(moop_type_word($child_type)) . ', not the '
                               . htmlspecialchars(moop_type_word($type)) . '; scores and descriptions may still differ.';
                            echo '</div>';
                        } else {
                            echo '<div class="isoform-note">';
                            echo '  <i class="fas fa-info-circle" aria-hidden="true"></i> ';
                            echo '  <strong>' . $n_children . ' ' . $child_word . ', annotated differently.</strong> ';
                            echo '  Compare the sections below rather than reading only the first.';
                            echo '</div>';
                        }
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
    <div id="pnav-sequences" data-nav-label="Sequences">
    <?php
    // $gene_name is built in the controller and passed via $data
    $enable_downloads = true;
    // Gene pages only. sequences_display.php is shared with Retrieve Sequences,
    // and Primer Maker is deliberately a gene-page tool (user, 2026-08-07) — so
    // the send-to-maker button is opted INTO here rather than appearing wherever
    // this component happens to be included.
    $enable_primer_design = true;
    $primer_design_context = [
        'organism' => $organism_name,
        'assembly' => $genome_accession,
        'gene_set' => $gene_set_name,
        'feature'  => $feature_uniquename,
    ];
    $assembly_name    = $genome_accession;
    $organism_data    = $config->getPath('organism_data');

    $sequences_file = __DIR__ . '/../sequences_display.php';
    if (file_exists($sequences_file)) {
        include_once $sequences_file;
    }
    ?>
    </div><!-- /#pnav-sequences -->

      </div><!-- /.pnav-main -->
    </div><!-- /.pnav-layout -->

</div>
</div><!-- End page_container -->

<?php
// ── Page help ────────────────────────────────────────────────────────────────
// Card modals, not the collapsible alert boxes these replace. A collapsible pushes the
// page down when it opens, so what you were reading moves; and collapsed content is
// invisible content, which is how the old help drifted unnoticed. Modals need no JS init
// (Bootstrap data-api), so they cannot sit dead the way a per-page-init popover can.

// "How to read it" varies with whether genome sequence is actually present — otherwise the
// help promises a click that does nothing.
$gm_read_cards = [
    ['label' => 'Always 5′ → 3′', 'html' => true,
     'text'  => 'Drawn left to right in the direction of transcription. A reverse-strand gene is flipped, so the diagram reads the same way on either strand.'],
    ['label' => 'One row per isoform',
     'text'  => 'The gene spans the top; each mRNA below is one alternative transcript. Rows are aligned, so exon differences line up vertically.'],
    ['label' => 'Click a row',
     'text'  => 'Jumps to that isoform\'s annotations further down the page.'],
];
if (!empty($genome_seq_available)) {
    $gm_read_cards[] = ['label' => 'Click a feature',
        'text' => 'Exons, CDS blocks, introns and both flanking regions open their sequence. Flank size is adjustable inside that popup.'];
}

echo help_modal(
    'gene-model-help',
    'Reading the gene structure diagram',
    [
        ['heading' => 'What the parts are', 'cards' => array_map(
            // A swatch in the exact colour the SVG is painted, not a Bootstrap colour name.
            // The names have no orange/purple that matches, so 'secondary' turned Downstream
            // grey — and a legend whose colours disagree with the picture is worse than none.
            function (array $l) {
                return [
                    'label' => $l['label'],
                    'html'  => true,
                    'text'  => '<span style="display:inline-block;width:.75rem;height:.75rem;'
                             . 'border-radius:3px;vertical-align:-1px;margin-right:.4rem;'
                             . 'background:' . htmlspecialchars($l['hex']) . ';"></span>'
                             . htmlspecialchars($l['text']),
                ];
            },
            $gene_model_legend
        )],
        ['heading' => 'How to read it', 'cards' => $gm_read_cards],
        ['heading' => 'Getting sequence out', 'cards' => [
            ['label' => 'This gene',
             'text'  => 'Use Genomic or GFF in the header of this box, or the Sequences section at the foot of the page.'],
            ['label' => 'Many genes at once', 'html' => true,
             'text'  => 'For mRNA, CDS, protein, genomic or flanking sequence across many features, use <a href="/'
                      . htmlspecialchars($config->getString('site', 'moop')) . '/tools/moopmart.php?organism='
                      . urlencode($organism_name) . '">MOOPmart</a>.'],
        ]],
    ],
    ['intro' => 'Every isoform of this gene, drawn to scale against the genome.']
);

// ── Feature Hierarchy ────────────────────────────────────────────────────────
// The tree is the one place the page states the parent/child model outright, and three of
// its four signals are silent: the green badge, the fact that an ID is sometimes a link
// and sometimes not, and why the count sits on the transcript rather than the gene. The
// not-a-link case is the one that reads as broken — cds and protein rows are deliberately
// plain text because no annotation section exists for them anywhere on the page, and
// without saying so the user is left clicking something that looks live. (Those rows WERE
// links once, and dead ones; see moop_annotation_card_anchor().)
echo help_modal(
    'hierarchy-help',
    'Reading the feature hierarchy',
    [
        ['heading' => 'What it shows', 'cards' => [
            ['label' => 'What belongs to what',
             'text'  => 'The ' . htmlspecialchars(moop_type_word($type)) . ', the transcripts it produces, and the coding sequence and protein each transcript gives.'],
            ['label' => 'Colour = feature type',
             'text'  => 'The badge after each ID uses the same colour for a type everywhere on the site.'],
        ]],
        ['heading' => 'The green count', 'cards' => [
            ['label' => 'How many annotations',
             'text'  => 'A green badge counts every annotation on that feature, across all types. No badge means none.'],
            ['label' => 'Usually on the transcript',
             'text'  => 'Annotations attach to the transcript, so the ' . htmlspecialchars(moop_type_word($type)) . ' itself often carries none even when the page is full of results.'],
        ]],
        ['heading' => 'Clicking', 'cards' => [
            ['label' => 'An ID that is a link jumps to its annotations',
             'text'  => 'It scrolls to that feature\'s section further down the page.'],
            ['label' => 'Plain text means there is nothing to jump to',
             'text'  => 'Coding sequences and proteins have no annotation section of their own, so their IDs are not links rather than links that do nothing.'],
        ]],
    ],
    ['intro' => 'How this ' . htmlspecialchars(moop_type_word($type)) . ' is built, and where its annotations sit.']
);

// ── Page-level orientation ───────────────────────────────────────────────────
// A gene page is dense — five sections, a diagram, and a table per annotation type per
// transcript. This is the "where am I and what am I looking at" card, not a manual: the
// sections each carry their own (i) for detail. One card = one thing, ~20 words.
//
// The section cards are built conditionally so the help never describes a section that is
// not on the page — Gene Structure is absent when there is no gene model, and Annotations
// when nothing is annotated.
$page_sections = [];
if (!empty($gene_model)) {
    $page_sections[] = ['label' => 'Gene Structure',
        'text' => 'Every transcript drawn to scale against the genome. Click a feature for its sequence, or a row to jump to its annotations.'];
}
$page_sections[] = ['label' => 'Feature Hierarchy',
    'text' => 'What belongs to what: the ' . htmlspecialchars(moop_type_word($type)) . ', its transcripts, and their coding sequences and proteins.'];
if (!empty($children_hierarchical) || !empty($all_annotations[$feature_id])) {
    $page_sections[] = ['label' => 'Annotations',
        'text' => 'What analyses found for this sequence, grouped by transcript and then by type. One table per type.'];
}
$page_sections[] = ['label' => 'Sequences',
    'text' => 'Download the genomic, transcript, coding or protein sequence for anything on this page.'];

echo help_modal(
    'gene-page-help',
    'What this page shows',
    [
        ['heading' => 'In short', 'cards' => [
            ['label' => 'One ' . htmlspecialchars(moop_type_word($type)),
             'text'  => 'Everything this site holds about it: its structure, its transcripts, what is known about them, and their sequences.'],
            ['label' => 'You may have searched for a transcript',
             'text'  => 'A transcript, CDS or protein ID all land here. The page always opens at the ' . htmlspecialchars(moop_type_word($type)) . ' they belong to.'],
        ]],
        ['heading' => 'The sections', 'cards' => $page_sections],
        ['heading' => 'Worth knowing', 'cards' => [
            ['label' => 'Annotations belong to the transcript',
             'text'  => 'Not to the ' . htmlspecialchars(moop_type_word($type)) . '. That is why they are listed separately under each one, even when they match.'],
            ['label' => 'Use the list on the left',
             'text'  => 'It mirrors the page and carries the count beside each entry, so you can see where the results are before scrolling.'],
            ['label' => 'Every section folds',
             'text'  => 'Click a section heading to collapse it, and again to reopen. Nothing is lost — it is only hidden.'],
            ['label' => 'Collapse all transcripts',
             'text'  => 'The button by Annotations folds every transcript at once, which on a long gene turns the whole page into one screen.'],
        ]],
    ],
    ['intro' => 'A long page — this is what is on it and in what order.']
);

// Annotation-type cards are generated from $annotation_labels / $analysis_desc — the same
// arrays that colour the badges on this page — so the help lists exactly the types this
// site has configured, in the admin's own order, and cannot describe a type that is gone.
$annot_cards = [];
foreach ($analysis_order as $__t) {
    $__desc = trim((string)($analysis_desc[$__t] ?? ''));
    if ($__desc === '') continue;
    // These descriptions are ADMIN-SUPPLIED (metadata/annotation_config.json) and some
    // legitimately carry emphasis — 2 of the 10 types here use <strong>. Escaped, that
    // showed as literal "<strong>orthologs</strong>" in the modal; passed raw it would make
    // an admin-editable field into stored HTML, which is exactly the injection risk
    // lib/help_ui.php cites for defaulting card text to escaped.
    //
    // So: allow a small inline whitelist and escape everything else. strip_tags() removes
    // any tag outside the list (including <script> and any attribute-bearing tag, since it
    // drops whole tags rather than sanitising them).
    $annot_cards[] = [
        'label' => $annotation_labels[$__t] ?? $__t,
        'color' => $annotation_colors[$__t] ?? 'secondary',
        'html'  => true,
        'text'  => strip_tags($__desc, '<strong><b><em><i><code><sub><sup>'),
    ];
}

$annot_sections = [
    ['heading' => 'What an annotation is', 'cards' => [
        ['label' => 'A computed hit',
         'text'  => 'Not a curated statement about this gene — a result some analysis produced for this sequence.'],
        ['label' => 'Attached per transcript', 'html' => true,
         'text'  => 'Annotations belong to an ' . gloss('mRNA') . ', not to the ' . gloss('gene') . ', which is why they are grouped under each isoform.'],
        ['label' => 'Only what exists',
         'text'  => 'A type with no results is not shown at all, so a missing section means no hits, not an error.'],
        ['label' => 'The number on each badge',
         'text'  => 'How many rows that type has for that transcript. The same counts appear in the section list on the left.'],
    ]],
];
if ($annot_cards) {
    $annot_sections[] = ['heading' => 'The types on this site', 'cards' => $annot_cards];
}
$annot_sections[] = ['heading' => 'Getting them out', 'cards' => [
    ['label' => 'Download All Annotations',
     'text'  => 'Every annotation for this gene, all types and all transcripts, as one CSV.'],
    ['label' => 'Per-table export', 'html' => true,
     'text'  => 'Each table has its own <strong>Copy</strong>, <strong>CSV</strong> and <strong>Excel</strong> buttons for just that type.'],
    ['label' => 'Search within a type',
     'text'  => 'The box beside those buttons filters that one table as you type.'],
]];

echo help_modal('annotations-help', 'Annotations on this page', $annot_sections,
    ['intro' => 'What these results are, and where each one came from.']);
