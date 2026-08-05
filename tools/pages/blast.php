<?php
/**
 * BLAST SEARCH - Content File
 * 
 * Variables available (extracted from $data array by render_display_page):
 * - $accessible_sources
 * - $sources_by_group
 * - $context_organism
 * - $context_assembly
 * - $context_group
 * - $search_query
 * - $blast_program
 * - $selected_source
 * - $search_error
 * - $blast_result
 * - $evalue
 * - $max_results
 * - $site
 */
?>

<div class="container mt-5">

    <div class="card shadow-sm mb-4">
      <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
        <?= page_title('BLAST Search', 'fa fa-dna') ?>
        <?= help_modal_trigger('blast-help', '', 'How to use BLAST') ?>
      </div>
      <div class="card-body py-2">
        <p class="text-muted small mb-0">Search genome assemblies by sequence similarity. Paste a DNA or protein sequence, select a program and database, and run.</p>
      </div>
    </div>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>

        <?php if (isset($search_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($search_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="blastForm">
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_gene_set" value="<?= htmlspecialchars($context_gene_set) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" name="organism" value="">
            <input type="hidden" name="assembly" value="">
            <input type="hidden" name="gene_set" value="">

            <!-- Step 1: Sequence Input -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">1</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Paste a sequence</span>
                </div>
                <div class="card-body py-3">
                    <textarea
                        id="query"
                        name="query"
                        class="form-control fasta-textarea-ids"
                        rows="8"
                        required
                        placeholder="Enter sequence in FASTA format or plain text"
                    ><?= htmlspecialchars($search_query) ?></textarea>
                    <small class="form-text text-muted">FASTA format (with &gt;) or plain sequence.</small>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm fw-semibold text-white btn-moop-teal" onclick="loadSampleSequence('protein')">
                            <i class="fa fa-flask me-1"></i>Sample Protein
                        </button>
                        <button type="button" class="btn btn-sm fw-semibold text-white btn-moop-teal" onclick="loadSampleSequence('nucleotide')">
                            <i class="fa fa-flask me-1"></i>Sample Nucleotide
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: BLAST Program -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">2</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Select a BLAST program</span>
                </div>
                <div class="card-body py-3">
                    <div id="sequenceTypeInfo" class="sequence-type-info mb-2" style="display:none;">
                        <small id="sequenceTypeMessage"></small>
                    </div>
                    <select id="blast_program" name="blast_program" class="form-select" onchange="updateDatabaseList(); applyBlastProgramDefaults(this.value);">
                        <option value="blastn"       <?= $blast_program === 'blastn'       ? 'selected' : '' ?>>BLASTn — DNA query vs DNA database</option>
                        <option value="blastp"       <?= $blast_program === 'blastp'       ? 'selected' : '' ?>>BLASTp — Protein query vs protein database</option>
                        <option value="blastx"       <?= $blast_program === 'blastx'       ? 'selected' : '' ?>>BLASTx — DNA query vs protein database (translated)</option>
                        <option value="tblastn"      <?= $blast_program === 'tblastn'      ? 'selected' : '' ?>>tBLASTn — Protein query vs DNA database (translated)</option>
                        <option value="tblastx"      <?= $blast_program === 'tblastx'      ? 'selected' : '' ?>>tBLASTx — DNA query vs DNA database (both translated)</option>
                        <option value="blastn-short" <?= $blast_program === 'blastn-short' ? 'selected' : '' ?>>BLASTn-short — Short DNA query (primers, ~20nt)</option>
                    </select>
                    <div id="blastn-short-notice" class="alert alert-info py-2 mt-2 small mb-0 <?= $blast_program === 'blastn-short' ? '' : 'd-none' ?>">
                        <i class="fa fa-circle-info me-1"></i>
                        Optimized for short sequences: word size 7, E-value 1000, adjusted gap costs, no low-complexity filter.
                        Advanced options have been pre-filled — you can override them.
                        <?php // Plain '&' -- help_modal_trigger() escapes the label itself. ?>
                        <?= help_modal_trigger('blast-short-help', 'Primers & short sequences', 'Help: searching with primers and other short sequences') ?>
                    </div>
                </div>
            </div>

            <!-- Step 3: Organism and Database -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">3</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Select organism and database</span>
                </div>
                <div class="card-body py-3">
                    <?php
                    $clear_filter_function = 'clearBlastSourceFilters';
                    $on_change_function = 'updateDatabaseList';
                    include __DIR__ . '/../../includes/source-list.php';
                    ?>
                    <div class="mt-3 p-3 bg-light border rounded">
                        <strong>Currently selected:</strong>
                        <div id="currentSelection" style="margin-top:8px; font-size:14px;">
                            <span style="color:#999;">None selected</span>
                        </div>
                    </div>
                    <div class="mt-3" id="databaseSelector">
                        <label class="form-label fw-semibold">Database</label>
                        <div id="databaseBadges" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <div style="padding:15px; text-align:center; color:#666; width:100%;">
                                <small>Select an assembly first</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Options (unnumbered — optional) -->
            <div class="mb-3">
                <button class="btn btn-outline-moop w-100 d-flex align-items-center justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#advOptions" aria-expanded="false" aria-controls="advOptions">
                    <span>
                        <i class="fas fa-sliders-h me-2"></i><strong>Advanced Options</strong>
                        <?php // Shown only when a preset has changed these values. The panel stays
                              // CLOSED -- opening it confronts a first-time user with a dozen
                              // controls they did not ask for. This says something changed and
                              // where to look, without making them look. ?>
                        <?php // Initial state comes from the SERVER, like the notice above it.
                              // Toggled only by the onchange handler, the badge vanished on the
                              // page returned after a short search -- the one moment the values
                              // most obviously are adjusted. ?>
                        <span id="adv-modified-badge" class="badge rounded-pill ms-2 <?= $blast_program === 'blastn-short' ? '' : 'd-none' ?>"
                              style="background-color:#cff4e4; color:#0f5132; font-weight:600;">
                            <i class="fa fa-circle-check me-1"></i>Adjusted for short sequences
                        </span>
                    </span>
                    <i class="fa fa-chevron-down adv-chevron" style="font-size:0.8rem; transition:transform 0.2s;"></i>
                </button>
                
                <div id="advOptions" class="collapse mt-3">
                    <div class="card card-body">
                        <!-- blastn-short preset notice -->
                        <div id="adv-short-notice" class="d-none mb-3 small fw-semibold" style="color:#0891b2;">
                            <i class="fa fa-circle-check me-1"></i>Optimized for short searches — highlighted parameters have been adjusted.
                        </div>
                        <!-- Basic Parameters -->
                        <div class="row">
                            <div class="col-md-6">
                                <label for="evalue" class="form-label adv-short-param"><strong>E-value Threshold</strong></label>
                                <select id="evalue" name="evalue" class="form-select" onchange="toggleEvalueCustom()">
                                    <option value="1000" <?= $evalue === '1000' ? 'selected' : '' ?>>1000 (short queries / primers)</option>
                                    <option value="10" <?= $evalue === '10' ? 'selected' : '' ?>>10</option>
                                    <option value="1" <?= $evalue === '1' ? 'selected' : '' ?>>1</option>
                                    <option value="0.1" <?= $evalue === '0.1' ? 'selected' : '' ?>>0.1</option>
                                    <option value="1e-3" <?= $evalue === '1e-3' ? 'selected' : '' ?>>1e-3 (default)</option>
                                    <option value="1e-6" <?= $evalue === '1e-6' ? 'selected' : '' ?>>1e-6</option>
                                    <option value="1e-9" <?= $evalue === '1e-9' ? 'selected' : '' ?>>1e-9</option>
                                    <option value="1e-12" <?= $evalue === '1e-12' ? 'selected' : '' ?>>1e-12</option>
                                    <option value="custom" <?= !in_array($evalue, ['1000', '10', '1', '0.1', '1e-3', '1e-6', '1e-9', '1e-12']) && !empty($evalue) ? 'selected' : '' ?>>Custom</option>
                                </select>
                                <div id="evalue_custom_container" style="display: <?= !in_array($evalue, ['1000', '10', '1', '0.1', '1e-3', '1e-6', '1e-9', '1e-12']) && !empty($evalue) ? 'block' : 'none' ?>; margin-top: 8px;">
                                    <input type="text" id="evalue_custom" name="evalue_custom" class="form-control" placeholder="e.g., 1e-15, 0.05" value="<?= !in_array($evalue, ['1000', '10', '1', '0.1', '1e-3', '1e-6', '1e-9', '1e-12']) && !empty($evalue) ? htmlspecialchars($evalue) : htmlspecialchars($evalue_custom) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="max_results" class="form-label"><strong>Maximum Hits</strong></label>
                                <input type="number" id="max_results" name="max_results" class="form-control" value="<?= htmlspecialchars($max_results) ?>" min="1">
                                <small class="form-text text-muted">Maximum number of hits to return</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="matrix" class="form-label"><strong>Scoring Matrix</strong></label>
                                <select id="matrix" name="matrix" class="form-select">
                                    <option value="BLOSUM45" <?= isset($blast_options) && $blast_options['matrix'] === 'BLOSUM45' ? 'selected' : '' ?>>BLOSUM45</option>
                                    <option value="BLOSUM62" <?= !isset($blast_options) || (isset($blast_options) && $blast_options['matrix'] === 'BLOSUM62') ? 'selected' : '' ?>>BLOSUM62 (default)</option>
                                    <option value="BLOSUM80" <?= isset($blast_options) && $blast_options['matrix'] === 'BLOSUM80' ? 'selected' : '' ?>>BLOSUM80</option>
                                    <option value="PAM30" <?= isset($blast_options) && $blast_options['matrix'] === 'PAM30' ? 'selected' : '' ?>>PAM30</option>
                                    <option value="PAM70" <?= isset($blast_options) && $blast_options['matrix'] === 'PAM70' ? 'selected' : '' ?>>PAM70</option>
                                    <option value="PAM250" <?= isset($blast_options) && $blast_options['matrix'] === 'PAM250' ? 'selected' : '' ?>>PAM250</option>
                                </select>
                                <small class="form-text text-muted">Only used for protein searches</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="filter_seq" id="filter_seq" <?= (isset($blast_options) && $blast_options['filter']) ? 'checked' : '' ?>>
                                    <label class="form-check-label adv-short-param" for="filter_seq">
                                        Filter low complexity regions
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Advanced Options -->
                        <hr class="my-4">
                        <h6 class="text-muted mb-3">Additional Parameters</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="word_size" class="form-label adv-short-param"><strong>Word Size</strong></label>
                                <input type="number" id="word_size" name="word_size" class="form-control" value="<?= isset($blast_options) && $blast_options['word_size'] ? htmlspecialchars($blast_options['word_size']) : '' ?>" placeholder="Default (program-specific)" min="1">
                                <small class="form-text text-muted">Length of initial exact match (typically 11 for blastn, 3 for blastp)</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="max_hsps" class="form-label"><strong>Max HSPs</strong></label>
                                <input type="number" id="max_hsps" name="max_hsps" class="form-control" value="<?= isset($blast_options) && $blast_options['max_hsps'] ? htmlspecialchars($blast_options['max_hsps']) : '' ?>" placeholder="Unlimited" min="1">
                                <small class="form-text text-muted">Maximum number of HSPs to return per subject</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="perc_identity" class="form-label"><strong>Percent Identity</strong></label>
                                <input type="number" id="perc_identity" name="perc_identity" class="form-control" value="<?= isset($blast_options) && $blast_options['perc_identity'] ? htmlspecialchars($blast_options['perc_identity']) : '' ?>" placeholder="No threshold" min="0" max="100" step="0.1">
                                <small class="form-text text-muted">Minimum percent identity (0-100)</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="culling_limit" class="form-label"><strong>Culling Limit</strong></label>
                                <input type="number" id="culling_limit" name="culling_limit" class="form-control" value="<?= isset($blast_options) && $blast_options['culling_limit'] ? htmlspecialchars($blast_options['culling_limit']) : '' ?>" placeholder="No limit" min="0">
                                <small class="form-text text-muted">Max alignments per subject (0 = unlimited)</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="gapopen" class="form-label adv-short-param"><strong>Gap Open Penalty</strong></label>
                                <input type="number" id="gapopen" name="gapopen" class="form-control" value="<?= isset($blast_options) && $blast_options['gapopen'] ? htmlspecialchars($blast_options['gapopen']) : '' ?>" placeholder="Default" min="1">
                                <small class="form-text text-muted">Cost to open a gap</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="gapextend" class="form-label adv-short-param"><strong>Gap Extend Penalty</strong></label>
                                <input type="number" id="gapextend" name="gapextend" class="form-control" value="<?= isset($blast_options) && $blast_options['gapextend'] ? htmlspecialchars($blast_options['gapextend']) : '' ?>" placeholder="Default" min="1">
                                <small class="form-text text-muted">Cost to extend a gap</small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="threshold" class="form-label"><strong>Threshold</strong></label>
                                <input type="number" id="threshold" name="threshold" class="form-control" value="<?= isset($blast_options) && $blast_options['threshold'] ? htmlspecialchars($blast_options['threshold']) : '' ?>" placeholder="Default" step="0.1">
                                <small class="form-text text-muted">Minimum score for extending HSP (protein searches)</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="strand" class="form-label"><strong>Strand (DNA only)</strong></label>
                                <select id="strand" name="strand" class="form-select">
                                    <option value="plus" <?= isset($blast_options) && $blast_options['strand'] === 'plus' ? 'selected' : 'selected' ?>>Plus strand</option>
                                    <option value="minus" <?= isset($blast_options) && $blast_options['strand'] === 'minus' ? 'selected' : '' ?>>Minus strand</option>
                                    <option value="both" <?= isset($blast_options) && $blast_options['strand'] === 'both' ? 'selected' : '' ?>>Both strands</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="soft_masking" id="soft_masking" <?= isset($blast_options) && $blast_options['soft_masking'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="soft_masking">
                                        <strong>Soft Masking</strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block">Apply soft masking to query and database</small>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ungapped" id="ungapped" <?= isset($blast_options) && $blast_options['ungapped'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ungapped">
                                        <strong>Ungapped</strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block">Perform ungapped alignment only</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Run -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">4</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Run BLAST</span>
                </div>
                <div class="card-body py-3">
                    <button type="submit" class="btn btn-lg fw-semibold text-white w-100 btn-moop-action" id="searchBtn">
                        <i class="fa fa-search me-1"></i>Run BLAST
                    </button>
                    <?php /* Start over. Navigates to the bare page rather than resetting fields
                             in JS: a reset built from a list of fields silently stops covering
                             any control added later, and after a POST the browser's own
                             form.reset() restores the SUBMITTED values, not the defaults. A
                             clean GET is the only definition of "default" that cannot drift. */ ?>
                    <div class="text-center mt-2">
                        <a href="<?= htmlspecialchars('/' . $site . '/tools/blast.php') ?>"
                           class="btn btn-sm btn-link text-secondary" id="resetBlastBtn">
                            <i class="fa fa-rotate-left me-1"></i>Clear form and start over
                        </a>
                    </div>
                    <?php /* Inline "uh-oh" for a missed assembly/database, in place of a browser
                             alert(); js/blast-manager.js toggles it. */ ?>
                    <div id="blast-select-hint" class="tools-select-hint small mt-2" style="display:none;">
                        <?php /* Names the numbered step, not a direction. "above" with an
                                 up-arrow bakes in one layout: the steps stack on a narrow
                                 window but sit differently on a wide one, and the arrow is
                                 then pointing at nothing. A step number does not move. */ ?>
                        <i class="fa fa-circle-exclamation me-1"></i> Choose an organism and database in step 3 before running BLAST.
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Results Section -->
        <?php if (isset($blast_result) && !empty($blast_result)): ?>
            <div class="card mt-5 shadow-sm" id="blastResultsCard">
                <div class="card-header text-white d-flex align-items-center tool-header">
                    <span class="text-uppercase fw-semibold section-eyebrow"><i class="fa fa-chart-bar me-2"></i>BLAST Results</span>
                </div>
                <div class="card-body">
                    <div class="mb-4 d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadResultsText();">
                            <i class="fa fa-download"></i> Download as TXT
                        </button>
                        <?php if (!empty($blast_result['tabular'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadResultsTabular();">
                            <i class="fa fa-table"></i> Download as TSV
                        </button>
                        <?php endif; ?>
                        <?php if (!empty($blast_result['output'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadResultsXML();">
                            <i class="fa fa-code"></i> Download as XML
                        </button>
                        <?php endif; ?>
                    </div>

                    <?= getToggleQuerySectionScript() ?>
                    <?= generateCompleteBlastVisualization($blast_result, $search_query, $blast_program, $blast_options ?? [], $blast_linkout_context ?? []) ?>

                    <?php if (isset($blast_result['pairwise'])): ?>
                        <div id="pairwiseOutput" style="display: none;">
                            <?= htmlspecialchars($blast_result['pairwise']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($blast_result['tabular'])): ?>
                        <div id="tabularOutput" style="display: none;"><?= htmlspecialchars($blast_result['tabular']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($blast_result['output'])): ?>
                        <div id="xmlOutput" style="display: none;"><?= htmlspecialchars($blast_result['output']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php
// Short-sequence help, opened from the BLASTn-short notice in step 2. Its own modal
// rather than a section of the general one, because the advice INVERTS: everywhere else
// a strict E-value is what makes results trustworthy, and here it is what hides them.
include __DIR__ . '/../../includes/blast_short_help_modal.php';

// How-to-use help, opened by the (i) on the page header. A card modal rather than the
// hand-rolled data-bs-html popover it replaces: the popover needed a per-page init
// (blast-manager.js initPopovers, now removed), and a modal opens from the Bootstrap
// data-api with none. Steps carry their number in the label so order survives the grid.
echo help_modal(
    'blast-help',
    'How to use BLAST',
    [[
        'heading' => '',
        'cards'   => [
            ['label' => '1. Paste a sequence', 'text' => 'DNA or protein, in FASTA or as plain text.'],
            ['label' => '2. Pick a program',   'text' => 'The program decides which databases you can search — blastn for nucleotide, blastp for protein, and so on.'],
            ['label' => '3. Choose a database', 'text' => 'Select an organism and one of its databases to search against.'],
            ['label' => '4. Adjust if needed',  'text' => 'Advanced options set e-value, maximum hits, scoring matrix and more. The defaults are fine for most searches.'],
            ['label' => '5. Run BLAST',         'text' => 'Results list each hit with its alignment, and can link back to the gene page and genome browser.'],
        ],
    ]],
    ['intro' => 'Search genome assemblies by sequence similarity, in five steps.']
);
?>

<style>
.adv-chevron { transition: transform 0.2s; }
#advOptions.show ~ * .adv-chevron,
.adv-chevron.open { transform: rotate(180deg); }
.blast-short-highlight { color: var(--moop-tool-header) !important; }
</style>
<script>
document.getElementById('advOptions')?.addEventListener('show.bs.collapse', function () {
    document.querySelector('.adv-chevron')?.classList.add('open');
});
document.getElementById('advOptions')?.addEventListener('hide.bs.collapse', function () {
    document.querySelector('.adv-chevron')?.classList.remove('open');
});

// Whether the BLASTn-short preset currently owns the advanced values, so that switching
// away can put them back. Initialised from what the server rendered, so a page returning
// from a short search knows the preset is in effect.
let blastShortPresetApplied = <?= $blast_program === 'blastn-short' ? 'true' : 'false' ?>;

function applyBlastProgramDefaults(program) {
    const notice    = document.getElementById('blastn-short-notice');
    const advNotice = document.getElementById('adv-short-notice');
    const badge     = document.getElementById('adv-modified-badge');
    const isShort   = program === 'blastn-short';

    notice?.classList.toggle('d-none', !isShort);
    advNotice?.classList.toggle('d-none', !isShort);
    // The collapsed bar carries the signal instead of the panel being forced open.
    badge?.classList.toggle('d-none', !isShort);
    document.querySelectorAll('.adv-short-param').forEach(el => {
        el.classList.toggle('blast-short-highlight', isShort);
    });

    if (isShort) {
        // Advanced Options stays CLOSED on purpose. It used to spring open here so the
        // user could "see what was set", which meant a first-time user picking a primer
        // search was immediately faced with a dozen controls they had not asked for. The
        // badge in the bar says the values changed; anyone who wants the detail opens it,
        // and the changed fields are still highlighted when they do.

        // Pre-fill advanced fields
        const wordSize  = document.getElementById('word_size');
        const evalue    = document.getElementById('evalue');
        const filterCb  = document.getElementById('filter_seq');
        const gapopen   = document.getElementById('gapopen');
        const gapextend = document.getElementById('gapextend');
        if (wordSize)  wordSize.value   = '7';
        // 1000, not 10. blastn-short's own default IS 1000 (`blastn -help`); passing 10
        // overrode it and cut a real 20nt primer from 566 hits to 26. The notice below
        // has always SAID 1000 -- only the code disagreed.
        if (evalue)    evalue.value     = '1000';
        // DUST off. It is `no` for blastn-short upstream, and left on it silently
        // returns ZERO hits for any primer with a simple repeat (ATATAT..., poly-A).
        if (filterCb)  filterCb.checked = false;
        if (gapopen)   gapopen.value    = '5';
        if (gapextend) gapextend.value  = '2';
        // Auto-select genome database if available
        const dbBadges = document.getElementById('databaseBadges');
        if (dbBadges) {
            const radios = dbBadges.querySelectorAll('input[type="radio"][name="blast_db"]');
            let genomeRadio = null;
            radios.forEach(r => {
                const label = dbBadges.querySelector('label[for="' + r.id + '"]');
                if (label && /genome/i.test(label.textContent)) genomeRadio = r;
            });
            if (genomeRadio) genomeRadio.checked = true;
        }
    } else if (blastShortPresetApplied) {
        // Switching AWAY from the short preset must put the values back, or the user runs
        // an ordinary blastn at E-value 1000 with word size 7 and no filter, with nothing
        // on screen saying so -- the badge has just been hidden.
        //
        // This was harmless until 2026-08-03 only because word_size and friends were never
        // passed to BLAST at all. Now that they are, leaving them behind is a real wrong
        // result. Blank means "use the program default" on the server side.
        const wordSize  = document.getElementById('word_size');
        const evalue    = document.getElementById('evalue');
        const filterCb  = document.getElementById('filter_seq');
        const gapopen   = document.getElementById('gapopen');
        const gapextend = document.getElementById('gapextend');
        if (wordSize)  wordSize.value   = '';
        if (evalue)    evalue.value     = '1e-3';
        if (filterCb)  filterCb.checked = true;
        if (gapopen)   gapopen.value    = '';
        if (gapextend) gapextend.value  = '';
    }

    blastShortPresetApplied = isShort;
}
</script>
