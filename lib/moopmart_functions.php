<?php
/**
 * MOOPmart — MOOP Mega Search Query and Export Functions
 *
 * Provides the full query and streaming export layer for MOOPmart:
 *   - Feature queries with annotation-based filters (DB)
 *   - Coordinate attachment and range filtering (feature_coords.tsv)
 *   - TSV/CSV annotation export helpers
 *   - Genomic FASTA streaming: whole gene, upstream, downstream, per-exon
 *
 * Depends on (caller must include before this file):
 *   lib/functions_database.php  — fetchData()
 *   lib/blast_functions.php     — loadFeatureCoords(), extractFastaRegion(), reverseComplement()
 */

/** Max rows a preview endpoint materialises and returns. Counts stay exact regardless. */
const MOOPMART_PREVIEW_ROW_CAP = 100;

// ============================================================
// ID RESOLUTION
// ============================================================

/**
 * Resolve raw input IDs (gene, mRNA, protein, CDS, etc.) to gene-level uniquenames
 * with a provenance string explaining why each gene was included.
 *
 * Uses getAncestors() to walk the full hierarchy regardless of depth, so
 * protein→CDS→mRNA→gene (3 levels) works just as well as mRNA→gene (1 level).
 * Multiple input IDs that resolve to the same gene accumulate their reasons.
 *
 * Requires parent_functions.php (included transitively via blast_functions.php).
 *
 * @param string[] $input_ids    Raw IDs from user input
 * @param string   $db_path      Path to organism.sqlite
 * @param int[]    $gene_set_ids Accessible gene_set_ids for this organism
 * @return array   [gene_uniquename => reason_string]
 */
function moopmartResolveInputIds(array $input_ids, string $db_path, array $gene_set_ids): array
{
    if (empty($input_ids) || empty($gene_set_ids)) return [];

    $by_gene = [];

    // Single batch CTE: walk UP the tree from every input ID simultaneously.
    // One query resolves all IDs regardless of depth (gene/mRNA/protein/CDS).
    $ph_ids = implode(',', array_fill(0, count($input_ids), '?'));
    $ph_gs  = implode(',', array_fill(0, count($gene_set_ids), '?'));

    $query = "WITH RECURSIVE chain AS (
        SELECT f.feature_uniquename AS input_name,
               f.feature_type       AS input_type,
               f.feature_id,
               f.feature_uniquename AS node_name,
               f.parent_feature_id
        FROM   feature f
        WHERE  f.feature_uniquename IN ($ph_ids)
          AND  f.gene_set_id IN ($ph_gs)
        UNION ALL
        SELECT c.input_name,
               c.input_type,
               f.feature_id,
               f.feature_uniquename,
               f.parent_feature_id
        FROM   feature f
        JOIN   chain c ON f.feature_id = c.parent_feature_id
    )
    SELECT input_name, input_type, node_name AS gene_uniquename
    FROM   chain
    WHERE  parent_feature_id IS NULL";

    $rows = fetchData($query, $db_path, array_merge($input_ids, $gene_set_ids));

    foreach ($rows as $row) {
        $reason = ($row['input_name'] === $row['gene_uniquename'])
            ? "Gene ID: {$row['input_name']}"
            : moopmartFeatureTypeLabel($row['input_type']) . ": {$row['input_name']}";
        $by_gene[$row['gene_uniquename']][] = $reason;
    }

    $result = [];
    foreach ($by_gene as $gene => $reasons) {
        $result[$gene] = implode('; ', array_unique($reasons));
    }
    return $result;
}

/**
 * Human-readable label for a feature type, used in "Why included" reason strings.
 */
function moopmartFeatureTypeLabel(string $type): string
{
    return match (strtolower($type)) {
        'mrna', 'transcript'    => 'mRNA ID',
        'cds'                   => 'CDS ID',
        'polypeptide', 'protein' => 'Protein ID',
        default                 => ucfirst($type) . ' ID',
    };
}

/**
 * Build a uniform "why included" reason string from the non-ID active filters.
 * Applied to every row when no per-row ID resolution is available.
 *
 * @param array $filters      The $filters array passed to moopmartQueryFeatures()
 * @param array $coord_filter ['chr'=>'', 'start'=>0, 'end'=>0]
 * @return string
 */
function buildMoopmartFilterReason(array $filters, array $coord_filter): string
{
    $parts = [];
    if (!empty($filters['gene_name']))        $parts[] = 'Name: ' . $filters['gene_name'];
    if (!empty($filters['gene_description'])) $parts[] = 'Description: ' . $filters['gene_description'];
    foreach ($filters['annotation_criteria'] ?? [] as $crit) {
        $ann = [];
        if (!empty($crit['src'])) $ann[] = $crit['src'];
        if (!empty($crit['acc'])) $ann[] = $crit['acc'];
        if (!empty($crit['kw']))  $ann[] = 'keyword: "' . $crit['kw'] . '"';
        if ($ann) $parts[] = 'Annotation: ' . implode(', ', $ann);
    }
    if (!empty($coord_filter['chr'])) {
        $loc = 'Location: ' . $coord_filter['chr'];
        if (!empty($coord_filter['start'])) $loc .= ':' . number_format((int)$coord_filter['start']);
        if (!empty($coord_filter['end']))   $loc .= '–' . number_format((int)$coord_filter['end']);
        $parts[] = $loc;
    }
    return implode(' + ', $parts);
}

// ============================================================
// MRNA / PROTEIN CHILD ID EXPANSION
// ============================================================

/**
 * Feature types treated as transcript children of a gene.
 * moopmartGetChildIds() and moopmartCountChildRows() must agree on this list, or a
 * preview's reported row count will not match the rows it actually renders.
 *
 * @return string[]
 */
function moopmartMrnaTypes(): array
{
    return ['mrna', 'transcript', 'lnc_rna', 'ncrna', 'pre_mirna', 'rrna', 'trna',
            'pseudogenic_transcript', 'processed_transcript'];
}

/**
 * Count transcript children per gene without materialising them.
 *
 * Lets a caller total up the expanded row count for a whole organism while only ever
 * holding one int per gene, instead of one copy of the gene row per transcript.
 *
 * @param int[]  $gene_feature_ids
 * @param string $db_path
 * @return array [gene_feature_id => child_count]  (genes with no children are absent)
 */
