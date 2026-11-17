<?php
/**
 * api/v1/pathways — the KEGG pathways of a gene set (or of an organism, or one assembly), with how many
 * genes are in each: the table on the gene-set / organism pages. Use it to find a pathway's id by name, then
 * api/v1/pathway_genes for its genes.
 *
 * GET organism (required); assembly, gene_set (optional). With gene_set (and assembly): that gene set's
 *     table; otherwise every gene set of the organism (or assembly) this visitor may open, merged -- genes
 *     then per gene set ("assembly/gene_set" => count), never summed (gene sets of one genome share genes).
 *
 * Rows: pathway (map id), name, class, sub, genes, kos_found (the gene set's KOs in the pathway), kos_total
 * (KOs in the KEGG reference pathway, when the site has the reference). Cached per gene set
 * (moop_kegg_gene_set_pathways(), lib/kegg.php). Rate-limit class: api.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/annotation_genes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
$json = function (int $code, array $body) {
    http_response_code($code);
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
};
$org = trim((string) ($_GET['organism'] ?? ''));
$asm = trim((string) ($_GET['assembly'] ?? ''));
$gs  = trim((string) ($_GET['gene_set'] ?? ''));
if ($org === '') $json(400, ['error' => 'organism is required.']);
$scope = moop_v1_scope([$org], $asm, $gs);
if (!$scope) $json(404, ['error' => "No gene set that you can open for $org."]);

try {
    if ($gs !== '' && count($scope) === 1) {
        $g = $scope[0];
        $dir = ConfigManager::getInstance()->getPath('organism_data') . "/$org/{$g['assembly']}/{$g['gene_set']}";
        foreach (moop_kegg_visible_gene_sets($org, $g['db'], $g['assembly']) as $v) if ($v['gene_set'] === $gs) $dir = $v['dir'];
        $t = moop_kegg_gene_set_pathways($g['db'], $g['gene_set_id'], $dir);
        $json(200, ['organism' => $org, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
                    'total' => count($t['rows']), 'pathways' => $t['rows'], 'kegg_reference_date' => $t['reference_date']]);
    }
    $t = moop_kegg_organism_pathways($org, $scope[0]['db'], $asm);
    $json(200, ['organism' => $org] + ($asm !== '' ? ['assembly' => $asm] : []) + ['gene_sets' => $t['gene_sets'],
                'total' => count($t['rows']), 'pathways' => $t['rows'], 'kegg_reference_date' => $t['reference_date']]);
} catch (Throwable $e) {
    error_log("api/v1/pathways $org: " . $e->getMessage());
    $json(500, ['error' => 'The pathway table could not be read.']);
}
