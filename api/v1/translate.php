<?php
/**
 * api/v1/translate — the Rosetta Stone ID translator: the same gene under its other IDs (other
 * assemblies, older transcriptomes, array probes ...).
 *
 * GET organism (required), ids (space / comma separated, up to 500), into[] (optional: only these
 *     sources), format=json (default) | tsv.
 *     organism alone: the sources this organism's translator knows ({ sources }).
 *
 * The same translation, columns and caps as the ID Translator page (tools/crosswalk.php:
 * moop_crosswalk_translate() + moop_crosswalk_translate_tsv()), but always data: no matches is an
 * empty result, never the page. Organisms the visitor cannot open: 404, as if absent.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/crosswalk.php';

const V1_TRANSLATE_MAX_IDS = 500, V1_TRANSLATE_MAX_ROWS = 5000;   // as tools/crosswalk.php

header('Cache-Control: private, no-store');
$format   = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
$organism = (string) ($_GET['organism'] ?? '');
$json = function (int $code, array $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
};

// The same rule as the ID Translator page: an organism with a crosswalk this visitor may see.
$open = isset(moop_crosswalk_sources()[$organism]) && is_file(moop_crosswalk_file($organism))
     && (moop_session_is_admin() || is_public_organism($organism) || has_access('COLLABORATOR', $organism));
if (!$open) $json(404, ['error' => "No ID translator for \"$organism\" that you can open."]);

$sources = moop_crosswalk_source_list($organism);
$typed   = preg_split('/[\s,;]+/', trim((string) ($_GET['ids'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
if (!$typed) $json(200, ['organism' => $organism, 'sources' => $sources]);
$too_many = count($typed) > V1_TRANSLATE_MAX_IDS;
$typed    = array_slice($typed, 0, V1_TRANSLATE_MAX_IDS);
$into     = array_values(array_intersect(array_map('strval', (array) ($_GET['into'] ?? [])), array_keys($sources)));

$result = moop_crosswalk_translate($organism, $typed, $into, V1_TRANSLATE_MAX_ROWS);
$cfg    = moop_crosswalk_sources()[$organism];
$tsv    = $result['rows'] ? moop_crosswalk_translate_tsv($organism, $result,
            array_values(array_filter((array) ($cfg['always_show'] ?? []), 'is_string')), (bool) $into) : '';

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    echo $tsv;
    exit;
}
$lines = array_values(array_filter(explode("\n", $tsv), fn($l) => $l !== ''));
$head  = $lines ? explode("\t", array_shift($lines)) : [];
$rows  = array_map(fn($l) => array_combine($head, array_pad(explode("\t", $l), count($head), '')), $lines);
$found = array_unique(array_column($rows, 'from_id'));
$json(200, [
    'organism'  => $organism,
    'rows'      => $rows,
    'not_found' => array_values(array_diff($typed, $found)),
    'notes'     => array_values(array_filter([
        $too_many ? 'Only the first ' . V1_TRANSLATE_MAX_IDS . ' IDs were translated.' : null,
        count($rows) >= V1_TRANSLATE_MAX_ROWS ? 'Stopped at ' . V1_TRANSLATE_MAX_ROWS . ' rows.' : null,
    ])),
]);