function moopmartCountChildRows(array $gene_feature_ids, string $db_path): array
{
    if (empty($gene_feature_ids)) return [];

    $mrna_types = moopmartMrnaTypes();
    $type_ph    = implode(',', array_fill(0, count($mrna_types), '?'));

    $counts = [];
    foreach (array_chunk($gene_feature_ids, 500) as $chunk) {
        $ph    = implode(',', array_fill(0, count($chunk), '?'));
        $query = "SELECT parent_feature_id AS gene_fid, COUNT(*) AS n
                  FROM   feature
                  WHERE  parent_feature_id IN ($ph)
                    AND  LOWER(feature_type) IN ($type_ph)
                  GROUP BY parent_feature_id";

        foreach (fetchData($query, $db_path, array_merge($chunk, $mrna_types)) as $row) {
            $counts[(int)$row['gene_fid']] = (int)$row['n'];
        }
    }
    return $counts;
}

/**
 * Fetch mRNA and protein (polypeptide) IDs for a list of gene feature_ids.
 * Returns one entry per mRNA child, with the protein ID of its first polypeptide grandchild.
 *
 * @param int[]  $gene_feature_ids
 * @param string $db_path
 * @return array [gene_feature_id => [['mrna_id'=>'...', 'protein_id'=>'...'], ...]]
 */
function moopmartGetChildIds(array $gene_feature_ids, string $db_path): array
{
    if (empty($gene_feature_ids)) return [];

    $mrna_types = moopmartMrnaTypes();
    $type_ph    = implode(',', array_fill(0, count($mrna_types), '?'));

    $result = [];
    foreach (array_chunk($gene_feature_ids, 500) as $chunk) {
        $ph    = implode(',', array_fill(0, count($chunk), '?'));
        // Protein may be a direct child of mRNA OR a grandchild via CDS — handle both.
        // Feature type may be stored as 'protein' or 'polypeptide' depending on the source GFF.
        $query = "SELECT
                      mrna.parent_feature_id  AS gene_fid,
                      mrna.feature_uniquename AS mrna_id,
                      COALESCE(
                          (SELECT prot.feature_uniquename FROM feature prot
                           WHERE  prot.parent_feature_id = mrna.feature_id
                             AND  LOWER(prot.feature_type) IN ('protein','polypeptide')
                           LIMIT  1),
                          (SELECT prot.feature_uniquename FROM feature cds
                           JOIN   feature prot ON prot.parent_feature_id = cds.feature_id
                                              AND LOWER(prot.feature_type) IN ('protein','polypeptide')
                           WHERE  cds.parent_feature_id = mrna.feature_id
                           LIMIT  1)
                      ) AS protein_id
                  FROM  feature mrna
                  WHERE mrna.parent_feature_id IN ($ph)
                    AND LOWER(mrna.feature_type) IN ($type_ph)
                  ORDER BY mrna.parent_feature_id, mrna.feature_uniquename";

        foreach (fetchData($query, $db_path, array_merge($chunk, $mrna_types)) as $row) {
            $result[(int)$row['gene_fid']][] = [
                'mrna_id'    => $row['mrna_id'],
                'protein_id' => $row['protein_id'] ?? '',
            ];
        }
    }
    return $result;
}

/**
 * Expand gene-level feature rows to one row per mRNA child.
 * Each expanded row inherits the gene row's data and adds mrna_id and protein_id.
 * Genes with no mRNA children produce one row with empty mrna_id/protein_id.
 *
 * @param array  $gene_features  Rows from moopmartAttachCoords() with db_path set
 * @param string $db_path        Path to this organism's organism.sqlite
 * @return array
 */
function moopmartExpandToMrnaRows(array $gene_features, string $db_path): array
{
    if (empty($gene_features)) return [];

    $child_ids = moopmartGetChildIds(array_column($gene_features, 'feature_id'), $db_path);

    $expanded = [];
    foreach ($gene_features as $f) {
        $children = $child_ids[$f['feature_id']] ?? null;
        if ($children) {
            foreach ($children as $child) {
                $row               = $f;
                $row['mrna_id']    = $child['mrna_id'];
                $row['protein_id'] = $child['protein_id'];
                $expanded[]        = $row;
            }
        } else {
            $f['mrna_id']    = '';
            $f['protein_id'] = '';
            $expanded[]      = $f;
        }
    }
    return $expanded;
}

// ============================================================
// SEARCH TERM PARSING
// ============================================================

/**
 * Parse a free-text search term using the same rules as annotation search:
 *   - Quoted ("exact phrase") → single LIKE '%phrase%' condition
 *   - Multi-word              → one LIKE condition per token, ANDed by caller
 *   - Tokens < 3 chars        → ignored (same as annotation search minimum)
 *   - Returns null            → no usable tokens; caller should skip the filter
 *
 * @param string $raw    Raw input from the user
 * @param string $column SQL column expression to match against (e.g. 'f.feature_name')
 * @return array|null ['conditions'=>[...], 'params'=>[...]] or null if nothing to filter on
 */
function moopmartBuildTextConditions(string $raw, string $column): ?array
{
    $raw = trim($raw);
    if ($raw === '') return null;

    // Quoted search — strip quotes, treat whole string as one exact phrase
    if (strlen($raw) >= 2 &&
        (($raw[0] === '"' && $raw[-1] === '"') || ($raw[0] === "'" && $raw[-1] === "'"))) {
        $term = trim(substr($raw, 1, -1));
        if ($term === '') return null;
        return ['conditions' => ["$column LIKE ?"], 'params' => ["%$term%"]];
    }

    // Multi-word: split on whitespace, drop tokens shorter than 3 chars
    $tokens = array_values(array_filter(
        preg_split('/\s+/', $raw),
        fn($t) => strlen($t) >= 3
    ));
    if (empty($tokens)) return null;

    $conditions = [];
    $params     = [];
    foreach ($tokens as $token) {
        $conditions[] = "$column LIKE ?";
        $params[]     = '%' . $token . '%';
    }
    return ['conditions' => $conditions, 'params' => $params];
}

// ============================================================
// DB QUERY FUNCTIONS
// ============================================================

