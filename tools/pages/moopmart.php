<?php
/**
 * MOOPmart — Data Exporter
 * Variables: $scope (lib/scope_selector.php),
 *            $annotation_source_names, $annotation_source_types
 */

?>
<div class="container py-3">

  <!-- Header -->
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('MOOPmart — Data Exporter') ?>
      <?= help_modal_trigger('mm-help', '', 'What MOOPmart does') ?>
        <?= page_purpose('Export annotation data or sequences.', '', false, false) ?>
    </div>
    <?php /* Page explainer (work list H, 2026-11-24), in place of the Tools-menu blurb, which said
             "table" only: what you can add, named, and that sequences come out too. The steps are
             in the (i) beside the title (mm-help), so they are not repeated here. Always shown --
             not tied to the menu_blurbs setting -- and written as a literal so the i18n scan sees it. */ ?>
    <div class="tool-page-blurb"><?= t('Build a table of genes and add annotations such as GO terms, orthologs, KEGG pathways or protein domains — or download their sequences.') ?></div>
  </div>

  <?php if (($prefill_annotation['acc'] ?? '') !== ''): ?>
  <?php /* A link filled this page in (KEGG "Genes", a GO term page): say so, and what to do next -- the
           Preview button is far down the page, below steps 1-3 (Sofia, 2026-11-25). In the page, not a
           browser pop-up, which browsers block. */ ?>
  <div class="alert alert-dismissible d-flex align-items-start gap-2 mb-4 mm-prefill-note" role="status" id="mm-prefill-note">
    <i class="fa fa-info-circle mt-1" aria-hidden="true"></i>
    <div>
      <?= t_markup('This page was filled in from the link you followed: genes with %s, with their annotation columns ticked.',
                   '<strong>' . htmlspecialchars($prefill_annotation['acc']) . '</strong>'
                   . (($prefill_annotation['src'] ?? '') !== '' ? ' (' . htmlspecialchars(moop_annotation_source_label($prefill_annotation['src'])) . ')' : '')) ?>
      <?= t('Change anything you like below, then press Preview to check the genes, and Download to get the file.') ?>
      <div class="mt-2"><button type="button" class="btn btn-sm mm-prefill-btn" id="mm-prefill-go"><i class="fa fa-eye me-1" aria-hidden="true"></i><?= t('Go to Preview') ?></button></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('Close') ?>"></button>
  </div>
  <?php endif; ?>

  <?php
  // MOOPmart help modals, converted from two hand-built modals of <ul>/<h6> prose to the
  // shared card modal (help_modal). They gain the consistent card treatment used across the
  // site and, being help_modal()s, need no page init — see the removed popover block in
  // js/modules/moopmart.js.

  // Opened by the (i) on the page header. The "what is this tool" overview: what you can
  // build a list from, what you can add to it, and how you take it away. The detailed
  // mechanics of the five build methods live in the Step 2 modal below, not here — this
  // stays a summary so the two do not restate each other.
  echo help_modal(
      'mm-help',
      'What MOOPmart does',
      [[
          'heading' => '',
          // One card per numbered step on the page, carrying the SAME .step-badge number, so
          // the overview reads as a map of the four boxes below it. The per-step detail lives
          // at each step's own (i) — this stays a walkthrough, not a repeat of it.
          'cards'   => [
              ['num' => '1', 'label' => 'Select organisms',
               'text' => 'Pick one or more assemblies to pull features from. Filter the list to find them fast.'],
              ['num' => '2', 'label' => 'Select genes',
               'text' => 'Narrow to the features you want — by ID, name, description, annotation, or location. '
                       . 'Every section is optional and they stack with AND.'],
              ['num' => '3', 'label' => 'Output options',
               'text' => 'Choose the format — a TSV spreadsheet or FASTA sequences — and which columns to include.'],
              ['num' => '4', 'label' => 'Preview & download',
               'text' => 'Check the first 100 matches, then download every matching feature.'],
          ],
      ]],
      ['intro' => 'A four-step way to build a custom list of features across your assemblies and download it. '
                . 'If you only need to look features up rather than export them, Annotation Search is simpler.']
  );

  // Opened by the (i) on the Step 2 header. The five build methods in detail — one card per
  // method, matching the five accordion sections directly below it on the page.
  echo help_modal(
      'mm-build-help',
      'How to build your list',
      [[
          'heading' => '',
          'cards'   => [
              [
                  'label' => 'By Feature IDs',
                  'text'  => 'Paste IDs one per line or comma-separated. Each resolves up to its parent gene: '
                           . 'a gene ID like <code>AT1G12345</code> directly, an mRNA <code>AT1G12345.1</code> or '
                           . 'protein <code>XP_023382306.1</code> by walking up.',
                  'html'  => true,
              ],
              [
                  'label' => 'By Feature Name',
                  'text'  => 'Partial, case-insensitive match on the name field. <code>HDAC</code> finds '
                           . '<em>HDAC1</em>, <em>HDAC2</em>, <em>pHDAC3</em>.',
                  'html'  => true,
              ],
              [
                  'label' => 'By Feature Description',
                  'text'  => 'Partial match on the description. <code>kinase</code> finds anything described as, '
                           . 'say, a <em>serine/threonine-protein kinase</em>.',
                  'html'  => true,
              ],
              [
                  'label' => 'By Annotation',
                  'text'  => 'Filter by attached annotations — GO, InterPro, BLAST hits. Each row is one criterion '
                           . 'and all rows must match: an accession like <code>GO:0006351</code>, or a keyword like '
                           . '<code>transcription factor</code>.',
                  'html'  => true,
              ],
              [
                  'label' => 'By Chromosomal Location',
                  'text'  => 'Every feature overlapping a range. Available only when exactly one assembly is '
                           . 'selected in Step 1; enter a chromosome or scaffold and optional start/end.',
              ],
              [
                  'label'  => 'A worked example',
                  'accent' => true,
                  'text'   => 'Fill <strong>By Chromosomal Location</strong> with <code>Chr1 : 1–20000</code> '
                            . '<em>and</em> <strong>By Annotation</strong> with <em>histone deacetylase</em>, and your '
                            . 'list is every feature on Chr1 in that range that <em>also</em> has a matching '
                            . 'annotation — not one or the other, both.',
                  'html'   => true,
              ],
          ],
      ]],
      ['intro' => 'Each section is a different way to add features to the list you want to work with. '
                . 'The criteria are additive (AND) — a feature has to match every section you fill in.']
  );

  // Opened by the (i) on the Step 3 header. Absorbs the old "Design your output" popover so the
  // output-format detail has ONE home. The inline (i) beside the Wide/Long toggle stays as a
  // short field_help for the one control; this modal is the fuller reference for all formats.
  echo help_modal(
      'mm-output-help',
      'Output formats',
      [[
          'heading' => '',
          'cards'   => [
              [
                  'label' => 'TSV',
                  'text'  => 'A plain-text spreadsheet, columns separated by tabs. In Excel, use '
                           . '<em>File → Open</em> (or <em>Data → From Text/CSV</em> if it lands in one column).',
                  'html'  => true,
              ],
              [
                  'label' => 'Wide vs Long',
                  'text'  => 'Wide puts all of a feature\'s annotation values on one row, joined with "; ". '
                           . 'Long gives one row per annotation — easier to filter in Excel.',
              ],
              [
                  'label' => 'FASTA',
                  'text'  => 'Standard sequence format. Each entry is a <code>&gt;header</code> line then the '
                           . 'sequence — ready for BLAST, MUSCLE, Galaxy, or any text editor.',
                  'html'  => true,
              ],
              [
                  'label' => 'Choosing columns',
                  'text'  => 'Pick which columns to include and their order, then Preview or Download in Step 4.',
              ],
          ],
      ]]
  );
  ?>


  <!-- ① Select Organisms -->
  <?php
  $scope_opts = [
      'step'       => '1',
      'title'      => t('Select organisms'),
      'help_intro' => 'Choose which organisms — or specific gene sets — to pull features from.',
      'help_need'  => 'You need at least one selection before the steps below will do anything.',
  ];
  include __DIR__ . '/../../includes/scope_selector.php';
  ?>

  <!-- ② Build Your List -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">2</span>
      <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Select Genes') ?></span>
      <?= help_modal_trigger('mm-build-help', '', 'How to build your list') ?>
      <small class="ms-auto" style="color:rgba(255,255,255,0.75); font-size:0.78rem;"><?= t('all sections optional') ?></small>
    </div>
    <div class="card-body pt-2 pb-3">
      <p class="text-muted small mb-3">Fill any section to narrow your list — sections combine with AND, so a <?= gloss('feature') ?> must satisfy every filled section.</p>

      <!-- Accordion sections -->
      <div class="d-flex flex-column gap-2">

        <!-- By Feature IDs -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-ids-header" role="button"
               aria-expanded="false" aria-controls="mm-ids-body"
               data-bs-toggle="collapse" data-bs-target="#mm-ids-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold section-eyebrow"><?= t('By Feature IDs') ?></span>
            </span>
          </div>
          <div class="collapse" id="mm-ids-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">
                Paste <?= gloss('gene') ?>, <?= gloss('mRNA') ?> or <?= gloss('protein') ?> IDs.
                <?= field_help(
                    'One per line or comma/space separated. Each ID is resolved to its gene: a protein ID '
                    . 'walks up to the parent mRNA, then the parent gene. An "Inclusion Criteria" column in '
                    . 'your output shows exactly which input ID each result came from.',
                    'Feature IDs'
                ) ?>
              </p>
              <textarea id="mm-feature-ids" class="form-control moop-input" rows="4"
                        placeholder="e.g. gene1, mRNA1.1, XP_023382306.1&#10;or one per line"></textarea>
            </div>
          </div>
        </div>

        <!-- By Feature Name -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-name-header" role="button"
               aria-expanded="false" aria-controls="mm-name-body"
               data-bs-toggle="collapse" data-bs-target="#mm-name-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold section-eyebrow"><?= t('By Feature Name') ?></span>
            </span>
          </div>
          <div class="collapse" id="mm-name-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Searches the <?= gloss('feature') ?> name field. Matches words from their start, like Search.
                <?= help_modal_trigger('exporter-text-help', '', 'How these text filters match') ?>
              </p>
              <input type="text" id="mm-gene-name" class="form-control moop-input"
                     placeholder="<?= t('e.g. BRCA1') ?>">
            </div>
          </div>
        </div>

        <!-- By Feature Description -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-desc-header" role="button"
               aria-expanded="false" aria-controls="mm-desc-body"
               data-bs-toggle="collapse" data-bs-target="#mm-desc-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold section-eyebrow"><?= t('By Feature Description') ?></span>
            </span>
          </div>
          <div class="collapse" id="mm-desc-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Searches the <?= gloss('feature') ?> description field. Matches words from their start, like Search.
                <?= help_modal_trigger('exporter-text-help', '', 'How these text filters match') ?>
              </p>
              <input type="text" id="mm-gene-description" class="form-control moop-input"
                     placeholder="<?= t('e.g. kinase') ?>">
            </div>
          </div>
        </div>

        <!-- By Annotation -->
        <?php
        // Build dropdown HTML for reuse by JS when adding new criteria rows
        // data-type on each optgroup preserves the pristine type name: the JS availability
        // counter rewrites the optgroup label to "Type (N)", so it needs the original to
        // build from rather than re-parsing its own output.
        // Type first, then source (work list 2026-11-18: one dropdown of every source ran to 185
        // entries). The type list is short; the source list holds only the chosen type's sources --
        // js/modules/moopmart.js rebuilds it from this markup, keeping only those found in the
        // organisms picked. Values stay the database names; only the visible text is relabelled.
        $_pf = $prefill_annotation ?? ['acc' => '', 'src' => '', 'sub' => false];
        $_pf_type = '';
        foreach ($annotation_source_types as $_type => $_td) {
            if ($_pf['src'] !== '' && in_array($_pf['src'], $_td['sources'], true)) { $_pf_type = $_type; break; }
        }
        $ann_type_select = '<select class="form-select form-select-sm moop-input mm-ann-type-select" aria-label="'
                         . htmlspecialchars(t_plain('Annotation type')) . '">'
                         . '<option value="">' . htmlspecialchars(t_plain('Any annotation type')) . '</option>';
        foreach ($annotation_source_types as $_type => $_td):
            $_lbl = moop_annotation_type_label($_type);
            $ann_type_select .= '<option value="' . htmlspecialchars($_type, ENT_QUOTES) . '" data-label="' . htmlspecialchars($_lbl, ENT_QUOTES) . '"'
                              . ($_type === $_pf_type ? ' selected' : '') . '>' . htmlspecialchars($_lbl) . '</option>';
        endforeach;
        $ann_type_select .= '</select>';
        // data-type on each optgroup is the type's database name, which the type select matches.
        $ann_dropdown = '<select class="form-select form-select-sm moop-input mm-ann-src-select mt-1" aria-label="'
                      . htmlspecialchars(t_plain('Source')) . '"' . ($_pf_type === '' ? ' hidden' : '') . '>'
                      . '<option value="">' . htmlspecialchars(t_plain('Any source')) . '</option>';
        foreach ($annotation_source_types as $_type => $_td):
            $ann_dropdown .= '<optgroup label="' . htmlspecialchars(moop_annotation_type_label($_type)) . '" data-type="' . htmlspecialchars($_type, ENT_QUOTES) . '">';
            foreach ($_td['sources'] as $_src):
                $_slbl = moop_annotation_source_label($_src);
                $ann_dropdown .= '<option value="' . htmlspecialchars($_src) . '" data-label="' . htmlspecialchars($_slbl, ENT_QUOTES) . '"'
                               . ($_src === $_pf['src'] ? ' selected' : '') . '>' . htmlspecialchars($_slbl) . '</option>';
            endforeach;
            $ann_dropdown .= '</optgroup>';
        endforeach;
        $ann_dropdown .= '</select>';
        $_pf_open = $_pf['acc'] !== '' || $_pf['src'] !== '';
        ?>
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-ann-filter-header" role="button"
               aria-expanded="<?= $_pf_open ? 'true' : 'false' ?>" aria-controls="mm-ann-filter-body"
               data-bs-toggle="collapse" data-bs-target="#mm-ann-filter-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold section-eyebrow"><?= t('By Annotation') ?></span>
            </span>
          </div>
          <div class="collapse<?= $_pf_open ? ' show' : '' ?>" id="mm-ann-filter-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-3">
                Every <?= gloss('feature') ?> must satisfy <strong>all</strong> criteria (AND).
                Each row filters by <?= gloss('annotation type') ?>, exact <?= gloss('accession') ?>, or keyword — fill any combination.
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted"
                        style="font-size:0.85rem; line-height:1; vertical-align:middle;"
                        data-bs-toggle="modal" data-bs-target="#ann-types-modal" title="<?= t('About annotation types') ?>">
                  <i class="fa fa-info-circle"></i>
                </button>
              </p>

              <!-- Column headers -->
              <div class="row g-2 mb-1 text-muted" style="font-size:0.75rem;">
                <div class="col-sm-4"><?= gloss('annotation type', 'Annotation type') ?></div>
                <div class="col-sm-4"><?= t('Accession') ?> <span class="text-muted"><?= t('(exact)') ?></span></div>
                <div class="col-sm-4">Keyword
                  <?= help_modal_trigger('exporter-text-help', '', 'How these text filters match') ?>
                </div>
              </div>

              <!-- Criteria rows -->
              <div id="mm-ann-criteria">
                <div class="mm-ann-criterion row g-2 mb-2 align-items-center">
                  <div class="col-sm-4"><?= $ann_type_select ?><?= $ann_dropdown ?></div>
                  <div class="col-sm-4"><input type="text" class="form-control form-control-sm moop-input mm-ann-accession" placeholder="<?= t('e.g. GO:0006351') ?>" value="<?= htmlspecialchars($_pf['acc']) ?>">
                    <label class="form-check small mt-1 mb-0" title="<?= t('A GO term also finds the genes annotated to the terms below it (is a, part of) -- the genes Anatomy & Gene Function counts.') ?>"><input type="checkbox" class="form-check-input mm-ann-sub"<?= !empty($_pf['sub']) ? ' checked' : '' ?>> <span class="form-check-label"><?= t('and more specific GO terms') ?></span></label></div>
                  <div class="col-sm-3"><input type="text" class="form-control form-control-sm moop-input mm-ann-keyword" placeholder="<?= t('e.g. transporter') ?>"></div>
                  <div class="col-sm-1"></div>
                </div>
              </div>

              <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="mm-add-criterion">
                <i class="fa fa-plus me-1"></i> <?= t('Add criterion') . "\n" ?>
              </button>
            </div>
          </div>
        </div>

        <!-- By Chromosomal Location -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-loc-header" role="button"
               aria-expanded="false" aria-controls="mm-loc-body"
               data-bs-toggle="collapse" data-bs-target="#mm-loc-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold section-eyebrow"><?= t('By Chromosomal Location') ?></span>
            </span>
          </div>
          <div class="collapse" id="mm-loc-body">
            <div class="browse-select-panel">
              <?php /* Note text (state) and (i) (the "why one assembly?") share one line, so the
                       (i) sits with the note instead of floating. updateCoordState() swaps the
                       note text but leaves the sibling (i) in place. */ ?>
              <div class="d-flex align-items-baseline gap-1 mb-2">
                <span class="small text-muted fst-italic" id="mm-coord-note">Select exactly one <?= gloss('assembly') ?> in Step <span class="step-ref">1</span> <?= t('to enable location search.') ?></span>
                <?= field_help(
                    'Location search works on one assembly at a time. The chromosome and scaffold names '
                    . 'are read from that assembly\'s own reference sequences — they differ between '
                    . 'assemblies — so the field auto-fills only when exactly one is selected.',
                    'Why one assembly?'
                ) ?>
              </div>
              <div class="row g-2">
                <div class="col-sm-4">
                  <label class="form-label small mb-1">Chr / <?= gloss('scaffold') ?></label>
                  <input type="text" id="mm-coord-chr" class="form-control form-control-sm moop-input"
                         placeholder="<?= t('e.g. CHR01') ?>" list="mm-chr-datalist" autocomplete="off" disabled>
                  <datalist id="mm-chr-datalist"></datalist>
                </div>
                <div class="col-sm-4">
                  <label class="form-label small mb-1"><?= t('Start') ?> <span class="text-muted"><?= t('(1-based)') ?></span></label>
                  <input type="number" id="mm-coord-start" class="form-control form-control-sm" placeholder="1" min="1" disabled>
                </div>
                <div class="col-sm-4">
                  <label class="form-label small mb-1"><?= t('End') ?> <span class="text-muted"><?= t('(1-based)') ?></span></label>
                  <input type="number" id="mm-coord-end" class="form-control form-control-sm" placeholder="1000000" min="1" disabled>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /accordion sections -->
    </div>
  </div>

  <!-- ③ Design Your Output -->
  <div class="card mb-3 shadow-sm">
    <?php /* Only the title region is the collapse toggle, not the whole header — so the help
             (i) beside it is a SIBLING of the toggle, not a child, and can be an ordinary
             help_modal_trigger() with no event-propagation fight. A modal opens by document
             delegation, so a stopPropagation hack on a nested trigger would also swallow the
             modal; keeping the two controls separate is cleaner than reconciling them. */ ?>
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <div class="d-flex align-items-center gap-2 cursor-pointer"
           data-bs-toggle="collapse" data-bs-target="#mm-design-body" aria-expanded="false" aria-controls="mm-design-body">
        <span class="step-badge me-2">3</span>
        <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Select Output Options') ?></span>
      </div>
      <?= help_modal_trigger('mm-output-help', '', 'Output formats') ?>
      <i class="fa fa-chevron-down ms-auto cursor-pointer" style="font-size:0.75rem; opacity:0.8; transition:transform 0.2s;" id="mm-design-chevron"
         data-bs-toggle="collapse" data-bs-target="#mm-design-body" aria-controls="mm-design-body"></i>
    </div>
    <div class="collapse" id="mm-design-body">
    <div class="card-body pt-3">

      <!-- Format: segmented pill (TSV | FASTA) -->
      <div class="mb-4">
        <div class="small fw-semibold text-muted mb-1"><?= t('Output format') ?></div>
        <div class="btn-group btn-group-sm mm-seg" role="group" aria-label="<?= t('Output format') ?>">
          <input type="radio" class="btn-check" name="mm-format" id="mm-format-tsv" value="tsv" autocomplete="off" checked>
          <label class="btn" for="mm-format-tsv"><i class="fa fa-file-alt me-1"></i><?= t('TSV') ?></label>
          <input type="radio" class="btn-check" name="mm-format" id="mm-format-fasta" value="fasta" autocomplete="off">
          <label class="btn" for="mm-format-fasta"><i class="fa fa-dna me-1"></i><?= t('FASTA') ?></label>
        </div>
      </div>

      <!-- TSV options -->
      <div id="mm-tsv-options">

        <!-- Table layout: segmented pill (Long | Wide) -->
        <div class="mb-4">
          <div class="small fw-semibold text-muted mb-1">Table layout
            <?= field_help(
                'Long (default): one row per annotation, gene and mRNA IDs repeating — easiest to filter in Excel. '
                . 'Wide: one row per mRNA, a source\'s values joined with "; ".',
                'Table layout'
            ) ?>
          </div>
          <div class="btn-group btn-group-sm mm-seg" role="group" aria-label="<?= t('Table layout') ?>">
            <input type="radio" class="btn-check" name="mm-layout" id="mm-layout-long" value="long" autocomplete="off" checked>
            <label class="btn" for="mm-layout-long"><?= t('Long') ?></label>
            <input type="radio" class="btn-check" name="mm-layout" id="mm-layout-wide" value="wide" autocomplete="off">
            <label class="btn" for="mm-layout-wide"><?= t('Wide') ?></label>
          </div>
        </div>

        <!-- Feature columns -->
        <div class="mb-3">
          <div class="small fw-semibold text-muted mb-2">Feature columns to include in TSV
            <?= help_modal_trigger('mm-cols-help', '', 'About the TSV columns') ?>
          </div>
          <div id="mm-feat-col-list" style="max-width:320px;">
            <?php
            $feat_cols = [
              'organism'         => 'Organism',
              'assembly'         => 'Assembly',
              'gene_set'         => 'Gene Set',
              'gene_id'          => 'Gene ID',
              'gene_name'        => 'Gene Name',
              'gene_description' => 'Gene Description',
              'mrna_id'          => 'mRNA ID',
              'protein_id'       => 'Protein ID',
              'chr'              => 'Chr',
              'start'            => 'Start',
              'stop'             => 'Stop',
              'strand'           => 'Strand',
              'why_included'     => 'Inclusion Criteria',
            ];
            foreach ($feat_cols as $val => $lbl):
            ?>
            <div class="mm-col-item d-flex align-items-center gap-2 px-2 py-1 mb-1 rounded border"
                 data-col="<?= $val ?>" style="cursor:pointer; user-select:none;">
              <span class="mm-col-num badge mm-col-num-badge"></span>
              <span class="mm-col-label small"><?= $lbl ?></span>
              <div class="ms-auto d-flex" style="gap:2px;">
                <button type="button" class="mm-col-up border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="<?= t('Move up') ?>">&#9650;</button>
                <button type="button" class="mm-col-down border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="<?= t('Move down') ?>">&#9660;</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">Click to include/exclude &middot; arrows to reorder</div>
        </div>

        <?php
        // Help for the TSV columns. Most are self-explanatory, so this leads with the one that
        // is not — Inclusion Criteria — as an accent card, and covers the rest in a sentence.
        echo help_modal(
            'mm-cols-help',
            'About the TSV columns',
            [
                [
                    'heading' => 'Feature columns',
                    'cards'   => [
                        [
                            'label'  => 'Inclusion Criteria',
                            'accent' => true,
                            'text'   => 'Why each feature is in your list — which Step <span class="step-ref">2</span> criterion pulled it in. '
                                      . 'A feature matched By Feature IDs shows the ID you entered; one from By Annotation '
                                      . 'shows the matching annotation; from By Location, the overlapping range. It is the '
                                      . 'column for checking your list did what you meant — turn it on when a result surprises you.',
                            'html'   => true,
                        ],
                        [
                            'label' => 'Gene Set',
                            'text'  => 'The named set of gene models the feature belongs to. One assembly can carry more '
                                     . 'than one gene set, so this says which it came from.',
                        ],
                        [
                            'label' => 'The rest',
                            'text'  => 'Organism, Assembly, the Gene / mRNA / Protein IDs, Gene Name and Description, and '
                                     . 'the coordinates (Chr, Start, Stop, Strand) are the feature\'s basic facts. Include '
                                     . 'the ones you need and drag to reorder.',
                        ],
                    ],
                ],
                [
                    'heading' => 'Annotation columns',
                    'note'    => 'These appear in the export only if you select annotation types below.',
                    'cards'   => [
                        [
                            'label' => 'Annotation Type',
                            'text'  => 'The broad category — e.g. Gene Ontology, Domains, Homologs.',
                        ],
                        [
                            'label' => 'Annotation Source',
                            'text'  => 'The specific database within a type — e.g. Pfam or InterPro under Domains.',
                        ],
                        [
                            'label' => 'Annotation ID',
                            'text'  => 'The accession — e.g. GO:0006351 or IPR000719.',
                        ],
                        [
                            'label' => 'Annotation Description',
                            'text'  => 'The human-readable text of the annotation.',
                        ],
                        ...($anatomy_organisms ? [[
                            'label' => 'Anatomical Expression',
                            'text'  => 'Where a gene is reported to be expressed, as anatomy terms curated from published papers (ID = the term, Description = its name). Only for organisms that have these curations; Expression by Anatomy shows each record with its paper and evidence.',
                        ]] : []),
                    ],
                ],
            ],
            ['intro' => 'Pick which columns the TSV has, and their order.']
        );
        ?>

        <!-- Annotation types panel. Type first, like the By Annotation filter in Step 2: 11 type rows,
             each closed; ticking a type takes all its sources, opening it picks single ones. Until
             2026-11-24 all ~185 sources were listed open at once. Sources the organisms picked in
             Step 1 lack are hidden by js/modules/moopmart.js and never sent (in the wide layout each
             sent source becomes two columns, empty or not). -->
        <?php if (!empty($annotation_source_types)): ?>
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-1">
            <div class="small fw-semibold text-muted"><?= t('Annotation types to include') ?>
              <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted"
                      style="font-size:0.85rem; line-height:1; vertical-align:middle;"
                      data-bs-toggle="modal" data-bs-target="#ann-types-modal" title="<?= t('About annotation types') ?>">
                <i class="fa fa-info-circle"></i>
              </button>
            </div>
            <span class="small text-muted" id="mm-ann-selected"></span>
            <div class="d-flex gap-1 ms-auto">
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="mm-ann-all"><?= t('All') ?></button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="mm-ann-none"><?= t('None') ?></button>
            </div>
          </div>
          <p class="small text-muted mb-2"><?= t('Tick a type to include all of its sources, or open it to pick single sources. Only the types your organisms have are listed.') ?></p>
          <div class="mb-1">
            <input type="search" class="form-control form-control-sm moop-input" id="mm-ann-filter"
                   placeholder="<?= t('Find a type or source, e.g. Pfam or Homo sapiens') ?>" autocomplete="off">
          </div>
          <div id="mm-ann-panel" style="overflow-y:auto; max-height:360px;" class="border rounded p-1">
            <?php foreach ($annotation_source_types as $type => $type_data):
              $type_safe = 'mm-atype-' . preg_replace('/[^a-z0-9]/i', '_', $type);
              $color     = htmlspecialchars($type_data['color']);
              $_on       = in_array($type, $prefill_ann_types ?? [], true) ? ' checked' : '';
            ?>
            <div class="mm-ann-group" data-type="<?= htmlspecialchars($type) ?>">
              <div class="d-flex align-items-center gap-2 px-2 py-1 rounded mm-ann-type-row">
                <input type="checkbox" class="form-check-input mb-0 mm-ann-type-cb flex-shrink-0"
                       id="<?= $type_safe ?>" data-type="<?= htmlspecialchars($type) ?>"<?= $_on ?>>
                <label for="<?= $type_safe ?>" class="form-check-label mb-0" style="cursor:pointer;">
                  <span class="badge bg-<?= $color ?>"><?= htmlspecialchars(moop_annotation_type_label($type)) ?></span>
                  <span class="small text-muted mm-ann-type-genes"></span>
                </label>
                <button type="button" class="btn btn-link btn-sm p-0 ms-auto text-muted text-decoration-none small mm-ann-open-btn"
                        aria-expanded="false">
                  <span class="mm-ann-nsrc"></span> <i class="fa fa-chevron-down" aria-hidden="true"></i>
                </button>
              </div>
              <div class="ps-4 pb-1 mm-ann-sources d-none">
                <?php foreach ($type_data['sources'] as $src_name):
                  $safe_id = 'mm-ann-' . preg_replace('/[^a-z0-9]/i', '_', $src_name);
                ?>
                <div class="d-flex align-items-center gap-1 px-1 py-0 mm-ann-item">
                  <input type="checkbox" class="form-check-input flex-shrink-0 mm-ann-col mb-0"
                         id="<?= $safe_id ?>"
                         value="<?= htmlspecialchars($src_name) ?>"
                         data-type="<?= htmlspecialchars($type) ?>"<?= ($_on || in_array($src_name, $prefill_ann_sources ?? [], true)) ? ' checked' : '' ?><?= ($src_name === MOOPMART_ANATOMY_SOURCE && $type === MOOPMART_ANATOMY_TYPE)
                             ? ' data-orgs="' . htmlspecialchars(implode(',', $anatomy_organisms ?? [])) . '"' : '' ?>>
                  <label class="form-check-label mb-0" for="<?= $safe_id ?>"
                         style="cursor:pointer; font-size:0.82rem;">
                    <?= htmlspecialchars(moop_annotation_source_label($src_name)) ?>
                  </label>
                  <span class="badge bg-secondary ms-1 mm-ann-count d-none" style="font-size:0.68rem;"
                        data-src="<?= htmlspecialchars($src_name) ?>"
                        title="<?= t('Genes in the selected organisms that carry this source') ?>"></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-1" id="mm-ann-counts"></div>
        </div>
        <?php endif; ?>

        <!-- Annotation columns -->
        <div class="mb-3">
          <div class="small fw-semibold text-muted mb-2"><?= t('Annotation columns (used when an annotation type above is ticked)') ?>
            <?= help_modal_trigger('mm-cols-help', '', 'About the TSV columns') ?>
          </div>
          <div id="mm-ann-col-list" style="max-width:320px;">
            <?php
            $ann_cols = [
              'ann_type'        => 'Annotation Type',
              'ann_source'      => 'Annotation Source',
              'ann_id'          => 'Annotation ID',
              'ann_description' => 'Annotation Description',
            ];
            foreach ($ann_cols as $val => $lbl):
            ?>
            <div class="mm-col-item d-flex align-items-center gap-2 px-2 py-1 mb-1 rounded border"
                 data-col="<?= $val ?>" style="cursor:pointer; user-select:none;">
              <span class="mm-col-num badge mm-col-num-badge"></span>
              <span class="mm-col-label small"><?= $lbl ?></span>
              <div class="ms-auto d-flex" style="gap:2px;">
                <button type="button" class="mm-col-up border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="<?= t('Move up') ?>">&#9650;</button>
                <button type="button" class="mm-col-down border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="<?= t('Move down') ?>">&#9660;</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">Click to include/exclude &middot; arrows to reorder</div>
        </div>

      </div><!-- /mm-tsv-options -->

      <!-- FASTA options -->
      <div id="mm-fasta-options" class="d-none">
        <div class="small fw-semibold text-muted mb-2">Sequence type
          <?= field_help(
              'Pick one form of each feature\'s sequence. Genomic includes introns; Exons gives each '
              . 'exon, CDS and UTR piece of the first isoform as its own sequence, numbered 5\' to 3\' '
              . '(gene.exon1, gene.CDS1, ...); mRNA, CDS and '
              . 'protein are the usual forms. For Upstream or Downstream, set Flank size below to '
              . 'choose how many bases of genomic sequence to pull before or after the gene.',
              'Sequence type'
          ) ?>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php
          $fasta_modes = [
            'gene'       => 'Genomic',
            'exons'      => 'Exons',
            'transcript' => 'mRNA',
            'cds'        => 'CDS',
            'protein'    => 'Protein',
            'upstream'   => 'Upstream',
            'downstream' => 'Downstream',
          ];
          foreach ($fasta_modes as $mode => $lbl):
          ?>
          <div class="form-check form-check-inline mb-0">
            <input class="form-check-input mm-fasta-mode" type="radio" name="mm-fasta-type"
                   id="mm-fasta-<?= $mode ?>" value="<?= $mode ?>"
                   <?= $mode === 'gene' ? 'checked' : '' ?>>
            <label class="form-check-label small" for="mm-fasta-<?= $mode ?>"><?= $lbl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="mm-flank-wrap" class="d-none">
          <label class="form-label small mb-1"><?= t('Flank size (bp)') ?></label>
          <input type="number" id="mm-flank-bp" class="form-control form-control-sm moop-input"
                 style="max-width:160px;" placeholder="<?= t('e.g. 2000') ?>" min="1" max="100000">
        </div>
      </div>

    </div>
    </div><!-- /collapse -->
  </div>

  <!-- ④ Preview & Download -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">4</span>
      <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Preview & Download') ?></span>
      <?= field_help(
          'Preview shows the first 100 matching features so you can check your list. Download exports every matching feature, not just those 100.',
          'Preview & Download'
      ) ?>
    </div>
    <div class="card-body py-3">
      <?php /* Two buttons, one line, with the arrow showing the intended order:
               Preview -> Download. Preview used to be a quiet text link set apart from a
               full-width Download, which read as a footnote rather than a step — a user
               could easily not register it as the thing to do first. */ ?>
      <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
        <button type="button" class="btn mm-dl-secondary" id="mm-preview-btn">
          <span id="mm-count-spinner" class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
          <i class="fa fa-eye me-1"></i> <?= t('Preview') . "\n" ?>
        </button>
        <i class="fa fa-arrow-right mm-step-arrow" aria-hidden="true"></i>
        <button type="button" class="btn mm-dl-primary flex-grow-1" id="mm-dl-btn">
          <i class="fa fa-download me-1"></i> <span id="mm-dl-label"><?= t('Download TSV') ?></span>
        </button>
      </div>
      <div id="mm-count-result" class="small text-muted"></div>
      <?php /* What a FASTA export leaves out, and why (filled by moopmart.js from fasta_check).
               These used to be "# WARNING" lines at the end of the file itself, which some
               tools reject; the file is now pure FASTA and the reasons live here. */ ?>
      <div id="mm-fasta-notes" class="alert alert-warning small py-2 px-3 mt-2 mb-0 d-none" role="status"></div>
      <?php /* Shown only when the selection includes a gene set with expression data (moopmart.js).
               A heatmap is ONE gene set, so a list spanning several is split into one heatmap
               per gene set, and the gene sets without data are named as left out. */ ?>
