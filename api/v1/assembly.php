<?php
/**
 * api/v1/assembly — assembly statistics and gene-set completeness: what the assembly and gene-set pages
 * show in their summary cards.
 *
 * GET organism (required); assembly (optional: one assembly; otherwise every assembly of the organism this
 *     visitor may open).
 *
 * Per assembly: kind (genome | transcripts -- assembled transcripts have no genome around their genes),
 * stats (the pipeline's assembly_stats.json: total_length, sequences, n50, l50, n90, l90, longest,
 * contig_n50, gc_pct, gap_pct ...; else the scaffold figures derived from the .fai -- moop_assembly_stats_or_fallback()),
 * and gene_sets: genes, transcripts, busco (each run: lineage, mode, C/S/D/F/M percentages and counts,
 * plus 'notation' "C:79.4%[S:76.3%,D:3.1%],F:4.1%,M:16.5%,n:954"). Only gene sets the visitor may open;
 * an assembly with none of them is absent. Rate-limit class: api.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['annotation_genes', 'gene_set_summary', 'busco', 'genomic_regions'] as $__lib) require_once __DIR__ . "/../../lib/$__lib.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
$json = function (int $code, array $body) {
    http_response_code($code);
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
};
$org = trim((string) ($_GET['organism'] ?? ''));
$asm = trim((string) ($_GET['assembly'] ?? ''));
if ($org === '') $json(400, ['error' => 'organism is required.']);
$scope = moop_v1_scope([$org], $asm);
if (!$scope) $json(404, ['error' => "No assembly that you can open for $org" . ($asm !== '' ? " named $asm" : '') . '.']);

// Gene and transcript counts from the organism's summary file (built in the background; may be absent).
$summary = null;
$sfile = moop_gene_set_summary_file($org);
if ($sfile !== '' && is_file($sfile) && !moop_gene_set_summary_is_stale(dirname($scope[0]['db']), $sfile)) {
    $summary = json_decode((string) @file_get_contents($sfile), true);
}
$drop = array_flip(['fasta_md5', 'fasta_mtime', 'fasta_bytes', 'version', 'from_pipeline']);
$out = [];
foreach ($scope as $g) {
    $adir = dirname($g['dir']);
    if (!isset($out[$g['assembly']])) {
        $stats = moop_assembly_stats_or_fallback($adir, $summary);
        $out[$g['assembly']] = ['assembly' => $g['assembly'], 'kind' => moop_genome_kind($adir),
                                'stats' => $stats ? array_diff_key($stats, $drop) : null,
                                'page' => moop_public_url('tools/assembly.php?' . http_build_query(['organism' => $org, 'assembly' => $g['assembly']])),
                                'gene_sets' => []];
    }
    $counts = $summary['gene_sets']["{$g['assembly']}/{$g['gene_set']}"] ?? null;
    $busco  = moop_load_busco($g['dir']);
    $out[$g['assembly']]['gene_sets'][] = [
        'gene_set'    => $g['gene_set'],
        'genes'       => $counts['genes'] ?? null,
        'transcripts' => $counts['transcripts'] ?? null,
        'busco'       => $busco ? array_map(fn($r) => array_diff_key($r, ['protein_fasta_md5' => 1]) + ['notation' => moop_busco_notation($r)], $busco['runs']) : null,
    ];
}
$json(200, ['organism' => $org, 'assemblies' => array_values($out),
            'notes' => ['null = not known (not computed yet), never zero.']]);
