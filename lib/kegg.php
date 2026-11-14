<?php
/**
 * KEGG on the site: pathway names and classes, and links to KEGG's own maps with a gene set's KOs coloured.
 *
 * The data are three annotation sources loaded by the pipeline (notes/KEGG_PATHWAY_PLAN_2026-11-13.md):
 * "KEGG Orthology (eggNOG)" (type Gene Families), "KEGG Pathway (eggNOG)" and "KEGG Module (eggNOG)" (type
 * Pathways). feature_annotation.score = how many KOs eggNOG gave the gene (1 = one; more = ambiguous).
 * Web plan: notes/KEGG_GENE_PAGE_PLAN.md.
 *
 * Maps are LINKED, never hosted: KEGG's pathway images may not be republished without a licence. KEGG draws
 * the map and colours the KOs we name. The form kegg.jp/pathway/mapX+K... is a 404 (checked 2026-11-14);
 * kegg-bin/show_pathway with map= and multi_query= works -- in its exact form (moop_kegg_map_url()).
 */

require_once __DIR__ . '/cache_paths.php';

const MOOP_KEGG_PATHWAY_SOURCE = 'KEGG Pathway (eggNOG)';
const MOOP_KEGG_KO_SOURCE      = 'KEGG Orthology (eggNOG)';

/**
 * Map colours, one meaning each. The legend beside the link (moop_kegg_map_legend_html()) reads this, so
 * the two cannot disagree.
 */
const MOOP_KEGG_COLOURS = [
    'this_gene' => '#ff8c42',
    'many'      => '#5cb85c',
    'one'       => '#bfe8bf',
    'ambiguous' => '#fff1a8',
];

/** What each colour means, for the legend (each a literal, so the translation scan lists it). */
function moop_kegg_colour_labels(): array {
    return [
        'this_gene' => t('this gene'),
        'many'      => t('several genes in the gene set'),
        'one'       => t('one gene in the gene set'),
        'ambiguous' => t('only ambiguous assignments (more than one KO)'),
    ];
}

/**
 * "TNF signaling pathway (Environmental Information Processing; Signal transduction)" ->
 * ['TNF signaling pathway', 'Environmental Information Processing', 'Signal transduction'].
 * The pipeline writes every pathway description this way (all 428 in Nematostella, 2026-11-14); a name
 * that does not fit comes back whole with an empty class, never cut.
 *
 * @return array{0:string,1:string,2:string}
 */
function moop_kegg_split_pathway_name(string $description): array {
    if (preg_match('/^(.*\S)\s*\(([^();]+);\s*([^()]+)\)$/', trim($description), $m)) {
        return [$m[1], trim($m[2]), trim($m[3])];
    }
    return [trim($description), '', ''];
}

/** KEGG's own order of its top pathway classes (br08901); anything else sorts after, by name. */
const MOOP_KEGG_CLASS_ORDER = ['Metabolism', 'Genetic Information Processing', 'Environmental Information Processing',
    'Cellular Processes', 'Organismal Systems', 'Human Diseases', 'Drug Development'];

function moop_kegg_sort_classes(array $by_class): array {
    uksort($by_class, function ($a, $b) {
        $ia = array_search($a, MOOP_KEGG_CLASS_ORDER, true);
        $ib = array_search($b, MOOP_KEGG_CLASS_ORDER, true);
        return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
    });
    return $by_class;
}

/** A KEGG reference pathway id as the pipeline loads them ("map04668"). */
function moop_kegg_valid_pathway(string $id): bool {
    return preg_match('/^map\d{5}\z/', $id) === 1;
}

/**
 * The KOs a gene set has in one pathway: [KO => ['genes' => n, 'ambiguous' => bool]].
 * 'ambiguous' = every feature holding that KO got it with score > 1 (eggNOG offered several KOs).
 *
 * A feature with KOs A and B whose pathway came through A also brings B; KEGG simply does not draw a KO
 * that is not on the map, so the link is unaffected. (With organisms/.kegg/ko_pathway.tsv on the site
 * this could filter first; not needed for colouring.)
 *
 * @throws PDOException when the database cannot be read (the caller decides what that means)
 */
