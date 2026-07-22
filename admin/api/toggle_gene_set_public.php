<?php
/**
 * Toggle the `public` visibility flag on one gene-set entry in
 * organism_assembly_groups.json.
 *
 * Public is a per-gene-set property of the data: when true, the gene set is
 * visible to everyone (access level PUBLIC) while staying in its existing
 * taxonomic groups. Read by entry_is_public() (includes/access_control.php).
 *
 * POST: organism, assembly, gene_set
 * Returns JSON: { success, public, message }
 *
 * Bootstraps via admin_init.php → admin auth + CSRF verified automatically.
 */

include_once __DIR__ . '/../admin_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$organism = trim($_POST['organism'] ?? '');
$assembly = trim($_POST['assembly'] ?? '');
$gene_set = trim($_POST['gene_set'] ?? '');

if ($organism === '' || $assembly === '') {
    echo json_encode(['success' => false, 'message' => 'organism and assembly are required']);
    exit;
}

$metadata_path = $config->getPath('metadata_path');
$groups_file   = "$metadata_path/organism_assembly_groups.json";

if (!file_exists($groups_file) || !is_writable($groups_file)) {
    echo json_encode(['success' => false, 'message' => 'organism_assembly_groups.json is not writable']);
    exit;
}

$data = loadJsonFile($groups_file, []);

$found     = false;
$is_public = false;
foreach ($data as &$entry) {
    if (($entry['organism'] ?? '') === $organism
        && ($entry['assembly'] ?? '') === $assembly
        && (string)($entry['gene_set'] ?? '') === $gene_set) {
        $entry['public'] = !(($entry['public'] ?? false) === true);
        $is_public = $entry['public'];
        $found     = true;
        break;
    }
}
unset($entry);

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Gene set not found']);
    exit;
}

if (file_put_contents($groups_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save']);
    exit;
}

// Change log, matching the other Manage Groups mutations.
$log_dir = "$metadata_path/change_log";
if (is_dir($log_dir) && is_writable($log_dir)) {
    $who   = function_exists('get_username') ? (get_username() ?: 'admin') : 'admin';
    $state = $is_public ? 'PUBLIC' : 'restricted';
    $line  = sprintf("[%s] %s set %s / %s / %s -> %s\n",
        date('Y-m-d H:i:s'), $who, $organism, $assembly, $gene_set, $state);
    @file_put_contents("$log_dir/manage_groups.log", $line, FILE_APPEND);
}

echo json_encode(['success' => true, 'public' => $is_public]);
