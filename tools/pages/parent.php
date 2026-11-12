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
        <?= t('Jump to') . "\n" ?>
        <svg class="pnav-jb-chev" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
      </button>
      <?php /* Matches the first sidebar entry, which is the feature's own ID — see the
               note on #pnav-overview. The JS overwrites this on scroll; it only has to be
               right before the first scrollspy tick. */ ?>
      <span class="pnav-jb-current" id="pnavJbCurrent"><?= htmlspecialchars($feature_uniquename) ?></span>
      <div class="pnav-jb-dd" id="pnavJbDd"></div>
    </div>

    <div class="pnav-layout">
      <aside class="pnav-side" aria-label="<?= t('Jump to') ?>">
        <div class="pnav-side-head">
          <span class="pnav-side-title"><?= t('Jump to') ?></span>
          <button class="pnav-toggle" id="pnavToggle" type="button" aria-label="<?= t('Collapse navigation') ?>" aria-expanded="true" title="<?= t('Collapse (content goes full width)') ?>">
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
            // The symbol is shown separately, before the title; it never doubles as the title.
            // ($name here used to be the fallback, but it was never passed to this file.)
            $gene_symbol = $gene_symbol ?? '';
            $overview_title = !empty($description) ? decodeAnnotationText($description) : '';

            // How this gene was named (gene_naming). The first statement says what the gene
            // is — the Identity sentence, or why it has no name — and sits under the title;
            // the rest are the labelled point-by-point list. Absent on databases without
            // naming tables, which leaves the card exactly as it was.
            $naming_statements = $gene_naming['statements'] ?? [];
            $naming_lead = null;
            $naming_list = [];
            foreach ($naming_statements as $__s) {
                if ($naming_lead === null && in_array($__s['kind'], ['identity', 'no_name'], true)) {
                    $naming_lead = $__s;
                } else {
                    $naming_list[] = $__s;
                }
            }
            if ($naming_lead !== null && $naming_lead['kind'] === 'no_name') {
                // The description of an unnamed gene is only its own transcript ID, which
                // the heading would repeat to no purpose. Say plainly that it has no name;
                // the reason is the line directly beneath.
                $overview_title = 'Unnamed gene';
                $gene_symbol = '';
            } else {
                // The naming pipeline ends each name with its evidence code, e.g.
                // "Sushi/SCR/CCP domain-containing protein [ISM|ipr|sim~|omaR]". Unreadable at a
                // glance (user, 2026-10-06); the Identity sentence below says it in words. Matched
                // by the pipeline's own codes only, so it applies whether or not this database
                // has naming tables (organisms not yet reloaded still carry the tag).
                $overview_title = moop_strip_naming_tag($overview_title);
            }

            // Plain-text summary for pasting into notes. Built here rather than scraped
            // from the DOM: the overview is a <dl> of labels and links, so a hand
            // selection drags in blank lines and layout whitespace. Same fields, same
            // order as the box, one per line, no headings — the user asked for exactly
            // what they would have highlighted, minus the mess.
            // One line however many isoforms: the longest protein, and "longest of N" only
            // when there is a choice. Plain text here; the card adds the same words.
            $protein_length_text = '';
            if ($longest_protein !== '') {
                $n_prot = count($protein_lengths);
                $protein_length_text = 'Protein: ' . number_format($protein_lengths[$longest_protein]) . ' aa'
                    . ($n_prot > 1 ? ' (longest of ' . $n_prot . ')' : '');
            }
            $copy_lines = [$feature_uniquename];
            $copy_lines[] = trim($gene_symbol . ($gene_symbol !== '' && $overview_title !== '' ? ' — ' : '') . $overview_title);
            $badges = htmlspecialchars_decode(strip_tags($type));
            if (!empty($children_hierarchical)) {
                $n = count($children_hierarchical);
                $__ct = $children_hierarchical[0]['feature_type'] ?? 'mRNA';
                $badges .= ' ' . ($n > 1 ? t_plain('%1$d %2$s children', $n, $__ct) : t_plain('%1$d %2$s child', $n, $__ct));
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
            if ($longest_protein !== '') {
                $copy_lines[] = $protein_length_text;
            }
            // The naming statements, after the rest: the lead sentence, then one labelled
            // line per statement, in the card's order (user, 2026-10-06).
            if ($naming_lead !== null) {
                $copy_lines[] = $naming_lead['naming_text']
                    . (!empty($naming_lead['accession']) ? ' (' . $naming_lead['accession'] . ')' : '');
            }
            foreach ($naming_list as $__s) {
                $copy_lines[] = moop_gene_naming_label($__s['kind']) . ': ' . $__s['naming_text'];
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
                        <i class="fa fa-copy"></i> <span class="copy-label"><?= t('Copy') ?></span>
                    </button>
                    <?= help_modal_trigger('gene-page-help', '', 'Help: what this page shows') ?>
                    <?php /* Declared, not shown — the title bar already says which gene this is;
                             this exists so the page declares its PURPOSE for the router. */ ?>
                    <?= page_purpose('Everything recorded for one gene — its structure, transcripts, annotations and sequences.', '', false, false) ?>
                </span>
            </div>
            <div class="feature-overview-body">
                <h1 class="feature-title">
                    <?php if ($gene_symbol !== ''): ?>
                        <span class="feature-symbol" title="<?= t('Gene symbol') ?>"><?= htmlspecialchars($gene_symbol) ?></span>
                    <?php endif; ?>
                    <?php if ($overview_title !== ''): ?>
                        <?= htmlspecialchars($overview_title) ?>
                    <?php elseif ($gene_symbol === ''): ?>
                        <span class="feature-title-empty"><?= t('No description available') ?></span>
                    <?php endif; ?>
                </h1>
                <?php /* The chips belong to the title: they say what kind of feature this is.
                         They sat below the naming statements for a while, cut off from it. */ ?>
                <div class="mb-2">
                    <span class="badge bg-feature-gene text-white badge-sm"><?= htmlspecialchars($type) ?></span>
                    <?php if (!empty($children_hierarchical)):
                        $first_child_type = $children_hierarchical[0]['feature_type'] ?? 'mRNA';
                        $child_class = strtoupper($first_child_type) === 'MRNA' ? 'bg-feature-mrna' : 'bg-feature-gene';
                        $direct_child_count = count($children_hierarchical);
                    ?>
                        <span class="badge text-white ms-1 badge-sm <?= $child_class ?>">
                            <?= $direct_child_count > 1 ? t('%1$d %2$s children', $direct_child_count, $first_child_type) : t('%1$d %2$s child', $direct_child_count, $first_child_type) . "\n" ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($naming_lead !== null): ?>
                <p class="gene-naming-lead<?= $naming_lead['kind'] === 'no_name' ? ' is-unnamed' : '' ?>">
                    <?= htmlspecialchars($naming_lead['naming_text']) ?>
                    <?php if (!empty($naming_lead['accession'])): ?>
                        <?php if (!empty($naming_lead['url'])): ?>
                            <a href="<?= htmlspecialchars($naming_lead['url']) ?>" target="_blank" rel="noopener"
                               class="gene-naming-acc"><?= htmlspecialchars($naming_lead['accession']) ?><i class="fa fa-external-link-alt link-icon"></i></a>
                        <?php else: ?>
                            <span class="gene-naming-acc"><?= htmlspecialchars($naming_lead['accession']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($naming_list)): ?>
                <?php /* Open by default; the annotation tables below start collapsed instead
                         (user, 2026-10-06: "I really don't want to overwhelm users"). Long
                         statements clamp to two lines with a "more" — Cautions run to 571
                         characters. */ ?>
                <dl class="gene-naming-list">
                    <?php foreach ($naming_list as $__s): ?>
                        <dt class="gn-kind-<?= htmlspecialchars($__s['kind']) ?>"><?= htmlspecialchars(moop_gene_naming_label($__s['kind'])) ?></dt>
                        <dd class="gn-kind-<?= htmlspecialchars($__s['kind']) ?>">
                            <span class="gn-text"><?= htmlspecialchars($__s['naming_text']) ?><?php
                                if ($__s['kind'] === 'protein' && !empty($naming_isoform['mrna'])): ?>
                                <a href="#<?= htmlspecialchars(moop_annotation_card_anchor($naming_isoform['mrna'])) ?>" class="gene-naming-acc">its annotations</a><?php endif; ?></span>
                            <button type="button" class="gn-more" hidden><?= t('more') ?></button>
                        </dd>
                    <?php endforeach; ?>
                </dl>
                <?php if (!empty($gene_naming['data_version'])):
                    // A gene that keeps its own name (RefSeq, Ensembl…) carries a source_name
                    // statement ending "(from RefSeq)". MOOP did not name it then — saying so
                    // contradicted the lead line, which reads "Named by its own annotation".
                    $__kept_from = null;
                    $__moop_name = false;
                    foreach ($naming_statements as $__s) {
                        if ($__s['kind'] === 'source_name' && preg_match('/\(from ([^)]+)\)\s*$/', $__s['naming_text'], $__m)) {
                            $__kept_from = $__m[1];
                        }
                        $__moop_name = $__moop_name || $__s['kind'] === 'pipeline_name';
                        // A name from a naming species (copied or "-like") or a curator is not from
                        // HGNC: "Named by MOOP from HGNC release" was wrong for planarian names copied
                        // from S. mediterranea S3h1 (user, 2026-11-04). The Identity statement says
                        // where the name came from; the HGNC release dates MOOP's human evidence.
                        if ($__s['kind'] === 'identity' && $__kept_from === null) {
                            if (($__s['link_kind'] ?? '') === 'ncbi'
                                && preg_match('/(?:name copied from (.+)|named after it \((.+)\), marked -like)$/', $__s['naming_text'], $__m)) {
                                $__kept_from = $__m[1] !== '' ? $__m[1] : $__m[2];
                            } elseif (str_starts_with($__s['naming_text'], 'Named by a curator')) {
                                // (a curated Identity has no link: its link_kind is blank)
                                $__kept_from = 'a curator';
                            }
                        }
                    }
                    $__release = htmlspecialchars($gene_naming['data_version']);
                ?>
                    <?php if ($__kept_from !== null): ?>
                    <p class="gene-naming-version"><?= $__moop_name
                        ? t_markup("Name from %1\$s · MOOP's evidence and suggested name use HGNC release %2\$s", htmlspecialchars($__kept_from), $__release)
                        : t_markup("Name from %1\$s · MOOP's evidence uses HGNC release %2\$s", htmlspecialchars($__kept_from), $__release) ?></p>
                    <?php else: ?>
                    <p class="gene-naming-version"><?= t_markup('Named by MOOP from HGNC release %s', $__release) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
                <?php
                $jbrowse_assembly_file = $config->getPath('metadata_path')
                    . '/jbrowse2-configs/assemblies/'
                    . $organism_name . '_' . $genome_accession . '.json';
                $jbrowse_loc = file_exists($jbrowse_assembly_file)
                    ? (!empty($feature_loc) ? $feature_loc['loc_string'] : $feature_uniquename)
                    : null;
                ?>
                <dl class="feature-info-grid mb-0">
                    <dt><?= t('Organism') ?></dt>
                    <dd><a href="<?= moop_url_base() ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><em class="sci-name"><?= htmlspecialchars($genus) ?> <?= htmlspecialchars($species) ?></em><?php if ($common_name): ?> (<?= htmlspecialchars($common_name) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <dt><?= t('Assembly') ?></dt>
                    <dd><a href="<?= moop_url_base() ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&parent=<?= urlencode($feature_uniquename) ?>" class="link-light-bordered"><?= htmlspecialchars(assembly_label($genome_name, $genome_accession)) ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <dt><?= t('Gene Set') ?></dt>
                    <dd><a href="<?= moop_url_base() ?>/tools/gene_set.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>" class="link-light-bordered"><?= htmlspecialchars($gene_set_name) ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                    <?php if (!empty($feature_loc)): ?>
                    <dt><?= t('Location') ?></dt>
                    <dd><?php
                        $loc_text = htmlspecialchars($feature_loc['seqname'])
                            . ':' . number_format($feature_loc['start'])
                            . '&ndash;' . number_format($feature_loc['end']);
                        if ($feature_loc['strand'] === '+' || $feature_loc['strand'] === '-') {
                            $loc_text .= ' (' . ($feature_loc['strand'] === '+' ? '+' : '&minus;') . ')';
                        }
                        if ($jbrowse_loc && moop_tool_on('genome_browser')) {  // switched-off tool: no link to it (moop_tool_on(), lib/functions_tools.php)
                            $browser_url = moop_url_base() . '/jbrowse2.php?organism=' . urlencode($organism_name)
                                . '&assembly=' . urlencode($genome_accession)
                                . '&loc=' . urlencode($feature_loc['loc_string']);
                            echo '<a href="' . $browser_url . '" target="_blank" class="link-light-bordered">' . $loc_text . ' <i class="fa fa-external-link-alt link-icon"></i></a>';
                        } else {
                            echo $loc_text;
                        }
                    ?></dd>
                    <?php endif; ?>
                    <?php if ($longest_protein !== ''): ?>
                    <dt><?= t('Protein') ?></dt>
                    <dd><?= t('%s aa', number_format($protein_lengths[$longest_protein])) ?><?php if (count($protein_lengths) > 1): ?>
                        <span class="text-muted" title="<?= htmlspecialchars($longest_protein) ?>"><?= t('(longest of %d)', count($protein_lengths)) ?></span><?php endif; ?></dd>
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
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#geneModelSection" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Gene Structure') ?>" title="<?= t('Show or hide Gene Structure') ?>">
                <i class="fas fa-minus toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Gene Structure') ?></span>
            <span class="ms-2 text-muted small">
                <?= number_format(count($gene_model['isoforms'])) ?> <?= gloss('isoform', count($gene_model['isoforms']) !== 1 ? 'isoforms' : 'isoform') ?>
                <?php if (!empty($big_gene)): ?>
                    · <span title="<?= t('Too many to draw at once. Open a transcript under Annotations to draw its own structure.') ?>"><?= t('showing the longest') ?></span>
                <?php endif; ?>
            </span>
            <?= help_modal_trigger('gene-model-help', '', 'Help: reading the gene structure diagram') ?>
            <?php /* One treatment for all of these: they are all "get this gene's data out".
                     They used to be outline-secondary, outline-primary and outline-success,
                     so the colours implied three different kinds of thing and signalled
                     nothing. The VERB is carried by the icon instead — palette opens a
                     viewer, download downloads — and the two downloads are grouped because
                     they are the same action in two formats. */ ?>
            <?php /* flex-wrap: on a phone the three buttons were 0.6 px wider than the header,
                     enough to make the page scroll sideways. */ ?>
            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($genome_seq_available)): ?>
                <button class="btn btn-sm moop-data-btn" id="gene-model-fmt-btn"
                        title="<?= t('Open the sequence with each feature type highlighted') ?>"><i class="fas fa-palette me-1"></i><?= t('Format sequence') ?></button>
                <?php endif; ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= t('Download this gene') ?>">
                    <?php /* Not on a transcript assembly: the "genomic" sequence would be the transcript again */ ?>
                    <?php if (!empty($genome_seq_available) && empty($genome_is_transcripts)): ?>
                    <button class="btn moop-data-btn" id="gene-model-seq-btn"
                            title="<?= t('Download the genomic sequence — gene locus plus each isoform span') ?>"><i class="fas fa-download me-1"></i><?= t('FASTA') ?></button>
                    <?php endif; ?>
                    <button class="btn moop-data-btn" id="gene-model-gff-btn"
                            title="<?= t('Download GFF3 — gene, mRNA, exon, CDS, UTR and all sub-features') ?>"><i class="fas fa-download me-1"></i><?= t('GFF') ?></button>
                </div>
            </div>
        </div>
        <div id="geneModelSection" class="collapse show">
            <div class="card-body p-3">
                <?php
                $__svg = '<svg id="gene-model-svg" width="100%" style="display:block; overflow:visible; flex:1 1 0; min-width:0;"'
                       . (!empty($big_gene_diagram_anchor) ? ' data-show-only="' . htmlspecialchars($big_gene_diagram_anchor) . '"' : '')
                       . '></svg>';
                ?>
                <?php if (!empty($neighbor_nav)):
                    // The diagram is always drawn 5'->3' (a reverse-strand gene is flipped), so
                    // upstream is on the LEFT of the picture for every gene. Which way that is in
                    // the genome depends on strand: '+' upstream = lower coordinates. A gene with
                    // no strand is drawn as '+', so its buttons read in genome order instead.
                    $__stranded = in_array($neighbor_nav['strand'], ['+', '-'], true);
                    $__up_side  = $neighbor_nav['strand'] === '-' ? 'right' : 'left';
                    $__dn_side  = $__up_side === 'left' ? 'right' : 'left';
                    // In the visitor's language: the words also reach js/modules/gene-neighbors.js via data-label.
                    $__up_label = htmlspecialchars($__stranded ? t_plain('upstream gene') : t_plain('previous gene'));
                    $__dn_label = htmlspecialchars($__stranded ? t_plain('downstream gene') : t_plain('next gene')); ?>
                <?php /* « and » either side of the diagram, on the side each neighbour lies (Sofia,
                         2026-10-27). They were two labelled buttons on a row of their own, which on
                         a phone needed ~300 px and stuck out of the card, so the page scrolled
                         sideways. data-label carries the words the status line uses; the hover says
                         "Go to the upstream gene page" (a Bootstrap tooltip made by gene-neighbors.js, with no
                         fade). No data-bs-toggle="tooltip": parent-tools.js would then make a second,
                         default (fading) one on the same button, and that is the one that showed. */ ?>
                <div class="gene-neighbors"
                     data-organism="<?= htmlspecialchars($neighbor_nav['organism']) ?>"
                     data-assembly="<?= htmlspecialchars($neighbor_nav['assembly']) ?>"
                     data-gene-set="<?= htmlspecialchars($neighbor_nav['gene_set']) ?>"
                     data-uniquename="<?= htmlspecialchars($neighbor_nav['uniquename']) ?>">
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm moop-data-btn gene-neighbor-btn gene-neighbor-chevron" data-side="<?= $__up_side ?>"
                                data-label="<?= $__up_label ?>" aria-label="<?= t_markup('Go to the %s', $__up_label) ?>"
                                title="<?= t_markup('Go to the %s page', $__up_label) ?>">
                            <i class="fas fa-angle-double-left" aria-hidden="true"></i></button>
                        <?= $__svg ?>
                        <button type="button" class="btn btn-sm moop-data-btn gene-neighbor-btn gene-neighbor-chevron" data-side="<?= $__dn_side ?>"
                                data-label="<?= $__dn_label ?>" aria-label="<?= t_markup('Go to the %s', $__dn_label) ?>"
                                title="<?= t_markup('Go to the %s page', $__dn_label) ?>">
                            <i class="fas fa-angle-double-right" aria-hidden="true"></i></button>
                    </div>
                    <div class="gene-neighbor-status small text-muted text-center mt-1" role="status"></div>
                </div>
                <?php else: ?>
                <?= $__svg ?>
                <?php endif; ?>
                <?php /* The legend drops Intron for these, but a one-block drawing with no note
                         reads as a broken or missing gene model. Say why there is nothing to draw. */ ?>
                <?php if (!empty($genome_is_transcripts) && moop_site_is_transcriptomes()): ?>
                <p class="small text-muted mb-0 mt-2">
                    <?= t('Exons already joined: a transcriptome') . "\n" ?>
                    <?= field_help(
                        'This species\' gene sequences come from its RNA, where each gene is already a '
                        . 'spliced transcript: the exons are joined and the introns are gone, so there '
                        . 'are none to draw.',
                        'Exons already joined'
                    ) ?>
                </p>
<?php elseif (!empty($genome_is_transcripts)): ?>
                <p class="small text-muted mb-0 mt-2">
                    <?= t('No introns: transcript assembly') . "\n" ?>
                    <?= field_help(
                        'This assembly is one assembled transcript per gene, not a genome. Each gene is '
                        . 'its own spliced transcript, so its exons are already joined and there is no '
                        . 'genome sequence to place introns or flanks in.',
                        'No introns'
                    ) ?>
                </p>
                <?php endif; ?>

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
                // An assembled transcript has no introns (and no flanks, below) to point at
                if (!empty($genome_is_transcripts)) $gene_model_legend = array_values(array_filter($gene_model_legend, fn($l) => $l['label'] !== 'Intron'));
                if (!empty($genome_seq_available) && empty($genome_is_transcripts)) {
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
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#hierarchySection" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Feature Hierarchy') ?>" title="<?= t('Show or hide Feature Hierarchy') ?>">
                <i class="fas fa-sitemap toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Feature Hierarchy') ?></span>
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
                                    <span class="badge bg-success text-white badge-sm"><?= $parent_annot_count > 1 ? t('%d annotations', $parent_annot_count) : t('%d annotation', $parent_annot_count) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($big_gene)):
                                    // Thousands of tree lines (each transcript with its CDS and
                                    // protein: 632 KB at 1,083 transcripts) say nothing a reader can
                                    // take in. One line, and the list under Annotations has them all.
                                    $__first = $children_hierarchical[0]['feature_type'] ?? 'mRNA'; ?>
                                    <ul><li><span class="tree-char">└── </span>
                                        <span class="text-dark"><?= number_format(count($children_hierarchical)) ?> <?= htmlspecialchars($__first) ?></span>
                                        <span class="text-muted small"><?= t('— each with its CDS and protein; listed under Annotations') ?></span>
                                    </li></ul>
                                <?php else: ?>
                                <?= generateTreeHTML($children_hierarchical, $all_annotations, $analysis_order, 0, $protein_lengths, $longest_protein, (string)($naming_isoform['protein'] ?? '')) ?>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($expression_summary)):
        // Expression — "is this gene expressed?", in words. Deliberately no chart and no numbers
        // on the face of it (user, 2026-10-09: "most users don't care about TPM, they like
        // genes"); the TPM behind each word is in the hover text for those who do.
        // Spec: notes/EXPRESSION_COUNT_TABLES_PLAN.md.
        $__ex      = $expression_summary;
        $__n_exp   = count($__ex['experiments']);
        $__tpm     = fn(?float $v) => $v === null ? '' : ($v < 10 ? number_format($v, 1) : number_format($v, 0));
    ?>
    <!-- Expression Section -->
    <div class="card shadow-sm mb-4" id="pnav-expression" data-nav-label="Expression">
        <div class="card-header d-flex align-items-center flex-wrap">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#expressionSection" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Expression') ?>" title="<?= t('Show or hide Expression') ?>">
                <i class="fas fa-chart-bar toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Expression') ?></span>
            <span class="ms-2 text-muted small">
                <?php if ($__ex['with_data'] === 0): ?>
                    <?= ($__n_exp !== 1 ? t('not measured in any of %d experiments', $__n_exp) : t('not measured in any of %d experiment', $__n_exp)) . "\n" ?>
                <?php else: ?>
                    <?= ($__ex['with_data'] !== 1 ? t('detected in %1$d of %2$d experiments', $__ex['detected'], $__ex['with_data'])
                                                   : t('detected in %1$d of %2$d experiment', $__ex['detected'], $__ex['with_data'])) . "\n" ?>
                    <?php if ($__ex['with_data'] < $__n_exp): ?>
                        · <?= t('%d without data for this gene', $__n_exp - $__ex['with_data']) . "\n" ?>
                    <?php endif; ?>
                <?php endif; ?>
            </span>
            <?php if ($__ex['with_data'] > 0): ?>
            <?php /* Into the Expression Explorer with this gene already in the list, to add others. */ ?>
