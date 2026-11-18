<?php
/**
 * api/v1/nearby_genes — the genes next to a gene, or in a stretch of genome the person names.
 *
 * GET organism  (required)
 *     id        a gene (or transcript / protein) ID: its neighbours, upstream and downstream of it
 *       n         genes on each side (default 5, at most 50)
 *       distance  instead of n: every gene within this many bases of it (at most 5,000,000)
 *     -- or --
 *     assembly, gene_set, seqname, start, end: the genes overlapping that region (1-based, inclusive),
 *       at most 10,000,000 bases; flank adds bases on both sides. The person gives the coordinates --
 *       MOOP holds no peaks, QTLs or other features to start from.
 *     assembly, gene_set  with id: only needed when the ID is in more than one gene set (HTTP 300)
 *     format    json (default) | tsv
 *
 * Upstream / downstream follow the GENE'S strand (upstream = 5' of it); a gene on "." or a region
 * uses the sequence's direction (upstream = lower coordinates). distance = bases between the two
 * genes (0 = they overlap). Loci come from the gene set's feature_coords.tsv (moop_gene_loci()),
 * so a gene set never registered with JBrowse, or a transcriptome (no genome), has none.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['annotation_genes', 'gene_lookup', 'feature_coords', 'genomic_regions'] as $__lib) require_once __DIR__ . "/../../lib/$__lib.php";
header('Cache-Control: private, no-store');

$fail = function (int $code, string $msg, array $extra = []) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg] + $extra, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};
$org      = trim((string) ($_GET['organism'] ?? ''));
$id       = trim((string) ($_GET['id'] ?? ''));
$assembly = trim((string) ($_GET['assembly'] ?? ''));
$gene_set = trim((string) ($_GET['gene_set'] ?? ''));
$format   = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
if ($org === '') $fail(400, 'organism is required.');

// The gene set: from the ID (the gene page's own lookup), or named with the region.
if ($id !== '') {
    $L = moop_gene_lookup($org, $id, $assembly, $gene_set);
    if ($L['status'] === 'choose') {
        $fail(300, "$id is in more than one gene set: add assembly and gene_set.",
              ['choices' => array_map(fn($c) => ['assembly' => $c['assembly'], 'gene_set' => $c['gene_set']], $L['choices'])]);
    }
    if ($L['status'] !== 'ok') $fail(404, "$id: not found (or not public).");
    $gsid = (int) $L['gene_set_ids'][0];
    $scope = array_values(array_filter(moop_v1_scope([$org]), fn($g) => (int) $g['gene_set_id'] === $gsid));
} else {
    if ($assembly === '' || $gene_set === '' || !isset($_GET['seqname'], $_GET['start'], $_GET['end'])) {
        $fail(400, 'Give id (a gene), or assembly, gene_set, seqname, start and end (a region).');
    }
    $scope = moop_v1_scope([$org], $assembly, $gene_set);
}
if (!$scope) $fail(404, 'No gene set that you can open for that.');
$g = $scope[0];
if (moop_genome_kind(dirname($g['dir'])) === 'transcripts') {
    $fail(422, "{$g['assembly']} is a transcriptome (assembled transcripts, no genome): its genes have no neighbours.");
}
$loci = moop_gene_loci($g['dir']);
if (!$loci) $fail(404, "No gene coordinates for {$g['gene_set']} (not registered with the genome browser yet).");

$where = ['organism' => $org, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set']];
$rows = [];
if ($id !== '') {
    $gene = (string) $L['row']['feature_uniquename'];
    $self = null;
    foreach ($loci as $chr => $list) {
        foreach ($list as $i => $r) if ($r[3] === $gene) { $self = [$chr, $i, $r]; break 2; }
    }
    if (!$self) $fail(404, "$gene has no coordinates in {$g['gene_set']}.");
    [$chr, $i, $me] = $self;
    $list   = $loci[$chr];
    $minus  = $me[2] === '-';
    $dist   = isset($_GET['distance']) ? max(0, min(5000000, (int) $_GET['distance'])) : null;
    $n      = $dist === null ? max(1, min(50, (int) ($_GET['n'] ?? 5))) : PHP_INT_MAX;
    $gap    = fn(array $o) => max(0, max($o[0], $me[0]) - min($o[1], $me[1]) - 1);
    // Walk out from the gene in both directions along the sequence; stop at n genes or past distance.
    // Genes are sorted by start, so a long gene can sit further along the list yet overlap -- both
    // walks therefore test the gap, not the index.
    foreach ([-1, 1] as $step) {
        $taken = 0;
        for ($j = $i + $step; $j >= 0 && $j < count($list) && $taken < $n; $j += $step) {
            $o = $list[$j];
            $d = $gap($o);
            if ($dist !== null && $d > $dist) {
                if ($step === 1) break;      // further starts only move away
                continue;                    // a lower start may still overlap (long gene): keep looking
            }
            $lower = $o[0] < $me[0] || ($o[0] === $me[0] && $j < $i);
            $side  = $d === 0 ? 'overlapping' : (($lower xor $minus) ? 'upstream' : 'downstream');
            $rows[] = ['gene' => $o[3], 'side' => $side, 'distance' => $d, 'seqname' => (string) $chr,
                       'start' => $o[0], 'end' => $o[1], 'strand' => $o[2]];
            $taken++;
        }
    }
    $order = ['upstream' => 0, 'overlapping' => 1, 'downstream' => 2];
    usort($rows, fn($a, $b) => [$order[$a['side']], $a['side'] === 'upstream' ? -$a['distance'] : $a['distance']]
                            <=> [$order[$b['side']], $b['side'] === 'upstream' ? -$b['distance'] : $b['distance']]);
    $about = $where + ['gene' => $gene, 'seqname' => (string) $chr, 'start' => $me[0], 'end' => $me[1], 'strand' => $me[2]]
           + ($dist !== null ? ['distance' => $dist] : ['n' => $n]);
} else {
    $chr   = (string) $_GET['seqname'];
    $flank = max(0, min(5000000, (int) ($_GET['flank'] ?? 0)));
    $from  = max(1, (int) $_GET['start'] - $flank);
    $to    = (int) $_GET['end'] + $flank;
    if ($to < $from || $to - $from > 10000000) $fail(400, 'start must be <= end, and the region at most 10,000,000 bases.');
    if (!isset($loci[$chr])) $fail(404, "$chr: no genes on that sequence in {$g['gene_set']} (or no such sequence).");
    foreach ($loci[$chr] as $o) {
        if ($o[0] > $to) break;
        if ($o[1] < $from) continue;
        $rows[] = ['gene' => $o[3], 'seqname' => (string) $chr, 'start' => $o[0], 'end' => $o[1], 'strand' => $o[2]];
    }
    $about = $where + ['seqname' => (string) $chr, 'start' => $from, 'end' => $to];
}

// Names and descriptions, one query.
if ($rows) {
    $dbh = getDbConnection($g['db']);
    $st  = $dbh->prepare("SELECT f.feature_uniquename, " . moop_gene_name_sql('f') . ", f.feature_description FROM feature f
                           WHERE f.gene_set_id = ? AND f.feature_uniquename IN (SELECT value FROM json_each(?))");
    $st->execute([(int) $g['gene_set_id'], json_encode(array_column($rows, 'gene'))]);
    $info = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$u, $nm, $d]) $info[$u] = ['name' => moop_gene_symbol($nm, $u), 'description' => (string) $d];
    foreach ($rows as &$r) $r = ['gene' => $r['gene']] + ($info[$r['gene']] ?? ['name' => '', 'description' => '']) + $r;
    unset($r);
}

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    $cols = $id !== '' ? ['gene', 'name', 'description', 'side', 'distance', 'seqname', 'start', 'end', 'strand']
                       : ['gene', 'name', 'description', 'seqname', 'start', 'end', 'strand'];
    echo implode("\t", $cols) . "\n";
    foreach ($rows as $r) echo implode("\t", array_map(fn($c) => str_replace(["\t", "\n"], ' ', (string) ($r[$c] ?? '')), $cols)) . "\n";
    exit;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, ($id !== '' ? 'around' : 'region') => $about, 'total' => count($rows), 'genes' => $rows],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
