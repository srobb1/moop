<?php
/**
 * Baseline harness for the four hierarchy walkers.
 * Run BEFORE and AFTER the consolidation; the two outputs must be byte-identical.
 */
unset($_SERVER['REMOTE_ADDR']); $_SESSION = [];
chdir('/var/www/html/moop');
require_once '/var/www/html/moop/includes/config_init.php';
require_once '/var/www/html/moop/lib/functions_database.php';
require_once '/var/www/html/moop/lib/database_queries.php';
require_once '/var/www/html/moop/lib/parent_functions.php';
require_once '/var/www/html/moop/lib/moopmart_functions.php';
require_once '/var/www/html/moop/lib/extract_search_helpers.php';

// Organisms chosen to discriminate: T2G suffix ids, RefSeq prefix ids, Ensembl
// independent ids, transcript-level annotations, gene-level annotations.
$cases = [
    ['Nematostella_vectensis',        'NV2t021704001.1',  'NV2t021704001.1:pep'],
    ['Bradyrhizobium_diazoefficiens', 'WP_011083461.1',   'cds-WP_011083461.1'],
    ['Drosophila_melanogaster',       'FBtr0070000',      'FBpp0291548'],
    ['Schmidtea_lugubris',            'SlugcT0000001.1',  'SlugcT0000001.1:pep'],
    ['Petromyzon_marinus',            'PM00915.1',        'PM00915.1:pep'],
];

function gsids($db) {
    $r = fetchData('SELECT gene_set_id FROM gene_set', $db, []);
    return array_map(fn($x) => (int)$x['gene_set_id'], $r);
}
function dump($label, $val) {
    echo "### $label\n";
    echo json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
}

foreach ($cases as [$org, $parentish, $childish]) {
    $db = "organisms/$org/organism.sqlite";
    if (!file_exists($db)) { echo "== $org: NO DB ==\n"; continue; }
    $gs = gsids($db);
    echo "\n======== $org ========\n";

    // 1. gene page — whole chain
    foreach ([$parentish, $childish] as $u) {
        $rows = getAncestors($u, $db, $gs);
        dump("getAncestors($u)", array_map(fn($r) => [$r['feature_uniquename'], $r['feature_type']], $rows));
    }

    // 2. MOOPmart — to the root
    dump("moopmartResolveInputIds([$parentish,$childish])",
         moopmartResolveInputIds([$parentish, $childish], $db, $gs));

    // 3. sequence retrieval — both directions
    dump("expandFeaturesToAllSequenceTypes([$childish])",
         expandFeaturesToAllSequenceTypes([$childish], $db, '', '', $gs));

    // 4. search — to the annotation-bearing level
    dump("moop_annotation_levels", moop_annotation_levels($db, $org));
    $hits = searchFeaturesByUniquenameForSearch(preg_replace('/\.\d+.*$/', '', $parentish), $db, '', '', '', []);
    dump("searchFeaturesByUniquenameForSearch(stem)",
         array_map(fn($r) => [$r['feature_uniquename'], $r['feature_type'], $r['matched_uniquename'] ?? ''], $hits));
}
