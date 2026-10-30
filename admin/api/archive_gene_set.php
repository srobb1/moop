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
 * Retires it (lib/retire.php, gene-set level): the directory moves to organisms/.retired/
 * with its JBrowse track files, its group entries are saved and removed, and it can be
 * restored from Manage Organisms → Retired. Until 2026-10-30 this called archiveGeneSet(),
 * which moved it to archived_gene_sets/ — inside the docroot and served over HTTP — and
 * deleted the group entries and track JSON with no record. That function is gone.
 *
 * POST params: organism, assembly, gene_set
 */

require_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/retire.php';

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

$username = (string)($_SESSION['username'] ?? 'admin');
$r = moop_retire('gene_set', $organism, $assembly, $gene_set, $username, $config);
$result = ['success' => $r['ok'], 'error' => $r['error'], 'removed' => $r['done'],
           'archived_to' => $r['bundle'] ? 'organisms/' . MOOP_RETIRED_DIR . '/' . $r['bundle'] : null];

if ($result['success']) {
    $metadata_path = $config->getPath('metadata_path');   // the retire wrote the change-log line

    // Refresh this one organism's entry in organisms/.organism_cache.json synchronously.
    // Without this, the dashboard/Manage Groups "orphaned in database" warning would
    // keep showing this tuple until the next periodic cache refresh (housekeeping runs
    // this at most every 12h — see housekeeping_refresh_organism_cache_if_stale()),
    // even though the archive above already fixed it. A single-organism rescan is fast
    // enough to do inline here rather than waiting on that schedule.
    // The archive itself (the destructive move + reference cleanup) has already
    // completed successfully at this point. The cache refresh below is a
    // convenience so the dashboard updates immediately — it must never be allowed
    // to fail the request, or an error here masks a completed archive as a 500
    // (which the browser then sees as an "Unexpected token '<'" JSON parse error).
    // On failure we log and flag it; housekeeping_refresh_organism_cache_if_stale()
    // will pick up the change within its normal interval regardless.
    try {
        $sequence_types     = $config->getSequenceTypes();
        $taxonomy_tree_file = "$metadata_path/taxonomy_tree_config.json";
        $groups_file        = "$metadata_path/organism_assembly_groups.json";
        $groups_data        = getGroupData();
        getCachedOrganismsInfo($organism_data, $sequence_types, $taxonomy_tree_file, $groups_data, $groups_file, false, null, [$organism]);
    } catch (\Throwable $e) {
        error_log('archive_gene_set: archive succeeded but organism-cache refresh failed: ' . $e->getMessage());
        $result['cache_refresh_deferred'] = true;
    }
}

echo json_encode($result);
