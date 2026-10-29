<?php
/**
 * Extract/Search Common Helper Functions
 * 
 * Consolidates repeated logic from:
 * - tools/*.php (retrieve_sequences.php, retrieve_selected_sequences.php)
 * - tools/*.php (multi_organism_search.php, annotation_search_ajax.php)
 * 
 * Provides unified handling for:
 * - Multi-organism parameter parsing (multiple input formats)
 * - Context parameter extraction
 * - Sequence extraction and formatting
 * - File download orchestration
 * - Source list organization
 */

/**
 * Parse organism parameter from various sources and formats
 * 
 * Handles multiple input formats:
 * - Array from multi-search context (organisms[])
 * - Single organism from context parameters
 * - Comma-separated string
 * 
 * @param string|array $organisms_param - Raw parameter value
 * @param string $context_organism - Optional fallback organism
 * @return array - ['organisms' => [], 'string' => 'comma,separated,list']
 */
function parseOrganismParameter($organisms_param, $context_organism = '') {
    $filter_organisms = [];
    $filter_organisms_string = '';
    
    // First check for array (highest priority - from multi-search)
    if (is_array($organisms_param)) {
        $filter_organisms = array_filter($organisms_param);
        $filter_organisms_string = implode(',', $filter_organisms);
    } 
    // Then check for single organism context
    elseif (!empty($context_organism)) {
        $filter_organisms = [$context_organism];
        $filter_organisms_string = $context_organism;
    }
    // Finally try comma-separated string format
    else {
        $filter_organisms_string = trim($organisms_param);
        if (!empty($filter_organisms_string)) {
            $filter_organisms = array_map('trim', explode(',', $filter_organisms_string));
            $filter_organisms = array_filter($filter_organisms);
        }
    }
    
    return [
        'organisms' => $filter_organisms,
        'string' => $filter_organisms_string
    ];
}

/**
 * Extract context parameters from request
 * 
 * Checks explicit context_* fields first (highest priority), then regular fields as fallback
 * 
 * @return array - ['organism' => '', 'assembly' => '', 'group' => '', 'display_name' => '', 'context_page' => '']
 */
function parseContextParameters() {
    return [
        'organism'     => trim($_GET['context_organism'] ?? $_POST['context_organism'] ?? $_GET['organism']  ?? $_POST['organism']  ?? ''),
        'assembly'     => trim($_GET['context_assembly'] ?? $_POST['context_assembly'] ?? $_GET['assembly']  ?? $_POST['assembly']  ?? ''),
        'gene_set'     => trim($_GET['context_gene_set'] ?? $_POST['context_gene_set'] ?? $_GET['gene_set'] ?? $_POST['gene_set']  ?? ''),
        'group'        => trim($_GET['context_group']    ?? $_POST['context_group']    ?? $_GET['group']     ?? $_POST['group']     ?? ''),
        'display_name' => trim($_GET['display_name'] ?? $_POST['display_name'] ?? ''),
        'context_page' => trim($_GET['context_page']  ?? $_POST['context_page']  ?? ''),
    ];
}

/**
 * Parse the feature IDs a user typed, each optionally followed by a range.
 *
 * Entries are separated by newlines or commas. A range is the trailing part of an entry:
 * `ID:1-30`, `ID:1..30`, `ID 1-30` or `ID 1..30` -- all the same. Everything before the
 * range is the ID, so an ID that itself contains a colon (`P1:pep:1-30`) keeps it.
 *
 * Every entry is its own REQUEST, in the order typed: `X:1-30` and `X:31-40` are two
 * requests, and `X` alongside them is a third (the whole sequence). This used to keep only
 * the first range per ID and drop a reversed one without a word.
 *
 * A range that cannot be satisfied -- start after end, or a 0 start (positions count from
 * 1) -- makes the whole input invalid, with an error naming the entries, rather than being
 * silently skipped. A range past the end of a sequence is fine: blastdbcmd stops at the end.
 *
 * @param string $uniquenames_string comma- or newline-separated entries
 * @return array{valid: bool, requests: list<array{id: string, start: ?int, end: ?int}>,
 *               uniquenames: list<string>, has_ranges: bool, error: string}
 */
function parseFeatureIds($uniquenames_string) {
    $fail = fn(string $error) => ['valid' => false, 'requests' => [], 'uniquenames' => [], 'has_ranges' => false, 'error' => $error];

    $entries = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string)$uniquenames_string)), 'strlen'));
    if (!$entries) return $fail('No feature IDs provided');

    $requests = [];
    $bad      = [];
    foreach ($entries as $entry) {
        if (preg_match('/^(.+?)[\s:]+(\d+)(?:-|\.\.)(\d+)$/', $entry, $m)) {
            [$start, $end] = [(int)$m[2], (int)$m[3]];
            if ($start < 1 || $start > $end) { $bad[] = $entry; continue; }
            $requests[] = ['id' => trim($m[1]), 'start' => $start, 'end' => $end];
        } else {
            $requests[] = ['id' => $entry, 'start' => null, 'end' => null];
        }
    }
    if ($bad) {
        return $fail('These ranges cannot be extracted -- a range is start-end, counting from 1, with start no greater than end: '
                     . implode(', ', $bad));
    }

    return [
        'valid'       => true,
        'requests'    => $requests,
        'uniquenames' => array_values(array_unique(array_column($requests, 'id'))),
        'has_ranges'  => (bool)array_filter($requests, fn($r) => $r['start'] !== null),
        'error'       => '',
    ];
}

