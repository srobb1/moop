<?php
/**
 * PRIMER MAKER — content file. FIRST DRAFT 2026-08-07.
 *
 * Variables from $data: $presets, $primer_type, $sequence_text, $num_return,
 * $size_range, $results, $run_error, $notes, $template_id, $primer3_ok,
 * $primer3_problem, $context_*, $site
 *
 * Step numbering and card styling follow tools/pages/primer_blast.php so the two
 * primer tools look like siblings rather than two different applications.
 */
?>

<div class="container mt-5">

    <div class="card mb-4 shadow-sm">
        <div class="card-header text-white d-flex align-items-center tool-header">
            <span class="text-uppercase fw-semibold section-eyebrow">
                <i class="fa fa-wand-magic-sparkles me-2"></i>Primer Maker
            </span>
        </div>
        <div class="card-body">
            <?= page_purpose('Design PCR, qPCR, RT-PCR or sequencing primers from a sequence, and get a table you can paste straight into a spreadsheet.') ?>
        </div>
    </div>

    <?php // primer3 missing is an ADMIN problem, and the user can do nothing about
          // it — so say that plainly rather than letting them fill in the form and
          // hit an error on submit. ?>
    <?php if (!$primer3_ok): ?>
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="fa fa-triangle-exclamation me-2"></i>Primer design is unavailable</h5>
            <?php if ($primer3_problem === 'missing'): ?>
                <p class="mb-0">The primer3 program is not installed on this server. An administrator
                can install it — the Admin dashboard's environment check says how.</p>
            <?php else: ?>
                <p class="mb-0">primer3 is installed but its thermodynamic parameter tables are
                missing, so every design would fail. An administrator can fix this by re-running
                the installer.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" id="primerMakerForm">
        <?= csrf_input_field() ?>
        <input type="hidden" name="organism" value="<?= htmlspecialchars($context_organism) ?>">
        <input type="hidden" name="assembly" value="<?= htmlspecialchars($context_assembly) ?>">
        <input type="hidden" name="gene_set" value="<?= htmlspecialchars($context_gene_set) ?>">
        <input type="hidden" name="feature"  value="<?= htmlspecialchars($context_feature) ?>">
        <input type="hidden" name="seq_type" value="<?= htmlspecialchars($seq_type) ?>">

        <!-- Step 1 — sequence -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header text-white tool-header">
                <span class="step-badge me-2">1</span>
                <span class="fw-semibold" style="font-size:0.9rem;">Your sequence</span>
            </div>
            <div class="card-body py-3">
                <p class="small text-muted mb-2">
                    Paste DNA, with or without a FASTA header. Primers are designed from this
                    sequence, so include the region you want to amplify plus some room either side.
                </p>
                <textarea name="sequence" class="form-control" rows="8" style="font-family:monospace; font-size:0.85rem;"
                          placeholder="&gt;my_gene&#10;ATGGCTAGCTAGCTAGCATCGATCGATCG..."><?= htmlspecialchars($sequence_text) ?></textarea>
                <?php
                $with_seq = array_values(array_filter($isoforms, fn($i) => $i['fasta_id'] !== ''));
                ?>
                <?php if (count($with_seq) === 1 && $selected_isoform !== ''): ?>
                    <?php // One transcript: nothing to ask, so do not ask. ?>
                    <div class="form-text">
                        <i class="fa fa-circle-check text-success me-1"></i>
                        Filled in with <strong><?= htmlspecialchars($with_seq[0]['fasta_id']) ?></strong>,
                        the only transcript of <?= htmlspecialchars($context_feature) ?>
                        (<?= number_format((int)$with_seq[0]['length']) ?> bp).
                    </div>
                <?php elseif (count($with_seq) > 1): ?>
                    <?php // Several: picking one for them would be guessing, and the
                          // transcripts differ in exactly the way that matters here. ?>
                    <div class="mt-3">
                        <label for="isoform" class="form-label mb-1">
                            <strong><?= htmlspecialchars($context_feature) ?></strong> has
                            <?= count($with_seq) ?> transcripts — which one?
                            <?= field_help(
                                'Isoforms of the same gene differ in which exons they contain, so a primer '
                                . 'pair that works on one may not amplify another — and for RT-PCR the exon '
                                . 'junctions themselves differ. Pick the transcript you actually care about. '
                                . 'Choosing one replaces the sequence box with its sequence.',
                                'Which transcript?'
                            ) ?>
                        </label>
                        <div class="d-flex gap-2 align-items-start flex-wrap">
                            <select name="isoform" id="isoform" class="form-select" style="max-width:32rem;">
                                <?php foreach ($isoforms as $iso): ?>
                                    <option value="<?= htmlspecialchars($iso['id']) ?>"
                                            <?= $iso['fasta_id'] === '' ? 'disabled' : '' ?>
                                            <?= $selected_isoform === $iso['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($iso['fasta_id'] ?: $iso['id']) ?>
                                        — <?= htmlspecialchars($iso['type']) ?><?php
                                        echo $iso['length'] !== null
                                            ? ', ' . number_format((int)$iso['length']) . ' bp'
                                            : ' (no sequence available)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php // A plain submit, not JS: it re-renders the page with the
                                  // chosen transcript prefilled, and works with no scripting. ?>
                            <button type="submit" name="load_isoform" value="1" class="btn btn-outline-secondary">
                                <i class="fa fa-arrow-down me-1"></i>Load this sequence
                            </button>
                        </div>
                        <?php $missing = count($isoforms) - count($with_seq); ?>
                        <?php if ($missing > 0): ?>
                            <div class="form-text">
                                <?= $missing ?> further transcript<?= $missing === 1 ? ' has' : 's have' ?>
                                no sequence in this gene set, so <?= $missing === 1 ? 'it is' : 'they are' ?>
                                listed but cannot be chosen.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($context_feature): ?>
                    <div class="form-text">
                        <i class="fa fa-link me-1"></i>Arrived from
                        <strong><?= htmlspecialchars($context_feature) ?></strong>, but no transcript
                        sequence was found for it in this gene set. Paste a sequence above.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2 — what kind -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header text-white tool-header">
                <span class="step-badge me-2">2</span>
                <span class="fw-semibold" style="font-size:0.9rem;">What are you making?</span>
            </div>
            <div class="card-body py-3">
                <div class="row g-3">
                    <?php foreach ($presets as $key => $preset): ?>
                        <div class="col-md-6">
                            <div class="form-check border rounded p-3 h-100">
                                <input class="form-check-input" type="radio" name="primer_type"
                                       id="type_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($key) ?>"
                                       <?= $primer_type === $key ? 'checked' : '' ?>>
                                <label class="form-check-label" for="type_<?= htmlspecialchars($key) ?>">
                                    <strong><?= htmlspecialchars($preset['label']) ?></strong>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($preset['blurb']) ?></div>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Step 3 — options -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header text-white tool-header">
                <span class="step-badge me-2">3</span>
                <span class="fw-semibold" style="font-size:0.9rem;">Options</span>
            </div>
            <div class="card-body py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-body py-3">
                                <label for="size_range" class="form-label mb-2">
                                    <strong>Product size range</strong>
                                    <?= field_help(
                                        'How big the amplicon should be, as low-high in base pairs. Leave it '
                                        . 'blank to use the range that goes with the primer type you picked. '
                                        . 'Widening this is the first thing to try when no primers are found.',
                                        'Product size range'
                                    ) ?>
                                </label>
                                <input type="text" class="form-control" id="size_range" name="size_range"
                                       placeholder="<?= htmlspecialchars($presets[$primer_type]['params']['PRIMER_PRODUCT_SIZE_RANGE'] ?? '100-400') ?>"
                                       value="<?= htmlspecialchars($size_range) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-body py-3">
                                <label for="num_return" class="form-label mb-2">
                                    <strong>How many pairs to return</strong>
                                    <?= field_help(
                                        'Primer3 ranks pairs by its own penalty score, best first. Asking for '
                                        . 'more gives you alternatives to choose between; it does not make the '
                                        . 'first one any better.',
                                        'How many pairs'
                                    ) ?>
                                </label>
                                <input type="number" class="form-control" id="num_return" name="num_return"
                                       min="1" max="20" value="<?= (int)$num_return ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" <?= $primer3_ok ? '' : 'disabled' ?>>
            <i class="fa fa-wand-magic-sparkles me-1"></i>Design primers
        </button>
        <a href="<?= htmlspecialchars('/' . $site . '/tools/primer_maker.php') ?>" class="btn btn-outline-secondary ms-2">Clear</a>
    </form>

    <?php if ($run_error): ?>
        <div class="alert alert-danger mt-4"><i class="fa fa-circle-exclamation me-2"></i><?= htmlspecialchars($run_error) ?></div>
    <?php endif; ?>

    <?php foreach ($notes as $note): ?>
        <div class="alert alert-info mt-3 small mb-0"><i class="fa fa-circle-info me-2"></i><?= htmlspecialchars($note) ?></div>
    <?php endforeach; ?>

    <?php
    // Results. Columns are keyed by NAME here and in the export, never by
    // position — the results-table work on 2026-07-23 found four defects caused
    // by positional column identity, and this table is explicitly meant to be
    // pasted into a spreadsheet.
    $columns = [
        'rank'           => '#',
        'left_sequence'  => 'Forward primer',
        'left_tm'        => 'F Tm',
        'left_gc'        => 'F GC%',
        'left_start'     => 'F start',
        'right_sequence' => 'Reverse primer',
        'right_tm'       => 'R Tm',
        'right_gc'       => 'R GC%',
        'right_end'      => 'R end',
        'product_size'   => 'Product (bp)',
        'pair_penalty'   => 'Penalty',
    ];
    $export = [];
    ?>

    <?php foreach ($results as $entry): ?>
        <?php if (empty($entry['pairs'])) continue; ?>
        <div class="card mt-4 shadow-sm">
            <div class="card-header text-white tool-header">
                <span class="text-uppercase fw-semibold section-eyebrow">
                    <i class="fa fa-table me-2"></i><?= count($entry['pairs']) ?> primer pair<?= count($entry['pairs']) === 1 ? '' : 's' ?>
                    for <?= htmlspecialchars($entry['id']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr><?php foreach ($columns as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entry['pairs'] as $pair): ?>
                            <?php
                            $row = [];
                            foreach (array_keys($columns) as $key) {
                                $v = $pair[$key] ?? '';
                                // Trim primer3's float padding — 59.972000 is not
                                // more accurate than 60.0, it is just wider.
                                if (in_array($key, ['left_tm','right_tm','left_gc','right_gc'], true) && $v !== '') {
                                    $v = number_format((float)$v, 1);
                                } elseif ($key === 'pair_penalty' && $v !== '') {
                                    $v = number_format((float)$v, 3);
                                }
                                $row[$key] = $v;
                            }
                            $export[] = $row;
                            ?>
                            <tr>
                                <?php foreach ($row as $key => $v): ?>
                                    <td class="<?= in_array($key, ['left_sequence','right_sequence'], true) ? '' : 'small' ?>"
                                        <?= in_array($key, ['left_sequence','right_sequence'], true) ? 'style="font-family:monospace;"' : '' ?>><?= htmlspecialchars((string)$v) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end">
                                    <?php
                                    // Chaining, per row: design → check, with no copy-paste
                                    // round trip. A real POST rather than a link, so Primer
                                    // BLAST RUNS rather than just arriving with its box
                                    // filled — the question "is this pair specific" is the
                                    // reason the button exists.
                                    //
                                    // Named _F/_R so Primer BLAST's own input parser pairs
                                    // them by suffix, which is its first supported shape.
                                    $chain_name = preg_replace('/[^A-Za-z0-9_.-]/', '_', $entry['id']) . '_p' . $pair['rank'];
                                    $chain_fasta = ">{$chain_name}_F\n" . ($pair['left_sequence'] ?? '')
                                                 . "\n>{$chain_name}_R\n" . ($pair['right_sequence'] ?? '');
                                    ?>
                                    <form method="post" action="<?= htmlspecialchars('/' . $site . '/tools/primer_blast.php') ?>"
                                          target="_blank" class="d-inline">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="primers" value="<?= htmlspecialchars($chain_fasta) ?>">
                                        <?php if ($context_organism && $context_assembly && $context_gene_set): ?>
                                            <input type="hidden" name="selected_source"
                                                   value="<?= htmlspecialchars($context_organism . '|' . $context_assembly . '|' . $context_gene_set) ?>">
                                            <?php // cDNA: these were designed on a transcript, so the
                                                  // transcriptome is the template they came from — and it
                                                  // is what makes the intron comparison meaningful. ?>
                                            <input type="hidden" name="search_mode" value="transcript">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Check this pair for specificity and product size">
                                            <i class="fa fa-vials me-1"></i>Check
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php // The stated requirement: get this into a spreadsheet. Same
                      // shape as Primer BLAST's download — rows keyed by name, and
                      // the header derived from those keys. ?>
                <script type="application/json" id="primerMakerExport"><?= json_encode($export) ?></script>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadPrimerMakerResults();">
                    <i class="fa fa-download me-1"></i>Download results (TSV)
                </button>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<script>
// Header derived from the DATA, so it cannot drift from the columns above.
function downloadPrimerMakerResults() {
    var node = document.getElementById('primerMakerExport');
    if (!node) { return; }
    var rows;
    try { rows = JSON.parse(node.textContent); } catch (e) { return; }
    if (!rows || !rows.length) { return; }

    var header = Object.keys(rows[0]);
    var tsv = [header.join('\t')];
    rows.forEach(function (r) {
        tsv.push(header.map(function (k) {
            var c = r[k];
            return (c === null || c === undefined ? '' : String(c)).replace(/[\t\r\n]/g, ' ');
        }).join('\t'));
    });

    var blob = new Blob([tsv.join('\n') + '\n'], {type: 'text/tab-separated-values'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href = url;
    a.download = 'primer_maker_results.tsv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
