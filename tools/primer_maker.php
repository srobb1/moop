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
include_once __DIR__ . '/../lib/primer/PrimerPresets.php';
include_once __DIR__ . '/../lib/primer/PrimerNames.php';
include_once __DIR__ . '/../lib/primer/SequenceMarkup.php';
include_once __DIR__ . '/../lib/primer/ExonMap.php';
include_once __DIR__ . '/../lib/primer/PrimerMakerRun.php';   // the design run, shared with the API
include_once __DIR__ . '/../lib/gene_isoforms.php';
include_once __DIR__ . '/../lib/genomic_regions.php';   // moop_genome_kind()
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../includes/source-selector-helpers.php';

$site      = $config->getString('site');
$siteTitle = $config->getString('siteTitle');

// ------------------------------------------------------------------ presets
//
// What "kind of primer" actually means: a handful of Primer3 numbers -- the "cards" of step 2. The
// four shipped ones and the site's own (Admin -> Primer Maker) come from lib/primer/PrimerPresets.php,
// so the selector, the help and the run all read the same values. $PRESETS is filled further down,
// once the context is known: on a transcript assembly the RT-PCR card is not offered at all.

// --------------------------------------------------------------------- input
// GET too: the Sequences section on a gene page links here with the type
// already chosen (a transcript means RT-PCR), and reading POST only made
// that link silently fall back to Standard PCR.
$requested_type = (string)($_POST['primer_type'] ?? $_GET['primer_type'] ?? '');

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
// The exact FASTA id of the sequence picked on a gene page's Sequences card ("X-RA:cds", a
// RefSeq "cds-XP_...", ...) -- CDS ids cannot be derived from the transcript id.
$context_seq_id   = trim($_GET['seq_id']    ?? $_POST['seq_id']    ?? '');

// The gene set's files are read straight from these names, so they must name a gene set this
// viewer may open -- the same check every other sequence path makes. Without it, any visitor
// could prefill Primer Maker from a private gene set's FASTA (found 2026-10-13).
if ($context_organism !== '' || $context_assembly !== '' || $context_gene_set !== '') {
    $ctx_ok = $context_organism !== '' && $context_assembly !== '' && $context_gene_set !== ''
           && !array_filter([$context_organism, $context_assembly, $context_gene_set],
                            fn($n) => strpbrk($n, "/\\\0") !== false || $n[0] === '.')
           && has_gene_set_access($context_organism, $context_assembly, $context_gene_set);
    if (!$ctx_ok) {
        $context_organism = $context_assembly = $context_gene_set = $context_feature = $context_seq_id = '';
    }
}

// A 'transcripts' assembly has no known exon-intron boundaries, so RT-PCR (which must span one)
// is refused there unless the user marks junctions with "|" -- see PRIMER_JUNCTION_TYPES.
$JUNCTION_TYPES = PRIMER_JUNCTION_TYPES;
$context_is_transcripts = primer_maker_is_transcripts($context_organism, $context_assembly);

// The cards this visitor is offered, and the one selected: the one asked for if it is offered, else
// the site's default, else the first. As shipped (no 'primer_maker' saved) that is all four cards
// with Standard PCR selected -- except that RT-PCR is left out on a transcript assembly, where it
// could only fail (it used to arrive SELECTED there from a gene page's mRNA, 2026-11-06).
$primer_settings = $config->getArray('primer_maker');
// Opened from the Tools menu there is no assembly to ask, so the site answers: where EVERY assembly is
// a transcriptome (cuttingclass) no sequence here has known junctions, and RT-PCR is left out too.
$no_junctions = $context_assembly !== '' ? $context_is_transcripts : moop_site_is_transcriptomes();
$PRESETS     = PrimerPresets::forSite($primer_settings, moop_current_lang(), $no_junctions);
$primer_type = PrimerPresets::choose($PRESETS, $requested_type, $primer_settings);

// A card can bring a tag (a site's "WISH probe" card selects T4P). Only on ARRIVAL -- a posted form
// says what the visitor chose, including "no tag".
if (!isset($_POST['tail']) && ($PRESETS[$primer_type]['tail'] ?? '') !== ''
    && isset($tail_catalogue[$PRESETS[$primer_type]['tail']])) {
    $tail_choice = $PRESETS[$primer_type]['tail'];
    $tail = PrimerTails::resolve($tail_choice, $tail_custom_f, $tail_custom_r, $tail_catalogue);
}

