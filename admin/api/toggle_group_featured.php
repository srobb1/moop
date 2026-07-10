<?php
/**
 * Toggle featured flag for a group in group_descriptions.json
 *
 * POST parameters:
 *   group_name  - name of the group to toggle
 *
 * Returns JSON: { success: bool, featured: bool, message: string }
 */

include_once __DIR__ . '/../../admin/admin_init.php';

header('Content-Type: application/json');

$group_name = trim($_POST['group_name'] ?? '');
if ($group_name === '') {
    echo json_encode(['success' => false, 'message' => 'group_name required']);
    exit;
}

$config     = ConfigManager::getInstance();
$desc_file  = $config->getPath('metadata_path') . '/group_descriptions.json';

if (!file_exists($desc_file) || !is_writable($desc_file)) {
    echo json_encode(['success' => false, 'message' => 'group_descriptions.json not writable']);
    exit;
}

$data = loadJsonFile($desc_file, []);

$found    = false;
$featured = false;
foreach ($data as &$group) {
    if ($group['group_name'] === $group_name) {
        $group['featured'] = !($group['featured'] ?? false);
        $featured = $group['featured'];
        $found    = true;
        break;
    }
}
unset($group);

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Group not found']);
    exit;
}

if (file_put_contents($desc_file, json_encode($data, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save']);
    exit;
}

echo json_encode(['success' => true, 'featured' => $featured]);
