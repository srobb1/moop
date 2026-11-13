<?php
/**
 * ID Translator (simrbase: "Rosetta Stone Transcript Mapper") -- paste ids of any kind (older
 * transcriptomes, ESTs, array probes, gene models) and get the reference gene each maps to, plus
 * the ids of that same gene in the sources you pick. Data: the organism's crosswalk.sqlite
 * (lib/crosswalk.php, format docs/CROSSWALK_FORMAT.md). Plan: notes/PLANOSPHERE_PAGE_ROSETTA_PLAN.md
 * step 4. Read-only; a GET form, so a result is a shareable link.
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/crosswalk.php';
include_once __DIR__ . '/../includes/layout.php';

const MOOP_CROSSWALK_MAX_IDS  = 500;
const MOOP_CROSSWALK_MAX_ROWS = 5000;

// The organisms with a crosswalk this visitor may see; ?organism= picks one, else the first.
$organisms = [];
foreach (array_keys(moop_crosswalk_sources()) as $o) {
    if (is_file(moop_crosswalk_file($o)) && (moop_session_is_admin() || is_public_organism($o) || has_access('COLLABORATOR', $o))) $organisms[] = $o;
}
$organism = in_array($_GET['organism'] ?? '', $organisms, true) ? (string) $_GET['organism'] : ($organisms[0] ?? '');

$sources  = $organism ? moop_crosswalk_source_list($organism) : [];
$ids_text = trim((string) ($_GET['ids'] ?? ''));
$into     = array_values(array_intersect(array_map('strval', (array) ($_GET['into'] ?? [])), array_keys($sources)));
$typed    = array_slice(preg_split('/[\s,;]+/', $ids_text, -1, PREG_SPLIT_NO_EMPTY), 0, MOOP_CROSSWALK_MAX_IDS + 1);
$too_many = count($typed) > MOOP_CROSSWALK_MAX_IDS;
$typed    = array_slice($typed, 0, MOOP_CROSSWALK_MAX_IDS);

$result = null;
if ($organism && $typed) {
    $result = moop_crosswalk_translate($organism, $typed, $into, moop_search_permitted_scope($organism), MOOP_CROSSWALK_MAX_ROWS);
}
$cfg = $organism ? moop_crosswalk_sources()[$organism] : [];

// ?download=tsv: the same result as a file, written on the server by the one TSV writer the gene
// page's "Same gene in this species" download also uses (organism, assembly, gene set columns; the
// whole result, not the table's current page/sort; English headings in every language).
if (($_GET['download'] ?? '') === 'tsv' && $result && $result['rows']) {
    require_once __DIR__ . '/../lib/download_filename.php';
    $__fn = moop_download_filename('id_translator', [$organism], 'tsv');
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addcslashes($__fn, '"\\') . '"');
    echo moop_crosswalk_translate_tsv($organism, $result, array_values(array_filter((array) ($cfg['always_show'] ?? []), 'is_string')), (bool) $into);
    exit;
}
$tool_title = trim((string) ($cfg['name'] ?? '')) ?: 'ID Translator';

echo render_display_page(__DIR__ . '/pages/crosswalk.php', [
    'organism' => $organism, 'organisms' => $organisms, 'sources' => $sources, 'ids_text' => $ids_text,
    'source_groups' => $organism ? moop_crosswalk_source_groups($organism) : [],
    'into' => $into, 'result' => $result, 'too_many' => $too_many, 'tool_title' => $tool_title,
    'always' => array_values(array_filter((array) ($cfg['always_show'] ?? []), 'is_string')),
    'source_about' => array_map(fn($c) => (string) ($c['about'] ?? ''), (array) ($cfg['sources'] ?? [])),
    'max_ids' => MOOP_CROSSWALK_MAX_IDS, 'max_rows' => MOOP_CROSSWALK_MAX_ROWS,
    'page_script' => [moop_url_base() . '/js/crosswalk.js'],
], $tool_title);
