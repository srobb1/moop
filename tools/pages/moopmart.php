<?php
/**
 * MOOPmart — Data Exporter
 * Variables: $scope_tree, $organism_info, $organism_groups,
 *            $annotation_source_names, $annotation_source_types
 */

// Group color hash (matches index.js GROUP_COLORS palette)
$gp = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63','#00bcd4','#795548','#607d8b'];
$groupColor = fn($n) => $gp[abs(array_sum(array_map('ord', str_split($n))) * 31) % count($gp)];
?>
<div class="container py-3">

  <!-- Header -->
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2" style="background-color:#0891b2;">
      <?= page_title('MOOPmart — Data Exporter') ?>
      <?= help_modal_trigger('mm-help', '', 'What MOOPmart does') ?>
    </div>
    <div class="card-body py-2">
      <p class="text-muted small mb-0">Export annotation data or sequences.</p>
    </div>
  </div>

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


  <?php
  // Step 1 help, opened by the (i) on the header. Covers the whole selection mechanic —
  // the filter, that an organism means all its gene sets, what the Details toggle narrows
  // to, and the one-selection minimum. Grew past a single popover as the user added points,
  // so it is a card modal like the other step overviews.
  echo help_modal(
      'mm-scope-help',
      'How to select organisms',
      [[
          'heading' => '',
          'cards'   => [
              ['label' => 'Find them fast',
               'text'  => 'Type in the box to filter by group, genus / species or common name, assembly, or gene set.'],
              ['label' => 'Pick an organism',
               'text'  => 'Selecting an organism includes all of its gene sets — the usual case.'],
              ['label' => 'Or narrow with Details',
               'text'  => 'Turn on the Details switch to expand each organism and choose a specific assembly or gene set instead.'],
              ['label' => 'Pick at least one',
               'text'  => 'You need at least one selection before the steps below will do anything.'],
          ],
      ]],
      ['intro' => 'Choose which organisms — or specific gene sets — to pull features from.']
  );
  ?>

  <!-- ① Select Organisms -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">1</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Select organisms</span>
      <?= help_modal_trigger('mm-scope-help', '', 'How to select organisms') ?>
      <button type="button" class="btn btn-sm btn-light py-0 ms-auto mm-toggle-all">
        <span class="mm-toggle-all-label">Select all</span>
      </button>
    </div>
    <div class="px-2 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
      <input type="text" class="form-control form-control-sm moop-input" id="mm-scope-filter"
             placeholder="Filter by group, organism, assembly, gene set…" autocomplete="off">
      <div class="form-check form-switch mb-0 flex-shrink-0">
        <input class="form-check-input" type="checkbox" role="switch" id="mm-scope-detail">
        <label class="form-check-label small text-muted text-nowrap" for="mm-scope-detail">Details</label>
      </div>
    </div>
    <div style="overflow-y:auto; max-height:180px; background:#fff;" id="mm-scope-list" class="mm-scope-detail-hidden">
      <?php if (empty($scope_tree)): ?>
        <p class="text-muted small p-3">No accessible organisms found.</p>
      <?php else:
        // How many gene-set rows each organism has, so a multi-gene-set organism shows one
        // row in the simple view (with a "N gene sets" note) instead of N identical-looking
        // rows — the extra rows only distinguish themselves once Details is on. See the
        // representative-row logic below and the simple-view CSS at the bottom of the file.
        $orgGsCount = [];
        foreach ($scope_tree as $o => $asmList) {
            $orgGsCount[$o] = array_sum(array_map('count', $asmList));
        }
        $seenOrg = [];
        $rowIdx = 0;
        foreach ($scope_tree as $organism => $assemblies):
          $info   = $organism_info[$organism] ?? [];
          $label  = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $organism);
          $cn     = $info['common_name'] ?? '';
          $groups = $organism_groups[$organism] ?? [];
          foreach ($assemblies as $asm => $gene_sets):
            $an           = $assembly_names[$organism][$asm] ?? '';
            $asmDisplay   = $an ? $an : $asm;
            $asmAccession = $an ? $asm : '';
            foreach ($gene_sets as $gs):
              $rowIdx++;
              $gsid = 'mm_gs_' . $rowIdx;
              $searchSimple = strtolower("$label $cn " . implode(' ', $groups));
              $searchDetail = strtolower("$asm $an $gs");
              $search = $searchSimple . ' ' . $searchDetail;
              // First row for this organism is the representative shown in simple view; the
              // rest are hidden there and revealed by Details.
              $isRep   = !isset($seenOrg[$organism]);
              $seenOrg[$organism] = true;
              $gsCount = $orgGsCount[$organism] ?? 1;
      ?>
      <div class="org-select-row mm-scope-row<?= $isRep ? '' : ' mm-scope-row-secondary' ?>"
           data-org="<?= htmlspecialchars($organism) ?>"
           data-search="<?= htmlspecialchars($search) ?>"
           data-search-simple="<?= htmlspecialchars($searchSimple) ?>"
           data-search-detail="<?= htmlspecialchars($searchDetail) ?>">
        <input type="checkbox" class="mm-gs-cb visually-hidden"
               id="<?= $gsid ?>"
               data-org="<?= htmlspecialchars($organism) ?>"
               data-asm="<?= htmlspecialchars($asm) ?>"
               data-gs="<?= htmlspecialchars($gs) ?>">
        <span class="org-groups flex-shrink-0">
          <?php foreach ($groups as $g): ?>
          <span class="org-group-chip" style="background:<?= $groupColor($g) ?>"><?= htmlspecialchars($g) ?></span>
          <?php endforeach; ?>
        </span>
        <span class="flex-grow-1 text-truncate" style="min-width:0;">
          <em><?= htmlspecialchars($label) ?></em>
          <?php if ($cn): ?><span class="text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($cn) ?></span><?php endif; ?>
          <?php if ($isRep && $gsCount > 1): ?><span class="mm-scope-gs-count text-muted" style="font-size:0.78em;"> · <?= (int)$gsCount ?> gene sets</span><?php endif; ?>
          <span class="mm-scope-row-detail text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($asmDisplay) ?><?php if ($asmAccession): ?> <span style="font-size:0.9em;">(<?= htmlspecialchars($asmAccession) ?>)</span><?php endif; ?> › <?= htmlspecialchars($gs ?: '(default)') ?></span>
        </span>
        <span class="org-check flex-shrink-0"><i class="fas fa-check text-success"></i></span>
      </div>
      <?php endforeach; endforeach; endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="px-3 py-1 border-top" style="background:#f8f9fa; font-size:0.8rem;">
      <span class="text-muted" id="mm-scope-counts">Select at least one organism above</span>
      <div id="mm-scope-names" class="text-muted mt-1" style="font-size:0.78rem; font-style:italic;"></div>
    </div>
  </div>

  <!-- ② Build Your List -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">2</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Select Genes</span>
      <?= help_modal_trigger('mm-build-help', '', 'How to build your list') ?>
      <small class="ms-auto" style="color:rgba(255,255,255,0.75); font-size:0.78rem;">all sections optional</small>
    </div>
    <div class="card-body pt-2 pb-3">
      <p class="text-muted small mb-3">All sections are combined with AND — features must satisfy every filled section.</p>

      <!-- Accordion sections -->
      <div class="d-flex flex-column gap-2">

        <!-- By Feature IDs -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-ids-header" role="button"
               aria-expanded="false" aria-controls="mm-ids-body"
               data-bs-toggle="collapse" data-bs-target="#mm-ids-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Feature IDs</span>
            </span>
          </div>
          <div class="collapse" id="mm-ids-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">
                Paste gene, mRNA or protein IDs.
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
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Feature Name</span>
            </span>
          </div>
          <div class="collapse" id="mm-name-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Partial match, case-insensitive. Searches the feature name field.
                <?= help_modal_trigger('search-help', '', 'How to search') ?>
              </p>
              <input type="text" id="mm-gene-name" class="form-control moop-input"
                     placeholder="e.g. BRCA1">
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
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Feature Description</span>
            </span>
          </div>
          <div class="collapse" id="mm-desc-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Searches the feature description field. Partial match, case-insensitive.
                <?= help_modal_trigger('search-help', '', 'How to search') ?>
              </p>
              <input type="text" id="mm-gene-description" class="form-control moop-input"
                     placeholder="e.g. kinase">
            </div>
          </div>
        </div>

        <!-- By Annotation -->
        <?php
        // Build dropdown HTML for reuse by JS when adding new criteria rows
        // data-type on each optgroup preserves the pristine type name: the JS availability
        // counter rewrites the optgroup label to "Type (N)", so it needs the original to
        // build from rather than re-parsing its own output.
        $ann_dropdown = '<select class="form-select form-select-sm moop-input mm-ann-src-select">'
                      . '<option value="">Any annotation type</option>';
        foreach ($annotation_source_types as $_type => $_td):
            $ann_dropdown .= '<optgroup label="' . htmlspecialchars($_type) . '" data-type="' . htmlspecialchars($_type, ENT_QUOTES) . '">';
            foreach ($_td['sources'] as $_src):
                $ann_dropdown .= '<option value="' . htmlspecialchars($_src) . '">' . htmlspecialchars($_src) . '</option>';
            endforeach;
            $ann_dropdown .= '</optgroup>';
        endforeach;
        $ann_dropdown .= '</select>';
        ?>
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-ann-filter-header" role="button"
               aria-expanded="false" aria-controls="mm-ann-filter-body"
               data-bs-toggle="collapse" data-bs-target="#mm-ann-filter-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Annotation</span>
            </span>
          </div>
          <div class="collapse" id="mm-ann-filter-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-3">
                Every feature must satisfy <strong>all</strong> criteria (AND).
                Each row filters by annotation type, exact accession, or keyword — fill any combination.
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted"
                        style="font-size:0.85rem; line-height:1; vertical-align:middle;"
                        data-bs-toggle="modal" data-bs-target="#ann-types-modal" title="About annotation types">
                  <i class="fa fa-info-circle"></i>
                </button>
              </p>

              <!-- Column headers -->
              <div class="row g-2 mb-1 text-muted" style="font-size:0.75rem;">
                <div class="col-sm-4">Annotation type</div>
                <div class="col-sm-4">Accession <span class="text-muted">(exact)</span></div>
                <div class="col-sm-4">Keyword
                  <?= help_modal_trigger('search-help', '', 'How to search') ?>
                </div>
              </div>

              <!-- Criteria rows -->
              <div id="mm-ann-criteria">
                <div class="mm-ann-criterion row g-2 mb-2 align-items-center">
                  <div class="col-sm-4"><?= $ann_dropdown ?></div>
                  <div class="col-sm-4"><input type="text" class="form-control form-control-sm moop-input mm-ann-accession" placeholder="e.g. GO:0006351"></div>
                  <div class="col-sm-3"><input type="text" class="form-control form-control-sm moop-input mm-ann-keyword" placeholder="e.g. transporter"></div>
                  <div class="col-sm-1"></div>
                </div>
              </div>

              <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="mm-add-criterion">
                <i class="fa fa-plus me-1"></i> Add criterion
              </button>
            </div>
          </div>
        </div>

        <script>const mmAnnDropdownHtml = <?= json_encode($ann_dropdown) ?>;</script>

        <!-- By Chromosomal Location -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-loc-header" role="button"
               aria-expanded="false" aria-controls="mm-loc-body"
               data-bs-toggle="collapse" data-bs-target="#mm-loc-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Chromosomal Location</span>
            </span>
          </div>
          <div class="collapse" id="mm-loc-body">
            <div class="browse-select-panel">
              <p class="small text-muted fst-italic mb-2" id="mm-coord-note">
                Select exactly one assembly in Step 1 to enable location search.
              </p>
              <div class="row g-2">
                <div class="col-sm-4">
                  <label class="form-label small mb-1">Chr / scaffold</label>
                  <input type="text" id="mm-coord-chr" class="form-control form-control-sm moop-input"
                         placeholder="e.g. CHR01" list="mm-chr-datalist" autocomplete="off" disabled>
                  <datalist id="mm-chr-datalist"></datalist>
                </div>
                <div class="col-sm-4">
                  <label class="form-label small mb-1">Start <span class="text-muted">(1-based)</span></label>
                  <input type="number" id="mm-coord-start" class="form-control form-control-sm" placeholder="1" min="1" disabled>
                </div>
                <div class="col-sm-4">
                  <label class="form-label small mb-1">End <span class="text-muted">(1-based)</span></label>
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
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <div class="d-flex align-items-center gap-2 cursor-pointer"
           data-bs-toggle="collapse" data-bs-target="#mm-design-body" aria-expanded="false" aria-controls="mm-design-body">
        <span class="step-badge me-2">3</span>
        <span class="fw-semibold" style="font-size:0.9rem;">Select Output Options</span>
      </div>
      <?= help_modal_trigger('mm-output-help', '', 'Output formats') ?>
      <i class="fa fa-chevron-down ms-auto cursor-pointer" style="font-size:0.75rem; opacity:0.8; transition:transform 0.2s;" id="mm-design-chevron"
         data-bs-toggle="collapse" data-bs-target="#mm-design-body" aria-controls="mm-design-body"></i>
    </div>
    <div class="collapse" id="mm-design-body">
    <div class="card-body pt-3">

      <!-- Format toggle -->
      <div class="d-flex align-items-center gap-2 mb-4">
        <span id="mm-label-tsv" class="small fw-semibold" style="color:#0891b2; transition:color 0.15s;"><i class="fa fa-file-alt me-1"></i>TSV</span>
        <div class="form-check form-switch mb-0 mx-1">
          <input class="form-check-input" type="checkbox" role="switch" id="mm-format-switch" aria-label="FASTA format">
        </div>
        <span id="mm-label-fasta" class="small" style="color:#adb5bd; transition:color 0.15s;"><i class="fa fa-dna me-1"></i>FASTA</span>
      </div>

      <!-- TSV options -->
      <div id="mm-tsv-options">

        <!-- Wide / Long -->
        <div class="mb-4 d-flex align-items-center gap-2">
          <span id="mm-label-long" class="small fw-semibold" style="color:#0891b2; transition:color 0.15s;">Long</span>
          <div class="form-check form-switch mb-0 mx-1">
            <input class="form-check-input" type="checkbox" role="switch" id="mm-ann-wide-switch" aria-label="Wide format">
          </div>
          <span id="mm-label-wide" class="small" style="color:#adb5bd; transition:color 0.15s;">Wide</span>
          <?= field_help(
              'Long (default): one row per annotation, gene and mRNA IDs repeating — easiest to filter in Excel. '
              . 'Wide: one row per mRNA, a source\'s values joined with "; ".',
              'Table layout'
          ) ?>
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
              <span class="mm-col-num badge" style="min-width:1.5em; text-align:center; font-size:0.72rem; padding:0.25em 0.4em; background:#0891b2;"></span>
              <span class="mm-col-label small"><?= $lbl ?></span>
              <div class="ms-auto d-flex" style="gap:2px;">
                <button type="button" class="mm-col-up border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="Move up">&#9650;</button>
                <button type="button" class="mm-col-down border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="Move down">&#9660;</button>
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
            [[
                'heading' => '',
                'cards'   => [
                    [
                        'label'  => 'Inclusion Criteria',
                        'accent' => true,
                        'text'   => 'Why each feature is in your list — which Step 2 criterion pulled it in. '
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
            ]],
            ['intro' => 'Pick which columns the TSV has, and their order. Most are self-explanatory — these are the two worth a note.']
        );
        ?>

        <!-- Annotation columns -->
        <div class="mb-3">
          <div class="small fw-semibold text-muted mb-2">Annotation columns to include in TSV if Annotation types (below) are selected</div>
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
              <span class="mm-col-num badge" style="min-width:1.5em; text-align:center; font-size:0.72rem; padding:0.25em 0.4em; background:#0891b2;"></span>
              <span class="mm-col-label small"><?= $lbl ?></span>
              <div class="ms-auto d-flex" style="gap:2px;">
                <button type="button" class="mm-col-up border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="Move up">&#9650;</button>
                <button type="button" class="mm-col-down border-0 rounded p-0 lh-1 text-muted"
                        style="background:none; font-size:0.7rem; width:1.4em; height:1.4em;" title="Move down">&#9660;</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">Click to include/exclude &middot; arrows to reorder</div>
        </div>

        <!-- Annotation sources panel -->
        <?php if (!empty($annotation_source_types)): ?>
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <div class="small fw-semibold text-muted">Annotation types to include in TSV</div>
            <div class="d-flex gap-1 ms-auto">
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="mm-ann-all">All</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="mm-ann-none">None</button>
            </div>
          </div>
          <div class="px-2 pb-1">
            <input type="text" class="form-control form-control-sm moop-input" id="mm-ann-filter"
                   placeholder="Filter annotation types…" autocomplete="off">
          </div>
          <div id="mm-ann-panel" style="overflow-y:auto; max-height:220px;" class="border rounded mt-1 p-2">
            <?php foreach ($annotation_source_types as $type => $type_data):
              $type_safe = 'mm-atype-' . preg_replace('/[^a-z0-9]/i', '_', $type);
              $color     = htmlspecialchars($type_data['color']);
            ?>
            <div class="mm-ann-group mb-2">
              <div class="d-flex align-items-center px-1 py-1 rounded mb-1" style="background:#f1f3f5;">
                <input type="checkbox" class="form-check-input me-2 mb-0 mm-ann-type-cb flex-shrink-0"
                       id="<?= $type_safe ?>" data-type="<?= htmlspecialchars($type) ?>">
                <label for="<?= $type_safe ?>" class="form-check-label fw-semibold mb-0 me-auto"
                       style="cursor:pointer; font-size:0.88rem;">
                  <span class="badge bg-<?= $color ?> me-1"><?= htmlspecialchars($type) ?></span>
                </label>
              </div>
              <div class="ps-3">
                <?php foreach ($type_data['sources'] as $src_name):
                  $safe_id = 'mm-ann-' . preg_replace('/[^a-z0-9]/i', '_', $src_name);
                ?>
                <div class="d-flex align-items-center gap-1 px-1 py-1 mm-ann-item">
                  <input type="checkbox" class="form-check-input flex-shrink-0 mm-ann-col mb-0"
                         id="<?= $safe_id ?>"
                         value="<?= htmlspecialchars($src_name) ?>"
                         data-type="<?= htmlspecialchars($type) ?>">
                  <label class="form-check-label mb-0" for="<?= $safe_id ?>"
                         style="cursor:pointer; font-size:0.82rem;">
                    <?= htmlspecialchars($src_name) ?>
                  </label>
                  <span class="badge bg-secondary ms-1 mm-ann-count d-none" style="font-size:0.68rem;"
                        data-src="<?= htmlspecialchars($src_name) ?>"
                        title="Annotations available in the selected organisms (across all their genes)"></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-1" id="mm-ann-counts"></div>
        </div>
        <?php endif; ?>

      </div><!-- /mm-tsv-options -->

      <!-- FASTA options -->
      <div id="mm-fasta-options" class="d-none">
        <div class="small fw-semibold text-muted mb-2">Sequence type</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php
          $fasta_modes = [
            'gene'       => 'Genomic',
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
          <label class="form-label small mb-1">Flank size (bp)</label>
          <input type="number" id="mm-flank-bp" class="form-control form-control-sm moop-input"
                 style="max-width:160px;" placeholder="e.g. 2000" min="1" max="100000">
        </div>
      </div>

    </div>
    </div><!-- /collapse -->
  </div>

  <!-- ④ Preview & Download -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">4</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Preview &amp; Download</span>
      <?= field_help(
          'Preview shows the first 100 matching features so you can check your list. Download exports every matching feature, not just those 100.',
          'Preview & Download'
      ) ?>
    </div>
    <div class="card-body py-3 d-flex align-items-center gap-3 flex-wrap">
      <button type="button" class="btn btn-outline-primary" id="mm-preview-btn">
        <span id="mm-count-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
        <i class="fa fa-eye me-1"></i> Preview
      </button>
      <button type="button" class="btn btn-tool-emerald" id="mm-dl-btn">
        <i class="fa fa-download me-1"></i> <span id="mm-dl-label">Download TSV</span>
      </button>
      <div id="mm-count-result" class="small text-muted"></div>
    </div>
  </div>

  <!-- Results preview — TSV table -->
  <div id="mm-results-section" class="d-none mt-3">
    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Preview <small id="mm-results-caption" class="text-muted fw-normal ms-1"></small></span>
        <span class="text-muted small">Download exports the full result set.</span>
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
        <span class="fw-semibold">FASTA Preview <small id="mm-fasta-caption" class="text-muted fw-normal ms-1"></small></span>
        <span class="text-muted small">Showing first 10 sequences. Download exports all.</span>
      </div>
      <div class="card-body p-2">
        <pre id="mm-fasta-preview-text" class="mb-0" style="font-size:0.78rem; max-height:400px; overflow-y:auto; background:#f8f9fa; border-radius:4px; padding:0.75rem;"></pre>
      </div>
    </div>
  </div>

</div>

<?php include_once __DIR__ . '/../../includes/ann_types_modal.php'; ?>

<!-- Select-all-organisms warning modal -->
<div class="modal fade" id="mm-select-all-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning bg-opacity-10 py-2">
        <h5 class="modal-title fw-bold"><i class="fa fa-triangle-exclamation text-warning me-2"></i>Select all organisms?</h5>
      </div>
      <div class="modal-body">
        This will select all <strong id="mm-select-all-count"></strong> across all organisms.
        Searches across all organisms can take a while — consider selecting only the ones you need.
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="mm-select-all-confirm">Select all</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Simple/detail toggle for organism scope list.
   Simple view (detail-hidden): one row per organism — hide the per-gene-set detail text
   and the secondary rows, show the "N gene sets" note so the collapse is legible.
   Detail view: reveal every gene-set row and its detail, hide the now-redundant count. */
#mm-scope-list.mm-scope-detail-hidden .mm-scope-row-detail    { display: none; }
#mm-scope-list.mm-scope-detail-hidden .mm-scope-row-secondary { display: none; }
#mm-scope-list:not(.mm-scope-detail-hidden) .mm-scope-gs-count { display: none; }
/* Filter matched the hidden assembly/gene-set text: reveal that one row's detail even in
   simple view, so the user sees what their query matched. And when a representative row is
   force-showing a specific gene set, hide its "N gene sets" note — the user filtered to one
   gene set, so it should read as that single gene set, not as the collapsed organism. */
#mm-scope-list.mm-scope-detail-hidden .mm-scope-row.mm-scope-detail-forced .mm-scope-row-detail { display: inline; }
#mm-scope-list.mm-scope-detail-hidden .mm-scope-row.mm-scope-detail-forced .mm-scope-gs-count  { display: none; }
/* Darker border on FASTA type radio buttons */
#mm-fasta-options .form-check-input[type="radio"] { border-color: #6c757d; }
</style>

<?php /* Shared search-box help — ONE home, included by every page with a search
        box. 'multi' pages search several organisms at once and get the organism
        selection card plus the per-organism phrasing of the result cap. */ ?>
<?php $search_help_scope = 'single';
      include __DIR__ . '/../../includes/search_help_modal.php'; ?>