/**
 * Query gene-level features for MOOPmart, applying annotation and type filters.
 *
 * Coordinates are NOT in the DB — call moopmartAttachCoords() afterwards to
 * join with feature_coords.tsv and apply any coordinate-range filter.
 *
 * @param int[]  $gene_set_ids  Accessible gene_set_ids — MUST come from access control
 * @param string $db_path       Path to organism.sqlite
 * @param array  $filters {
 *   feature_types?:         string[]  e.g. ['gene','pseudogene']; empty = all types
 *   feature_id?:            string    Exact match on feature_uniquename
 *   gene_name?:             string    LIKE match on feature_name
 *   gene_description?:      string    LIKE match on feature_description
 *   annotation_criteria?:   array     Each element: ['src'=>'', 'acc'=>'', 'kw'=>'']
 *                                     Each criterion generates its own EXISTS clause (AND between them).
 *                                     Empty src/acc/kw fields within a criterion are skipped.
 * }
 * @return array  Rows: feature_id, uniquename, name, description, type,
 *                      gene_set_id, gene_set_name, genome_accession, genome_name, organism_name
 */
function moopmartQueryFeatures(array $gene_set_ids, string $db_path, array $filters = []): array
{
    if (empty($gene_set_ids)) return [];

    $params       = [];
    $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
    array_push($params, ...$gene_set_ids);

    $where = ["f.gene_set_id IN ($placeholders)"];

    // Default to gene-level features so output is one row per gene.
    // Callers may override by passing feature_types explicitly.
    $types = !empty($filters['feature_types'])
        ? array_values($filters['feature_types'])
        : ['gene', 'pseudogene'];
    $tp    = implode(',', array_fill(0, count($types), '?'));
    $where[] = "f.feature_type IN ($tp)";
    array_push($params, ...$types);

    // Feature-level filters (applied directly on gene rows)
    // feature_ids contains already-resolved gene uniquenames from moopmartResolveInputIds()
    if (!empty($filters['feature_ids'])) {
        $ids = $filters['feature_ids'];
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "f.feature_uniquename IN ($ph)";
        array_push($params, ...$ids);
    }
    if (!empty($filters['gene_name'])) {
        $parsed = moopmartBuildTextConditions($filters['gene_name'], 'f.feature_name');
        if ($parsed) { foreach ($parsed['conditions'] as $c) $where[] = $c; array_push($params, ...$parsed['params']); }
    }
    if (!empty($filters['gene_description'])) {
        $parsed = moopmartBuildTextConditions($filters['gene_description'], 'f.feature_description');
        if ($parsed) { foreach ($parsed['conditions'] as $c) $where[] = $c; array_push($params, ...$parsed['params']); }
    }

    // Annotation criteria — each entry generates its own EXISTS clause (AND between them).
    // Annotations live on mRNA/transcript children, not directly on gene features.
    foreach ($filters['annotation_criteria'] ?? [] as $criterion) {
        $crit_where  = [];
        $crit_params = [];
        if (!empty($criterion['src'])) {
            $crit_where[]  = 'ans.annotation_source_name = ?';
            $crit_params[] = $criterion['src'];
        }
        if (!empty($criterion['acc'])) {
            $crit_where[]  = 'a.annotation_accession = ?';
            $crit_params[] = $criterion['acc'];
        }
        if (!empty($criterion['kw'])) {
            $parsed = moopmartBuildTextConditions($criterion['kw'], 'a.annotation_description');
            if ($parsed) { foreach ($parsed['conditions'] as $c) $crit_where[] = $c; array_push($crit_params, ...$parsed['params']); }
        }
        if (!empty($crit_where)) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM feature           child
                JOIN feature_annotation fa2 ON fa2.feature_id         = child.feature_id
                JOIN annotation         a   ON fa2.annotation_id      = a.annotation_id
                JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
                WHERE child.parent_feature_id = f.feature_id
                  AND ' . implode(' AND ', $crit_where) . '
            )';
            array_push($params, ...$crit_params);
        }
    }

    $where_sql = implode(' AND ', $where);

    $query = "SELECT DISTINCT
                  f.feature_id,
                  f.feature_uniquename  AS uniquename,
                  f.feature_name        AS name,
                  f.feature_description AS description,
                  f.feature_type        AS type,
                  f.gene_set_id,
                  gs.gene_set_name,
                  g.genome_accession,
                  g.genome_name,
                  o.genus || ' ' || o.species AS organism_name
              FROM feature f
              JOIN gene_set gs ON f.gene_set_id  = gs.gene_set_id
              JOIN genome   g  ON gs.genome_id   = g.genome_id
              JOIN organism o  ON g.organism_id  = o.organism_id
              WHERE $where_sql
              ORDER BY f.feature_uniquename";

    return fetchData($query, $db_path, $params);
}

// ============================================================
// ATTACH-BASED BATCH QUERIES (multi-organism, shared connection)
// ============================================================

/**
 * Build the SQL + params for one organism's feature query, with all table
 * references prefixed by $pfx (e.g. "db3.") for use with ATTACH.
 * Returns ['sql' => string, 'params' => array], or ['sql'=>'','params'=>[]] if nothing to query.
 */
