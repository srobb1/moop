<?php
/**
 * api/v1/catalog — what this visitor can open: organisms -> assemblies -> gene sets, with
 * complete links to each page and download. The starting point of the agent guide (llms.txt):
 * every other api/v1 call takes organism / assembly / gene_set values from here.
 *
 * api/v1/ is a PROMISE: these names and fields stay as they are once published in llms.txt
 * (notes/AGENT_ACCESS_AUDIT_2026-11-17.md). Add fields freely; never rename or remove one --
 * a change that breaks callers goes in api/v2/.
 *
 * GET, no parameters. JSON. Access: the same per-visitor list the scope selector uses
 * (moop_scope_selector_data()), so an anonymous visitor sees public data only, and a name it
 * cannot open is simply absent -- never "forbidden", which would confirm it exists.
 */

include_once __DIR__ . '/../../tools/tool_init.php';
include_once __DIR__ . '/../../lib/scope_selector.php';
require_once __DIR__ . '/../../lib/gene_set_summary.php';   // + cache_paths.php, busco.php
require_once __DIR__ . '/../../lib/expression_functions.php';
require_once __DIR__ . '/../../lib/anatomy_expression.php';
$expr_overrides = moop_expression_load_overrides(rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/expression_overrides.json');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');   // differs per visitor

$config        = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$seq_types     = $config->getArray('sequence_types', []);
$data          = moop_scope_selector_data(true);

$q = fn(array $params) => http_build_query($params, '', '&', PHP_QUERY_RFC3986);
$download = function (array $where, string $file) use ($q): string {
    return moop_public_url('api/v1/download.php?' . $q($where + ['filename' => $file]));
};

$organisms = [];
foreach ($data['tree'] as $org => $assemblies) {
    $info = $data['organism_info'][$org] ?? [];
    $asm_out = [];
    // Counts for choosing between gene sets -- both files are built in the background and may be
    // missing or stale; then the figures are null ("not known"), never zero.
    $sfile   = moop_gene_set_summary_file($org);
    $summary = !moop_gene_set_summary_is_stale("$organism_data/$org", $sfile)
        ? json_decode((string) @file_get_contents($sfile), true) : null;
    $type_genes = moop_read_gene_set_annotation_counts($org)['type_genes'] ?? [];
    foreach ($assemblies as $asm => $gene_sets) {
        $asm_dir = "$organism_data/$org/$asm";
        $genome  = $seq_types['genome']['pattern'] ?? 'genome.fa';
        $gs_out  = [];
        foreach ($gene_sets as $gs) {
            if ($gs === MOOP_BLAST_ONLY) continue;   // the assembly itself, not a gene set
            $where = ['organism' => $org, 'assembly' => $asm, 'gene_set' => $gs];
            $files = [];
            foreach ($seq_types as $type => $t) {
                if ($type === 'genome' || empty($t['pattern'])) continue;
                if (is_file("$asm_dir/$gs/{$t['pattern']}")) $files[$type] = $download($where, $t['pattern']);
            }
            if (is_file("$asm_dir/$gs/genes.gff")) $files['gff'] = $download($where, 'genes.gff');
            $genes = $summary['gene_sets']["$asm/$gs"]['genes'] ?? null;
            $types = $type_genes["$asm/$gs"] ?? null;
            $pct   = null;
            if ($genes && is_array($types)) {
                $pct = [];
                foreach (['go' => 'Gene Ontology', 'domains' => 'Domains', 'orthologs' => 'Orthologs', 'kegg' => 'Pathways'] as $k => $t) {
                    $pct[$k] = round(100 * (int) ($types[$t] ?? 0) / $genes, 1);
                }
            }
            $busco = moop_load_busco("$asm_dir/$gs");
            // RNA-seq experiments here (api/v1/expression_genes), only those this visitor may see.
            $expr  = is_dir("$asm_dir/$gs/expression")
                ? array_map(fn($e) => ['experiment' => $e['slug'], 'label' => $e['label'], 'unit' => $e['unit']],
                            array_values(moop_expression_bundle_experiments("$asm_dir/$gs/expression", "$org/$asm/$gs/", $expr_overrides)))
                : [];
            $gs_out[] = [
                'gene_set'  => $gs,
                'genes'     => $genes,
                'percent_of_genes_with' => $pct,
                // Every annotation type in this gene set => genes carrying one (the names other calls take:
                // gene_annotations type=, the gene record's sections). null = counts not built yet.
                'annotation_types' => is_array($types) ? (object) array_map('intval', $types) : null,
                'busco'     => $busco ? array_map(fn($r) => "{$r['lineage']} " . moop_busco_notation($r), $busco['runs']) : null,
                'expression_experiments' => $expr,
                'page'      => moop_public_url('tools/gene_set.php?' . $q($where)),
                'downloads' => (object) $files,
            ];
        }
        $blast_only = !empty($data['blast_only'][$org][$asm]);
        $asm_out[] = [
            'assembly'     => $asm,
            'name'         => $data['assembly_names'][$org][$asm] ?? '',
            // A genome-only (BLAST-only) assembly has no assembly page: there is nothing but the genome.
            'page'         => $blast_only ? null
                : moop_public_url('tools/assembly.php?' . $q(['organism' => $org, 'assembly' => $asm])),
            'genome_fasta' => is_file("$asm_dir/$genome")
                ? $download(['organism' => $org, 'assembly' => $asm], $genome) : null,
            'kind'         => moop_genome_kind($asm_dir),   // genome | transcripts (a transcriptome: no genome coordinates)
            'blast_only'   => $blast_only,   // genome only: no genes to search
            'gene_sets'    => $gs_out,
        ];
    }
    $organisms[] = [
        'organism'    => $org,
        'genus'       => $info['genus'] ?? '',
        'species'     => $info['species'] ?? '',
        'common_name' => $info['common_name'] ?? '',
        'groups'      => array_values(array_filter($data['organism_groups'][$org] ?? [], fn($g) => $g !== 'PUBLIC')),
        'page'        => moop_public_url('tools/organism.php?' . $q(['organism' => $org])),
        // Curated "expressed in" records from papers, by anatomy term (api/v1/anatomy_genes).
        'curated_anatomy' => is_file(moop_page_file($org)),
        'assemblies'  => $asm_out,
    ];
}

echo json_encode([
    'api_version' => 1,
    'site'        => $config->getString('siteTitle', ''),
    'guide'       => moop_public_url('llms.txt'),
    'access'      => is_logged_in() || get_access_level() !== 'PUBLIC'
        ? 'Lists what your login can open.'
        : 'Public data only. Restricted data is not listed and cannot be opened without a login.',
    'organisms'   => $organisms,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
