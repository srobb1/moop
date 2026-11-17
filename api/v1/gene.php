<?php
/**
 * api/v1/gene — one gene as a record: the gene page, downloadable. JSON for agents and scripts,
 * Markdown for people (and agents) to read, save or quote.
 *
 * GET organism   (required) organism directory name, from api/v1/catalog
 *     id         (required) a gene, transcript, CDS or protein ID -- the record is always the GENE
 *                above it (also accepted: uniquename, the gene page's name for it)
 *     assembly, gene_set  (optional) when the ID is in more than one gene set
 *     format     json (default) | md
 *     go_sources (optional) only GO terms from these sources: eggnog | panther | oma | interpro,
 *                comma-separated (lib/go_sources.php). Each GO term is listed once with 'sources'.
 *
 * Answers: 200 the record; 404 not found (also for anything this visitor may not open -- the same
 * answer, so the endpoint never confirms that restricted data exists); 300 the ID is in more than
 * one gene set (JSON 'choices', each with its own record URL); 400 a missing parameter.
 *
 * Same gene, same visitor, same answer as the gene page: the lookup (access, gene set, the gene
 * above the ID) is moop_gene_lookup() and the anatomy / also-known-as section is
 * moop_gene_page_section(), both shared with tools/parent.php. Lists are capped (CAPS below) and
 * say so ('total' vs items shown); the full annotation table is api/v1/annotations.
 *
 * api/v1/ is a PROMISE (api/v1/catalog.php): add fields freely, never rename or remove one.
 */

include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['parent_functions', 'gene_lookup', 'gff_gene_model', 'fasta_index', 'extract_search_helpers',
          'expression_functions', 'clade_homologs', 'crosswalk', 'anatomy_expression', 'functions_display', 'go_sources'] as $__lib) {
    require_once __DIR__ . "/../../lib/$__lib.php";
}

const GENE_V1_CAPS = ['annotations_per_type' => 100, 'orthologs' => 100, 'gene_family' => 200,
                      'anatomy' => 200, 'also_known_as' => 200];

$format   = ($_GET['format'] ?? 'json') === 'md' ? 'md' : 'json';
$organism = trim((string) ($_GET['organism'] ?? ''));
$id       = trim((string) ($_GET['id'] ?? $_GET['uniquename'] ?? ''));
$assembly = trim((string) ($_GET['assembly'] ?? ''));
$gene_set = trim((string) ($_GET['gene_set'] ?? ''));

header('Cache-Control: private, no-store');   // differs per visitor
$q = fn(array $p) => http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null), '', '&', PHP_QUERY_RFC3986);

