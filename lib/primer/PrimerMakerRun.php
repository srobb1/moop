<?php
/**
 * One Primer Maker design run: pasted FASTA in, primer pairs (and the sequences that gave none,
 * with why) out. Shared by the page (tools/primer_maker.php) and the API (api/v1/primer_maker.php),
 * so an agent and a person get the same primers for the same input -- moved here unchanged from
 * the page's controller on 2026-11-24.
 *
 * $req keys (the page's form fields, already read and trimmed):
 *   sequence, primer_type, presets (PrimerPresets::forSite()), num_return, size_range, longest,
 *   options (Primer3Design::OPTIONS field => raw string), target, tail (PrimerTails::resolve()),
 *   organism / assembly / gene_set -- ONLY when the caller has checked access to that gene set;
 *   they let RT-PCR look up the exon junctions of a transcript taken from it.
 *
 * Returns results (Primer3Design::run()'s, plus product_sequence and any tail keys), failures
 * ([name, reason] per sequence that produced nothing), notes, run_error (null = it ran),
 * junctions and template_id (of the last record, as the page has always shown).
 */

require_once __DIR__ . '/Primer3.php';
require_once __DIR__ . '/Primer3Design.php';
require_once __DIR__ . '/PrimerTails.php';
require_once __DIR__ . '/PrimerNames.php';
require_once __DIR__ . '/SequenceMarkup.php';
require_once __DIR__ . '/ExonMap.php';
require_once __DIR__ . '/../gene_isoforms.php';     // moop_transcript_sequence()
require_once __DIR__ . '/../genomic_regions.php';   // moop_genome_kind()

/**
 * Primer types that must span an exon junction. qPCR is NOT one: it needs no junction (Sofia,
 * 2026-11-03); it is a short-product, tight-Tm preset that works on any template.
 */
const PRIMER_JUNCTION_TYPES = ['rtpcr'];

/** Whether an assembly is transcripts only (one per gene) -- no exon-intron boundaries known. */
function primer_maker_is_transcripts(string $organism, string $assembly): bool
{
    if ($organism === '' || $assembly === '') return false;
    return moop_genome_kind(ConfigManager::getInstance()->getPath('organism_data') . '/' . $organism . '/' . $assembly) === 'transcripts';
}

