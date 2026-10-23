<?php
/**
 * Expression tool: a list of genes x chosen experiments -> heatmap (+ TSV).
 *
 * Reads the gene set's expression bundles (notes/EXPRESSION_BUNDLE_SPEC.md) through the same
 * functions as the gene page's Expression card, so both show the same numbers.
 *
 * Request (GET, or POST -- so another page, e.g. the Data Exporter, can send a long list):
 *   organism, assembly, gene_set   the gene set (only gene sets with expression data are offered)
 *   genes                          ids, one per line (or separated by spaces/commas); gene,
 *                                  transcript or protein ids -- each is drawn as its gene's row
 *   experiments[]                  experiment folder names; absent = all
 *   mode = level|pattern           absolute (log) shading, or each gene scaled to its own highest
 *   sort = input|level             the order pasted, or highest-expressed first
 *   download = tsv                 the shown genes x experiments, one row per sample, with the
 *                                  ID(s) pasted for each gene (input_ids)
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/expression_functions.php';
include_once __DIR__ . '/../includes/layout.php';

$organism_data = $config->getPath('organism_data');
$site          = $config->getString('site');
$user_level    = get_access_level();
$req           = fn(string $k) => $_POST[$k] ?? $_GET[$k] ?? null;

// 1. Gene set: only those with expression data this visitor may see. A request naming any other
//    is treated as naming none -- the names never reach a path unchecked.
$gene_sets = moop_expression_gene_sets($organism_data, $user_level);
$chosen = null;
foreach ($gene_sets as $gs) {
    if ($gs['organism'] === (string)$req('organism') && $gs['assembly'] === (string)$req('assembly')
        && $gs['gene_set'] === (string)$req('gene_set')) {
        $chosen = $gs;
    }
}
if (!$chosen && count($gene_sets) === 1) $chosen = $gene_sets[0];

$experiments = [];
if ($chosen) {
    $overrides = moop_expression_load_overrides(rtrim($config->getPath('metadata_path'), '/') . '/expression_overrides.json');
    $key       = "{$chosen['organism']}/{$chosen['assembly']}/{$chosen['gene_set']}/";
    $experiments = moop_expression_bundle_experiments(
        moop_expression_bundle_dir($chosen['organism'], $chosen['assembly'], $chosen['gene_set']), $key, $user_level, $overrides);
}

// 3. Experiments: the ticked ones -- or all of them when they were not chosen on this form (first
//    visit, or a gene list sent from another page). A sent form with every box unticked sends no
//    experiments[] at all, so the form marks itself (show=1); otherwise "none ticked" reads as "all".
$asked_slugs = $req('experiments');
$form_sent   = $req('show') !== null;
$selected = is_array($asked_slugs)
    ? array_filter($experiments, fn($e) => in_array($e['slug'], $asked_slugs, true))
    : ($form_sent ? [] : $experiments);

// 2. Genes: ids as typed, in order, duplicates dropped.
$genes_text = trim((string)$req('genes'));
$typed = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', $genes_text) ?: [], 'strlen')));
$too_many = count($typed) > MOOP_EXPRESSION_TOOL_MAX_GENES;
if ($too_many) $typed = array_slice($typed, 0, MOOP_EXPRESSION_TOOL_MAX_GENES);

$mode = $req('mode') === 'pattern' ? 'pattern' : 'level';
$sort = $req('sort') === 'level' ? 'level' : 'input';

$genes = []; $unresolved = []; $ambiguous = []; $via_parent = []; $no_rows = []; $inputs_for = [];
$heatmap = null; $matrix = [];
if ($chosen && $typed && $selected) {
    $db_file = organism_db_path($chosen['organism']);
    $gs_row  = fetchData('SELECT gs.gene_set_id FROM gene_set gs JOIN genome g ON g.genome_id = gs.genome_id
                           WHERE g.genome_accession = ? AND gs.gene_set_name = ?',
                         $db_file, [$chosen['assembly'], $chosen['gene_set']])[0] ?? null;
    if ($gs_row) {
        $gs_id    = (int)$gs_row['gene_set_id'];
        $resolved = moop_resolve_input_ids($typed, $db_file, [$gs_id], $unresolved);
        // Version-ambiguous input ("X" matching X.1 and X.2) is reported, never guessed.
        foreach ($resolved as $input => $hits) if (count($hits) > 1) $ambiguous[$input] = $hits;
        $roots = moop_expression_root_genes(array_merge(...array_values($resolved ?: [[]])), $db_file, $gs_id);
        foreach ($typed as $input) {
            foreach ($resolved[$input] ?? [] as $hit) {
                $gene = $roots[$hit] ?? null;
                if ($gene === null) continue;
                if ($gene !== $hit) $via_parent[$input] = $gene;
                if (!in_array($gene, $genes, true)) $genes[] = $gene;
                // What was pasted for each gene, for the TSV's input_ids column: two transcripts of
                // one gene are one row in the heatmap but both are named in the download.
                if (!in_array($input, $inputs_for[$gene] ?? [], true)) $inputs_for[$gene][] = $input;
            }
        }
    }

    if ($genes) {
        ['experiments' => $selected, 'matrix' => $matrix] = moop_expression_heatmap_matrix($selected, $genes);
        foreach ($genes as $g) {
            if (!array_filter($matrix[$g], fn($groups) => $groups !== null)) $no_rows[] = $g;
        }
        if ($sort === 'level') {
            $peak = function ($g) use ($matrix) {
                $m = -1.0;
                foreach ($matrix[$g] as $groups) foreach ($groups ?? [] as $x) if ($x['mean'] !== null) $m = max($m, $x['mean']);
                return $m;
            };
            usort($genes, fn($a, $b) => $peak($b) <=> $peak($a));
        }

        if ($req('download') === 'tsv') {
            // One row per sample, like the gene page's download (the same columns, from the same
            // function), plus input_ids: the ID(s) pasted for that gene -- a transcript or protein
            // id when that is what was pasted, several when several resolved to the one gene.
            $out = "gene\texperiment\texperiment_id\tsample\tcondition\treplicate\tvalue\tunit\tinput_ids\n";
            foreach ($selected as $i => $e) {
                try { $vals = moop_expression_bundle_values($e, $genes); } catch (RuntimeException $ex) { continue; }
                foreach ($genes as $g) {
                    if (!isset($vals[$g])) continue;
                    $rows = moop_expression_gene_tsv($g, [$i => $e], [$i => $vals[$g]]);
                    $ids  = str_replace(["\t", "\n", "\r"], ' ', implode(',', $inputs_for[$g] ?? []));
                    foreach (array_slice(explode("\n", rtrim($rows, "\n")), 1) as $line) $out .= "$line\t$ids\n";
                }
            }
            header('Content-Type: text/tab-separated-values; charset=utf-8');
            header('Content-Disposition: attachment; filename="expression_' . $chosen['gene_set'] . '.tsv"');
            echo $out;
            exit;
        }
        $heatmap = moop_expression_heatmap_svg($genes, $selected, $matrix, $mode,
                                               moop_expression_gene_symbols($genes, $db_file, $gs_id),
                                               fn($g) => "/$site/tools/parent.php?" . http_build_query([
                                                   'organism' => $chosen['organism'], 'uniquename' => $g,
                                                   'assembly' => $chosen['assembly'], 'gene_set' => $chosen['gene_set']]));
    }
}

// The same request with one setting changed -- the mode / sort / download links.
$link = function (array $change) use ($chosen, $genes_text, $selected, $mode, $sort, $site) {
    $q = ['organism' => $chosen['organism'] ?? '', 'assembly' => $chosen['assembly'] ?? '', 'gene_set' => $chosen['gene_set'] ?? '',
          'genes' => $genes_text, 'experiments' => array_values(array_column($selected, 'slug')),
          'mode' => $mode, 'sort' => $sort, 'show' => 1];
    return "/$site/tools/expression.php?" . http_build_query(array_merge($q, $change));
};

echo render_display_page(__DIR__ . '/pages/expression.php', [
    'site'            => $site,
    'gene_sets'       => $gene_sets,
    'chosen'          => $chosen,
    'experiments'     => $experiments,
    'selected_slugs'  => array_column($selected, 'slug'),
    'genes_text'      => $genes_text,
    'typed'           => $typed,
    'too_many'        => $too_many,
    'form_sent'       => $form_sent,
    'genes'           => $genes,
    'unresolved'      => $unresolved,
    'ambiguous'       => $ambiguous,
    'via_parent'      => $via_parent,
    'no_rows'         => $no_rows,
    'heatmap'         => $heatmap,
    'mode'            => $mode,
    'units_label'     => moop_expression_units_label($selected),
    'sort'            => $sort,
    'link'            => $link,
    'page_styles'     => ["/$site/css/expression-tool.css"],
], 'Expression Explorer');