/** Answer with an error (or the 'choose' list) in the requested format, and stop. */
$fail = function (int $code, string $message, array $extra = []) use ($format) {
    http_response_code($code);
    if ($format === 'md') {
        header('Content-Type: text/markdown; charset=utf-8');
        echo "# $message\n";
        foreach ($extra['choices'] ?? [] as $c) echo "\n- {$c['assembly_label']} / {$c['gene_set']}: <{$c['record']}>";
        echo "\n";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['api_version' => 1, 'error' => $message] + $extra, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    exit;
};

if ($organism === '' || $id === '') $fail(400, 'organism and id are required.');
$go_src = moop_go_sources_param((string) ($_GET['go_sources'] ?? ''));   // lib/go_sources.php
if ($go_src['unknown']) {
    $fail(400, 'Unknown go_sources: ' . implode(', ', $go_src['unknown']) . '. Use: ' . implode(', ', array_keys(moop_go_sources())) . '.');
}

$L = moop_gene_lookup($organism, $id, $assembly, $gene_set);
if ($L['status'] === 'choose') {
    $choices = array_map(fn($c) => [
        'assembly_label' => $c['assembly_label'], 'assembly' => $c['assembly'], 'gene_set' => $c['gene_set'],
        'record' => moop_public_url('api/v1/gene.php?' . $q(['organism' => $organism, 'id' => $id,
            'assembly' => $c['assembly'], 'gene_set' => $c['gene_set'], 'format' => $format === 'md' ? 'md' : ''])),
    ], $L['choices']);
    $fail(300, "$id is in more than one gene set; choose one.", ['choices' => $choices]);
}
if ($L['status'] !== 'ok') $fail(404, "No gene $id in $organism that you can open.");

// ── Gather: the same reads the gene page makes ─────────────────────────────────────────────────
$config   = ConfigManager::getInstance();
$row      = $L['row'];
$gsids    = $L['gene_set_ids'];
$db       = $config->getPath('organism_data') . "/$organism/organism.sqlite";
$gene_id  = (string) $row['feature_uniquename'];
$asm      = (string) $row['genome_accession'];
$gs       = (string) $row['gene_set_name'];
$gs_dir   = $config->getPath('organism_data') . "/$organism/$asm/$gs";
$where    = ['organism' => $organism, 'assembly' => $asm, 'gene_set' => $gs];

$children  = getChildren($row['feature_id'], $db, $gsids);
$child_ids = array_column($children, 'feature_uniquename');
$big_gene  = count(childTreeFromRows($row['feature_id'], $children)) >= MOOP_BIG_GENE_TRANSCRIPTS;

$gm  = moop_gene_model($organism, $asm, $gs, $gene_id, (string) $row['feature_type']);
$loc = $gm['loc'] ?? moop_feature_coords_loc($gs_dir, $gene_id);

// Protein lengths, from the protein FASTA's index (a point lookup, as on the page).
$protein_len = [];
$protein_ids = array_column(array_filter($children, fn($c) => _fasta_key_for_type((string) $c['feature_type']) === 'protein'), 'feature_uniquename');
$pattern = $config->getArray('sequence_types', [])['protein']['pattern'] ?? null;
$pfasta  = $pattern ? (glob("$gs_dir/$pattern")[0] ?? null) : null;
if ($protein_ids && $pfasta && moop_fasta_index_available($pfasta)) {
    foreach (moop_fai_lookup("$pfasta.fai", $protein_ids) as $pid => $e) $protein_len[$pid] = (int) $e['len'];
}

// Transcripts (the gene's direct children), each with the protein it makes. A protein can sit
// any depth below its transcript (Smed: transcript -> cds -> protein), so climb from the protein.
$parent_of = array_column($children, 'parent_feature_id', 'feature_id');
$transcripts = [];
foreach ($children as $c) {
    if ((string) $c['parent_feature_id'] === (string) $row['feature_id']) {
        $transcripts[$c['feature_id']] = ['id' => $c['feature_uniquename'], 'type' => $c['feature_type']];
    }
}
foreach ($children as $p) {
    if (!isset($protein_len[$p['feature_uniquename']])) continue;
    for ($up = $p['feature_id'], $n = 0; $up !== null && !isset($transcripts[$up]) && $n < 20; $n++) {
        $up = $parent_of[$up] ?? null;
    }
    if ($up !== null && isset($transcripts[$up]) && !isset($transcripts[$up]['protein'])) {
        $transcripts[$up]['protein'] = ['id' => $p['feature_uniquename'], 'length_aa' => $protein_len[$p['feature_uniquename']]];
    }
}
$transcripts = array_values($transcripts);

// Annotations, merged across the gene and its transcripts: one entry per (source, accession), with
// the features that carry it. A big gene (hundreds of transcripts) gives the gene's own only, as the page does.
$ann_ids = $big_gene ? [$row['feature_id']] : array_merge([$row['feature_id']], array_column($children, 'feature_id'));
$annotations = [];
foreach (getAllAnnotationsForFeatures($ann_ids, $db) as $by_type) {
    foreach ($by_type as $type => $rows) {
        foreach ($rows as $a) {
            $acc   = (string) $a['annotation_accession'];
            $is_go = (bool) preg_match('/^GO:\d{7}$/', $acc);
            // A GO term is ONE entry however many sources assign it, flagged with each (go_sources=
            // keeps only some). Everything else: one entry per (source, accession), as before.
            $go_key = $is_go ? (moop_go_source_key((string) $a['annotation_source_name']) ?? (string) $a['annotation_source_name']) : null;
            if ($is_go && $go_src['keys'] !== null && !in_array($go_key, $go_src['keys'], true)) continue;
            $key = $is_go ? $acc : $a['annotation_source_name'] . "\x1f" . $acc;
            if ($is_go && isset($annotations[$type][$key]) && !in_array($go_key, $annotations[$type][$key]['sources'], true)) {
                $annotations[$type][$key]['sources'][] = $go_key;
                $annotations[$type][$key]['source']   .= '; ' . $a['annotation_source_name'];
            }
            if (!isset($annotations[$type][$key])) {
                $base = trim((string) $a['annotation_accession_url']);
                $item = ['source' => $a['annotation_source_name'], 'accession' => $acc,
                         'description' => $a['annotation_description'],
                         'score' => is_numeric($a['score']) ? $a['score'] + 0 : $a['score'],
                         'url' => $base !== '' ? $base . urlencode($acc) : null, 'on' => []];
                if ($is_go) {
                    $item['sources']   = [$go_key];
                    // The term's name alone: GO descriptions carry the whole definition
                    // ('protein binding: "Binding to a protein." [GOC:curators]'), which the
                    // Markdown leaves out -- 133 definitions crowded out the rest of the record.
                    $item['name'] = preg_match('/^(.*?): "/s', (string) $a['annotation_description'], $m) ? $m[1] : (string) $a['annotation_description'];
                    // GO terms: ID + name, no definition (Sofia 2026-11-17) -- the term page has it.
                    unset($item['description'], $item['score']);
                    $item['term_page'] = moop_public_url('tools/ontology_term.php?' . $q(['o' => 'GO', 'id' => $acc]));
                }
                $annotations[$type][$key] = $item;
            }
            $annotations[$type][$key]['on'][] = $a['feature_uniquename'];
        }
    }
}
$ann_out = [];
foreach ($annotations as $type => $items) {
    $items = array_values($items);
    foreach ($items as &$it) $it['on'] = array_values(array_unique($it['on']));
    unset($it);
    $ann_out[$type] = ['total' => count($items), 'items' => array_slice($items, 0, GENE_V1_CAPS['annotations_per_type'])];
}

// Expression: one call per experiment, as the page's Expression card (experiment access checked inside).
$expression = [];
$__e = moop_expression_gene_data($organism, $asm, $gs, $gene_id);
if ($__e) {
    foreach (moop_expression_summarize($__e['experiments'], $__e['values'])['experiments'] ?? [] as $x) {
        $expression[] = array_intersect_key($x, array_flip(['label', 'citation', 'call', 'level', 'top_group', 'top_mean', 'unit', 'threshold']));
    }
}

// Orthologs and gene family in related species: the rows of the page cards' own TSV downloads
// (moop_clade_rows(), shared with api/v1/orthologs.php).
['orthologs' => $orthologs, 'family' => $family] = moop_clade_rows($organism, $gs, [$gene_id, $L['ancestor_uniquename']], $child_ids);

// Curated anatomical expression (PAGE) and the gene's ids in other sources.
[$page, ] = moop_gene_page_section($organism, $gene_id, $child_ids);
$anatomy = [];
foreach ($page['curations'] ?? [] as $c) {
    $lab = fn($t) => $t ? ['id' => $t, 'label' => $page['labels'][$t] ?? ''] : null;
    $anatomy[] = ['term' => $lab($c['term_id']), 'stage' => $lab($c['stage_id']), 'specimen' => $lab($c['specimen_id']),
                  'evidence' => $lab($c['evidence_id']), 'accession' => $c['accession'], 'pmid' => $c['pmid'],
                  'reference' => trim("{$c['authors']} ({$c['year']}) {$c['title']} {$c['journal']}")];
}
// One entry per (id, source): the crosswalk returns a row per path to each id (via each transcript and
// reference gene), so MACF1 came back as 3,907 rows of 29 ids. The gene page de-duplicates as it groups.
$aka = [];
foreach ($page['aka'] ?? [] as $m) $aka[$m['source'] . "\x1f" . $m['accession']] = ['accession' => $m['accession'], 'source' => $m['source']];
$aka = array_values($aka);

// Links.
$links = ['gene_page' => moop_public_url('tools/parent.php?' . $q(['organism' => $organism, 'uniquename' => $gene_id] + ['assembly' => $asm, 'gene_set' => $gs])),
          'record_json' => moop_public_url('api/v1/gene.php?' . $q(['organism' => $organism, 'id' => $gene_id] + $where)),
          'record_markdown' => moop_public_url('api/v1/gene.php?' . $q(['organism' => $organism, 'id' => $gene_id] + $where + ['format' => 'md'])),
          'annotations_csv' => moop_public_url('api/v1/annotations.php?' . $q(['organism' => $organism, 'uniquename' => $gene_id]))];
$jb_file = $config->getPath('metadata_path') . "/jbrowse2-configs/assemblies/{$organism}_{$asm}.json";
if ($loc && file_exists($jb_file) && moop_tool_on('genome_browser')) {
    $links['genome_browser'] = moop_public_url('jbrowse2.php?' . $q(['organism' => $organism, 'assembly' => $asm, 'loc' => $loc['loc_string']]));
}

$naming = array_map(fn($s) => array_filter(['kind' => $s['kind'], 'text' => $s['naming_text'],
    'accession' => $s['accession'] ?? null, 'url' => $s['url'] ?? null], fn($v) => $v !== null && $v !== ''),
    getGeneNaming($row['feature_id'], $db)['statements'] ?? []);

$cap = fn(array $list, string $k) => ['total' => count($list), 'items' => array_slice($list, 0, GENE_V1_CAPS[$k])];
$record = [
    'api_version' => 1,
    'site'        => $config->getString('siteTitle', ''),
    'gene' => [
        'id'          => $gene_id,
        'symbol'      => moop_gene_symbol($row['feature_name'], $gene_id, $child_ids),
        'name'        => $row['feature_name'],
        'description' => $row['feature_description'],
        'type'        => $row['feature_type'],
        'asked_for'   => $id,
        'organism'    => $organism,
        'species'     => trim($row['genus'] . ' ' . $row['species'] . ' ' . $row['subtype']),
        'common_name' => $row['common_name'],
        'taxon_id'    => $row['taxon_id'],
        'assembly'    => $asm,
        'assembly_name' => genome_display_name((string) $row['genome_name'], $asm),
        'gene_set'    => $gs,
    ],
    'location'    => $loc ? array_intersect_key($loc, array_flip(['seqname', 'start', 'end', 'strand'])) : null,
    'naming'      => $naming,
    'transcripts' => $transcripts,
    'annotations' => (object) $ann_out,
    'go_sources'  => ['applied' => $go_src['keys'] ?? 'all', 'legend' => moop_go_sources_legend()],
    'expression'  => $expression,
    'orthologs_related_species'   => $cap($orthologs, 'orthologs'),
    'gene_family_related_species' => $cap($family, 'gene_family'),
    'anatomy_expression' => $cap($anatomy, 'anatomy'),
    'also_known_as'      => $cap($aka, 'also_known_as'),
    'links'       => $links,
    'notes'       => array_values(array_filter([
        $big_gene ? 'This gene has ' . count($transcripts) . ' transcripts; annotations listed are the gene\'s own. Each transcript has its own record.' : null,
        'Lists are capped; "total" is the full count. The complete annotation table is links.annotations_csv.',
    ])),
];

// &download=1: a file to save (the gene page's Download menu), named like every other MOOP download.
require_once __DIR__ . '/../../lib/download_filename.php';
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . addcslashes(
        moop_download_filename('gene_record', [$organism, $asm, $gs, $gene_id], $format === 'md' ? 'md' : 'json'), '"\\') . '"');
}
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ── Markdown ───────────────────────────────────────────────────────────────────────────────────
header('Content-Type: text/markdown; charset=utf-8');
$cell = fn($v) => str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], (string) $v);
$link = fn($text, $url) => $url ? '[' . $cell($text) . '](' . $url . ')' : $cell($text);
$table = function (array $head, array $rows) use ($cell): string {
    $out = '| ' . implode(' | ', array_map($cell, $head)) . " |\n|" . str_repeat(' --- |', count($head)) . "\n";
    foreach ($rows as $r) $out .= '| ' . implode(' | ', array_map(fn($v) => $v, $r)) . " |\n";
    return $out;
};
$g = $record['gene'];
$md  = '# ' . ($g['symbol'] !== '' ? "{$g['symbol']} ({$g['id']})" : $g['id']) . "\n\n";
$md .= $g['description'] ? $cell($g['description']) . "\n\n" : '';
$md .= "- **Organism:** *{$g['species']}*" . ($g['common_name'] ? " ({$g['common_name']})" : '') . "\n";
$md .= "- **Assembly / gene set:** " . ($g['assembly_name'] ?: $g['assembly']) . " / {$g['gene_set']}\n";
if ($record['location']) {
    $l = $record['location'];
    $md .= "- **Location:** {$l['seqname']}:" . number_format($l['start']) . '-' . number_format($l['end']) . " ({$l['strand']})\n";
}
$md .= "- **Gene page:** <{$links['gene_page']}>\n";
if (isset($links['genome_browser'])) $md .= "- **Genome browser:** <{$links['genome_browser']}>\n";
$md .= "- **Source:** {$record['site']}, " . gmdate('Y-m-d') . "\n";

