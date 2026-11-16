<?php
/**
 * The organism → assembly → gene-set scope selector — server side.
 *
 * ONE implementation for every page where a visitor picks organisms to work on (Annotation
 * Search, Data Exporter). Until 2026-11-16 each page built its own tree and rendered its own
 * copy of the list, and the copies drifted every time one was touched: the Data Exporter
 * ignored ?organism= / ?assembly= / ?gene_set= / ?group= entirely, so the "MOOPmart" link on
 * every gene-set page opened with nothing selected, while Search honoured all four.
 * notes/SHARED_SCOPE_SELECTOR_PLAN.md has the history.
 *
 * Pieces:
 *   moop_scope_selector_data()     -- the tree + display info, from the visitor's access
 *   moop_scope_context_from_request() -- what to pre-select, from where the visitor came
 *   includes/scope_selector.php    -- the card (list, filter, Selected panel, counts, modals)
 *   js/modules/scope-selector.js   -- the behaviour
 *
 * BLAST and Retrieve Sequences use a different, single-select component
 * (includes/source-list.php); folding them in is Phase 2/3 of the plan.
 */

require_once __DIR__ . '/functions_access.php';
require_once __DIR__ . '/functions_data.php';
require_once __DIR__ . '/extract_search_helpers.php';   // parseContextParameters(), parseOrganismParameter()

/**
 * Everything the selector renders, built from the gene sets this visitor may see.
 * BLAST-only genomes are left out (getAccessibleGeneSets() default): they have no gene set,
 * so nothing here can search or export them.
 *
 * @return array{
 *   tree: array<string, array<string, list<string>>>,  organism => assembly => [gene_set, ...], all sorted
 *   organism_info: array<string, array{genus:string, species:string, common_name:string}>,
 *   assembly_names: array<string, array<string, string>>,  human-readable name, '' when none
 *   organism_groups: array<string, list<string>>,          the visitor's groups per organism
 *   sources_by_group: array                                 getAccessibleGeneSets(), for the group context
 * }
 */
function moop_scope_selector_data(): array {
    $organism_data    = ConfigManager::getInstance()->getPath('organism_data');
    $sources_by_group = getAccessibleGeneSets();
    $sources          = flattenSourcesList($sources_by_group);

    $tree = [];
    $organism_info = [];
    $assembly_names = [];
    foreach ($sources as $src) {
        $org = $src['organism'];
        $asm = $src['assembly'];
        $gs  = (string)($src['gene_set'] ?? '');

        $tree[$org][$asm] ??= [];
        if ($gs !== '' && !in_array($gs, $tree[$org][$asm], true)) {
            $tree[$org][$asm][] = $gs;
        }
        if (!isset($organism_info[$org])) {
            $info = loadOrganismInfo($org, $organism_data) ?: [];
            $organism_info[$org] = [
                'genus'       => $info['genus']       ?? '',
                'species'     => $info['species']     ?? '',
                'common_name' => $info['common_name'] ?? '',
            ];
        }
        if (!isset($assembly_names[$org][$asm])) {
            $assembly_names[$org][$asm] = genome_display_name($src['genome_name'] ?? '', $asm);   // '' for none / "NA"
        }
    }

    ksort($tree);
    foreach ($tree as &$asms) {
        ksort($asms);
        foreach ($asms as &$gene_sets) sort($gene_sets);
        unset($gene_sets);
    }
    unset($asms);

    return [
        'tree'             => $tree,
        'organism_info'    => $organism_info,
        'assembly_names'   => $assembly_names,
        // From the visitor's own sources, so a chip never names a group they cannot see.
        'organism_groups'  => moop_organism_groups_map($sources),   // sorted, one order site-wide
        'sources_by_group' => $sources_by_group,
    ];
}

/**
 * What to pre-select, from the page the visitor came from. Returns null for "nothing" --
 * selection is mandatory and there is never an implicit select-all (an unscoped fan-out over
 * every organism is the most expensive query the site can run).
 *
 *   ?organism=X [&assembly=Y [&gene_set=Z]]  -> that organism, narrowed to the assembly / gene set
 *   ?group=G                                 -> every organism in the group
 *   ?organisms[]=A&organisms[]=B, ?organisms=A,B -> those organisms (GET or POST)
 *
 * The context_* spellings are accepted too (parseContextParameters()). Names the visitor cannot
 * see are dropped, so a context can never select, or reveal, anything the list does not show.
 *
 * @return array{organisms: list<string>, assembly?: string, gene_set?: string}|null
 */
function moop_scope_context_from_request(array $scope): ?array {
    $tree = $scope['tree'];
    $ctx  = parseContextParameters();

    if ($ctx['organism'] !== '') {
        if (!isset($tree[$ctx['organism']])) return null;
        $out = ['organisms' => [$ctx['organism']]];
        if ($ctx['assembly'] !== '') $out['assembly'] = $ctx['assembly'];
        if ($ctx['gene_set'] !== '') $out['gene_set'] = $ctx['gene_set'];
        return $out;
    }

    if ($ctx['group'] !== '') {
        $orgs = array_keys($scope['sources_by_group'][$ctx['group']] ?? []);
    } else {
        // Array or comma-separated string, as the other tools accept it.
        $orgs = parseOrganismParameter($_POST['organisms'] ?? $_GET['organisms'] ?? '')['organisms'];
    }
    $orgs = array_values(array_unique(array_filter(
        array_map('strval', array_filter($orgs, 'is_scalar')),
        fn($o) => isset($tree[$o])
    )));
    return $orgs ? ['organisms' => $orgs] : null;
}