/**
 * The blastdbcmd batch lines for ONE FASTA file: each request applied to its ID and to every
 * descendant of that ID, keeping only the IDs this FASTA holds ($sequence_ids). A gene has no
 * sequence of its own, so `G:1-30` becomes `G-RA 1-30` in the transcript FASTA, `G-RA:cds 1-30`
 * in the CDS FASTA, and so on. Identical lines are emitted once.
 *
 * @param list<array{id: string, start: ?int, end: ?int}> $requests from parseFeatureIds()
 * @param list<string>                $sequence_ids       IDs routed to this FASTA
 * @param array<string, list<string>> $parent_to_children ID => all its descendants' IDs
 * @return list<string> "ID" or "ID start-end"
 */
function buildSequenceBatch(array $requests, array $sequence_ids, array $parent_to_children = []): array {
    $here  = array_flip($sequence_ids);
    $lines = [];
    foreach ($requests as $r) {
        foreach (array_merge([$r['id']], $parent_to_children[$r['id']] ?? []) as $id) {
            if (!isset($here[$id])) continue;
            $lines[] = $r['start'] === null ? $id : "$id {$r['start']}-{$r['end']}";
        }
    }
    return array_values(array_unique($lines));
}

/**
 * Map a DB feature_type to the sequence_types config key used for FASTA routing.
 * Returns null for types that have no dedicated FASTA (gene, exon, pseudogene, etc.).
 */
function _fasta_key_for_type(string $type): ?string {
    static $map = [
        'mRNA'        => 'transcript',
        'transcript'  => 'transcript',
        'CDS'         => 'cds',
        'cds'         => 'cds',
        'protein'     => 'protein',
        'polypeptide' => 'protein',
    ];
    return $map[$type] ?? null;
}

/**
 * Batch-look up feature types from a SQLite DB for a list of uniquenames.
 *
 * @param array  $uniquenames  Feature uniquenames to resolve
 * @param string $db_path      Path to organism.sqlite
 * @return array [$uniquename => $feature_type|null]  null = not found in DB
 */
/**
 * Map feature uniquenames to their feature_type.
 *
 * $assembly and $gene_set are optional but SHOULD be passed whenever the caller knows
 * them. Without them this looks a uniquename up across the organism's entire database,
 * and a uniquename is only unique within a gene set — an organism carrying two gene sets
 * (or two assemblies) can return several rows for one name, whereupon the last row
 * silently wins and a feature can be typed from the wrong gene set. (A sibling,
 * buildTypedIdsForGenes(), scoped its lookups with `f.gene_set_id = ?` and this one did
 * not -- the inconsistency that made the bug easy to miss. The sibling had no callers and
 * was deleted 2026-10-21.)
 */
function buildTypedIds(array $uniquenames, string $db_path, string $assembly = '', string $gene_set = ''): array {
    $result = array_fill_keys($uniquenames, null);
    if (empty($uniquenames) || !file_exists($db_path)) {
        return $result;
    }
    $placeholders = implode(',', array_fill(0, count($uniquenames), '?'));
    $scoped = ($assembly !== '' || $gene_set !== '');

    $sql = "SELECT f.feature_uniquename, f.feature_type FROM feature f";
    if ($scoped) {
        $sql .= " JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id"
              . " JOIN genome   g  ON g.genome_id    = gs.genome_id";
    }
    $sql .= " WHERE f.feature_uniquename IN ($placeholders)";

    $params = array_values($uniquenames);
    if ($gene_set !== '') {
        $sql .= " AND gs.gene_set_name = ?";
        $params[] = $gene_set;
    }
    if ($assembly !== '') {
        // Accept either the accession or the human-readable genome name, as the rest of
        // the app does when it resolves an assembly.
        $sql .= " AND (g.genome_accession = ? OR g.genome_name = ?)";
        $params[] = $assembly;
        $params[] = $assembly;
    }

    try {
        $dbh  = getDbConnection($db_path);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['feature_uniquename']] = $row['feature_type'];
        }
    } catch (Exception $e) {
        // Return nulls on error — callers fall back to try-all behavior
    }
    return $result;
}

/**
 * Expand gene uniquenames to their extractable descendants (mRNA, CDS, protein).
 *
 * Moopmart queries at gene level but FASTAs are indexed by child IDs.
 * Two-level walk: genes → mRNA children → CDS/protein grandchildren.
 * Features already at an extractable type pass through unchanged.
 *
 * @param array  $gene_uniquenames  Gene-level uniquenames to expand
 * @param int    $gene_set_id       Scope expansion to this gene set
 * @param string $db_path           Path to organism.sqlite
 * @return array [$uniquename => $feature_type]
 */
