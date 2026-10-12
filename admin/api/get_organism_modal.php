<?php
/**
 * On-demand organism modal renderer.
 * Returns HTML for a single Bootstrap modal-dialog based on type + organism.
 *
 * This file only validates the request and dispatches. Each modal's data is gathered in
 * lib/organism_modals.php and displayed by admin/pages/organism_modals/{type}.php.
 */
include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/blast_functions.php';
include_once __DIR__ . '/../../lib/functions_data.php';
include_once __DIR__ . '/../../lib/organism_modals.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$type     = $_POST['type']     ?? '';
$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';

if (!in_array($type, ['db', 'metadata', 'asm', 'status'], true)) {
    http_response_code(400);
    echo 'Invalid modal type';
    exit;
}

// Organism names are directory names: letters, digits, underscores, hyphens, dots.
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $organism)) {
    http_response_code(400);
    echo 'Invalid organism name';
    exit;
}

$organism_data      = $config->getPath('organism_data');
$metadata_path      = $config->getPath('metadata_path');
$sequence_types     = $config->getSequenceTypes();
$groups_data        = getGroupData();
$taxonomy_tree_file = $metadata_path . '/taxonomy_tree_config.json';

$cache_file = moop_organism_cache_file();
if (!file_exists($cache_file)) {
    http_response_code(503);
    echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-warning">Cache unavailable — try refreshing the cache.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
    exit;
}

$raw_cache = loadJsonFile($cache_file, []);
$organisms = $raw_cache['data'] ?? [];

if (!isset($organisms[$organism])) {
    http_response_code(404);
    echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-danger">Organism not found. Try refreshing the cache.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
    exit;
}

$data = $organisms[$organism];

header('Content-Type: text/html; charset=utf-8');

switch ($type) {
    case 'db':
        $vars = organism_modal_db_data($organism, $data, $organism_data);
        if ($vars === null) {
            echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-warning">No database validation data available.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
            break;
        }
        organism_modal_render('db', $vars);
        break;
    case 'metadata':
        organism_modal_render('metadata', organism_modal_metadata_data($organism, $data, $organism_data));
        break;
    case 'asm':
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $assembly)) {
            http_response_code(400);
            echo 'Invalid assembly name';
            exit;
        }
        organism_modal_render('asm', organism_modal_asm_data($organism, $assembly, $data, $sequence_types, $groups_data, $organism_data));
        break;
    case 'status':
        organism_modal_render('status', organism_modal_status_data($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types));
        break;
}
