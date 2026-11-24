<?php
/**
 * api/v1/primer_maker — design PCR primers with Primer3: the Primer Maker page's own run
 * (primer_maker_design(), lib/primer/PrimerMakerRun.php), so an agent gets the primers a person would.
 *
 * GET or POST (POST for long sequences). Give EITHER
 *   sequence   FASTA (up to Primer3Design::MAX_RECORDS records; each at least 60 bp of A/C/G/T/N).
 *              Marks work as on the page: "|" = a primer must cross this point, [ ] = the product must
 *              contain this region.
 * OR
 *   organism + gene   a gene or transcript ID; its transcript is fetched (a gene with several transcripts
 *              answers 300 with them -- ask again with one). assembly, gene_set when the ID is in more
 *              than one gene set (300). seq_type transcript (default) | cds.
 *              For RT-PCR the transcript's exon junctions are looked up, so a primer spans one.
 *
 * Options (all optional; blank = the primer type's own value, as on the page):
 *   primer_type   a card of this site (400 lists them); default the site's default (Standard PCR)
 *   num_return    pairs per sequence, 1-20 (default 5)
 *   size_range    "150-400", or one number = the largest product wanted
 *   longest=1     the longest product the sequence allows
 *   target        "start,length" region the product must contain (one sequence only)
 *   tail          a 5' tag: none | custom | a catalogue key (400 lists them); custom takes
 *                 tail_forward / tail_reverse. Tags are added AFTER design: every Tm/GC is for the bare primer.
 *   size_opt size_min size_max tm_opt tm_min tm_max tm_diff gc_opt gc_min gc_max gc_clamp max_poly_x
 *                 salt_corr junction_5p junction_3p   (Primer3Design::OPTIONS: bounds are checked, 400 on a bad one)
 *   format        json (default) | md | tsv (the page's "Download table" file, byte for byte)
 *
 * Synchronous: primer3 answers in well under a second. Rate-limit class: heavy.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/primer/PrimerPresets.php';
require_once __DIR__ . '/../../lib/primer/PrimerMakerRun.php';
require_once __DIR__ . '/../../lib/gene_lookup.php';
require_once __DIR__ . '/../../lib/rows_tsv.php';

header('Cache-Control: private, no-store');
$out = function (int $code, array $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};
$fail  = fn(int $code, string $msg, array $extra = []) => $out($code, ['error' => $msg] + $extra);
$param = fn(string $k) => trim((string) ($_POST[$k] ?? $_GET[$k] ?? ''));
$config = ConfigManager::getInstance();

$format = strtolower($param('format') ?: 'json');
if (!in_array($format, ['json', 'md', 'tsv'], true)) $fail(400, 'format is json, md or tsv.');

$sequence = str_replace("\r", '', (string) ($_POST['sequence'] ?? $_GET['sequence'] ?? ''));
$organism = $param('organism');
$gene     = $param('gene');
if (trim($sequence) !== '' && $gene !== '') $fail(400, 'Give sequence OR organism + gene, not both.');
if (trim($sequence) === '' && ($organism === '' || $gene === '')) {
    $fail(400, 'Give sequence (FASTA), or organism + gene (a gene or transcript ID) to design from its transcript.');
}

// ---------------------------------------------------------------- a gene: fetch its transcript
// The context (organism / assembly / gene_set) is passed to the run ONLY from here, where
// moop_gene_lookup() has checked the visitor may open that gene set -- it is what lets RT-PCR look
// up exon junctions, and it reads that gene set's files.
$ctx = ['organism' => '', 'assembly' => '', 'gene_set' => ''];
$from = null;
if ($gene !== '') {
    $seq_type = strtolower($param('seq_type') ?: 'transcript');
    if (!in_array($seq_type, moop_primer_sequence_types(), true)) $fail(400, 'seq_type is transcript or cds.');
    $L = moop_gene_lookup($organism, $gene, $param('assembly'), $param('gene_set'));
    if ($L['status'] === 'choose') {
        $fail(300, "$gene is in more than one gene set; ask again with assembly and gene_set.",
              ['choices' => array_map(fn($c) => ['assembly' => $c['assembly'], 'gene_set' => $c['gene_set']], $L['choices'])]);
    }
    if ($L['status'] !== 'ok') $fail(404, "No $gene in $organism that you can open (names are as in api/v1/catalog).");
    $src = null;
    foreach ($L['accessible_sources'] as $s) {
        if (in_array((int) ($s['gene_set_id'] ?? 0), array_map('intval', $L['gene_set_ids']), true)) { $src = $s; break; }
    }
    if ($src === null) $fail(404, "No $gene in $organism that you can open.");
    $ctx = ['organism' => $organism, 'assembly' => $src['assembly'], 'gene_set' => $src['gene_set']];

    // Which transcript. The ID itself when it IS one; the only one; otherwise ask -- isoforms differ
    // in exactly what matters for primers, so picking one would be guessing (as on the page).
    $gene_id  = (string) $L['row']['feature_uniquename'];
    $isoforms = array_values(array_filter(moop_gene_isoforms($organism, $src['assembly'], $src['gene_set'], $gene_id),
                                          fn($i) => $i['fasta_id'] !== ''));
    $strip  = fn(string $id) => preg_replace('/^(?:rna|cds|gene|id)-/', '', $id);
    $chosen = null;
    foreach ($isoforms as $iso) {
        if (in_array($gene, [$iso['id'], $iso['fasta_id'], $strip($iso['id'])], true)) { $chosen = $iso; break; }
    }
    if ($chosen === null && count($isoforms) === 1) $chosen = $isoforms[0];
    if ($chosen === null && !$isoforms) $fail(404, "$gene has no transcript with a sequence in this gene set.");
    if ($chosen === null) {
        $fail(300, "$gene has " . count($isoforms) . ' transcripts; ask again with gene = one of them.',
              ['transcripts' => array_map(fn($i) => ['id' => $strip($i['id']), 'length' => $i['length']], $isoforms)]);
    }
    $fetch = $chosen['fasta_id'];
    $seq   = moop_transcript_sequence($src['path'], $fetch, $seq_type);
    if ($seq === '') {
        $fail(404, "No $seq_type sequence is stored under " . $strip($fetch) . '. Fetch it from api/v1/sequences (type='
              . $seq_type . ') and send it as sequence.');
    }
    $sequence = '>' . $strip($fetch) . "\n" . chunk_split($seq, 60, "\n");
    $from = ['organism' => $organism, 'assembly' => $src['assembly'], 'gene_set' => $src['gene_set'],
             'gene' => $gene_id, 'transcript' => $strip($fetch), 'seq_type' => $seq_type, 'length' => strlen($seq)];
}

// ---------------------------------------------------------------- the primer type and tag
// No junctions are known on a transcripts-only assembly (or, with no gene, on a site where every
// assembly is one): RT-PCR is not offered there, exactly as on the page.
$no_junctions = $ctx['assembly'] !== '' ? primer_maker_is_transcripts($ctx['organism'], $ctx['assembly'])
                                        : moop_site_is_transcriptomes();
$settings = $config->getArray('primer_maker');
$presets  = PrimerPresets::forSite($settings, moop_current_lang(), $no_junctions);
$type     = strtolower($param('primer_type'));
if ($type !== '' && !isset($presets[$type])) {
    $fail(400, "No primer type $type here.", ['primer_types' => array_map(fn($p) => $p['label'], $presets)]);
}
$type = PrimerPresets::choose($presets, $type, $settings);

$catalogue = PrimerTails::catalogue($config->getArray('primer_tails'));
$tail_id   = strtolower($param('tail'));
if ($tail_id === '') $tail_id = (string) ($presets[$type]['tail'] ?? '');   // a card may bring its tag, as on arrival
if ($tail_id !== '' && $tail_id !== 'none' && $tail_id !== 'custom' && !isset($catalogue[$tail_id])) {
    $fail(400, "No tag $tail_id here.", ['tails' => ['none' => 'no tag', 'custom' => 'tail_forward / tail_reverse']
                                                   + array_map(fn($t) => $t['label'], $catalogue)]);
}
$tail = PrimerTails::resolve($tail_id, $param('tail_forward'), $param('tail_reverse'), $catalogue);

$options = [];
foreach (array_keys(Primer3Design::OPTIONS) as $field) $options[$field] = $param($field);

// ---------------------------------------------------------------- the run
$run = primer_maker_design([
    'sequence' => $sequence, 'primer_type' => $type, 'presets' => $presets,
    'num_return' => max(1, min(20, (int) ($param('num_return') ?: 5))),
    'size_range' => $param('size_range'), 'longest' => in_array(strtolower($param('longest')), ['1', 'true', 'yes'], true),
    'options' => $options, 'target' => $param('target'), 'tail' => $tail,
] + $ctx);

$rows = primer_maker_export_rows($run, $tail);
if ($format === 'tsv' && $run['run_error'] === null) {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    echo moop_rows_to_tsv($rows);
    exit;
}

$pairs = array_values(array_filter($rows, fn($r) => ($r['left_sequence'] ?? '') !== ''));
$body  = ['primer_type' => ['key' => $type, 'label' => $presets[$type]['label']]]
       + ($tail['id'] !== 'none' ? ['tag' => ['id' => $tail['id'], 'label' => $tail['label'],
                                               'forward' => $tail['forward'], 'reverse' => $tail['reverse']]] : [])
       + ($from ? ['from' => $from] : []);
if ($run['run_error'] !== null) {
    $code = Primer3::status()['ok'] ? 422 : 503;
    $fail($code, $run['run_error'], $body + ['failures' => $run['failures'], 'notes' => $run['notes']]);
}
$check = implode("\n", array_merge(["name\tforward\treverse"], array_map(
    fn($r) => preg_replace('/_F$/', '', $r['left_name']) . "\t" . $r['left_sequence'] . "\t" . $r['right_sequence'], $pairs)));
$body += [
    'pairs'    => $pairs,
    'failures' => $run['failures'],
    'notes'    => $run['notes'],
    'next'     => 'Check that a pair amplifies only its target: api/v1/primer_blast.php with primers = check_primers and '
                . ($from ? "organism={$from['organism']}, assembly={$from['assembly']}, gene_set={$from['gene_set']}"
                         : 'the organism (and assembly / gene_set) the sequence is from')
                . ' (add mode=cdna for RT-PCR / qPCR on cDNA). Tm, GC and length are for the primer without its tag; '
                . 'left_tailed / right_tailed are what to order.',
    'check_primers' => $check,
    'download' => 'format=tsv gives these rows as the page\'s "Download table" file.',
];

if ($format === 'md') {
    header('Content-Type: text/markdown; charset=utf-8');
    $md = "# Primer Maker: {$body['primer_type']['label']}\n\n";
    if ($from) $md .= "From {$from['transcript']} ({$from['seq_type']}, " . number_format($from['length']) . " bp), gene {$from['gene']}, {$from['organism']} {$from['assembly']} / {$from['gene_set']}.\n\n";
    foreach ($run['notes'] as $n) $md .= "- $n\n";
    if ($run['notes']) $md .= "\n";
    if ($pairs) {
        $tagged = isset($pairs[0]['left_tailed']) || isset($pairs[0]['right_tailed']);
        $md .= "| Forward | Sequence | Tm | GC% | Reverse | Sequence | Tm | GC% | Product (bp) |" . ($tagged ? ' Forward to order | Reverse to order |' : '') . "\n";
        $md .= "|---|---|---|---|---|---|---|---|---|" . ($tagged ? '---|---|' : '') . "\n";
        foreach ($pairs as $p) {
            $md .= "| {$p['left_name']} | {$p['left_sequence']} | {$p['left_tm']} | {$p['left_gc']} | {$p['right_name']} | {$p['right_sequence']} | {$p['right_tm']} | {$p['right_gc']} | {$p['product_size']} |"
                 . ($tagged ? ' ' . ($p['left_tailed'] ?? '') . ' | ' . ($p['right_tailed'] ?? '') . ' |' : '') . "\n";
        }
    }
    if ($run['failures']) {
        $md .= "\n## No primers\n\n";
        foreach ($run['failures'] as $f) $md .= "- **{$f['name']}**: {$f['reason']}\n";
    }
    $md .= "\n" . $body['next'] . "\n";
    echo $md;
    exit;
}
$out(200, $body);