/**
 * Expand selected features to every related feature that has a sequence.
 *
 * extractSequencesForAllTypes() buckets each id by ITS OWN feature_type, so handing it a
 * list of mRNAs yields mRNA sequences and nothing else. The search-results FASTA button
 * did exactly that, which is why it returned only transcripts while the gene page and
 * Retrieve Sequences return all three.
 *
 * ---------------------------------------------------------------------------
 * DELIBERATELY FEATURE-TYPE AGNOSTIC
 *
 * This walks the parent/child graph and never names a feature type. The rule is simply:
 *
 *     take every ANCESTOR and every DESCENDANT of what the user selected.
 *
 * On today's gene -> mRNA -> CDS -> protein hierarchy that produces exactly the right
 * answer, and it keeps producing it if the hierarchy ever changes shape:
 *
 *   selected a gene     -> descendants = all isoforms and their CDS/proteins
 *   selected one mRNA   -> descendants = that isoform's CDS/protein; ancestors = its gene
 *   selected a protein  -> ancestors  = its CDS, mRNA and gene
 *
 * Note what this does NOT do: it never takes the descendants OF an ancestor. That is what
 * keeps a single selected isoform from dragging in its sibling isoforms, and it falls out
 * of the shape of the walk rather than from a special case.
 *
 * Types that have no per-feature FASTA (gene, exon, ...) cost nothing to include —
 * extractSequencesForAllTypes() drops them via _fasta_key_for_type(). So this function
 * does not need to know which types those are either.
 *
 * Scoped by assembly / gene set when given: a feature_uniquename is unique only within a
 * gene set (see buildTypedIds()). The walk itself stays inside one gene set naturally,
 * because parent and child rows share a gene_set_id.
 *
 * Both walks are single recursive CTEs, the same shape getChildren() in
 * lib/parent_functions.php already used — one query per direction rather than one per
 * level of the hierarchy. `UNION` (not `UNION ALL`) makes them cycle-safe: a feature_id
 * already in the working set is never re-added, so a malformed parent chain cannot spin.
 *
 * $gene_set_ids is the ACCESS FILTER, and any caller that has one must pass it. The parent
 * page restricts its feature walk to the gene sets the viewer is allowed to see; expanding
 * without that restriction would surface features from a gene set the user has no access
 * to. It is applied to the rows the walks return, not merely to the seed.
 *
 * @param array  $uniquenames  the selected features
 * @param string $db_path      organism.sqlite
 * @param string $assembly     optional scope — accession or genome name
 * @param string $gene_set     optional scope — gene set name
 * @param array  $gene_set_ids optional ACCESS filter — accessible gene_set_id values
 * @param ?array $family       optional, filled with every row both walks returned, feature_id =>
 *                             [feature_uniquename, feature_type, feature_name, parent_feature_id]:
 *                             the family the header context is worked out from
 *                             (moop_anchors_from_family()) without walking up again
 * @return array uniquename => feature_type, ready for extractSequencesForAllTypes()
 */
function expandFeaturesToAllSequenceTypes(
    array  $uniquenames,
    string $db_path,
    string $assembly = '',
    string $gene_set = '',
    array  $gene_set_ids = [],
    ?array &$family = null
): array {
    if (empty($uniquenames) || !file_exists($db_path)) {
        return [];
    }

    try {
        $dbh = getDbConnection($db_path);

        // --- Seed: the selected features, scoped to this assembly / gene set ----------
        $ph     = implode(',', array_fill(0, count($uniquenames), '?'));
        $sql    = "SELECT f.feature_id FROM feature f";
        $params = array_values($uniquenames);
        if ($assembly !== '' || $gene_set !== '') {
            $sql .= " JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                      JOIN genome   g  ON g.genome_id    = gs.genome_id";
        }
        $sql .= " WHERE f.feature_uniquename IN ($ph)";
        if ($gene_set !== '') { $sql .= " AND gs.gene_set_name = ?"; $params[] = $gene_set; }
        if ($assembly !== '') {
            $sql .= " AND (g.genome_accession = ? OR g.genome_name = ?)";
            $params[] = $assembly; $params[] = $assembly;
        }
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $seed_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$seed_ids) {
            return buildTypedIds($uniquenames, $db_path, $assembly, $gene_set);
        }

        // Access filter, applied to every row the walks return as well as the seed.
        $gs_clause = '';
        $gs_params = [];
        if (!empty($gene_set_ids)) {
            $gsph      = implode(',', array_fill(0, count($gene_set_ids), '?'));
            $gs_clause = " AND f.gene_set_id IN ($gsph)";
            $gs_params = array_values($gene_set_ids);
        }

        $seed_ph = implode(',', array_fill(0, count($seed_ids), '?'));
        // The family's links and names only when asked for: every other caller keeps the query
        // it had, so nothing else depends on those columns.
        $cols = $family !== null ? 'f.feature_id, f.feature_uniquename, f.feature_type, f.feature_name, f.parent_feature_id'
                                 : 'f.feature_uniquename, f.feature_type';

        // --- Down: every descendant, in ONE recursive query ---------------------------
        $descendants = "WITH RECURSIVE d(feature_id) AS (
                            SELECT feature_id FROM feature WHERE feature_id IN ($seed_ph)
                            UNION
                            SELECT f.feature_id FROM feature f JOIN d ON f.parent_feature_id = d.feature_id
                        )
                        SELECT $cols
                          FROM feature f JOIN d ON d.feature_id = f.feature_id
                         WHERE 1=1 $gs_clause";

        // --- Up: every ancestor of the SEED rows only ---------------------------------
        // Climbing from the seed (not from the descendants) is what keeps sibling
        // branches out: we never descend again from anything found on the way up.
        $ancestors = "WITH RECURSIVE a(feature_id, parent_feature_id) AS (
                          SELECT feature_id, parent_feature_id FROM feature WHERE feature_id IN ($seed_ph)
                          UNION
                          SELECT f.feature_id, f.parent_feature_id
                            FROM feature f JOIN a ON f.feature_id = a.parent_feature_id
                      )
                      SELECT $cols
                        FROM feature f JOIN a ON a.feature_id = f.feature_id
                       WHERE 1=1 $gs_clause";

        $typed = [];
        foreach ([$descendants, $ancestors] as $query) {
            $s = $dbh->prepare($query);
            $s->execute(array_merge(array_values($seed_ids), $gs_params));
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $typed[$row['feature_uniquename']] = $row['feature_type'];
                if ($family !== null) $family[$row['feature_id']] = $row;
            }
        }

        return $typed ?: buildTypedIds($uniquenames, $db_path, $assembly, $gene_set);

    } catch (Exception $e) {
        // Fall back to the unexpanded behaviour rather than returning nothing.
        return buildTypedIds($uniquenames, $db_path, $assembly, $gene_set);
    }
}


