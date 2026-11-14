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
 * "Genes in this gene set" (the search, prefilled and scoped). Modules stay in the table only.
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
                    $genes = moop_url('tools/search.php?' . http_build_query($ctx + ['q' => $id]));
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
