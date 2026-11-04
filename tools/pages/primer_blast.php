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

/**
 * BLAST wraps subject ids as ref|ACC| — noise in a table, and FATAL in a JBrowse
 * track: refName must be the reference sequence's real name or the track silently
 * renders nothing at all. One definition, in PrimerBlast, because the controller
 * needs the same cleaning to look transcripts up in the exon index.
 */
$clean_id = function ($s) {
    return PrimerBlast::cleanSubjectId($s);
};

/**
 * Colours for the primer binding sites drawn in JBrowse.
 *
 * Okabe-Ito blue and vermillion: distinguishable under the common forms of colour
 * blindness, which a red/green pair would not be.
 */
const PRIMER_COLOR_FORWARD = '#0072B2';
const PRIMER_COLOR_REVERSE = '#D55E00';
const PRIMER_COLOR_PRODUCT = '#999999';

/**
 * A JBrowse2 session track drawing one predicted product: the amplicon as the
 * parent feature, with each primer's binding site as a coloured subfeature.
 *
 * Same mechanism as the BLAST hit linkout (lib/blast_results_visualizer.php) —
 * a FromConfigAdapter track passed in the URL — with an explicit renderer added,
 * because JBrowse's default colouring cannot tell the two primers apart.
 *
 * Two kinds of product arrive here and both end up as genomic coordinates:
 *   - a GENOMIC product, whose coordinates are already genomic;
 *   - a cDNA product carrying $prod['genomic'], mapped back through the exon
 *     structure by ExonMap. A primer crossing an exon junction then has TWO
 *     blocks, and is drawn as two boxes either side of the intron — the picture
 *     that shows why such a primer cannot amplify contaminating gDNA.
 */