/**
 * Extract sequences using type-based FASTA routing.
 *
 * Each ID is routed to exactly one FASTA file based on its feature_type:
 *   mRNA/transcript → transcript.nt.fa
 *   CDS             → cds.nt.fa
 *   protein         → protein.aa.fa
 *   gene/exon/etc.  → skipped (no per-feature FASTA for these types)
 *
 * IDs with a null type (not found in DB) fall back to the old behavior:
 * they are tried against every applicable FASTA.
 *
 * @param string $assembly_dir       Path to the gene-set directory
 * @param array  $typed_ids          [$uniquename => $feature_type|null]
 * @param array  $sequence_types     Sequence type config from ConfigManager::getSequenceTypes()
 * @param string $organism           Passed through to blastdbcmd helper
 * @param string $assembly           Passed through to blastdbcmd helper
 * @param array  $requests           parseFeatureIds()['requests'] -- what the user typed,
 *                                   ranges included; empty = every typed ID, whole
 * @param array  $parent_to_children ID => all its descendants, so a request reaches them
 * @return array ['success' => bool, 'content' => [type => fasta_string], 'errors' => []]
 */
function extractSequencesForAllTypes(
    string $assembly_dir,
    array  $typed_ids,
    array  $sequence_types,
    string $organism = '',
    string $assembly = '',
    array  $requests = [],
    array  $parent_to_children = []
): array {
    $displayed_content = [];
    $tool_errors       = [];
    $system_errors     = [];

    // Sort IDs into per-FASTA buckets; unknowns collected separately
    $buckets = [];
    $untyped = [];
    foreach ($typed_ids as $uniquename => $feature_type) {
        if ($feature_type === null) {
            $untyped[] = $uniquename;
            continue;
        }
        $key = _fasta_key_for_type($feature_type);
        if ($key === null) continue; // gene, exon, etc. — no per-feature FASTA
        $buckets[$key][] = $uniquename;
    }

    $run_extract = function (string $seq_type, string $fasta_file, array $ids)
        use (&$displayed_content, &$tool_errors, &$system_errors, $organism, $assembly, $requests, $parent_to_children)
    {
        $result = extractSequencesFromBlastDb($fasta_file, $ids, $organism, $assembly, $requests, $parent_to_children);
        if ($result['success']) {
            $lines   = array_filter(explode("\n", $result['content']), fn($l) => trim($l) !== '');
            $content = implode("\n", $lines);
            if ($content !== '') {
                $displayed_content[$seq_type] = isset($displayed_content[$seq_type])
                    ? $displayed_content[$seq_type] . "\n" . $content
                    : $content;
            }
        } elseif (!empty($result['error'])) {
            $tool_errors[] = $result['error'];
            if (!empty($result['system_error'])) $system_errors[] = $result['error'] . ' (' . basename($fasta_file) . ')';
        }
    };

    // Typed IDs → single FASTA each
    foreach ($sequence_types as $seq_type => $config) {
        if ($seq_type === 'genome') continue;
        if (empty($buckets[$seq_type])) continue;
        $files = glob("$assembly_dir/{$config['pattern']}");
        if (!empty($files)) {
            $run_extract($seq_type, $files[0], $buckets[$seq_type]);
        }
    }

    // Untyped IDs → try every FASTA (safe fallback for IDs not in DB)
    if (!empty($untyped)) {
        foreach ($sequence_types as $seq_type => $config) {
            if ($seq_type === 'genome') continue;
            $files = glob("$assembly_dir/{$config['pattern']}");
            if (!empty($files)) {
                $run_extract($seq_type, $files[0], $untyped);
            }
        }
    }

    $errors = empty($displayed_content) ? array_values(array_unique($tool_errors)) : [];   // one per FASTA tried
    return [
        'success' => !empty($displayed_content),
        'content' => $displayed_content,
        'errors'  => $errors,
        // Server faults only (missing BLAST database, extraction tool failed) -- what belongs in
        // the admin error log. 'errors' also holds "not found" for IDs the visitor typed, which
        // is their input, not a fault; logging it filled the log with typos (2026-10-29).
        'system_errors' => array_values(array_unique($system_errors)),
    ];
}

/**
 * gene=, gene_name=, transcript= in the headers of extracted sequences -- the header standard
 * (moop_header_context()), so a protein or CDS says which gene and isoform it is once it has
 * left the page. One batched lookup for every ID in every type; the genomic records already
 * carry it. Only the headers change: the ID stays first, the source's description is kept.
 * If the lookup fails the sequences are returned as they were -- context is never worth
 * losing the sequences over.
 *
 * @param array $content     type => FASTA, as extractSequencesForAllTypes() returns it
 * @param array $gene_set_ids the scope -- a uniquename is unique only within one gene set
 */
function moop_content_with_context(array $content, string $db_path, array $gene_set_ids): array {
    require_once __DIR__ . '/genomic_regions.php';
    $ids = [];
    foreach ($content as $type => $fasta) {
        if ($type === 'genome' || !preg_match_all('/^>(\S+)/m', $fasta, $m)) continue;
        foreach ($m[1] as $id) $ids[preg_replace('/:\d+-\d+$/', '', $id)] = true;
    }
    if (!$ids || !is_file($db_path)) return $content;
    try {
        $anchors = moop_genomic_anchors(array_keys($ids), $db_path, $gene_set_ids);
    } catch (Exception $e) {
        return $content;
    }
    foreach ($content as $type => $fasta) {
        if ($type !== 'genome') $content[$type] = moop_fasta_with_context($fasta, $anchors);
    }
    return $content;
}

