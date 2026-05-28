<?php
/**
 * MOOPmart Chr Names — Return sorted chromosome/scaffold names for one gene set.
 *
 * POST parameters:
 *   source  - "organism|assembly|gene_set" key (must be accessible to the current user)
 *
 * Returns JSON: { chrs: ["CHR01", "CHR02", ...] }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';

header('Content-Type: application/json');
csrf_protect();

$source_key = trim($_POST['source'] ?? '');
if (!$source_key) {
    echo json_encode(['chrs' => []]);
    exit;
}

// Validate the requested source against what the user can actually access
$all_accessible    = flattenSourcesList(getAccessibleAssemblies());
$accessible_by_key = [];
foreach ($all_accessible as $src) {
    $key = $src['organism'] . '|' . $src['assembly'] . '|' . ($src['gene_set'] ?? '');
    $accessible_by_key[$key] = $src;
}

$src = $accessible_by_key[$source_key] ?? null;
if (!$src || empty($src['path'])) {
    echo json_encode(['chrs' => []]);
    exit;
}

echo json_encode(['chrs' => moopmartGetChrNames($src['path'])]);