function moopmartBuildFeatureQueryPart(array $gene_set_ids, array $filters, string $organism, string $pfx): array
{
    $params       = [];
    $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
    array_push($params, ...$gene_set_ids);

    $types = !empty($filters['feature_types']) ? array_values($filters['feature_types']) : ['gene', 'pseudogene'];
    $tp    = implode(',', array_fill(0, count($types), '?'));
    $where = ["f.gene_set_id IN ($placeholders)", "f.feature_type IN ($tp)"];
    array_push($params, ...$types);

    if (!empty($filters['feature_ids'])) {
        $ids = $filters['feature_ids'][$organism] ?? [];
        if (empty($ids)) return ['sql' => '', 'params' => []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "f.feature_uniquename IN ($ph)";
        array_push($params, ...$ids);
    }
    if (!empty($filters['gene_name'])) {
        $parsed = moopmartBuildTextConditions($filters['gene_name'], 'f.feature_name');
        if ($parsed) { foreach ($parsed['conditions'] as $c) $where[] = $c; array_push($params, ...$parsed['params']); }
    }
    if (!empty($filters['gene_description'])) {
        $parsed = moopmartBuildTextConditions($filters['gene_description'], 'f.feature_description');
        if ($parsed) { foreach ($parsed['conditions'] as $c) $where[] = $c; array_push($params, ...$parsed['params']); }
    }
    foreach ($filters['annotation_criteria'] ?? [] as $criterion) {
        $crit_where = []; $crit_params = [];
        if (!empty($criterion['src'])) { $crit_where[] = 'ans.annotation_source_name = ?'; $crit_params[] = $criterion['src']; }
        if (!empty($criterion['acc'])) { $crit_where[] = 'a.annotation_accession = ?';      $crit_params[] = $criterion['acc']; }
        if (!empty($criterion['kw'])) {
            $parsed = moopmartBuildTextConditions($criterion['kw'], 'a.annotation_description');
            if ($parsed) { foreach ($parsed['conditions'] as $c) $crit_where[] = $c; array_push($crit_params, ...$parsed['params']); }
        }
        if (!empty($crit_where)) {
            $where[] = "EXISTS (SELECT 1 FROM {$pfx}feature child
                        JOIN {$pfx}feature_annotation fa2 ON fa2.feature_id = child.feature_id
                        JOIN {$pfx}annotation a ON fa2.annotation_id = a.annotation_id
                        JOIN {$pfx}annotation_source ans ON a.annotation_source_id = ans.annotation_source_id
                        WHERE child.parent_feature_id = f.feature_id AND " . implode(' AND ', $crit_where) . ')';
            array_push($params, ...$crit_params);
        }
    }

    $where_sql = implode(' AND ', $where);
    $sql = "SELECT DISTINCT f.feature_id, f.feature_uniquename AS uniquename,
                f.feature_name AS name, f.feature_description AS description,
                f.feature_type AS type, f.gene_set_id,
                gs.gene_set_name, g.genome_accession, g.genome_name,
                o.genus || ' ' || o.species AS organism_name,
                ? AS organism_dir
            FROM {$pfx}feature f
            JOIN {$pfx}gene_set gs ON f.gene_set_id = gs.gene_set_id
            JOIN {$pfx}genome   g  ON gs.genome_id  = g.genome_id
            JOIN {$pfx}organism o  ON g.organism_id = o.organism_id
            WHERE $where_sql";
    array_unshift($params, $organism);

    return ['sql' => $sql, 'params' => $params];
}

/**
 * Count features matching moopmartQueryFeatures filters (lightweight preview).
 */
function moopmartCountFeatures(array $gene_set_ids, string $db_path, array $filters = []): int
{
    if (empty($gene_set_ids)) return 0;

    $params       = [];
    $placeholders = implode(',', array_fill(0, count($gene_set_ids), '?'));
    array_push($params, ...$gene_set_ids);

    $where = ["f.gene_set_id IN ($placeholders)"];

    $types = !empty($filters['feature_types'])
        ? array_values($filters['feature_types'])
        : ['gene', 'pseudogene'];
    $tp    = implode(',', array_fill(0, count($types), '?'));
    $where[] = "f.feature_type IN ($tp)";
    array_push($params, ...$types);

    if (!empty($filters['feature_ids'])) {
        $ids = $filters['feature_ids'];
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "f.feature_uniquename IN ($ph)";
        array_push($params, ...$ids);
    }
    if (!empty($filters['gene_name'])) {
        $parsed = moopmartBuildTextConditions($filters['gene_name'], 'f.feature_name');
        if ($parsed) { foreach ($parsed['conditions'] as $c) $where[] = $c; array_push($params, ...$parsed['params']); }
    }
    if (!empty($filters['gene_description'])) {
        $parsed = moopmartBuildTextConditions($filters['gene_description'], 'f.feature_description');
        if ($parsed) { foreach ($parsed['conditions'] as $c) $where[] = $c; array_push($params, ...$parsed['params']); }
    }

    foreach ($filters['annotation_criteria'] ?? [] as $criterion) {
        $crit_where  = [];
        $crit_params = [];
        if (!empty($criterion['src'])) {
            $crit_where[]  = 'ans.annotation_source_name = ?';
            $crit_params[] = $criterion['src'];
        }
        if (!empty($criterion['acc'])) {
            $crit_where[]  = 'a.annotation_accession = ?';
            $crit_params[] = $criterion['acc'];
        }
        if (!empty($criterion['kw'])) {
            $parsed = moopmartBuildTextConditions($criterion['kw'], 'a.annotation_description');
            if ($parsed) { foreach ($parsed['conditions'] as $c) $crit_where[] = $c; array_push($crit_params, ...$parsed['params']); }
        }
        if (!empty($crit_where)) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM feature           child
                JOIN feature_annotation fa2 ON fa2.feature_id         = child.feature_id
                JOIN annotation         a   ON fa2.annotation_id      = a.annotation_id
                JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
                WHERE child.parent_feature_id = f.feature_id
                  AND ' . implode(' AND ', $crit_where) . '
            )';
            array_push($params, ...$crit_params);
        }
    }

    $where_sql = implode(' AND ', $where);

    $query = "SELECT COUNT(DISTINCT f.feature_id) AS cnt
              FROM feature f
              JOIN gene_set gs ON f.gene_set_id  = gs.gene_set_id
              JOIN genome   g  ON gs.genome_id   = g.genome_id
              JOIN organism o  ON g.organism_id  = o.organism_id
              WHERE $where_sql";

    $rows = fetchData($query, $db_path, $params);
    return (int)($rows[0]['cnt'] ?? 0);
}

/**
 * Fetch annotations for a list of feature_ids, grouped by feature then source.
 *
 * Chunked in batches of 500 to avoid oversized IN clauses on large result sets.
 *
 * @param int[]  $feature_ids
 * @param string $db_path
 * @return array [feature_id => [source_name => [{accession, description}, ...], ...], ...]
 */