$primer_session_track = function (array $prod, $pair_name, $jb2_assembly_id) use ($clean_id) {
    $mapped = $prod['genomic'] ?? null;

    $ref = $mapped ? $mapped['chr'] : $clean_id($prod['subject']);
    $track_id = 'primer_' . substr(md5($pair_name . $prod['subject'] . $prod['start']), 0, 10);

    // Normalise both cases to the same shape: one entry per primer, each with a
    // list of genomic blocks. A genomic hit is simply a primer with one block.
    $parts = [];
    if ($mapped) {
        $parts = $mapped['hits'];
    } else {
        foreach ($prod['hits'] as $hit) {
            $parts[] = [
                'primer'   => $hit['primer'],
                'mismatch' => $hit['mismatch'],
                'strand'   => $hit['strand'],
                'blocks'   => [[$hit['start'], $hit['end']]],
                'spliced'  => false,
            ];
        }
    }

    $subfeatures = [];
    foreach ($parts as $n => $part) {
        $is_forward = ($part['primer'] === 'forward');
        $label = ($is_forward ? 'forward' : 'reverse') . ' primer'
               . ($part['mismatch'] > 0 ? ' (' . $part['mismatch'] . ' mismatch'
                  . ($part['mismatch'] === 1 ? '' : 'es') . ')' : '')
               // Both halves carry the same label, so hovering either one says
               // the true thing. Unlabelled, a split primer is indistinguishable
               // from two separate primers — the opposite of what it shows.
               . ($part['spliced'] ? ' — spans an exon junction' : '');

        foreach ($part['blocks'] as $b => list($start, $end)) {
            $subfeatures[] = [
                'uniqueId' => $track_id . '_p' . $n . '_' . $b,
                'refName'  => $ref,
                'start'    => $start - 1,          // JBrowse features are 0-based
                'end'      => $end,
                'strand'   => $part['strand'] === '-' ? -1 : 1,
                'name'     => $label,
                'type'     => 'primer_binding_site',
                'color'    => $is_forward ? PRIMER_COLOR_FORWARD : PRIMER_COLOR_REVERSE,
            ];
        }
    }

    list($p_start, $p_end) = $mapped ? $mapped['span'] : [$prod['start'], $prod['end']];

    $features = [[
        'uniqueId'    => $track_id . '_product',
        'refName'     => $ref,
        'start'       => $p_start - 1,
        'end'         => $p_end,
        'name'        => $pair_name . ' — ' . number_format($prod['size']) . ' bp '
                       . ($mapped ? 'cDNA product' : 'product'),
        'type'        => 'PCR_product',
        'color'       => PRIMER_COLOR_PRODUCT,
        'subfeatures' => $subfeatures,
    ]];

    return [
        [
            'type'          => 'FeatureTrack',
            'trackId'       => $track_id,
            'name'          => 'Primer BLAST: ' . $pair_name,
            'assemblyNames' => [$jb2_assembly_id],
            'adapter'       => ['type' => 'FromConfigAdapter', 'features' => $features],
            // Without this the two primers render identically and the picture
            // loses the one thing it is being drawn to show. The parent has
            // subfeatures, so JBrowse uses its segments glyph: a box per primer
            // joined by a straight connector across the intervening sequence.
            'displays'      => [[
                'type'      => 'LinearBasicDisplay',
                'displayId' => $track_id . '-LinearBasicDisplay',
                'renderer'  => [
                    'type'   => 'SvgFeatureRenderer',
                    'color1' => "jexl:get(feature,'color') || '" . PRIMER_COLOR_PRODUCT . "'",
                ],
            ]],
        ],
    ];
};

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
          <?= page_purpose_info('Check primer pairs against a genome and transcriptome: how many products they would amplify, where those land, and how big each one is.') ?>
      </div>
    </div>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong><?= t('No accessible assemblies found.') ?></strong>
            <p class="mb-0"><?= t('You do not have access to any organism assemblies, or the data directory is misconfigured.') ?></p>
        </div>
    <?php else: ?>

        <?php if (!empty($search_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="primerSearchError">
                <strong><i class="fa fa-exclamation-circle"></i> <?= t('Error:') ?></strong> <?= htmlspecialchars($search_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('Close') ?>"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="primerBlastForm" enctype="multipart/form-data"
              data-default-mismatch="<?= (int)PrimerBlast::DISPLAY_MISMATCH_DEFAULT ?>"
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
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Enter your primers') ?></span>
                </div>
                <div class="card-body py-3">
                    <?php // One line for the whole step: it governs BOTH the textarea and the
                          // upload below it, so it sits above both rather than between them. ?>
                    <p class="mb-2">
                        <?= t('Paste your primers below, or upload a file.') . "\n" ?>
                        <span class="text-muted"><?= t('Four formats are accepted and detected automatically.') ?></span>
                        <?= help_modal_trigger('primer-formats-help', 'Accepted formats', 'Accepted primer input formats') ?>
                    </p>

                    <textarea
                        name="primers"
                        id="primers"
                        rows="8"
                        class="form-control fasta-textarea-ids"
                        placeholder="&gt;TP53_F&#10;GCTTGAGCTGTTATCTGTGC&#10;&gt;TP53_R&#10;GCGGTGCTTCTGGGCTGAGT"
                    ><?= htmlspecialchars($primer_text) ?></textarea>

                    <label for="primer_file" class="form-label mt-3"><?= t('…or upload a file') ?></label>
                    <input type="file" class="form-control" id="primer_file" name="primer_file"
                           accept=".fa,.fasta,.fas,.txt,.tsv,.csv">
                    <div class="form-text">
                        <?= t("The file's contents are loaded into the box above, where you can edit them before searching.") ?> <?= t('Max 1 MB.') . "\n" ?>
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
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Select the assembly to check against') ?></span>
                </div>
                <div class="card-body py-3">
                    <?php
                    $clear_filter_function = 'clearPrimerSourceFilters';
                    $on_change_function    = 'updatePrimerSelection';
                    $source_list_help_id   = 'source-list-help';
                    include __DIR__ . '/../../includes/source-list.php';
                    ?>
                    <div class="mt-3 p-3 bg-light border rounded">
                        <strong><?= t('Currently selected:') ?></strong>
                        <div id="primerCurrentSelection" style="margin-top:8px; font-size:14px;">
                            <span class="text-muted"><?= t('None selected') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Options -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">3</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Search options') ?></span>
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
                                <strong><?= t('PCR input') ?></strong>
                                <?= help_modal_trigger('pcr-input-help', '', 'What each PCR input searches') ?>
                              </label>
                              <?php // Inline: only two mutually exclusive choices with short
                                    // labels, so stacking them wasted the card's height and made
                                    // this card taller than its two siblings for no reason.
                                    //
                                    // Boxed in .form-control so this card reads as a set with its
                                    // two siblings, which each hold a bordered input. Bare radios
                                    // beside two framed fields made the row look unfinished.
                                    //
                                    // .form-control ON A DIV is deliberate: it is the SAME class
                                    // the sibling inputs use, so border, radius, padding and
                                    // background are identical by construction rather than
                                    // matched by eye and left to drift. Nothing in css/ overrides
                                    // .form-control, so this is Bootstrap's rule set unchanged.
                                    // The radios keep their own semantics; only the frame is
                                    // borrowed. ?>
                              <div class="form-control">
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="search_mode" id="mode_genome"
                                         value="genome" <?= $search_mode === 'genome' ? 'checked' : '' ?>>
                                  <label class="form-check-label" for="mode_genome"><?= t('Genomic DNA') ?></label>
                                </div>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="search_mode" id="mode_transcript"
                                         value="transcript" <?= $search_mode === 'transcript' ? 'checked' : '' ?>>
                                  <label class="form-check-label" for="mode_transcript"><?= t('cDNA') ?></label>
                                </div>
                              </div>
                              <div class="form-text text-warning-emphasis mt-1 d-none" id="pcrInputNote"></div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="card h-100 border">
                            <div class="card-body py-3">
                              <label for="max_mismatch" class="form-label mb-2">
                                <strong><?= t('Mismatches listed') ?></strong>
                                <?= field_help(
                                    'The search keeps every site with up to ' . PrimerBlast::SEARCH_MAX_MISMATCH . ' mismatches per '
                                    . 'primer, because a single mismatch, or a few toward the 5′ end, can still '
                                    . 'amplify — and the verdict is judged on all of them. This box only changes '
                                    . 'which products are displayed in the results: those with this many '
                                    . 'mismatches or fewer, counted over the whole primer.',
                                    'Mismatches listed'
                                ) ?>
                              </label>
                              <input type="number" class="form-control" id="max_mismatch" name="max_mismatch"
                                     min="0" max="<?= (int)PrimerBlast::SEARCH_MAX_MISMATCH ?>" value="<?= (int)$max_mismatch ?>">
                            </div>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="card h-100 border">
                            <div class="card-body py-3">
                              <label for="max_product" class="form-label mb-2">
                                <strong><?= t('Largest product to report') ?></strong>
                                <?= field_help(
                                    'Products bigger than this are counted but not listed. Standard PCR '
                                    . 'is dependable for products under ' . PrimerPairs::amplifiableKb() . ' kb; longer ones often amplify poorly '
                                    . 'or not at all without a long-range polymerase. The genomic product of '
                                    . 'a pair that spans an intron includes the intron and is much larger, so '
                                    . 'the default is set high enough to keep those visible. Lower it to see '
                                    . 'only what would realistically amplify.',
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
                    <i class="fa fa-search"></i> <?= t('Check primers') . "\n" ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="resetPrimerForm">
                    <i class="fa fa-undo"></i> <?= t('Clear form') . "\n" ?>
                </button>
                <span class="text-muted small"><?= t('Clears the primers, the assembly, and the options.') ?></span>
            </div>
        </form>

        <?php // ---------------------------------------------------------- results ?>
        <?php // Everything below describes the SEARCH THAT RAN. Clearing the form must
              // take it away too, or the page shows an answer to a question the form no
              // longer asks. ?>
        <div id="primerResults" data-ran="<?= !empty($parse_result) ? '1' : '' ?>">
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
            // Canonical rows for the download, so the file is built from the DATA
            // rather than re-parsed out of the rendered table -- a table formats
            // numbers with commas, and re-reading them back is how an export ends
            // up subtly wrong.
            $export = [];
            ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header text-white d-flex align-items-center tool-header">
                    <span class="fw-semibold"><?= t('Results') ?></span>
                    <?= help_modal_trigger('results-help', '', 'How to read these results') ?>
                    <span class="ms-auto small">
                        <?= htmlspecialchars(implode(' + ', array_map($db_label, array_keys($searched_dbs)))) ?>
                        · <?= ($max_mismatch === 1 ? t('listing up to %1$d mismatch and %2$s bp', $max_mismatch, number_format($max_product))
                                                   : t('listing up to %1$d mismatches and %2$s bp', $max_mismatch, number_format($max_product))) . "\n" ?>
                    </span>
                </div>
                <div class="card-body">

                <?php foreach ($results as $r): ?>
                    <?php
                    $pair   = $r['pair'];
                    $t_prod = isset($r['by_db']['transcript']) ? PrimerPairs::primaryProduct($r['by_db']['transcript']) : null;
                    // The genomic product is the REAL locus — a site where both primers would
                    // prime (PrimerPairs::realLocus()). The closest-looking site is kept apart
                    // ($g_near): for a junction primer it is a half-match that never primes,
                    // and treating it as the product reported "gDNA 7,906 bp" above a pair
                    // with no genomic product at all (2026-10-29).
                    $g_prod = isset($r['by_db']['genome'])     ? PrimerPairs::realLocus($r['by_db']['genome'])      : null;
                    $g_near = isset($r['by_db']['genome'])     ? PrimerPairs::primaryProduct($r['by_db']['genome']) : null;
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
                    // THE RULE: a product counts when PrimerPairs::isAmplifiable() says it
                    // would amplify -- under AMPLIFIABLE_MAX (3 kb since 2026-10-13), fewer than
                    // 6 mismatches per primer, and fewer than 2 in the last 5 bases at the 3'
                    // end (NCBI Primer-BLAST's thresholds). The search keeps every site up to
                    // PrimerBlast::SEARCH_MAX_MISMATCH, so this sees all of them whatever the
                    // "Mismatches listed" box says. One is clean, more than one is ambiguous.
                    // Everything else is still LISTED -- it is real -- it just does not count.
                    $amp = function (array $found) {
                        return array_values(array_filter($found['products'] ?? [],
                            ['PrimerPairs', 'isAmplifiable']));
                    };
                    $amp_intended = count($amp($r['by_db'][$intended_db] ?? []));
                    $amp_contam   = ($intended_db === 'transcript' && isset($r['by_db']['genome']))
                        ? count($amp($r['by_db']['genome'])) : 0;

                    // Specificity is about GENES. On cDNA each product carries its gene
                    // (tools/primer_blast.php); isoforms of one gene are one target, not
                    // several. When any product's gene is unknown, count products as before.
                    $amp_products = $amp($r['by_db'][$intended_db] ?? []);
                    $amp_genes = [];
                    foreach ($amp_products as $pr) {
                        if (!isset($pr['gene'])) { $amp_genes = null; break; }
                        $amp_genes[$pr['gene']['id']] = $pr['gene'];
                    }
                    $gene_word = fn(array $g) => $g['name'] !== '' && $g['name'] !== $g['id'] ? $g['name'] . ' (' . $g['id'] . ')' : $g['id'];
                    $isoforms_note = null;   // one gene, several transcripts: say so, it is not a warning
                    $reasons = [];
                    if ($amp_genes !== null && $amp_intended > 1) {
                        if (count($amp_genes) > 1) {
                            $reasons[] = t_plain('%1$d different genes could amplify: %2$s', count($amp_genes),
                                         implode(', ', array_map($gene_word, array_slice(array_values($amp_genes), 0, 4)))
                                       . (count($amp_genes) > 4 ? ', …' : ''));
                        } else {
                            $sizes = array_values(array_unique(array_map(fn($pr) => (int)$pr['size'], $amp_products)));
                            sort($sizes);
                            $isoforms_note = [
                                'gene'  => $gene_word(reset($amp_genes)),
                                'n'     => $amp_intended,
                                'tx'    => array_map(fn($pr) => PrimerBlast::cleanSubjectId($pr['subject']), $amp_products),
                                'sizes' => $sizes,
                            ];
                        }
                    } elseif ($amp_intended > 1) {
                        $reasons[] = t_plain('%d products on your template could amplify', $amp_intended);
                    }
                    if ($amp_contam > 1) {
                        $reasons[] = t_plain('genomic DNA could give %d products, not just the one matching locus', $amp_contam);
                    }
                    foreach ($r['by_db'] as $f) {
                        foreach ($amp($f) as $pr) {
                            if (!empty($pr['self_pairing'])) {
                                $reasons[] = t_plain('one primer amplifies on its own (self-pairing)');
                                break 2;
                            }
                        }
                    }

                    $total = $amp_intended;

                    // Products on the template that would amplify but for their LENGTH: they
                    // are listed in the table (anything up to "Largest product to report"),
                    // yet the verdict counts only those under AMPLIFIABLE_MAX. Without naming that, the
                    // badge said "no product" above a perfect 3,733 bp genomic product
                    // (Congeria COKUS1KC_0031865-RA, pair 5 across two introns).
                    $too_long = array_values(array_filter($r['by_db'][$intended_db]['products'] ?? [],
                        fn($pr) => $pr['size'] > PrimerPairs::AMPLIFIABLE_MAX
                                && $pr['size'] <= $max_product
                                && PrimerPairs::isAmplifiable(array_merge($pr, ['size' => 0]))));
                    $kb = PrimerPairs::amplifiableKb();

                    if ($total === 0 && $too_long) {
                        $verdict = ['none', 'bg-secondary', t_plain('no product under %s kb', $kb)];
                    } elseif ($total === 0) {
                        $verdict = ['none', 'bg-secondary', t_plain('no product')];
                    } elseif (!$reasons) {
                        $verdict = ['specific', 'bg-success', t_plain('specific')];
                    } else {
                        $verdict = ['nonspecific', 'bg-warning text-dark', t_plain('non-specific')];
                    }
                    ?>
                    <div class="pb-pair mb-4 pb-3 border-bottom">

                        <?php // ---- headline: name, sequences, and the verdict together ---- ?>
                        <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                            <h5 class="mb-0"><?= htmlspecialchars($pair['name']) ?></h5>
                            <span class="badge <?= $verdict[1] ?>"><?= htmlspecialchars($verdict[2]) ?></span>
                            <?= help_modal_trigger('results-help', '', 'What specific and non-specific mean') ?>
                        </div>

                        <?php if ($total === 0): ?>
                            <?php // Say this even when there are reasons: otherwise "no product"
                                  // sits above a note about a self-pairing gDNA product and reads
                                  // as a contradiction. ?>
                            <div class="small text-muted mb-2">
                                <?php if ($too_long):
                                    $sizes = array_map(fn($pr) => number_format($pr['size']) . ' bp', $too_long);
                                    $n     = count($too_long); ?>
                                    <?= $n === 1 ? t('The product below is %s.', $sizes[0])
                                                 : t('The %1$d products below are %2$s and %3$s.', $n, implode(', ', array_slice($sizes, 0, -1)), end($sizes)) ?>
                                    <?= ($n === 1 ? t('Standard PCR is dependable for products under %s kb; longer ones often amplify poorly or not at all, so it is not counted.', $kb)
                                                  : t('Standard PCR is dependable for products under %s kb; longer ones often amplify poorly or not at all, so they are not counted.', $kb)) . "\n" ?>
                                <?php else: ?>
                                    <?= t('Nothing amplifies on your template at these settings.') . "\n" ?>
                                    <?php if ($intended_db === 'genome' && $search_mode !== 'transcript'): ?>
                                        <?php // The question this answer raises (user, 2026-10-29: "when I do
                                              // gDNA it says I don't have any alignments") — for primers made
                                              // on a transcript the answer is usually the good one. ?>
                                        <br><?= t('Designed these on a transcript (an mRNA, an XM_/NM_ ID, or with Primer Maker)?') . "\n" ?>
                                        <?= t_markup('A primer that sits across an %s junction has no match in genomic DNA — that is what makes it good for RT-PCR.', gloss('exon')) . "\n" ?>
                                        <?= t_markup('Set %1$s to %2$s to check it against the %3$s.', '<strong>' . t('PCR input') . '</strong>', '<strong>cDNA</strong>',
                                                     gloss('transcript', 'transcripts')) . "\n" ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($isoforms_note): ?>
                            <div class="small mb-2">
                                <i class="fa fa-check-circle text-success"></i>
                                <span class="text-success-emphasis"><?= t('One gene: %s', $isoforms_note['gene']) ?></span>
                                <span class="text-muted"><?= t_markup('— the %1$d products are %2$s of the same gene (%3$s).', (string) (int)$isoforms_note['n'],
                                                                      gloss('isoform', 'isoforms'), htmlspecialchars(implode(', ', $isoforms_note['tx']))) . "\n" ?>
                                <?php if (count($isoforms_note['sizes']) === 1): ?>
                                    <?= t('All are %s bp, so you will see one band.', number_format($isoforms_note['sizes'][0])) . "\n" ?>
                                    <?= t('The pair does not tell these isoforms apart.') . "\n" ?>
                                <?php else: ?>
                                    <?= t('Their sizes differ (%1$s), so expect %2$d bands if the isoforms are all expressed.',
                                          implode(', ', array_map(fn($n) => number_format($n) . ' bp', $isoforms_note['sizes'])), count($isoforms_note['sizes'])) . "\n" ?>
                                <?php endif; ?></span>
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

                        <?php
                        // No genomic product at all, with a cDNA product present: the
                        // strongest RT-PCR result there is, and previously reported only as
                        // a bare "None under 10,000 bp", which reads as a shortfall rather
                        // than the win it is. Usually means a primer spans an exon junction
                        // and so has no continuous genomic match.
                        // NO genomic product at all -- not merely one too large to amplify.
                        // $amp_contam would be 0 in both cases, which wrongly gave this note
                        // to an intron-spanning pair whose 6,389 bp genomic product exists
                        // and is simply over the size threshold.
                        $no_gdna = $intended_db === 'transcript'
                                && $t_size !== null
                                && isset($r['by_db']['genome'])
                                && $g_size === null;
                        ?>
                        <?php
                        // Where the exon index was available, the intron structure is now
                        // OBSERVED rather than inferred from two sizes differing. Both
                        // notes below say so when it is, and fall back to the inferred
                        // wording when it is not -- most gene sets have no exon index yet.
                        $t_mapped     = $t_prod['genomic'] ?? null;
                        $junction     = $t_mapped['spliced'] ?? false;
                        $junction_p   = null;
                        if ($junction) {
                            foreach ($t_mapped['hits'] as $h) {
                                if (!empty($h['spliced'])) { $junction_p = $h['primer']; break; }
                            }
                        }
                        ?>
                        <?php if ($no_gdna): ?>
                            <div class="small mb-3">
                                <i class="fa fa-check-circle text-success"></i>
                                <span class="text-success-emphasis"><?= t('No genomic product') ?></span>
                                <span class="text-muted"><?= t('— genomic DNA in the sample would not amplify at all.') . "\n" ?>
                                <?php if ($junction): ?>
                                    <?= empty($t_mapped['introns'])
                                        ? t('The %s primer sits across an exon junction, so that sequence exists only in spliced mRNA.', $junction_p)
                                        : t_markup('The %1$s primer sits across an exon junction, so that sequence exists only in spliced mRNA; on the genome the product spans %2$s %3$s.',
                                                   htmlspecialchars($junction_p), (string) (int)$t_mapped['introns'],
                                                   gloss('intron', $t_mapped['introns'] === 1 ? 'intron' : 'introns')) ?>
                                <?php else: ?>
                                    <?= t('Usually means a primer spans an exon junction.') . "\n" ?>
                                <?php endif; ?>
                                <?php if ($g_near): ?>
                                    <?= ((int)$g_near['max_mismatch'] === 1
                                        ? t('The closest genomic site (%1$s bp, %2$d mismatch) would not prime.', number_format($g_near['size']), (int)$g_near['max_mismatch'])
                                        : t('The closest genomic site (%1$s bp, %2$d mismatches) would not prime.', number_format($g_near['size']), (int)$g_near['max_mismatch'])) . "\n" ?>
                                <?php endif; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($t_size !== null && $g_size !== null): ?>
                            <?php // Without the exon index this is INFERRED from the two sizes
                                  // differing, never observed -- hence "likely", and the two
                                  // numbers it was inferred from. With the index the intron
                                  // count is read off the mapping and can be stated outright. ?>
                            <div class="small mb-3">
                                <?php if (!empty($t_mapped) && $t_mapped['introns'] > 0): ?>
                                    <i class="fa fa-info-circle text-success"></i>
                                    <span class="text-success-emphasis"><?= $t_mapped['introns'] === 1 ? t('Spans %d intron', 1) : t('Spans %d introns', (int)$t_mapped['introns']) ?></span>
                                    <span class="text-muted"><?= t('— cDNA %1$s bp vs gDNA %2$s bp', number_format($t_size), number_format($g_size)) ?></span>
                                <?php elseif ($g_size > $t_size): ?>
                                    <i class="fa fa-info-circle text-success"></i>
                                    <span class="text-success-emphasis"><?= t('Likely spans an intron') ?></span>
                                    <span class="text-muted"><?= t('— cDNA %1$s bp vs gDNA %2$s bp', number_format($t_size), number_format($g_size)) ?></span>
                                <?php else: ?>
                                    <i class="fa fa-info-circle text-warning"></i>
                                    <span class="text-warning-emphasis"><?= t('No intron between the primers') ?></span>
                                    <span class="text-muted"><?= t('— cDNA and gDNA products are both %s bp', number_format($t_size)) ?></span>
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
                                // Size and mismatches are both DISPLAY limits; a product hidden by
                                // either still counted toward the verdict.
                                $shown = array_values(array_filter($found['products'], function ($pr) use ($max_product, $max_mismatch) {
                                    return $pr['size'] <= $max_product && $pr['max_mismatch'] <= $max_mismatch;
                                }));
                                // Each hidden product is counted ONCE: by size if it is too large,
                                // otherwise by mismatches.
                                $over_mm = count(array_filter($found['products'],
                                    fn($pr) => $pr['size'] <= $max_product && $pr['max_mismatch'] > $max_mismatch));
                                $hidden_by_display = count($found['products']) - count($shown) - $over_mm;
                                $over_display = $hidden_by_display + (int)$found['over_max'];
                                ?>
                                <?php
                                // Hidden sites that would still amplify. Zero is the usual answer, and
                                // the one worth saying: "None shown — 99 larger than 10,000 bp" read as
                                // missing results when it meant "nothing here would amplify".
                                $hidden_amp = count(array_filter($found['products'], fn($pr) =>
                                    ($pr['size'] > $max_product || $pr['max_mismatch'] > $max_mismatch) && PrimerPairs::isAmplifiable($pr)));

                                // "Not listed: ..." -- said in two places below (nothing shown / under the
                                // table), built ONCE here so the two cannot drift. Whole phrases per
                                // plural form, through t() (CLAUDE.md 7b).
                                $__mm_forms = [['%1$d site where a primer has more than %2$d mismatch',  '%1$d site where a primer has more than %2$d mismatches'],
                                               ['%1$d sites where a primer has more than %2$d mismatch', '%1$d sites where a primer has more than %2$d mismatches']];
                                $not_listed_html = t('Not listed:') . "\n"
                                    . htmlspecialchars(implode(', ', array_filter([
                                        $over_mm ? t_plain($__mm_forms[$over_mm === 1 ? 0 : 1][$max_mismatch === 1 ? 0 : 1], $over_mm, $max_mismatch) : '',
                                        $over_display ? t_plain('%1$s where the primers are more than %2$s bp apart', number_format($over_display), number_format($max_product)) : '',
                                    ])))
                                    . ($hidden_amp === 0
                                        ? ' ' . t('— none of them would amplify.')
                                        : '. ' . ($hidden_amp === 1
                                            ? t_markup('%1$d of them could amplify — raise %2$s or the size limit to see it.', '1', '<strong>' . t('Mismatches listed') . '</strong>')
                                            : t_markup('%1$d of them could amplify — raise %2$s or the size limit to see them.', (string) $hidden_amp, '<strong>' . t('Mismatches listed') . '</strong>')));
                                ?>
                                <?php if (empty($shown)): ?>
                                    <div class="text-muted small mb-1">
                                        <?php if ($over_display > 0 || $over_mm > 0): ?>
                                            <?= ($hidden_amp === 0 ? t('No product would amplify here.') : t('None shown.')) . "\n" ?>
                                            <?= $not_listed_html . "\n" ?>
                                        <?php else: ?>
                                            <?= t('No products.') . "\n" ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-1">
                                        <tbody>
                                        <?php foreach ($shown as $prod): ?>
                                            <?php
                                            $subject = $clean_id($prod['subject']);

                                            // The intron facts the page shows as text and a badge.
                                            // A download has neither, so without these columns the
                                            // single most important property of an RT-PCR pair --
                                            // that it cannot amplify gDNA -- survives only as the
                                            // absence of a genomic row, which reads as missing data.
                                            //
                                            // '' means NOT DETERMINED (a genomic row, or a gene set
                                            // with no exon index); 0 and 'none' are real answers.
                                            // Collapsing the two would make "no intron here" and
                                            // "we could not tell" the same cell.
                                            $mapped_x = $prod['genomic'] ?? null;
                                            $introns  = $mapped_x ? (int)$mapped_x['introns'] : '';
                                            $junction = '';
                                            if ($mapped_x) {
                                                $which = [];
                                                foreach ($mapped_x['hits'] as $h) {
                                                    if (!empty($h['spliced'])) $which[] = $h['primer'];
                                                }
                                                $junction = $which ? implode('+', array_unique($which)) : 'none';
                                            }

                                            // Keyed by NAME, and the download derives its header from
                                            // these keys. The header used to be a separate hardcoded
                                            // list in js/primer-blast.js, so the two files had to agree
                                            // on column ORDER with nothing checking that they did.
                                            $export[] = [
                                                'pair'            => $pair['name'],
                                                'forward'         => $pair['forward'],
                                                'reverse'         => $pair['reverse'],
                                                'template'        => $db_key === 'genome' ? 'genomic' : 'cDNA',
                                                'subject'         => $subject,
                                                'start'           => $prod['start'],
                                                'end'             => $prod['end'],
                                                'product_size'    => $prod['size'],
                                                'max_mismatch'    => $prod['max_mismatch'],
                                                // Name WHICH primer: a downloaded row has no badge to
                                                // hover, so "self-pairing" alone loses the fact.
                                                'formed_by'       => $prod['self_pairing']
                                                    ? $prod['primers'][0] . ' self-pairing'
                                                    : 'forward+reverse',
                                                'introns_spanned' => $introns,
                                                'junction_primer' => $junction,
                                            ];
                                            ?>
                                            <tr>
                                                <td class="fw-bold" style="width:7rem; white-space:nowrap;">
                                                    <?= number_format($prod['size']) ?> bp
                                                </td>
                                                <td style="font-family:monospace; font-size:0.85rem;">
                                                    <?= htmlspecialchars($subject) ?>:<?= number_format($prod['start']) ?>–<?= number_format($prod['end']) ?>
                                                    <?php if (isset($prod['gene'])): ?>
                                                        <span class="text-muted small" style="font-family:inherit;">· <?= htmlspecialchars($gene_word($prod['gene'])) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="width:8rem;">
                                                    <span class="text-muted small"><?= $prod['max_mismatch'] === 1 ? t('%d mismatch', 1) : t('%d mismatches', (int)$prod['max_mismatch']) ?></span>
                                                    <?php // Listed because it is a real match, but it does not count toward
                                                          // the grade — say so on the row, or a table of five products under
                                                          // "One gene … 2 products" reads as a contradiction. ?>
                                                    <?php if (!PrimerPairs::wouldPrime($prod)): ?>
                                                        <br><span class="small text-warning-emphasis"
                                                                  title="<?= t('A primer has 6 or more mismatches, or 2 or more in the last 5 bases of its 3′ end — it would not prime here.') ?>"><?= t('would not prime') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="width:11rem;">
                                                    <?php if (!empty($prod['self_pairing'])): ?>
                                                        <?php // Both ends of this product are the SAME oligo. A pair is
                                                              // implicit for normal products, so only this case is labelled,
                                                              // and compactly -- the loop icon carries the idea, the popover
                                                              // and the Results help carry the explanation. ?>
                                                        <span class="badge bg-danger" title="<?= t('%s primer at both ends', $prod['primers'][0]) ?>">
                                                            <i class="fa fa-redo"></i> <?= t('%s self-pairing', $prod['primers'][0]) . "\n" ?>
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
                                                    <?php if (!empty($prod['genomic']['spliced'])): ?>
                                                        <?php // The reason to design an RT-PCR primer this way, and
                                                              // it is invisible in the size and coordinates alone.
                                                              // Stated here so it does not depend on the reader
                                                              // opening the browser to notice. ?>
                                                        <span class="badge bg-success" title="<?= t('A primer sits across an exon-exon junction') ?>">
                                                            <i class="fa fa-cut"></i> <?= t('junction primer') . "\n" ?>
                                                        </span>
                                                        <?= field_help(
                                                            'One of these primers sits across an exon-exon junction — half of it '
                                                            . 'binds the end of one exon and half binds the start of the next. '
                                                            . 'That sequence exists only in spliced mRNA, so the pair cannot '
                                                            . 'amplify contaminating genomic DNA. Open the browser to see the '
                                                            . 'primer drawn as two boxes either side of the intron.',
                                                            'Spans an exon junction'
                                                        ) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end" style="width:8rem;">
                                                    <?php
                                                    // A genomic product IS a locus. A cDNA product becomes one
                                                    // once ExonMap has placed it -- which is what gives a
                                                    // junction-spanning primer a browser link at all.
                                                    $mapped_g = $prod['genomic'] ?? null;
                                                    $can_link = moop_tool_on('genome_browser')   // switched off: no Browser button
                                                              && !empty($result_organism) && !empty($result_assembly)
                                                              && ($db_key === 'genome' || $mapped_g !== null)
                                                              // the browser can only open registered assemblies
                                                              && isJBrowseAssemblyRegistered($result_organism, $result_assembly);
                                                    ?>
                                                    <?php if ($can_link): ?>
                                                        <?php
                                                        // A little context either side, so the primers are not
                                                        // flush against the edge of the view.
                                                        //
                                                        // Padded by the DISPLAYED span, not by the product size:
                                                        // a cDNA product mapped across introns covers far more
                                                        // genomic sequence than its own length, so padding by
                                                        // that length would give a 349 bp margin on a 6 kb view.
                                                        list($loc_ref, $loc_start, $loc_end) = $mapped_g
                                                            ? [$mapped_g['chr'], $mapped_g['span'][0], $mapped_g['span'][1]]
                                                            : [$subject, $prod['start'], $prod['end']];
                                                        $pad   = max(50, (int)(($loc_end - $loc_start + 1) * 0.1));
                                                        $loc   = $loc_ref . ':' . max(1, $loc_start - $pad)
                                                               . '..' . ($loc_end + $pad);
                                                        $track = $primer_session_track(
                                                            $prod, $pair['name'], $result_organism . '_' . $result_assembly
                                                        );
                                                        $jb  = '/' . $site . '/jbrowse2.php?organism=' . urlencode($result_organism)
                                                             . '&assembly=' . urlencode($result_assembly)
                                                             . '&loc=' . urlencode($loc)
                                                             . '&sessionTracks=' . urlencode(json_encode($track))
                                                             . '&sessionTrackId=' . urlencode(implode(',', array_column($track, 'trackId')));
                                                        ?>
                                                        <a href="<?= htmlspecialchars($jb) ?>" target="_blank" rel="noopener"
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fa fa-dna"></i> <?= t('Browser') . "\n" ?>
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
                                <?php if (($over_display > 0 || $over_mm > 0) && !empty($shown)): ?>
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        <?= $not_listed_html . "\n" ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php // Download built from the canonical rows collected above. ?>
                <script type="application/json" id="primerExportData"><?= json_encode($export) ?></script>
                <button type="button" class="btn btn-outline-primary btn-sm" id="downloadPrimerResults">
                    <i class="fa fa-download"></i> <?= t('Download results (TSV)') . "\n" ?>
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

// The help cards below carry markup (bold terms, code, colour swatches), which help_modal() does
// not translate -- a translation is never trusted to carry markup. So each card is written here as
// whole translated sentences, the markup passed in as arguments (CLAUDE.md 7b).
$b  = fn(string $text) => '<strong>' . t($text) . '</strong>';
$c  = fn(string $code) => '<code>' . htmlspecialchars($code) . '</code>';
$sw = fn(string $hex)  => '<span style="display:inline-block;width:0.8em;height:0.8em;background:'
                        . htmlspecialchars($hex) . ';vertical-align:middle;border-radius:2px;"></span>';

echo help_modal(
    'primer-formats-help',
    'Accepted primer formats',
    [[
        'heading' => '',
        'cards'   => [
            [
                'label' => 'FASTA — one primer per record',
                'html'  => true,
                'text'  => t_markup('Paired by name suffix: %1$s, and also %2$s, %3$s, %4$s.', $c('_F') . '/' . $c('_R'), $c('_fwd') . '/' . $c('_rev'), $c('_left') . '/' . $c('_right'), $c('_forward') . '/' . $c('_reverse'))
                         . ' ' . t('If the names carry no suffix, records are paired in the order given — 1st with 2nd, 3rd with 4th.')
                         . $ex(">TP53_F\nGCTTGAGCTGTTATCTGTGC\n>TP53_R\nGCGGTGCTTCTGGGCTGAGT"),
            ],
            [
                'label' => 'FASTA — both primers in one record',
                'html'  => true,
                'text'  => t_markup('Separate the two primers with a run of %1$s %2$ss.', $b('three or more'), $c('N'))
                         . ' ' . t_markup('Shorter runs are left alone, so a single %s stays a degenerate base rather than splitting your primer.', $c('N'))
                         . $ex(">TP53\nGCTTGAGCTGTTATCTGTGCNNNNNNNNGCGGTGCTTCTGGGCTGAGT"),
            ],
            [
                'label' => 'TSV or CSV',
                'html'  => true,
                'text'  => t('Include a header row.')
                         . ' ' . t_markup('Columns are found by %s, never by position, so their order does not matter.', '<em>' . t('name') . '</em>')
                         . ' ' . t_markup('%1$s also accepts %2$s or %3$s; %4$s also accepts %5$s or %6$s.', $c('forward'), $c('left'), $c('fwd'), $c('reverse'), $c('right'), $c('rev'))
                         . $ex("name\tforward\treverse\nTP53\tGCTTGAGCTGTTATCTGTGC\tGCGGTGCTTCTGGGCTGAGT"),
            ],
            [
                'label' => 'Plain sequences',
                'html'  => true,
                'text'  => t('One sequence per line, no names.') . ' ' . t('They are paired in the order given and named for you.')
                         . ' ' . t('Position numbers and line wrapping are ignored, so a sequence copied out of a document still works.')
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
                'text'  => t('Every assembly you have access to, one row per gene set.')
                         . ' ' . t_markup('The badges on each row read left to right as %1$s, %2$s, %3$s, and %4$s.', $b('group'), $b('organism'), $b('assembly'), $b('gene set')),
            ],
            [
                'label' => 'Filtering',
                'html'  => true,
                'text'  => t('The box above the list narrows it as you type, matching against all four of those at once — so a group name, a species, an accession or a gene set name all work.')
                         . ' ' . t_markup('%s restores the full list.', $b('Clear Filters')),
            ],
            [
                // 'color' is the API's badge form, for exactly this case: the card title
                // renders as the same badge the page shows, so the eye matches the help to
                // the thing it just looked at. Putting markup in 'label' does NOT work —
                // labels are always escaped, and the tag showed as literal text.
                'label' => '!',
                'color' => 'warning',
                'html'  => true,
                'text'  => $b('Not usable here.')
                         . ' ' . t("This tool needs the assembly's genome to say where a product lands, and some assemblies have none — they are transcriptome assemblies, so that is correct, not missing data.")
                         . ' ' . t_markup('Most are still usable with %1$s set to %2$s.', $b('PCR input'), '<strong>cDNA</strong>')
                         . ' ' . t('Hover a marker for its own reason.'),
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
                'label' => 'specific',
                'color' => 'success',
                'html'  => true,
                'text'  => t('Exactly one product could amplify — one on your template, and nothing else.')
                         . ' ' . t_markup('On cDNA this is counted by %s: isoforms of one gene give the same product, so a pair that amplifies several isoforms of one gene is still specific (a note names them, and says whether their sizes differ).', $b('gene'))
                         . ' ' . t('If your input is cDNA, genomic DNA gives only the one matching locus, which is expected: that is the same place with its introns included.'),
            ],
            [
                'label' => 'non-specific',
                'color' => 'warning',
                'html'  => true,
                'text'  => t('More than one product could amplify.')
                         . ' ' . t('The reasons are listed under the name: more than one gene on your cDNA (or several products on genomic DNA), extra genomic products, or a self-pairing primer.')
                         . ' ' . t('Non-specific is not unusable — read the reasons and decide.')
                         . ' ' . t_markup('A listed product marked %s does not count.', $b('would not prime')),
            ],
            [
                'label' => 'no product',
                'color' => 'secondary',
                'html'  => true,
                'text'  => t('Nothing would amplify on your template: no place where both primers bind, facing each other, with %d or fewer mismatches each.', PrimerBlast::SEARCH_MAX_MISMATCH)
                         . ' ' . t_markup('The %1$s and %2$s boxes do not change this — they only decide what is listed.', $b('Mismatches listed'), $b('Largest product to report')),
            ],
            [
                'label' => 'no product under ' . PrimerPairs::amplifiableKb() . ' kb',
                'color' => 'secondary',
                'html'  => true,
                'text'  => t('A product is listed, but it is longer than %s kb.', PrimerPairs::amplifiableKb())
                         . ' ' . t('Standard PCR is dependable for products under %s kb; longer ones often amplify poorly or not at all, so it is not counted.', PrimerPairs::amplifiableKb())
                         . ' ' . t('A long-range polymerase may still amplify it.'),
            ],
            [
                'label' => 'Only what would really amplify counts',
                'accent' => true,
                'html'  => true,
                'text'  => t_markup('A product counts when it is %1$s (where standard PCR is dependable) and neither primer is blocked: fewer than 6 mismatches overall, and fewer than 2 within the %2$s.',
                                  '<strong>' . t('under %s kb', PrimerPairs::amplifiableKb()) . '</strong>', $b("last 5 bases of the 3′ end"))
                         . ' ' . t('Only those count toward the grade — one is specific, more than one is non-specific.')
                         . ' ' . t('Anything outside is still listed because it is real; it is just not expected to amplify.'),
            ],
            [
                'label' => 'The size limit does not change the grade',
                'accent' => true,
                'html'  => true,
                'text'  => t_markup('%s controls what is LISTED, nothing more.', $b('Largest product to report'))
                         . ' ' . t('The grade is always computed over amplifiable products, so lowering the limit hides rows without quietly turning a non-specific pair specific.')
                         . ' ' . t('A display control should never change a scientific conclusion.'),
            ],
            [
                'label' => 'Viewing a product in the browser',
                'html'  => true,
                'text'  => t_markup('%s opens the product in JBrowse with a track showing both binding sites as boxes, joined by a connector across the sequence between them.', $b('Browser'))
                         . ' ' . t_markup('%1$s blue is the %2$s primer, %3$s orange the %4$s.', $sw(PRIMER_COLOR_FORWARD), $b('forward'), $sw(PRIMER_COLOR_REVERSE), $b('reverse'))
                         . ' ' . t('Only genomic products get a link — a cDNA product has no genomic coordinates to open.'),
            ],
            [
                'label' => 'What counts as a product',
                'html'  => true,
                'text'  => t_markup('Both primers must land on the %1$s, on %2$s, %3$s, and close enough to amplify between them.', $b('same sequence'), $b('opposite strands'), $b('facing each other'))
                         . ' ' . t('A primer that matches somewhere on its own makes nothing, so only products are listed.'),
            ],
            [
                'label' => 'Mismatches listed',
                'html'  => true,
                'text'  => t('Up to %d mismatches per primer are kept, as NCBI Primer-BLAST does, because a single mismatch — or a few toward the 5′ end — can still amplify, and the verdict is judged on all of them.', PrimerBlast::SEARCH_MAX_MISMATCH)
                         . ' ' . t_markup('This box only changes which products are %s in the results.', $b('displayed'))
                         . ' ' . t_markup('Mismatches are counted over the %s: bases the alignment did not reach count too.', $b('whole primer')),
                'accent' => true,
            ],
            [
                'label' => 'Worked example',
                'html'  => true,
                'text'  => t_markup('A pair with an off-target where the reverse primer has 2 mismatches near its 5′ end: it is graded %s at any setting, because that site would amplify.', '<em>' . t('non-specific') . '</em>')
                         . ' ' . t_markup('With %s at 1 the off-target row is not listed — a note says so — and at 2 it is listed, marked "2 mismatches".', $b('Mismatches listed')),
                'accent' => true,
            ],
            [
                'label' => 'Why 3′ position matters more than count',
                'accent' => true,
                'html'  => true,
                'text'  => t('The polymerase extends from the 3′ end, so a mismatch in the last few bases all but stops extension while the same mismatch near the 5′ end is tolerated.')
                         . ' ' . t_markup('Two primers with an identical mismatch %s can therefore behave completely differently.', '<em>' . t('count') . '</em>')
                         . ' ' . t('These thresholds follow NCBI Primer-BLAST, which dismisses an unintended target when a primer carries 2 or more mismatches in its last 5 bases.')
                         . ' ' . t('The number in the table is the worse of the two primers.'),
            ],
            [
                'label' => 'gDNA: N products',
                'html'  => true,
                'text'  => t_markup('Appears when genomic DNA would give %s product.', $b('more than one'))
                         . ' ' . t('Exactly one is expected — it is the same locus your cDNA product came from, just with the introns included.')
                         . ' ' . t('More than one means the primers also pair somewhere else in the genome, which the cDNA result alone cannot show you.'),
            ],
            [
                'label' => 'Self-pairing',
                'html'  => true,
                'text'  => t_markup('Marked %s.', '<span class="badge bg-danger"><i class="fa fa-redo"></i> ' . t('reverse self-pairing') . '</span>')
                         . ' ' . t('One primer can match twice on opposite strands, facing itself, and amplify the fragment between — so both ends of that product are the same oligo, and the other primer is not involved at all.')
                         . ' ' . t('A real source of unexpected bands, and one you would never predict by thinking about the pair.')
                         . ' ' . t('The badge names the primer responsible, and so does the TSV download.'),
            ],
            [
                'label' => 'No genomic product',
                'html'  => true,
                'text'  => t('The strongest result for RT-PCR: your cDNA product forms, and genomic DNA gives nothing at all — not a bigger band, no band.')
                         . ' ' . t('Usually it means a primer sits across an exon junction, so it has no continuous match in the genome to bind to.')
                         . ' ' . t_markup('Such a pair also gets no %s link, because there is no genomic product to show.', $b('Browser')),
            ],
            [
                'label' => 'Likely spans an intron',
                'html'  => true,
                'text'  => t('Shown when the gDNA product is larger than the cDNA one — the extra length is intron.')
                         . ' ' . t_markup('It is %s from the two sizes, not observed, hence "likely".', '<em>' . t('inferred') . '</em>')
                         . ' ' . t('Equal sizes mean both primers sit in one exon, and the pair cannot tell cDNA from genomic DNA.'),
            ],
            [
                'label' => 'Products over the size limit',
                'html'  => true,
                'text'  => t('Counted but not listed, because very long products do not amplify under normal PCR.')
                         . ' ' . t_markup('They are still real pairings, so the count is shown rather than hidden — raise %s to see them.', $b('Largest product to report')),
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
                'text'  => t_markup('Searches the %s only, because that is your template.', $b('genome'))
                         . ' ' . t('Products are reported at their genomic size — so if the pair happens to sit either side of an intron, the intron is part of the product and it will be larger than you might expect from the mRNA sequence.'),
            ],
            [
                'label' => 'cDNA',
                'html'  => true,
                'text'  => t_markup('Searches the %s — both, always.', $b('transcriptome and the genome'))
                         . ' ' . t('The transcriptome gives the product you actually want, at its true cDNA size.')
                         . ' ' . t_markup('The genome is searched as well because %s — not just RT-PCR.', '<em>' . t('any reaction on cDNA can carry over genomic DNA') . '</em>')
                         . ' ' . t('It shows what that genomic DNA would amplify, and how big it would be.'),
            ],
            [
                'label' => 'Why cDNA still searches the genome',
                'html'  => true,
                'text'  => t('A transcript-only check cannot see genomic sites at all, so it would report a pair as clean while gDNA in the sample amplified something else entirely.')
                         . ' ' . t_markup('Searching both is also what lets the tool tell you whether your pair %s: if the genomic product is bigger than the cDNA product, it does — and genomic DNA in the sample gives a different-sized band, or none at all.', $b('spans an intron')),
            ],
        ],
    ]],
    ['intro' => 'Choose the template that goes into your reaction, and the right databases are '
              . 'searched for you.']
);
?>