function primer_maker_design(array $req): array
{
    $config        = ConfigManager::getInstance();
    $sequence_text = trim((string) ($req['sequence'] ?? ''));
    $PRESETS       = $req['presets'];
    $primer_type   = $req['primer_type'];
    $num_return    = (int) ($req['num_return'] ?? 5);
    $size_range    = trim((string) ($req['size_range'] ?? ''));
    $longest       = !empty($req['longest']);
    $options       = $req['options'] ?? [];
    $target        = trim((string) ($req['target'] ?? ''));
    $tail          = $req['tail'];
    $context_organism = (string) ($req['organism'] ?? '');
    $context_assembly = (string) ($req['assembly'] ?? '');
    $context_gene_set = (string) ($req['gene_set'] ?? '');
    $context_is_transcripts = primer_maker_is_transcripts($context_organism, $context_assembly);
    $JUNCTION_TYPES = PRIMER_JUNCTION_TYPES;

    $results       = [];
    $run_error     = null;
    $notes         = [];
    $junctions     = [];
    $template_id   = '';
    $failures      = [];
    $option_errors = $tail['errors'] ?? [];   // a bad tail is a design option like any other
    $p3            = Primer3::status();

    if (!$p3['ok']) {
        $run_error = $p3['problem'] === 'missing'
            ? 'primer3 is not installed on this server, so primers cannot be designed. '
              . 'An administrator can install it with scripts/install_primer3.sh.'
            : 'primer3 is installed but its thermodynamic parameters are missing, so it cannot '
              . 'run. An administrator can fix this by re-running scripts/install_primer3.sh.';
    } elseif ($sequence_text === '') {
        $run_error = 'Paste a sequence to design primers from.';
    } elseif ($context_is_transcripts && in_array($primer_type, $JUNCTION_TYPES, true)
              && strpos($sequence_text, '|') === false) {
        $run_error = $PRESETS[$primer_type]['label'] . ' primers cannot be designed for this sequence. '
            . 'This assembly is transcripts only (one per gene), not a genome, so its exon–intron '
            . 'boundaries are not known, and ' . $PRESETS[$primer_type]['label'] . ' primers must '
            . 'span an exon junction so they cannot amplify genomic DNA. Choose Standard PCR, or '
            . 'mark the junctions yourself with "|" in the sequence if you know them.';
    } else {
        // ⭐ SEVERAL SEQUENCES, BUT ONLY IN FASTA (user, 2026-08-18). Every '>'
        // starts a record; a paste with no header is one sequence however many
        // lines it spans. Each record is named, marked up, junction-checked and
        // reported on INDEPENDENTLY — the reason multi-record input waited this
        // long is that a results table has to say which sequence a row came from,
        // and it now does, in the primer names themselves.
        $raw_records  = Primer3Design::splitRecords($sequence_text);
        $records      = [];   // what primer3 is given
        $markup_notes = [];   // held back until we know a design will run
        $used_names   = [];
        $no_junctions = [];   // RT-PCR records with nothing to span
        $min_length   = 0;

        // ⭐ A SEQUENCE THAT PRODUCED NOTHING IS A RESULT (user, 2026-08-18), and
        // it has to survive into the file as well as onto the page. Two ways to
        // get here and they are equally worth reporting: rejected before primer3
        // ran (too short, not DNA, unclosed bracket), or run and returned no
        // pairs. Both land in ONE list, so "which of my sequences worked" is
        // answered in one place rather than by counting tables.
        //
        // Silence here is the failure mode that matters: paste 20 sequences, get
        // 17 tables, and nothing tells you which three are missing or why.
        $failures = [];

        if (count($raw_records) > Primer3Design::MAX_RECORDS) {
            $run_error = 'That is ' . count($raw_records) . ' sequences. '
                       . Primer3Design::MAX_RECORDS . ' at a time is the limit — design these in '
                       . 'batches, so the results stay readable.';
            $raw_records = [];
        }

        $multi = count($raw_records) > 1;

        foreach ($raw_records as $index => $raw) {
            // Keep the markup characters; SequenceMarkup separates DNA from
            // instructions. Stripping to [A-Za-z] here, as this used to, silently
            // ate every mark the user typed.
            $marked = preg_replace('/[^A-Za-z|\[\]-]/', '', $raw['body']);

            // What to CALL it before it has earned a real name. A rejected record
            // is never named by PrimerNames — the generated name is built FROM a
            // usable sequence, and there isn't one — so the header, or its
            // position in the paste, is all there is. "Sequence 3" beats "your
            // sequence" the moment there are several.
            $label = $raw['header'] !== '' ? $raw['header'] : 'sequence ' . ($index + 1);

            $markup = SequenceMarkup::parse($marked);
            $seq    = $markup['sequence'];

            // ⚠️ A BAD RECORD IS NAMED AND SKIPPED, NOT DESIGNED AROUND SILENTLY.
            // With one sequence these were fatal, and stayed fatal — nothing else
            // was going to run. With several, refusing the whole paste because
            // sequence 7 has a typo throws away six good designs; dropping it
            // quietly is worse still, since the user counts the tables and finds
            // one missing with no explanation.
            if ($markup['errors']) {
                // Report a markup problem AS a markup problem. parse() returns an
                // empty sequence when it gives up, so falling through to the
                // checks below answered an unclosed "[" with "that sequence is
                // too short to design primers from" — true of the empty string,
                // and useless.
                $failures[] = ['name' => $label, 'reason' => implode(' ', $markup['errors'])];
                continue;
            }
            if ($seq === '') {
                $failures[] = ['name' => $label, 'reason' => 'There is no sequence under that header.'];
                continue;
            }
            if (strlen($seq) < 60) {
                $failures[] = ['name' => $label, 'reason' => 'Too short to design primers from at '
                             . strlen($seq) . ' bp — 60 bp is about the minimum, and more is better.'];
                continue;
            }
            if (preg_match('/[^ACGTN]/', $seq)) {
                $failures[] = ['name' => $label,
                               'reason' => 'Contains characters that are not DNA bases (A, C, G, T, N).'];
                continue;
            }

            // Named only now that the sequence is known to be usable, because a
            // generated name is BUILT from it. Every primer designed from pasted
            // DNA used to be called sequence_p1_F — the same name for every
            // sequence, every session and every user, which stops being a name
            // the moment two designs share a spreadsheet or an order form.
            $template_id = PrimerNames::template($raw['header'], $seq);

            // Two records CAN arrive with the same name — the same header twice,
            // or two headerless sequences of equal length starting with the same
            // five bases. Renaming is the only way the tables and the order form
            // stay unambiguous, but a silent rename is its own bug, so it is said
            // out loud.
            if (isset($used_names[$template_id])) {
                $original = $template_id;
                $template_id .= '_' . (++$used_names[$original]);
                $notes[] = 'Two sequences are both called ' . $original . '; the second is shown as '
                         . $template_id . ' so its primers can be told apart.';
            }
            $used_names[$template_id] = 1;

            if ($raw['header'] === '') {
                $notes[] = ($multi ? 'Sequence ' . ($index + 1) . ' has no FASTA header, so it is called '
                                   : 'No FASTA header was given, so this sequence is called ')
                         . $template_id . ' — today\'s date, its first five bases and its length. '
                         . 'Primers below are named after it. Put a ">name" line above your sequence '
                         . 'to choose the name yourself.';
            }

            $record = ['id' => $template_id, 'template' => $seq];
            $min_length = $min_length === 0 ? strlen($seq) : min($min_length, strlen($seq));

            foreach ($markup['notes'] as $note) {
                $markup_notes[] = $multi ? $template_id . ': ' . $note : $note;
            }

            // A region the product must span — a SNP, an exon, a domain. Checked
            // against THIS template rather than left to primer3, whose answer to
            // a target past the end is a tag-named error about an included
            // region the user never mentioned.
            // ---- regions the product must contain ----
            // Two ways to say it, and saying it twice is ambiguous rather than
            // emphatic — so both at once is an error, not a silent precedence rule.
            $targets = $markup['targets'];
            $parsed_target = ['target' => null];

            if ($target !== '' && $multi) {
                // One box, one set of coordinates, several sequences of different
                // lengths: position 300 means a different place in each. The [ ]
                // mark travels WITH its sequence, which is what this needs.
                $option_errors['multi_target'] =
                    '"Region to include" describes one sequence, but you pasted ' . count($raw_records)
                    . '. Mark the region in each sequence with [ ] instead, or design them one at a time.';
            } elseif ($target !== '') {
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
                $no_junctions[] = $template_id;
            }

            $records[] = $record;
        }

        // One line however many sequences lack junctions — the same paragraph
        // repeated 20 times is not 20 times as informative.
        if ($no_junctions) {
            $notes[] = (count($no_junctions) === 1 && !$multi
                           ? 'No exon junctions are known for this sequence, so nothing forced a primer '
                           : 'No exon junctions are known for ' . count($no_junctions) . ' of these '
                             . 'sequences (' . implode(', ', array_slice($no_junctions, 0, 3))
                             . (count($no_junctions) > 3 ? ', …' : '') . '), so nothing forced a primer ')
                     . 'across one — these are ordinary primers. Either arrive from a gene page, '
                     . 'or mark the junctions yourself by putting "|" in the sequence.';
        }

        if ($run_error !== null) {
            // Already fatal (too many records); nothing below applies.
        } elseif (!$records) {
            // Nothing to run. With a SINGLE sequence this is the whole story, so
            // it stays a plain error message — a card listing one row, above an
            // empty page, would be ceremony. With several, the card does the
            // work and the error just says to read it.
            $run_error = count($failures) === 1
                ? $failures[0]['reason']
                : 'None of those ' . count($failures) . ' sequences could be used.';
        } else {
            $template_id = $records[0]['id'];
            $seq = $records[0]['template'];

            $params = $PRESETS[$primer_type]['params'];
            $params['PRIMER_NUM_RETURN'] = $num_return;

            // Product size. Three ways to say it, in order of precedence:
            //   "as long as possible"  → a ladder built from the template
            //   "low-high"             → exactly that range
            //   a single number        → a ladder capped at that number, which is
            //                            what the old "biggest product you would
            //                            want" field meant
            if ($longest) {
                // ⚠️ Built from the SHORTEST sequence. The product size range is a
                // GLOBAL primer3 tag — one range for every record in the run — so
                // a ladder sized to the longest would ask for products that do not
                // fit in the others, and they would return nothing while looking
                // like difficult sequences.
                $ladder = Primer3Design::productSizeLadder($min_length);
                if ($ladder !== '') {
                    $params['PRIMER_PRODUCT_SIZE_RANGE'] = $ladder;
                    $notes[] = 'Asking for the longest product '
                             . ($multi ? 'the shortest of these sequences (' . number_format($min_length)
                                         . ' bp) allows' : 'this sequence allows')
                             . ', trying ' . str_replace(' ', ' bp, then ', $ladder) . ' bp.';
                } else {
                    $notes[] = ($multi ? 'The shortest sequence here is' : 'This sequence is')
                             . ' too short to ask for the longest possible product, '
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

            // One primer3 run for every sequence. boulder-IO is record-oriented
            // and buildInput writes the globals once, so N sequences cost one
            // process rather than N.
            $run = Primer3Design::run($records, $params);
            if (!$run['success']) {
                // primer3 speaks in tag names. Translated where we can, passed
                // through untouched where we cannot — see friendlyError().
                // Sized by the SHORTEST template: "your product is longer than
                // your sequence" has to name the sequence that made it true.
                $run_error = Primer3Design::friendlyError($run['error'], $min_length);
            } else {
                $results = $run['results'];

                // The amplicon itself, derived from the template primer3 echoed
                // back to us. Done BEFORE tails go on, because the tailed form is
                // built from this one — and done here rather than in the view so
                // there is one amplicon per pair, not one per surface that wants
                // to show it.
                foreach ($results as $ri => $r) {
                    foreach ($r['pairs'] as $pi => $pair) {
                        $results[$ri]['pairs'][$pi]['product_sequence']
                            = Primer3Design::productSequence($r['template'], $pair);
                    }
                }

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
                        $notes[] = 'With the tag, the longest oligo here is ' . $longest_oligo
                                 . ' bases. Above about ' . PrimerTails::LONG_OLIGO . ' most vendors '
                                 . 'charge more and suggest purification beyond standard desalting — '
                                 . 'worth checking before you order. The primer itself is unaffected: '
                                 . 'every Tm, GC and length figure below is for the untagged primer, '
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

                        // Reported the same way as a sequence rejected before the
                        // run: one list, one row per sequence, on the page AND in
                        // the file. It used to be a note above the tables, which
                        // with several sequences is the easiest thing on the page
                        // to scroll past — and it never reached the download at
                        // all, so a saved file quietly held 17 of 20 sequences.
                        $failures[] = [
                            'name'   => $r['id'],
                            'reason' => $explain !== ''
                                ? 'No primers met the criteria. Primer3 says — ' . $explain
                                  . '. The largest count names the setting to loosen first.'
                                : 'No primers met the criteria. Try widening the product size range, '
                                  . 'the Tm window, or the GC range.',
                        ];
                    }
                }
            }
            }
        }
    }

    return compact('results', 'failures', 'notes', 'run_error', 'junctions', 'template_id');
}