if ($naming) {
    $md .= "\n## How this gene was named\n\n";
    foreach ($naming as $n) $md .= '- ' . $cell($n['text'] ?? '') . (isset($n['url']) ? " ({$link($n['accession'] ?? 'link', $n['url'])})" : '') . "\n";
}
if ($transcripts) {
    $md .= "\n## Transcripts\n\n" . $table(['Transcript', 'Type', 'Protein', 'Length (aa)'], array_map(fn($t) =>
        [$cell($t['id']), $cell($t['type']), $cell($t['protein']['id'] ?? ''), (string) ($t['protein']['length_aa'] ?? '')], $transcripts));
}
// Order for a reader that stops early (an assistant reading the first ~15,000 characters): the
// short sections first -- expression, the annotation types (domains, orthologs, location ...),
// orthologs in related species -- then the long ones: curated anatomy, other IDs, and GO last.
// GO terms by name only (see 'name' above): 133 definitions crowded out the rest of the record.
// Homolog descriptions from Ensembl are raw FASTA headers ("ENSACAP... pep primary_assembly:AnoCar2.0v2:
// GL343481.1:... gene:ENSACAG... gene_biotype:protein_coding ... gene_symbol:MACF1 description:..."):
// in Markdown, the symbol and description alone, or the gene ID when it has neither.
$ens_desc = function ($d) {
    $d = (string) $d;
    if (!preg_match('/\spep\s+\S+:\S+.*\bgene:(\S+)/', $d, $g)) return $d;
    $sym  = preg_match('/\bgene_symbol:(\S+)/', $d, $m) ? $m[1] : '';
    $desc = preg_match('/\bdescription:(.*?)(?:\s*\[Source:.*)?$/', $d, $m) ? trim($m[1]) : '';
    return trim($sym . ($sym !== '' && $desc !== '' ? ' -- ' : '') . $desc) ?: 'gene ' . $g[1];
};
$ann_md = function (bool $go) use ($ann_out, $table, $link, $cell, $links, $ens_desc): string {
    $out = '';
    foreach ($ann_out as $type => $block) {
        if ((stripos($type, 'gene ontology') !== false) !== $go) continue;
        $out .= "\n## $type\n\n";
        if ($block['total'] > count($block['items'])) $out .= "{$block['total']} in all; first " . count($block['items']) . " shown. Full table: <{$links['annotations_csv']}>\n\n";
        $out .= $go
            ? $table(['GO ID', 'Name', 'Source'], array_map(fn($a) =>
                [$link($a['accession'], $a['term_page'] ?? $a['url']), $cell($a['name']), $cell($a['source'])], $block['items']))
            : $table(['Accession', 'Description', 'Source', 'Score'], array_map(fn($a) =>
                [$link($a['accession'], $a['url']), $cell($ens_desc($a['description'])), $cell($a['source']), $cell($a['score'])], $block['items']));
    }
    return $out;
};
if ($expression) {
    $md .= "\n## Expression\n\n" . $table(['Experiment', 'Expressed?', 'Level', 'Highest in', 'Mean', 'Reference'], array_map(fn($x) =>
        [$cell($x['label'] ?? ''), $cell($x['call'] ?? ''), $cell($x['level'] ?? ''), $cell($x['top_group'] ?? ''),
         isset($x['top_mean']) ? round((float) $x['top_mean'], 2) . ' ' . $cell($x['unit'] ?? '') : '', $cell($x['citation'] ?? '')], $expression));
}
$md .= $ann_md(false);
// The Markdown is read by assistants with a limited read (~15,000 characters, 2026-11-17 test), so
// its long lists stop at MD_CAP rows and point to the call that returns all of them; the JSON keeps
// GENE_V1_CAPS. References shortened to "First author et al. (year)" -- the full title repeated on
// every anatomy row was most of that section.
$MD_CAP  = 20;
$more    = fn(array $b, int $shown, string $where) => $b['total'] > $shown ? "{$b['total']} in all; first $shown shown. All: <$where>\n\n" : '';
$short_ref = fn($r) => preg_match('/^(.*?\(\d{4}\))/', (string) $r, $m) ? $m[1] : (string) $r;
$v1 = fn(string $ep, array $p) => moop_public_url('api/v1/' . $ep . '?' . $q($p + $where));
foreach (['orthologs_related_species' => ['Orthologs in related species', []], 'gene_family_related_species' => ['Gene family in related species', ['family' => 1]]] as $k => [$title, $extra]) {
    $b = $record[$k];
    if (!$b['total']) continue;
    $rows = array_slice($b['items'], 0, $MD_CAP);
    $md .= "\n## $title\n\n" . $more($b, count($rows), $v1('orthologs.php', ['organism' => $organism, 'ids' => $gene_id] + $extra + ['format' => 'tsv']));
    $md .= $table(['Organism', 'Gene', 'Name', 'Relationship', 'Clade'], array_map(fn($r) =>
        [$cell(str_replace('_', ' ', $r['gene2_organism'] ?? '')), $cell($r['gene2_id'] ?? ''), $cell($r['gene2_name'] ?? ''),
         $cell($r['relationship'] ?? ''), $cell($r['clade'] ?? '')], $rows));
}
if ($record['anatomy_expression']['total']) {
    $b = $record['anatomy_expression'];
    $rows = array_slice($b['items'], 0, $MD_CAP);
    $md .= "\n## Anatomical expression (curated)\n\n" . $more($b, count($rows), $links['gene_page']);
    $md .= $table(['Where', 'Stage', 'Evidence', 'Reference'], array_map(fn($a) =>
        [$cell($a['term']['label'] ?? $a['term']['id'] ?? ''), $cell($a['stage']['label'] ?? ''), $cell($a['evidence']['label'] ?? ''),
         $a['pmid'] ? $link($short_ref($a['reference']), 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($a['pmid']) . '/') : $cell($short_ref($a['reference']))], $rows));
}
if ($record['also_known_as']['total']) {
    $b = $record['also_known_as'];
    $rows = array_slice($b['items'], 0, $MD_CAP);
    $md .= "\n## Also known as\n\n" . $more($b, count($rows), $links['gene_page']);
    $md .= implode(', ', array_map(fn($a) => $cell($a['accession']) . ' (' . $cell($a['source']) . ')', $rows)) . "\n";
}
$md .= $ann_md(true);
$md .= "\n---\n" . implode("\n", array_map(fn($n) => '_' . str_replace('links.annotations_csv', "<{$links['annotations_csv']}>", $n) . '_', $record['notes'])) . "\nJSON: <{$links['record_json']}>\n";
echo $md;
