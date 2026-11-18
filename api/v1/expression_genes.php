<?php
/**
 * api/v1/expression_genes — genes by RNA-seq expression in one experiment: "which genes are highest
 * in stage 6?", "genes specific to the pharynx sample". The gene record answers the other direction
 * (where one gene is expressed).
 *
 * GET organism, assembly, gene_set  (required) a gene set with expression data -- the catalog lists
 *               its experiments (has.expression)
 *     experiment  (optional) the experiment's id; without it (or without group): the experiments and
 *               their groups (conditions), so the caller can choose
 *     group     a group (condition / stage / tissue) of that experiment -- its full name, or a unique start
 *               of it ("Stage 6" for "Stage 6 (7–9 d)")
 *     sort      level (default: highest mean in the group first) | specific (highest ratio of the
 *               group's mean to the next-highest group's: genes that mark this condition)
 *     n         genes to return (default 100, at most 2,000)
 *     format    json (default) | tsv
 *
 * Only genes DETECTED in the group (its mean reaches the experiment's own detect_threshold) are
 * listed. Values are the experiment's unit (TPM, RPKM, CPM -- given). Same bundles and access rules
 * as the gene page and the Expression tool (moop_expression_bundle_experiments()).
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['annotation_genes', 'expression_functions'] as $__lib) require_once __DIR__ . "/../../lib/$__lib.php";
header('Cache-Control: private, no-store');

$fail = function (int $code, string $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
};
$json = function (array $out) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1] + $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
};
$org = trim((string) ($_GET['organism'] ?? ''));
$asm = trim((string) ($_GET['assembly'] ?? ''));
$gs  = trim((string) ($_GET['gene_set'] ?? ''));
if ($org === '' || $asm === '' || $gs === '') $fail(400, 'organism, assembly and gene_set are required.');
$scope = moop_v1_scope([$org], $asm, $gs);
if (!$scope) $fail(404, 'No gene set that you can open for that.');
$g = $scope[0];
$experiments = moop_v1_expression_experiments($org, $g['assembly'], $g['gene_set']);
if (!$experiments) $fail(404, "{$g['gene_set']} has no expression data that you can open.");

$slug  = trim((string) ($_GET['experiment'] ?? ''));
$group = trim((string) ($_GET['group'] ?? ''));
$exp   = null;
foreach ($experiments as $e) if ($e['slug'] === $slug) $exp = $e;
if ($exp === null || $group === '') {
    // Not enough to answer: say what there is to choose from.
    $json(['organism' => $org, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
           'experiments' => array_map('moop_v1_expression_describe', array_values($experiments)),
           'notes' => [$slug !== '' && $exp === null ? "No experiment '$slug' here." : 'Choose an experiment and one of its groups.']]);
}
$groups = array_values(array_unique(array_column($exp['samples'], 'group_name')));
if (!in_array($group, $groups, true)) {
    // "Stage 6" for "Stage 6 (7–9 d)": a unique case-insensitive start of one group name is enough.
    $starts = array_values(array_filter($groups, fn($x) => stripos($x, $group) === 0));
    if (count($starts) === 1) $group = $starts[0];
}
if (!in_array($group, $groups, true)) $fail(400, "No group '$group' in {$exp['slug']}. Groups: " . implode(', ', $groups) . '.');

$sort = ($_GET['sort'] ?? '') === 'specific' ? 'specific' : 'level';
$n    = max(1, min(2000, (int) ($_GET['n'] ?? 100)));
$thr  = (float) $exp['detect_threshold'];

// One pass over the whole matrix.
$rows = [];
$fh = @fopen($exp['tpm_file'], 'r');
if (!$fh) $fail(500, 'The expression table could not be read.');
fgets($fh);
while (($line = fgets($fh)) !== false) {
    $cells = explode("\t", rtrim($line, "\r\n"));
    $vals = [];
    foreach ($exp['tpm_columns'] as $c) {
        $v = $cells[$c] ?? '';
        $vals[] = ($v === '' || $v === 'NA' || !is_numeric($v)) ? null : (float) $v;
    }
    $means = [];
    foreach (moop_expression_group_means($exp['samples'], $vals) as $m) if ($m['mean'] !== null) $means[$m['group']] = $m['mean'];
    $mine = $means[$group] ?? null;
    if ($mine === null || $mine < $thr) continue;
    $others = $means;
    unset($others[$group]);
    $next = $others ? max($others) : 0.0;
    $rows[] = ['feature' => $cells[0], 'mean' => round($mine, 3), 'next_highest' => round($next, 3),
               'ratio' => round($mine / max($next, $thr), 2), 'top_group' => array_search(max($means), $means, true)];
}
fclose($fh);
$detected = count($rows);
usort($rows, $sort === 'specific'
    ? fn($a, $b) => [$b['ratio'], $b['mean']] <=> [$a['ratio'], $a['mean']]
    : fn($a, $b) => [$b['mean'], $a['feature']] <=> [$a['mean'], $b['feature']]);
$rows = array_slice($rows, 0, $n);

// The matrix's ids are genes (or transcripts on an old bundle): report the gene, its name.
$root = moop_expression_root_genes(array_column($rows, 'feature'), $g['db'], (int) $g['gene_set_id']);
$genes = array_values(array_unique(array_map(fn($r) => $root[$r['feature']] ?? $r['feature'], $rows)));
$info = [];
if ($genes) {
    $st = getDbConnection($g['db'])->prepare("SELECT f.feature_uniquename, " . moop_gene_name_sql('f') . ", f.feature_description FROM feature f
                                               WHERE f.gene_set_id = ? AND f.feature_uniquename IN (SELECT value FROM json_each(?))");
    $st->execute([(int) $g['gene_set_id'], json_encode($genes)]);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$u, $nm, $d]) $info[$u] = ['name' => moop_gene_symbol($nm, $u), 'description' => (string) $d];
}
foreach ($rows as &$r) {
    $gene = $root[$r['feature']] ?? $r['feature'];
    $r = ['gene' => $gene] + ($info[$gene] ?? ['name' => '', 'description' => '']) + array_diff_key($r, ['feature' => 1])
       + ($gene !== $r['feature'] ? ['measured_as' => $r['feature']] : []);
}
unset($r);

$about = ['organism' => $org, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'], 'experiment' => $exp['slug'],
          'group' => $group, 'unit' => $exp['unit'], 'detect_threshold' => $thr, 'sort' => $sort,
          'detected_in_group' => $detected, 'shown' => count($rows)];
$notes = ["mean = this group's mean ({$exp['unit']}); next_highest = the highest other group's mean; ratio = mean / next_highest "
        . "(the threshold when that is lower). Only genes detected in the group (mean >= {$thr}) are listed."];
if (($_GET['format'] ?? '') === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    echo "gene\tname\tdescription\tmean\tnext_highest\tratio\ttop_group\n";
    foreach ($rows as $r) echo implode("\t", array_map(fn($v) => str_replace(["\t", "\n"], ' ', (string) $v),
        [$r['gene'], $r['name'], $r['description'], $r['mean'], $r['next_highest'], $r['ratio'], $r['top_group']])) . "\n";
    foreach ($notes as $x) echo "# $x\n";
    exit;
}
$json(['query' => $about, 'genes' => $rows, 'notes' => $notes]);

/** The experiments of one gene set this visitor may see (the gene page's own list). */
function moop_v1_expression_experiments(string $org, string $asm, string $gs): array {
    $dir = moop_expression_bundle_dir($org, $asm, $gs);
    if (!is_dir($dir)) return [];
    $overrides = moop_expression_load_overrides(
        rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/expression_overrides.json');
    return moop_expression_bundle_experiments($dir, "$org/$asm/$gs/", $overrides);
}

/** An experiment for a chooser: id, label, unit, citation, groups in sample order with replicate counts. */
function moop_v1_expression_describe(array $e): array {
    $groups = [];
    foreach ($e['samples'] as $s) $groups[$s['group_name']] = ($groups[$s['group_name']] ?? 0) + 1;
    return ['experiment' => $e['slug'], 'label' => $e['label'], 'unit' => $e['unit'],
            'citation' => (string) ($e['meta']['citation'] ?? ''), 'assay' => (string) ($e['meta']['assay'] ?? ''),
            'detect_threshold' => $e['detect_threshold'],
            'groups' => array_map(fn($k, $v) => ['group' => $k, 'samples' => $v], array_keys($groups), $groups)];
}
