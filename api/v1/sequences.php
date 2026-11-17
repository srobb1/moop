<?php
/**
 * api/v1/sequences — sequences for a list of IDs: protein, transcript, CDS, or the genomic region with
 * upstream / downstream flanks -- and pieces of any of them (ID:1-60). Sequence Retrieval's own extraction
 * (moop_retrieval_stream(), lib/extract_search_helpers.php) as a GET, so an agent gets exactly what the page
 * would give: a gene reaches its transcripts / CDS / proteins, a range typed on a gene applies to each.
 *
 * GET organism  (required)
 *     ids       (required) up to 200, separated by commas, semicolons or new lines; each optionally a
 *               range, ID:start-end (1-based, inclusive; on a protein it counts amino acids)
 *     type      protein (default) | transcript | cds | genomic
 *     upstream, downstream   bp of flank, up to 100,000 each (type=genomic only). Read 5'->3' along the
 *               feature: on the minus strand, upstream lies at higher coordinates and is reverse-complemented.
 *     flanks_only=1  only the flanks, not the region itself (type=genomic)
 *     assembly, gene_set  the gene set the IDs are in; when left out, found from the first ID (HTTP 300 when
 *               that ID is in more than one gene set)
 *     format    fasta (default) | json ({sequences: [{header, sequence}], not_found, notes})
 *
 * At most 20 MB of sequence per call (estimated first; HTTP 413 above it -- download the gene set's FASTA
 * from api/v1/catalog instead). IDs not in the gene set: JSON not_found, FASTA header X-Not-Found. A gene set
 * the visitor cannot open: 404, as if absent. Rate-limit class: heavy.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['annotation_genes', 'extract_search_helpers', 'genomic_regions', 'gene_lookup', 'parent_functions'] as $__lib) {
    require_once __DIR__ . "/../../lib/$__lib.php";
}

const V1_SEQ_MAX_IDS = 200, V1_SEQ_MAX_BYTES = 20000000;

header('Cache-Control: private, no-store');
$fail = function (int $code, string $msg, array $extra = []) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg] + $extra, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};
$config   = ConfigManager::getInstance();
$organism = trim((string) ($_GET['organism'] ?? ''));
$format   = ($_GET['format'] ?? '') === 'json' ? 'json' : 'fasta';
$type     = strtolower(trim((string) ($_GET['type'] ?? 'protein')));
if ($type === 'genome') $type = 'genomic';
if (!in_array($type, ['protein', 'transcript', 'cds', 'genomic'], true)) $fail(400, 'type is protein, transcript, cds or genomic.');
$up   = max(0, (int) ($_GET['upstream'] ?? 0));
$down = max(0, (int) ($_GET['downstream'] ?? 0));
$flanks_only = !empty($_GET['flanks_only']);
if (($up || $down || $flanks_only) && $type !== 'genomic') $fail(400, 'upstream, downstream and flanks_only need type=genomic.');
if ($up > MOOP_MAX_FLANK_BP || $down > MOOP_MAX_FLANK_BP) $fail(400, 'A flank is at most ' . number_format(MOOP_MAX_FLANK_BP) . ' bp.');
if ($flanks_only && !$up && !$down) $fail(400, 'flanks_only needs upstream or downstream.');

$raw = str_replace(';', ',', (string) ($_GET['ids'] ?? $_GET['id'] ?? ''));
if ($organism === '' || trim($raw) === '') $fail(400, 'organism and ids are required.');
$parsed = parseFeatureIds($raw);
if (!$parsed['valid']) $fail(400, $parsed['error']);
$requests = $parsed['requests'];
if (count($parsed['uniquenames']) > V1_SEQ_MAX_IDS) $fail(400, 'At most ' . V1_SEQ_MAX_IDS . ' IDs per call.');

// The gene set: as named, or the first ID's.
$asm = trim((string) ($_GET['assembly'] ?? ''));
$gs  = trim((string) ($_GET['gene_set'] ?? ''));
if ($gs === '') {
    $L = moop_gene_lookup($organism, $parsed['uniquenames'][0], $asm, '');
    if ($L['status'] === 'choose') {
        $fail(300, "{$parsed['uniquenames'][0]} is in more than one gene set; ask again with assembly and gene_set.",
              ['choices' => array_map(fn($c) => ['assembly' => $c['assembly'], 'gene_set' => $c['gene_set']], $L['choices'])]);
    }
    if ($L['status'] !== 'ok') $fail(404, "No {$parsed['uniquenames'][0]} in $organism that you can open.");
    [$asm, $gs] = [(string) $L['row']['genome_accession'], (string) $L['row']['gene_set_name']];
}
$scope = moop_v1_scope([$organism], $asm, $gs);
if (count($scope) !== 1) $fail(404, 'No such gene set that you can open.');
$g = $scope[0];
$assembly_dir = dirname($g['dir']);
$seq_types = $config->getArray('sequence_types', []);
$key = $type === 'genomic' ? 'genome' : $type;
if ($type === 'genomic') {
    if (moop_genome_kind($assembly_dir) !== 'genome') {
        $fail(400, 'This assembly is assembled transcripts, not a genome: there is no genomic region or flank around a gene. Use type=transcript.');
    }
} elseif (!isset($seq_types[$key]) || !glob("{$g['dir']}/{$seq_types[$key]['pattern']}")) {
    $fail(404, "This gene set has no $type sequences.");
}

$gs_ids    = [$g['gene_set_id']];
$found     = moop_retrieval_found($parsed['uniquenames'], $g['db'], $gs_ids);
$not_found = array_values(array_filter($parsed['uniquenames'], fn($id) => !isset($found[$id])));
$requests  = array_values(array_filter($requests, fn($r) => isset($found[$r['id']])));

$children = moop_retrieval_children(array_values(array_unique(array_column($requests, 'id'))), $g['db'], $gs_ids);
$anchors  = $type === 'genomic' ? moop_genomic_anchors(array_column($requests, 'id'), $g['db'], $gs_ids) : [];
$estimate = moop_retrieval_estimate_bytes($requests, $children, $g['dir'], $type === 'genomic' ? [] : [$key => $seq_types[$key]],
                                          ['anchors' => $anchors, 'upstream' => $up, 'downstream' => $down, 'has_genome' => $type === 'genomic']);
if ($estimate > V1_SEQ_MAX_BYTES) {
    $fail(413, 'About ' . round($estimate / 1e6) . ' MB of sequence; one call gives at most ' . (V1_SEQ_MAX_BYTES / 1e6)
          . ' MB. Ask for fewer IDs, or download the whole FASTA (links in api/v1/catalog).');
}

// Through a temporary stream so the not-found list and notes can still go in the headers / JSON.
$buf = fopen('php://temp', 'w+');
$ctx = ['db_path' => $g['db'], 'gene_set_ids' => $gs_ids, 'gene_set_dir' => $g['dir'], 'assembly_dir' => $assembly_dir,
        'sequence_types' => $seq_types, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'], 'organism' => $organism,
        'upstream' => $up, 'downstream' => $down];
$notes = [];
if ($requests) {
    if ($type === 'genomic') {
        // The page's genomic rules, with their notes (skips, too-long regions); flanks_only drops the region records.
        $emit = function (string $rec) use ($buf, $flanks_only) {
            if ($flanks_only && !preg_match('/^>\S+(?: \S+=\S+)* (?:upstream|downstream)_\d+bp /', $rec)) return;
            fwrite($buf, rtrim($rec, "\n") . "\n");
        };
        $notes = moop_retrieve_genomic($requests, $g['dir'], $assembly_dir, $up, $down, $anchors, $emit)['notes'];
    } else {
        moop_retrieval_stream($buf, $requests, $key, $ctx);
    }
}
rewind($buf);

if ($format === 'fasta') {
    header('Content-Type: text/plain; charset=utf-8');
    if ($not_found) header('X-Not-Found: ' . implode(' ', array_slice($not_found, 0, 50)));
    stream_copy_to_stream($buf, fopen('php://output', 'w'));
    exit;
}
$seqs = [];
foreach (preg_split('/^>/m', stream_get_contents($buf), -1, PREG_SPLIT_NO_EMPTY) as $rec) {
    [$head, $body] = array_pad(explode("\n", $rec, 2), 2, '');
    $seqs[] = ['header' => $head, 'sequence' => str_replace(["\n", "\r"], '', $body)];
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, 'organism' => $organism, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
                  'type' => $type] + ($type === 'genomic' ? ['upstream' => $up, 'downstream' => $down, 'flanks_only' => $flanks_only] : [])
                 + ['sequences' => $seqs, 'not_found' => $not_found, 'notes' => $notes],
                 JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
