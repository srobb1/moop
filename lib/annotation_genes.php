<?php
/**
 * Genes by annotation, for the api/v1 calls an AI assistant uses (2026-11-17, Sofia: "get genes with these
 * protein domains in these organisms, assemblies, gene sets; get the domains in these genes; get all the
 * genes in a pathway; get the pathway for this gene").
 *
 *   moop_v1_scope()              the gene sets a call covers: one or more organisms, optionally narrowed to an
 *                                assembly / gene set -- only those this visitor may open
 *   moop_annotation_type_genes() genes in one gene set carrying any (or all) of some accessions of one
 *                                annotation type (Domains, Pathways ...)
 *   moop_annotation_type_search() accessions of one type whose name contains a text ("kinase")
 *
 * Annotations sit on the mRNA (sometimes the gene): a hit on a transcript counts for its parent gene, as in
 * moop_kegg_gene_set_rows(). Queries go accession-first (annotation_accession_idx), so a call costs the rows
 * of the asked accessions, not the gene set.
 */
require_once __DIR__ . '/functions_database.php';
require_once __DIR__ . '/kegg.php';

/**
 * The gene sets a call covers, each ['organism','assembly','gene_set','gene_set_id','db'].
 * Organisms the visitor cannot open, or without a database, give nothing -- the same as a name that
 * does not exist. assembly / gene_set narrow only when exactly one organism is asked for.
 */
function moop_v1_scope(array $organisms, string $assembly = '', string $gene_set = ''): array {
    $out = [];
    $one = count($organisms) === 1;
    foreach ($organisms as $org) {
        if (!moop_plain_name($org)) continue;
        $db = organism_db_path($org);
        if (!is_file($db)) continue;
        try {
            $sets = moop_kegg_visible_gene_sets($org, $db, $one ? $assembly : '');
        } catch (Throwable $e) {
            error_log("moop_v1_scope $org: " . $e->getMessage());
            continue;
        }
        foreach ($sets as $s) {
            if ($one && $gene_set !== '' && $s['gene_set'] !== $gene_set) continue;
            $out[] = ['organism' => $org, 'assembly' => $s['assembly'], 'gene_set' => $s['gene_set'],
                      'gene_set_id' => $s['gene_set_id'], 'db' => $db];
        }
    }
    return $out;
}

/** Normalise a KEGG pathway id: 04142, ko04142, hsa04142, map04142 -> map04142; modules (M00001) as they are. */
function moop_v1_pathway_id(string $id): string {
    $id = trim($id);
    if (preg_match('/^(?:[a-z]{2,4})?(\d{5})$/i', $id, $m)) return 'map' . $m[1];
    if (preg_match('/^M\d{5}$/i', $id)) return strtoupper($id);
    return $id;
}

/**
 * Genes in one gene set carrying the accessions (of one annotation type).
 *
 * @param bool $all  true: only genes carrying EVERY accession; false: any of them
 * @return array{total:int, genes:list<array{gene:string,name:string,description:string,
 *               hits:list<array{accession:string,name:string,source:string,score:?float,on:list<string>}>}>}
 * @throws PDOException
 */