/** IDs per batch in moop_stream_sequences(). 500 genes of Myotis protein+isoforms ≈ 3 MB. */
const MOOP_STREAM_BATCH = 500;

/**
 * Write ONE sequence type for any number of features to $handle, a batch at a time.
 *
 * The bulk path. extractSequencesForAllTypes() builds every type for every id as strings in
 * memory, which is right for a page showing a few genes and wrong for a whole gene set: the
 * Data Exporter's protein export did exactly that and peaked at 969 MB on Myotis (limit 128M,
 * so a 502). Here each batch is expanded (gene -> its isoforms/CDS/proteins, scoped to the
 * gene set), extracted for $type only, written, and freed before the next.
 *
 * A gene and all its descendants are expanded together, so an isoform is never split across
 * a batch boundary. An id already written (given twice, or reached from two inputs) is not
 * written again. Within a batch the order is extractSequencesForAllTypes()'s, so a list that
 * fits in one batch comes out exactly as before.
 *
 * @param resource $handle
 * @param string   $gene_set_dir  directory holding the gene set's FASTA files
 * @param string[] $ids           feature uniquenames (genes or anything below them)
 * @param string   $type          sequence_types key: protein | transcript | cds
 * @param array    $opts          db_path, sequence_types (required); assembly, gene_set (scope by
 *                                name), organism, gene_set_ids (the ACCESS filter -- pass it), batch,
 *                                context (true = gene=/gene_name=/transcript= headers, the standard;
 *                                worked out from the expansion's own walk, no second lookup)
 * @return array{written:int, has_file:bool}  has_file false = the gene set has no $type FASTA
 */
function moop_stream_sequences($handle, string $gene_set_dir, array $ids, string $type, array $opts): array {
    $config = $opts['sequence_types'][$type] ?? null;
    if (!$config || !glob("$gene_set_dir/{$config['pattern']}")) {
        return ['written' => 0, 'has_file' => false];
    }
    $one_type = [$type => $config];
    $seen     = [];
    $written  = 0;

    foreach (array_chunk(array_values(array_unique($ids)), $opts['batch'] ?? MOOP_STREAM_BATCH) as $chunk) {
        // With context, the expansion also hands back the family it walked, and the headers are
        // worked out from that in memory (moop_anchors_from_family()). A separate lookup per
        // sequence cost +22-34% of the export's time (Myotis protein 3.4 -> 4.5 s).
        $family = !empty($opts['context']) ? [] : null;
        $typed = array_diff_key(
            expandFeaturesToAllSequenceTypes($chunk, $opts['db_path'], $opts['assembly'] ?? '', $opts['gene_set'] ?? '', $opts['gene_set_ids'] ?? [], $family),
            $seen
        );
        if (!$typed) continue;
        $seen += array_fill_keys(array_keys($typed), true);

        $fasta = extractSequencesForAllTypes($gene_set_dir, $typed, $one_type, $opts['organism'] ?? '', $opts['assembly'] ?? '')['content'][$type] ?? '';
        if ($fasta === '') continue;
        if ($family) {
            // Only the IDs that are this file's headers (a protein file: about a quarter of the family)
            require_once __DIR__ . '/genomic_regions.php';
            $in_file = array_keys(array_filter($typed, fn($t) => $t === null || _fasta_key_for_type((string)$t) === $type));
            $fasta   = moop_fasta_with_context($fasta, moop_anchors_from_family($in_file, $family));
        }
        fwrite($handle, $fasta . "\n");
        $written += substr_count($fasta, "\n>") + 1;
        unset($typed, $fasta);
    }
    return ['written' => $written, 'has_file' => true];
}

/**
 * Every descendant of each typed ID: Sequence Retrieval's expansion.
 *
 * A gene reaches its transcripts, CDS and proteins, and a range typed on the gene applies to
 * all of them; a protein reaches nothing. (Unlike expandFeaturesToAllSequenceTypes(), which
 * the search-results paths use, this never walks UP.) The page and its downloads both call
 * this, so a download holds what the page shows. Two small queries per ID: 0.2 s for 2,000
 * Myotis genes, 2.2 s for 20,000.
 *
 * @param array $gene_set_ids the ACCESS filter, and the scope: a uniquename is unique only
 *                            within one gene set, and an organism may carry several
 * @return array<string, list<string>> typed ID => its descendants' uniquenames (IDs with none omitted)
 */
function moop_retrieval_children(array $ids, string $db_path, array $gene_set_ids): array {
    $map = [];
    foreach ($ids as $id) {
        $feature = getFeatureByUniquename($id, $db_path, $gene_set_ids);
        if (empty($feature)) continue;
        $children = array_column(getChildren($feature['feature_id'], $db_path, $gene_set_ids), 'feature_uniquename');
        if ($children) $map[$id] = $children;
    }
    return $map;
}

/**
 * Sequence Retrieval's download: ONE type for any number of typed IDs, a batch at a time.
 *
 * Each batch is exactly what the page does for a list that size -- moop_retrieval_children(),
 * buildTypedIds(), extractSequencesForAllTypes() with the typed ranges, or for 'genome'
 * moop_retrieve_genomic() with its flanks -- so a list that fits in one batch downloads
 * byte-for-byte what the page shows (plus a final newline). The download used to re-run the
 * page's whole extraction, every type plus the genome, to send one of them.
 *
 * A record written in an earlier batch is not written again (an ID typed twice, or a
 * transcript typed as well as its gene).
 *
 * @param resource $handle
 * @param array    $requests  parseFeatureIds()['requests']: id + optional start/end
 * @param string   $type      protein | transcript | cds | genome
 * @param array    $ctx       db_path, gene_set_ids, gene_set_dir, assembly_dir, sequence_types;
 *                            assembly, gene_set, organism, upstream, downstream, batch
 * @return int records written
 */
