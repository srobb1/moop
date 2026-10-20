<?php
/**
 * The organism page's gene set comparison: one row per (assembly, gene set) the viewer can open,
 * so choosing between them does not mean opening each one.
 *
 * Every figure is either precomputed or a small file read -- an organism page never walks the
 * feature table or a genome index:
 *
 *   gene_set_summary.json  (cache, per organism; BUILT ONLY in housekeeping's background run)
 *     assemblies.{dir}     sequences, total_length, n50 -- from {dir}/genome.fa.fai. A .fai can
 *                          be 70 MB (Carollia: millions of contigs), so it is sorted by `sort`,
 *                          streamed, never loaded into PHP.
 *     gene_sets.{acc/gs}   genes, transcripts, homology_genes, homology_transcripts: how many
 *                          have a homolog or ortholog (moop_homology_types()). Annotations attach
 *                          to transcripts; a gene counts when any of its transcripts does.
 *   busco.json             per gene set (lib/busco.php)
 *   geneset.json           per gene set: source, date_added
 *
 * A summary older than organism.sqlite is ignored (its counts may predate a reload); the row
 * then shows "—" for those columns instead of numbers that may be wrong.
 */

require_once __DIR__ . '/busco.php';
require_once __DIR__ . '/cache_paths.php';

/**
 * Which annotation types count as "this gene resembles a known gene" for the comparison's homology
 * column (user, 2026-10-20). NOT "any annotation": DeepLoc predicts a location for every protein, so
 * "any" was 100% for Congeria and meaningless. GO terms, domains and families are left out too --
 * GO is derived from the others; the question is "does this gene have a known relative", which is
 * what separates real genes from spurious predictions.
 *
 * GENERAL, because installations name their annotation types differently:
 *   - annotation_config.json annotation_types.{type}.homology = true|false decides, when set;
 *   - otherwise a type counts when its name contains "homolog" or "ortholog" (any case) --
 *     here Homologs, Orthologs and RBBH Homolog; Paralogs and the rest do not.
 *
 * @param list<string> $db_types  the annotation types present in the database
 * @return list<string>
 */
function moop_homology_types(array $db_types): array {
    $flags = moop_homology_flags();
    return array_values(array_filter($db_types, fn($t) => $flags[$t]
        ?? (bool)preg_match('/homolog|ortholog/i', (string)$t)));
}

/** The explicit homology flags from annotation_config.json: {type: bool}, only types that set one. */
function moop_homology_flags(): array {
    $cfg = loadJsonFile(ConfigManager::getInstance()->getPath('metadata_path') . '/annotation_config.json', []);
    $flags = [];
    foreach (($cfg['annotation_types'] ?? []) as $type => $settings) {
        if (is_array($settings) && array_key_exists('homology', $settings)) {
            $flags[(string)$type] = (bool)$settings['homology'];
        }
    }
    ksort($flags);
    return $flags;
}

/**
 * Bump when what the summary file holds changes. A file of another version is stale, so the next
 * background run rebuilds it -- mtimes alone cannot see a change in the code that built it.
 * 2: homology_* (genes with a homolog/ortholog) replaced annotated_* (any annotation).
 * 3: homology types chosen by name or config flag, recorded in the file.
 */
const MOOP_GENE_SET_SUMMARY_VERSION = 3;

function moop_gene_set_summary_file(string $organism): string {
    $dir = moop_cache_root() . '/' . $organism;
    return moop_ensure_cache_dir($dir) ? "$dir/gene_set_summary.json" : '';
}

/**
 * Is this organism's summary missing or older than anything it was built from?
 */
function moop_gene_set_summary_is_stale(string $organism_dir, string $file): bool {
    if ($file === '' || !is_file($file)) return true;
    $doc = json_decode((string)@file_get_contents($file), true);
    if (($doc['version'] ?? null) !== MOOP_GENE_SET_SUMMARY_VERSION) return true;
    // Built with different homology flags (an admin changed annotation_config.json): rebuild.
    if (($doc['homology_flags'] ?? null) !== moop_homology_flags()) return true;
    $built = filemtime($file);
    if (is_file("$organism_dir/organism.sqlite") && filemtime("$organism_dir/organism.sqlite") > $built) return true;
    foreach (glob("$organism_dir/*/" . genome_fasta_filename() . '.fai') ?: [] as $fai) {
        if (filemtime($fai) > $built) return true;
    }
    return false;
}