function moopmartGetAnnotationsForFeatures(array $feature_ids, string $db_path): array
{
    if (empty($feature_ids)) return [];

    // Annotations live on mRNA/transcript children, not on gene features directly.
    // Join through parent_feature_id so gene feature_ids resolve to their children's
    // annotations. Result is keyed by the gene (parent) feature_id.
    $result = [];
    foreach (array_chunk($feature_ids, 500) as $chunk) {
        $ph    = implode(',', array_fill(0, count($chunk), '?'));
        $query = "SELECT child.parent_feature_id   AS gene_feature_id,
                         ans.annotation_source_name,
                         a.annotation_accession,
                         a.annotation_description
                  FROM feature            child
                  JOIN feature_annotation fa  ON fa.feature_id          = child.feature_id
                  JOIN annotation         a   ON fa.annotation_id       = a.annotation_id
                  JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
                  WHERE child.parent_feature_id IN ($ph)
                  ORDER BY child.parent_feature_id, ans.annotation_source_name, a.annotation_accession";

        foreach (fetchData($query, $db_path, $chunk) as $row) {
            $gfid = $row['gene_feature_id'];
            $src  = $row['annotation_source_name'];
            $acc  = $row['annotation_accession'];
            // Deduplicate: the same accession may appear on multiple mRNA isoforms
            $result[$gfid][$src][$acc] = [
                'accession'   => $acc,
                'description' => $row['annotation_description'],
            ];
        }
    }

    // Convert inner associative-by-accession arrays to plain indexed arrays
    foreach ($result as &$by_source) {
        foreach ($by_source as &$entries) {
            $entries = array_values($entries);
        }
    }
    unset($by_source, $entries);

    return $result;
}

/**
 * Return the sorted list of unique chromosome/scaffold names for a gene set.
 *
 * Result is cached beside the TSV (chr_names_cache.json) and invalidated
 * automatically when feature_coords.tsv is newer than the cache.
 */
function moopmartGetChrNames(string $gene_set_path): array
{
    $tsv   = "$gene_set_path/feature_coords.tsv";
    if (!file_exists($tsv)) return [];

    // Source TSV stays in the organism tree; only the cache goes to the cache root.
    $cache_dir = moop_cache_dir_for($gene_set_path);
    $cache     = $cache_dir !== '' ? "$cache_dir/chr_names_cache.json" : '';
    if ($cache !== '' && file_exists($cache) && filemtime($cache) >= filemtime($tsv)) {
        return loadJsonFile($cache, []);
    }

    $chrs = [];
    $fh   = fopen($tsv, 'r');
    if ($fh === false) return [];
    while (($line = fgets($fh)) !== false) {
        $p = explode("\t", $line, 4);
        if (isset($p[2])) $chrs[trim($p[2])] = true;
    }
    fclose($fh);

    $result = array_keys($chrs);
    sort($result, SORT_NATURAL);
    if ($cache !== '') @file_put_contents($cache, json_encode($result));
    return $result;
}

// ============================================================
// COORDINATE FUNCTIONS (coords are in feature_coords.tsv, not the DB)
// ============================================================

/**
 * Load coordinates from feature_coords.tsv for a given set of feature uniquenames.
 *
 * Each row in the TSV maps any feature (gene, mRNA, exon, …) to its gene ancestor's
 * genomic coordinates.  Passing $filter_uniquenames limits the load to exactly the
 * features that were returned by moopmartQueryFeatures(), avoiding the need to slurp
 * the entire (potentially large) TSV into memory.
 *
 * Without a filter the function falls back to loading only gene-level rows
 * (where uniquename === gene_id) for memory efficiency — useful if the full
 * coord set is needed regardless of what the DB query returned.
 *
 * @param string   $gene_set_path       Absolute path to the gene_set directory
 * @param string[] $filter_uniquenames  Only load entries for these uniquenames;
 *                                      empty array = load gene-level rows only
 * @return array [uniquename => ['chr'=>'...','start'=>N,'end'=>N,'strand'=>'+'|'-']]
 */
function moopmartLoadGeneCoords(string $gene_set_path, array $filter_uniquenames = []): array
{
    $tsv = "$gene_set_path/feature_coords.tsv";
    if (!file_exists($tsv)) return [];

    // Build a fast lookup set when a filter is provided.
    $filter = !empty($filter_uniquenames) ? array_flip($filter_uniquenames) : null;

    $coords = [];
    $fh     = fopen($tsv, 'r');
    if ($fh === false) return [];
    while (($line = fgets($fh)) !== false) {
        $p = explode("\t", rtrim($line));
        if (count($p) < 6) continue;
        [$uniquename, $gene_id, $chr, $start, $end, $strand] = $p;

        if ($filter !== null) {
            // Targeted load: match on gene_id (col1) — feature uniquenames are gene-level,
            // but col0 in the TSV is the mRNA/isoform uniquename with a suffix.
            if (!isset($filter[$gene_id])) continue;
            if (isset($coords[$gene_id])) continue; // keep first isoform only
            $coords[$gene_id] = [
                'chr'    => $chr,
                'start'  => (int)$start,
                'end'    => (int)$end,
                'strand' => rtrim($strand),
            ];
        } else {
            // No filter: gene-level rows only (col0 === col1) for memory efficiency.
            if ($uniquename !== $gene_id) continue;
            $coords[$uniquename] = [
                'chr'    => $chr,
                'start'  => (int)$start,
                'end'    => (int)$end,
                'strand' => $strand,
            ];
        }
    }
    fclose($fh);
    return $coords;
}

/**
 * Attach coordinate data to feature rows and optionally filter by genomic region.
 *
 * Features with no coord entry are dropped unless feature_coords.tsv was absent
 * (empty $coords_by_gs_id entry), in which case they are kept with empty coord fields.
 *
 * @param array $features           Output of moopmartQueryFeatures()
 * @param array $coords_by_gs_id    [gene_set_id => [uniquename => [chr,start,end,strand]]]
 *                                  Built by caller using moopmartLoadGeneCoords() per source
 * @param array $coord_filter       Optional ['chr'=>'', 'start'=>0, 'end'=>0]
 * @return array  Feature rows with chr, start, end, strand merged in
 */
