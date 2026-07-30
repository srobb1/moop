<?php
/**
 * Gene-set identity cache — genome/gene_set ids and names without opening every database.
 *
 * ACCESS never needed SQLite. has_gene_set_access() decides it from
 * metadata/organism_assembly_groups.json plus users.json, and nothing else. What
 * getAccessibleGeneSets() opened 85 SQLite databases for was IDENTITY:
 *
 *   genome_name / genome_accession   display name + accession; the groups JSON carries
 *                                    only one 'assembly' string, not the pair
 *   genome_id / gene_set_id          used by the four callers that go on to query
 *                                    features (parent.php, download_annotations.php,
 *                                    moopmart_export.php, feature_search.php)
 *
 * Measured on this host with the page cache evicted and the eviction VERIFIED via
 * mincore (notes/bench/cache.py — an unverified evict() measures a cache hit and calls
 * it disk latency):
 *
 *     access decision, JSON + directory tests            1.7 ms
 *     identity via validateAssemblyDirectories()       ~240 ms   <- what this replaces
 *     get_organism_hierarchy.php over HTTP, cold         247 ms
 *     ... same request, second call in the session         3 ms   (session cache)
 *
 * So this is NOT a latency fix — getAccessibleGeneSets() is session-cached, and every
 * tool page calls it during render, so a user pays the cold cost once and the scope
 * modal that follows is already warm. The reason to remove the databases from this path
 * is CONTENTION. One sample of the same cold measurement, taken while the disk was busy,
 * came back at 3,887 ms — 16x — because it is 85 seeks against the rotational volume
 * that is already the bottleneck for search (see notes/QUERY_PERFORMANCE.md). Those 85
 * opens exist to read two tiny tables: 92 gene_set rows across the entire deployment.
 *
 * The facts are therefore cached in one small JSON file under cache_path, fingerprinted
 * PER ORGANISM on organism.sqlite's mtime+size — so a pipeline reload of one organism
 * re-reads one database and leaves the other 84 untouched.
 *
 * It is a cache in the strict sense: unreadable or unwritable means callers fall back to
 * reading the databases and are merely slow, never wrong. Deleting the file is safe.
 */

require_once __DIR__ . '/cache_paths.php';
require_once __DIR__ . '/functions_database.php';   // getDbConnection()
require_once __DIR__ . '/organism_cache.php';       // organism_cache_write_atomic()

// Bump when the stored structure changes, to invalidate every existing entry.
define('GENE_SET_IDENTITY_SCHEMA_VERSION', 1);

/**
 * Path to the identity cache, or '' if the cache directory cannot be created.
 */
function moop_gene_set_identity_file(): string
{
    $root = moop_cache_root();
    return moop_ensure_cache_dir($root) ? "$root/.gene_set_identity.json" : '';
}

/**
 * Change fingerprint for one organism's database: mtime + size.
 *
 * A reload rewrites the file, so both move. 'missing' and 'unreadable' are cached as
 * fingerprints in their own right, so a genuinely absent database does not re-stat and
 * re-fail on every request.
 */
function moop_gene_set_identity_fingerprint(string $db_path): string
{
    $st = @stat($db_path);
    if ($st === false) return 'missing';
    return $st['mtime'] . ':' . $st['size'];
}

/**
 * Read one organism's identity rows straight from its database.
 *
 * Reads exactly the two queries validateAssemblyDirectories() ran, and keeps its
 * tolerance for a missing gene_set table in older databases. Any failure yields empty
 * lists, which resolves to null ids — the same answer callers already got when
 * getGeneSetInfo() failed.
 */
