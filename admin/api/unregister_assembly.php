<?php
/**
 * Admin API: Unregister an orphaned JBrowse assembly
 *
 * Intended for the registrations flagged by the dashboard's "Data Health Issues" widget as
 * orphaned_jbrowse — the assembly is registered in JBrowse, but its source data under
 * organisms/ was renamed or removed outside MOOP, so the reference.fasta symlink that
 * registration created is dangling and serves HTTP 404 to every user who opens it.
 *
 * Removes only artifacts that registration itself created (registry JSON, data/genomes/
 * directory, trix index, registered sheet, empty track skeleton). Never touches organisms/.
 *
 * DELIBERATELY RESTRICTED to tuples that getOrphanedJBrowseRegistrations() currently
 * reports. A healthy registration cannot be removed through this endpoint: the whole point
 * is to fix what the health check found, and re-checking here means a request can never act
 * on a stale page the admin left open after the problem was already resolved.
 *
 * POST params: organism, assembly
 */

require_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/jbrowse/gene_set_functions.php';
require_once __DIR__ . '/../../lib/functions_data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism = trim($_POST['organism'] ?? '');
$assembly = trim($_POST['assembly'] ?? '');

if ($organism === '' || $assembly === '') {
    echo json_encode(['success' => false, 'error' => 'Organism and assembly are required']);
    exit;
}

foreach (['organism' => $organism, 'assembly' => $assembly] as $param => $val) {
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $val)) {
        echo json_encode(['success' => false, 'error' => "Invalid $param: $val"]);
        exit;
    }
}

// Re-check against live state: only a registration that is orphaned RIGHT NOW may be removed.
$organism_data = $config->getPath('organism_data');
$orphans       = getOrphanedJBrowseRegistrations($organism_data);

// If EVERY registration looks orphaned, the organisms tree is unreachable (unmounted share,
// wrong organism_data path) rather than the assemblies being individually broken. Refuse:
// the registrations are almost certainly fine, and removing them would mean rebuilding all
// of them once the data comes back.
$total = countJBrowseRegistrations();
if ($total > 1 && count($orphans) === $total) {
    echo json_encode([
        'success' => false,
        'error'   => "All $total JBrowse registrations report missing source data, which points at the "
                   . 'organism data directory being unavailable rather than at this assembly. '
                   . 'Nothing was changed — check that the data directory is mounted and readable first.',
    ]);
    exit;
}

$is_orphaned = false;
foreach ($orphans as $o) {
    if ($o['organism'] === $organism && $o['assembly'] === $assembly) {
        $is_orphaned = true;
        break;
    }
}
if (!$is_orphaned) {
    echo json_encode([
        'success' => false,
        'error'   => "$organism / $assembly is not an orphaned registration — its source data resolves. "
                   . 'Refresh the page; nothing was changed.',
    ]);
    exit;
}

// Note: no audit-trail entry is written. MOOP has no audit log today, and logs/error.log
// is not it — see notes/ERROR_LOG_IMPROVEMENTS_PLAN.md, where the viewer's core problem is
// already that routine messages drown the real failures. The removed/kept lists are
// returned to the caller and shown in the UI instead.
$result = unregisterAssembly($organism, $assembly, $config);

echo json_encode($result);
