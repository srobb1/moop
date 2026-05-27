<?php
/**
 * API: Quick-add an organism's ungrouped assemblies to a group.
 * Called from the manage_organisms page.
 */

include_once __DIR__ . '/../admin_init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$organism   = trim($_POST['organism']   ?? '');
$group_name = trim($_POST['group_name'] ?? '');

if ($organism === '' || $group_name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing organism or group name']);
    exit;
}

$metadata_path      = $config->getPath('metadata_path');
$organism_data_path = $config->getPath('organism_data');
$groups_file        = $metadata_path . '/organism_assembly_groups.json';

$groups_data = loadJsonFile($groups_file, []);

$org_path = $organism_data_path . '/' . $organism;
if (!is_dir($org_path)) {
    echo json_encode(['success' => false, 'error' => 'Organism directory not found']);
    exit;
}

$timestamp   = date('Y-m-d H:i:s');
$username    = $_SESSION['username'] ?? 'unknown';
$log_dir     = $metadata_path . '/change_log';
$log_file    = $log_dir . '/manage_groups.log';
if (!is_dir($log_dir)) @mkdir($log_dir, 0775, true);

$added       = 0;
$log_entries = [];

foreach (scandir($org_path) as $assembly) {
    if ($assembly === '.' || $assembly === '..') continue;
    $asm_path = $org_path . '/' . $assembly;
    if (!is_dir($asm_path)) continue;

    $gene_sets = array_map('basename', glob($asm_path . '/*', GLOB_ONLYDIR) ?: []);
    if (empty($gene_sets)) $gene_sets = ['v1'];

    foreach ($gene_sets as $gene_set) {
        $found = false;
        foreach ($groups_data as &$entry) {
            $entry_gs = $entry['gene_set'] ?? 'v1';
            if ($entry['organism'] === $organism && $entry['assembly'] === $assembly && $entry_gs === $gene_set) {
                $found = true;
                if (empty($entry['groups'])) {
                    $entry['groups'] = [$group_name];
                    $added++;
                    $log_entries[] = sprintf(
                        '[%s] QUICK_ADD by %s | Organism: %s | Assembly: %s | Gene set: %s | Groups: [%s]',
                        $timestamp, $username, $organism, $assembly, $gene_set, $group_name
                    );
                }
                break;
            }
        }
        unset($entry);

        if (!$found) {
            $groups_data[] = [
                'organism' => $organism,
                'assembly' => $assembly,
                'gene_set' => $gene_set,
                'groups'   => [$group_name],
            ];
            $added++;
            $log_entries[] = sprintf(
                '[%s] QUICK_ADD by %s | Organism: %s | Assembly: %s | Gene set: %s | Groups: [%s]',
                $timestamp, $username, $organism, $assembly, $gene_set, $group_name
            );
        }
    }
}

if ($added === 0) {
    echo json_encode(['success' => false, 'error' => 'All assemblies already have group assignments']);
    exit;
}

if (@file_put_contents($groups_file, json_encode($groups_data, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'error' => 'Could not write to groups file']);
    exit;
}

if (!empty($log_entries)) {
    file_put_contents($log_file, implode("\n", $log_entries) . "\n", FILE_APPEND);
}

echo json_encode(['success' => true, 'added' => $added, 'group' => $group_name]);