<?php if (moop_tool_on('expression')): ?>
            <a class="btn btn-sm btn-outline-secondary py-0 ms-auto"
               href="<?= htmlspecialchars(moop_url_base()) ?>/tools/expression.php?<?= htmlspecialchars(http_build_query([
                   'organism' => $organism_name, 'assembly' => $genome_accession, 'gene_set' => $gene_set_name,
                   'genes' => $feature_uniquename])) ?>"
               title="Open the Expression Explorer with this gene, to compare it with others">
                <i class="fa fa-th"></i> <?= t('Compare genes') ?></a>
<?php endif; ?>
            <form method="POST" action="" class="ms-1">
                <?= csrf_input_field() ?>
                <input type="hidden" name="download_expression" value="1">
                <button type="submit" class="btn btn-sm btn-outline-secondary py-0"
                        title="<?= t('This gene\'s value in every sample, one row per sample, with its unit') ?>">
                    <i class="fa fa-download"></i> <?= t('TSV') . "\n" ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div id="expressionSection" class="collapse show">
            <div class="card-body py-2">
                <?php
                // The first few experiments are shown; the rest sit in a collapsed block behind
                // "Show all" (user, 2026-10-09). All values come from the one read the controller
                // already made, so revealing them costs nothing — Bootstrap's collapse does the
                // toggle, no script of ours.
                $__first = 5;
                $__render_row = function (array $__e) use ($__tpm) {
                    $__lvl  = $__e['level'];
                    $__hint = $__e['call'] === 'unknown'
                        ? 'This gene is not in this experiment\'s data.'
                        : 'Highest condition average: ' . $__tpm($__e['top_mean']) . ' ' . $__e['unit']
                          . ' (' . $__e['top_group'] . '). Detected means at least '
                          . $__tpm($__e['threshold']) . ' ' . $__e['unit'] . ' in some condition.';
                    $__pmid = preg_match('/^PMID:\s*(\d+)$/i', $__e['citation'], $__m) ? $__m[1] : '';
                    ?>
                        <tr>
                            <td class="expr-exp">
                                <?= htmlspecialchars($__e['label']) ?>
                                <?php if ($__pmid !== ''): ?>
                                    <a class="expr-cite" href="https://pubmed.ncbi.nlm.nih.gov/<?= $__pmid ?>/" target="_blank" rel="noopener"
                                       title="Publication for this experiment">PMID <?= $__pmid ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="expr-lvl-cell">
                                <span class="expr-level expr-level-<?= str_replace(' ', '-', $__lvl) ?>" title="<?= htmlspecialchars($__hint) ?>"><?= htmlspecialchars($__lvl) ?></span>
                            </td>
                            <td class="expr-spark-cell"><?= moop_expression_sparkline_svg($__e['groups'], $__lvl, $__e['unit']) ?></td>
                        </tr>
                    <?php
                };
                $__shown = array_slice($__ex['experiments'], 0, $__first);
                $__more  = array_slice($__ex['experiments'], $__first);
                ?>
                <table class="table table-sm align-middle mb-1 expr-table">
                    <tbody>
                    <?php foreach ($__shown as $__e) $__render_row($__e); ?>
                    </tbody>
                    <?php if ($__more): ?>
                    <tbody id="expressionMore" class="collapse">
                    <?php foreach ($__more as $__e) $__render_row($__e); ?>
                    </tbody>
                    <?php endif; ?>
                </table>
                <?php if ($__more): ?>
                <button type="button" class="btn btn-sm btn-link px-0 expr-more-btn collapsed"
                        data-bs-toggle="collapse" data-bs-target="#expressionMore" aria-expanded="false" aria-controls="expressionMore">
                    <span class="when-collapsed"><?= t('Show all %d experiments', $__n_exp) ?></span>
                    <span class="when-expanded"><?= t('Show fewer') ?></span>
                </button>
                <?php endif; ?>
                <div class="text-muted expr-key">
                    <?= t('Level = the condition with the highest average (replicates averaged): off below 1 · low 1–10 · medium 10–100 · high 100 and above (%s).',
                          moop_expression_units_label($__ex['experiments'])) ?>
                    <?= t("Bars: each condition's average, in order; hover a bar for its value.") . "\n" ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php if (!empty($page_section)):   /* at column 0, no blank line before: emits nothing at all when absent */
        // PAGE: where this gene was published as expressed (curated, by anatomy term), and its ids in
        // other transcriptomes. Built in tools/parent.php from anatomy_expression.sqlite + crosswalk.sqlite.
        // A compact summary first (terms with counts); the records and the ids open on demand.
        $__pc  = $page_section['curations'];
        $__pl  = $page_section['labels'];
        $__PL  = fn(?string $id) => $id === null || $id === '' ? '' : ($__pl[$id] ?? $id);
        $__by_term = [];
        foreach ($__pc as $__r) $__by_term[$__r['term_id']] = ($__by_term[$__r['term_id']] ?? 0) + 1;
        arsort($__by_term);
        $__papers = count(array_unique(array_column($__pc, 'pmid')));
        $__aka_by = [];
        foreach ($page_section['aka'] as $__m) $__aka_by[$__m['source']][] = $__m['accession'];
    ?>
    <!-- Anatomical expression (curated; data source per organism, e.g. PAGE) -->
    <div class="card shadow-sm mb-4" id="pnav-page" data-nav-label="Anatomical expression">
        <div class="card-header d-flex align-items-center flex-wrap">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#pageSection" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Anatomical expression') ?>" title="<?= t('Show or hide Anatomical expression') ?>">
                <i class="fas fa-microscope toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Anatomical expression') ?></span>
            <span class="ms-2 text-muted small">
                <?php if ($__pc): ?>
                    <?= ($__papers !== 1 ? t('%1$d records in %2$d anatomy terms, from %3$d papers', count($__pc), count($__by_term), $__papers)
                                         : t('%1$d records in %2$d anatomy terms, from %3$d paper', count($__pc), count($__by_term), $__papers)) . "\n" ?>
                <?php else: ?>
                    <?= t('no published expression records') . "\n" ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="collapse show" id="pageSection">
            <div class="card-body">
                <?php if ($__pc): ?>
                <p class="small text-muted mb-2"><?= t('Published as expressed in:') ?><?php if ($page_section['source'] !== ''): ?> <span class="ms-1"><?= t('Data:') ?> <?= $page_section['source_url'] !== '' ? '<a href="' . htmlspecialchars($page_section['source_url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($page_section['source']) . '</a>' : htmlspecialchars($page_section['source']) ?></span><?php endif; ?></p>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <?php foreach (array_slice($__by_term, 0, 40, true) as $__tid => $__n): ?>
                        <a class="badge rounded-pill text-bg-light border text-decoration-none" href="<?= htmlspecialchars(moop_url('tools/anatomy_search.php') . '?' . http_build_query(['term' => $__tid, 'rel' => 'rdfs:subClassOf'])) ?>" title="<?= htmlspecialchars($__tid) ?>"><?= htmlspecialchars($__PL($__tid)) ?> <span class="text-muted"><?= (int) $__n ?></span></a>
                    <?php endforeach; ?>
                    <?php if (count($__by_term) > 40): ?><span class="small text-muted"><?= t('and %d more', count($__by_term) - 40) ?></span><?php endif; ?>
                </div>
                <details class="mb-3">
                    <summary class="small"><?= t('Show all %d records', count($__pc)) ?></summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-striped align-middle small mb-0">
                            <thead><tr><th><?= t('Expressed in') ?></th><th><?= t('Evidence') ?></th><th><?= t('Life stage') ?></th><th><?= t('Specimen') ?></th><th><?= t('Recorded on') ?></th><th><?= t('Paper') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($__pc as $__r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($__PL($__r['term_id'])) ?></td>
                                    <?php foreach (['evidence_id', 'stage_id', 'specimen_id'] as $__col): $__v = (string) ($__r[$__col] ?? ''); ?>
                                    <td><?= $__v === '' ? '' : moop_term_icon($__v, $__PL($__v)) . htmlspecialchars($__PL($__v)) ?></td>
                                    <?php endforeach; ?>
                                    <td class="font-monospace"><?= htmlspecialchars($__r['accession']) ?></td>
                                    <td><a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode((string) $__r['pmid']) ?>/" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) $__r['title']) ?>"><?= htmlspecialchars(trim(($__r['authors'] ?: 'PMID ' . $__r['pmid']) . ' ' . $__r['year'])) ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <?php endif; ?>
                <?php if ($__aka_by): ?>
                <details>
                    <summary class="small"><?= t('Also known as: %1$d ids in %2$d other transcriptomes and datasets', count($page_section['aka']), count($__aka_by)) ?><?= $page_section['name'] !== '' ? ' <span class="text-muted">(' . htmlspecialchars(t_plain($page_section['name'])) . ')</span>' : '' ?></summary>
                    <dl class="row small mb-0 mt-2">
                        <?php foreach ($__aka_by as $__src => $__ids): ?>
                            <dt class="col-sm-3 font-monospace"><?= htmlspecialchars($__src) ?></dt>
                            <dd class="col-sm-9 font-monospace"><?= htmlspecialchars(implode(' ', array_slice(array_unique($__ids), 0, 50))) ?><?= count(array_unique($__ids)) > 50 ? ' …' : '' ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </details>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php if (!empty($image_section)):
    // Gene images, one titled part per ANALYSIS (images/analyses.tsv). Thumbnails open a quick
    // view (js/modules/gene-images.js); every file goes through api/gene_image.php, which
    // re-checks access. docs/ORGANISM_SIDE_FILES.md §2.
    $__il  = $image_section['labels'];
    $__IL  = fn(?string $id) => $id === null || $id === '' ? '' : ($__il[$id] ?? $id);
    $__img_url = fn(string $file, bool $thumb) => moop_url('api/gene_image.php') . '?'
        . http_build_query(['organism' => $organism_name, 'file' => $file] + ($thumb ? ['size' => 'thumb'] : []));
    $__ng = count($image_section['groups']);
    // Stills are thumbnails here; movies and panels (MOOP_IMAGE_GALLERY_ONLY) are counted and linked to
    // the gallery, filtered to this gene -- a gene page about one gene, not the embryo's architecture.
    $__still_total = 0;
    foreach ($image_section['groups'] as &$__gg) {
        $__gg['stills'] = array_values(array_filter($__gg['images'], fn($i) => !in_array($i['kind'], MOOP_IMAGE_GALLERY_ONLY, true)));
        $__gg['elsewhere'] = array_count_values(array_column(array_filter($__gg['images'], fn($i) => in_array($i['kind'], MOOP_IMAGE_GALLERY_ONLY, true)), 'kind'));
        $__still_total += count($__gg['stills']);
    }
    unset($__gg);
?>
    <!-- Gene images, by analysis -->
    <div class="card shadow-sm mb-4" id="pnav-images" data-nav-label="Images">
        <div class="card-header d-flex align-items-center flex-wrap">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#imagesSection" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Images') ?>" title="<?= t('Show or hide Images') ?>">
                <i class="fas fa-images toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Images') ?></span>
            <span class="ms-2 text-muted small"><?= ($__ng !== 1 ? t('%1$d images from %2$d analyses', $__still_total, $__ng)
                                                                 : t('%1$d images from %2$d analysis', $__still_total, $__ng)) . "\n" ?></span>
        </div>
        <div class="collapse show" id="imagesSection">
            <div class="card-body">
                <?php foreach ($image_section['groups'] as $__gi => $__g): $__a = $__g['analysis']; ?>
                <?= '<div class="gene-images-analysis' . ($__gi ? ' mt-4' : '') . '" id="pnav-images-' . (int) $__gi . '" data-nav-sub="' . htmlspecialchars($__a['title']) . '">' . "\n" ?>
                    <h6 class="mb-1"><?= htmlspecialchars($__a['title']) ?></h6>
                    <p class="small text-muted mb-2">
                        <?= $__a['description'] !== null ? htmlspecialchars($__a['description']) : '' ?>
                        <?php if ($__a['pmid']): ?><a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode($__a['pmid']) ?>/" target="_blank" rel="noopener"><?= htmlspecialchars($__a['citation'] ?: 'PMID ' . $__a['pmid']) ?></a><?php endif; ?>
                        <?php if (!empty($__a['link_url'])): ?><br><a href="<?= htmlspecialchars($__a['link_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(t_plain($__a['link_text'])) ?></a><?php endif; ?>
                    </p>
                    <?php if ($__g['elsewhere']):
                        $__parts = [];
                        foreach ($__g['elsewhere'] as $__k => $__n) {
                            $__parts[] = $__k === 'movie' ? ($__n !== 1 ? t('%d embryo movies', $__n) : t('%d embryo movie', 1))
                                                          : ($__n !== 1 ? t('%d stage panels', $__n) : t('%d stage panel', 1));
                        }
                        $__gal = moop_url('tools/image_gallery.php') . '?' . http_build_query(['organism' => $organism_name, 'analysis' => $__a['analysis'],
                                 'gene' => $feature_uniquename, 'kind' => implode(',', array_keys($__g['elsewhere']))]);
                    ?>
                    <p class="small mb-2 gene-images-elsewhere"><i class="fas fa-film me-1 text-muted"></i><?= t_markup('This gene also appears in %1$s. %2$s', count($__parts) === 2 ? t_plain('%1$s and %2$s', $__parts[0], $__parts[1]) : implode(', ', $__parts),
                        '<a href="' . htmlspecialchars($__gal) . '">' . t('View them') . '</a>') ?></p>
                    <?php endif; ?>
                    <?php if ($__g['stills']): ?>
                    <div class="gene-images-grid">
                        <?php if (!empty($__a['key_image'])):
                            // The analysis's key (a cluster map): first, so the plots after it can be read.
                            $__key_url = moop_url('api/gene_image.php') . '?' . http_build_query(['organism' => $organism_name, 'key' => $__a['analysis']]);
                            $__key_cap = (string) ($__a['key_caption'] ?: t_plain('Key'));
                        ?>
                        <?= '<button type="button" class="gene-image-tile gene-image-key" title="' . htmlspecialchars($__key_cap) . '" data-full="' . htmlspecialchars($__key_url) . '" data-movie="" data-title="' . htmlspecialchars($__key_cap) . '" data-caption="" data-genes="" data-terms="" data-credit="">' . "\n" ?>
                            <?= '<img src="' . htmlspecialchars($__key_url . '&size=thumb') . '" alt="' . htmlspecialchars($__key_cap) . '" loading="lazy" decoding="async" width="160" height="120">' . "\n" ?>
                            <span class="gene-image-label"><strong><?= t('Cluster map') ?></strong></span>
                        </button>
                        <?php endif; ?>
                        <?php foreach ($__g['stills'] as $__im):
                            $__is_movie = $__im['kind'] === 'movie';
                            $__stage = $__IL($__im['stage_id']);
                            $__label = trim(($__stage !== '' ? $__stage . ' · ' : '') . t_plain($__im['kind']));
                            $__data = [
                                'full'    => $__img_url($__im['file'], false),
                                'movie'   => $__is_movie ? '1' : '',
                                'title'   => $__label,
                                'caption' => (string) $__im['caption'],
                                'genes'   => implode(', ', array_map(fn($id) => isset($image_section['names'][$id]) ? "$id ({$image_section['names'][$id]})" : $id, $__im['accessions'])),
                                'terms'   => implode(', ', array_map($__IL, $__im['terms'])),
                                'credit'  => trim((string) $__im['credit']),
                            ];
                        ?>
                        <?= '<button type="button" class="gene-image-tile" title="' . htmlspecialchars($__label) . '"'
                            . implode('', array_map(fn($k, $v) => ' data-' . $k . '="' . htmlspecialchars($v) . '"', array_keys($__data), $__data)) . '>' . "\n" ?>
                            <?php if ($__is_movie): ?>
                            <span class="gene-image-movie" aria-hidden="true"><i class="fas fa-play"></i></span>
                            <?php else: ?>
                            <?= '<img src="' . htmlspecialchars($__img_url($__im['file'], true)) . '" alt="' . htmlspecialchars($__label) . '" loading="lazy" decoding="async" width="160" height="120">' . "\n" ?>
                            <?php endif; ?>
                            <span class="gene-image-label"><?= htmlspecialchars($__label) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="modal fade" id="geneImageModal" tabindex="-1" aria-labelledby="geneImageModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="geneImageModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('Close') ?>"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="gene-image-full mb-3"></div>
                    <dl class="row small text-start mb-0 gene-image-facts"></dl>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-sm btn-outline-secondary gene-image-open" href="#" target="_blank" rel="noopener"><?= t('Open full size') ?></a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if (!empty($compgen_section)):
    // Comparative genomics across MOOP organisms (OrthoFinder clades, lib/clade_homologs.php). Words
    // fixed in docs/CLADE_HOMOLOGS_FORMAT.md: "Orthologs in <clade>", "Paralogs in <species>" (same
    // species), "Other family members (not orthologs of this gene)" (other species) -- never plain
    // "homologs" (that is the similarity annotation type below).
    $__cl_labels = $compgen_section['labels'];
    // A gene: its id (linked), then its symbol if it has a real one, then a short description --
    // so a reader can see when a listed gene is plainly something else (a zinc finger in a PARP family).
    $__cur_aliases = [];   // the clade being drawn: [organism => [gene => [['gene_set','assembly','gene'], ...]]] (format 2)
    $__cl_also = function (string $org, string $gene) use (&$__cur_aliases): string {
        $by = [];
        foreach ($__cur_aliases[$org][$gene] ?? [] as $a) {
            $u = moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $org, 'uniquename' => $a['gene'], 'assembly' => $a['assembly'], 'gene_set' => $a['gene_set']]);
            $by[$a['gene_set']][] = '<a class="font-monospace" href="' . htmlspecialchars($u) . '">' . htmlspecialchars($a['gene']) . '</a>';
        }
        $h = '';
        foreach ($by as $gs => $links) $h .= ' <span class="compgen-also">' . t_markup('also in %1$s: %2$s', htmlspecialchars($gs), implode(', ', $links)) . '</span>';
        return $h;
    };
    $__cl_link = function (string $org, array $o, string $gene) use ($__cl_labels, $__cl_also): string {
        $url  = moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $org, 'uniquename' => $gene, 'assembly' => $o['assembly'], 'gene_set' => $o['gene_set']]);
        $l    = $__cl_labels[$org][$gene] ?? null;
        $name = $l ? trim($l['name']) : '';
        $desc = $l ? trim(preg_replace('/\s*\[[^\]]*\]\s*$/', '', $l['description'])) : '';   // drop the "[ISO|1to1|...]" evidence tag
        $is_id = !moop_clade_has_symbol($name, $gene);
        if ($desc !== '' && !moop_clade_has_symbol($desc, $gene)) $desc = '';     // a "description" that is only an id
        $short = mb_strlen($desc) > 70 ? mb_substr($desc, 0, 68) . '…' : $desc;
        return '<a class="font-monospace" href="' . htmlspecialchars($url) . '"' . ($desc !== '' ? ' title="' . htmlspecialchars($desc) . '"' : '') . '>'
             . htmlspecialchars($gene) . '</a>'
             . (!$is_id ? ' <strong>' . htmlspecialchars($name) . '</strong>' : '')
             . ($short !== '' ? ' <span class="text-muted">' . htmlspecialchars($short) . '</span>' : '')
             . $__cl_also($org, $gene);
    };
    $__kind_tip = fn(string $k) => [
        '1:1'       => t_plain('One gene on each side: the clearest ortholog.'),
        '1:many'    => t_plain('This gene has several copies in the other species (duplicated there).'),
        'many:1'    => t_plain('Several genes here share one counterpart in the other species (duplicated here).'),
        'many:many' => t_plain('Duplicated in both species.'),
    ][$k] ?? $k;
    $__cl_list = function (string $org, array $o, array $genes, int $show, string $more_label) use ($__cl_link, $__kind_tip): string {
        $h = '';
        foreach (array_slice($genes, 0, $show) as $g) $h .= '<div>' . (is_array($g) ? '<span class="badge compgen-kind" title="' . htmlspecialchars($__kind_tip($g['kind'])) . '">' . htmlspecialchars($g['kind']) . '</span> ' . $__cl_link($org, $o, $g['gene']) : $__cl_link($org, $o, $g)) . '</div>';
        if (count($genes) > $show) {
            $h .= '<details><summary>' . htmlspecialchars($more_label) . '</summary>';
            foreach (array_slice($genes, $show, 200) as $g) $h .= '<div>' . (is_array($g) ? '<span class="badge compgen-kind" title="' . htmlspecialchars($__kind_tip($g['kind'])) . '">' . htmlspecialchars($g['kind']) . '</span> ' . $__cl_link($org, $o, $g['gene']) : $__cl_link($org, $o, $g)) . '</div>';
            if (count($genes) > $show + 200) $h .= '<div class="text-muted">' . t('and %d more', count($genes) - $show - 200) . '</div>';
            $h .= '</details>';
        }
        return $h;
    };
    $__ncl = count($compgen_section['clades']);
    // Within each organism: 1:1 first, then genes with a real gene symbol, then the rest -- so a
    // named counterpart is not buried under unnamed gene models (PARP16 among 43 Scolanthus genes).
    foreach ($compgen_section['clades'] as &$__c0) foreach ($__c0['organisms'] as $__org0 => &$__o0) {
        $__named = fn(string $g) => moop_clade_has_symbol((string) ($__cl_labels[$__org0][$g]['name'] ?? ''), $g);
        usort($__o0['orthologs'], fn($a, $b) => [$b['kind'] === '1:1', $__named($b['gene']), $a['gene']] <=> [$a['kind'] === '1:1', $__named($a['gene']), $b['gene']]);
    }
    unset($__c0, $__o0);
