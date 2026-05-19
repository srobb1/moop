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
 *   feature_types?:        string[]  e.g. ['gene','pseudogene']; empty = all types
 *   feature_id?:           string    Exact match on feature_uniquename
 *   gene_name?:            string    LIKE match on feature_name
 *   gene_description?:     string    LIKE match on feature_description
 *   annotation_source?:    string    Require annotation from this source name
 *   annotation_accession?: string    Require this exact accession (e.g. 'GO:0006351')
 *   annotation_keyword?:   string    LIKE match on annotation_description
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
    if (!empty($filters['feature_id'])) {
        // Match on the gene's own uniquename, OR on any child (transcript) uniquename —
        // so typing either a gene ID or a transcript ID finds the same gene row.
        $where[]  = '(f.feature_uniquename = ? OR EXISTS (
                          SELECT 1 FROM feature child
                          WHERE child.parent_feature_id = f.feature_id
                            AND child.feature_uniquename = ?))';
        $params[] = $filters['feature_id'];
        $params[] = $filters['feature_id'];
    }
    if (!empty($filters['gene_name'])) {
        $where[]  = 'f.feature_name LIKE ?';
        $params[] = '%' . $filters['gene_name'] . '%';
    }
    if (!empty($filters['gene_description'])) {
        $where[]  = 'f.feature_description LIKE ?';
        $params[] = '%' . $filters['gene_description'] . '%';
    }

    // Annotation filter via EXISTS on child features — annotations in the DB are
    // stored on mRNA/transcript features (direct children of genes via parent_feature_id),
    // not on gene features themselves.
    $ann_where  = [];
    $ann_params = [];
    if (!empty($filters['annotation_source'])) {
        $ann_where[]  = 'ans.annotation_source_name = ?';
        $ann_params[] = $filters['annotation_source'];
    }
    if (!empty($filters['annotation_accession'])) {
        $ann_where[]  = 'a.annotation_accession = ?';
        $ann_params[] = $filters['annotation_accession'];
    }
    if (!empty($filters['annotation_keyword'])) {
        $ann_where[]  = 'a.annotation_description LIKE ?';
        $ann_params[] = '%' . $filters['annotation_keyword'] . '%';
    }
    if (!empty($ann_where)) {
        $ann_clause = implode(' AND ', $ann_where);
        $where[] = "EXISTS (
            SELECT 1
            FROM feature           child
            JOIN feature_annotation fa2 ON fa2.feature_id         = child.feature_id
            JOIN annotation         a   ON fa2.annotation_id      = a.annotation_id
            JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
            WHERE child.parent_feature_id = f.feature_id
              AND $ann_clause
        )";
        array_push($params, ...$ann_params);
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

    if (!empty($filters['feature_id'])) {
        $where[]  = '(f.feature_uniquename = ? OR EXISTS (
                          SELECT 1 FROM feature child
                          WHERE child.parent_feature_id = f.feature_id
                            AND child.feature_uniquename = ?))';
        $params[] = $filters['feature_id'];
        $params[] = $filters['feature_id'];
    }
    if (!empty($filters['gene_name'])) {
        $where[]  = 'f.feature_name LIKE ?';
        $params[] = '%' . $filters['gene_name'] . '%';
    }
    if (!empty($filters['gene_description'])) {
        $where[]  = 'f.feature_description LIKE ?';
        $params[] = '%' . $filters['gene_description'] . '%';
    }

    $ann_where  = [];
    $ann_params = [];
    if (!empty($filters['annotation_source'])) {
        $ann_where[]  = 'ans.annotation_source_name = ?';
        $ann_params[] = $filters['annotation_source'];
    }
    if (!empty($filters['annotation_accession'])) {
        $ann_where[]  = 'a.annotation_accession = ?';
        $ann_params[] = $filters['annotation_accession'];
    }
    if (!empty($filters['annotation_keyword'])) {
        $ann_where[]  = 'a.annotation_description LIKE ?';
        $ann_params[] = '%' . $filters['annotation_keyword'] . '%';
    }
    if (!empty($ann_where)) {
        $ann_clause = implode(' AND ', $ann_where);
        $where[] = "EXISTS (
            SELECT 1
            FROM feature           child
            JOIN feature_annotation fa2 ON fa2.feature_id         = child.feature_id
            JOIN annotation         a   ON fa2.annotation_id      = a.annotation_id
            JOIN annotation_source  ans ON a.annotation_source_id = ans.annotation_source_id
            WHERE child.parent_feature_id = f.feature_id
              AND $ann_clause
        )";
        array_push($params, ...$ann_params);
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

    $cache = "$gene_set_path/chr_names_cache.json";
    if (file_exists($cache) && filemtime($cache) >= filemtime($tsv)) {
        return json_decode(file_get_contents($cache), true) ?: [];
    }

    $chrs = [];
    $fh   = fopen($tsv, 'r');
    while (($line = fgets($fh)) !== false) {
        $p = explode("\t", $line, 4);
        if (isset($p[2])) $chrs[trim($p[2])] = true;
    }
    fclose($fh);

    $result = array_keys($chrs);
    sort($result, SORT_NATURAL);
    @file_put_contents($cache, json_encode($result));
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
    while (($line = fgets($fh)) !== false) {
        $p = explode("\t", rtrim($line));
        if (count($p) < 6) continue;
        [$uniquename, $gene_id, $chr, $start, $end, $strand] = $p;

        if ($filter !== null) {
            // Targeted load: any row whose uniquename is in the requested set.
            if (!isset($filter[$uniquename])) continue;
        } else {
            // No filter: gene-level rows only (col0 === col1) for memory efficiency.
            if ($uniquename !== $gene_id) continue;
        }

        $coords[$uniquename] = [
            'chr'    => $chr,
            'start'  => (int)$start,
            'end'    => (int)$end,
            'strand' => $strand,
        ];
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
 * @param string   $gff_path         Path to genomic.gff
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
 * @param string   $gff_path      Path to genomic.gff (used for exons mode only)
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
    $fasta = "$assembly_dir/genome.fa";
    $fai   = "$assembly_dir/genome.fa.fai";
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