/**
 * Length statistics from a .fai, streamed through sort(1): {sequences, total_length, n50}.
 * null if the file cannot be read.
 */
function moop_fai_stats(string $fai): ?array {
    if (!is_file($fai) || filesize($fai) === 0) return null;
    $total = 0; $count = 0;
    $fh = @fopen($fai, 'r');
    if (!$fh) return null;
    while (($line = fgets($fh)) !== false) {
        $parts = explode("\t", $line, 3);
        if (isset($parts[1]) && ctype_digit($parts[1])) {
            $total += (int)$parts[1];
            $count++;
        }
    }
    fclose($fh);
    if ($count === 0) return null;

    // Longest first; N50 is the length at which the running sum first reaches half the total.
    // sort's stderr is dropped: stopping at the N50 closes the pipe early, and sort then
    // reports "Broken pipe" -- expected, not an error.
    $cmd = 'cut -f2 ' . escapeshellarg($fai) . ' | LC_ALL=C sort -rn 2>/dev/null';
    $pipe = popen($cmd, 'r');
    if (!$pipe) return null;
    $n50 = 0; $running = 0;
    while (($len = fgets($pipe)) !== false) {
        $running += (int)$len;
        if ($running * 2 >= $total) { $n50 = (int)$len; break; }
    }
    pclose($pipe);
    return ['sequences' => $count, 'total_length' => $total, 'n50' => $n50];
}

/**
 * Build one organism's summary. Seconds on a large organism: background run only.
 */
function moop_build_gene_set_summary(string $organism_dir): array {
    $out = ['version' => MOOP_GENE_SET_SUMMARY_VERSION, 'homology_flags' => moop_homology_flags(),
            'homology_types' => [], 'assemblies' => [], 'gene_sets' => []];
    foreach (glob("$organism_dir/*/" . genome_fasta_filename() . '.fai') ?: [] as $fai) {
        $stats = moop_fai_stats($fai);
        if ($stats) $out['assemblies'][basename(dirname($fai))] = $stats;
    }

    $db = "$organism_dir/organism.sqlite";
    if (!is_file($db)) return $out;
    $key = "g.genome_accession || '/' || gs.gene_set_name";
    foreach (fetchData("SELECT $key AS k,
                               COUNT(CASE WHEN f.feature_type = 'gene' THEN 1 END) AS genes,
                               COUNT(CASE WHEN f.feature_type = 'mRNA' THEN 1 END) AS transcripts
                        FROM gene_set gs
                        JOIN genome g ON g.genome_id = gs.genome_id
                        LEFT JOIN feature f ON f.gene_set_id = gs.gene_set_id
                        GROUP BY gs.gene_set_id", $db, []) as $row) {
        $out['gene_sets'][$row['k']] = ['genes' => (int)$row['genes'], 'transcripts' => (int)$row['transcripts'],
                                        'homology_genes' => 0, 'homology_transcripts' => 0];
    }
    // A gene counts when it, or one of its transcripts, carries a homolog or ortholog.
    $homology = moop_homology_types(array_column(
        fetchData('SELECT DISTINCT annotation_type FROM annotation_source', $db, []), 'annotation_type'));
    $out['homology_types'] = $homology;   // recorded, so the page can say what was counted
    if (!$homology) {
        // Nothing homolog-like in this database: the figure is unknown, not zero.
        foreach ($out['gene_sets'] as &$gs_counts) {
            $gs_counts['homology_genes'] = $gs_counts['homology_transcripts'] = null;
        }
        unset($gs_counts);
        return $out;
    }
    $types = implode(',', array_fill(0, count($homology), '?'));
    foreach (fetchData("SELECT $key AS k,
                               COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id
                                                   WHEN p.feature_type = 'gene' THEN p.feature_id END) AS homology_genes,
                               COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) AS homology_transcripts
                        FROM feature f
                        LEFT JOIN feature p ON p.feature_id = f.parent_feature_id
                        JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                        JOIN genome g ON g.genome_id = gs.genome_id
                        WHERE f.feature_id IN (SELECT fa.feature_id FROM feature_annotation fa
                                               JOIN annotation a ON a.annotation_id = fa.annotation_id
                                               JOIN annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
                                               WHERE ans.annotation_type IN ($types))
                        GROUP BY gs.gene_set_id", $db, $homology) as $row) {
        if (isset($out['gene_sets'][$row['k']])) {
            $out['gene_sets'][$row['k']]['homology_genes']       = (int)$row['homology_genes'];
            $out['gene_sets'][$row['k']]['homology_transcripts'] = (int)$row['homology_transcripts'];
        }
    }
    return $out;
}

