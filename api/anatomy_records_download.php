<?php
/**
 * Anatomical Expression Search -- download EVERY matching record (and only those that pass the
 * table's filter, when one is set) as a TSV: the page's own parameters + q=<filter>.
 * Read-only; only the organism this visitor may see. Rate-limited as a heavy request
 * (config 'rate_limit_heavy').
 */
include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/anatomy_expression.php';
require_once __DIR__ . '/../lib/crosswalk.php';
require_once __DIR__ . '/../lib/download_filename.php';

$organism = moop_page_visible_organism();
$spec = $organism !== '' ? moop_page_spec($organism, $_GET) : ['ok' => false];
if (!$spec['ok']) { http_response_code(404); header('Content-Type: text/plain'); echo "Nothing to download.\n"; exit; }
$text = (string) ($_GET['q'] ?? '');

// Names for every id in the matches, and each transcript's reference gene(s), fetched once.
$ids = []; $evs = []; $accs = [];
moop_page_records_each($organism, $spec, $text, function ($r) use (&$ids, &$evs, &$accs) {
    $ids[$r['term_id']] = 1; $ids[(string) $r['stage_id']] = 1; $ids[(string) $r['specimen_id']] = 1;
    $evs[(string) $r['evidence_id']] = 1; $accs[$r['accession']] = 1;
});
$labels = moop_ontology_labels(array_keys($ids), moop_ontology_by_purpose('anatomy'))
        + moop_ontology_labels(array_keys($evs), moop_ontology_by_purpose('evidence'));
$genes = [];
foreach (array_chunk(array_keys($accs), 2000) as $chunk)
    foreach (moop_crosswalk_genes($organism, $chunk) as $acc_lc => $rows) $genes[$acc_lc] = implode(';', array_unique(array_column($rows, 'ref_id')));

$what = $spec['mode'] === 'term' ? ($spec['meta']['term'] ?? 'term') : ($spec['mode'] === 'pub' ? 'PMID' . $spec['filters']['pmid'] : 'ids');
$name = function_exists('moop_download_filename')
      ? moop_download_filename('anatomical_expression', [$organism, str_replace(':', '_', $what)], 'tsv')
      : 'anatomical_expression.tsv';
header('Content-Type: text/tab-separated-values; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
$clean = fn($s) => str_replace(["\t", "\r", "\n"], ' ', (string) $s);
fwrite($out, implode("\t", ['accession', 'reference_genes', 'term_id', 'term', 'evidence_id', 'evidence', 'stage_id', 'stage',
                            'specimen_id', 'specimen', 'pmid', 'authors', 'year', 'title', 'journal', 'curated_on']) . "\n");
moop_page_records_each($organism, $spec, $text, function ($r) use ($out, $labels, $genes, $clean) {
    $L = fn($i) => $i === null || $i === '' ? '' : ($labels[$i] ?? $i);
    fwrite($out, implode("\t", array_map($clean, [$r['accession'], $genes[mb_strtolower($r['accession'])] ?? '',
        $r['term_id'], $L($r['term_id']), $r['evidence_id'], $L($r['evidence_id']), $r['stage_id'], $L($r['stage_id']),
        $r['specimen_id'], $L($r['specimen_id']), $r['pmid'], $r['authors'], $r['year'], $r['title'], $r['journal'], $r['curated_on']])) . "\n");
});