$results       = [];
$failures      = [];
$run_error     = null;
$notes         = [];
$junctions     = [];
$template_id   = '';

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
    $isoforms = moop_gene_isoforms($context_organism, $context_assembly, $context_gene_set, $context_feature);

    // Only isoforms that actually have sequence can be offered. One that does
    // not is still listed, disabled, rather than silently dropped — a gene that
    // shows 6 of 7 transcripts with no explanation looks like missing data.
    $with_seq = array_values(array_filter($isoforms, fn($i) => $i['fasta_id'] !== ''));

    // The gene page names the transcript by its DATABASE id (XM_...); this list uses the GFF's
    // (rna-XM_...). Select the same isoform either way.
    if ($selected_isoform !== '' && !in_array($selected_isoform, array_column($isoforms, 'id'), true)) {
        foreach ($isoforms as $__iso) {
            if (preg_replace('/^[A-Za-z]+[-:]/', '', $__iso['id']) === $selected_isoform) { $selected_isoform = $__iso['id']; break; }
        }
    }

    if ($selected_isoform === '' && count($with_seq) === 1) {
        $selected_isoform = $with_seq[0]['id'];
    }

    // Prefill when the box is empty, or when the user explicitly asked to load
    // a transcript. Never silently overwrite something they pasted — that is
    // why "Load this sequence" is its own button rather than an onchange.
    $load_isoform = isset($_POST['load_isoform']);
    if ($selected_isoform !== ''
        && ($load_isoform || $_SERVER['REQUEST_METHOD'] !== 'POST' || trim($_POST['sequence'] ?? '') === '')) {
        // The exact sequence the Sequences card showed, when it said which; otherwise the
        // transcript's (by either id convention).
        $fetch_id = ($context_seq_id !== '' && !isset($_POST['load_isoform'])) ? $context_seq_id : $selected_isoform;
        $seq = moop_transcript_sequence($gene_set_path, $fetch_id, $seq_type);
        if ($seq !== '') {
            $sequence_text = '>' . preg_replace('/^(?:rna|cds|gene|id)-/', '', $fetch_id)
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
    // The run itself is lib/primer/PrimerMakerRun.php, shared with api/v1/primer_maker.php.
    $run = primer_maker_design([
        'sequence' => $sequence_text, 'primer_type' => $primer_type, 'presets' => $PRESETS,
        'num_return' => $num_return, 'size_range' => $size_range, 'longest' => $longest,
        'options' => $options, 'target' => $target, 'tail' => $tail,
        // Access-checked above; emptied when the visitor may not open that gene set.
        'organism' => $context_organism, 'assembly' => $context_assembly, 'gene_set' => $context_gene_set,
    ]);
    $results     = $run['results'];
    $failures    = $run['failures'];
    $notes       = $run['notes'];
    $run_error   = $run['run_error'];
    $junctions   = $run['junctions'];
    $template_id = $run['template_id'];
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
    // Sequences that produced no primers, each with the reason. Rendered as its
    // own card and written into the TSV — see the view.
    'failures'         => $failures ?? [],
    'primer3_ok'       => $p3['ok'],
    'primer3_problem'  => $p3['problem'],
    'context_organism' => $context_organism,
    'context_assembly' => $context_assembly,
    'context_is_transcripts' => $context_is_transcripts,
    'junction_types'   => $JUNCTION_TYPES,
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
        moop_url_base() . '/css/display.css',
    ],
];

$display_config = [
    'title'        => 'Primer Maker',
    'content_file' => __DIR__ . '/pages/primer_maker.php',
    // ⚠️ copy-to-clipboard is NOT loaded globally — every page that wants the
    // .copyable behaviour asks for it (parent.php and sequences_display.php each
    // do). Without it the oligo FASTA block silently stops being clickable: it
    // still LOOKS copyable, cursor and all, because the classes are pure CSS.
    'page_script'  => [
        moop_url_base() . '/js/modules/copy-to-clipboard.js',
        moop_url_base() . '/js/primer-maker.js',
    ],
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