/**
 * Every column of the results, keyed by name, with where it appears: [label, 'both' | 'file' | 'no'].
 * The page's table and file and the API's file all come from this one list.
 */
function primer_maker_column_spec(array $tail, bool $multi): array
{
    // ONE table of columns, with where each one appears.
    //
    // ⭐ THE SCREEN SHOWS A SUBSET OF THE FILE (user, 2026-08-18): the screen is
    // for judging a pair — name, sequence, length, Tm, GC, product size — while
    // the file is for keeping, so it also carries each primer's start position
    // and the pair penalty. Declaring that ONCE, as 'file' versus 'both', is what
    // stops the two drifting; two hand-written lists is how the other results
    // table grew four silent defects (CLAUDE.md §9b).
    //
    // Each primer's NAME travels with it in both. Same string in the table, the
    // TSV, the FASTA below and the pair handed to Primer BLAST — all from
    // PrimerNames::primer(), so a row cannot be called one thing on screen and
    // another in the file you order from.
    //
    // 📍 START ONLY, AND ALWAYS THE LOWEST COORDINATE (user). primer3 reports the
    // reverse primer by its 5' end, which is its HIGHEST coordinate — the opposite
    // convention from the forward primer, in the same column of the same table.
    // parseOutput already computes right_start as the lowest base, so both
    // columns mean "where this primer begins in the sequence you pasted".
    $has_tail = ($tail['forward'] ?? '') !== '' || ($tail['reverse'] ?? '') !== '';
    return [
        // On screen only when there IS more than one sequence — with one, the card
        // header already names it and the column would repeat that on every row.
        // Always in the file: it is what lets a sequence that produced NO primers
        // occupy a row, and what lets a spreadsheet group rows by sequence without
        // parsing a primer name.
        'sequence'      => ['Sequence',        $multi ? 'both' : 'file'],
        'rank'          => ['#',               'both'],
        'left_name'     => ['Forward name',    'both'],
        'left_sequence' => ['Forward primer',  'both'],
        'left_tailed'   => ['Forward to order', ($tail['forward'] ?? '') !== '' ? 'both' : 'no'],
        'left_length'   => ['F len',           'both'],
        'left_tm'       => ['F Tm',            'both'],
        'left_gc'       => ['F GC%',           'both'],
        'left_start'    => ['F start',         'file'],
        'right_name'     => ['Reverse name',   'both'],
        'right_sequence' => ['Reverse primer', 'both'],
        'right_tailed'   => ['Reverse to order', ($tail['reverse'] ?? '') !== '' ? 'both' : 'no'],
        'right_length'   => ['R len',          'both'],
        'right_tm'       => ['R Tm',           'both'],
        'right_gc'       => ['R GC%',          'both'],
        'right_start'    => ['R start',        'file'],
        'product_size'   => ['Product (bp)',   'both'],
        // The band you actually see on a gel: the insert plus both tails. The
        // workflow this replaces only ever reported the first number, which is
        // the one you do NOT measure.
        'product_size_tailed' => ['Product + tags', $has_tail ? 'both' : 'no'],
        'pair_penalty'  => ['Penalty',         'file'],
        // Empty on every designed row; on a failure row it is the whole point.
        'note'          => ['Note',            'file'],

        // ⭐ THE AMPLICONS GO LAST (user, 2026-08-19), after even the penalty and
        // the note. They are the two widest columns in the file by an order of
        // magnitude — hundreds of characters where nothing else reaches twenty —
        // so any column placed after them is one a reader has to scroll past
        // them to reach. Everything short enough to read at a glance comes
        // first; these two sit at the end, where their width costs nothing.
        //
        // ⭐ FILE-ONLY, for the same reason turned around: a 300 bp string cannot
        // be read in a table cell and would push every other column off the
        // screen. It is what gets pasted into a synthesis order, a restriction
        // map or an alignment — work done in the file, not on the page.
        // Single-line by construction (substr of a template already stripped of
        // whitespace), and downloadTsv() strips tabs and newlines from every
        // cell besides, so a stray break cannot split one row into two.
        'product_sequence' => ['Product sequence', 'file'],
        // The band that actually comes out of the tube once the tags are on it —
        // paired with 'Product + tags' for the same reason that column exists,
        // and gated the same way so it never appears as a copy of the untagged
        // sequence.
        'product_sequence_tailed' => ['Product sequence + tags', $has_tail ? 'file' : 'no'],
    ];

}