function moop_retrieval_stream($handle, array $requests, string $type, array $ctx): int {
    $written = 0;
    $seen    = [];
    // One record out, unless it was already written (by its header line)
    $write = function (string $record) use ($handle, &$seen, &$written) {
        $record = rtrim($record, "\n");
        $header = strtok($record, "\n");
        if (isset($seen[$header])) return;
        $seen[$header] = true;
        fwrite($handle, $record . "\n");
        $written++;
    };
    foreach (array_chunk($requests, $ctx['batch'] ?? MOOP_STREAM_BATCH) as $batch) {
        $ids = array_values(array_unique(array_column($batch, 'id')));
        try {
            $children = moop_retrieval_children($ids, $ctx['db_path'], $ctx['gene_set_ids']);
            $anchors  = $type === 'genome' ? moop_genomic_anchors($ids, $ctx['db_path'], $ctx['gene_set_ids']) : [];
        } catch (Exception $e) {
            // Without the database, extract the typed IDs as they are -- as the page does.
            $children = [];
            $anchors  = [];
        }

        if ($type === 'genome') {
            // Each region is written as it is cut: a region runs to 500 kb, so a batch of them
            // collected first could reach ~300 MB.
            moop_retrieve_genomic($batch, $ctx['gene_set_dir'], $ctx['assembly_dir'],
                                  $ctx['upstream'] ?? 0, $ctx['downstream'] ?? 0, $anchors, $write);
            continue;
        }
        $all   = array_values(array_unique(array_merge($ids, ...array_values($children))));
        $typed = buildTypedIds($all, $ctx['db_path'], $ctx['assembly'] ?? '', $ctx['gene_set'] ?? '');
        $fasta = extractSequencesForAllTypes($ctx['gene_set_dir'], $typed, [$type => $ctx['sequence_types'][$type]],
                                             $ctx['organism'] ?? '', $ctx['assembly'] ?? '', $batch, $children)['content'][$type] ?? '';
        // The same headers the page shows (gene=, transcript=)
        $fasta = moop_content_with_context([$type => $fasta], $ctx['db_path'], $ctx['gene_set_ids'])[$type];
        // One record per ">" line, each written as it is found (no second copy of the batch)
        $at  = 0;
        $len = strlen($fasta);
        while ($at < $len) {
            $next = strpos($fasta, "\n>", $at);
            $end  = $next === false ? $len : $next;
            $write(substr($fasta, $at, $end - $at));
            $at = $end + 1;
        }
        unset($fasta, $children, $anchors);
    }
    return $written;
}

/**
 * Sequence Retrieval and Retrieve Selected SHOW at most this many typed IDs; a longer list is
 * offered as a download only (any length: nothing is capped, it is just not drawn).
 * Measured 2026-10-27 on Myotis: 100 gene IDs made a 6 MB page, 200 a 15.7 MB page, 300 a 502.
 */
const MOOP_RETRIEVAL_SHOW_MAX_IDS = 50;

/**
 * ...and at most this much sequence, estimated before any is read
 * (moop_retrieval_estimate_bytes()). Fifty genes can still be huge: each genomic region runs to
 * 500 kb, plus flanks of up to 100 kb a side.
 *
 * Measured 2026-10-27. Typical genes: 50 Myotis genes estimate 2.27 MB and render a 2.3 MB
 * page (the estimate tracks the page closely), so 2 MB -- the first guess -- would have sent
 * almost every 50-gene list to download. Browser (headless Chrome): ~170 ms per MB to load
 * (2.3 MB 0.44 s, 4.3 MB 0.77 s, 8.3 MB 1.4 s, 16 MB 2.8 s), scrolling flat. Server: a page
 * costs ~5.5x its size in PHP memory (15.7 MB worked, ~23 MB hit the 128M limit), so 5 MB is
 * ~28 MB -- comfortably clear of the limit with other users on the server.
 */
const MOOP_RETRIEVAL_SHOW_MAX_BYTES = 5000000;

/**
 * Which of these IDs exist in the gene set: one IN (...) query per 5,000 (SQLite's limit on
 * bound values is 32,766), never one query per ID.
 *
 * @param array $gene_set_ids the scope; empty = the whole database
 * @return array<string, true>
 */
function moop_retrieval_found(array $ids, string $db_path, array $gene_set_ids): array {
    $found = [];
    if (!$ids || !is_file($db_path)) return $found;
    $gs = $gene_set_ids ? ' AND gene_set_id IN (' . implode(',', array_fill(0, count($gene_set_ids), '?')) . ')' : '';
    foreach (array_chunk(array_values(array_unique($ids)), 5000) as $chunk) {
        $sql = 'SELECT DISTINCT feature_uniquename FROM feature WHERE feature_uniquename IN ('
             . implode(',', array_fill(0, count($chunk), '?')) . ')' . $gs;
        foreach (fetchData($sql, $db_path, array_merge($chunk, array_values($gene_set_ids))) as $row) {
            $found[$row['feature_uniquename']] = true;
        }
    }
    return $found;
}

