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
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#0891b2;">
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">MOOPmart — Data Exporter</span>
      <button type="button" class="btn btn-link p-0 text-white"
              style="font-size:1rem; opacity:0.85; line-height:1;"
              data-bs-toggle="modal" data-bs-target="#mm-help-modal">
        <i class="fa fa-info-circle"></i>
      </button>
    </div>
    <div class="card-body py-2">
      <p class="text-muted small mb-0">Export annotation data or sequences.</p>
    </div>
  </div>

  <!-- Help Modal -->
  <div class="modal fade" id="mm-help-modal" tabindex="-1" aria-labelledby="mm-help-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header py-2" style="background:#0891b2; color:#fff;">
          <h6 class="modal-title fw-semibold mb-0" id="mm-help-modal-label">What can MOOPmart do?</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body small">
          <p class="mb-3">MOOPmart lets you build a custom list of genomic features — genes, mRNAs, or other annotation types — across one or more assemblies, then enrich and download that list.</p>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">Build your list by…</h6>
          <ul class="mb-3">
            <li><strong>Feature IDs</strong> — paste a set of known gene or mRNA IDs</li>
            <li><strong>Shared feature names</strong> — find features that share a common name across assemblies</li>
            <li><strong>Annotation descriptions</strong> — e.g. all features with <em>"HDAC"</em> anywhere in their description</li>
            <li><strong>Annotation IDs</strong> — e.g. all features with the Gene Ontology term <em>GO:0006351</em></li>
            <li><strong>Genomic coordinates</strong> — all features within a specific chromosomal range</li>
          </ul>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">Decorate your list with…</h6>
          <ul class="mb-3">
            <li>UniProt/Swiss-Prot homolog information</li>
            <li>PFAM domain annotations</li>
            <li>Gene Ontology terms</li>
            <li>Any other annotation columns available for your assemblies</li>
          </ul>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">Download as…</h6>
          <ul class="mb-0">
            <li><strong>TSV (spreadsheet)</strong> — choose and reorder the columns you want</li>
            <li><strong>FASTA</strong> — pick the sequence type: genomic (with introns), mRNA, CDS, protein, or upstream/downstream flanking sequence</li>
          </ul>
        </div>
        <div class="modal-footer py-2">
          <a href="search.php" class="btn btn-sm btn-outline-secondary">Try Annotation Search first</a>
          <button type="button" class="btn btn-sm text-white" style="background:#0891b2;" data-bs-dismiss="modal">Got it</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Build Your List help modal -->
  <div class="modal fade" id="mm-build-help-modal" tabindex="-1" aria-labelledby="mm-build-help-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header py-2" style="background:#0891b2; color:#fff;">
          <h6 class="modal-title fw-semibold mb-0" id="mm-build-help-label">How to build your list</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body small">

          <p class="mb-3">Each section is a different way to filter genes. Leave a section empty to ignore it. Fill more than one and only genes that satisfy <strong>all</strong> of them will appear in your list.</p>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">By Feature IDs</h6>
          <p class="mb-1">Paste one or more IDs — gene IDs, mRNA IDs, or protein IDs — one per line or comma-separated. Each is resolved to its parent gene automatically.</p>
          <ul class="mb-3">
            <li><strong>Gene ID</strong> — matched directly: <code>AT1G12345</code></li>
            <li><strong>mRNA ID</strong> — walks up to the parent gene: <code>AT1G12345.1</code></li>
            <li><strong>Protein ID</strong> — walks up through mRNA to gene: <code>XP_023382306.1</code></li>
          </ul>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">By Feature Name</h6>
          <p class="mb-3">Partial, case-insensitive match on the gene name field. For example, entering <code>HDAC</code> will find genes named <em>HDAC1</em>, <em>HDAC2</em>, <em>pHDAC3</em>, etc.</p>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">By Feature Description</h6>
          <p class="mb-3">Partial match on the gene description. For example, <code>kinase</code> finds any gene whose description contains that word, such as <em>serine/threonine-protein kinase</em>.</p>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">By Annotation</h6>
          <p class="mb-1">Filter by functional annotations attached to genes — GO terms, InterPro domains, BLAST hits, and more. Each row is one criterion; all rows must be satisfied (AND).</p>
          <ul class="mb-3">
            <li><strong>Annotation type</strong> — narrow to a specific database, e.g. <em>Gene Ontology</em> or <em>InterPro</em></li>
            <li><strong>Accession (exact)</strong> — match a specific ID, e.g. <code>GO:0006351</code> or <code>IPR000719</code></li>
            <li><strong>Keyword</strong> — partial match on the annotation description, e.g. <code>transcription factor</code></li>
          </ul>
          <p class="mb-3">Example: to find all genes with a kinase domain <em>and</em> a specific GO term, add two rows — one for <code>IPR000719</code> and one for <code>GO:0004672</code>.</p>

          <h6 class="fw-semibold mb-1" style="color:#0891b2;">By Chromosomal Location</h6>
          <p class="mb-0">Returns all genes whose coordinates overlap the specified range. Only available when exactly one assembly is selected in Step 1. Enter a chromosome or scaffold name and optional start/end positions.</p>

        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm text-white" style="background:#0891b2;" data-bs-dismiss="modal">Got it</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ① Select Organisms -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">1</span>
      <span class="fw-semibold me-auto" style="font-size:0.9rem;">Select organisms</span>
      <div class="d-flex gap-1">
        <button type="button" class="btn btn-sm btn-outline-light py-0" id="mm-select-all">All</button>
        <button type="button" class="btn btn-sm btn-outline-light py-0" id="mm-clear-all">None</button>
      </div>
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
      ?>
      <div class="org-select-row mm-scope-row"
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
      <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline"
              style="font-size:0.85rem; color:rgba(255,255,255,0.8);"
              data-bs-toggle="modal" data-bs-target="#mm-build-help-modal">
        <i class="fa fa-info-circle"></i>
      </button>
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
                Paste gene IDs, mRNA IDs, or protein IDs — one per line or comma/space separated.
                Each ID is resolved to its gene: a protein ID walks up to the parent mRNA, then the parent gene.
                An <strong>Inclusion Criteria</strong> column in your output will show exactly which input ID each result came from.
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
                <i class="fa fa-info-circle search-instructions-trigger ms-1" style="cursor:pointer;" data-help-type="basic"></i>
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
                <i class="fa fa-info-circle search-instructions-trigger ms-1" style="cursor:pointer;" data-help-type="basic"></i>
              </p>
              <input type="text" id="mm-gene-description" class="form-control moop-input"
                     placeholder="e.g. kinase">
            </div>
          </div>
        </div>

        <!-- By Annotation -->
        <?php
        // Build dropdown HTML for reuse by JS when adding new criteria rows
        $ann_dropdown = '<select class="form-select form-select-sm moop-input mm-ann-src-select">'
                      . '<option value="">Any annotation type</option>';
        foreach ($annotation_source_types as $_type => $_td):
            $ann_dropdown .= '<optgroup label="' . htmlspecialchars($_type) . '">';
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
                  <i class="fa fa-info-circle search-instructions-trigger ms-1" style="cursor:pointer; font-size:0.9em;" data-help-type="basic"></i>
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
    <div class="card-header py-2 d-flex align-items-center gap-2 cursor-pointer" style="background:#0891b2; color:#fff;"
         data-bs-toggle="collapse" data-bs-target="#mm-design-body" aria-expanded="false" aria-controls="mm-design-body">
      <span class="step-badge me-2">3</span>
      <span class="fw-semibold me-auto" style="font-size:0.9rem;">Select Output Options</span>
      <i class="fa fa-info-circle" style="cursor:pointer; color:rgba(255,255,255,0.7);"
         data-bs-toggle="popover" data-bs-placement="left" data-bs-html="true"
         data-bs-title="Design your output"
         data-bs-content="Choose a <strong>format</strong> and which <strong>columns</strong> to include, then click Preview or Download in Step 4.<br><br><strong>TSV (Tab-Separated Values)</strong> — a plain-text spreadsheet where columns are separated by tab characters. To open in Excel: choose <em>File &rarr; Open</em>, select the downloaded file, and Excel will parse it automatically (or use <em>Data &rarr; From Text/CSV</em> if it opens as one column).<br><br><strong>Wide vs Long</strong> — Wide puts all annotation values for a feature on one row, joined with &#039;; &#039;. Long gives one row per annotation, which is easier to filter in Excel.<br><br><strong>FASTA</strong> — a standard sequence format used by bioinformatics tools. Each entry starts with a <code>&gt;header</code> line (containing the feature ID and organism), followed by the nucleotide or protein sequence on the next line. FASTA files can be opened in any text editor or loaded directly into tools like BLAST, MUSCLE, or Galaxy." onclick="event.stopPropagation();"></i>
      <i class="fa fa-chevron-down ms-1" style="font-size:0.75rem; opacity:0.8; transition:transform 0.2s;" id="mm-design-chevron"></i>
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
          <i class="fa fa-info-circle text-muted ms-1" style="cursor:pointer; font-size:0.85rem;"
             data-bs-toggle="popover" data-bs-placement="right" data-bs-html="true"
             data-bs-title="Table layout"
             data-bs-content="<strong>Long</strong> (default) — one row per mRNA per annotation. Gene and mRNA IDs repeat so every annotation has its own line — easiest to filter in Excel.<br><br><strong>Wide</strong> — one row per mRNA, all annotation values for each source joined with '; '"></i>
        </div>

        <!-- Feature columns -->
        <div class="mb-3">
          <div class="small fw-semibold text-muted mb-2">Feature columns to include in TSV</div>
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
            <div class="small fw-semibold text-muted">Annotation types to include</div>
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
      <i class="fa fa-info-circle ms-1" style="cursor:pointer; color:rgba(255,255,255,0.7);"
         data-bs-toggle="popover" data-bs-placement="right" data-bs-html="true"
         data-bs-title="Preview &amp; Download"
         data-bs-content="Preview shows the first 100 matching features so you can verify your list before downloading. The download exports <strong>all</strong> matching features."></i>
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
        This will select all <strong id="mm-select-all-count"></strong> gene sets across all organisms.
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
/* Simple/detail toggle for organism scope list */
#mm-scope-list.mm-scope-detail-hidden .mm-scope-row-detail { display: none; }
/* Darker border on FASTA type radio buttons */
#mm-fasta-options .form-check-input[type="radio"] { border-color: #6c757d; }
</style>
