<?php
/**
 * Record (or clear) a deliberate difference between a curated group and the taxonomy.
 *
 * A taxonomy suggestion says "the tree puts this organism under a rank matching a group
 * it is not in". Sometimes that is on purpose — groups are editorial and are not required
 * to be taxonomic. Dismissing one stores that decision so it stops being suggested,
 * WITHOUT changing group membership: this endpoint never touches
 * organism_assembly_groups.json.
 *
 * POST parameters:
 *   organism  - organism directory name
 *   group     - curated group name
 *   reason    - optional free text, recorded for the audit trail
 *   remove    - '1' to restore a previously dismissed suggestion
 *
 * Returns JSON: { success: bool, error: string }
 *
 * Auth + CSRF come from admin_init.php (see CLAUDE.md §5) — do NOT switch this to
 * admin_access_check.php, which checks the role but not the token.
 */

include_once __DIR__ . '/../../admin/admin_init.php';
require_once __DIR__ . '/../../lib/group_taxonomy_check.php';

header('Content-Type: application/json');

$organism = trim($_POST['organism'] ?? '');
$group    = trim($_POST['group'] ?? '');
$reason   = trim($_POST['reason'] ?? '');
$remove   = ($_POST['remove'] ?? '0') === '1';

if ($organism === '' || $group === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'organism and group are required']);
    exit;
}

// Keep the stored reason bounded; it is free text from a form.
if (strlen($reason) > 500) {
    $reason = substr($reason, 0, 500);
}

$user = $_SESSION['username'] ?? ($_SESSION['user'] ?? 'admin');

$result = moop_gt_set_exception($organism, $group, $reason, (string)$user, $remove);

if (!$result['ok']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true, 'error' => '']);