function moop_annotation_type_genes(string $db_file, int $gene_set_id, string $type, array $accessions,
                                    bool $all, int $cap): array {
    $accessions = array_values(array_unique(array_filter(array_map('strval', $accessions), 'strlen')));
    if (!$accessions) return ['total' => 0, 'genes' => []];
    $dbh = getDbConnection($db_file);
    $st  = $dbh->prepare("SELECT COALESCE(f.parent_feature_id, f.feature_id) AS gene, f.feature_uniquename AS on_id,
                                 a.annotation_accession, a.annotation_description, s.annotation_source_name, fa.score
                            FROM json_each(?) j
                           CROSS JOIN annotation a          ON a.annotation_accession = j.value
                           CROSS JOIN annotation_source s   ON s.annotation_source_id = a.annotation_source_id
                           CROSS JOIN feature_annotation fa ON fa.annotation_id = a.annotation_id
                           CROSS JOIN feature f             ON f.feature_id = fa.feature_id
                           WHERE s.annotation_type = ? AND f.gene_set_id = ?");
    // CROSS JOIN pins the order, accession first: left to the planner it walked the whole gene set
    // (feature_gene_set_id_idx) -- 10.8 s for PF00085 across Smed's six gene sets, warm (2026-11-17).
    $st->execute([json_encode($accessions), $type, $gene_set_id]);
    $by_gene = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $k = $r['annotation_source_name'] . "\x1f" . $r['annotation_accession'];
        $h = &$by_gene[(int) $r['gene']][$k];
        $h ??= ['accession' => $r['annotation_accession'], 'name' => (string) $r['annotation_description'],
                'source' => $r['annotation_source_name'], 'score' => null, 'on' => []];
        if ($r['score'] !== null && ($h['score'] === null || (float) $r['score'] < $h['score'])) $h['score'] = (float) $r['score'];
        if (!in_array($r['on_id'], $h['on'], true)) $h['on'][] = $r['on_id'];
        unset($h);
    }
    if ($all) {
        $want = count($accessions);
        $by_gene = array_filter($by_gene, fn($hits) => count(array_unique(array_column($hits, 'accession'))) >= $want);
    }
    $total = count($by_gene);
    if (!$by_gene) return ['total' => 0, 'genes' => []];

    $st = $dbh->prepare("SELECT feature_id, feature_uniquename, feature_name, feature_description FROM feature
                          WHERE feature_id IN (SELECT value FROM json_each(?))");
    $st->execute([json_encode(array_keys($by_gene))]);
    $info = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) $info[(int) $f['feature_id']] = $f;
    $genes = [];
    foreach ($by_gene as $gid => $hits) {
        $f = $info[$gid] ?? null;
        if (!$f) continue;
        $hits = array_values($hits);
        usort($hits, fn($a, $b) => [$a['accession'], $a['source']] <=> [$b['accession'], $b['source']]);
        $genes[] = ['gene' => $f['feature_uniquename'], 'name' => (string) $f['feature_name'],
                    'description' => (string) $f['feature_description'], 'hits' => $hits];
    }
    usort($genes, fn($a, $b) => strcmp($a['gene'], $b['gene']));
    return ['total' => $total, 'genes' => array_slice($genes, 0, $cap)];
}

/**
 * Accessions of one annotation type whose name contains $text (case-insensitive), or that IS $text; by accession.
 * @return list<array{accession:string,name:string,source:string}>
 * @throws PDOException
 */