/**
 * Roughly how many bases/residues Sequence Retrieval would put on the page, without reading
 * any sequence: lengths from each FASTA's .fai and from the coordinate files, a range counted
 * as its own length. Close enough to decide "show it" or "download it"; it ignores headers
 * and line breaks.
 *
 * @param array $requests  parseFeatureIds()['requests']
 * @param array $children  moop_retrieval_children()
 * @param array $genomic   anchors (moop_genomic_anchors()), upstream, downstream, has_genome
 */
function moop_retrieval_estimate_bytes(array $requests, array $children, string $gene_set_dir, array $sequence_types, array $genomic): int {
    require_once __DIR__ . '/fasta_index.php';
    $ids = array_values(array_unique(array_merge(array_column($requests, 'id'), ...array_values($children))));
    $len = [];   // id => [length, ...], one per FASTA it is in
    foreach ($sequence_types as $type => $config) {
        if ($type === 'genome') continue;
        $fasta = glob("$gene_set_dir/{$config['pattern']}")[0] ?? '';
        if ($fasta === '' || !moop_fasta_index_available($fasta)) continue;
        foreach (moop_fai_lookup("$fasta.fai", $ids) as $id => $e) $len[$id][] = $e['len'];
    }
    $anchors = $genomic['anchors'] ?? [];
    $loci = !empty($genomic['has_genome'])
        ? moop_feature_loci($gene_set_dir, array_map(fn($r) => $anchors[$r['id']]['locus'] ?? $r['id'], $requests))
        : [];
    $flanks = (int)($genomic['upstream'] ?? 0) + (int)($genomic['downstream'] ?? 0);

    $total = 0;
    foreach ($requests as $r) {
        $span = $r['start'] === null ? PHP_INT_MAX : $r['end'] - $r['start'] + 1;
        foreach (array_merge([$r['id']], $children[$r['id']] ?? []) as $id) {
            foreach ($len[$id] ?? [] as $l) $total += min($l, $span);
        }
        $locus = $loci[$anchors[$r['id']]['locus'] ?? $r['id']] ?? null;
        if ($locus) $total += min($locus['end'] - $locus['start'] + 1, $span, MOOP_MAX_REGION_BP) + $flanks;
    }
    return $total;
}

/**
 * Send the headers for a streamed sequence download and return a handle to write it to.
 *
 * sendFileDownload()'s streaming sibling: no Content-Length (the size is not known until the
 * last batch is written) and the output buffers cleared, so each batch leaves as it is written
 * instead of piling up in PHP. Call only once nothing can fail any more: after the first byte
 * the HTTP status cannot change.
 *
 * @return resource
 */
function beginSequenceDownload(string $sequence_type, array $scope = []) {
    require_once __DIR__ . '/download_filename.php';
    $filename = moop_download_filename('sequences', array_merge($scope, [$sequence_type]), 'fasta');
    while (ob_get_level()) ob_end_clean();
    set_time_limit(0);
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Cache-Control: no-cache, no-store');
    return fopen('php://output', 'w');
}

/**
 * Format extracted sequences for display component
 * 
 * Converts extracted content into format expected by sequences_display.php
 * 
 * @param array $displayed_content - Extracted sequences by type
 * @param array $sequence_types - Type configurations (from site_config)
 * @return array - Formatted for sequences_display.php inclusion
 */
function formatSequenceResults($displayed_content, $sequence_types) {
    $available_sequences = [];
    
    foreach ($displayed_content as $seq_type => $content) {
        
        // Parse FASTA content into individual sequences by ID
        $sequences = [];
        if (!empty($content)) {
            $current_id = null;
            $current_seq = [];
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (strpos($line, '>') === 0) {
                    // Header line
                    if (!is_null($current_id)) {
                        // Store previous sequence with full FASTA format (including >)
                        $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
                    }
                    // Extract ID from header (remove leading '>')
                    $current_id = substr($line, 1);
                    $current_seq = [];
                } else if (!empty($line)) {
                    // Sequence line (skip empty lines)
                    $current_seq[] = $line;
                }
            }
            
            // Store last sequence with full FASTA format
            if (!is_null($current_id)) {
                $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
            }
        }
        
        foreach ($sequences as $id => $seq_content) {
            $first_line = explode("\n", $seq_content)[0];
        }
        
        $available_sequences[$seq_type] = [
            'label' => $sequence_types[$seq_type]['label'] ?? ucfirst($seq_type),
            'sequences' => $sequences
        ];
    }
    
    return $available_sequences;
}

/**
 * Send file download response and exit
 * 
 * Sets appropriate headers and outputs file content
 * Should be called before any HTML output
 * 
 * @param string $content - File content to download
 * @param string $sequence_type - Type of sequence (for filename)
 * @param string $file_format - Format (fasta or txt)
 */
