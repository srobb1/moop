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
$accessible = getAccessibleAssemblies();

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

    $result[] = [
        'organism'   => $org,
        'assemblies' => array_values($assemblies),
    ];
}

echo json_encode($result);
