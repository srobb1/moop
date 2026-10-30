<?php
/**
 * Admin API: retire or restore an organism, assembly or gene set (lib/retire.php,
 * notes/RETIRE_DATA_PLAN.md).
 *
 * POST action=preview  level=organism|assembly|gene_set  organism=…  [assembly=…]  [gene_set=…]
 *                      → what retiring it would do (nothing changes)
 * POST action=retire   (same)  confirm=<its name, typed by the admin: the organism, assembly or gene set>
 * POST action=restore  bundle=…      (a directory name under organisms/.retired/)
 *                      settings_only=1 → only its groups and JBrowse settings, when it was copied
 *                      back the normal way (its data is already in place)
 *
 * Auth + CSRF come from admin_init.php. Paths are never taken from the request: every name must
 * be a directory that is there now (moop_retire_target()), the bundle one moop_retired_list() lists.
 */

require_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/retire.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$action   = (string)($_POST['action'] ?? '');
$level    = (string)($_POST['level'] ?? 'organism');
$organism = (string)($_POST['organism'] ?? '');
$assembly = (string)($_POST['assembly'] ?? '');
$gene_set = (string)($_POST['gene_set'] ?? '');
$username = (string)($_SESSION['username'] ?? 'admin');

if ($action === 'preview') {
    $p = moop_retire_preview($level, $organism, $assembly, $gene_set, $config);
    echo json_encode(['ok' => $p['ok'], 'error' => $p['error'], 'steps' => $p['steps'], 'notes' => $p['notes']]);
} elseif ($action === 'retire') {
    // The typed name is the confirmation: a stray click or a forged-but-tokened request cannot
    // retire anything without it.
    $name = ['organism' => $organism, 'assembly' => $assembly, 'gene_set' => $gene_set][$level] ?? '';
    if ($name === '' || ($_POST['confirm'] ?? '') !== $name) {
        echo json_encode(['ok' => false, 'error' => 'Type its name exactly to confirm.']);
        exit;
    }
    echo json_encode(moop_retire($level, $organism, $assembly, $gene_set, $username, $config));
} elseif ($action === 'restore') {
    $bundle = (string)($_POST['bundle'] ?? '');
    if (!in_array($bundle, array_column(moop_retired_list($config), 'bundle'), true)) {
        echo json_encode(['ok' => false, 'error' => 'No such retired bundle.']);
        exit;
    }
    echo json_encode(moop_restore($bundle, $username, $config, ($_POST['settings_only'] ?? '') === '1'));
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
