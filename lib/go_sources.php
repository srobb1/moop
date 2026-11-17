<?php
/**
 * Where a gene's GO terms come from -- the ONE list, so every place that flags or filters by source
 * uses the same names and meanings (api/v1/gene.php, api/v1/go_term_genes.php, and later the
 * gene-list GO endpoint and the website's GO tables).
 *
 * A GO annotation's source is its annotation_source_name in organism.sqlite, written by the
 * pipeline: "EggNOG (EggNOG2GO)", "InterProScan (PANTHER2GO)", "OMA2GO", "InterProScan (InterPro2GO)"
 * (the same four in every organism, 2026-11-17). Matched by the "...2GO" token, so a pipeline
 * renaming the tool part ("InterProScan 5 (PANTHER2GO)") keeps working. A source matching none of
 * these is reported under its own name, never dropped.
 */

/** key => [label, the token in annotation_source_name, what it means]. */
function moop_go_sources(): array {
    return [
        'eggnog'   => ['label' => 'EggNOG',   'token' => 'EggNOG2GO',
                       'about' => 'From the gene\'s orthology group in eggNOG.'],
        'panther'  => ['label' => 'PANTHER',  'token' => 'PANTHER2GO',
                       'about' => 'From the gene\'s PANTHER protein family (found by InterProScan).'],
        'oma'      => ['label' => 'OMA',      'token' => 'OMA2GO',
                       'about' => 'Carried over from the gene\'s orthologs (OMA).'],
        'interpro' => ['label' => 'InterPro', 'token' => 'InterPro2GO',
                       'about' => 'From a protein domain the gene contains (InterPro2GO).'],
    ];
}

/** The key for a database source name ('panther'), or null when it is none of the four. */
function moop_go_source_key(string $source_name): ?string {
    foreach (moop_go_sources() as $key => $s) {
        if (stripos($source_name, $s['token']) !== false) return $key;
    }
    return null;
}

/**
 * Read a go_sources= value ("panther" or "oma,panther"; empty = all).
 * @return array{keys: ?list<string>, unknown: list<string>}  keys null = no filter
 */
function moop_go_sources_param(string $value): array {
    $asked = array_values(array_unique(array_filter(array_map('strtolower', preg_split('/[\s,;]+/', trim($value))))));
    if (!$asked) return ['keys' => null, 'unknown' => []];
    $known = array_keys(moop_go_sources());
    return ['keys' => array_values(array_intersect($asked, $known)), 'unknown' => array_values(array_diff($asked, $known))];
}

/** The database source names in one organism that belong to these keys (for an SQL IN list). */
function moop_go_source_names(string $db_file, array $keys): array {
    $names = [];
    foreach (fetchData("SELECT DISTINCT annotation_source_name FROM annotation_source WHERE annotation_type = 'Gene Ontology'", $db_file) as $r) {
        $k = moop_go_source_key((string) $r['annotation_source_name']);
        if ($k !== null && in_array($k, $keys, true)) $names[] = (string) $r['annotation_source_name'];
    }
    return $names;
}

/** For a JSON answer: what each key means, so a reader of the data need not look it up. */
function moop_go_sources_legend(): array {
    return array_map(fn($s) => ['label' => $s['label'], 'about' => $s['about']], moop_go_sources());
}
