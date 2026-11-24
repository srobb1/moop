<?php
/**
 * api/v1/primer_blast — will these primers amplify only their target? The Primer BLAST page's own
 * check (primer_blast_check() + primer_blast_verdict(), lib/primer/PrimerBlastRun.php), so an agent
 * gets the verdict a person would.
 *
 * GET or POST:
 *   organism   required (folder name, as in api/v1/catalog)
 *   primers    the pairs, in any form the page accepts: a table with a header row naming
 *              name / forward / reverse (tab or comma), or FASTA. Up to API_PRIMER_BLAST_MAX_PAIRS.
 *              Or, for ONE pair: forward + reverse (+ name).
 *   mode       genome (default): the PCR template is genomic DNA -- products on the genome.
 *              cdna: the template is cDNA (RT-PCR / qPCR) -- products on the gene set's transcripts,
 *              AND on the genome, since genomic DNA left in an RNA prep can still amplify.
 *   assembly, gene_set   when the organism has more than one (300 lists them; for genome only the assembly matters)
 *   max_mismatch   how many mismatches per primer a LISTED product may have, 0-5 (default 2).
 *   max_product    the largest product LISTED, bp (default 10,000).
 *              Both only trim the lists: the verdict always weighs every site that could amplify.
 *   format     json (default) | md | tsv (the page's "Download results" file, byte for byte)
 *
 * The verdict per pair: specific (one product, or one gene's isoforms) | nonspecific (+ reasons) |
 * none (no product would amplify). Synchronous -- a few seconds; through the same BLAST queue as the
 * page. Rate-limit class: heavy.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/primer/PrimerBlastRun.php';
require_once __DIR__ . '/../../lib/rows_tsv.php';
require_once __DIR__ . '/../../includes/source-selector-helpers.php';

const API_PRIMER_BLAST_MAX_PAIRS = 20;

header('Cache-Control: private, no-store');
$out = function (int $code, array $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};
$fail  = fn(int $code, string $msg, array $extra = []) => $out($code, ['error' => $msg] + $extra);
$param = fn(string $k) => trim((string) ($_POST[$k] ?? $_GET[$k] ?? ''));

$format = strtolower($param('format') ?: 'json');
if (!in_array($format, ['json', 'md', 'tsv'], true)) $fail(400, 'format is json, md or tsv.');
$mode = strtolower($param('mode') ?: 'genome');
if (!in_array($mode, ['genome', 'cdna'], true)) $fail(400, 'mode is genome (genomic DNA template) or cdna (RT-PCR / qPCR).');
$search_mode = $mode === 'cdna' ? 'transcript' : 'genome';

$organism = $param('organism');
$primers  = str_replace("\r", '', (string) ($_POST['primers'] ?? $_GET['primers'] ?? ''));
if (trim($primers) === '' && $param('forward') !== '' && $param('reverse') !== '') {
    $primers = "name\tforward\treverse\n" . ($param('name') ?: 'pair_1') . "\t" . $param('forward') . "\t" . $param('reverse');
}
if ($organism === '' || trim($primers) === '') {
    $fail(400, 'Give organism, and primers (a table with a header row: name, forward, reverse) or forward + reverse.');
}

$parsed = PrimerInput::parse($primers);
if (empty($parsed['pairs'])) {
    $fail(400, 'No usable primer pairs: ' . implode(' ', $parsed['errors'] ?: ['none found.']),
          ['example' => "name\tforward\treverse\nmyGene_1\tACGTACGTACGTACGTACGT\tTGCATGCATGCATGCATGCA"]);
}
if (count($parsed['pairs']) > API_PRIMER_BLAST_MAX_PAIRS) {
    $fail(413, count($parsed['pairs']) . ' pairs; at most ' . API_PRIMER_BLAST_MAX_PAIRS . ' per call -- send the rest in another.');
}

$max_mismatch = $param('max_mismatch') === '' ? PrimerBlast::DISPLAY_MISMATCH_DEFAULT : (int) $param('max_mismatch');
$max_mismatch = max(0, min(PrimerBlast::SEARCH_MAX_MISMATCH, $max_mismatch));
$max_product  = $param('max_product') === '' ? PrimerPairs::MAX_PRODUCT_DEFAULT : (int) $param('max_product');
$max_product  = max(100, min(1000000, $max_product));

// The source: one the visitor may open, as the page offers them (getAccessibleGeneSets()).
// One entry per gene set: flattenSourcesList() repeats a gene set for every group it is in.
$sources = [];
foreach (flattenSourcesList(getAccessibleGeneSets()) as $s) {
    if ($s['organism'] === $organism) $sources[$s['assembly'] . '|' . ($s['gene_set'] ?? '')] ??= $s;
}
$sources = array_values($sources);
if (!$sources) $fail(404, "No organism $organism that you can open (names are as in api/v1/catalog).");
foreach (['assembly', 'gene_set'] as $k) {
    if (($v = $param($k)) !== '') $sources = array_values(array_filter($sources, fn($s) => ($s[$k] ?? '') === $v));
}
if (!$sources) $fail(404, "No such assembly / gene set of $organism that you can open.");
if ($mode === 'genome') {   // one genome per assembly, whichever gene set: do not ask which
    $by_asm = [];
    foreach ($sources as $s) $by_asm[$s['assembly']] ??= $s;
    $sources = array_values($by_asm);
}
if (count($sources) > 1) {
    $fail(300, "$organism has more than one " . ($mode === 'genome' ? 'assembly' : 'gene set') . '; ask again with assembly' . ($mode === 'genome' ? '' : ' and gene_set') . '.',
          ['choices' => array_map(fn($s) => ['assembly' => $s['assembly']] + ($mode === 'genome' ? [] : ['gene_set' => $s['gene_set'] ?? '']), $sources)]);
}
$source = $sources[0];

// ---------------------------------------------------------------- the check
$check = primer_blast_check($parsed['pairs'], $source, $search_mode, $max_product);
if ($check['error'] !== null) {
    // The page's wording names the page's control; say it the API's way.
    $fail(422, str_replace('Set PCR input to "cDNA"', 'Use mode=cdna', $check['error']), ['notes' => $check['db_notes']]);
}

$rows = [];
$pairs_out = [];
foreach ($check['results'] as $r) {
    $pair = $r['pair'];
    $v    = primer_blast_verdict($r, $search_mode, $max_product);
    $by_template = [];
    foreach ($r['by_db'] as $db_key => $found) {
        $shown = primer_blast_shown_products($found, $max_product, $max_mismatch);
        $listed = [];
        foreach ($shown as $prod) {
            $row    = primer_blast_export_row($pair, $db_key, $prod, $v['key']);
            $rows[] = $row;
            $listed[] = array_diff_key($row, array_flip(['pair', 'forward', 'reverse', 'pair_verdict', 'template']))
                      + ['would_amplify' => PrimerPairs::isAmplifiable($prod)];
        }
        // What the limits left out -- and whether any of it would still amplify (the verdict counted it).
        $hidden = array_filter($found['products'] ?? [], fn($pr) => $pr['size'] > $max_product || $pr['max_mismatch'] > $max_mismatch);
        $by_template[$db_key === 'genome' ? 'genomic' : 'cDNA'] = [
            'products'   => $listed,
            'not_listed' => count($hidden) + (int) ($found['over_max'] ?? 0),
            'not_listed_that_could_amplify' => count(array_filter($hidden, ['PrimerPairs', 'isAmplifiable'])),
        ];
    }
    $iso = $v['isoforms_note'];
    $pairs_out[] = [
        'name' => $pair['name'], 'forward' => $pair['forward'], 'reverse' => $pair['reverse'],
        'verdict' => $v['key'], 'verdict_label' => $v['label'], 'reasons' => $v['reasons'],
        'products_that_would_amplify' => $v['amplifiable'],
    ] + ($iso ? ['one_gene_several_isoforms' => $iso['gene'] . ': ' . $iso['n'] . ' transcripts (' . implode(', ', $iso['tx'])
                                                . '), products ' . implode(', ', $iso['sizes']) . ' bp'] : [])
      + ['templates' => $by_template];
}

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    echo moop_rows_to_tsv($rows);
    exit;
}

$body = [
    'organism' => $organism, 'assembly' => $source['assembly'],
    'gene_set' => $mode === 'cdna' ? ($source['gene_set'] ?? '') : '',
    'mode' => $mode, 'searched' => array_map(fn($k) => $k === 'genome' ? 'genomic DNA' : 'cDNA (transcripts)', array_keys($check['searched_dbs'])),
    'listing' => "products up to $max_product bp with at most $max_mismatch mismatches per primer",
    'pairs' => $pairs_out,
    'notes' => array_merge($check['db_notes'], $parsed['errors'] ?? [], $parsed['warnings'] ?? []),
    'how_to_read' => 'A product would amplify when it is under ' . PrimerPairs::amplifiableKb() . ' kb, each primer has fewer than 6 '
                   . 'mismatches, and fewer than 2 in its last 5 bases at the 3\' end (NCBI Primer-BLAST\'s thresholds). '
                   . 'specific = one such product on the template (or isoforms of one gene); nonspecific = more, see reasons; '
                   . 'none = nothing would amplify. In cdna mode, introns_spanned > 0 on the cDNA product means genomic DNA '
                   . 'cannot give the same band.',
    'download' => 'format=tsv gives the listed products as the page\'s "Download results" file.',
];

if ($format === 'md') {
    header('Content-Type: text/markdown; charset=utf-8');
    $md = "# Primer BLAST: $organism {$source['assembly']}" . ($body['gene_set'] !== '' ? " / {$body['gene_set']}" : '')
        . " ($mode)\n\nSearched: " . implode(' + ', $body['searched']) . "; listing {$body['listing']}.\n\n";
    foreach ($pairs_out as $p) {
        $md .= "## {$p['name']}: **{$p['verdict_label']}**\n\n`{$p['forward']}` / `{$p['reverse']}`\n\n";
        foreach ($p['reasons'] as $why) $md .= "- $why\n";
        if (isset($p['one_gene_several_isoforms'])) $md .= "- One gene, several isoforms: {$p['one_gene_several_isoforms']}\n";
        foreach ($p['templates'] as $tpl => $t) {
            $md .= "\n$tpl: " . ($t['products'] ? '' : 'no product listed') . "\n";
            foreach ($t['products'] as $pr) {
                $md .= "- {$pr['product_size']} bp at {$pr['subject']}:{$pr['start']}-{$pr['end']}, {$pr['max_mismatch']} mismatch(es)"
                     . ($pr['gene'] !== '' ? ", gene {$pr['gene']}" . ($pr['gene_name'] !== '' ? " ({$pr['gene_name']})" : '') : '')
                     . ($pr['introns_spanned'] !== '' ? ", spans {$pr['introns_spanned']} intron(s)" : '')
                     . ($pr['formed_by'] !== 'forward+reverse' ? ", {$pr['formed_by']}" : '')
                     . ($pr['would_amplify'] ? '' : ' (would not amplify)') . "\n";
            }
            if ($t['not_listed']) $md .= "- not listed: {$t['not_listed']} (outside the limits), of which {$t['not_listed_that_could_amplify']} could amplify\n";
        }
        $md .= "\n";
    }
    foreach ($body['notes'] as $n) $md .= "> $n\n";
    echo $md . "\n" . $body['how_to_read'] . "\n";
    exit;
}
$out(200, $body);