/** The keys of the columns that go in the FILE (everything not 'no'). */
function primer_maker_file_columns(array $tail, bool $multi): array
{
    return array_keys(array_filter(primer_maker_column_spec($tail, $multi), fn($c) => $c[1] !== 'no'));
}

/** One designed pair as a row over $file_keys: names from PrimerNames, Tm/GC to 1 decimal. */
function primer_maker_row(array $pair, string $entry_id, array $file_keys): array
{
    $export_columns = array_flip($file_keys);
    $pair['left_name']  = PrimerNames::primer($entry_id, $pair['rank'] ?? 0, 'left');
    $pair['right_name'] = PrimerNames::primer($entry_id, $pair['rank'] ?? 0, 'right');
    $pair['sequence']   = $entry_id;
    $pair['note']       = '';
    // Built over the FILE's columns, which are the superset;
    // the screen then renders the ones marked for it. One
    // pass, so a value cannot be formatted one way in the
    // table and another in the download.
    $row = [];
    foreach (array_keys($export_columns) as $key) {
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
    return $row;
}

/** A sequence that produced no primers, as a row: its name and the reason, every other cell empty. */
function primer_maker_failure_row(array $failure, array $file_keys): array
{
    $row = array_fill_keys($file_keys, '');
    $row['sequence'] = $failure['name'];
    $row['note']     = $failure['reason'];
    return $row;
}

/**
 * Every row of the file, as the page's download writes it: the designed pairs, then the sequences
 * that produced none. (The page leaves the failure out of the file when it IS the whole error --
 * one sequence, nothing ran -- and so does this.)
 */
function primer_maker_export_rows(array $run, array $tail): array
{
    $entries = array_values(array_filter($run['results'], fn($e) => !empty($e['pairs'])));
    $keys    = primer_maker_file_columns($tail, count($entries) > 1);
    $rows    = [];
    foreach ($entries as $entry) {
        foreach ($entry['pairs'] as $pair) $rows[] = primer_maker_row($pair, $entry['id'], $keys);
    }
    if ($run['failures'] && !($run['run_error'] !== null && count($run['failures']) === 1)) {
        foreach ($run['failures'] as $f) $rows[] = primer_maker_failure_row($f, $keys);
    }
    return $rows;
}
