<?php
/**
 * The organism → assembly → gene-set scope selector — server side.
 *
 * ONE implementation for every page where a visitor picks organisms to work on (Annotation
 * Search, Data Exporter; single mode: Retrieve Sequences, BLAST, Primer BLAST). Until 2026-11-16 each page built its own tree and rendered its own
 * copy of the list, and the copies drifted every time one was touched: the Data Exporter
 * ignored ?organism= / ?assembly= / ?gene_set= / ?group= entirely, so the "MOOPmart" link on
 * every gene-set page opened with nothing selected, while Search honoured all four.
 * notes/archive/SHARED_SCOPE_SELECTOR_PLAN.md has the history.
 *
 * Pieces:
 *   moop_scope_selector_data()     -- the tree + display info, from the visitor's access
 *   moop_scope_context_from_request() -- what to pre-select, from where the visitor came
 *   includes/scope_selector.php    -- the card (list, filter, Selected panel, counts, modals)
 *   js/modules/scope-selector.js   -- the behaviour
 *
 * Single mode serves the tools that work on exactly one gene set (Retrieve Sequences, BLAST,
 * Primer BLAST) -- moop_scope_single_state() decides their pre-selection. It replaced the old
 * source list (includes/source-list.php) on 2026-11-16.
 */

require_once __DIR__ . '/functions_access.php';
require_once __DIR__ . '/functions_data.php';
require_once __DIR__ . '/extract_search_helpers.php';   // parseContextParameters(), parseOrganismParameter()

/**
 * Everything the selector renders, built from the gene sets this visitor may see.
 * BLAST-only genomes are left out unless $include_blast_only (BLAST asks for them): they have
 * no gene set, so Search, the Data Exporter or Retrieve Sequences could do nothing with one.
 * Included, each is an entry whose gene set is 'blast_only', listed in 'blast_only'.
 *
 * @return array{
 *   tree: array<string, array<string, list<string>>>,  organism => assembly => [gene_set, ...], all sorted
 *   organism_info: array<string, array{genus:string, species:string, common_name:string}>,
 *   assembly_names: array<string, array<string, string>>,  human-readable name, '' when none
 *   organism_groups: array<string, list<string>>,          the visitor's groups per organism
 *   blast_only: array<string, array<string, true>>,        BLAST-only genomes, when included
 *   sources_by_group: array                                 getAccessibleGeneSets(), for the group context
 * }
 */
function moop_scope_selector_data(bool $include_blast_only = false): array {
    $organism_data    = ConfigManager::getInstance()->getPath('organism_data');
    $sources_by_group = getAccessibleGeneSets(null, null, null, $include_blast_only);
    $sources          = flattenSourcesList($sources_by_group);

    $tree = [];
    $organism_info = [];
    $assembly_names = [];
    $blast_only = [];   // [organism][assembly] = true: a BLAST-only genome (its "gene set" is 'blast_only')
    foreach ($sources as $src) {
        $org = $src['organism'];
        $asm = $src['assembly'];
        $gs  = (string)($src['gene_set'] ?? '');

        $tree[$org][$asm] ??= [];
        if (!empty($src['blast_only'])) $blast_only[$org][$asm] = true;
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
        'blast_only'       => $blast_only,
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

/**
 * Single mode (Retrieve Sequences, BLAST, Primer BLAST): which gene set starts selected, and
 * what the filter starts with. One rule for all three pages (Sofia, 2026-11-16):
 *
 *   selected -- the gene set the form just submitted (a finished search comes back with the
 *               same choice), else $fallback (the page's own context-based choice), else the
 *               first gene set in group order, as the old list did -- EXCEPT arriving from a group or an organisms list: nothing, the
 *               visitor chooses within the filtered list.
 *   filter   -- arriving from a group: the group's name. From an organism / assembly / gene-set
 *               page: the organism's name, so its gene sets show with the chosen one highlighted.
 *
 * Only keys that are rows of this visitor's tree are ever selected.
 *
 * @param string $fallback    "organism|assembly|gene_set" the page chose (prepareSourceSelection())
 * @param bool   $auto_first  false: with no submitted / context choice, select nothing
 *   only     -- arriving with an organisms list (?organisms[]= / POST organisms[], what the
 *               group, home and multi-organism pages' tool buttons send): the list shows only
 *               those organisms until the visitor clicks "Show all". Text cannot express "these
 *               35", so this is separate from the filter.
 *
 * @return array{selected: string, filter: string, only: list<string>}
 */
function moop_scope_single_state(array $scope, string $fallback = '', bool $auto_first = true): array {
    $tree = $scope['tree'];
    $ctx  = parseContextParameters();
    $is_row = function (string $key) use ($tree): bool {
        $p = explode('|', $key);
        return count($p) === 3 && in_array($p[2], $tree[$p[0]][$p[1]] ?? [], true);
    };

    $filter = '';
    $group_or_list = false;
    if ($ctx['organism'] !== '' && isset($tree[$ctx['organism']])) {
        $info   = $scope['organism_info'][$ctx['organism']] ?? [];
        $filter = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $ctx['organism']);
    } elseif ($ctx['group'] !== '') {
        $filter = $ctx['group'];
        $group_or_list = true;
    }
    // An organisms list narrows the list to those organisms (names this visitor cannot see are
    // dropped). Only without an organism context, which already names the one to show.
    $only = [];
    if ($ctx['organism'] === '') {
        $only = array_values(array_unique(array_filter(
            array_map('strval', parseOrganismParameter($_POST['organisms'] ?? $_GET['organisms'] ?? '')['organisms']),
            fn($o) => isset($tree[$o])
        )));
        if ($only) $group_or_list = true;
    }

    $posted = (string)($_POST['selected_source'] ?? '');
    if ($posted !== '' && $is_row($posted)) {
        $selected = $posted;
    } elseif ($group_or_list) {
        $selected = '';
    } elseif ($fallback !== '' && $is_row($fallback)) {
        $selected = $fallback;
    } elseif (!$auto_first) {
        $selected = '';   // the tool starts with nothing chosen (Primer BLAST always has)
    } else {
        // The first gene set in group order -- the row the old source list selected first,
        // so opening a tool directly starts where it always did.
        $selected = '';
        foreach (flattenSourcesList($scope['sources_by_group']) as $src) {
            $key = $src['organism'] . '|' . $src['assembly'] . '|' . ($src['gene_set'] ?? '');
            if ($is_row($key)) { $selected = $key; break; }
        }
    }
    return ['selected' => $selected, 'filter' => $filter, 'only' => $only];
}

/**
 * Narrow the selector's data to a set of organisms -- and, where given, to some of their
 * assemblies (a group can hold only some of an organism's assemblies). For the group page,
 * whose search is limited to the group: rows outside it are not rendered at all.
 *
 * @param array $organisms  [organism => [assembly, ...]] (getAccessibleOrganismsInGroup()), or
 *                          [organism => true] for every assembly of that organism
 */
function moop_scope_restrict(array $scope, array $organisms): array {
    $tree = [];
    foreach ($scope['tree'] as $org => $asms) {
        if (!isset($organisms[$org])) continue;
        $keep = $organisms[$org];
        foreach ($asms as $asm => $gss) {
            if (is_array($keep) && $keep && !in_array($asm, $keep, true)) continue;
            $tree[$org][$asm] = $gss;
        }
    }
    $scope['tree'] = $tree;
    return $scope;
}