function moop_kegg_gene_set_pathway_kos(string $db_file, int $gene_set_id, string $pathway): array {
    $sql = "SELECT ak.annotation_accession AS ko,
                   COUNT(DISTINCT fk.feature_id) AS genes,
                   MIN(COALESCE(fk.score, 1)) AS min_score
              FROM annotation ap
              JOIN annotation_source sp ON sp.annotation_source_id = ap.annotation_source_id
              JOIN feature_annotation fp ON fp.annotation_id = ap.annotation_id
              JOIN feature f            ON f.feature_id = fp.feature_id
              JOIN feature_annotation fk ON fk.feature_id = fp.feature_id
              JOIN annotation ak        ON ak.annotation_id = fk.annotation_id
              JOIN annotation_source sk ON sk.annotation_source_id = ak.annotation_source_id
             WHERE sp.annotation_source_name = ? AND ap.annotation_accession = ?
               AND sk.annotation_source_name = ? AND f.gene_set_id = ?
             GROUP BY ak.annotation_accession";
    $dbh = getDbConnection($db_file);
    $st  = $dbh->prepare($sql);
    $st->execute([MOOP_KEGG_PATHWAY_SOURCE, $pathway, MOOP_KEGG_KO_SOURCE, $gene_set_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['ko']] = ['genes' => (int)$r['genes'], 'ambiguous' => (float)$r['min_score'] > 1];
    }
    return $out;
}