function moopmartAttachCoords(array $features, array $coords_by_gs_id, array $coord_filter = []): array
{
    $filter_chr   = $coord_filter['chr']   ?? '';
    $filter_start = (int)($coord_filter['start'] ?? 0);
    $filter_end   = (int)($coord_filter['end']   ?? 0);

    $has_filter = $filter_chr !== '' || $filter_start > 0 || $filter_end > 0;

    $result = [];
    foreach ($features as $f) {
        $coords = $coords_by_gs_id[$f['gene_set_id']] ?? [];
        $entry  = $coords[$f['uniquename']] ?? null;

        if (!$entry) {
            // With a coord filter we can't verify the feature is in range — drop it.
            // Without a coord filter keep the feature; just leave coord fields empty.
            if ($has_filter) continue;
            $result[] = array_merge($f, ['chr' => '', 'start' => '', 'end' => '', 'strand' => '']);
            continue;
        }

        if ($filter_chr !== '' && $entry['chr'] !== $filter_chr) continue;
        if ($filter_start > 0 && $entry['end']   < $filter_start) continue;
        if ($filter_end   > 0 && $entry['start'] > $filter_end)   continue;

        $result[] = array_merge($f, $entry);
    }
    return $result;
}

// ============================================================
// GFF EXON EXTRACTION
// ============================================================

/**
 * Stream through a GFF3 file once and collect sub-feature coordinates for a set of genes.
 *
 * Only the first isoform (first mRNA-type child encountered) per gene is used.
 * Sub-features collected: exon, CDS, five_prime_UTR, three_prime_UTR, UTR.
 * Features assumed to be in parent-before-child order (standard GFF3).
 *
 * @param string   $gff_path         Path to genes.gff
 * @param string[] $gene_uniquenames  Gene IDs to collect sub-features for
 * @return array   [gene_id => [{start, end, type, strand}, ...]] sorted by start ASC
 */
function moopmartGetExonCoords(string $gff_path, array $gene_uniquenames): array
{
    if (!file_exists($gff_path) || empty($gene_uniquenames)) return [];

    $target_genes    = array_flip($gene_uniquenames);
    $gene_first_mrna = [];  // gene_id => mrna_id (first isoform only)
    $mrna_to_gene    = [];  // mrna_id => gene_id
    $sub_features    = [];  // gene_id => [{start, end, type, strand}]

    $mrna_types = ['mrna', 'transcript', 'lnc_rna', 'ncrna', 'pre_mirna', 'rrna', 'trna',
                   'pseudogenic_transcript', 'processed_transcript'];
    $exon_types = ['exon', 'cds', 'five_prime_utr', 'three_prime_utr', 'utr'];

    $fh = fopen($gff_path, 'r');
    if ($fh === false) return [];
    while (($line = fgets($fh)) !== false) {
        if ($line[0] === '#') continue;
        $parts = explode("\t", rtrim($line));
        if (count($parts) < 9) continue;

        $feat_type_raw = $parts[2];
        $feat_type     = strtolower($feat_type_raw);
        $attrs         = $parts[8];

        if (!preg_match('/\bID=([^;]+)/', $attrs, $mid)) continue;
        $id = $mid[1];

        $parent = null;
        if (preg_match('/\bParent=([^;,]+)/', $attrs, $pm)) {
            $parent = $pm[1];
        }

        // Detect first mRNA-type child of each target gene
        if ($parent !== null
            && isset($target_genes[$parent])
            && in_array($feat_type, $mrna_types)
            && !isset($gene_first_mrna[$parent])
        ) {
            $gene_first_mrna[$parent] = $id;
            $mrna_to_gene[$id]        = $parent;
        }

        // Collect exon/CDS/UTR children of the first isoform
        if ($parent !== null
            && isset($mrna_to_gene[$parent])
            && in_array($feat_type, $exon_types)
        ) {
            $gene_id = $mrna_to_gene[$parent];
            $sub_features[$gene_id][] = [
                'start'  => (int)$parts[3],
                'end'    => (int)$parts[4],
                'type'   => $feat_type_raw,
                'strand' => $parts[6],
            ];
        }
    }
    fclose($fh);

    foreach ($sub_features as &$feats) {
        usort($feats, fn($a, $b) => $a['start'] - $b['start']);
    }

    return $sub_features;
}

// ============================================================
// GENOMIC FASTA STREAMING
// ============================================================

/**
 * Stream genomic FASTA sequences for a set of genes to a file handle.
 *
 * Modes:
 *   'gene'       — whole gene body (start to end)
 *   'upstream'   — $flank_bp upstream of the gene start, strand-aware
 *   'downstream' — $flank_bp downstream of the gene end, strand-aware
 *   'exons'      — each exon/CDS/UTR sub-feature as a separate FASTA record
 *
 * Strand logic for upstream/downstream on minus-strand genes:
 *   upstream:   extract [end+1, end+flank_bp], then reverse-complement
 *   downstream: extract [max(1, start-flank_bp), start-1], then reverse-complement
 *
 * @param array    $genes         Rows from moopmartAttachCoords():
 *                                [{uniquename, chr, start, end, strand, genome_accession,
 *                                  gene_set_name, organism_name, ...}]
 * @param string   $assembly_dir  Path to {organism_data}/{organism}/{assembly}/
 *                                genome.fa lives here (not in gene_set subdir)
 * @param string   $gff_path      Path to genes.gff (used for exons mode only)
 * @param string   $mode          'gene' | 'upstream' | 'downstream' | 'exons'
 * @param int      $flank_bp      Flank size in bp (upstream/downstream only; ignored otherwise)
 * @param resource $handle        Writable file handle to stream FASTA output to
 */
