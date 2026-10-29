<?php
/**
 * Admin API: retire or restore an organism (lib/retire.php, notes/RETIRE_DATA_PLAN.md).
 *
 * POST action=preview  organism=…   → what retiring it would do (nothing changes)
 * POST action=retire   organism=…   confirm=<the organism name, typed by the admin>
 * POST action=restore  bundle=…      (a directory name under organisms/.retired/)
 *
 * Auth + CSRF come from admin_init.php. Paths are never taken from the request: the organism
 * must be one moop_organism_dirs() lists, the bundle one moop_retired_list() lists.
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
$organism = (string)($_POST['organism'] ?? '');
$username = (string)($_SESSION['username'] ?? 'admin');

if ($action === 'preview') {
    $p = moop_retire_preview($organism, $config);
    echo json_encode(['ok' => $p['ok'], 'error' => $p['error'], 'steps' => $p['steps']]);
} elseif ($action === 'retire') {
    // The typed name is the confirmation: a stray click or a forged-but-tokened request cannot
    // retire an organism without it.
    if (($_POST['confirm'] ?? '') !== $organism || $organism === '') {
        echo json_encode(['ok' => false, 'error' => 'Type the organism\'s name exactly to confirm.']);
        exit;
    }
    echo json_encode(moop_retire_organism($organism, $username, $config));
} elseif ($action === 'restore') {
    $bundle = (string)($_POST['bundle'] ?? '');
    if (!in_array($bundle, array_column(moop_retired_list($config), 'bundle'), true)) {
        echo json_encode(['ok' => false, 'error' => 'No such retired bundle.']);
        exit;
    }
    echo json_encode(moop_restore_organism($bundle, $username, $config));
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
