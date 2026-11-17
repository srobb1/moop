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
            $gs_out[] = [
                'gene_set'  => $gs,
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
