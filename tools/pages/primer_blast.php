<?php
/**
 * PRIMER BLAST - Content File
 *
 * Variables available (extracted from $data by render_display_page):
 * - $accessible_sources, $sources_by_group, $filter_organisms
 * - $context_organism, $context_assembly, $context_gene_set, $context_group
 * - $selected_source, $selected_organism, $selected_assembly_name, $selected_assembly_accession
 * - $primer_text, $max_mismatch, $max_product, $search_mode
 * - $parse_result, $results, $searched_dbs, $db_notes, $search_error
 * - $site
 */

/** Short label, for the "Searched: …" line in the results header. */
$db_label = function ($key) {
    return $key === 'genome' ? 'Genome' : 'Transcriptome';
};

/**
 * Label for a result section: what template the product would come from.
 *
 * Naming the template rather than interpreting it ("your intended product",
 * "contamination") keeps the two sections symmetrical and lets the reader draw
 * the comparison themselves — which is the point, since the two sizes differing
 * IS the intron answer. The intended template is already listed first.
 */
$db_section_label = function ($key) {
    return $key === 'transcript'
        ? 'Predicted Product from cDNA'
        : 'Predicted Product from gDNA';
};
?>

<div class="container mt-5">

    <div class="card shadow-sm mb-4">
      <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
        <?= page_title('Primer BLAST', 'fa fa-vials') ?>
      </div>
      <div class="card-body py-2">
        <?= page_purpose('Check primer pairs against a genome and transcriptome: how many products they would amplify, where those land, and how big each one is.') ?>
      </div>
    </div>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>

        <?php if (!empty($search_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="primerSearchError">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($search_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="primerBlastForm" enctype="multipart/form-data"
              data-default-mismatch="1"
              data-default-product="<?= (int)PrimerPairs::MAX_PRODUCT_DEFAULT ?>"
              data-default-mode="genome">
            <?= csrf_input_field() ?>
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_gene_set" value="<?= htmlspecialchars($context_gene_set) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">

            <!-- Step 1: Primers -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">1</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Enter your primers</span>
                </div>
                <div class="card-body py-3">
                    <?php // One line for the whole step: it governs BOTH the textarea and the
                          // upload below it, so it sits above both rather than between them. ?>
                    <p class="mb-2">
                        Paste your primers below, or upload a file.
                        <span class="text-muted">Four formats are accepted and detected automatically.</span>
                        <?= help_modal_trigger('primer-formats-help', 'Accepted formats', 'Accepted primer input formats') ?>
                    </p>

                    <textarea
                        name="primers"
                        id="primers"
                        rows="8"
                        class="form-control fasta-textarea-ids"
                        placeholder="&gt;TP53_F&#10;GCTTGAGCTGTTATCTGTGC&#10;&gt;TP53_R&#10;GCGGTGCTTCTGGGCTGAGT"
                    ><?= htmlspecialchars($primer_text) ?></textarea>

                    <label for="primer_file" class="form-label mt-3">…or upload a file</label>
                    <input type="file" class="form-control" id="primer_file" name="primer_file"
                           accept=".fa,.fasta,.fas,.txt,.tsv,.csv">
                    <div class="form-text">
                        The file's contents are loaded into the box above, where you can edit them
                        before searching. Max 1 MB.
                    </div>

                    <div class="alert alert-info py-2 px-3 mt-2 d-none" id="fileOverrideNotice">
                        <i class="fa fa-info-circle"></i>
                        <span id="fileOverrideName"></span>
                    </div>

                </div>
            </div>

            <!-- Step 2: Source -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">2</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Select the assembly to check against</span>
                </div>
                <div class="card-body py-3">
                    <?php
                    $clear_filter_function = 'clearPrimerSourceFilters';
                    $on_change_function    = 'updatePrimerSelection';
                    $source_list_help_id   = 'source-list-help';
                    include __DIR__ . '/../../includes/source-list.php';
                    ?>
                    <div class="mt-3 p-3 bg-light border rounded">
                        <strong>Currently selected:</strong>
                        <div id="primerCurrentSelection" style="margin-top:8px; font-size:14px;">
                            <span class="text-muted">None selected</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Options -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">3</span>
                    <span class="fw-semibold" style="font-size:0.9rem;">Search options</span>
                </div>
                <div class="card-body py-3">
                    <div class="row g-3">

                        <div class="col-md-4">
                          <div class="card h-100 border">
                            <div class="card-body py-3">
                              <?php // d-block: Bootstrap's .form-label is inline-block, and so are
                                    // the .form-check-inline radios that follow -- without this they
                                    // flow up onto the label's line. ?>
                              <label class="form-label d-block mb-2">
                                <strong>PCR input</strong>
                                <?= help_modal_trigger('pcr-input-help', '', 'What each PCR input searches') ?>
                              </label>
                              <?php // Inline: only two mutually exclusive choices with short
                                    // labels, so stacking them wasted the card's height and made
                                    // this card taller than its two siblings for no reason. ?>
                              <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="search_mode" id="mode_genome"
                                       value="genome" <?= $search_mode === 'genome' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mode_genome">Genomic DNA</label>
                              </div>
                              <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="search_mode" id="mode_transcript"
                                       value="transcript" <?= $search_mode === 'transcript' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mode_transcript">cDNA</label>
                              </div>
                              <div class="form-text text-warning-emphasis mt-1 d-none" id="pcrInputNote"></div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="card h-100 border">
                            <div class="card-body py-3">
                              <label for="max_mismatch" class="form-label mb-2">
                                <strong>Mismatches allowed</strong>
                                <?= field_help(
                                    'The most mismatches a match may have and still be kept. The default of 1 '
                                    . 'keeps single-mismatch sites, because those can still prime and amplify — '
                                    . 'a perfect-match-only search would hide real off-targets. Raise it to see '
                                    . 'weaker sites too; set it to 0 for exact matches only.',
                                    'Mismatches allowed'
                                ) ?>
                              </label>
                              <input type="number" class="form-control" id="max_mismatch" name="max_mismatch"
                                     min="0" max="5" value="<?= (int)$max_mismatch ?>">
                            </div>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="card h-100 border">
                            <div class="card-body py-3">
                              <label for="max_product" class="form-label mb-2">
                                <strong>Largest product to report</strong>
                                <?= field_help(
                                    'Products bigger than this are counted but not listed. Standard PCR '
                                    . 'makes about 2 kb, but the genomic product of a pair that spans an '
                                    . 'intron includes the intron and is much larger — so the default is '
                                    . 'set high enough to keep those visible. Lower it to see only what '
                                    . 'would realistically amplify.',
                                    'Largest product to report'
                                ) ?>
                              </label>
                              <input type="number" class="form-control" id="max_product" name="max_product"
                                     min="100" max="1000000" step="100" value="<?= (int)$max_product ?>">
                            </div>
                          </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="mb-4 d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Check primers
                </button>
                <button type="button" class="btn btn-outline-secondary" id="resetPrimerForm">
                    <i class="fa fa-rotate-left"></i> Clear form
                </button>
                <span class="text-muted small">Clears the primers, the assembly, and the options.</span>
            </div>
        </form>

        <?php // ---------------------------------------------------------- results ?>
        <?php // Everything below describes the SEARCH THAT RAN. Clearing the form must
              // take it away too, or the page shows an answer to a question the form no
              // longer asks. ?>
        <div id="primerResults">
        <?php if (!empty($parse_result)): ?>

            <?php foreach (($parse_result['errors'] ?? []) as $err): ?>
                <div class="alert alert-danger py-2"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>

            <?php foreach (($parse_result['warnings'] ?? []) as $warn): ?>
                <div class="alert alert-warning py-2"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($warn) ?></div>
            <?php endforeach; ?>

            <?php foreach ($db_notes as $note): ?>
                <div class="alert alert-info py-2"><i class="fa fa-info-circle"></i> <?= htmlspecialchars($note) ?></div>
            <?php endforeach; ?>

        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <?php
            /** BLAST wraps subject ids as ref|ACC| — noise in a results table. */
            $clean_id = function ($s) {
                if (preg_match('/^(?:ref|gb|emb|dbj|lcl)\|([^|]+)\|?$/', $s, $m)) {
                    return $m[1];
                }
                return $s;
            };
            // Canonical rows for the download, so the file is built from the DATA
            // rather than re-parsed out of the rendered table -- a table formats
            // numbers with commas, and re-reading them back is how an export ends
            // up subtly wrong.
            $export = [];
            ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header text-white d-flex align-items-center tool-header">
                    <span class="fw-semibold">Results</span>
                    <?= help_modal_trigger('results-help', '', 'How to read these results') ?>
                    <span class="ms-auto small">
                        <?= htmlspecialchars(implode(' + ', array_map($db_label, array_keys($searched_dbs)))) ?>
                        · up to <?= (int)$max_mismatch ?> mismatch<?= $max_mismatch === 1 ? '' : 'es' ?>
                        · products to <?= number_format($max_product) ?> bp
                    </span>
                </div>
                <div class="card-body">

                <?php foreach ($results as $r): ?>
                    <?php
                    $pair   = $r['pair'];
                    $t_prod = isset($r['by_db']['transcript']) ? PrimerPairs::primaryProduct($r['by_db']['transcript']) : null;
                    $g_prod = isset($r['by_db']['genome'])     ? PrimerPairs::primaryProduct($r['by_db']['genome'])     : null;
                    $t_size = $t_prod['size'] ?? null;
                    $g_size = $g_prod['size'] ?? null;
                    // Specificity is judged against the INTENDED template only. Summing
                    // across databases counts one amplicon twice -- the cDNA product and
                    // the genomic product are the same fragment seen against two
                    // templates -- and a perfectly specific pair then reads "2 products".
                    $intended_db = ($search_mode === 'transcript' && isset($r['by_db']['transcript']))
                        ? 'transcript' : 'genome';

                    // ONE quality call, with the reasons it is not clean. Counts alone made
                    // the reader do this arithmetic themselves, and "specific — 1 product"
                    // beside "gDNA: 2 products" read as a contradiction.
                    // THE RULE (user, 2026-08-06): with traditional PCR a product under
                    // 2 kb carrying fewer than 3 mismatches is expected to amplify. Count
                    // those; one is clean, more than one is ambiguous. Everything else is
                    // still LISTED -- it is real -- it just does not count.
                    $amp = function (array $found) {
                        return array_values(array_filter($found['products'] ?? [],
                            ['PrimerPairs', 'isAmplifiable']));
                    };
                    $amp_intended = count($amp($r['by_db'][$intended_db] ?? []));
                    $amp_contam   = ($intended_db === 'transcript' && isset($r['by_db']['genome']))
                        ? count($amp($r['by_db']['genome'])) : 0;

                    $reasons = [];
                    if ($amp_intended > 1) {
                        $reasons[] = $amp_intended . ' products on your template could amplify';
                    }
                    if ($amp_contam > 1) {
                        $reasons[] = 'genomic DNA could give ' . $amp_contam . ' products, not just the one matching locus';
                    }
                    foreach ($r['by_db'] as $f) {
                        foreach ($amp($f) as $pr) {
                            if (!empty($pr['self_pairing'])) {
                                $reasons[] = 'one primer amplifies on its own (self-pairing)';
                                break 2;
                            }
                        }
                    }

                    $total = $amp_intended;

                    if ($total === 0) {
                        $verdict = ['none', 'bg-secondary', 'no product'];
                    } elseif (!$reasons) {
                        $verdict = ['clear', 'bg-success', 'clear'];
                    } else {
                        $verdict = ['ambiguous', 'bg-warning text-dark', 'ambiguous'];
                    }
                    ?>
                    <div class="pb-pair mb-4 pb-3 border-bottom">

                        <?php // ---- headline: name, sequences, and the verdict together ---- ?>
                        <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                            <h5 class="mb-0"><?= htmlspecialchars($pair['name']) ?></h5>
                            <span class="badge <?= $verdict[1] ?>"><?= htmlspecialchars($verdict[2]) ?></span>
                            <?= help_modal_trigger('results-help', '', 'What clear and ambiguous mean') ?>
                        </div>

                        <?php if ($total === 0): ?>
                            <?php // Say this even when there are reasons: otherwise "no product"
                                  // sits above a note about a self-pairing gDNA product and reads
                                  // as a contradiction. ?>
                            <div class="small text-muted mb-2">
                                Nothing amplifies on your template at these settings.
                            </div>
                        <?php endif; ?>
                        <?php if ($reasons): ?>
                            <ul class="small text-warning-emphasis mb-2 ps-3">
                                <?php foreach ($reasons as $why): ?>
                                    <li><?= htmlspecialchars($why) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>


                        <div class="small text-muted mb-3" style="font-family:monospace;">
                            F <?= htmlspecialchars($pair['forward']) ?> (<?= strlen($pair['forward']) ?>)
                            &nbsp;&nbsp;R <?= htmlspecialchars($pair['reverse']) ?> (<?= strlen($pair['reverse']) ?>)
                        </div>

                        <?php if ($t_size !== null && $g_size !== null): ?>
                            <?php // A note, not a verdict: the intron is INFERRED from the two
                                  // sizes differing, never observed directly, so the wording says
                                  // "likely" and shows the two numbers it was inferred from. ?>
                            <div class="small mb-3">
                                <?php if ($g_size > $t_size): ?>
                                    <i class="fa fa-circle-info text-success"></i>
                                    <span class="text-success-emphasis">Likely spans an intron</span>
                                    <span class="text-muted">— cDNA <?= number_format($t_size) ?> bp vs gDNA <?= number_format($g_size) ?> bp</span>
                                <?php else: ?>
                                    <i class="fa fa-circle-info text-warning"></i>
                                    <span class="text-warning-emphasis">No intron between the primers</span>
                                    <span class="text-muted">— cDNA and gDNA products are both <?= number_format($t_size) ?> bp</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($r['by_db'] as $db_key => $found): ?>
                            <div class="mb-3">
                                <div class="fw-semibold small text-muted mb-1">
                                    <?= htmlspecialchars($db_section_label($db_key)) ?>
                                </div>

                                <?php
                                // Split what the engine found into what this display limit shows
                                // and what it does not. The verdict above used AMPLIFIABLE_MAX and
                                // is unaffected by either.
                                $shown = array_values(array_filter($found['products'], function ($pr) use ($max_product) {
                                    return $pr['size'] <= $max_product;
                                }));
                                $hidden_by_display = count($found['products']) - count($shown);
                                $over_display = $hidden_by_display + (int)$found['over_max'];
                                ?>
                                <?php if (empty($shown)): ?>
                                    <div class="text-muted small mb-1">
                                        <?php if ($over_display > 0): ?>
                                            None under <?= number_format($max_product) ?> bp —
                                            <?= number_format($over_display) ?> larger.
                                        <?php else: ?>
                                            No products.
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-1">
                                        <tbody>
                                        <?php foreach ($shown as $prod): ?>
                                            <?php
                                            $subject = $clean_id($prod['subject']);
                                            $export[] = [
                                                $pair['name'], $pair['forward'], $pair['reverse'],
                                                $db_key === 'genome' ? 'genomic' : 'cDNA',
                                                $subject, $prod['start'], $prod['end'], $prod['size'],
                                                $prod['max_mismatch'],
                                                // Name WHICH primer: a downloaded row has no badge to
                                                // hover, so "self-pairing" alone loses the fact.
                                                $prod['self_pairing']
                                                    ? $prod['primers'][0] . ' self-pairing'
                                                    : 'forward+reverse',
                                            ];
                                            ?>
                                            <tr>
                                                <td class="fw-bold" style="width:7rem; white-space:nowrap;">
                                                    <?= number_format($prod['size']) ?> bp
                                                </td>
                                                <td style="font-family:monospace; font-size:0.85rem;">
                                                    <?= htmlspecialchars($subject) ?>:<?= number_format($prod['start']) ?>–<?= number_format($prod['end']) ?>
                                                </td>
                                                <td style="width:8rem;">
                                                    <span class="text-muted small"><?= (int)$prod['max_mismatch'] ?> mismatch<?= $prod['max_mismatch'] === 1 ? '' : 'es' ?></span>
                                                </td>
                                                <td style="width:11rem;">
                                                    <?php if (!empty($prod['self_pairing'])): ?>
                                                        <?php // Both ends of this product are the SAME oligo. A pair is
                                                              // implicit for normal products, so only this case is labelled,
                                                              // and compactly -- the loop icon carries the idea, the popover
                                                              // and the Results help carry the explanation. ?>
                                                        <span class="badge bg-danger" title="<?= htmlspecialchars($prod['primers'][0]) ?> primer at both ends">
                                                            <i class="fa fa-repeat"></i> <?= htmlspecialchars($prod['primers'][0]) ?> self-pairing
                                                        </span>
                                                        <?= field_help(
                                                            'Both ends of this product use the SAME primer, not one of each. '
                                                            . 'A single primer can match in two nearby places on opposite '
                                                            . 'strands, facing each other — and then it amplifies the fragment '
                                                            . 'between them on its own. It is a real source of unexpected '
                                                            . 'bands, and one you would not predict by thinking about the pair, '
                                                            . 'which is why it is called out here.',
                                                            'Same primer at both ends'
                                                        ) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end" style="width:8rem;">
                                                    <?php if ($db_key === 'genome' && !empty($result_organism) && !empty($result_assembly)): ?>
                                                        <?php
                                                        // The genomic product IS a locus, so it links straight into
                                                        // the browser -- where the gap between the cDNA and genomic
                                                        // sizes is visible as the introns it crosses.
                                                        $loc = $subject . ':' . $prod['start'] . '..' . $prod['end'];
                                                        $jb  = '/' . $site . '/jbrowse2.php?organism=' . urlencode($result_organism)
                                                             . '&assembly=' . urlencode($result_assembly)
                                                             . '&loc=' . urlencode($loc);
                                                        ?>
                                                        <a href="<?= htmlspecialchars($jb) ?>" target="_blank" rel="noopener"
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fa fa-dna"></i> Browser
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                <?php endif; ?>

                                <?php
                                // Report ONLY things that could be a product. Individual primer
                                // matches, partial alignments and over-mismatch hits are not
                                // products and cannot become one without changing a setting, so
                                // listing them buried the answer in noise. What remains is the one
                                // case where a real product exists but is not shown: it was larger
                                // than the size limit.
                                ?>
                                <?php if (!empty($over_display) && !empty($shown)): ?>
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        <?= number_format($over_display) ?> further product<?= $over_display === 1 ? '' : 's' ?>
                                        larger than <?= number_format($max_product) ?> bp — raise the size limit to see <?= $over_display === 1 ? 'it' : 'them' ?>.
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php // Download built from the canonical rows collected above. ?>
                <script type="application/json" id="primerExportData"><?= json_encode($export) ?></script>
                <button type="button" class="btn btn-outline-primary btn-sm" id="downloadPrimerResults">
                    <i class="fa fa-download"></i> Download results (TSV)
                </button>

                </div>
            </div>
        <?php endif; ?>

        </div><!-- /#primerResults -->

    <?php endif; ?>
</div>

<?php
/**
 * Accepted-formats help. Each card carries a worked example, because the rule
 * ("paired by _F/_R, otherwise in the order given") is far easier to see than
 * to read.
 */
$ex = function (string $sample): string {
    return '<pre class="mb-0 mt-2 p-2 bg-light border rounded small"'
         . ' style="font-family:monospace; white-space:pre;">'
         . htmlspecialchars($sample) . '</pre>';
};

echo help_modal(
    'primer-formats-help',
    'Accepted primer formats',
    [[
        'heading' => '',
        'cards'   => [
            [
                'label' => 'FASTA — one primer per record',
                'html'  => true,
                'text'  => 'Paired by name suffix: <code>_F</code>/<code>_R</code>, and also '
                         . '<code>_fwd</code>/<code>_rev</code>, <code>_left</code>/<code>_right</code>, '
                         . '<code>_forward</code>/<code>_reverse</code>. If the names carry no suffix, '
                         . 'records are paired in the order given — 1st with 2nd, 3rd with 4th.'
                         . $ex(">TP53_F\nGCTTGAGCTGTTATCTGTGC\n>TP53_R\nGCGGTGCTTCTGGGCTGAGT"),
            ],
            [
                'label' => 'FASTA — both primers in one record',
                'html'  => true,
                'text'  => 'Separate the two primers with a run of <strong>three or more</strong> '
                         . '<code>N</code>s. Shorter runs are left alone, so a single <code>N</code> '
                         . 'stays a degenerate base rather than splitting your primer.'
                         . $ex(">TP53\nGCTTGAGCTGTTATCTGTGCNNNNNNNNGCGGTGCTTCTGGGCTGAGT"),
            ],
            [
                'label' => 'TSV or CSV',
                'html'  => true,
                'text'  => 'Include a header row. Columns are found by <em>name</em>, never by position, '
                         . 'so their order does not matter. <code>forward</code> also accepts '
                         . '<code>left</code> or <code>fwd</code>; <code>reverse</code> also accepts '
                         . '<code>right</code> or <code>rev</code>.'
                         . $ex("name\tforward\treverse\nTP53\tGCTTGAGCTGTTATCTGTGC\tGCGGTGCTTCTGGGCTGAGT"),
            ],
            [
                'label' => 'Plain sequences',
                'html'  => true,
                'text'  => 'One sequence per line, no names. They are paired in the order given and '
                         . 'named for you. Position numbers and line wrapping are ignored, so a '
                         . 'sequence copied out of a document still works.'
                         . $ex("GCTTGAGCTGTTATCTGTGC\nGCGGTGCTTCTGGGCTGAGT"),
            ],
        ],
    ]],
    ['intro' => 'This applies to both the text box and an uploaded file. '
              . 'Anything that cannot be read is reported by name — nothing is dropped quietly.']
);

/**
 * How the source list works, including what the "!" means.
 *
 * The marker is tool-specific — here it means "no genome index" — so the
 * explanation belongs with the page, not inside the shared partial.
 */
echo help_modal(
    'source-list-help',
    'Choosing an assembly',
    [[
        'heading' => '',
        'cards'   => [
            [
                'label' => 'What is in the list',
                'html'  => true,
                'text'  => 'Every assembly you have access to, one row per gene set. The badges '
                         . 'on each row read left to right as <strong>group</strong>, '
                         . '<strong>organism</strong>, <strong>assembly</strong>, and '
                         . '<strong>gene set</strong>.',
            ],
            [
                'label' => 'Filtering',
                'html'  => true,
                'text'  => 'The box above the list narrows it as you type, matching against all '
                         . 'four of those at once — so a group name, a species, an accession or a '
                         . 'gene set name all work. <strong>Clear Filters</strong> restores the '
                         . 'full list.',
            ],
            [
                // 'color' is the API's badge form, for exactly this case: the card title
                // renders as the same badge the page shows, so the eye matches the help to
                // the thing it just looked at. Putting markup in 'label' does NOT work —
                // labels are always escaped, and the tag showed as literal text.
                'label' => '!',
                'color' => 'warning',
                'html'  => true,
                'text'  => '<strong>Not usable here.</strong> This tool needs the assembly\'s '
                         . 'genome to say where a product lands, and some assemblies have none — '
                         . 'they are transcriptome assemblies, so that is correct, not missing '
                         . 'data. Most are still usable with <strong>PCR input</strong> set to '
                         . '<strong>cDNA</strong>. Hover a marker for its own reason.',
            ],
        ],
    ]],
    ['intro' => 'One row per gene set, filtered live as you type.']
);

/**
 * How to read the results. Built from the questions actually asked while using
 * the tool, in the order they came up — which is why it leads with "what counts
 * as a product" rather than with a feature tour.
 */
echo help_modal(
    'results-help',
    'Reading the results',
    [[
        'heading' => '',
        'cards'   => [
            [
                'label' => 'clear',
                'color' => 'success',
                'html'  => true,
                'text'  => 'Exactly one product could amplify — one on your template, and nothing else. '
                         . 'If your input is cDNA, genomic DNA gives only the one matching locus, which is '
                         . 'expected: that is the same place with its introns included.',
            ],
            [
                'label' => 'ambiguous',
                'color' => 'warning',
                'html'  => true,
                'text'  => 'More than one product could amplify. The reasons are listed under the name: '
                         . 'several products on your template, extra genomic products, or a self-pairing '
                         . 'primer. Ambiguous is not unusable — read the reasons and decide.',
            ],
            [
                'label' => 'no product',
                'color' => 'secondary',
                'html'  => true,
                'text'  => 'Nothing amplifies on your template <em>at these settings</em>. That is not '
                         . 'the same as "these primers do not work" — a primer with more mismatches than '
                         . 'the limit is invisible, so raising <strong>Mismatches allowed</strong> or '
                         . '<strong>Largest product to report</strong> may reveal it.',
            ],
            [
                'label' => 'Only what would really amplify counts',
                'accent' => true,
                'html'  => true,
                'text'  => 'A product is expected to amplify when it is <strong>under 2 kb</strong> and '
                         . 'neither primer is blocked: fewer than 6 mismatches overall, and fewer than 2 '
                         . 'within the <strong>last 5 bases of the 3′ end</strong>. Only those count toward '
                         . 'the grade — one is clear, more than one is ambiguous. Anything outside is still '
                         . 'listed because it is real, it simply will not amplify.',
            ],
            [
                'label' => 'The size limit does not change the grade',
                'accent' => true,
                'html'  => true,
                'text'  => '<strong>Largest product to report</strong> controls what is LISTED, nothing '
                         . 'more. The grade is always computed over amplifiable products, so lowering the '
                         . 'limit hides rows without quietly turning an ambiguous pair clear. A display '
                         . 'control should never change a scientific conclusion.',
            ],
            [
                'label' => 'What counts as a product',
                'html'  => true,
                'text'  => 'Both primers must land on the <strong>same sequence</strong>, on '
                         . '<strong>opposite strands</strong>, <strong>facing each other</strong>, and close '
                         . 'enough to amplify between them. A primer that matches somewhere on its own '
                         . 'makes nothing, so only products are listed.',
            ],
            [
                'label' => 'Mismatches allowed',
                'html'  => true,
                'text'  => 'A match is kept if it has this many mismatches <em>or fewer</em>. The default of '
                         . '1 keeps single-mismatch sites because those can still prime. <strong>A primer '
                         . 'with more mismatches than the limit is simply not there</strong> — so a pair can '
                         . 'show no product until you raise it.',
                'accent' => true,
            ],
            [
                'label' => 'Worked example',
                'html'  => true,
                'text'  => 'A pair whose reverse primer carries 2 mismatches against the target: at a limit '
                         . 'of <strong>1</strong> it reports <em>no product</em>; at <strong>2</strong> the '
                         . 'same pair reports its real 494 bp product, marked "2 mismatches". Nothing changed '
                         . 'but the setting — the product was always there, below the threshold.',
                'accent' => true,
            ],
            [
                'label' => 'Why 3′ position matters more than count',
                'accent' => true,
                'html'  => true,
                'text'  => 'The polymerase extends from the 3′ end, so a mismatch in the last few bases '
                         . 'all but stops extension while the same mismatch near the 5′ end is tolerated. '
                         . 'Two primers with an identical mismatch <em>count</em> can therefore behave '
                         . 'completely differently. These thresholds follow NCBI Primer-BLAST, which '
                         . 'dismisses an unintended target when a primer carries 2 or more mismatches in '
                         . 'its last 5 bases. The number in the table is the worse of the two primers.',
            ],
            [
                'label' => 'gDNA: N products',
                'html'  => true,
                'text'  => 'Appears when genomic DNA would give <strong>more than one</strong> product. '
                         . 'Exactly one is expected — it is the same locus your cDNA product came from, '
                         . 'just with the introns included. More than one means the primers also pair '
                         . 'somewhere else in the genome, which the cDNA result alone cannot show you.',
            ],
            [
                'label' => 'Self-pairing',
                'html'  => true,
                'text'  => 'Marked <span class="badge bg-danger"><i class="fa fa-repeat"></i> reverse self-pairing</span>. '
                         . 'One primer can match twice on opposite strands, facing itself, and amplify the '
                         . 'fragment between — so both ends of that product are the same oligo, and the other '
                         . 'primer is not involved at all. A real source of unexpected bands, and one you '
                         . 'would never predict by thinking about the pair. The badge names the primer '
                         . 'responsible, and so does the TSV download.',
            ],
            [
                'label' => 'Likely spans an intron',
                'html'  => true,
                'text'  => 'Shown when the gDNA product is larger than the cDNA one — the extra length is '
                         . 'intron. It is <em>inferred</em> from the two sizes, not observed, hence '
                         . '"likely". Equal sizes mean both primers sit in one exon, and the pair cannot '
                         . 'tell cDNA from genomic DNA.',
            ],
            [
                'label' => 'Products over the size limit',
                'html'  => true,
                'text'  => 'Counted but not listed, because very long products do not amplify under normal '
                         . 'PCR. They are still real pairings, so the count is shown rather than hidden — '
                         . 'raise <strong>Largest product to report</strong> to see them.',
            ],
        ],
    ]],
    ['intro' => 'Only products are reported — combinations that could actually amplify.']
);

/**
 * What gets searched, expressed as the thing the user actually chooses: the
 * template going into their reaction. Framing it as "genome vs transcriptome"
 * made "the genome is always searched" read as an odd caveat; framing it as the
 * PCR input makes it the obvious consequence it is.
 */
echo help_modal(
    'pcr-input-help',
    'PCR input — and what each one searches',
    [[
        'heading' => '',
        'cards'   => [
            [
                'label' => 'Genomic DNA',
                'html'  => true,
                'text'  => 'Searches the <strong>genome</strong> only, because that is your template. '
                         . 'Products are reported at their genomic size — so if the pair happens to sit '
                         . 'either side of an intron, the intron is part of the product and it will be '
                         . 'larger than you might expect from the mRNA sequence.',
            ],
            [
                'label' => 'cDNA',
                'html'  => true,
                'text'  => 'Searches the <strong>transcriptome and the genome</strong> — both, always. '
                         . 'The transcriptome gives the product you actually want, at its true cDNA size. '
                         . 'The genome is searched as well because <em>any reaction on cDNA can carry '
                         . 'over genomic DNA</em> — not just RT-PCR. It shows what that genomic DNA '
                         . 'would amplify, and how big it would be.',
            ],
            [
                'label' => 'Why cDNA still searches the genome',
                'html'  => true,
                'text'  => 'A transcript-only check cannot see genomic sites at all, so it would report a '
                         . 'pair as clean while gDNA in the sample amplified something else entirely. '
                         . 'Searching both is also what lets the tool tell you whether your pair '
                         . '<strong>spans an intron</strong>: if the genomic product is bigger than the '
                         . 'cDNA product, it does — and genomic DNA in the sample gives a different-sized '
                         . 'band, or none at all.',
            ],
        ],
    ]],
    ['intro' => 'Choose the template that goes into your reaction, and the right databases are '
              . 'searched for you.']
);
?>
