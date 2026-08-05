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
$accessible = flattenSourcesList(getAccessibleGeneSets());

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

/**
 * Run the cross-organism ID lookup for one needle, in one match mode.
 *
 * $mode is 'exact' (feature_uniquename = ?) or 'version' (GLOB 'needle.*', i.e. the same
 * ID carrying any version suffix).
 *
 * ⚠️ GLOB, deliberately, NOT LIKE. Both express "starts with"; only GLOB keeps the index.
 * SQLite's LIKE is case-insensitive by default, so it cannot use the BINARY-collated
 * feature_uniquename_idx and degrades to a full covering-index SCAN. Measured on
 * Nematostella:
 *     = 'NV2t021704001.1'      SEARCH ... (feature_uniquename=?)      0.00s
 *     GLOB 'NV2t021704001.*'   SEARCH ... (uniquename>? AND <?)       0.00s
 *     LIKE 'NV2t021704001.%'   SCAN  ...                              0.01s
 * That gap is small for one database and is the whole cost model for this endpoint, which
 * UNION ALLs across EVERY accessible database: a seek stays a seek 85 times over, a scan
 * becomes 85 full index scans. Do not "simplify" this to LIKE.
 */
function moop_feature_id_lookup(array $batches, string $needle, string $mode, array $feature_types, string $site): array {
    $results = [];

    foreach ($batches as $batch) {
    try {
        // In-memory coordinator DB for batched cross-organism ATTACH — still routed
        // through the one door so it gets strict error mode consistently.
        $pdo = getDbConnection(':memory:');

        $parts  = [];
        $params = [];

        // Both branches bind a single placeholder, so the parameter list is identical.
        $match_sql = ($mode === 'version') ? 'GLOB ?' : '= ?';
        $match_val = ($mode === 'version') ? $needle . '.*' : $needle;

        foreach ($batch as $i => $entry) {
            $alias = "db$i";
            // Attach READ-ONLY via a file: URI so the attached organism DB opens O_RDONLY
            // (no O_RDWR write-open → no SELinux { write } denial once organisms/ is
            // read-only). Enabled by the URI flag on the :memory: coordinator (see
            // getDbConnection). Organism paths are controlled (no spaces/URI-special chars).
            $ro_uri = 'file:' . $entry['path'] . '?mode=ro';
            $pdo->exec('ATTACH DATABASE ' . $pdo->quote($ro_uri) . " AS $alias");

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
                 WHERE  f.feature_uniquename $match_sql
                 AND    f.feature_type IN ($type_ph)
                 AND    f.gene_set_id  IN ($gs_ids)";

            array_push($params, $entry['organism'], $match_val, ...$feature_types);
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

    return $results;
}

// Version-tolerant ladder. Each rung runs ONLY if the one above found nothing, so the
// common case — a user pasting the exact ID that MOOP itself displayed — is still a single
// index seek per database and costs exactly what it did before.
//
// The rungs exist because MOOP shows versioned accessions (NV2t021704001.1) while users
// paste IDs from papers, spreadsheets and older releases, where the version is routinely
// absent or stale. Before this, "NV2t021704001" returned NOTHING from the index search box
// even though the feature is right there — an exact-match endpoint reporting a real gene as
// missing, which reads as "the site does not have my gene".
//
// Order matters: exact-as-typed always wins, so an ID that genuinely exists unversioned is
// never shadowed by a versioned near-match.
$results = moop_feature_id_lookup($batches, $q, 'exact', $feature_types, $site);

if (empty($results)) {
    // Typed unversioned, stored versioned: NV2t021704001 -> NV2t021704001.1
    $results = moop_feature_id_lookup($batches, $q, 'version', $feature_types, $site);
}

if (empty($results) && preg_match('/^(.+)\.\d+$/', $q, $m)) {
    // Typed versioned, stored unversioned or stored at a DIFFERENT version:
    // NV2t021704001.1 -> NV2t021704001, then -> NV2t021704001.<any>
    $base    = $m[1];
    $results = moop_feature_id_lookup($batches, $base, 'exact', $feature_types, $site);

    if (empty($results)) {
        $results = moop_feature_id_lookup($batches, $base, 'version', $feature_types, $site);
    }
}

echo json_encode(['results' => $results]);
