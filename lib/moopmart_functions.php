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
 *   lib/blast_functions.php     — extractFastaRegion(), reverseComplement()
 *   lib/feature_coords.php      — feature_coords.tsv lookups
 */

// Required outright rather than relied on transitively: the ID-resolution CTE below
// uses MOOP_HIERARCHY_MAX_DEPTH, and an undefined constant is a fatal Error in PHP 8.
// Every other include of this file already uses include_once/require_once, so this
// is idempotent.
require_once __DIR__ . '/parent_functions.php';

// Also required outright: moopmartResolveInputIds() calls moop_resolve_input_ids() for
// version-tolerant ID matching. Same reasoning as the line above — an undefined function is
// a fatal Error, and this file is included from endpoints that do not otherwise pull in
// database_queries.php. (That file require_once's parent_functions.php itself; the order
// here is harmless because neither has include-time side effects.)
require_once __DIR__ . '/database_queries.php';
require_once __DIR__ . '/genomic_regions.php';      // moopmartStreamGenomicFasta()
require_once __DIR__ . '/gff_gene_model.php';       // GFF id rules, for exon mode
require_once __DIR__ . '/feature_types.php';        // moop_transcript_types()

/** Max rows a preview endpoint materialises and returns. Counts stay exact regardless. */
const MOOPMART_PREVIEW_ROW_CAP = 100;

// ============================================================
// ID RESOLUTION
// ============================================================

/**
 * Resolve raw input IDs (gene, mRNA, protein, CDS, etc.) to gene-level uniquenames
 * with a provenance string explaining why each gene was included.
 *
 * Uses the shared walker (moop_hierarchy_walk) to climb regardless of depth, so
 * protein→CDS→mRNA→gene (3 levels) works just as well as mRNA→gene (1 level).
 * Multiple input IDs that resolve to the same gene accumulate their reasons.
 *
 * IDs are matched version-tolerantly (moop_resolve_input_ids): MOOP displays
 * NV2t021704001.1 but users paste NV2t021704001, and an exact-only match resolved nothing
 * and exported an empty file with no explanation. Reported 2026-08-05 from the UI.
 *
 * @param string[] $input_ids    Raw IDs from user input
 * @param string   $db_path      Path to organism.sqlite
 * @param int[]    $gene_set_ids Accessible gene_set_ids for this organism
 * @param string[] $unresolved   OUT: inputs that matched nothing in this database. Callers
 *                               MUST surface these -- a silently smaller export is the
 *                               failure this parameter exists to prevent.
 * @return array   [gene_uniquename => reason_string]
 */