<?php if (moop_tool_on('expression')): ?>
      <div id="mm-expr-row" class="d-none mt-2 pt-2 border-top">
        <button type="button" class="btn btn-sm mm-dl-secondary" id="mm-expr-btn">
          <span id="mm-expr-spinner" class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
          <i class="fa fa-th me-1"></i> <?= t('Send to RNA-seq Expression Explorer') . "\n" ?>
        </button>
        <span class="small text-muted ms-1"><?= t('Heatmap of these genes, one gene set at a time.') ?></span>
        <div id="mm-expr-result" class="small mt-2"></div>
      </div>
<?php endif; ?>
    </div>
  </div>

  <!-- Results preview — TSV table -->
  <div id="mm-results-section" class="d-none mt-3">
    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= t('Preview') ?> <small id="mm-results-caption" class="text-muted fw-normal ms-1"></small></span>
        <span class="text-muted small"><?= t('Download exports the full result set.') ?></span>
      </div>
      <div class="card-body p-2">
        <div class="table-responsive">
          <table id="mm-results-table" class="table table-sm table-striped table-hover w-100" style="font-size:0.85rem;"></table>
        </div>
      </div>
    </div>
  </div>

  <!-- Results preview — FASTA -->
  <div id="mm-fasta-preview-section" class="d-none mt-3">
    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= t('FASTA Preview') ?> <small id="mm-fasta-caption" class="text-muted fw-normal ms-1"></small></span>
        <span class="text-muted small"><?= t('Showing first 10 sequences. Download exports all.') ?></span>
      </div>
      <div class="card-body p-2">
        <pre id="mm-fasta-preview-text" class="mb-0" style="font-size:0.78rem; max-height:400px; overflow-y:auto; background:#f8f9fa; border-radius:4px; padding:0.75rem;"></pre>
      </div>
    </div>
  </div>

