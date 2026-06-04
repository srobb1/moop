<?php
/**
 * Feature ID Search
 *
 * Exact-match search on feature_uniquename across all SQLite databases
 * the current user has access to.  Databases are queried in batches of
 * 10 using SQLite ATTACH so each batch is a single round-trip.
 *
 * GET parameters:
 *   q  - exact feature uniquename to search for (required)
 *
 * Returns JSON: { results: [{uniquename, type, organism, assembly, gene_set, url}] }
 *           or: { error: "message" }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['error' => 'Query required']);
    exit;
}

$config        = ConfigManager::getInstance();
$site          = $config->getString('site', 'moop');
$organism_data = $config->getPath('organism_data');

// Flat list of every source the current user can access
$accessible = flattenSourcesList(getAccessibleAssemblies());

if (empty($accessible)) {
    echo json_encode(['results' => []]);
    exit;
}

// Deduplicate by SQLite path; collect accessible gene_set_ids per organism db
$db_map = [];
foreach ($accessible as $src) {
    $path = "$organism_data/{$src['organism']}/organism.sqlite";
    if (!file_exists($path)) continue;
    if (!isset($db_map[$path])) {
        $db_map[$path] = ['organism' => $src['organism'], 'path' => $path, 'gene_set_ids' => []];
    }
    if (!empty($src['gene_set_id'])) {
        $db_map[$path]['gene_set_ids'][] = (int)$src['gene_set_id'];
    }
}

$db_entries    = array_values($db_map);
$batches       = array_chunk($db_entries, 10);
$feature_types = ['gene', 'mRNA', 'protein', 'polypeptide'];
$results       = [];

foreach ($batches as $batch) {
    try {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $parts  = [];
        $params = [];

        foreach ($batch as $i => $entry) {
            $alias = "db$i";
            $pdo->exec('ATTACH DATABASE ' . $pdo->quote($entry['path']) . " AS $alias");

            $type_ph  = implode(',', array_fill(0, count($feature_types), '?'));
            $gs_ids   = implode(',', array_map('intval', $entry['gene_set_ids'] ?: [0]));

            $parts[] =
                "SELECT ? AS organism,
                        f.feature_uniquename,
                        f.feature_type,
                        g.genome_accession,
                        gs.gene_set_name
                 FROM   {$alias}.feature   f
                 JOIN   {$alias}.gene_set  gs ON f.gene_set_id = gs.gene_set_id
                 JOIN   {$alias}.genome    g  ON gs.genome_id  = g.genome_id
                 WHERE  f.feature_uniquename = ?
                 AND    f.feature_type IN ($type_ph)
                 AND    f.gene_set_id  IN ($gs_ids)";

            array_push($params, $entry['organism'], $q, ...$feature_types);
        }

        $stmt = $pdo->prepare(implode(' UNION ALL ', $parts));
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'uniquename' => $row['feature_uniquename'],
                'type'       => $row['feature_type'],
                'organism'   => $row['organism'],
                'assembly'   => $row['genome_accession'],
                'gene_set'   => $row['gene_set_name'],
                'url'        => "/$site/tools/parent.php"
                              . '?organism='  . urlencode($row['organism'])
                              . '&uniquename=' . urlencode($row['feature_uniquename'])
                              . '&assembly='  . urlencode($row['genome_accession'])
                              . '&gene_set='  . urlencode($row['gene_set_name']),
            ];
        }

        $pdo = null;

    } catch (Exception $e) {
        error_log('feature_search batch error: ' . $e->getMessage());
    }
}

echo json_encode(['results' => $results]);