/**
 * The comparison rows for an organism page: every gene set the viewer can open, with whatever is
 * known about it. Cheap: small JSON reads only.
 *
 * @param array $assemblies  accession => display label (getOrganismAssemblies(), already
 *                           access-filtered)
 * @return list<array>
 */
function moop_gene_set_comparison_rows(string $organism, array $assemblies, string $organism_data): array {
    $org_dir = "$organism_data/$organism";
    $file    = moop_gene_set_summary_file($organism);
    $summary = null;
    if ($file !== '' && is_file($file) && !moop_gene_set_summary_is_stale($org_dir, $file)) {
        $summary = json_decode((string)@file_get_contents($file), true);
    }
    $db = "$org_dir/organism.sqlite";
    if (!is_file($db)) return [];

    $rows = [];
    foreach ($assemblies as $accession => $label) {
        foreach (getAssemblyGeneSetNames($accession, $db) as $gs) {
            if (!has_gene_set_access($organism, $accession, $gs['gene_set_name'])) continue;
            // The directory the files are actually in: the accession, else the genome name
            // (the same rule as the gene set and assembly pages).
            $asm_dir = is_dir("$org_dir/$accession") ? $accession
                     : (($gs['genome_name'] !== '' && is_dir("$org_dir/{$gs['genome_name']}")) ? $gs['genome_name'] : $accession);
            $gs_dir  = "$org_dir/$asm_dir/{$gs['gene_set_name']}";
            $meta    = loadJsonFile("$gs_dir/geneset.json", []);
            $busco   = moop_load_busco($gs_dir);

            $euk = null;
            foreach ($busco['runs'] ?? [] as $run) {
                if (strpos($run['lineage'], 'eukaryota') === 0) { $euk = $run; break; }
            }

            $counts   = $summary['gene_sets']["$accession/{$gs['gene_set_name']}"] ?? null;
            $asm_stat = $summary['assemblies'][$asm_dir] ?? null;
            $warnings = $busco['warnings'] ?? [];
            if ($counts && $counts['transcripts'] > 0 && $counts['homology_transcripts'] === 0) {   // 0, not null
                $warnings[] = 'No gene in this gene set has a homolog or ortholog attached.';
            }

            $rows[] = [
                'assembly'       => $accession,
                'assembly_label' => $label,
                'gene_set'       => $gs['gene_set_name'],
                'source'         => is_string($meta['source'] ?? null) ? $meta['source'] : '',
                'date_added'     => is_string($meta['date_added'] ?? null) ? $meta['date_added'] : '',
                'counts'         => $counts,
                'assembly_stats' => $asm_stat,
                'busco_euk'      => $euk,
                'warnings'       => $warnings,
            ];
        }
    }
    return $rows;
}

/**
 * Gene set names (and the genome name, for the directory fallback) for one assembly. Names only --
 * no feature counts, which are what made getAssemblyGeneSets() a feature-table walk.
 */
function getAssemblyGeneSetNames(string $assembly, string $db): array {
    return fetchData("SELECT gs.gene_set_name, g.genome_name
                      FROM gene_set gs JOIN genome g ON g.genome_id = gs.genome_id
                      WHERE g.genome_accession = ? ORDER BY gs.gene_set_name", $db, [$assembly]) ?: [];
}

/** 1234567 -> "1.2 Mb"; for N50 and assembly size. */
function moop_format_bp(int $bp): string {
    if ($bp >= 1e9) return number_format($bp / 1e9, 2) . ' Gb';
    if ($bp >= 1e6) return number_format($bp / 1e6, 1) . ' Mb';
    if ($bp >= 1e3) return number_format($bp / 1e3, 1) . ' kb';
    return number_format($bp) . ' bp';
}
