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
 * Still to do before this is finished (Monday):
 *   - 5' cloning tails (T4P and friends). ORDER MATTERS: design on the bare
 *     template, compute every statistic on the untailed primer, check
 *     specificity untailed, and only THEN append the tail and report both forms.
 *     See the PRODUCT SPEC in notes/PRIMER_BLAST_TOOL_PLAN.md — the existing
 *     workflow's ordering is the spec, not an accident.
 *   - Chaining: hand a designed pair straight to Primer BLAST without a
 *     copy-paste round trip.
 *   - Dash-separated pasted sequence as an alternative way to mark a junction.
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/primer/Primer3.php';
include_once __DIR__ . '/../lib/primer/Primer3Design.php';
include_once __DIR__ . '/../lib/primer/ExonMap.php';
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
$PRESETS = [
    'standard' => [
        'label'  => 'Standard PCR',
        'blurb'  => 'General-purpose primers. 100–400 bp product, Tm around 60 °C.',
        'params' => ['PRIMER_PRODUCT_SIZE_RANGE' => '100-400'],
    ],
    'qpcr' => [
        'label'  => 'qPCR',
        'blurb'  => 'Short products amplify efficiently in real time: 70–150 bp, tighter Tm.',
        'params' => [
            'PRIMER_PRODUCT_SIZE_RANGE' => '70-150',
            'PRIMER_OPT_TM'             => 60.0,
            'PRIMER_MIN_TM'             => 58.0,
            'PRIMER_MAX_TM'             => 62.0,
        ],
    ],
    'rtpcr' => [
        'label'  => 'RT-PCR (spans an exon junction)',
        'blurb'  => 'Forces a primer ACROSS a junction, so the pair cannot amplify contaminating '
                  . 'genomic DNA. Needs a transcript whose exon structure is known — arrive from '
                  . 'a gene page, or pick a transcript below.',
        'params' => ['PRIMER_PRODUCT_SIZE_RANGE' => '70-300'],
    ],
    'sequencing' => [
        'label'  => 'Sequencing',
        'blurb'  => 'Longer products and a wider Tm window, for reading through a region.',
        'params' => [
            'PRIMER_PRODUCT_SIZE_RANGE' => '400-900',
            'PRIMER_MIN_TM'             => 55.0,
            'PRIMER_MAX_TM'             => 65.0,
        ],
    ],
];

// --------------------------------------------------------------------- input
$primer_type = $_POST['primer_type'] ?? 'standard';
if (!isset($PRESETS[$primer_type])) {
    $primer_type = 'standard';
}

$sequence_text = trim($_POST['sequence'] ?? '');
$num_return    = max(1, min(20, (int)($_POST['num_return'] ?? 5)));
$size_range    = trim($_POST['size_range'] ?? '');

// Context from a gene page: organism / assembly / gene set, and the feature the
// user was looking at. Accepted from GET so the toolbox link carries it.
$context_organism = trim($_GET['organism']  ?? $_POST['organism']  ?? '');
$context_assembly = trim($_GET['assembly']  ?? $_POST['assembly']  ?? '');
$context_gene_set = trim($_GET['gene_set']  ?? $_POST['gene_set']  ?? '');
$context_feature  = trim($_GET['feature']   ?? $_POST['feature']   ?? '');

$results     = [];
$run_error   = null;
$notes       = [];
$junctions   = [];
$template_id = '';

$p3 = Primer3::status();

// ------------------------------------------------------------------ the run
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $seq   = '';
        foreach ($lines as $line) {
            if (strlen($line) && $line[0] === '>') {
                if ($seq !== '') break;                 // second record: stop
                $template_id = trim(substr($line, 1)) ?: 'sequence';
                continue;
            }
            $seq .= preg_replace('/[^A-Za-z]/', '', $line);
        }
        $seq = strtoupper($seq);

        if (strlen($seq) < 60) {
            $run_error = 'That sequence is too short to design primers from — 60 bp is about the '
                       . 'minimum, and more is better.';
        } elseif (preg_match('/[^ACGTN]/', $seq)) {
            $run_error = 'The sequence contains characters that are not DNA bases (A, C, G, T, N).';
        } else {
            $record = ['id' => $template_id, 'template' => $seq];

            // RT-PCR: the junction positions are what force a primer across a
            // boundary. Taken from the exon index when the sequence is a known
            // transcript — which is the whole reason exon_coords.tsv exists.
            if ($primer_type === 'rtpcr') {
                $junctions = [];
                if ($context_organism && $context_assembly && $context_gene_set) {
                    $gs_path = $config->getPath('organism_data') . '/' . $context_organism
                             . '/' . $context_assembly . '/' . $context_gene_set;
                    $recs = ExonMap::load($gs_path, [$template_id]);
                    if (!empty($recs[$template_id])) {
                        $offset = 0;
                        $exons  = $recs[$template_id]['exons'];
                        if ($recs[$template_id]['strand'] === '-') {
                            $exons = array_reverse($exons);
                        }
                        foreach (array_slice($exons, 0, -1) as list($s, $e)) {
                            $offset     += $e - $s + 1;
                            $junctions[] = $offset;   // last transcript base of this exon
                        }
                    }
                }

                if (empty($junctions)) {
                    $notes[] = 'No exon structure is known for this sequence, so no junction could '
                             . 'be targeted — these are ordinary primers, not RT-PCR primers. '
                             . 'Arrive from a gene page, or choose a different primer type.';
                } else {
                    $record['junctions'] = $junctions;
                    $notes[] = count($junctions) . ' exon junction'
                             . (count($junctions) === 1 ? '' : 's')
                             . ' found; primers are required to span one.';
                }
            }

            $params = $PRESETS[$primer_type]['params'];
            $params['PRIMER_NUM_RETURN'] = $num_return;
            if ($size_range !== '' && preg_match('/^\d+\s*-\s*\d+$/', $size_range)) {
                $params['PRIMER_PRODUCT_SIZE_RANGE'] = preg_replace('/\s+/', '', $size_range);
            }

            $run = Primer3Design::run([$record], $params);
            if (!$run['success']) {
                $run_error = $run['error'];
            } else {
                $results = $run['results'];
                foreach ($results as $r) {
                    if (empty($r['pairs'])) {
                        // primer3's own explanation, which says WHY — "high tm",
                        // "GC content failed" and so on. Far more useful than a
                        // bare "no primers found", and it is right there in the
                        // output.
                        $notes[] = 'No primers met the criteria. Primer3 says: '
                                 . ($r['explain']['pair'] ?: $r['explain']['left'] ?: 'no explanation given')
                                 . '. Try widening the product size range.';
                    }
                }
            }
        }
    }
}

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

    'page_styles'      => [
        '/' . $site . '/css/display.css',
    ],
];

$display_config = [
    'title'        => 'Primer Maker',
    'content_file' => __DIR__ . '/pages/primer_maker.php',
];

include_once __DIR__ . '/display-template.php';