</div>

<?php include_once __DIR__ . '/../../includes/ann_types_modal.php'; ?>

<!-- Large-unfiltered-export warning: no Step 2 filter across many organisms -->
<div class="modal fade" id="mm-big-export-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning bg-opacity-10 py-2">
        <h5 class="modal-title fw-bold"><i class="fa fa-exclamation-triangle text-warning me-2"></i><?= t('Export every feature?') ?></h5>
      </div>
      <div class="modal-body">
        <?= t_markup('No filters are set in Step 2, so this exports %1$s across %2$s organisms (%3$s gene sets).',
                     '<strong>' . t('every feature') . '</strong>', '<strong class="mm-be-orgs"></strong>', '<strong class="mm-be-gs"></strong>') . "\n" ?>
        <?= t('That can be a very large file. Preview first to see the total, or go ahead.') . "\n" ?>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('Cancel') ?></button>
        <button type="button" class="btn btn-warning mm-be-confirm"><?= t('Download anyway') ?></button>
      </div>
    </div>
  </div>
</div>

<style>
/* Darker border on FASTA type radio buttons */
#mm-fasta-options .form-check-input[type="radio"] { border-color: #6c757d; }
/* Segmented pill toggles (Output format, Table layout) — the selected side fills teal.
   Clearer than a slider, which leaves "which side is on?" to guesswork. */