function moopmartResolveInputIds(array $input_ids, string $db_path, array $gene_set_ids, &$unresolved = null): array
{
    if (empty($input_ids) || empty($gene_set_ids)) return [];

    // Version-tolerant ID resolution FIRST, then the climb.
    //
    // Ambiguity is kept, not resolved: if a stem matches .1 and .2 both are seeded, both
    // export, and the reason string says so (user decision, 2026-08-05 -- "list both, export
    // both, report it"). Silently picking one would make an export depend on a tie-break
    // nobody can see.
    $resolved = moop_resolve_input_ids($input_ids, $db_path, $gene_set_ids, $unresolved);
    if (empty($resolved)) return [];

    // uniquename -> the id the USER typed, for the reason label.
    $typed_as = [];
    $seeds    = [];
    foreach ($resolved as $typed => $names) {
        foreach ($names as $n) { $seeds[] = $n; $typed_as[$n] = (string)$typed; }
    }

    // The shared walker carries the cycle guards. Note what it does NOT do, deliberately: a
    // self-parented feature terminates at the guard and never satisfies "no parent" below, so
    // it resolves to nothing. The cure is reloading the data, not teaching this to accept a
    // broken hierarchy as a root.
    $rows = moop_hierarchy_walk($seeds, $db_path, 'up', $gene_set_ids);
    if (empty($rows)) return [];

    // The seed's OWN type, for the reason label. Depth 0 is the seed itself.
    $seed_type = [];
    foreach ($rows as $r) {
        if ((int)$r['depth'] === 0) $seed_type[(string)$r['seed_name']] = (string)$r['feature_type'];
    }

    $by_gene = [];
    foreach ($rows as $r) {
        // The root is the node with no parent -- the same test the CTE used to make.
        $pid = $r['parent_feature_id'];
        if ($pid !== null && $pid !== '' && $pid !== 'NULL') continue;

        $input = (string)$r['seed_name'];
        $gene  = (string)$r['feature_uniquename'];
        $typed = $typed_as[$input] ?? $input;

        // Say what MATCHED when it differs from what was typed, so a row that came back
        // under a version the user did not enter is explained rather than surprising.
        $shown = ($typed === $input) ? $input : "{$input} (matched {$typed})";
        $reason = ($input === $gene && $typed === $input)
            ? "Gene ID: {$input}"
            : moopmartFeatureTypeLabel($seed_type[$input] ?? '') . ": {$shown}";
        $by_gene[$gene][] = $reason;
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

    $mrna_types = moop_transcript_types();
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
 * @return array [gene_feature_id => [['mrna_fid'=>int, 'mrna_id'=>'...', 'protein_id'=>'...'], ...]]
 */
function moopmartGetChildIds(array $gene_feature_ids, string $db_path): array
{
    if (empty($gene_feature_ids)) return [];

    $mrna_types = moop_transcript_types();
    $type_ph    = implode(',', array_fill(0, count($mrna_types), '?'));

    $result = [];
    foreach (array_chunk($gene_feature_ids, 500) as $chunk) {
        $ph    = implode(',', array_fill(0, count($chunk), '?'));
        // Protein may be a direct child of mRNA OR a grandchild via CDS — handle both.
        // Feature type may be stored as 'protein' or 'polypeptide' depending on the source GFF.
        $query = "SELECT
                      mrna.parent_feature_id  AS gene_fid,
                      mrna.feature_id         AS mrna_fid,
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
                'mrna_fid'   => (int)$row['mrna_fid'],
                'mrna_id'    => $row['mrna_id'],
                'protein_id' => $row['protein_id'] ?? '',
            ];
        }
    }
    return $result;
}

/**
 * Expand gene-level feature rows to one row per mRNA child.
 * Each expanded row inherits the gene row's data and adds mrna_id, protein_id and
 * mrna_feature_id (what moopmartRowAnnotations() reads the isoform's own annotations by).
 * Genes with no mRNA children produce one row with empty ids and a null mrna_feature_id.
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
                $row                    = $f;
                $row['mrna_feature_id'] = $child['mrna_fid'];
                $row['mrna_id']         = $child['mrna_id'];
                $row['protein_id']      = $child['protein_id'];
                $expanded[]             = $row;
            }
        } else {
            $f['mrna_feature_id'] = null;
            $f['mrna_id']         = '';
            $f['protein_id']      = '';
            $expanded[]           = $f;
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
    foreach ($filters['annotation_criteria'] ?? [] as $criterion) {
        $clause = moopmartAnnotationCriterionClause($criterion);
        if ($clause) {
            $where[] = $clause['sql'];
            array_push($params, ...$clause['params']);
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
// ANNOTATIONS — one rule for which annotations belong to a row
// ============================================================

/**
 * SQL for one annotation criterion: the gene matches if the annotation is stored on the
 * gene itself OR on one of its children. Most loaders attach annotations to the mRNA, but a
 * gene with no transcript level (Bradyrhizobium: 117,552 annotations on genes) carries them
 * itself — a children-only test reported "No features matched" for every such filter.
 *
 * @param array $criterion ['src' => source name (exact), 'acc' => accession, 'kw' => keywords]
 * @return array|null ['sql' => string, 'params' => array], or null if the criterion is empty
 */
function moopmartAnnotationCriterionClause(array $criterion): ?array
{
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
    if (empty($crit_where)) return null;

    $crit_sql = implode(' AND ', $crit_where);
    $match    = fn(string $holder) => "EXISTS (
                SELECT 1
                FROM feature_annotation fa2
                JOIN annotation         a   ON fa2.annotation_id      = a.annotation_id
                JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
                WHERE fa2.feature_id IN ($holder)
                  AND $crit_sql
            )";
    // Two EXISTS rather than one OR'd join, so each side keeps its index
    // (feature_annotation.feature_id, feature.parent_feature_id).
    return [
        'sql'    => '(' . $match('f.feature_id') . ' OR '
                        . $match('SELECT child.feature_id FROM feature child WHERE child.parent_feature_id = f.feature_id') . ')',
        'params' => array_merge($crit_params, $crit_params),
    ];
}