function moop_annotation_type_search(string $db_file, string $type, string $text, int $cap): array {
    $text = trim($text);
    if ($text === '') return [];
    $dbh = getDbConnection($db_file);
    $st  = $dbh->prepare("SELECT a.annotation_accession, a.annotation_description, s.annotation_source_name
                            FROM annotation a JOIN annotation_source s ON s.annotation_source_id = a.annotation_source_id
                           WHERE s.annotation_type = ? AND (a.annotation_description LIKE ? ESCAPE '\\' OR a.annotation_accession = ?)
                           GROUP BY a.annotation_accession, s.annotation_source_name
                           ORDER BY a.annotation_accession LIMIT ?");
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%';
    $st->execute([$type, $like, $text, $cap]);
    return array_map(fn($r) => ['accession' => $r[0], 'name' => (string) $r[1], 'source' => $r[2]], $st->fetchAll(PDO::FETCH_NUM));
}

/**
 * The whole GET call behind api/v1/domain_genes and api/v1/pathway_genes: parse, scope, query each gene set,
 * answer JSON or TSV. $o: type (annotation type), param (the accession parameter's name), normalise (callable
 * or null), max_acc, by_name (bool: name= allowed), cap (genes per gene set), label (for messages).
 */
function moop_v1_annotation_genes_endpoint(array $o): void {
    $format = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
    $split  = fn($v) => array_values(array_unique(preg_split('/[\s,;]+/', trim((string) $v), -1, PREG_SPLIT_NO_EMPTY)));
    $orgs   = array_slice($split($_GET['organism'] ?? ''), 0, 20);
    $accs   = array_slice($split($_GET[$o['param']] ?? ''), 0, $o['max_acc']);
    if ($o['normalise']) $accs = array_values(array_unique(array_map($o['normalise'], $accs)));
    $name   = $o['by_name'] ? trim((string) ($_GET['name'] ?? '')) : '';
    $all    = ($_GET['match'] ?? '') === 'all';
    $fail = function (int $code, string $msg) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['api_version' => 1, 'error' => $msg], JSON_UNESCAPED_SLASHES);
        exit;
    };
    if (!$orgs || (!$accs && $name === '')) {
        $fail(400, "organism and {$o['param']}" . ($o['by_name'] ? ' (or name)' : '') . ' are required.');
    }
    $scope = moop_v1_scope($orgs, (string) ($_GET['assembly'] ?? ''), (string) ($_GET['gene_set'] ?? ''));
    if (!$scope) $fail(404, 'No gene set that you can open for ' . implode(', ', $orgs) . '.');

    $results = [];
    $matched = [];
    foreach ($scope as $g) {
        try {
            $these = $accs;
            if ($name !== '') {
                foreach (moop_annotation_type_search($g['db'], $o['type'], $name, 50) as $m) {
                    $these[] = $m['accession'];
                    $matched[$m['accession']] = $m;
                }
            }
            // name= with match=all would ask for every domain named "kinase" at once: name means any.
            $r = moop_annotation_type_genes($g['db'], $g['gene_set_id'], $o['type'], $these, $all && $name === '', $o['cap']);
        } catch (Throwable $e) {
            error_log("api/v1 {$o['label']} {$g['organism']}: " . $e->getMessage());
            $r = ['total' => 0, 'genes' => [], 'error' => 'could not be read'];
        }
        $results[] = ['organism' => $g['organism'], 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
                      'total' => $r['total'], 'shown' => count($r['genes'])] + (isset($r['error']) ? ['error' => $r['error']] : [])
                   + ['genes' => $r['genes']];
    }
    $capped = array_filter($results, fn($r) => $r['total'] > $r['shown']);
    $notes = array_values(array_filter([
        $capped ? "Each gene set lists at most {$o['cap']} genes; 'total' is the full count." : null,
        $name !== '' && count($matched) >= 50 ? 'name matched 50 or more accessions; only the first 50 per organism were used.' : null,
    ]));

    if ($format === 'tsv') {
        header('Content-Type: text/tab-separated-values; charset=utf-8');
        $cell = fn($v) => str_replace(["\t", "\r", "\n"], ' ', (string) $v);
        echo implode("\t", ['organism', 'assembly', 'gene_set', 'gene', 'gene_name', 'gene_description',
                            'accession', 'accession_name', 'source', 'score', 'on']) . "\n";
        foreach ($results as $r) {
            foreach ($r['genes'] as $gene) {
                foreach ($gene['hits'] as $h) {
                    echo implode("\t", array_map($cell, [$r['organism'], $r['assembly'], $r['gene_set'], $gene['gene'], $gene['name'],
                        $gene['description'], $h['accession'], $h['name'], $h['source'], $h['score'] ?? '', implode(' ', $h['on'])])) . "\n";
                }
            }
        }
        foreach ($notes as $n) echo "# $n\n";
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'type' => $o['type'], $o['param'] => $accs]
        + ($name !== '' ? ['name' => $name, 'name_matched' => array_values($matched)] : [])
        + ['match' => $all && $name === '' ? 'all' : 'any', 'results' => $results, 'notes' => $notes],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