function moop_gene_set_identity_read_db(string $db_path): array
{
    $entry = ['genomes' => [], 'gene_sets' => []];
    if (!is_file($db_path) || !is_readable($db_path)) return $entry;

    try {
        $dbh = getDbConnection($db_path);
        $entry['genomes'] = $dbh->query(
            "SELECT genome_id, genome_name, genome_accession FROM genome ORDER BY genome_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        try {
            $entry['gene_sets'] = $dbh->query(
                "SELECT gene_set_id, genome_id, gene_set_name FROM gene_set ORDER BY gene_set_name"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // gene_set table may not exist in older DBs — treat as no gene sets
        }
    } catch (Exception $e) {
        error_log('MOOP gene-set identity: could not read ' . $db_path . ' — ' . $e->getMessage());
    }

    return $entry;
}

/**
 * Identity rows for a set of organisms.
 *
 * Served from cache for every organism whose database has not changed; only the changed
 * ones are opened. Writes the cache back once, at the end, if anything moved.
 *
 * @param  array  $organisms      Organism directory names
 * @param  string $organism_data  Root of the organism data tree
 * @return array  organism => ['genomes' => [...], 'gene_sets' => [...], 'fp' => string]
 */
function moop_gene_set_identity(array $organisms, string $organism_data): array
{
    $organisms = array_values(array_unique($organisms));
    if (empty($organisms)) return [];

    $cache_file = moop_gene_set_identity_file();
    $stored     = [];
    if ($cache_file !== '' && is_file($cache_file)) {
        $raw = json_decode((string)@file_get_contents($cache_file), true);
        if (is_array($raw) && ($raw['schema_version'] ?? 0) === GENE_SET_IDENTITY_SCHEMA_VERSION) {
            $stored = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        }
    }

    $out   = [];
    $dirty = false;
    foreach ($organisms as $org) {
        $fp = moop_gene_set_identity_fingerprint("$organism_data/$org/organism.sqlite");
        if (isset($stored[$org]) && ($stored[$org]['fp'] ?? null) === $fp) {
            $out[$org] = $stored[$org];
            continue;
        }
        $entry       = moop_gene_set_identity_read_db("$organism_data/$org/organism.sqlite");
        $entry['fp'] = $fp;
        $out[$org]   = $entry;
        $stored[$org] = $entry;
        $dirty = true;
    }

    if ($dirty && $cache_file !== '') {
        // Drop organisms that no longer exist on disk, so a long-lived deployment's cache
        // does not accumulate entries for removed organisms forever.
        foreach (array_keys($stored) as $org) {
            if (!is_dir("$organism_data/$org")) unset($stored[$org]);
        }
        organism_cache_write_atomic($cache_file, [
            'schema_version' => GENE_SET_IDENTITY_SCHEMA_VERSION,
            'generated'      => date('c'),
            'data'           => $stored,
        ]);
    }

    return $out;
}

/**
 * Resolve one (assembly, gene_set) pair against an organism's identity rows.
 *
 * Reproduces exactly what getAccessibleGeneSets() previously obtained from
 * validateAssemblyDirectories() + getGeneSetInfo():
 *
 *  - the genome row is the one whose genome_name OR genome_accession equals the assembly
 *    named in organism_assembly_groups.json;
 *  - the assembly DIRECTORY is genome_name if that is a directory, else genome_accession
 *    if that is, else the name from the JSON (validateAssemblyDirectories' 'directory_found',
 *    which fell back via `?? $assembly`);
 *  - gene_set_id is the gene_set row with that name under a matching genome — the same
 *    condition as getGeneSetInfo()'s JOIN.
 *
 * TYPES ARE DELIBERATE. genome_id stays a STRING because it came from PDO, which returns
 * strings on this deployment (PHP 8.0), and resolveSourceSelection() compares it with ===.
 * gene_set_id is cast to int because getGeneSetInfo() cast it. Changing either would
 * silently alter comparisons rather than break loudly.
 *
 * @param  array  $identity      One organism's entry from moop_gene_set_identity()
 * @param  string $organism_dir  {organism_data}/{organism}
 * @return array  genome_id, genome_name, genome_accession, assembly_dir, gene_set_id
 */
function moop_resolve_gene_set_identity(array $identity, string $organism_dir, string $assembly, string $gene_set): array
{
    $found = [
        'genome_id'        => null,
        'genome_name'      => null,
        'genome_accession' => null,
        'assembly_dir'     => $assembly,
        'gene_set_id'      => null,
    ];

    $matching_genome_ids = [];
    foreach ($identity['genomes'] ?? [] as $genome) {
        $name = $genome['genome_name'] ?? null;
        $acc  = $genome['genome_accession'] ?? null;
        if ($name !== $assembly && $acc !== $assembly) continue;

        $matching_genome_ids[] = (string)($genome['genome_id'] ?? '');

        if ($found['genome_id'] === null) {
            $found['genome_id']        = $genome['genome_id'] ?? null;
            $found['genome_name']      = $name;
            $found['genome_accession'] = $acc;
            if     ((string)$name !== '' && is_dir("$organism_dir/$name")) $found['assembly_dir'] = $name;
            elseif ((string)$acc  !== '' && is_dir("$organism_dir/$acc"))  $found['assembly_dir'] = $acc;
        }
    }

    if (!empty($matching_genome_ids)) {
        foreach ($identity['gene_sets'] ?? [] as $gs) {
            if (($gs['gene_set_name'] ?? null) !== $gene_set) continue;
            if (!in_array((string)($gs['genome_id'] ?? ''), $matching_genome_ids, true)) continue;
            $found['gene_set_id'] = (int)$gs['gene_set_id'];
            break;
        }
    }

    return $found;
}