/**
 * Fetch the annotations of a set of genes and their children, keyed by the feature that
 * holds them, so a row can be given exactly its own (moopmartRowAnnotations()).
 *
 * Keys: a transcript child's annotations under the TRANSCRIPT's feature_id; the gene's own
 * annotations, and those of any non-transcript child, under the GENE's feature_id. This used
 * to merge every isoform's annotations under the gene, so each isoform row of the export
 * showed its siblings' hits too (15,203 Drosophila isoform rows), and it never looked at the
 * gene itself, so a gene-level annotation was never exported at all.
 *
 * Chunked in batches of 500 to avoid oversized IN clauses on large result sets.
 *
 * @param int[]  $gene_feature_ids
 * @param string $db_path
 * @return array [feature_id => [source_name => [accession => {accession, description}]]]
 */
function moopmartGetAnnotationsForGenes(array $gene_feature_ids, string $db_path): array
{
    if (empty($gene_feature_ids)) return [];

    $transcript_types = array_flip(moop_transcript_types());
    $result = [];
    foreach (array_chunk($gene_feature_ids, 500) as $chunk) {
        $ph     = implode(',', array_fill(0, count($chunk), '?'));
        $select = "SELECT f.feature_id, f.parent_feature_id, LOWER(f.feature_type) AS type, %d AS is_child,
                          ans.annotation_source_name, a.annotation_accession, a.annotation_description
                   FROM feature            f
                   JOIN feature_annotation fa  ON fa.feature_id          = f.feature_id
                   JOIN annotation         a   ON fa.annotation_id       = a.annotation_id
                   JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
                   WHERE %s IN ($ph)";
        $query  = sprintf($select, 0, 'f.feature_id') . ' UNION ALL ' . sprintf($select, 1, 'f.parent_feature_id')
                . ' ORDER BY annotation_source_name, annotation_accession';

        foreach (fetchData($query, $db_path, array_merge($chunk, $chunk)) as $row) {
            $holder = ($row['is_child'] && isset($transcript_types[$row['type']]))
                ? (int)$row['feature_id']          // an isoform's own
                : (int)($row['is_child'] ? $row['parent_feature_id'] : $row['feature_id']);  // the gene's
            $acc = $row['annotation_accession'];
            $result[$holder][$row['annotation_source_name']][$acc] = [
                'accession'   => $acc,
                'description' => $row['annotation_description'],
            ];
        }
    }
    return $result;
}

/**
 * The annotations one exported row carries: its gene's own, plus — for an isoform row — that
 * isoform's, and never a sibling isoform's. The download and the preview both read this, so
 * the preview stays the file.
 *
 * @param array $anns Output of moopmartGetAnnotationsForGenes() for the row's database
 * @param array $row  A row from moopmartExpandToMrnaRows()
 * @return array [source_name => [{accession, description}, ...]], sources and accessions sorted
 */
function moopmartRowAnnotations(array $anns, array $row): array
{
    $merged = $anns[$row['feature_id']] ?? [];
    $mfid   = $row['mrna_feature_id'] ?? null;
    if ($mfid !== null) {
        foreach ($anns[$mfid] ?? [] as $src => $entries) {
            foreach ($entries as $acc => $entry) $merged[$src][$acc] = $entry;
        }
    }
    foreach ($merged as &$entries) {
        ksort($entries, SORT_STRING);
        $entries = array_values($entries);
    }
    unset($entries);
    return $merged;
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

    $result = moop_feature_coords_seqnames($gene_set_path) ?? [];
    if ($cache !== '') @file_put_contents($cache, json_encode($result));
    return $result;
}

// ============================================================
// COORDINATE FUNCTIONS (coords are in feature_coords.tsv, not the DB)
// ============================================================

