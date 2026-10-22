<?php
/**
 * AJAX endpoint to get annotation sources grouped by type
 * Used by advanced search filter modal
 * 
 * Parameters: organisms (comma-separated list) OR organism (single, for backwards compatibility);
 *             optionally, for one organism, assembly (an accession) and gene_set;
 *             optionally group (a group name): only that group's gene sets;
 *             optionally gene_sets (JSON list of "organism/accession/gene_set"): for the
 *             organisms named in it, only those gene sets
 * Returns: JSON with grouped sources, from only the gene sets this viewer may open
 */

ob_start();

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/database_queries.php';

ob_end_clean();

header('Content-Type: application/json');

// Accept either single organism or comma-separated list of organisms
$organism_param = $_GET['organism'] ?? '';
$organisms_param = $_GET['organisms'] ?? '';

$organisms = [];
if (!empty($organisms_param)) {
    // Multiple organisms provided as comma-separated list
    $organisms = array_filter(array_map('trim', explode(',', $organisms_param)));
} elseif (!empty($organism_param)) {
    // Single organism provided (backwards compatibility)
    $organisms = [$organism_param];
}

if (empty($organisms)) {
    echo json_encode(['error' => 'Missing organism parameter']);
    exit;
}

// Only what this viewer may open. Source names and counts are data: before 2026-10-20 this
// endpoint answered for any organism named, so an anonymous request listed every source and
// count of an organism with no public assembly. ADMIN and IP_IN_RANGE see everything and get
// the organism-wide counts; anyone else gets the sum of the gene sets they may open, never
// the organism's figure, which would include the gene sets they may not.
// Matching against the accessible list also rejects a name that is not an organism at all
// (it is used to build database and cache paths below).
$full_access = has_access('ADMIN') || has_access('IP_IN_RANGE');
// Optional: a group (the group page). A group can hold only some of an organism's gene sets,
// so its counts are those gene sets' own, never the whole organism's.
$group_param = (string)($_GET['group'] ?? '');
$viewer_gene_sets = [];   // organism => ["accession/gene_set" => true]
foreach (getAccessibleGeneSets() as $group_name => $group_orgs) {
    if ($group_param !== '' && (string)$group_name !== $group_param) continue;
    foreach ($group_orgs as $org => $sources) {
        foreach ($sources as $source) {
            $accession = $source['genome_accession'] ?? $source['assembly'];
            $viewer_gene_sets[$org][$accession . '/' . $source['gene_set']] = true;
        }
    }
}
$organisms = array_values(array_filter(array_unique($organisms),
    fn($org) => isset($viewer_gene_sets[$org])));

// Optional scope within a single organism: an assembly (the assembly page), or an assembly
// + gene set (the gene set page). The counts are then that scope's own, so the filter neither
// shows organism-wide numbers nor offers a source with nothing in it. Ignored for several
// organisms -- an accession belongs to one.
$assembly_param = trim((string)($_GET['assembly'] ?? ''));
$gene_set_param = trim((string)($_GET['gene_set'] ?? ''));
if (count($organisms) !== 1 || $assembly_param === '') {
    $assembly_param = $gene_set_param = '';
}

// Optional (the search page): gene_sets, a JSON list of "organism/accession/gene_set" for the
// organisms the user picked only PART of. Those organisms count just the listed gene sets; an
// organism not in the list counts all of its own. Partial organisms only, so a full selection
// of every organism never puts hundreds of gene sets in the URL.
$only_gene_sets = [];   // organism => ["accession/gene_set" => true]
foreach ((array)(json_decode((string)($_GET['gene_sets'] ?? ''), true) ?: []) as $item) {
    $parts = is_string($item) ? explode('/', $item, 2) : [];
    if (count($parts) === 2) $only_gene_sets[$parts[0]][$parts[1]] = true;
}

$organism_data = $config->getPath('organism_data');

// Aggregate sources from all organisms
$aggregated_sources = [];
$type_genes = [];      // type => genes carrying at least one annotation of it
$counts_known = true;  // false while any gene set in scope has no counts built yet
$answered = [];   // the organisms that contributed, for the response
$metadata_path = $config->getPath('metadata_path');
$config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFile($config_file, []);