function moopmartStreamGenomicFasta(
    array $genes,
    string $assembly_dir,
    string $gff_path,
    string $mode,
    int $flank_bp,
    $handle
): void {
    $genome_file = genome_fasta_filename();
    $fasta = "$assembly_dir/$genome_file";
    $fai   = "$assembly_dir/$genome_file.fai";
    if (!file_exists($fasta) || !file_exists($fai)) return;

    // Pre-load all exon coords in one GFF pass for the exons mode
    $exon_coords = [];
    if ($mode === 'exons') {
        $exon_coords = moopmartGetExonCoords($gff_path, array_column($genes, 'uniquename'));
    }

    foreach ($genes as $g) {
        $uname  = $g['uniquename'];
        $chr    = $g['chr'];
        $gstart = $g['start'];
        $gend   = $g['end'];
        $strand = $g['strand'];

        if ($mode === 'exons') {
            $gene_exons = $exon_coords[$uname] ?? [];
            if (empty($gene_exons)) continue;
            $n = 0;
            foreach ($gene_exons as $exon) {
                $n++;
                $seq = extractFastaRegion($fasta, $fai, $chr, $exon['start'], $exon['end']);
                if ($seq === null) continue;
                if ($strand === '-') $seq = reverseComplement($seq);
                $header = ">$uname.{$exon['type']}$n {$chr}:{$exon['start']}-{$exon['end']}($strand)";
                fwrite($handle, "$header\n" . chunk_split($seq, 60, "\n"));
            }
            continue;
        }

        // Compute the genomic region to extract based on mode + strand
        $ext_start = $gstart;
        $ext_end   = $gend;
        $rc        = ($strand === '-');

        if ($mode === 'upstream') {
            if ($strand === '+') {
                $ext_start = max(1, $gstart - $flank_bp);
                $ext_end   = $gstart - 1;
                $rc        = false;
            } else {
                $ext_start = $gend + 1;
                $ext_end   = $gend + $flank_bp;
                $rc        = true;
            }
        } elseif ($mode === 'downstream') {
            if ($strand === '+') {
                $ext_start = $gend + 1;
                $ext_end   = $gend + $flank_bp;
                $rc        = false;
            } else {
                $ext_start = max(1, $gstart - $flank_bp);
                $ext_end   = $gstart - 1;
                $rc        = true;
            }
        }

        if ($ext_start < 1 || $ext_start > $ext_end) continue;

        $seq = extractFastaRegion($fasta, $fai, $chr, $ext_start, $ext_end);
        if ($seq === null) continue;
        if ($rc) $seq = reverseComplement($seq);

        $label = match($mode) {
            'upstream'   => "upstream_{$flank_bp}bp",
            'downstream' => "downstream_{$flank_bp}bp",
            default      => 'gene',
        };
        $header = ">$uname $label {$chr}:{$ext_start}-{$ext_end}($strand)";
        fwrite($handle, "$header\n" . chunk_split($seq, 60, "\n"));
    }
}

/**
 * Parse the shared MOOPmart preview/export request parameters from a POST array
 * into the structures the preview endpoints consume. Keeps the aggregate and
 * per-organism preview endpoints parsing identically.
 *
 * @param array $post  Usually $_POST
 * @return array {
 *   raw_input_ids, filters, coord_filter, global_filter_reason,
 *   annotation_columns, ann_incl_id, ann_incl_desc
 * }
 */
function moopmartParsePreviewRequest(array $post): array {
    // Raw input IDs (resolved per-organism downstream)
    $raw_input_ids = array_values(array_filter(array_map('trim', (array)($post['feature_ids'] ?? []))));

    // Base filters (no feature_ids — those are resolved per-organism)
    $filters = [];
    $types = array_filter($post['feature_types'] ?? []);
    if (!empty($types))                     $filters['feature_types']    = array_values($types);
    if (!empty($post['gene_name']))         $filters['gene_name']        = trim($post['gene_name']);
    if (!empty($post['gene_description']))  $filters['gene_description'] = trim($post['gene_description']);

    $crit_srcs = $post['ann_criteria_src'] ?? [];
    $crit_accs = $post['ann_criteria_acc'] ?? [];
    $crit_kws  = $post['ann_criteria_kw']  ?? [];
    $criteria  = [];
    foreach (array_keys((array)$crit_srcs) as $i) {
        $src = trim($crit_srcs[$i] ?? '');
        $acc = trim($crit_accs[$i] ?? '');
        $kw  = trim($crit_kws[$i]  ?? '');
        if ($src !== '' || $acc !== '' || $kw !== '') {
            $criteria[] = ['src' => $src, 'acc' => $acc, 'kw' => $kw];
        }
    }
    if (!empty($criteria)) $filters['annotation_criteria'] = $criteria;

    $coord_filter = [];
    if (!empty($post['coord_chr']))   $coord_filter['chr']   = trim($post['coord_chr']);
    if (!empty($post['coord_start'])) $coord_filter['start'] = (int)$post['coord_start'];
    if (!empty($post['coord_end']))   $coord_filter['end']   = (int)$post['coord_end'];

    // Uniform reason string for non-ID filters (same for every matched row)
    $global_filter_reason = buildMoopmartFilterReason($filters, $coord_filter);

    // Annotation columns for the preview (which sources, and ID vs Description)
    $annotation_columns = array_values(array_filter($post['annotation_columns'] ?? []));
    $requested_ann      = array_flip(array_values(array_filter($post['ann_columns'] ?? [])));
    $ann_incl_id        = empty($requested_ann) || isset($requested_ann['ann_id']);
    $ann_incl_desc      = empty($requested_ann) || isset($requested_ann['ann_description']);

    return [
        'raw_input_ids'        => $raw_input_ids,
        'filters'              => $filters,
        'coord_filter'         => $coord_filter,
        'global_filter_reason' => $global_filter_reason,
        'annotation_columns'   => $annotation_columns,
        'ann_incl_id'          => $ann_incl_id,
        'ann_incl_desc'        => $ann_incl_desc,
    ];
}

/**
 * Collect matching, mRNA-expanded preview rows for a SINGLE organism.
 *
 * Backs api/moopmart_preview_organism.php, which the MOOPmart UI calls once per
 * selected organism. Pass $row_cap to bound how many rows are materialised —
 * 'row_count' stays exact regardless.
 *
 * @param string $org                  Organism directory name
 * @param array  $org_sources          Selected source rows for this organism (each has gene_set_id, path, ...)
 * @param array  $filters              Base filters (feature_types, gene_name, annotation_criteria, ...) — no feature_ids
 * @param array  $coord_filter         ['chr'=>, 'start'=>, 'end'=>] or []
 * @param array  $raw_input_ids        Raw user-supplied IDs (resolved per-organism here)
 * @param string $global_filter_reason Uniform "why included" string for non-ID filters
 * @param string $organism_data        Base organism-data path
 * @return array ['gene_count' => int, 'rows' => array]  rows = one entry per mRNA
 */