/**
 * Attach coordinate data to feature rows and optionally filter by genomic region.
 *
 * Features with no coord entry are dropped unless feature_coords.tsv was absent
 * (empty $coords_by_gs_id entry), in which case they are kept with empty coord fields.
 *
 * @param array $features           Output of moopmartQueryFeatures()
 * @param array $coords_by_gs_id    [gene_set_id => [uniquename => [chr,start,end,strand]]]
 *                                  Built by caller using moop_feature_coords_by_gene() per source
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
 * Only the first isoform (first transcript-type child encountered) per gene is used.
 * Sub-features collected: exon, CDS, five_prime_UTR, three_prime_UTR, UTR.
 * Features assumed to be in parent-before-child order (standard GFF3).
 *
 * Ids are matched by lib/gff_gene_model.php's rules (prefix, GeneID), and links by its
 * moop_gff_parents(). This used to require an ID= on every line -- exon lines usually carry
 * only Parent=, so six gene sets (NV2 among them) exported no exons at all -- and to match the
 * GFF gene ID to the database id exactly, which missed every "gene:"-prefixed GFF.
 *
 * @param string   $gff_path         Path to genes.gff
 * @param string[] $gene_uniquenames  Gene IDs (database uniquenames) to collect sub-features for
 * @return array   [gene_id => [{start, end, type, strand}, ...]] sorted by start ASC
 */