/** The KOs of one feature (a gene page's transcript): [KO, ...]. */
function moop_kegg_feature_kos(string $db_file, int $gene_set_id, string $uniquename): array {
    $dbh = getDbConnection($db_file);
    $st  = $dbh->prepare("SELECT DISTINCT a.annotation_accession
                            FROM feature f
                            JOIN feature_annotation fa ON fa.feature_id = f.feature_id
                            JOIN annotation a          ON a.annotation_id = fa.annotation_id
                            JOIN annotation_source s   ON s.annotation_source_id = a.annotation_source_id
                           WHERE f.gene_set_id = ? AND f.feature_uniquename = ? AND s.annotation_source_name = ?");
    $st->execute([$gene_set_id, $uniquename, MOOP_KEGG_KO_SOURCE]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * The colour of each KO: this gene's first, then many / one / ambiguous. Pure, so it is tested directly.
 *
 * @param array $kos       moop_kegg_gene_set_pathway_kos()
 * @param array $this_gene KOs to mark as "this gene"
 * @return array<string,string> KO => colour key of MOOP_KEGG_COLOURS
 */
function moop_kegg_ko_colours(array $kos, array $this_gene = []): array {
    $out = [];
    foreach ($kos as $ko => $k) {
        $out[$ko] = in_array($ko, $this_gene, true) ? 'this_gene'
                  : ($k['ambiguous'] ? 'ambiguous' : ($k['genes'] > 1 ? 'many' : 'one'));
    }
    foreach ($this_gene as $ko) $out[$ko] ??= 'this_gene';
    ksort($out);
    return $out;
}

/**
 * KEGG's map with those KOs coloured (KEGG ignores a KO that is not on that map). Exactly KEGG's documented
 * form (kegg.jp/kegg/webapp/color_url.html): "<KO>+<bg>,<fg>" lines joined by %0d%0a. Checked in a browser
 * 2026-11-14: without ",<fg>" and CR+LF KEGG colours NOTHING, silently. The reference map (map…, not ko…)
 * leaves a KO the gene set lacks white, so only our colours show.
 */
function moop_kegg_map_url(string $pathway, array $ko_colours): string {
    $lines = [];
    foreach ($ko_colours as $ko => $key) {
        if (!preg_match('/^K\d{5}\z/', $ko) || !isset(MOOP_KEGG_COLOURS[$key])) continue;
        $lines[] = $ko . '+' . rawurlencode(MOOP_KEGG_COLOURS[$key]) . ',black';
    }
    return 'https://www.kegg.jp/kegg-bin/show_pathway?map=' . rawurlencode($pathway)
         . '&multi_query=' . implode('%0d%0a', $lines);
}

/** The colour key, for beside a map link. $keys limits it (a gene-set table has no "this gene"). */
function moop_kegg_map_legend_html(array $keys = []): string {
    $html = '<span class="kegg-legend">';
    foreach (moop_kegg_colour_labels() as $key => $label) {
        if ($keys && !in_array($key, $keys, true)) continue;
        $html .= '<span class="kegg-legend-item"><span class="kegg-swatch" style="background:' . MOOP_KEGG_COLOURS[$key] . '"></span>'
               . $label . '</span>';
    }
    return $html . '</span>';
}

/**
 * The gene page's KEGG pathway summary, above the Pathways table: the pathways grouped by KEGG class,
 * each with "View on KEGG" (api/kegg_map.php: the gene set's KOs coloured, this gene's marked) and
 * "Genes in this gene set" (the annotation search: this gene set, only the KEGG pathway source, run at once). Modules stay in the table only.
 *
 * @param array $rows     the Pathways rows of one feature (generateAnnotationTableHTML())
 * @param array $link_ctx organism / assembly / gene_set of the page
 * @return string HTML, '' when no row is a KEGG pathway
 */
function moop_kegg_gene_pathways_html(array $rows, string $uniquename, array $link_ctx): string {
    $by_class = [];
    $n = 0;
    foreach ($rows as $r) {
        if (($r['annotation_source_name'] ?? '') !== MOOP_KEGG_PATHWAY_SOURCE) continue;
        $id = (string)($r['annotation_accession'] ?? '');
        if (!moop_kegg_valid_pathway($id)) continue;
        [$name, $top, $sub] = moop_kegg_split_pathway_name((string)($r['annotation_description'] ?? ''));
        $by_class[$top !== '' ? $top : 'Other'][$sub][$id] = ['name' => $name, 'ambiguous' => (float)($r['score'] ?? 1) > 1];
        $n++;
    }
    if ($n === 0) return '';
    $by_class = moop_kegg_sort_classes($by_class);

    $ctx = array_filter(['organism' => $link_ctx['organism'] ?? '', 'assembly' => $link_ctx['assembly'] ?? '',
                         'gene_set' => $link_ctx['gene_set'] ?? ''], 'strlen');
    $can_link = count($ctx) === 3;
    // Many pathways (one Nematostella gene has 126): classes start closed, the counts say what is inside.
    $open = $n <= 12;

    $html = '<div class="kegg-pathways mb-2">';
    $html .= '<div class="small text-muted mb-1">'
           . t('KEGG pathways by class. "View on KEGG" opens KEGG\'s map with this gene and the rest of the gene set coloured:')
           . ' ' . moop_kegg_map_legend_html() . '</div>';
    foreach ($by_class as $top => $subs) {
        $count = array_sum(array_map('count', $subs));
        $html .= '<details class="kegg-class"' . ($open ? ' open' : '') . '><summary><strong>' . htmlspecialchars($top)
               . '</strong> <span class="badge bg-light text-dark">' . $count . '</span></summary><ul class="kegg-pathway-list">';
        ksort($subs);
        foreach ($subs as $sub => $paths) {
            if ($sub !== '') $html .= '<li class="kegg-sub">' . htmlspecialchars($sub) . '</li>';
            uasort($paths, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            foreach ($paths as $id => $p) {
                $html .= '<li class="kegg-pathway">' . htmlspecialchars($p['name']) . ' <span class="text-muted small">' . htmlspecialchars($id) . '</span>';
                if ($p['ambiguous']) {
                    $html .= ' <span class="badge kegg-ambiguous" title="' . t('This gene was given more than one KO; this pathway comes from one of them.') . '">'
                           . t('ambiguous') . '</span>';
                }
                if ($can_link) {
                    $map = moop_url('api/kegg_map.php?' . http_build_query($ctx + ['pathway' => $id, 'gene' => $uniquename]));
                    // The annotation search: this gene set, this pathway id, ONLY the KEGG pathway source, run at once
                    $genes = moop_url('tools/search.php?' . http_build_query($ctx + ['q' => $id, 'source' => MOOP_KEGG_PATHWAY_SOURCE]));
                    $html .= ' <a href="' . htmlspecialchars($map) . '" target="_blank" rel="noopener">' . t('View on KEGG')
                           . ' <i class="fa fa-external-link-alt link-icon"></i></a>'
                           . ' · <a href="' . htmlspecialchars($genes) . '">' . t('Genes in this gene set') . '</a>';
                }
                $html .= '</li>';
            }
        }
        $html .= '</ul></details>';
    }
    return $html . '</div>';
}

// ---------------------------------------------------------------- gene-set pathway table

/**
 * The KEGG reference snapshot copy2moop puts in organisms/.kegg/ (fetch_kegg_reference.sh files), or null
 * when it is not on this site. Cached per request; ~2 MB of ko_pathway.tsv.
 *
 * @return array{date:string, names:array<string,string>, classes:array<string,array{0:string,1:string}>,
 *               kos:array<string,array<string,true>>, mtime:int}|null
 */
function moop_kegg_reference(): ?array {
    static $ref = false;
    if ($ref !== false) return $ref;
    $dir = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/.kegg';
    $ref = null;
    if (!is_readable("$dir/pathway.tsv") || !is_readable("$dir/ko_pathway.tsv")) return null;

    $names = [];
    foreach (file("$dir/pathway.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        [$id, $name] = array_pad(explode("\t", $line, 2), 2, '');
        if (moop_kegg_valid_pathway($id)) $names[$id] = trim($name);
    }
    $kos = [];
    foreach (file("$dir/ko_pathway.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        // "ko:K00001<TAB>path:map00010" -- each pair is listed for map… and ko…; keep map…
        if (preg_match('/^ko:(K\d{5})\tpath:(map\d{5})$/', $line, $m)) $kos[$m[2]][$m[1]] = true;
    }
    $classes = [];
    $tree = is_readable("$dir/pathway_classes.json") ? json_decode((string)file_get_contents("$dir/pathway_classes.json"), true) : null;
    foreach ((array)($tree['children'] ?? []) as $top) {
        foreach ((array)($top['children'] ?? []) as $sub) {
            foreach ((array)($sub['children'] ?? []) as $leaf) {
                if (preg_match('/^(\d{5})\s/', (string)($leaf['name'] ?? ''), $m)) {
                    $classes['map' . $m[1]] = [(string)$top['name'], (string)$sub['name']];
                }
            }
        }
    }
    $date = '';
    if (is_readable("$dir/info.txt") && preg_match('/^\s*pathway\s+\d+\s+(\S+)/m', (string)file_get_contents("$dir/info.txt"), $m)) {
        $date = str_replace('/', '-', $m[1]);
    }
    $mtime = max(array_map(fn($f) => (int)@filemtime("$dir/$f"), ['pathway.tsv', 'ko_pathway.tsv', 'pathway_classes.json']));
    $ref = ['date' => $date, 'names' => $names, 'classes' => $classes, 'kos' => $kos, 'mtime' => $mtime];
    return $ref;
}

/**
 * gene => [accession => score] for one KEGG source in one gene set. Genes, not transcripts: annotations sit on
 * the mRNA, so a transcript counts as its parent gene. Source first (0.1 s), not gene set first (1 s), measured
 * on Nematostella 2026-11-14 -- the planner otherwise walks every annotation of every feature.
 */
function moop_kegg_gene_set_rows(PDO $dbh, int $gene_set_id, string $source): array {
    $st = $dbh->prepare("SELECT COALESCE(f.parent_feature_id, f.feature_id) AS gene, a.annotation_accession AS acc, fa.score
                           FROM annotation_source s
                          CROSS JOIN annotation a          ON a.annotation_source_id = s.annotation_source_id
                          CROSS JOIN feature_annotation fa ON fa.annotation_id = a.annotation_id
                          CROSS JOIN feature f             ON f.feature_id = fa.feature_id
                          WHERE s.annotation_source_name = ? AND f.gene_set_id = ?");
    $st->execute([$source, $gene_set_id]);
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_NUM)) {
        $out[(int)$r[0]][$r[1]] = (float)($r[2] ?? 1);
    }
    return $out;
}

/**
 * One row per pathway the gene set has. Pure (rows in, table out), so it is tested without a database.
 *
 * kos_found: the KOs of the pathway's genes that are IN the pathway by the reference (without one, every KO
 * of those genes -- and no coverage). ambiguous_genes: genes whose every KO came with score > 1.
 *
 * @param array $gene_pathways moop_kegg_gene_set_rows(…, MOOP_KEGG_PATHWAY_SOURCE)
 * @param array $gene_kos      moop_kegg_gene_set_rows(…, MOOP_KEGG_KO_SOURCE)
 * @param array $descriptions  pathway id => description as loaded ("Name (Class; Sub)"), the fallback
 * @param ?array $ref          moop_kegg_reference()
 * @return list<array{pathway:string,name:string,class:string,sub:string,genes:int,ambiguous_genes:int,
 *                    kos_found:list<string>,kos_total:?int}>
 */
function moop_kegg_pathway_table(array $gene_pathways, array $gene_kos, array $descriptions, ?array $ref): array {
    $by_path = [];
    foreach ($gene_pathways as $gene => $paths) {
        foreach ($paths as $p => $score) $by_path[$p][$gene] = $score > 1;
    }
    $rows = [];
    foreach ($by_path as $p => $genes) {
        [$dname, $dtop, $dsub] = moop_kegg_split_pathway_name((string)($descriptions[$p] ?? $p));
        $in_ref = $ref['kos'][$p] ?? null;
        $found = [];
        foreach (array_keys($genes) as $g) {
            foreach (array_keys($gene_kos[$g] ?? []) as $ko) {
                if ($in_ref === null || isset($in_ref[$ko])) $found[$ko] = true;
            }
        }
        ksort($found);
        $rows[] = [
            'pathway'         => $p,
            'name'            => $ref['names'][$p] ?? $dname,
            'class'           => $ref['classes'][$p][0] ?? $dtop,
            'sub'             => $ref['classes'][$p][1] ?? $dsub,
            'genes'           => count($genes),
            'ambiguous_genes' => count(array_filter($genes)),
            'kos_found'       => array_keys($found),
            'kos_total'       => $in_ref !== null ? count($in_ref) : null,
        ];
    }
    usort($rows, fn($a, $b) => [$b['genes'], $a['pathway']] <=> [$a['genes'], $b['pathway']]);
    return $rows;
}

/**
 * The gene set's pathway table, cached in the gene set's cache dir until organism.sqlite or the reference
 * changes (~0.3 s to build; the gene-set page is visited often). A failure to read is an error to the
 * caller, never an empty table that reads as "no pathways".
 *
 * @return array{rows:list, reference_date:string, has_reference:bool}
 * @throws PDOException
 */
function moop_kegg_gene_set_pathways(string $db_path, int $gene_set_id, string $gene_set_dir): array {
    $ref   = moop_kegg_reference();
    $cdir  = moop_cache_dir_for($gene_set_dir);
    $cfile = $cdir !== '' ? "$cdir/kegg_pathways.json" : '';
    $stamp = (int)@filemtime($db_path) . ':' . ($ref['mtime'] ?? 0) . ':v1';
    if ($cfile !== '' && is_readable($cfile)) {
        $c = json_decode((string)file_get_contents($cfile), true);
        if (is_array($c) && ($c['stamp'] ?? '') === $stamp) return $c['data'];
    }

    $dbh = getDbConnection($db_path);
    $paths = moop_kegg_gene_set_rows($dbh, $gene_set_id, MOOP_KEGG_PATHWAY_SOURCE);
    $data = ['rows' => [], 'reference_date' => (string)($ref['date'] ?? ''), 'has_reference' => $ref !== null];
    if ($paths) {
        $kos = moop_kegg_gene_set_rows($dbh, $gene_set_id, MOOP_KEGG_KO_SOURCE);
        $st = $dbh->prepare("SELECT a.annotation_accession, a.annotation_description FROM annotation a
                               JOIN annotation_source s ON s.annotation_source_id = a.annotation_source_id
                              WHERE s.annotation_source_name = ?");
        $st->execute([MOOP_KEGG_PATHWAY_SOURCE]);
        $data['rows'] = moop_kegg_pathway_table($paths, $kos, $st->fetchAll(PDO::FETCH_KEY_PAIR), $ref);
    }
    if ($cfile !== '' && moop_ensure_cache_dir($cdir)) {
        $tmp = "$cfile." . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode(['stamp' => $stamp, 'data' => $data])) !== false) @rename($tmp, $cfile);
        else @unlink($tmp);
    }
    return $data;
}