function moopmartCollectOrganismRows(string $org, array $org_sources, array $filters, array $coord_filter, array $raw_input_ids, string $global_filter_reason, string $organism_data, ?int $row_cap = null): array {
    $empty = ['gene_count' => 0, 'row_count' => 0, 'rows' => []];

    $db = "$organism_data/$org/organism.sqlite";
    if (!file_exists($db)) return $empty;

    $gene_set_ids = array_values(array_filter(array_column($org_sources, 'gene_set_id')));
    if (empty($gene_set_ids)) return $empty;

    // Resolve input IDs for this organism and build per-gene reason map
    $id_reasons  = [];
    $org_filters = $filters;
    if (!empty($raw_input_ids)) {
        $id_reasons = moopmartResolveInputIds($raw_input_ids, $db, $gene_set_ids);
        if (empty($id_reasons)) return $empty;
        $org_filters['feature_ids'] = array_keys($id_reasons);
    }

    $features = moopmartQueryFeatures($gene_set_ids, $db, $org_filters);
    if (empty($features)) return $empty;

    $uniquenames_by_gs = [];
    foreach ($features as $f) {
        $uniquenames_by_gs[$f['gene_set_id']][] = $f['uniquename'];
    }

    $coords_by_gs = [];
    foreach ($org_sources as $src) {
        $gs_id = $src['gene_set_id'];
        if ($gs_id && !isset($coords_by_gs[$gs_id])) {
            $coords_by_gs[$gs_id] = moopmartLoadGeneCoords($src['path'], $uniquenames_by_gs[$gs_id] ?? []);
        }
    }

    $matched   = moopmartAttachCoords($features, $coords_by_gs, $coord_filter);
    $gene_rows = [];
    foreach ($matched as $f) {
        $f['organism_dir'] = $org;
        $f['db_path']      = $db;
        // "why included": ID resolution takes priority; fall back to filter description
        if (!empty($id_reasons)) {
            $reason = $id_reasons[$f['uniquename']] ?? '';
            if ($global_filter_reason) $reason .= ($reason ? ' + ' : '') . $global_filter_reason;
        } else {
            $reason = $global_filter_reason;
        }
        $f['match_reason'] = $reason;
        $gene_rows[]       = $f;
    }

    // Total expanded rows, counted rather than built: expansion copies the whole gene row
    // once per transcript, which for a large genome is hundreds of MB the caller would only
    // throw away. Genes with no transcript children still render one row, hence max(n, 1).
    $child_counts = moopmartCountChildRows(array_column($gene_rows, 'feature_id'), $db);
    $row_count    = 0;
    foreach ($gene_rows as $f) {
        $row_count += max($child_counts[(int)$f['feature_id']] ?? 0, 1);
    }

    // Expand only as far as the caller will read. Every gene yields at least one row, so the
    // first $row_cap genes always supply at least $row_cap rows.
    $to_expand = $row_cap === null ? $gene_rows : array_slice($gene_rows, 0, $row_cap);
    $mrna_rows = moopmartExpandToMrnaRows($to_expand, $db);
    if ($row_cap !== null && count($mrna_rows) > $row_cap) {
        $mrna_rows = array_slice($mrna_rows, 0, $row_cap);
    }

    return ['gene_count' => count($gene_rows), 'row_count' => $row_count, 'rows' => $mrna_rows];
}

/**
 * Build the JSON-ready preview rows (+ annotation column headers) from a slice
 * of mRNA-expanded rows. Fetches the selected annotation-source columns for the
 * preview rows only. Shared by the aggregate and per-organism preview endpoints.
 *
 * @param array $preview                     mRNA rows already sliced to the preview cap
 * @param array $annotation_columns_selected Selected annotation source names
 * @param bool  $ann_incl_id                 Include "ID:<src>" columns
 * @param bool  $ann_incl_desc               Include "Description:<src>" columns
 * @return array ['rows' => array, 'ann_col_headers' => array]
 */
function moopmartBuildPreviewRows(array $preview, array $annotation_columns_selected, bool $ann_incl_id, bool $ann_incl_desc): array {
    $clean_prev        = fn($s) => str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string)$s);
    $ann_col_headers   = [];
    $ann_by_uniquename = [];

    if (!empty($annotation_columns_selected)) {
        foreach ($annotation_columns_selected as $src) {
            if ($ann_incl_id)   $ann_col_headers[] = 'ID:' . $src;
            if ($ann_incl_desc) $ann_col_headers[] = 'Description:' . $src;
        }

        // Deduplicate by gene feature_id before fetching annotations
        $seen_fids     = [];
        $preview_by_db = [];
        foreach ($preview as $f) {
            if (empty($f['db_path']) || isset($seen_fids[$f['feature_id']])) continue;
            $seen_fids[$f['feature_id']]    = true;
            $preview_by_db[$f['db_path']][] = $f;
        }
        foreach ($preview_by_db as $db_path => $db_feats) {
            $chunk_anns = moopmartGetAnnotationsForFeatures(array_column($db_feats, 'feature_id'), $db_path);
            foreach ($db_feats as $f) {
                $ann_by_uniquename[$f['uniquename']] = $chunk_anns[$f['feature_id']] ?? [];
            }
        }
    }

    $rows = array_map(function ($f) use ($annotation_columns_selected, $ann_by_uniquename, $ann_incl_id, $ann_incl_desc, $clean_prev) {
        $row = [
            'uniquename'       => $f['uniquename'],
            'name'             => $f['name']          ?? '',
            'description'      => $f['description']   ?? '',
            'organism_dir'     => $f['organism_dir'],
            'genome_accession' => $f['genome_accession'],
            'gene_set_name'    => $f['gene_set_name'],
            'mrna_id'          => $f['mrna_id']       ?? '',
            'protein_id'       => $f['protein_id']    ?? '',
            'chr'              => $f['chr']            ?? '',
            'start'            => $f['start']          ?? '',
            'end'              => $f['end']            ?? '',
            'strand'           => $f['strand']         ?? '',
            'match_reason'     => $f['match_reason']   ?? '',
        ];
        foreach ($annotation_columns_selected as $src) {
            $entries = $ann_by_uniquename[$f['uniquename']][$src] ?? [];
            if ($ann_incl_id)   $row['ID:' . $src]          = implode('; ', array_map(fn($e) => $e['accession'], $entries));
            if ($ann_incl_desc) $row['Description:' . $src] = implode('; ', array_map(fn($e) => $clean_prev($e['description'] ?? ''), $entries));
        }
        return $row;
    }, $preview);

    return ['rows' => $rows, 'ann_col_headers' => $ann_col_headers];
}
