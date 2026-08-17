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
                <i class="fa fa-magic me-2"></i>Primer Maker
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
            <h5 class="alert-heading"><i class="fa fa-exclamation-triangle me-2"></i>Primer design is unavailable</h5>
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

                <?php // Two marks, two meanings, neither tied to a kind of primer.
                      // Spelled out here rather than only in a popover because a
                      // notation nobody knows exists gets used by nobody. ?>
                <div class="border rounded mt-2 px-3 py-2" style="background-color:#f6fbfd;">
                    <div class="small fw-semibold mb-1">
                        You can mark the sequence itself
                        <?= field_help(
                            'These marks are instructions, not bases — they are removed before the '
                            . 'sequence reaches Primer3, and they never count towards a position. '
                            . 'Neither one is tied to a kind of primer: a "|" is just as useful for a '
                            . 'fusion breakpoint, a vector–insert boundary or a scaffold join as it is '
                            . 'for an exon junction.',
                            'Marking up a sequence'
                        ) ?>
                    </div>
                    <div class="small text-muted">
                        <div>
                            <code class="fw-bold">|</code>
                            — a primer <strong>must cross</strong> this point.
                            <code>ACGT<span class="fw-bold">|</span>ACGT</code> puts it after base 4.
                            Add as many as you like; Primer3 uses whichever one it can place a primer
                            across and ignores the rest.
                        </div>
                        <div>
                            <code class="fw-bold">[ ]</code>
                            — every product <strong>must contain</strong> what is inside.
                            <code>ACGT<span class="fw-bold">[</span>ACGT<span class="fw-bold">]</span>ACGT</code>
                            keeps bases 5–8 in the amplicon. Same as the
                            <em>Region to include</em> box below — use one or the other.
                        </div>
                        <?php if ($context_feature): ?>
                            <div class="mt-1">
                                <i class="fa fa-check-circle text-success me-1"></i>
                                You arrived from a gene page, so for <strong>RT-PCR</strong> the exon
                                junctions are looked up for you — you do not need to mark them. They
                                are only used if the sequence still matches the stored transcript.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                $with_seq = array_values(array_filter($isoforms, fn($i) => $i['fasta_id'] !== ''));
                ?>
                <?php if (count($with_seq) === 1 && $selected_isoform !== ''): ?>
                    <?php // One transcript: nothing to ask, so do not ask. ?>
                    <div class="form-text">
                        <i class="fa fa-check-circle text-success me-1"></i>
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
                <?php
                // What each type actually DOES, in numbers. The blurb says what it is
                // for; this says what primer3 is told — and the two were impossible to
                // reconcile before, because "tighter Tm" does not say tighter than what.
                //
                // Read from $preset_options, which is the SAME array the placeholders and
                // the run are built from, so a card cannot advertise a value primer3 is
                // not given.
                $trim_num = function ($v) {
                    // 57.0 -> "57", 62.5 -> "62.5". primer3's float padding is width,
                    // not precision, and these are meant to be skimmed.
                    return rtrim(rtrim(number_format((float)$v, 1), '0'), '.');
                };
                $preset_summary = function ($key) use ($preset_options, $trim_num) {
                    $o = $preset_options[$key] ?? [];
                    if (!$o) {
                        return [];
                    }
                    return [
                        'Product ' . str_replace('-', '–', (string)$o['size_range']) . ' bp',
                        'Tm ' . $trim_num($o['tm_min']) . '–' . $trim_num($o['tm_max']) . ' °C'
                            . ' (best ' . $trim_num($o['tm_opt']) . ')',
                        'GC ' . $trim_num($o['gc_min']) . '–' . $trim_num($o['gc_max']) . '%',
                        'Length ' . $trim_num($o['size_min']) . '–' . $trim_num($o['size_max']) . ' nt',
                    ];
                };
                ?>
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
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <?php foreach ($preset_summary($key) as $bit): ?>
                                            <span class="badge rounded-pill fw-normal"
                                                  style="background-color:#e6f6fa; color:#0b6a80; font-size:0.72rem;">
                                                <?= htmlspecialchars($bit) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if ($key === 'rtpcr'): ?>
                                            <?php // The one preset whose difference is STRUCTURAL rather
                                                  // than numeric — it would be invisible in a row of
                                                  // numbers, and it is the whole point of the type. ?>
                                            <span class="badge rounded-pill fw-normal"
                                                  style="background-color:#fdeee0; color:#8a4b12; font-size:0.72rem;">
                                                + must span an exon junction
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mb-0 mt-3">
                    <i class="fa fa-info-circle me-1"></i>
                    These are starting values. Anything you set yourself in
                    <strong>More options</strong> below overrides them.
                </p>
            </div>
        </div>

        <!-- Step 3 — options -->
        <?php
        // One renderer for every numeric option, driven by Primer3Design::OPTIONS.
        // The bounds in the markup ARE the bounds the server enforces, because
        // both read the same table — a hand-written min/max per input is how a
        // form ends up accepting a value the backend rejects.
        $option_field = function ($field, $help = '') use ($option_specs, $options, $option_defaults) {
            $spec = $option_specs[$field];
            $ph   = $option_defaults[$field] ?? '';
            echo '<input type="number" class="form-control form-control-sm primer-option" '
               . 'id="' . htmlspecialchars($field) . '" name="' . htmlspecialchars($field) . '" '
               . 'data-option="' . htmlspecialchars($field) . '" '
               . 'min="' . htmlspecialchars((string)$spec['min']) . '" '
               . 'max="' . htmlspecialchars((string)$spec['max']) . '" '
               . ($spec['type'] === 'float' ? 'step="0.1" ' : 'step="1" ')
               . 'placeholder="' . htmlspecialchars((string)$ph) . '" '
               . 'aria-label="' . htmlspecialchars($spec['label']) . '" '
               . 'value="' . htmlspecialchars($options[$field] ?? '') . '">';
        };

        // Open the advanced panel when it holds something — a value the user
        // typed, or an error about one. Collapsed over a rejected value would
        // show an error pointing at a box that is not on screen.
        // Derived from the option table itself, so a field added to
        // Primer3Design::OPTIONS cannot be left out of this check and quietly
        // fail to open the panel that holds it.
        $advanced_open = (bool)$run_error;
        foreach (array_keys($option_specs) as $f) {
            if (($options[$f] ?? '') !== '') { $advanced_open = true; break; }
        }
        ?>
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
                                    <strong>Product size</strong>
                                    <?= field_help(
                                        'How big the amplicon should be. Give a range as low-high in base '
                                        . 'pairs (150-400), or a single number for the biggest product you '
                                        . 'would want — that asks for the longest amplicon it can find and '
                                        . 'works down from there. Leave it blank to use the range that goes '
                                        . 'with the primer type you picked. Widening this is the first thing '
                                        . 'to try when no primers are found.',
                                        'Product size'
                                    ) ?>
                                </label>
                                <input type="text" class="form-control" id="size_range" name="size_range"
                                       data-option="size_range"
                                       placeholder="<?= htmlspecialchars((string)($option_defaults['size_range'] ?? '100-400')) ?>"
                                       value="<?= htmlspecialchars($size_range) ?>">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1"
                                           id="longest_product" name="longest_product"
                                           <?= $longest ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="longest_product">
                                        As long as possible
                                        <?= field_help(
                                            'Asks for the longest product your sequence allows, rather than a '
                                            . 'fixed range. Primer3 is given several ranges — three quarters '
                                            . 'of the sequence, then half, then a quarter, then anything over '
                                            . '100 bp — and tries them in order, so it settles for a shorter '
                                            . 'product only when a longer one is impossible. Useful when you '
                                            . 'are cloning as much of a sequence as you can get. Overrides '
                                            . 'the box above.',
                                            'As long as possible'
                                        ) ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-body py-3">
                                <label for="target" class="form-label mb-2">
                                    <strong>Region to include <span class="fw-normal text-muted">(optional)</span></strong>
                                    <?= field_help(
                                        'Forces every product to span a particular stretch of your sequence — '
                                        . 'a variant you want to sequence across, an exon, a domain. Give it as '
                                        . 'start,length counting from 1, so 300,100 means "the product must '
                                        . 'cover positions 300 to 399". Primers themselves are kept outside the '
                                        . 'region, so you can read straight through it.',
                                        'Region to include'
                                    ) ?>
                                </label>
                                <input type="text" class="form-control" id="target" name="target"
                                       placeholder="e.g. 300,100"
                                       value="<?= htmlspecialchars($target) ?>">
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

                <?php // Advanced. Every box blank means "use the primer type's value",
                      // which the placeholder shows — so the panel can be opened,
                      // read, and closed again without changing anything.
                      //
                      // Styled as blast.php's "Advanced Options" toggle, deliberately:
                      // full-width .btn-outline-moop with the chevron pushed to the
                      // right edge. It was a .btn-link reading "Primer length, Tm and
                      // GC content", which looked like a section HEADING rather than
                      // something to click — and css/moop.css already records that
                      // Bootstrap's grey outline is "indistinguishable from disabled
                      // text on a page whose every other control carries colour". ?>
                <button class="btn btn-outline-moop w-100 d-flex align-items-center justify-content-between mt-3"
                        type="button"
                        data-bs-toggle="collapse" data-bs-target="#advancedOptions"
                        aria-expanded="<?= $advanced_open ? 'true' : 'false' ?>" aria-controls="advancedOptions">
                    <span>
                        <i class="fa fa-sliders-h me-2"></i><strong>More options</strong>
                        <span class="fw-normal">(Primer Length, Tm, and more)</span>
                    </span>
                    <?php // No text-muted on the parenthetical: this button INVERTS to
                          // solid teal on hover, and muted grey on teal is unreadable. ?>
                    <i class="fa fa-chevron-down collapse-chevron"></i>
                </button>

                <div class="collapse <?= $advanced_open ? 'show' : '' ?>" id="advancedOptions">
                    <div class="border rounded p-3 mt-1">
                        <p class="small text-muted mb-3">
                            Every box below is optional. Left blank, it uses the value shown in grey,
                            which comes from the primer type you chose in step 2 — so changing the
                            primer type changes these too.
                        </p>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" style="max-width:40rem;">
                                <thead>
                                    <tr class="small text-muted">
                                        <th style="width:14rem;"></th>
                                        <th class="fw-normal">Minimum</th>
                                        <th class="fw-normal">Optimum</th>
                                        <th class="fw-normal">Maximum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th scope="row" class="fw-semibold small">
                                            Primer length
                                            <?= field_help(
                                                'How long each primer is, in bases. 18–25 is the usual window: '
                                                . 'shorter primers are cheaper but bind less specifically, longer '
                                                . 'ones cost more per base. Primer3 will not design above 36 bases '
                                                . 'whatever you put here.',
                                                'Primer length'
                                            ) ?>
                                        </th>
                                        <td><?php $option_field('size_min'); ?></td>
                                        <td><?php $option_field('size_opt'); ?></td>
                                        <td><?php $option_field('size_max'); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="fw-semibold small">
                                            Melting temperature (°C)
                                            <?= field_help(
                                                'The temperature at which half the primer is bound to its target. '
                                                . 'It sets the annealing temperature of your reaction, so the two '
                                                . 'primers of a pair should be close to each other. Around 60 °C '
                                                . 'suits most PCR; lower it for AT-rich sequence you cannot get '
                                                . 'primers from.',
                                                'Melting temperature'
                                            ) ?>
                                        </th>
                                        <td><?php $option_field('tm_min'); ?></td>
                                        <td><?php $option_field('tm_opt'); ?></td>
                                        <td><?php $option_field('tm_max'); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="fw-semibold small">
                                            GC content (%)
                                            <?= field_help(
                                                'The percentage of the primer that is G or C. Too low and it binds '
                                                . 'weakly; too high and it can fold on itself or stick where it '
                                                . 'should not. 40–60% is the usual window, but a very AT-rich '
                                                . 'genome may force you below it.',
                                                'GC content'
                                            ) ?>
                                        </th>
                                        <td><?php $option_field('gc_min'); ?></td>
                                        <td><?php $option_field('gc_opt'); ?></td>
                                        <td><?php $option_field('gc_max'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-sm-6 col-lg-3">
                                <label for="gc_clamp" class="form-label small fw-semibold mb-1">
                                    GC clamp
                                    <?= field_help(
                                        'How many of the last bases at the 3′ end must be G or C. A G or C there '
                                        . 'binds tightly and helps the polymerase get started. 1 is common. Be '
                                        . 'careful going higher — on a test sequence a clamp of 3 still worked, '
                                        . 'but 6 rejected every candidate and returned nothing.',
                                        'GC clamp'
                                    ) ?>
                                </label>
                                <?php $option_field('gc_clamp'); ?>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="tm_diff" class="form-label small fw-semibold mb-1">
                                    Max Tm difference
                                    <?= field_help(
                                        'How far apart the two primers of a pair may be in melting temperature. '
                                        . 'If they differ a lot, no single annealing temperature suits both. '
                                        . '2–3 °C is a tight, sensible setting; primer3 barely constrains this '
                                        . 'by default.',
                                        'Max Tm difference'
                                    ) ?>
                                </label>
                                <?php $option_field('tm_diff'); ?>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="max_poly_x" class="form-label small fw-semibold mb-1">
                                    Longest single-base run
                                    <?= field_help(
                                        'Rejects primers containing a run of the same base, like AAAAAA. Long '
                                        . 'runs slip during replication and bind in more places than they should. '
                                        . '5 is primer3\'s default; lower it if you keep getting primers that '
                                        . 'misbehave.',
                                        'Longest single-base run'
                                    ) ?>
                                </label>
                                <?php $option_field('max_poly_x'); ?>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="junction_5p" class="form-label small fw-semibold mb-1">
                                    Bases past a <code>|</code>, 5′ side
                                    <?= field_help(
                                        'How far a primer must reach beyond a junction mark on its 5′ side. '
                                        . 'This is what makes a junction primer actually FAIL on genomic DNA: '
                                        . 'the further it reaches past the boundary, the worse it anneals to '
                                        . 'the unspliced sequence. Primer3\'s default is 7.',
                                        'Overlap on the 5′ side'
                                    ) ?>
                                </label>
                                <?php $option_field('junction_5p'); ?>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="junction_3p" class="form-label small fw-semibold mb-1">
                                    Bases past a <code>|</code>, 3′ side
                                    <?= field_help(
                                        'The same, on the 3′ side — the end the polymerase extends from, so it '
                                        . 'matters most. Primer3\'s default is only 4, which is a thin margin: '
                                        . 'a primer hanging 4 bases over the boundary still anneals to genomic '
                                        . 'DNA reasonably well. Raise it if a junction pair has to be strict.',
                                        'Overlap on the 3′ side'
                                    ) ?>
                                </label>
                                <?php $option_field('junction_3p'); ?>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="salt_corr" class="form-label small fw-semibold mb-1">
                                    Salt correction
                                    <?= field_help(
                                        'Which published formula is used to account for salt when calculating Tm. '
                                        . 'It changes the Tm NUMBERS, not the chemistry — so it matters mainly if '
                                        . 'you are comparing these values against another tool and want the same '
                                        . 'formula. SantaLucia 1998 is primer3\'s default.',
                                        'Salt correction'
                                    ) ?>
                                </label>
                                <select class="form-select form-select-sm" id="salt_corr" name="salt_corr">
                                    <?php
                                    // A SELECT, not a number box, because this is a
                                    // choice of formula and the values in between mean
                                    // nothing. It also closes a silent failure: the
                                    // workflow this replaces took it as free text, and
                                    // primer3 answers a salt correction of 3 with zero
                                    // primers, no error and no explanation.
                                    $salt_options = [
                                        '0' => 'Schildkraut & Lifson 1965',
                                        '1' => 'SantaLucia 1998 (default)',
                                        '2' => 'Owczarzy 2004',
                                    ];

                                    // ⚠️ A SELECT CANNOT BE BLANK, so "left alone means
                                    // the default" has to be made true here. Without
                                    // this the browser preselects the FIRST option —
                                    // Schildkraut & Lifson — and merely submitting the
                                    // form untouched would switch the Tm formula away
                                    // from primer3's default and report the result as
                                    // the user's choice. Every neighbouring field gets
                                    // this for free by being empty; this one does not.
                                    $salt_current = ($options['salt_corr'] ?? '') !== ''
                                        ? (string)$options['salt_corr']
                                        : (string)($option_defaults['salt_corr'] ?? '1');

                                    // ⚠️ (string)$value is NOT redundant. PHP coerces the
                                    // numeric string keys '0'/'1'/'2' of $salt_options to
                                    // INTEGERS, so a strict comparison against the string
                                    // '1' is false and NO option is ever marked selected —
                                    // which is how this select came to show its first
                                    // entry regardless of what was chosen.
                                    foreach ($salt_options as $value => $label): ?>
                                        <option value="<?= (int)$value ?>" <?= $salt_current === (string)$value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4 — 5' tails -->
        <?php
        $tail_selected = $tail_choice !== '' && $tail_choice !== 'none';
        ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header text-white tool-header">
                <span class="step-badge me-2">4</span>
                <span class="fw-semibold" style="font-size:0.9rem;">5′ tail <span class="fw-normal">(optional)</span></span>
            </div>
            <div class="card-body py-3">
                <?php // What a tail IS lives behind the (i), not in a paragraph above the
                      // control. And the "every number is for the untailed primer" caveat
                      // is NOT here at all: at this point the user has no numbers and no
                      // tail, so it answers a question they have not asked yet and reads
                      // as a warning about something they cannot see. It now appears with
                      // the results, and only when a tail was actually used. ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tail" class="form-label mb-1">
                            <strong>Add a tail</strong>
                            <?= field_help(
                                'Extra sequence added to the 5′ end of each primer when you order it — a '
                                . 'cloning adapter, a promoter, a barcode. It is not part of the primer and '
                                . 'does not bind the template; it ends up in the PCR product, ready for '
                                . 'whatever you do next.',
                                '5′ tails'
                            ) ?>
                        </label>
                        <select name="tail" id="tail" class="form-select">
                            <option value="none" <?= $tail_selected ? '' : 'selected' ?>>No tail — plain primers</option>
                            <?php foreach ($tail_catalogue as $entry): ?>
                                <option value="<?= htmlspecialchars($entry['id']) ?>"
                                        <?= $tail_choice === $entry['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($entry['label']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="custom" <?= $tail_choice === 'custom' ? 'selected' : '' ?>>Custom tail…</option>
                        </select>

                        <?php // Each adapter's own sequences and purpose, so choosing one
                              // is an informed act rather than picking a name. Hidden by
                              // JS for the entries that are not selected. ?>
                        <?php foreach ($tail_catalogue as $entry): ?>
                            <div class="form-text tail-detail" data-tail="<?= htmlspecialchars($entry['id']) ?>"
                                 <?= $tail_choice === $entry['id'] ? '' : 'hidden' ?>>
                                <?php if ($entry['note'] !== ''): ?>
                                    <div class="mb-1"><?= htmlspecialchars($entry['note']) ?></div>
                                <?php endif; ?>
                                <div style="font-family:monospace;">
                                    <?php if ($entry['forward'] !== ''): ?>
                                        F <?= htmlspecialchars($entry['forward']) ?>
                                        <span class="text-muted">(<?= strlen($entry['forward']) ?> nt)</span><br>
                                    <?php endif; ?>
                                    <?php if ($entry['reverse'] !== ''): ?>
                                        R <?= htmlspecialchars($entry['reverse']) ?>
                                        <span class="text-muted">(<?= strlen($entry['reverse']) ?> nt)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="col-md-6" id="customTailFields" <?= $tail_choice === 'custom' ? '' : 'hidden' ?>>
                        <label for="tail_custom_forward" class="form-label mb-1">
                            <strong>Your own tail</strong>
                            <?= field_help(
                                'The two tails are independent sequences — they are NOT reverse complements '
                                . 'of one another, so enter each as you want it synthesised, written 5′ to 3′. '
                                . 'Leave one blank to tail only the other primer. A, C, G and T only: an oligo '
                                . 'is made exactly as written, so an ambiguity code is not orderable.',
                                'Custom tail'
                            ) ?>
                        </label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text" style="width:6.5rem;">Forward</span>
                            <input type="text" class="form-control" id="tail_custom_forward"
                                   name="tail_custom_forward" style="font-family:monospace;"
                                   maxlength="<?= (int)PrimerTails::MAX_LENGTH ?>"
                                   placeholder="e.g. CATTACCATCCCG"
                                   value="<?= htmlspecialchars($tail_custom_f) ?>">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="width:6.5rem;">Reverse</span>
                            <input type="text" class="form-control" id="tail_custom_reverse"
                                   name="tail_custom_reverse" style="font-family:monospace;"
                                   maxlength="<?= (int)PrimerTails::MAX_LENGTH ?>"
                                   placeholder="e.g. CCAATTCTACCCG"
                                   value="<?= htmlspecialchars($tail_custom_r) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" <?= $primer3_ok ? '' : 'disabled' ?>>
            <i class="fa fa-magic me-1"></i>Design primers
        </button>
        <a href="<?= htmlspecialchars('/' . $site . '/tools/primer_maker.php') ?>" class="btn btn-outline-secondary ms-2">Clear</a>
    </form>

    <?php if ($run_error): ?>
        <div class="alert alert-danger mt-4"><i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($run_error) ?></div>
    <?php endif; ?>

    <?php foreach ($notes as $note): ?>
        <div class="alert alert-info mt-3 small mb-0"><i class="fa fa-info-circle me-2"></i><?= htmlspecialchars($note) ?></div>
    <?php endforeach; ?>

    <?php
    // Results. Columns are keyed by NAME here and in the export, never by
    // position — the results-table work on 2026-07-23 found four defects caused
    // by positional column identity, and this table is explicitly meant to be
    // pasted into a spreadsheet.
    $has_tail = ($tail['forward'] ?? '') !== '' || ($tail['reverse'] ?? '') !== '';

    $columns = ['rank' => '#', 'left_sequence' => 'Forward primer'];
    if ($has_tail && ($tail['forward'] ?? '') !== '') {
        $columns['left_tailed'] = 'Forward to order';
    }
    $columns += [
        'left_tm'        => 'F Tm',
        'left_gc'        => 'F GC%',
        'left_start'     => 'F start',
        'right_sequence' => 'Reverse primer',
    ];
    if ($has_tail && ($tail['reverse'] ?? '') !== '') {
        $columns['right_tailed'] = 'Reverse to order';
    }
    $columns += [
        'right_tm'      => 'R Tm',
        'right_gc'      => 'R GC%',
        'right_end'     => 'R end',
        'product_size'  => 'Product (bp)',
    ];
    if ($has_tail) {
        // The band you actually see on a gel: the insert plus both tails. The
        // workflow this replaces only ever reported the first number, which is
        // the one you do NOT measure.
        $columns['product_size_tailed'] = 'Product + tails';
    }
    $columns['pair_penalty'] = 'Penalty';

    // Which columns hold a sequence, so the monospace rule is keyed by NAME
    // rather than by position — positional column identity is what produced four
    // silent defects in the other results table (CLAUDE.md §9b).
    $sequence_columns = ['left_sequence', 'right_sequence', 'left_tailed', 'right_tailed'];

    $export = [];
    $oligos = [];
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
                <?php if ($has_tail): ?>
                    <?php // Moved here from the tail step (user, 2026-08-07): above the form
                          // it warned about numbers that did not exist yet. Here it sits
                          // directly above the columns it is about, and only appears when
                          // a tail was actually applied. ?>
                    <div class="alert alert-info py-2 small">
                        <i class="fa fa-info-circle me-1"></i>
                        <strong>Tm, GC and length below are for the primer without its
                        <?= htmlspecialchars($tail['label']) ?> tail</strong> — that is the part that
                        anneals, so it is what sets your annealing temperature. Both sequences are in
                        the table, and <strong>Check</strong> sends the untailed primer to Primer
                        BLAST, since the tail is not in the genome.
                    </div>
                <?php endif; ?>
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
                                    <?php $is_seq = in_array($key, $sequence_columns, true); ?>
                                    <td class="<?= $is_seq ? '' : 'small' ?>"
                                        <?= $is_seq ? 'style="font-family:monospace;"' : '' ?>><?php
                                        if ($is_seq && in_array($key, ['left_tailed','right_tailed'], true) && $v !== '') {
                                            // The tail is drawn apart from the primer so it is
                                            // obvious which part anneals and which part is
                                            // just along for the ride. Same string either
                                            // way — copying the cell still gives the whole
                                            // oligo, which is what gets ordered.
                                            $tail_seq = $key === 'left_tailed' ? $tail['forward'] : $tail['reverse'];
                                            echo '<span class="text-muted">' . htmlspecialchars($tail_seq) . '</span>'
                                               . htmlspecialchars(substr((string)$v, strlen($tail_seq)));
                                        } else {
                                            echo htmlspecialchars((string)$v);
                                        }
                                    ?></td>
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

                <?php // The Check/tail caveat used to be repeated here, below the table.
                      // It is stated once now, in the block ABOVE the table — where it is
                      // read before the numbers rather than after them. ?>
                <?php
                // Every output surface — table, FASTA, TSV — is rendered from ONE
                // set of records. The workflow this replaces had the table showing
                // tailed and untailed side by side while the FASTA block below it
                // emitted only the untailed form, its tailed branch commented out.
                // So the file you downloaded quietly disagreed with the table you
                // were reading.
                $entry_oligos = PrimerTails::oligoRecords($entry['id'], $entry['pairs'], $tail);
                $oligos = array_merge($oligos, $entry_oligos);
                ?>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadPrimerTsv">
                        <i class="fa fa-download me-1"></i>Download table (TSV)
                    </button>
                    <?php // Same disclosure affordance as the options toggle above — it
                          // expands in place, so it gets the same chevron rather than
                          // looking like a button that does something. ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                            data-bs-target="#oligoFasta" aria-expanded="false" aria-controls="oligoFasta">
                        <i class="fa fa-align-left me-1"></i>Oligos to order
                        <span class="badge bg-secondary ms-1"><?= count($entry_oligos) ?></span>
                        <i class="fa fa-chevron-down collapse-chevron ms-1"></i>
                    </button>
                </div>

                <div class="collapse mt-3" id="oligoFasta">
                    <p class="small text-muted mb-2">
                        <?php if ($has_tail): ?>
                            Both forms of every primer: the bare primer, and the full oligo with its
                            <?= htmlspecialchars($tail['label']) ?> tail. <strong>Order the tailed
                            ones</strong> — the bare sequences are here so you can check specificity
                            and paste them into Primer BLAST.
                        <?php else: ?>
                            Every primer in the table, ready to paste into an order form or a FASTA file.
                        <?php endif; ?>
                    </p>
                    <textarea class="form-control" rows="<?= min(20, max(4, count($entry_oligos) * 2)) ?>"
                              readonly style="font-family:monospace; font-size:0.8rem;"><?php
                        foreach ($entry_oligos as $oligo) {
                            echo '>' . htmlspecialchars($oligo['name'])
                               . ' len=' . (int)$oligo['length']
                               . ($oligo['tailed'] ? ' (with tail)' : '')
                               . "\n" . htmlspecialchars($oligo['sequence']) . "\n";
                        }
                    ?></textarea>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($export): ?>
        <?php // Data for the TSV download. The header is derived from the row keys
              // in js/primer-maker.js, so it cannot drift from the table above. ?>
        <script type="application/json" id="primerMakerExport"><?= json_encode($export) ?></script>
        <script type="application/json" id="primerMakerOligos"><?= json_encode($oligos) ?></script>
    <?php endif; ?>

</div>
