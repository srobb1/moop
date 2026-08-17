<?php
/**
 * PRIMER MAKER — design primers with Primer3.
 *
 * ⚠️ FIRST DRAFT, 2026-08-07. Working end to end, but see "Still to do" at the
 * bottom of this comment before treating anything here as settled.
 *
 * The design half of the primer tools. Primer BLAST (tools/primer_blast.php)
 * CHECKS pairs that already exist; this one MAKES them. They are separate pages
 * sharing lib/primer/ — decided 2026-08-06 — and chain by handing data across,
 * not by one page holding both modes.
 *
 * ONE PAGE FOR EVERY KIND OF PRIMER, by decision (user, 2026-08-07). The inputs
 * are identical whatever you are making, and what actually differs between
 * standard PCR, qPCR and RT-PCR is a set of Primer3 PARAMETERS plus, for RT-PCR,
 * one structural constraint. Primer3 itself is one binary driven by different
 * tags; splitting the UI would invent a distinction the tool underneath does not
 * have, and would duplicate the results table — which is the piece the user
 * pastes into a spreadsheet, and the piece that grew four silent defects the
 * last time it existed three times over.
 *
 * TWO WAYS IN, also by decision: paste your own sequence, or arrive from a gene
 * page with the transcript already chosen. Gene pages and the Tools menu only —
 * not every page, unlike Primer BLAST.
 *
 * DESIGN OPTIONS (2026-08-07): primer length, Tm, GC content, GC clamp, poly-X
 * and salt correction are all exposed, matching the controls the workflow this
 * replaces offered. Every one of them is BLANK BY DEFAULT and falls back to the
 * chosen primer type's value — so the preset stays meaningful and a user only
 * overrides what they actually care about. The field table, the bounds and the
 * min ≤ opt ≤ max rules live in Primer3Design::OPTIONS, not here.
 *
 * 5' TAILS (2026-08-07): T4P and friends, from a configurable catalogue plus
 * free text. ⛔ Tails are an OUTPUT ONLY (user, 2026-08-17): "i dont want to add
 * the oligos to the input, but only to the output. they should not be involved
 * in the testing of the output alignment to the genome either. users should only
 * add their primers minus oligos for the testing in our other tool." So they are
 * appended after primer3 has run, they never touch a Tm or GC number, and the
 * per-row Check button hands Primer BLAST the BARE primer. PrimerTails only ever
 * adds keys, which makes that true by construction rather than by discipline.
 *
 * Still to do:
 *   - Genomic sequence as a template. genome.fa holds chromosomes, so a gene id
 *     is not a record in it and the sequence has to come from a coordinate
 *     slice — which is how the Sequences section does it. Until then the
 *     hand-off button appears only for transcript and CDS.
 *   - Dash-separated pasted sequence as an alternative way to mark a junction.
 *   - Whether Retrieve Sequences should offer the same hand-off; it shares
 *     sequences_display.php, and the button is currently opted into by the gene
 *     page alone.
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/primer/Primer3.php';
include_once __DIR__ . '/../lib/primer/Primer3Design.php';
include_once __DIR__ . '/../lib/primer/PrimerTails.php';
include_once __DIR__ . '/../lib/primer/SequenceMarkup.php';
include_once __DIR__ . '/../lib/primer/ExonMap.php';
include_once __DIR__ . '/../lib/gene_isoforms.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../includes/source-selector-helpers.php';

$site      = $config->getString('site');
$siteTitle = $config->getString('siteTitle');

// ------------------------------------------------------------------ presets
//
// What "kind of primer" actually means: a handful of Primer3 numbers. Kept in
// one array so the selector, the help and the run all read the same values —
// the alternative is a switch in the controller and a description in the view
// that quietly disagree.
//
// ⚠️ THE BLURB SAYS WHY YOU WOULD PICK IT; THE NUMBERS ARE NOT REPEATED THERE.
// Step 2 renders each preset's actual values as badges, derived from 'params'
// merged over Primer3Design::DEFAULTS — so they cannot drift. Numbers written
// out in prose here would be a SECOND source of truth for the same fact, and
// they had already gone stale in two of the four: qPCR said "tighter Tm" without
// saying tighter than what, and RT-PCR never mentioned its product range at all.
$PRESETS = [
    'standard' => [
        'label'  => 'Standard PCR',
        'blurb'  => 'General-purpose primers — cloning, screening, checking a band on a gel.',
        'params' => ['PRIMER_PRODUCT_SIZE_RANGE' => '100-400'],
    ],
    'qpcr' => [
        'label'  => 'qPCR',
        'blurb'  => 'Short products, which amplify efficiently enough to be measured in real time.',
        'params' => [
            'PRIMER_PRODUCT_SIZE_RANGE' => '70-150',
            'PRIMER_OPT_TM'             => 60.0,
            'PRIMER_MIN_TM'             => 58.0,
            'PRIMER_MAX_TM'             => 62.0,
        ],
    ],
    'rtpcr' => [
        'label'  => 'RT-PCR (spans an exon junction)',
        // "or pick a transcript below" was confusing (user, 2026-08-07): the
        // transcript picker only exists when you arrived from a gene page whose
        // gene has several isoforms. Anyone who pasted a sequence went looking
        // for a control that was not on the page.
        'blurb'  => 'Forces a primer across an exon junction, so the pair cannot amplify '
                  . 'contaminating genomic DNA. Needs to know where the junctions are — arrive '
                  . 'from a gene page and they are looked up for you.',
        'params' => ['PRIMER_PRODUCT_SIZE_RANGE' => '70-300'],
    ],
    'sequencing' => [
        'label'  => 'Sequencing',
        'blurb'  => 'Longer products, for reading right through a region in one go.',
        'params' => [
            'PRIMER_PRODUCT_SIZE_RANGE' => '400-900',
            'PRIMER_MIN_TM'             => 55.0,
            'PRIMER_MAX_TM'             => 65.0,
        ],
    ],
];

// --------------------------------------------------------------------- input
// GET too: the Sequences section on a gene page links here with the type
// already chosen (a transcript means RT-PCR), and reading POST only made
// that link silently fall back to Standard PCR.
$primer_type = $_POST['primer_type'] ?? $_GET['primer_type'] ?? 'standard';
if (!isset($PRESETS[$primer_type])) {
    $primer_type = 'standard';
}

$sequence_text = trim($_POST['sequence'] ?? '');
$num_return    = max(1, min(20, (int)($_POST['num_return'] ?? 5)));
$size_range    = trim($_POST['size_range'] ?? '');
$longest       = isset($_POST['longest_product']);

// Advanced design options. Held as raw strings so the form can echo back exactly
// what was typed when something is rejected — re-rendering a cast value would
// show a "corrected" number the user never entered.
$options = [];
foreach (array_keys(Primer3Design::OPTIONS) as $field) {
    $options[$field] = trim($_POST[$field] ?? '');
}

// "The product must contain this region", as start,length — the SEQUENCE_TARGET
// field from the original web form. Not part of OPTIONS because it is a
// SEQUENCE_ tag, not a global: it belongs to one template and has to be checked
// against that template's length, which OPTIONS knows nothing about.
$target = trim($_POST['target'] ?? '');

// ------------------------------------------------------------------ 5' tails
$tail_catalogue = PrimerTails::catalogue($config->getArray('primer_tails'));
$tail_choice    = trim($_POST['tail'] ?? 'none');
$tail_custom_f  = trim($_POST['tail_custom_forward'] ?? '');
$tail_custom_r  = trim($_POST['tail_custom_reverse'] ?? '');
$tail = PrimerTails::resolve($tail_choice, $tail_custom_f, $tail_custom_r, $tail_catalogue);

// Context from a gene page: organism / assembly / gene set, and the feature the
// user was looking at. Accepted from GET so the toolbox link carries it.
$context_organism = trim($_GET['organism']  ?? $_POST['organism']  ?? '');
$context_assembly = trim($_GET['assembly']  ?? $_POST['assembly']  ?? '');
$context_gene_set = trim($_GET['gene_set']  ?? $_POST['gene_set']  ?? '');
$context_feature  = trim($_GET['feature']   ?? $_POST['feature']   ?? '');

$results       = [];
$run_error     = null;
$notes         = [];
$junctions     = [];
$template_id   = '';
$option_errors = $tail['errors'];   // a bad tail is a design option like any other

$p3 = Primer3::status();

// ------------------------------------------------- isoforms, when we came from a gene
//
// Arriving from a gene page, the useful thing is the sequence already in the
// box. A gene can have several transcripts though — 7 for one Nematostella gene
// here — and they differ in exactly the way that matters for primer design, so
// picking one for the user would be guessing. One isoform: prefill it, no
// question asked. Several: ask, and prefill on choosing.
$isoforms        = [];
$selected_isoform = trim($_POST['isoform'] ?? $_GET['isoform'] ?? '');
$gene_set_path   = '';

// Which FASTA to take the sequence from. The Sequences section on a gene page
// links here with this set, so "Design primers from this sequence" fetches the
// SAME sequence the reader was looking at — by id, rather than the page posting
// a copy of it.
$seq_type = trim($_GET['seq_type'] ?? $_POST['seq_type'] ?? 'transcript');
if (!in_array($seq_type, moop_primer_sequence_types(), true)) {
    $seq_type = 'transcript';
}

if ($context_organism !== '' && $context_assembly !== '' && $context_gene_set !== '' && $context_feature !== '') {
    $gene_set_path = $config->getPath('organism_data') . '/' . $context_organism
                   . '/' . $context_assembly . '/' . $context_gene_set;
    $isoforms = moop_gene_isoforms($gene_set_path, $context_feature);

    // Only isoforms that actually have sequence can be offered. One that does
    // not is still listed, disabled, rather than silently dropped — a gene that
    // shows 6 of 7 transcripts with no explanation looks like missing data.
    $with_seq = array_values(array_filter($isoforms, fn($i) => $i['fasta_id'] !== ''));

    if ($selected_isoform === '' && count($with_seq) === 1) {
        $selected_isoform = $with_seq[0]['id'];
    }

    // Prefill when the box is empty, or when the user explicitly asked to load
    // a transcript. Never silently overwrite something they pasted — that is
    // why "Load this sequence" is its own button rather than an onchange.
    $load_isoform = isset($_POST['load_isoform']);
    if ($selected_isoform !== ''
        && ($load_isoform || $_SERVER['REQUEST_METHOD'] !== 'POST' || trim($_POST['sequence'] ?? '') === '')) {
        $seq = moop_transcript_sequence($gene_set_path, $selected_isoform, $seq_type);
        if ($seq !== '') {
            $sequence_text = '>' . preg_replace('/^(?:rna|cds|gene|id)-/', '', $selected_isoform)
                           . "\n" . chunk_split($seq, 60, "\n");
        }
    }
}

// ------------------------------------------------------------------ the run
// "Load this sequence" is a POST, but it is not a request to DESIGN anything —
// it just swaps the transcript into the box. Running primer3 on it would answer
// a question the user has not asked yet, and would bury the newly loaded
// sequence under a table.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_POST['load_isoform'])   // "load this transcript" — swap the box, do not design
    && !isset($_POST['prefill'])) {     // arrived from a gene page's Sequences section
    if (!$p3['ok']) {
        $run_error = $p3['problem'] === 'missing'
            ? 'primer3 is not installed on this server, so primers cannot be designed. '
              . 'An administrator can install it with scripts/install_primer3.sh.'
            : 'primer3 is installed but its thermodynamic parameters are missing, so it cannot '
              . 'run. An administrator can fix this by re-running scripts/install_primer3.sh.';
    } elseif ($sequence_text === '') {
        $run_error = 'Paste a sequence to design primers from.';
    } else {
        // Accept FASTA or bare sequence. Only the first record is used for now;
        // multi-record input is a Monday question, because the results table and
        // the junction handling both need to say WHICH sequence a row came from.
        $template_id = 'sequence';
        $lines = preg_split('/\r\n|\r|\n/', $sequence_text);
        $marked = '';
        foreach ($lines as $line) {
            if (strlen($line) && $line[0] === '>') {
                if ($marked !== '') break;              // second record: stop
                $template_id = trim(substr($line, 1)) ?: 'sequence';
                continue;
            }
            // Keep the markup characters; SequenceMarkup separates DNA from
            // instructions. Stripping to [A-Za-z] here, as this used to, silently
            // ate every mark the user typed.
            $marked .= preg_replace('/[^A-Za-z|\[\]-]/', '', $line);
        }

        $markup = SequenceMarkup::parse($marked);
        $seq    = $markup['sequence'];

        // ⚠️ Markup notes are held back until we know the design will actually
        // run. Merged here, an unclosed bracket printed "Every product will
        // contain bases 301–400" beside the error saying nothing was designed.
        $markup_notes = $markup['notes'];

        if ($markup['errors']) {
            // Report a markup problem AS a markup problem. parse() returns an
            // empty sequence when it gives up, so falling through to the checks
            // below answered an unclosed "[" with "that sequence is too short to
            // design primers from" — true of the empty string, and useless.
            $run_error = implode(' ', $markup['errors']);
        } elseif (strlen($seq) < 60) {
            $run_error = 'That sequence is too short to design primers from — 60 bp is about the '
                       . 'minimum, and more is better.';
        } elseif (preg_match('/[^ACGTN]/', $seq)) {
            $run_error = 'The sequence contains characters that are not DNA bases (A, C, G, T, N).';
        } else {
            $record = ['id' => $template_id, 'template' => $seq];

            // A region the product must span — a SNP, an exon, a domain. Checked
            // against THIS template rather than left to primer3, whose answer to
            // a target past the end is a tag-named error about an included
            // region the user never mentioned.
            // ---- regions the product must contain ----
            // Two ways to say it, and saying it twice is ambiguous rather than
            // emphatic — so both at once is an error, not a silent precedence rule.
            $targets = $markup['targets'];
            $parsed_target = Primer3Design::parseTarget($target, strlen($seq));
            if ($parsed_target['error'] !== '') {
                $option_errors[] = $parsed_target['error'];
            } elseif ($parsed_target['target'] !== null) {
                if ($targets) {
                    $option_errors[] = 'You marked a region with [ ] in the sequence AND filled in '
                                     . '"Region to include". Use one or the other, so it is clear '
                                     . 'which you meant.';
                } else {
                    $targets[] = $parsed_target['target'];
                    $notes[] = 'Every pair below amplifies across positions '
                             . number_format($parsed_target['first']) . '–'
                             . number_format($parsed_target['last']) . '.';
                }
            }
            if ($targets) {
                // parseTarget already returns a 0-based start; markup targets are
                // 1-based, so they convert here — one place, like the junctions.
                $record['targets'] = [];
                foreach ($targets as $t) {
                    $record['targets'][] = $t === $parsed_target['target'] ? $t : [$t[0] - 1, $t[1]];
                }
            }

            // ---- points a primer must cross ----
            //
            // ⭐ NOT AN RT-PCR FEATURE. A "|" works for every primer type — a
            // fusion breakpoint, a vector/insert boundary, a scaffold join. RT-PCR
            // is just the case where MOOP can supply the marks itself.
            $junctions      = $markup['junctions'];
            $junction_source = $junctions ? 'marks' : '';

            if (!$junctions && $primer_type === 'rtpcr'
                && $context_organism && $context_assembly && $context_gene_set) {

                $gs_path = $config->getPath('organism_data') . '/' . $context_organism
                         . '/' . $context_assembly . '/' . $context_gene_set;

                // 🚨 VERIFY THE SEQUENCE, DO NOT TRUST THE NAME.
                // The exon index is keyed by the FASTA header, and the header is
                // just text in a textarea the user can edit. Before this check,
                // keeping the header ">XM_001626548.3" and pasting an unrelated
                // 900 bp sequence reported "5 exon junctions found" and designed
                // primers using positions from a 1,332 bp transcript. Tracking
                // "did they come from a gene page" would NOT have caught it —
                // provenance flags survive an edit; comparing the bases does not.
                $stored = moop_transcript_sequence($gs_path, $template_id, 'transcript');
                if ($stored !== '' && strtoupper(preg_replace('/\s+/', '', $stored)) === $seq) {
                    $recs = ExonMap::load($gs_path, [$template_id]);
                    if (!empty($recs[$template_id])) {
                        $offset = 0;
                        $exons  = $recs[$template_id]['exons'];
                        if ($recs[$template_id]['strand'] === '-') {
                            $exons = array_reverse($exons);
                        }
                        foreach (array_slice($exons, 0, -1) as list($s, $e)) {
                            $offset     += $e - $s + 1;
                            $junctions[] = $offset;   // junction FOLLOWS this transcript base
                        }
                        $junction_source = 'index';
                    }
                } elseif ($stored !== '') {
                    $notes[] = 'The sequence in the box is not ' . $template_id . ' as it is stored '
                             . 'here, so its exon positions do not apply and were not used. Mark the '
                             . 'junctions yourself with "|", or reload the transcript.';
                }
            }

            if ($junctions) {
                $record['junctions'] = $junctions;
                if ($junction_source === 'index') {
                    $notes[] = count($junctions) . ' exon junction'
                             . (count($junctions) === 1 ? '' : 's')
                             . ' looked up for ' . $template_id
                             . '; a primer must cross one of them.';
                }
            } elseif ($primer_type === 'rtpcr') {
                $notes[] = 'No exon junctions are known for this sequence, so nothing forced a primer '
                         . 'across one — these are ordinary primers. Either arrive from a gene page, '
                         . 'or mark the junctions yourself by putting "|" in the sequence.';
            }

            $params = $PRESETS[$primer_type]['params'];
            $params['PRIMER_NUM_RETURN'] = $num_return;

            // Product size. Three ways to say it, in order of precedence:
            //   "as long as possible"  → a ladder built from the template
            //   "low-high"             → exactly that range
            //   a single number        → a ladder capped at that number, which is
            //                            what the old "biggest product you would
            //                            want" field meant
            if ($longest) {
                $ladder = Primer3Design::productSizeLadder(strlen($seq));
                if ($ladder !== '') {
                    $params['PRIMER_PRODUCT_SIZE_RANGE'] = $ladder;
                    $notes[] = 'Asking for the longest product this sequence allows, trying '
                             . str_replace(' ', ' bp, then ', $ladder) . ' bp.';
                } else {
                    $notes[] = 'This sequence is too short to ask for the longest possible product, '
                             . 'so the usual range for ' . strtolower($PRESETS[$primer_type]['label'])
                             . ' was used instead.';
                }
            } elseif (preg_match('/^\d+\s*-\s*\d+$/', $size_range)) {
                $params['PRIMER_PRODUCT_SIZE_RANGE'] = preg_replace('/\s+/', '', $size_range);
            } elseif (preg_match('/^\d+$/', $size_range)) {
                $ladder = Primer3Design::productSizeLadder((int)$size_range);
                if ($ladder !== '') {
                    $params['PRIMER_PRODUCT_SIZE_RANGE'] = $ladder;
                    $notes[] = 'Read ' . $size_range . ' as the largest product you want; trying '
                             . str_replace(' ', ' bp, then ', $ladder) . ' bp.';
                } else {
                    $option_errors[] = 'A product of ' . $size_range . ' bp is smaller than the 100 bp '
                                     . 'floor. Give a range like 60-' . $size_range . ' instead.';
                }
            } elseif ($size_range !== '') {
                $option_errors[] = 'Product size range: "' . $size_range . '" is not a range. Use '
                                 . 'low-high, like 150-400, or a single number for the biggest '
                                 . 'product you want.';
            }

            // Advanced options last, so an explicit Tm or GC beats the preset's.
            // Validated against the preset-merged values, not against the bare
            // defaults — otherwise setting a Tm that conflicts with the CHOSEN
            // primer type would pass here and fail inside primer3.
            $opt = Primer3Design::optionParams($options, $params);
            $option_errors = array_merge($option_errors, $opt['errors']);
            $params = array_merge($params, $opt['params']);

            if ($option_errors) {
                // Nothing is run. primer3 would either error with tag names the
                // user never typed, or — for a salt correction of 3 or a GC clamp
                // of 6 — return zero pairs with no error at all, which reads as
                // "your sequence is difficult" rather than "that setting is out
                // of range".
                $run_error = 'Check the design options: ' . implode(' ', $option_errors);
            } else {
            // Safe to say what the marks did, now that we know the run happens.
            $notes = array_merge($markup_notes, $notes);

            $run = Primer3Design::run([$record], $params);
            if (!$run['success']) {
                // primer3 speaks in tag names. Translated where we can, passed
                // through untouched where we cannot — see friendlyError().
                $run_error = Primer3Design::friendlyError($run['error'], strlen($seq));
            } else {
                $results = $run['results'];

                // Tails go on HERE — after every statistic has been computed on
                // the bare primer, and as extra keys beside it rather than over
                // it. left_sequence/right_sequence still hold the untailed
                // primer, which is what the Check button hands to Primer BLAST.
                if (($tail['forward'] ?? '') !== '' || ($tail['reverse'] ?? '') !== '') {
                    $longest_oligo = 0;
                    foreach ($results as $ri => $r) {
                        foreach ($r['pairs'] as $pi => $pair) {
                            $tailed = PrimerTails::apply($pair, $tail);
                            $results[$ri]['pairs'][$pi] = $tailed;
                            $longest_oligo = max(
                                $longest_oligo,
                                (int)($tailed['left_oligo_length'] ?? 0),
                                (int)($tailed['right_oligo_length'] ?? 0)
                            );
                        }
                    }

                    // A cost, not a mistake — so it is a note, not a warning. The
                    // tail is what pushes an oligo over the threshold: a 20-mer is
                    // fine, the same primer with a T7 promoter is 40.
                    if ($longest_oligo > PrimerTails::LONG_OLIGO) {
                        $notes[] = 'With the tail, the longest oligo here is ' . $longest_oligo
                                 . ' bases. Above about ' . PrimerTails::LONG_OLIGO . ' most vendors '
                                 . 'charge more and suggest purification beyond standard desalting — '
                                 . 'worth checking before you order. The primer itself is unaffected: '
                                 . 'every Tm, GC and length figure below is for the untailed primer, '
                                 . 'which is the part that anneals.';
                    }
                }

                foreach ($results as $r) {
                    if (empty($r['pairs'])) {
                        // primer3's own explanation, which says WHY — "GC clamp
                        // failed 803, low tm 3, high tm 63". Far more useful than
                        // a bare "no primers found", and now that the page lets
                        // a user tighten Tm, GC and the clamp themselves, it is
                        // the only thing that says WHICH knob to turn back.
                        //
                        // ⚠️ It only exists because PRIMER_EXPLAIN_FLAG is set in
                        // Primer3Design::DEFAULTS. It was not, until 2026-08-07,
                        // so this branch read "no explanation given" every time.
                        // WHICH explanation is the useful one depends on where it
                        // failed, and picking the pair one unconditionally gets it
                        // wrong in the commonest case. When no individual primer
                        // survived, the pair line reads "considered 0, ok 0" —
                        // true, and useless — while the left/right lines carry the
                        // actual tally ("GC clamp failed 803, high tm 63"). Only
                        // when single primers WERE found and no combination worked
                        // does the pair line hold the answer.
                        $explain = '';
                        if ($r['explain']['pair'] !== '' && strpos($r['explain']['pair'], 'considered 0,') !== 0) {
                            $explain = 'pairing them failed — ' . $r['explain']['pair'];
                        } else {
                            $sides = array_filter([
                                $r['explain']['left']  !== '' ? 'forward: ' . $r['explain']['left']  : '',
                                $r['explain']['right'] !== '' ? 'reverse: ' . $r['explain']['right'] : '',
                            ]);
                            $explain = implode('; ', $sides);
                        }

                        $notes[] = $explain !== ''
                            ? 'No primers met the criteria. Primer3 says — ' . $explain
                              . '. The largest count names the setting to loosen first.'
                            : 'No primers met the criteria. Try widening the product size range, '
                              . 'the Tm window, or the GC range.';
                    }
                }
            }
            }
        }
    }
}

// ------------------------------------------------ what a blank box will use
//
// Derived from the SAME arrays the run uses, so a placeholder cannot claim one
// number while primer3 is given another. Built for every primer type, not just
// the selected one, so switching the radio can update the placeholders in the
// browser without a round trip.
$preset_option_defaults = [];
foreach ($PRESETS as $preset_key => $preset) {
    $effective = array_merge(Primer3Design::DEFAULTS, $preset['params']);

    $preset_option_defaults[$preset_key] = [];
    foreach (Primer3Design::OPTIONS as $field => $spec) {
        // Tags MOOP does not set (GC clamp, poly-X, salt correction, Tm spread)
        // fall back to primer3's own default, which OPTIONS records — so a blank
        // box still shows the number that will be used rather than nothing.
        $preset_option_defaults[$preset_key][$field] =
            $effective[$spec['tag']] ?? $spec['p3_default'] ?? '';
    }
    $preset_option_defaults[$preset_key]['size_range'] = $effective['PRIMER_PRODUCT_SIZE_RANGE'] ?? '';
}
$option_defaults = $preset_option_defaults[$primer_type];

// -------------------------------------------------------------------- render
$data = [
    'site'             => $site,
    'siteTitle'        => $siteTitle,
    'config'           => $config,
    'presets'          => $PRESETS,
    'primer_type'      => $primer_type,
    'sequence_text'    => $sequence_text,
    'num_return'       => $num_return,
    'size_range'       => $size_range,
    'results'          => $results,
    'run_error'        => $run_error,
    'notes'            => $notes,
    'junctions'        => $junctions,
    'template_id'      => $template_id,
    'primer3_ok'       => $p3['ok'],
    'primer3_problem'  => $p3['problem'],
    'context_organism' => $context_organism,
    'context_assembly' => $context_assembly,
    'context_gene_set' => $context_gene_set,
    'context_feature'  => $context_feature,
    'isoforms'         => $isoforms,
    'selected_isoform' => $selected_isoform,
    'seq_type'         => $seq_type,

    'options'          => $options,
    'longest'          => $longest,
    'target'           => $target,
    // Every primer type's effective values, so step 2 can SHOW what each one
    // actually does rather than only describing what it is for. Same array the
    // placeholders and the run read, so a card cannot advertise a number primer3
    // is not given.
    'preset_options'   => $preset_option_defaults,
    'option_specs'     => Primer3Design::OPTIONS,
    // What each field falls back to when left blank, for THIS primer type. The
    // view shows these as placeholders, so an empty box still tells the user the
    // number it is going to use.
    'option_defaults'  => $option_defaults,
    'tail_catalogue'   => $tail_catalogue,
    'tail'             => $tail,
    'tail_choice'      => $tail_choice,
    'tail_custom_f'    => $tail_custom_f,
    'tail_custom_r'    => $tail_custom_r,

    'page_styles'      => [
        '/' . $site . '/css/display.css',
    ],
];

$display_config = [
    'title'        => 'Primer Maker',
    'content_file' => __DIR__ . '/pages/primer_maker.php',
    'page_script'  => '/' . $site . '/js/primer-maker.js',
    'inline_scripts' => [
        // Every primer type's fallback values, so switching the radio updates
        // the placeholders without a round trip. Passing PHP values to JS is
        // exactly what inline_scripts is for (CLAUDE.md §7); the behaviour
        // itself lives in js/primer-maker.js.
        'const primerOptionDefaults = ' . json_encode($preset_option_defaults) . ';',
        'const primerTailCatalogue = ' . json_encode($tail_catalogue) . ';',
    ],
];

include_once __DIR__ . '/display-template.php';