function sendFileDownload($content, $sequence_type, $file_format = 'fasta', array $scope = []) {
    $ext = ($file_format === 'txt') ? 'txt' : 'fasta';
    // $scope carries organism/assembly/gene set. It is OPTIONAL so no caller breaks, but a
    // caller that omits it produces sequences_pep_2026-07-30.fasta -- which does not say
    // which organism, so two organisms collide in one download folder. Pass what you have.
    require_once __DIR__ . '/download_filename.php';
    $filename = moop_download_filename('sequences', array_merge($scope, [$sequence_type]), $ext);
    
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

/**
 * Build organism-filtered list of accessible assembly sources
 * 
 * Filters nested sources array by organism list
 * 
 * @param array $sources_by_group - Nested array from getAccessibleGeneSets()
 * @param array $filter_organisms - Optional organism filter list
 * @return array - Nested array [group][organism][...assemblies]
 */
function buildFilteredSourcesList($sources_by_group, $filter_organisms = []) {
    $filtered = [];
    
    foreach ($sources_by_group as $group_name => $organisms) {
        foreach ($organisms as $organism => $assemblies) {
            // Skip if organism filter is set and this organism is not in it
            if (!empty($filter_organisms) && !in_array($organism, $filter_organisms)) {
                continue;
            }
            
            if (!isset($filtered[$group_name])) {
                $filtered[$group_name] = [];
            }
            $filtered[$group_name][$organism] = $assemblies;
        }
    }
    
    return $filtered;
}

/**
 * Flatten nested sources array for sequential processing
 * 
 * Converts nested [group][organism][...sources] structure to flat list
 * Useful for iterating all sources without nested loops
 * 
 * @param array $sources_by_group - Nested array from getAccessibleGeneSets()
 * @return array - Flat list of all sources
 */
function flattenSourcesList($sources_by_group) {
    $accessible_sources = [];
    
    foreach ($sources_by_group as $group => $organisms) {
        foreach ($organisms as $org => $assemblies) {
            $accessible_sources = array_merge($accessible_sources, $assemblies);
        }
    }
    
    return $accessible_sources;
}

/**
 * Get available sequence types from all accessible sources
 * 
 * Scans assembly directories to determine which sequence types are available
 * Useful for populating UI dropdowns/display options
 * 
 * @param array $accessible_sources - Flattened list of sources
 * @param array $sequence_types - Type configurations (from site_config)
 * @return array - [type => label] for types that have available files
 */
function getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types) {
    $available_types = [];
    
    foreach ($accessible_sources as $source) {
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob($source['path'] . "/{$config['pattern']}");
            if (!empty($files)) {
                $available_types[$seq_type] = $config['label'];
            }
        }
    }
    
    return $available_types;
}

/**
 * Handle sequence download request
 * 
 * Checks for download flag and sends file if conditions are met.
 * Works with both array-based sequences (id => FASTA record)
 * and string-based sequences (from extractSequencesForAllTypes).
 * 
 * @param bool $download_flag - Whether download was requested
 * @param string $sequence_type - The sequence type to download
 * @param array|string $sequence_data - Either array of sequences or a string
 * @return bool - True if download was sent and script exited, false otherwise
 */
function handleSequenceDownload($download_flag, $sequence_type, $sequence_data, array $scope = []) {
    if (!$download_flag || empty($sequence_type)) {
        return false;
    }
    
    // Handle both formats: array (id => FASTA record) and string (from extractSequencesForAllTypes)
    $fasta_content = '';
    if (is_array($sequence_data)) {
        // Array format: feature_id => content
        if (!empty($sequence_data)) {
            $fasta_content = implode("\n", $sequence_data);
        }
    } else if (is_string($sequence_data)) {
        // String format: already combined FASTA content
        $fasta_content = $sequence_data;
    }
    
    if (!empty($fasta_content)) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        sendFileDownload($fasta_content, $sequence_type, $file_format, $scope);
        exit;
    }
    
    return false;
}

/**
 * Determine selected source (organism/assembly) based on URL/POST parameters
 * 
 * Selection priority (highest to lowest):
 * 1. Explicit assembly parameter
 * 2. Explicit organism parameter
 * 3. Group parameter (select first organism from group)
 * 4. Organisms filter list (select first organism)
 * 
 * @param array $context - Context parameters [organism, assembly, group, display_name, context_page]
 * @param array $filter_organisms - Pre-filtered list of organisms (from organisms[] or group)
 * @param array $accessible_sources - Flat list of all accessible sources
 * @param string $selected_organism - Optional pre-selected organism (input/output)
 * @param string $selected_assembly_accession - Optional pre-selected assembly (input/output)
 * @return array - ['selected_source' => 'org|assembly', 'selected_organism' => 'org', 'selected_assembly_accession' => 'accession', 'selected_assembly_name' => 'name']
 */
function determineSelectedSource($context, $filter_organisms, $accessible_sources, $selected_organism = '', $selected_assembly_accession = '') {
    $result = [
        'selected_source' => '',
        'selected_organism' => $selected_organism,
        'selected_assembly_accession' => $selected_assembly_accession,
        'selected_assembly_name' => ''
    ];
    
    // Case 1: Both organism and assembly explicitly specified — find first matching gene_set
    if (!empty($selected_organism) && !empty($selected_assembly_accession)) {
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $selected_organism &&
                ($source['assembly'] === $selected_assembly_accession ||
                 $source['genome_accession'] === $selected_assembly_accession)) {
                $result['selected_source'] = $selected_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                return $result;
            }
        }
        // Fallback if source not found (no gene_set info available)
        $result['selected_source'] = $selected_organism . '|' . $selected_assembly_accession . '|';
        return $result;
    }

    // Case 2: Only organism specified (select its first assembly + gene_set)
    if (!empty($selected_organism)) {
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $selected_organism) {
                $result['selected_source'] = $selected_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }

    // Case 3: Group specified (select first organism from group, then its first assembly + gene_set)
    if (!empty($context['group']) && !empty($filter_organisms)) {
        $first_organism = reset($filter_organisms);
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $first_organism && in_array($context['group'], $source['groups'] ?? [])) {
                $result['selected_source'] = $first_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                $result['selected_organism'] = $first_organism;
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }

    // Case 4: Organisms filter list specified (select first organism, then its first assembly + gene_set)
    if (!empty($filter_organisms)) {
        $first_organism = reset($filter_organisms);
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $first_organism) {
                $result['selected_source'] = $first_organism . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '');
                $result['selected_organism'] = $first_organism;
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }
    
    // No selection could be determined
    return $result;
}

?>