?>
    <!-- Comparative genomics (OrthoFinder clades on this site) -->
    <div class="card shadow-sm mb-4" id="pnav-compgen" data-nav-label="Comparative genomics">
        <div class="card-header d-flex align-items-center flex-wrap">
            <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#compgenSection" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Comparative genomics') ?>" title="<?= t('Show or hide Comparative genomics') ?>">
                <i class="fas fa-code-branch toggle-icon text-primary"></i>
            </span>
            <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Comparative genomics') ?></span>
            <span class="ms-2 text-muted small"><?= ($__ncl !== 1 ? t('orthologs and gene family across %d clades on this site', $__ncl)
                                                                  : t('orthologs and gene family across %d clade on this site', $__ncl)) . "\n" ?></span>
        </div>
        <div class="collapse show" id="compgenSection">
            <div class="card-body">
            <?php foreach ($compgen_section['clades'] as $__ci => $__c):
                $__cur_aliases = $__c['aliases'] ?? [];
                $__self = null; $__others = [];
                foreach ($__c['organisms'] as $__org => $__o) { if ($__o['self']) $__self = [$__org, $__o]; else $__others[$__org] = $__o; }
            ?>
                <?= '<div class="compgen-clade' . ($__ci ? ' mt-4' : '') . '" id="pnav-compgen-' . (int) $__ci . '" data-nav-sub="' . htmlspecialchars($__c['label']) . '">' . "\n" ?>
                    <h6 class="mb-1"><?= t('Orthologs in %s', $__c['label']) ?></h6>
                    <?php if (!empty($__c['via'])): ?>
                    <p class="small mb-2 compgen-via"><?= t_markup('Orthologs are computed on gene set %2$s. This gene is the same gene as %1$s there (matched by sequence), so these are its orthologs.',
                        '<a class="font-monospace" href="' . htmlspecialchars(moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $organism_name, 'uniquename' => $__c['via']['gene'], 'gene_set' => $__c['via']['gene_set']])) . '">' . htmlspecialchars($__c['via']['gene']) . '</a>',
                        htmlspecialchars($__c['via']['gene_set'])) ?></p>
                    <?php endif; ?>
                    <?php // The same gene in this species' other gene sets, minus the one this page is on.
                    $__cur_aliases[$organism_name][$__c['self_gene']] = array_values(array_filter($__cur_aliases[$organism_name][$__c['self_gene']] ?? [], fn($a) => $a['gene_set'] !== $compgen_section['page_gene_set']));
                    if ($__cur_aliases[$organism_name][$__c['self_gene']]): ?>
                    <p class="small mb-2"><?= t('The same gene in other gene sets:') ?><?= $__cl_also($organism_name, $__c['self_gene']) ?></p>
                    <?php endif; ?>
                    <?php if ($__others): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small mb-2 compgen-table">
                            <thead><tr><th><?= t('Organism') ?></th><th><?= t('Orthologs of this gene') ?></th><th><?= t('Other family members (not orthologs of this gene)') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($__others as $__org => $__o): $__no = count($__o['orthologs']); $__nf = count($__o['family']); ?>
                                <tr>
                                    <td class="text-nowrap"><em><?= htmlspecialchars(str_replace('_', ' ', $__org)) ?></em></td>
                                    <td><?= $__no ? $__cl_list($__org, $__o, $__o['orthologs'], 10, t_plain('Show %d more orthologs', $__no - 10)) : '<span class="text-muted">' . t('none') . '</span>' ?></td>
                                    <td><?= $__nf ? '<details><summary>' . ($__nf !== 1 ? t('%d genes', $__nf) : t('%d gene', 1)) . '</summary>' . $__cl_list($__org, $__o, $__o['family'], 200, '') . '</details>' : '<span class="text-muted">&mdash;</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    <?php if ($__self && $__self[1]['family']): $__np = count($__self[1]['family']); ?>
                    <details class="small mb-2">
                        <summary><?= $__np !== 1 ? t_markup('Paralogs in %1$s: %2$d genes', '<em>' . htmlspecialchars(str_replace('_', ' ', $__self[0])) . '</em>', $__np)
                                                 : t_markup('Paralogs in %1$s: %2$d gene', '<em>' . htmlspecialchars(str_replace('_', ' ', $__self[0])) . '</em>', 1) ?></summary>
                        <?= $__cl_list($__self[0], $__self[1], $__self[1]['family'], 200, '') . "\n" ?>
                    </details>
                    <?php endif; ?>
                    <p class="small text-muted mb-0"><?= t('Gene family: every gene in this group descended from one ancestral gene, orthologs and paralogs. Computed with %1$s %2$s on one gene set per species (%3$d gene sets); family %4$s.', $__c['method'], $__c['version'], $__c['gene_sets'], $__c['orthogroup']) ?></p>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

    <!-- Annotations Section -->
    <div class="card shadow-sm mb-4" id="pnav-annotations" data-nav-label="Annotations">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <?php /* Starts COLLAPSED (user, 2026-10-06: "I really don't want to overwhelm
                         users"). The overview card above is the summary; the tables are the
                         detail. The count says what is inside before it is opened, and every
                         link into it — sidebar, gene-structure rows, the hierarchy, a #hash —
                         opens it first (parent-nav.js, parent-tools.js). */ ?>
                <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#annotationsSection" aria-expanded="false" aria-controls="annotationsSection" role="button" aria-label="<?= t('Show or hide Annotations') ?>" title="<?= t('Show or hide Annotations') ?>">
                    <i class="fas fa-plus toggle-icon text-primary"></i>
                </span>
                <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Annotations') ?></span>
                <span class="ms-2 small text-muted annotations-count"><?php
                    $__n_tx = count($children_hierarchical);
                    if (empty($annotation_total)) {
                        echo 'none';
                    } else {
                        echo number_format($annotation_total) . ' annotation' . ($annotation_total == 1 ? '' : 's');
                        if ($__n_tx > 1) echo ' across ' . $__n_tx . ' transcripts';
                    }
                ?></span>
                <?php if (!empty($isoforms_share_annotations)): ?>
                    <?php /* Stated, not acted on. The tables stay per transcript — an annotation
                             belongs to the transcript, and grouping only the genes where the sets
                             happen to match would make the page structure depend on the data.
                             This is the one thing the per-transcript layout cannot show: that a
                             reader comparing N isoforms would find them identical. */ ?>
                    <span class="badge rounded-pill ms-2 fw-normal"
                          style="background-color:#e0f2f1; color:#0f5132; font-size:0.72rem;"
                          title="<?= t('Every transcript of this gene carries the same set of annotation types and accessions. Scores and descriptions may still differ.') ?>">
                        <?php /* "identical", not "annotated the same": the point is that the
                                 N sets below are the same set, which is the exception. About
                                 a third of multi-isoform genes are like this, so the normal
                                 expectation on arriving is that they differ and must be
                                 compared. "Annotated the same" read as a neutral description
                                 of how the data was produced rather than as a finding. */ ?>
                        <i class="fa fa-equals me-1" aria-hidden="true"></i><?= t('identical on all %d transcripts', (int)$annotated_isoform_count) . "\n" ?>
                    </span>
                <?php endif; ?>
                <?= help_modal_trigger('annotations-help', '', 'Help: what is an annotation?') ?>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php /* Collapse-all: on a gene with 17 transcripts, getting from the first
                         to the last means scrolling past every annotation table in between.
                         There was no way to fold them and no keyboard shortcut either. Only
                         shown when there is more than one transcript to fold. */ ?>
                <?php /* Not on a big gene: its cards are opened one at a time, and each has its own close. */ ?>
                <?php if (count($children_hierarchical) > 1 && empty($big_gene)): ?>
                <button type="button" class="btn btn-sm moop-data-btn" id="toggle-all-transcripts"
                        data-state="expanded"
                        title="<?= t('Collapse every transcript so the list fits on one screen') ?>">
                    <i class="fas fa-compress me-1"></i><span class="label"><?= t('Collapse all') ?></span>
                </button>
                <?php endif; ?>
                <a href="/<?= htmlspecialchars($config->getString('site', 'moop')) ?>/api/download_annotations.php?organism=<?= urlencode($organism_name) ?>&uniquename=<?= urlencode($feature_uniquename) ?>"
                   class="btn btn-sm moop-data-btn" title="Download every annotation on this page as one CSV">
                    <i class="fas fa-download me-1"></i><?= t('Download all') . "\n" ?>
                </a>
                <?php /* "Back to Top" removed: the section nav on the left already returns
                         you there from anywhere, and it duplicated that in one spot only. */ ?>
            </div>
        </div>
        <div id="annotationsSection" class="collapse">
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
                        echo generateAnnotationTableHTML($annot_results, $feature_uniquename, $type, $count, $display_label, $analysis_desc[$annotation_type] ?? '', $color, $organism_name, $annotation_type, $annotation_link_ctx);
                    }
                }
                
                // Big gene (tools/parent.php, MOOP_BIG_GENE_TRANSCRIPTS): a list of the
                // transcripts, each one's card loaded on demand, instead of every card at once.
                if (!empty($big_gene)) {
                    $has_annotations = true;
                    $__n_tx = count($children_hierarchical);
                    // Each transcript's protein, for its length (protein -> cds -> mRNA).
                    $__protein_of = [];
                    foreach ($children_hierarchical as $__c) {
                        $__stack = [$__c];
                        while ($__stack) {
                            $__node = array_pop($__stack);
                            if (isset($protein_lengths[$__node['feature_uniquename']])) {
                                $__protein_of[$__c['feature_uniquename']] = $__node['feature_uniquename'];
                            }
                            foreach ($__node['grandchildren'] ?? [] as $__g) $__stack[] = $__g;
                        }
                    }
                    ?>
                    <div class="isoform-note">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?= t_markup("%s — too many to show at once, so each transcript's annotations load when you ask for them.",
                                     '<strong>' . t('%s transcripts', number_format($__n_tx)) . '</strong>') . "\n" ?>
                        <?= t('Sort the list by annotations or protein length to find the ones worth opening.') . "\n" ?>
                    </div>
                    <?php
                    // The rows as compact data (~60 bytes a transcript); the browser builds the
                    // table from it (parent-tools.js). As server-rendered rows with their buttons
                    // this was ~600 bytes a transcript, 656 KB at 1,083 transcripts.
                    // [id, annotation count, protein length or null, is the longest protein]
                    $__rows = [];
                    foreach ($children_hierarchical as $__c) {
                        $__u = $__c['feature_uniquename'];
                        $__p = $__protein_of[$__u] ?? null;
                        $__rows[] = [$__u, (int)($child_annotation_counts[$__c['feature_id']] ?? 0),
                                     $__p !== null ? (int)$protein_lengths[$__p] : null, $__p !== null && $__p === $longest_protein];
                    }
                    ?>
                    <script type="application/json" id="bigGeneRows"><?= json_encode($__rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                    <div class="table-responsive">
                    <table id="bigGeneTranscripts" class="table table-sm table-striped table-hover" style="width:100%">
                        <thead><tr><th><?= t('Transcript') ?></th><?php if (!empty($gene_model['isoforms'])): ?><th class="col-structure"><?= t('Structure') ?></th><?php endif; ?><th><?= t('Annotations') ?></th><th><?= t('Protein') ?></th><th></th></tr></thead>
                    </table>
                    </div>
                    <div id="bigGeneCards" class="mt-3"
                         data-organism="<?= htmlspecialchars($organism_name) ?>"
                         data-gene="<?= htmlspecialchars($feature_uniquename) ?>"></div>
                    <?php
                }
                // Children annotations (with hierarchical support for grandchildren)
                elseif (!empty($children_hierarchical)) {
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
                        $rendered = generateChildAnnotationCards($child, $all_annotations, $analysis_order, $annotation_colors, $annotation_labels, $analysis_desc, $organism_name, $count, false, $annotated_child_types ?? [], $annotation_link_ctx);
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
    // Each sequence's TRANSCRIPT, so Primer Maker's own isoform list shows the one picked: a
    // transcript is its own; a CDS (or protein) is under its transcript in the feature tree.
    $__by_id = array_column($children, null, 'feature_id');
    $__transcript_of = [];
    foreach ($children as $__c) {
        $__t = $__c;
        while (isset($__by_id[$__t['parent_feature_id']]) && !in_array(strtolower($__t['feature_type']), ['mrna', 'transcript'], true)) {
            $__t = $__by_id[$__t['parent_feature_id']];
        }
        $__transcript_of[$__c['feature_uniquename']] = $__t['feature_uniquename'];
    }
    $primer_design_context = [
        'organism' => $organism_name,
        'assembly' => $genome_accession,
        'gene_set' => $gene_set_name,
        'feature'  => $feature_uniquename,
        'transcript_of' => $__transcript_of,
    ];
    $assembly_name    = $genome_accession;
    $organism_data    = $config->getPath('organism_data');

    $sequences_file = __DIR__ . '/../sequences_display.php';
    if (!empty($big_gene)) {
        // Hundreds of transcripts' sequences printed inline is what made these pages tens of
        // megabytes. Offer the same FASTA downloads the full page has; the controller streams
        // them (tools/parent.php, download_file).
        ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex align-items-center">
                <span class="collapse-section" data-bs-toggle="collapse" data-bs-target="#bigGeneSequences" aria-expanded="true" role="button" aria-label="<?= t('Show or hide Sequences') ?>" title="<?= t('Show or hide Sequences') ?>">
                    <i class="fas fa-minus toggle-icon text-primary"></i>
                </span>
                <span class="ms-2 text-uppercase fw-semibold section-eyebrow"><?= t('Sequences') ?></span>
            </div>
            <div id="bigGeneSequences" class="collapse show">
            <div class="card-body">
                <p class="text-muted mb-2"><?= t('This gene has %s transcripts — too many to print here.', number_format(count($children_hierarchical))) ?> <?= t('Download them as FASTA:') ?></p>
                <?php foreach (($sequence_types ?? $config->getSequenceTypes()) as $__st => $__conf):
                    if ($__st === 'genome') continue; ?>
                    <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="sequence_type" value="<?= htmlspecialchars($__st) ?>">
                        <input type="hidden" name="download_file" value="1">
                        <button type="submit" class="btn btn-sm btn-success me-1">
                            <i class="fa fa-download"></i> <?= htmlspecialchars($__conf['label'] ?? $__st) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
            </div>
        </div>
        <?php
    } elseif (file_exists($sequences_file)) {
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

// The Gene Structure help exists only when the Gene Structure card does. That card (and the
// legend it defines, which this help reuses) is drawn only when the gene set has a GFF to
// build a gene model from. Without this guard the help read an undefined $gene_model_legend
// and the whole gene page died with a fatal error for every gene in a gene set with no GFF
// (15 organisms on 2026-10-01, mostly transcriptome-only planarians). The rule for this
// page: no GFF means the GFF-dependent parts are left out, never that the page fails.
if (!empty($gene_model)) {
    // "How to read it" varies with whether genome sequence is actually present — otherwise the
    // help promises a click that does nothing.
    $gm_read_cards = [
        ['label' => 'Always 5′ → 3′',
         'text'  => 'Drawn left to right in the direction of transcription. A reverse-strand gene is flipped, so the diagram reads the same way on either strand.'],
        ['label' => 'One row per isoform',
         'text'  => 'The gene spans the top; each mRNA below is one alternative transcript. Rows are aligned, so exon differences line up vertically.'],
        ['label' => 'Click a row',
         'text'  => 'Jumps to that isoform\'s annotations further down the page.'],
    ];
    if (!empty($neighbor_nav)) {
        $gm_read_cards[] = ['label' => '« and » either side: the neighbouring genes',
            'text' => '« goes to the gene page of the nearest gene upstream (the 5′ side, the left of the picture), » to the nearest one downstream, on the same sequence (for a gene with no strand: lower and higher positions). Hover to see which. ' . (moop_tool_on('genome_browser') ? 'Genes with no page of their own (non-coding RNAs, which are in the genome browser only) are skipped.' : 'Genes with no page of their own (non-coding RNAs) are skipped.')];
    }
    if (!empty($genome_seq_available) && !empty($genome_is_transcripts)) {
        $gm_read_cards[] = ['label' => 'Click a feature',
            'text' => 'Exons and CDS blocks open their sequence. This gene set\'s sequences are assembled transcripts, not a genome, so there are no introns and no flanking sequence around them.'];
    } elseif (!empty($genome_seq_available)) {
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
                                 . t($l['text']),
                    ];
                },
                $gene_model_legend
            )],
            ['heading' => 'How to read it', 'cards' => $gm_read_cards],
            ['heading' => 'Getting sequence out', 'cards' => array_merge([
                ['label' => 'This gene',
                 'text'  => 'Use FASTA or GFF in the header of this box, or the Sequences section at the foot of the page.'],
            ], !moop_tool_on('moopmart') ? [] : [   // no Data Exporter on this site: no card pointing at it
                ['label' => 'Many genes at once', 'html' => true,
                 'text'  => t_markup('For mRNA, CDS, protein, genomic or flanking sequence across many features, use %s.',
                                     '<a href="/' . htmlspecialchars($config->getString('site', 'moop')) . '/tools/moopmart.php?organism='
                                     . urlencode($organism_name) . '">' . t('the Data Exporter') . '</a>')],
            ])],
        ],
        ['intro' => 'Every isoform of this gene, drawn to scale against the genome.']
    );
}

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
            ['label' => 'Color = feature type',
             'text'  => 'The badge after each ID uses the same color for a type everywhere on the site.'],
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
if (!empty($naming_statements)) {
    $page_sections[] = ['label' => 'How this gene was named',
        'text' => 'Under the title: what the gene is, then the evidence point by point. Cautions flags what to check before relying on the name.'];
}
if (!empty($gene_model)) {
    $page_sections[] = ['label' => 'Gene Structure',
        'text' => 'Every transcript drawn to scale against the genome. Click a feature for its sequence, or a row to jump to its annotations.'];
}
$page_sections[] = ['label' => 'Feature Hierarchy',
    'text' => 'What belongs to what: the ' . htmlspecialchars(moop_type_word($type)) . ', its transcripts, and their coding sequences and proteins.'];
if (!empty($children_hierarchical) || !empty($all_annotations[$feature_id])) {
    $page_sections[] = ['label' => 'Annotations',
        'text' => 'What analyses found for this sequence, grouped by transcript and then by type. Starts folded — click its heading to open.'];
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
         'text'  => t_markup('Annotations belong to an %1$s, not to the %2$s, which is why they are grouped under each isoform.', gloss('mRNA'), gloss('gene'))],
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
     'text'  => t_markup('Each table has its own %1$s, %2$s and %3$s buttons for just that type.', '<strong>' . t('Copy') . '</strong>', '<strong>CSV</strong>', '<strong>Excel</strong>')],
    ['label' => 'Search within a type',
     'text'  => 'The box beside those buttons filters that one table as you type.'],
]];

echo help_modal('annotations-help', 'Annotations on this page', $annot_sections,
    ['intro' => 'What these results are, and where each one came from.']);