/* The little numbered order-badge on a chosen output column. Identical markup appeared
   twice in this file; one class so the pair cannot drift apart. */
.mm-col-num-badge { min-width:1.5em; text-align:center; font-size:0.72rem; padding:0.25em 0.4em;
                    background: var(--moop-tool-header); }

.mm-seg .btn { color: var(--moop-tool-header); border-color: var(--moop-tool-header); font-weight: 600; }
.mm-seg .btn:hover { background: rgba(var(--moop-main-rgb),0.08); color: var(--moop-tool-header); }
.mm-seg .btn-check:checked + .btn { background: var(--moop-tool-header); border-color: var(--moop-tool-header); color: #fff; }
.mm-seg .btn-check:focus-visible + .btn { box-shadow: 0 0 0 0.2rem rgba(var(--moop-main-rgb),0.35); }
/* Step 4: Download is the primary action, in the site's action-button indigo (var(--moop-action)) —
   the same colour as Run BLAST and Retrieve Sequences — so final actions read alike across
   tools. Teal is the section/accent colour, not the action colour.
   Preview is the SAME hue one shade lighter (outline, not fill): same family, clearly the
   lesser commitment, and it reads as a step rather than a footnote. It was a quiet text
   link until 2026-08-05; that made the recommended first action the least visible thing in
   the card. */
.mm-dl-primary { background: var(--moop-action); border-color: var(--moop-action); color: #fff; font-weight: 600; }
.mm-dl-primary:hover, .mm-dl-primary:focus { background: var(--moop-action-hover); border-color: var(--moop-action-hover); color: #fff; }
.mm-dl-secondary { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; font-weight: 600; }
/* "Filled in from the link you followed": soft amber, so it stands out from the teal steps without shouting.
   Fixed colours on purpose -- it means "look here" on every site, like the data colours. */
.mm-prefill-note { background: var(--moop-attention-wash); border: 1px solid var(--moop-attention-border-soft); border-left: 5px solid var(--moop-attention); color: var(--moop-attention-text); }
.mm-prefill-note > .fa-info-circle { color: #d07a1c; }
.mm-prefill-btn { background: var(--moop-attention-soft); border: 1px solid var(--moop-attention-border); color: var(--moop-attention-text); font-weight: 600; }
.mm-prefill-btn:hover, .mm-prefill-btn:focus { background: var(--moop-attention-hover); border-color: var(--moop-attention-strong); color: var(--moop-attention-text-strong); }
.mm-dl-secondary:hover, .mm-dl-secondary:focus { background: #e0e7ff; border-color: #a5b4fc; color: #3730a3; }
/* The arrow carries the order (preview THEN download). Muted so it guides without competing
   with either button. */
.mm-step-arrow { color: #a5b4fc; font-size: 0.85rem; }
.mm-ann-type-row { background: #f6f7f8; margin-bottom: 2px; }
.mm-ann-open-btn { white-space: nowrap; }
/* .step-ref (the inline "Step N" badge) now lives in css/display.css — Annotation Search
   needed it too, so it moved to the shared sheet rather than being copied. */
</style>

<?php /* Its own modal, not the shared search help (includes/search_help_modal.php): the
        MATCHING is now the same as Search (moopmartBuildTextConditions() uses Search's FTS
        index and expression), but that modal's ID-first, filter-button and result-cap cards
        do not apply here — and the Exporter does not rank, which changes what "word endings
        are handled" means in practice. */
echo help_modal('exporter-text-help', 'How these text filters match', [
    ['heading' => 'How your words are matched', 'cards' => [
        ['label' => 'One word', 'html' => true,
         'text'  => 'Matches from the <strong>start</strong> of a word. <code>wnt</code> finds '
                  . '<em>wnt8b</em>, but <code>transposase</code> will not find '
                  . '<em>retrotransposase</em> — never from the middle. Upper and lower case are treated the same.'],
        ['label' => 'Several words', 'html' => true,
         'text'  => 'All of them must appear, in any order. <code>kinase domain</code> finds records with both.'],
        ['label' => 'Words together', 'html' => true,
         'text'  => 'Put them in quotes. <code>"ABC transporter"</code> needs those words side by side, in that order.'],
        ['label' => 'Word endings are handled', 'html' => true,
         'text'  => '<code>kinases</code> also finds <em>kinase</em>, and <code>transporter</code> also finds '
                  . '<em>transport</em>. The export includes every match, so a broad word can bring in more '
                  . 'genes than you expect — add a second word to narrow it.'],
    ]],
    ['heading' => 'Same rules as Search', 'cards' => [
        ['label' => 'One matcher for the whole site',
         'text'  => 'These boxes match words exactly the way the main Search does, so a word that finds a '
                  . 'gene there finds it here.'],
    ]],
], ['intro' => 'Applies to the name, description and annotation keyword boxes on this page.']); ?>
