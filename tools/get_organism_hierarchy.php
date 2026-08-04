<?php
/**
 * Returns organism/assembly/gene_set hierarchy for a comma-separated list of organisms.
 * Used by the scope filter modal.
 */

include_once __DIR__ . '/tool_init.php';

header('Content-Type: application/json');

$organisms_param = $_GET['organisms'] ?? '';
if (empty($organisms_param)) {
    echo json_encode([]);
    exit;
}

$requested = array_values(array_filter(array_map('trim', explode(',', $organisms_param))));
$accessible = getAccessibleGeneSets();
$organism_data = ConfigManager::getInstance()->getPath('organism_data');

// Flatten group-keyed result into $by_organism[$org][$accession|$gene_set] = source
// Using a composite key to deduplicate across groups.
$by_organism = [];
foreach ($accessible as $group => $org_data) {
    foreach ($org_data as $org => $sources) {
        if (!in_array($org, $requested, true)) {
            continue;
        }
        foreach ($sources as $source) {
            $accession = $source['genome_accession'] ?? $source['assembly'];
            $key = $accession . '|' . $source['gene_set'];
            $by_organism[$org][$key] = $source;
        }
    }
}

// Build output: [{organism, assemblies: [{accession, name, gene_sets: []}]}]
$result = [];
foreach ($requested as $org) {
    if (!isset($by_organism[$org])) {
        continue;
    }

    // Group by assembly accession
    $assemblies = [];
    foreach ($by_organism[$org] as $source) {
        $accession = $source['genome_accession'] ?? $source['assembly'];
        if (!isset($assemblies[$accession])) {
            $assemblies[$accession] = [
                'accession' => $accession,
                'name'      => $source['genome_name'] ?? $accession,
                'gene_sets' => [],
            ];
        }
        if (!in_array($source['gene_set'], $assemblies[$accession]['gene_sets'], true)) {
            $assemblies[$accession]['gene_sets'][] = $source['gene_set'];
        }
    }

    // Common name for the scope tree's organism row. The tree previously showed only
    // the directory name with underscores swapped for spaces, so a reader scanning a
    // group of 49 bats got 49 scientific binomials and nothing they recognise the
    // animal by -- while every other page that names an organism (organism, gene set,
    // gene) already shows the common name. All 85 organism.json files carry one, so
    // this is a display gap, not missing data.
    //
    // Read per organism rather than from the cache: loadOrganismInfo() is a small JSON
    // read, the list here is the organisms already in scope, and it keeps this endpoint
    // independent of whether the organism cache happens to be fresh.
    $org_info    = loadOrganismInfo($org, $organism_data);
    $common_name = trim((string) ($org_info['common_name'] ?? ''));

    $result[] = [
        'organism'    => $org,
        'common_name' => $common_name,
        'assemblies'  => array_values($assemblies),
    ];
}

echo json_encode($result);
