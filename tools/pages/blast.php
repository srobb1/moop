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
          <?= page_purpose('Search genome assemblies by sequence similarity. Paste a DNA or protein sequence, select a program and database, and run.', '', false, false) ?>
      </div>
      <?= tool_page_blurb('blast_search') ?>
    </div>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong><?= t('No accessible assemblies found.') ?></strong>
            <p class="mb-0"><?= t('You do not have access to any organism assemblies, or the data directory is misconfigured.') ?></p>
        </div>
    <?php else: ?>

        <?php if (isset($search_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> <?= t('Error:') ?></strong> <?= htmlspecialchars($search_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('Close') ?>"></button>
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
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Paste a sequence') ?></span>
                </div>
                <div class="card-body py-3">
                    <textarea
                        id="query"
                        name="query"
                        class="form-control fasta-textarea-ids"
                        rows="8"
                        required
                        placeholder="<?= t('Enter sequence in FASTA format or plain text') ?>"
                    ><?= htmlspecialchars($search_query) ?></textarea>
                    <small class="form-text text-muted"><?= t_markup('%s format (with >) or plain sequence.', gloss('fasta', 'FASTA')) ?>
                        <?= t('Up to %s sequences and %s letters per search.', number_format(max(1, $config->getInt('blast_max_query_seqs', 10))), number_format(max(1, $config->getInt('blast_max_query_letters', 100000)))) ?></small>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm fw-semibold text-white btn-moop-teal" onclick="loadSampleSequence('protein')">
                            <i class="fa fa-flask me-1"></i><?= t('Sample Protein') . "\n" ?>
                        </button>
                        <button type="button" class="btn btn-sm fw-semibold text-white btn-moop-teal" onclick="loadSampleSequence('nucleotide')">
                            <i class="fa fa-flask me-1"></i><?= t('Sample Nucleotide') . "\n" ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: BLAST Program -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">2</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Select a BLAST program') ?></span>
                    <?php
                    // The program decides what CAN be compared, and picking the wrong one
                    // returns an empty result that looks like "no match in this genome"
                    // rather than "wrong tool" -- so the chooser belongs at the control,
                    // not only in the general help.
                    ?>
                    <?= help_modal_trigger('blast-programs-help', '', 'Help: choosing a BLAST program') ?>
                </div>
                <div class="card-body py-3">
                    <div id="sequenceTypeInfo" class="sequence-type-info mb-2" style="display:none;">
                        <small id="sequenceTypeMessage"></small>
                    </div>
                    <select id="blast_program" name="blast_program" class="form-select" onchange="updateDatabaseList(); applyBlastProgramDefaults(this.value);">
                        <?php
                        // Rendered from blast_programs(), the same array the help modal builds
                        // its cards from. Written out six times by hand before, so the option
                        // text and the explanation of it could disagree with nothing to notice.
                        foreach (blast_programs() as $prog_id => $prog): ?>
                        <option value="<?= htmlspecialchars($prog_id) ?>" <?= $blast_program === $prog_id ? 'selected' : '' ?>><?= htmlspecialchars($prog['label'] . ' — ' . $prog['summary']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php /* Shown by js/modules/blast-short-nudge.js while the query is short DNA and
                             any other DNA-query program is selected. A suggestion: only the click switches. */ ?>
                    <div id="blast-short-suggest" class="alert alert-warning py-2 mt-2 small mb-0 d-none">
                        <i class="fa fa-lightbulb me-1"></i>
                        <span class="blast-short-what"></span>
                        <?= t('At this program\'s default settings a sequence this short is usually lost; BLASTn-short is built for it.') . "\n" ?>
                        <button type="button" class="btn btn-sm btn-moop-teal text-white ms-1 blast-short-switch"><?= t('Switch to BLASTn-short') ?></button>
                        <?= help_modal_trigger('blast-short-help', '', 'Help: searching with primers and other short sequences') ?>
                    </div>
                    <div id="blastn-short-notice" class="alert alert-info py-2 mt-2 small mb-0 <?= $blast_program === 'blastn-short' ? '' : 'd-none' ?>">
                        <i class="fa fa-info-circle me-1"></i>
                        <?= t_markup('Optimized for short sequences: word size 7, %s 1000, adjusted gap costs, no low-complexity filter.', gloss_first('e-value', 'E-value')) . "\n" ?>
                        <?= t('Advanced options have been pre-filled — you can override them.') . "\n" ?>
                        <?php // Plain '&' -- help_modal_trigger() escapes the label itself. ?>
                        <?= help_modal_trigger('blast-short-help', 'Primers & short sequences', 'Help: searching with primers and other short sequences') ?>
                        <?php
                        // Named on the PAGE, not only inside the help modal. Someone with a
                        // primer pair has already picked a program by this point and has no
                        // reason to open help -- they would run the search, read the HSPs by
                        // hand, and never learn the pair could be judged in one step.
                        ?>
<?php if (moop_tool_on('primer_blast')): ?>
                        <div class="mt-2 pt-2 border-top">
                            <i class="fa fa-vials me-1"></i>
                            <?= t_markup('Checking a primer %s?', '<strong>' . t('pair') . '</strong>') ?> <?= (moop_tool_on('genome_browser')
                                    ? t_markup('%s pairs the hits for you, reports every product size, and shows the products in the genome browser.', '<a href="' . htmlspecialchars(moop_url_base() . '/tools/primer_blast.php') . '">Primer BLAST</a>')
                                    : t_markup('%s pairs the hits for you and reports every product size.', '<a href="' . htmlspecialchars(moop_url_base() . '/tools/primer_blast.php') . '">Primer BLAST</a>')) . "\n" ?>
                            <?= t_markup('This page is the right tool for locating a %s short sequence.', '<em>' . t('single') . '</em>') . "\n" ?>
                        </div>
<?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Step 3: Organism and Database -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">3</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Select organism and database') ?></span>
                </div>
                <div class="card-body py-3">
                    <?php
                    // The shared organism selector, single mode (includes/scope_selector.php):
                    // one gene set or BLAST-only genome, as a radio named selected_source.
                    // The Database list below follows it (updateDatabaseList()).
                    $scope_opts = [
                        'mode'     => 'single',
                        'selected' => $scope_state['selected'],
                        'filter'   => $scope_state['filter'],
                        'only'         => $scope_state['only'],
                    ];
                    include __DIR__ . '/../../includes/scope_selector.php';
                    ?>
                    <div class="mt-3" id="databaseSelector">
                        <label class="form-label fw-semibold"><?= t('Database') ?></label>
                        <div id="databaseBadges" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <div style="padding:15px; text-align:center; color:#666; width:100%;">
                                <small><?= t('Select an assembly first') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Options (unnumbered — optional) -->
            <div class="mb-3">
                <button class="btn btn-outline-moop w-100 d-flex align-items-center justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#advOptions" aria-expanded="false" aria-controls="advOptions">
                    <span>
                        <i class="fas fa-sliders-h me-2"></i><strong><?= t('Advanced Options') ?></strong>
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
                            <i class="fa fa-check-circle me-1"></i><?= t('Adjusted for short sequences') . "\n" ?>
                        </span>
                    </span>
                    <?php // The shared arrow (css/moop.css): follows aria-expanded, no JavaScript. ?>
                    <i class="fa fa-chevron-down collapse-chevron"></i>
                </button>
                
                <div id="advOptions" class="collapse mt-3">
                    <div class="card card-body">
                        <!-- blastn-short preset notice -->
                        <div id="adv-short-notice" class="d-none mb-3 small fw-semibold" style="color:var(--moop-tool-header);">
                            <i class="fa fa-check-circle me-1"></i><?= t('Optimized for short searches — highlighted parameters have been adjusted.') . "\n" ?>
                        </div>
                        <!-- Basic Parameters -->
                        <div class="row">
                            <div class="col-md-6">
                                <label for="evalue" class="form-label adv-short-param"><strong><?= t('E-value Threshold') ?></strong></label>
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
                                <label for="max_results" class="form-label"><strong><?= t('Maximum Hits') ?></strong></label>
                                <input type="number" id="max_results" name="max_results" class="form-control" value="<?= (int)$max_results ?>" min="1" max="<?= (int)$max_hits_cap ?>">
                                <div class="form-text"><?= t('Up to %s. For more, run BLAST+ on your own computer.', number_format($max_hits_cap)) ?></div>
                                <small class="form-text text-muted"><?= t('Maximum number of hits to return') ?></small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="matrix" class="form-label"><strong><?= t('Scoring Matrix') ?></strong></label>
                                <?php
                                // $blast_options is [] until a search runs, so "isset" was always
                                // true and nothing matched: no option was selected and the browser
                                // fell back to the FIRST, BLOSUM45 -- every protein search ran
                                // BLOSUM45 under a label saying BLOSUM62 was the default.
                                $cur_matrix = $blast_options['matrix'] ?? 'BLOSUM62';
                                ?>
                                <select id="matrix" name="matrix" class="form-select">
                                    <?php foreach (['BLOSUM45', 'BLOSUM62', 'BLOSUM80', 'PAM30', 'PAM70', 'PAM250'] as $m): ?>
                                    <option value="<?= $m ?>" <?= $cur_matrix === $m ? 'selected' : '' ?>><?= $m ?><?= $m === 'BLOSUM62' ? ' (default)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted"><?= t('Only used for protein searches') ?></small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <?php // Before a search: BLAST's own default for the program (blast_programs() 'filter');
                                          // applyBlastProgramDefaults() keeps it in step when the program changes. ?>
                                    <input class="form-check-input" type="checkbox" name="filter_seq" id="filter_seq" <?= ($blast_options['filter'] ?? (blast_programs()[$blast_program]['filter'] ?? false)) ? 'checked' : '' ?>>
                                    <label class="form-check-label adv-short-param" for="filter_seq">
                                        <?= t('Filter low complexity regions') . "\n" ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Advanced Options -->
                        <hr class="my-4">
                        <h6 class="text-muted mb-3"><?= t('Additional Parameters') ?></h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="word_size" class="form-label adv-short-param"><strong><?= t('Word Size') ?></strong></label>
                                <input type="number" id="word_size" name="word_size" class="form-control" value="<?= !empty($blast_options['word_size']) ? htmlspecialchars($blast_options['word_size']) : '' ?>" placeholder="Default (program-specific)" min="1">
                                <small class="form-text text-muted"><?= t('Length of initial exact match (typically 11 for blastn, 3 for blastp)') ?></small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="max_hsps" class="form-label"><strong><?= t('Max HSPs') ?></strong></label>
                                <input type="number" id="max_hsps" name="max_hsps" class="form-control" value="<?= !empty($blast_options['max_hsps']) ? htmlspecialchars($blast_options['max_hsps']) : '' ?>" placeholder="Unlimited" min="1">
                                <small class="form-text text-muted"><?= t('Maximum number of HSPs to return per subject') ?></small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="perc_identity" class="form-label"><strong><?= gloss_first('percent identity', 'Percent Identity') ?></strong></label>
                                <input type="number" id="perc_identity" name="perc_identity" class="form-control" value="<?= !empty($blast_options['perc_identity']) ? htmlspecialchars($blast_options['perc_identity']) : '' ?>" placeholder="No threshold" min="0" max="100" step="0.1">
                                <small class="form-text text-muted"><?= t('Minimum percent identity (0-100)') ?></small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="culling_limit" class="form-label"><strong><?= t('Culling Limit') ?></strong></label>
                                <input type="number" id="culling_limit" name="culling_limit" class="form-control" value="<?= !empty($blast_options['culling_limit']) ? htmlspecialchars($blast_options['culling_limit']) : '' ?>" placeholder="No limit" min="0">
                                <small class="form-text text-muted"><?= t('Max alignments per subject (0 = unlimited)') ?></small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="gapopen" class="form-label adv-short-param"><strong><?= t('Gap Open Penalty') ?></strong></label>
                                <input type="number" id="gapopen" name="gapopen" class="form-control" value="<?= !empty($blast_options['gapopen']) ? htmlspecialchars($blast_options['gapopen']) : '' ?>" placeholder="Default" min="1">
                                <small class="form-text text-muted"><?= t('Cost to open a gap') ?></small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="gapextend" class="form-label adv-short-param"><strong><?= t('Gap Extend Penalty') ?></strong></label>
                                <input type="number" id="gapextend" name="gapextend" class="form-control" value="<?= !empty($blast_options['gapextend']) ? htmlspecialchars($blast_options['gapextend']) : '' ?>" placeholder="Default" min="1">
                                <small class="form-text text-muted"><?= t('Cost to extend a gap') ?></small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="threshold" class="form-label"><strong><?= t('Threshold') ?></strong></label>
                                <input type="number" id="threshold" name="threshold" class="form-control" value="<?= !empty($blast_options['threshold']) ? htmlspecialchars($blast_options['threshold']) : '' ?>" placeholder="Default" step="0.1">
                                <small class="form-text text-muted"><?= t('Minimum score for extending HSP (protein searches)') ?></small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="strand" class="form-label"><strong><?= t_markup('%s (DNA only)', gloss('strand', 'Strand')) ?></strong></label>
                                <?php
                                // Both by default, as BLAST itself does. "Plus" carried `selected` in
                                // BOTH branches of its condition, so every DNA search was plus-only and
                                // a query from the other strand returned nothing at all.
                                $cur_strand = $blast_options['strand'] ?? 'both';
                                ?>
                                <select id="strand" name="strand" class="form-select">
                                    <?php foreach (['both' => 'Both strands', 'plus' => 'Plus strand', 'minus' => 'Minus strand'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $cur_strand === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="soft_masking" id="soft_masking" <?= ($blast_options['soft_masking'] ?? (blast_programs()[$blast_program]['soft_masking'] ?? false)) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="soft_masking">
                                        <strong><?= t('Soft Masking') ?></strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block"><?= t('Apply soft masking to query and database') ?></small>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ungapped" id="ungapped" <?= !empty($blast_options['ungapped']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ungapped">
                                        <strong><?= t('Ungapped') ?></strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block"><?= t('Perform ungapped alignment only') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Run -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">4</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Run BLAST') ?></span>
                </div>
                <div class="card-body py-3">
                    <button type="submit" class="btn btn-lg fw-semibold text-white w-100 btn-moop-action" id="searchBtn">
                        <i class="fa fa-search me-1"></i><?= t('Run BLAST') . "\n" ?>
                    </button>
                    <?php /* Start over. Navigates to the bare page rather than resetting fields
                             in JS: a reset built from a list of fields silently stops covering
                             any control added later, and after a POST the browser's own
                             form.reset() restores the SUBMITTED values, not the defaults. A
                             clean GET is the only definition of "default" that cannot drift. */ ?>
                    <div class="text-center mt-2">
                        <a href="<?= htmlspecialchars(moop_url_base() . '/tools/blast.php') ?>"
                           class="btn btn-sm btn-link text-secondary" id="resetBlastBtn">
                            <i class="fa fa-undo me-1"></i><?= t('Clear form and start over') . "\n" ?>
                        </a>
                    </div>
                    <?php /* Inline "uh-oh" for a missed assembly/database, in place of a browser
                             alert(); js/blast-manager.js toggles it. */ ?>
                    <div id="blast-select-hint" class="tools-select-hint small mt-2" style="display:none;">
                        <?php /* Names the numbered step, not a direction. "above" with an
                                 up-arrow bakes in one layout: the steps stack on a narrow
                                 window but sit differently on a wide one, and the arrow is
                                 then pointing at nothing. A step number does not move. */ ?>
                        <i class="fa fa-exclamation-circle me-1"></i> <?= t('Choose an organism and database in step 3 before running BLAST.') . "\n" ?>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Results Section -->
        <?php if (isset($blast_result) && !empty($blast_result)): ?>
            <div class="card mt-5 shadow-sm" id="blastResultsCard">
                <div class="card-header text-white d-flex align-items-center tool-header">
                    <span class="text-uppercase fw-semibold section-eyebrow"><i class="fa fa-chart-bar me-2"></i><?= t('BLAST Results') ?></span>
                    <?php // Asked at the results, answered by controls back at step 2 — so the
                          // help has to be reachable from HERE, where the question occurs. ?>
                    <?= help_modal_trigger('blast-results-help', '', 'Help: reading and tuning your results') ?>
                </div>
                <div class="card-body">
                    <?php $blast_dl = $blast_job_id !== '' ? blast_job_available_formats($blast_job_id) : []; ?>
                    <?php if ($blast_dl): ?>
                    <?php /* Plain links to the files BLAST wrote, saved on the server for 24 hours
                             (lib/blast_jobs.php) -- the same files api/v1/blast.php hands out. */ ?>
                    <div class="mb-4 d-flex gap-2 flex-wrap">
                        <?php foreach (['txt' => ['fa-download', t('Download as TXT')], 'tsv' => ['fa-table', t('Download as TSV')], 'xml' => ['fa-code', t('Download as XML')]] as $fmt => [$icon, $label]): ?>
                        <?php if (in_array($fmt, $blast_dl, true)): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(blast_job_download_url($blast_job_id, $fmt)) ?>">
                            <i class="fa <?= $icon ?>"></i> <?= $label . "\n" ?>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif (!empty($blast_result['success'])): ?>
                    <div class="alert alert-warning mb-4"><?= t('The results are shown below, but the download files could not be saved on the server. This has been logged.') ?></div>
                    <?php endif; ?>

                    <?= getToggleQuerySectionScript() ?>
                    <?= generateCompleteBlastVisualization($blast_result, $search_query, $blast_program, $blast_options ?? [], $blast_linkout_context ?? []) ?>

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

// Program chooser, opened from the (i) beside the program select in step 2.
include __DIR__ . '/../../includes/blast_programs_help_modal.php';

// Reading and tuning results, opened from the (i) in the results card header.
include __DIR__ . '/../../includes/blast_results_help_modal.php';

// How-to-use help, opened by the (i) on the page header. A card modal rather than the
// hand-rolled data-bs-html popover it replaces: the popover needed a per-page init
// (blast-manager.js initPopovers, now removed), and a modal opens from the Bootstrap
// data-api with none. Steps carry their number in the label so order survives the grid.
echo help_modal(
    'blast-help',
    'How to use BLAST',
    [[
        'heading' => '',
        // 'num' and the labels match the page's own numbered step headers, so the help reads
        // as a map of the boxes below it -- the pattern Search and MOOPmart use. Advanced
        // options is unnumbered on the page, so it is unnumbered here, in the same place.
        'cards'   => [
            ['num' => '1', 'label' => 'Paste a sequence',               'text' => 'DNA or protein, in FASTA or as plain text.'],
            ['num' => '2', 'label' => 'Select a BLAST program',         'text' => 'The program decides which databases you can search — blastn for nucleotide, blastp for protein, and so on.'],
            ['num' => '3', 'label' => 'Select organism and database',   'text' => 'Select an organism and one of its databases to search against.'],
            [              'label' => 'Advanced options (optional)',    'text' => 'E-value, maximum hits, scoring matrix and more. The defaults are fine for most searches.'],
            ['num' => '4', 'label' => 'Run BLAST',                      'text' => moop_tool_on('genome_browser') ? 'Results list each hit with its alignment, and can link back to the gene page and genome browser.' : 'Results list each hit with its alignment, and can link back to its gene page.'],
        ],
    ]],
    ['intro' => 'Search genome assemblies by sequence similarity, in four steps.']
);
?>

<style>
.blast-short-highlight { color: var(--moop-tool-header) !important; }
</style>