function moopmartGetExonCoords(string $gff_path, array $gene_uniquenames): array
{
    if (!file_exists($gff_path) || empty($gene_uniquenames)) return [];

    $target_genes = array_flip($gene_uniquenames);
    $gff_gene     = [];  // GFF gene ID => database gene uniquename
    $has_first    = [];  // database gene uniquename => true once its first isoform is chosen
    $mrna_to_gene = [];  // GFF transcript ID => database gene uniquename
    $sub_features = [];  // database gene uniquename => [{start, end, type, strand}]

    $exon_types = ['exon', 'cds', 'five_prime_utr', 'three_prime_utr', 'utr'];

    $fh = fopen($gff_path, 'r');
    if ($fh === false) return [];
    while (($line = fgets($fh)) !== false) {
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode("\t", rtrim($line, "\r\n"));
        if (count($parts) < 9) continue;

        $feat_type_raw = $parts[2];
        $feat_type     = strtolower($feat_type_raw);
        $attrs         = moop_gff_attrs($parts[8]);
        $parents       = moop_gff_parents($attrs);
        $id            = $attrs['ID'] ?? '';

        // A target gene: remember which database id its GFF ID stands for
        if (!$parents && $id !== '') {
            foreach (moop_gff_db_id_candidates($attrs) as $cand) {
                if (!isset($target_genes[$cand])) continue;
                $gff_gene[$id] = $cand;
                // A top-level transcript (Feature type: mRNA, or a transcriptome) is its own isoform
                if (moop_gff_is_transcript_type($feat_type)) { $has_first[$cand] = true; $mrna_to_gene[$id] = $cand; }
                break;
            }
            continue;
        }

        // The first transcript-type child of each target gene
        if ($id !== '' && moop_gff_is_transcript_type($feat_type)) {
            foreach ($parents as $p) {
                $gene = $gff_gene[$p] ?? null;
                if ($gene !== null && !isset($has_first[$gene])) {
                    $has_first[$gene] = true;
                    $mrna_to_gene[$id] = $gene;
                    break;
                }
            }
            continue;
        }

        // Exon/CDS/UTR children of a chosen isoform -- no ID= needed -- or, for a gene with no
        // transcript level (bacteria: gene -> CDS), of the gene itself
        if (in_array($feat_type, $exon_types, true)) {
            foreach ($parents as $p) {
                $gene = $mrna_to_gene[$p] ?? null;
                if ($gene === null && isset($gff_gene[$p]) && !isset($has_first[$gff_gene[$p]])) $gene = $gff_gene[$p];
                if ($gene === null) continue;
                $sub_features[$gene][] = [
                    'start'  => (int)$parts[3],
                    'end'    => (int)$parts[4],
                    'type'   => $feat_type_raw,
                    'strand' => $parts[6],
                ];
                break;
            }
        }
    }
    fclose($fh);

    foreach ($sub_features as &$feats) {
        usort($feats, fn($a, $b) => $a['start'] - $b['start']);
    }
    unset($feats);

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
 * Region, strand and header rules: lib/genomic_regions.php (shared with Sequence Retrieval).
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
 * @return array  What was skipped and why, for moop_genomic_skip_notes() -- never silently
 */
function moopmartStreamGenomicFasta(
    array $genes,
    string $assembly_dir,
    string $gff_path,
    string $mode,
    int $flank_bp,
    $handle
): array {
    // One .fai lookup for the whole batch and the FASTA held open -- not a scan of the .fai
    // per gene (and per exon), which was most of a large genomic export's time.
    $genome = moop_genome_open($assembly_dir, array_column($genes, 'chr'));
    if (!$genome) return [];   // the caller reports the missing file

    $missing = array_flip(moop_genome_missing($genome, array_column($genes, 'chr')));
    $skips   = [];

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

        if ((string)$chr === '') { $skips['no_coords'][] = $uname; continue; }
        if (isset($missing[$chr])) { $skips['missing_chr'][$chr][] = $uname; continue; }

        if ($mode === 'exons') {
            $gene_exons = $exon_coords[$uname] ?? [];
            if (empty($gene_exons)) { $skips['no_exons'][] = $uname; continue; }
            // Numbered 5'->3' along the gene, each type counted on its own: exon1 is the
            // first exon transcribed and CDS1 the first coding block -- on the minus strand the
            // highest coordinates. (They shared one counter in coordinate order, so a minus-
            // strand gene's exon1 was its LAST exon, and the list read exon1, CDS2, exon3.)
            if ($strand === '-') $gene_exons = array_reverse($gene_exons);
            $n = [];
            foreach ($gene_exons as $exon) {
                $k = strtolower($exon['type']);
                $n[$k] = ($n[$k] ?? 0) + 1;
                $got = moop_genomic_sequence($genome, ['chr' => $chr, 'start' => $exon['start'], 'end' => $exon['end'], 'strand' => $strand]);
                if (!$got) continue;
                $r = $got['region'];
                fwrite($handle, ">$uname.{$exon['type']}{$n[$k]} {$chr}:{$r['start']}-{$r['end']}($strand)\n" . chunk_split($got['seq'], MOOP_FASTA_LINE_WIDTH, "\n"));
            }
            continue;
        }

        // The region, its orientation and the header come from lib/genomic_regions.php --
        // the same rules Sequence Retrieval uses, so both pages give the same bases.
        $part   = $mode === 'gene' ? 'body' : $mode;
        $region = moop_genomic_region(['chr' => $chr, 'start' => $gstart, 'end' => $gend, 'strand' => $strand], $part, $flank_bp);
        if (!$region) continue;
        $got = moop_genomic_sequence($genome, $region);
        if (!$got) continue;
        fwrite($handle, moop_genomic_fasta_record($uname, $part, $flank_bp, $got['region'], $got['seq']));
    }
    moop_genome_close($genome);
    return $skips;
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
        // The source name exactly as listed: never trimmed. Names are matched as stored, and eight
        // databases told an RBBH source from a Homologs one only by a trailing space.
        $src = (string)($crit_srcs[$i] ?? '');
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
    $ann_incl_src       = empty($requested_ann) || isset($requested_ann['ann_source']);
    // Long (default): one row per annotation. Wide: one row per feature, a source's values
    // joined. Parsed here once for the preview AND the download -- the preview used to be
    // wide whatever was chosen, so "Long" looked like it did nothing.
    $ann_format         = ($post['ann_format'] ?? 'long') === 'wide' ? 'wide' : 'long';

    return [
        'raw_input_ids'        => $raw_input_ids,
        'filters'              => $filters,
        'coord_filter'         => $coord_filter,
        'global_filter_reason' => $global_filter_reason,
        'annotation_columns'   => $annotation_columns,
        'ann_incl_id'          => $ann_incl_id,
        'ann_incl_desc'        => $ann_incl_desc,
        'ann_incl_src'         => $ann_incl_src,
        'ann_format'           => $ann_format,
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
 * @return array ['gene_count' => int, 'rows' => array, 'genes' => list<string>]  rows = one entry per mRNA;
 *               genes = every matched gene's uniquename, in match order (not capped)
 */
function moopmartCollectOrganismRows(string $org, array $org_sources, array $filters, array $coord_filter, array $raw_input_ids, string $global_filter_reason, string $organism_data, ?int $row_cap = null): array {
    $empty = ['gene_count' => 0, 'row_count' => 0, 'rows' => []];

    $db = "$organism_data/$org/organism.sqlite";
    if (!file_exists($db)) return $empty;

    $gene_set_ids = array_values(array_filter(array_column($org_sources, 'gene_set_id')));
    if (empty($gene_set_ids)) return $empty;

    // Resolve input IDs for this organism and build per-gene reason map
    $id_reasons  = [];
    $unresolved  = [];
    $org_filters = $filters;
    if (!empty($raw_input_ids)) {
        $id_reasons = moopmartResolveInputIds($raw_input_ids, $db, $gene_set_ids, $unresolved);
        // Report the misses even when NOTHING resolved -- an all-miss preview used to return
        // a bare empty result, which reads as "this organism has none of your genes" when the
        // truth may be "none of those IDs exist here". Those are different answers.
        if (empty($id_reasons)) return $empty + ['unresolved_ids' => $unresolved];
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
            $coords_by_gs[$gs_id] = moop_feature_coords_by_gene($src['path'], $uniquenames_by_gs[$gs_id] ?? []);
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

    return ['gene_count' => count($gene_rows), 'row_count' => $row_count, 'rows' => $mrna_rows,
            'genes' => array_column($gene_rows, 'uniquename'), 'unresolved_ids' => $unresolved];
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
function moopmartBuildPreviewRows(array $preview, array $annotation_columns_selected, bool $ann_incl_id, bool $ann_incl_desc,
                                  bool $ann_incl_src = true, string $ann_format = 'wide'): array {
    $clean_prev        = fn($s) => str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string)$s);
    $ann_col_headers   = [];
    $row_anns          = [];   // preview index => that row's own annotations
    $long              = $ann_format === 'long';
    $preview           = array_values($preview);   // row_anns, rows and the long expansion share these indexes

    if (!empty($annotation_columns_selected)) {
        // The download's own column names (api/moopmart_export.php), so the preview is the file.
        if ($long) {
            if ($ann_incl_src)  $ann_col_headers[] = 'annotation_source';
            if ($ann_incl_id)   $ann_col_headers[] = 'annotation_id';
            if ($ann_incl_desc) $ann_col_headers[] = 'annotation_description';
        } else {
            foreach ($annotation_columns_selected as $src) {
                if ($ann_incl_id)   $ann_col_headers[] = 'ID:' . $src;
                if ($ann_incl_desc) $ann_col_headers[] = 'Description:' . $src;
            }
        }

        // One fetch per database for its genes, then each row takes only its own annotations
        $gene_fids_by_db = [];
        foreach ($preview as $f) {
            if (!empty($f['db_path'])) $gene_fids_by_db[$f['db_path']][$f['feature_id']] = true;
        }
        $anns_by_db = [];
        foreach ($gene_fids_by_db as $db_path => $fids) {
            $anns_by_db[$db_path] = moopmartGetAnnotationsForGenes(array_keys($fids), $db_path);
        }
        foreach ($preview as $i => $f) {
            $row_anns[$i] = moopmartRowAnnotations($anns_by_db[$f['db_path'] ?? ''] ?? [], $f);
        }
    }

    $rows = array_map(function ($f, $i) use ($annotation_columns_selected, $row_anns, $ann_incl_id, $ann_incl_desc, $clean_prev, $long) {
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
        if ($long) return $row;   // expanded below, one row per annotation
        foreach ($annotation_columns_selected as $src) {
            $entries = $row_anns[$i][$src] ?? [];
            if ($ann_incl_id)   $row['ID:' . $src]          = implode('; ', array_map(fn($e) => $e['accession'], $entries));
            if ($ann_incl_desc) $row['Description:' . $src] = implode('; ', array_map(fn($e) => $clean_prev($e['description'] ?? ''), $entries));
        }
        return $row;
    }, $preview, array_keys($preview));

    // Long: the feature row repeated once per annotation, sources in the order chosen -- exactly
    // as the download writes it; a feature with none of the chosen annotations keeps one row.
    if ($long && $annotation_columns_selected) {
        $expanded = [];
        foreach ($rows as $i => $row) {
            $emitted = false;
            foreach ($annotation_columns_selected as $src) {
                foreach ($row_anns[$i][$src] ?? [] as $entry) {
                    $line = $row;
                    if ($ann_incl_src)  $line['annotation_source']      = $src;
                    if ($ann_incl_id)   $line['annotation_id']          = $entry['accession'];
                    if ($ann_incl_desc) $line['annotation_description'] = $clean_prev($entry['description'] ?? '');
                    $expanded[] = $line;
                    $emitted = true;
                }
            }
            if (!$emitted) $expanded[] = $row;
        }
        $rows = $expanded;
    }

    return ['rows' => $rows, 'ann_col_headers' => $ann_col_headers];
}
