<?php
/**
 * Admin API: Archive an orphaned gene set
 *
 * Intended for gene sets flagged by the dashboard's "Data Health Issues" widget as
 * orphaned_gene_set_directory — present on disk (and possibly still in groups.json /
 * JBrowse config) but no longer present in the organism's database, typically because
 * the DB was rebuilt elsewhere and the old gene set was dropped without cleaning up
 * this server. Does NOT touch organism.sqlite — by definition there's nothing there
 * for this tuple.
 *
 * Moves the source data directory to organisms/_archived_gene_sets/{org}/{asm}/ and
 * strips every derived reference: JBrowse track JSON, assembly primaryGeneTracks
 * entry, bgzip/tabix/trix build artifacts, groups.json access entries.
 *
 * POST params: organism, assembly, gene_set
 */

require_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/jbrowse/gene_set_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism = trim($_POST['organism'] ?? '');
$assembly = trim($_POST['assembly'] ?? '');
$gene_set = trim($_POST['gene_set'] ?? '');

if ($organism === '' || $assembly === '' || $gene_set === '') {
    echo json_encode(['success' => false, 'error' => 'Organism, assembly, and gene_set are required']);
    exit;
}

foreach (['organism' => $organism, 'assembly' => $assembly, 'gene_set' => $gene_set] as $param => $val) {
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $val)) {
        echo json_encode(['success' => false, 'error' => "Invalid $param: $val"]);
        exit;
    }
}

// Safety net: refuse unless this tuple is actually a confirmed DB orphan. Prevents
// this endpoint from ever being used (accidentally or otherwise) to nuke a gene set
// that's still live in the database.
$organism_data = $config->getPath('organism_data');
$db_file       = "$organism_data/$organism/organism.sqlite";
$validation    = file_exists($db_file) ? validateAssemblyDirectories($db_file, "$organism_data/$organism") : null;
$is_confirmed_orphan = false;
if ($validation) {
    foreach ($validation['mismatches'] ?? [] as $mm) {
        if (($mm['type'] ?? '') === 'orphaned_gene_set_directory'
            && ($mm['assembly_dir'] ?? '') === $assembly
            && ($mm['gene_set_name'] ?? '') === $gene_set) {
            $is_confirmed_orphan = true;
            break;
        }
    }
}
if (!$is_confirmed_orphan) {
    echo json_encode(['success' => false, 'error' => 'This gene set is still present in the database — refusing to archive. Re-run the database rebuild step, or remove it from the database first if this is intentional.']);
    exit;
}

$result = archiveGeneSet($organism, $assembly, $gene_set, $config);

if ($result['success']) {
    $metadata_path = $config->getPath('metadata_path');
    $log_dir       = "$metadata_path/change_log";
    if (!is_dir($log_dir)) @mkdir($log_dir, 0775, true);
    $username = $_SESSION['username'] ?? 'unknown';
    $entry = sprintf(
        "[%s] ARCHIVE_GENE_SET by %s | Organism: %s | Assembly: %s | Gene set: %s | Archived to: %s | Removed: %s\n",
        date('Y-m-d H:i:s'), $username, $organism, $assembly, $gene_set,
        $result['archived_to'], implode(', ', $result['removed'])
    );
    @file_put_contents("$log_dir/manage_groups.log", $entry, FILE_APPEND);

    // Refresh this one organism's entry in organisms/.organism_cache.json synchronously.
    // Without this, the dashboard/Manage Groups "orphaned in database" warning would
    // keep showing this tuple until the next periodic cache refresh (housekeeping runs
    // this at most every 12h — see housekeeping_refresh_organism_cache_if_stale()),
    // even though the archive above already fixed it. A single-organism rescan is fast
    // enough to do inline here rather than waiting on that schedule.
    $sequence_types     = $config->getSequenceTypes();
    $taxonomy_tree_file = "$metadata_path/taxonomy_tree_config.json";
    $groups_file        = "$metadata_path/organism_assembly_groups.json";
    $groups_data         = getGroupData();
    getCachedOrganismsInfo($organism_data, $sequence_types, $taxonomy_tree_file, $groups_data, $groups_file, false, null, [$organism]);
}

echo json_encode($result);
