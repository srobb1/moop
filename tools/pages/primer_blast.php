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
 * Label for a result section — says what the products MEAN, which depends on the
 * PCR input.
 *
 * The same genome search answers two different questions. With genomic DNA in the
 * tube it lists the products you are trying to make; with cDNA in the tube it
 * lists what a genomic carry-over would amplify instead. Labelling both "Genome"
 * leaves the reader to work that out, and gets it wrong in exactly the case that
 * matters — a contaminating band read as the intended one.
 */
$db_section_label = function ($key) use ($search_mode) {
    if ($key === 'transcript') {
        return 'From cDNA — your intended product';
    }
    return $search_mode === 'transcript'
        ? 'From contaminating genomic DNA'
        : 'From genomic DNA — your intended product';
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
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
                              <label class="form-label mb-2">
                                <strong>PCR input</strong>
                                <?= help_modal_trigger('pcr-input-help', '', 'What each PCR input searches') ?>
                              </label>
                              <div class="form-check">
                                <input class="form-check-input" type="radio" name="search_mode" id="mode_genome"
                                       value="genome" <?= $search_mode === 'genome' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mode_genome">Genomic DNA</label>
                              </div>
                              <div class="form-check">
                                <input class="form-check-input" type="radio" name="search_mode" id="mode_transcript"
                                       value="transcript" <?= $search_mode === 'transcript' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mode_transcript">cDNA</label>
                              </div>
                              <div class="form-text mt-2">What you put in the reaction.</div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="card h-100 border">
                            <div class="card-body py-3">
                              <label for="max_mismatch" class="form-label mb-2">
                                <strong>Mismatches allowed</strong>
                                <?= field_help(
                                    'Counted per primer, per site. Raise it to see more potential products, '
                                    . 'including ones that would only prime weakly. Lower it to see just the '
                                    . 'clean matches.',
                                    'Mismatches allowed'
                                ) ?>
                              </label>
                              <input type="number" class="form-control" id="max_mismatch" name="max_mismatch"
                                     min="0" max="5" value="<?= (int)$max_mismatch ?>">
                              <div class="form-text mt-2">Per primer, per site.</div>
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
                              <div class="form-text mt-2">Bigger ones are counted, not hidden.</div>
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
            <div class="card shadow-sm mb-4">
                <div class="card-header text-white d-flex align-items-center tool-header">
                    <span class="fw-semibold">Results</span>
                    <span class="ms-auto small">
                        Searched: <?= htmlspecialchars(implode(', ', array_map($db_label, array_keys($searched_dbs)))) ?>
                        · up to <?= (int)$max_mismatch ?> mismatch<?= $max_mismatch === 1 ? '' : 'es' ?>
                    </span>
                </div>
                <div class="card-body">

                <?php foreach ($results as $r): ?>
                    <?php $pair = $r['pair']; ?>
                    <div class="mb-4">
                        <h5 class="mb-1"><?= htmlspecialchars($pair['name']) ?></h5>
                        <div class="small text-muted mb-2" style="font-family:monospace;">
                            F <?= htmlspecialchars($pair['forward']) ?> (<?= strlen($pair['forward']) ?> nt)
                            &nbsp;·&nbsp;
                            R <?= htmlspecialchars($pair['reverse']) ?> (<?= strlen($pair['reverse']) ?> nt)
                        </div>

                        <?php foreach ($r['by_db'] as $db_key => $found): ?>
                            <div class="mb-3 ps-3 border-start">
                                <div class="mb-1">
                                    <strong><?= htmlspecialchars($db_section_label($db_key)) ?>:</strong>
                                    <?php if ($found['product_count'] === 0 && $found['over_max'] > 0): ?>
                                        <?php // "no products" would be a lie here: products DID form, they were
                                              // just larger than the reporting limit. Saying so points at the
                                              // control that would reveal them. ?>
                                        <span class="badge bg-secondary">none under <?= number_format($max_product) ?> bp</span>
                                        <span class="small ms-1">— raise the size limit to see the
                                        <?= number_format($found['over_max']) ?> larger one<?= $found['over_max'] === 1 ? '' : 's' ?>.</span>
                                    <?php elseif ($found['product_count'] === 0): ?>
                                        <span class="badge bg-secondary">no products</span>
                                    <?php elseif ($found['product_count'] === 1): ?>
                                        <span class="badge bg-success">1 product</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= (int)$found['product_count'] ?> products</span>
                                    <?php endif; ?>

                                    <span class="text-muted small ms-2">
                                        primer hits: forward <?= (int)$found['primer_hits']['forward'] ?>,
                                        reverse <?= (int)$found['primer_hits']['reverse'] ?>
                                    </span>
                                </div>

                                <?php if (!empty($found['carried_by'])): ?>
                                    <div class="alert alert-info py-1 px-2 small mb-2">
                                        <i class="fa fa-info-circle"></i>
                                        Specificity here rests on the <strong><?= htmlspecialchars($found['carried_by']) ?></strong>
                                        primer alone — the other one matches in many places. Keep it if you revise this pair.
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($found['products'])): ?>
                                    <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-1">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product size</th>
                                                <th>Location</th>
                                                <th>Mismatches</th>
                                                <th>Formed by</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($found['products'] as $prod): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= number_format($prod['size']) ?> bp</td>
                                                <td style="font-family:monospace; font-size:0.85rem;">
                                                    <?= htmlspecialchars($prod['subject']) ?>:<?= number_format($prod['start']) ?>–<?= number_format($prod['end']) ?>
                                                </td>
                                                <td><?= (int)$prod['max_mismatch'] ?></td>
                                                <td>
                                                    <?php if (!empty($prod['self_pairing'])): ?>
                                                        <span class="badge bg-danger">the <?= htmlspecialchars($prod['primers'][0]) ?> primer with itself</span>
                                                    <?php else: ?>
                                                        forward + reverse
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                <?php endif; ?>

                                <div class="small text-muted">
                                    <?php if ($found['over_max'] > 0): ?>
                                        <?= number_format($found['over_max']) ?> further combination<?= $found['over_max'] === 1 ? '' : 's' ?>
                                        exceeded the <?= number_format($max_product) ?> bp limit.
                                    <?php endif; ?>
                                    <?php if (!empty($found['below_floor'])): ?>
                                        <?= number_format($found['below_floor']) ?> partial alignments were too short to be priming sites.
                                    <?php endif; ?>
                                    <?php if (!empty($found['over_mismatch'])): ?>
                                        <?= number_format($found['over_mismatch']) ?> alignments exceeded the mismatch limit.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php
                        // Genomic and cDNA sizes differ exactly when the primers sit in
                        // different exons -- the genomic-contamination question, answered
                        // without any exon coordinates.
                        $g_prod = isset($r['by_db']['genome'])
                            ? PrimerPairs::primaryProduct($r['by_db']['genome']) : null;
                        $t_prod = isset($r['by_db']['transcript'])
                            ? PrimerPairs::primaryProduct($r['by_db']['transcript']) : null;
                        $g = $g_prod['size'] ?? null;
                        $t = $t_prod['size'] ?? null;
                        ?>
                        <?php if ($g !== null && $t !== null): ?>
                            <div class="alert <?= $g > $t ? 'alert-success' : 'alert-warning' ?> py-2 small">
                                <?php if ($g > $t): ?>
                                    <i class="fa fa-check-circle"></i>
                                    <strong>Spans at least one intron.</strong>
                                    Genomic product <?= number_format($g) ?> bp vs cDNA <?= number_format($t) ?> bp, so
                                    contaminating genomic DNA would give a larger product — or none at all.
                                <?php else: ?>
                                    <i class="fa fa-exclamation-triangle"></i>
                                    <strong>Both primers appear to sit within one exon.</strong>
                                    Genomic and cDNA products are the same size (<?= number_format($t) ?> bp), so this pair
                                    cannot distinguish cDNA from contaminating genomic DNA.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <hr>
                <?php endforeach; ?>

                </div>
            </div>
        <?php endif; ?>

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
                         . 'over genomic DNA</em> — not just RT-PCR. It shows what that contamination '
                         . 'would amplify, and how big it would be.',
            ],
            [
                'label' => 'Why cDNA still searches the genome',
                'html'  => true,
                'text'  => 'A transcript-only check cannot see genomic sites at all, so it would report a '
                         . 'pair as clean while gDNA in the sample amplified something else entirely. '
                         . 'Searching both is also what lets the tool tell you whether your pair '
                         . '<strong>spans an intron</strong>: if the genomic product is bigger than the '
                         . 'cDNA product, it does — and gDNA contamination will give a different-sized '
                         . 'band, or none at all.',
            ],
        ],
    ]],
    ['intro' => 'Choose the template that goes into your reaction, and the right databases are '
              . 'searched for you.']
);
?>