foreach ($organisms as $organism) {
    $db = "$organism_data/$organism/organism.sqlite";

    if (!file_exists($db)) {
        continue; // Skip organisms without database
    }

    // The gene sets this request covers: the viewer's, narrowed to the scope asked for.
    $only = $only_gene_sets[$organism] ?? null;
    $keys = array_values(array_filter(array_keys($viewer_gene_sets[$organism]),
        function ($key) use ($assembly_param, $gene_set_param, $only) {
            [$accession, $gene_set] = explode('/', $key, 2);
            return ($assembly_param === '' || $accession === $assembly_param)
                && ($gene_set_param === '' || $gene_set === $gene_set_param)
                && ($only === null || isset($only[$key]));
        }));
    if (!$keys) {
        continue;   // nothing in that scope this viewer may open
    }
    $answered[] = $organism;

    // Counts are GENES per source, built per gene set by housekeeping's background run
    // (getAnnotationSourcesByGeneSet). They add up exactly across gene sets and organisms,
    // since a gene belongs to one gene set -- so every scope, the whole organism included,
    // is the sum of its gene sets. While any gene set in scope is not built yet (after a
    // reload) the numbers are left out rather than shown partial: a viewer who may see
    // everything still gets every source NAME from the organism cache; anyone else gets the
    // names of the built gene sets only, never the organism's, which may include gene sets
    // they cannot open.
    $parts = [];
    $type_parts = [];
    foreach ($keys as $key) {
        [$accession, $gene_set] = explode('/', $key, 2);
        $part = moop_load_gene_set_annotation_counts($organism, $accession, $gene_set);
        if ($part === null) {
            $counts_known = false;
            continue;
        }
        $parts[] = $part;
        $type_parts[] = moop_load_gene_set_annotation_type_genes($organism, $accession, $gene_set) ?? [];
    }
    if (count($parts) === count($keys)) {
        $source_types = moop_sum_annotation_source_counts($parts);
        foreach (moop_sum_type_genes($type_parts) as $type => $n) {
            $type_genes[$type] = ($type_genes[$type] ?? 0) + $n;
        }
    } elseif ($full_access) {
        $source_types = moop_load_annotation_sources_cache($organism) ?? [];
    } else {
        $source_types = moop_sum_annotation_source_counts($parts);
    }

    foreach ($source_types as $type => $sources) {
        foreach ($sources as $source) {
            $name = $source['name'];
            $aggregated_sources[$type][$name]['name'] = $name;
            $aggregated_sources[$type][$name]['count'] =
                ($aggregated_sources[$type][$name]['count'] ?? 0) + (int)$source['count'];
        }
    }
}

// Convert back to indexed array format and add colors/descriptions
$source_types_with_color = [];
foreach ($aggregated_sources as $type => $sources_by_name) {
    $color = 'secondary'; // default
    $description = ''; // default
    if (!empty($annotation_config['annotation_types'][$type]['color'])) {
        $color = $annotation_config['annotation_types'][$type]['color'];
    }
    if (!empty($annotation_config['annotation_types'][$type]['description'])) {
        $description = $annotation_config['annotation_types'][$type]['description'];
    }
    
    $sources = array_values($sources_by_name);
    usort($sources, fn($a, $b) => $b['count'] <=> $a['count']);
    if (!$counts_known) {
        foreach ($sources as &$src) { $src['count'] = null; }
        unset($src);
    }
    $source_types_with_color[$type] = [
        'sources' => $sources,
        'genes' => $counts_known ? ($type_genes[$type] ?? 0) : null,
        'color' => $color,
        'description' => $description
    ];
}

$total_sources = 0;
foreach ($source_types_with_color as $type_data) {
    $total_sources += count($type_data['sources']);
}

// 'count' on a source and 'genes' on a type are GENES (a transcript in a gene set with no gene
// models), or null for all of them while counts are being rebuilt -- see the loop above.
echo json_encode([
    'organisms' => $answered,
    'counts' => $counts_known ? 'genes' : null,
    'source_types' => $source_types_with_color,
    'total_sources' => $total_sources,
]);

?>
