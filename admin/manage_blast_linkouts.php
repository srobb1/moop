<?php
/**
 * MANAGE BLAST LINKOUTS - Admin Controller
 *
 * Configures which linkout buttons appear on BLAST hit results:
 *   - Gene Page (parent.php, if organism.sqlite present)
 *   - JBrowse (jbrowse2.php at gene locus, if assembly registered)
 *   - External URLs — global (all DBs) and per-DB (organism|assembly|seq_type)
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/functions_data.php';

$editable_config_file = __DIR__ . '/../config/config_editable.json';
$message     = '';
$messageType = '';

$linkout_config = $config->getArray('blast_linkouts', [
    'gene_page'             => true,
    'gene_page_label'       => 'Gene Page',
    'jbrowse'               => true,
    'jbrowse_label'         => 'Genome Browser',
    'jbrowse_hsp_min_score' => 0,
    'external'              => [],
    'per_db_external'       => [],
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linkout_config['gene_page']             = isset($_POST['gene_page']);
    $linkout_config['gene_page_label']       = trim($_POST['gene_page_label'] ?? '') ?: 'Gene Page';
    $linkout_config['jbrowse']               = isset($_POST['jbrowse']);
    $linkout_config['jbrowse_label']         = trim($_POST['jbrowse_label'] ?? '') ?: 'Genome Browser';
    $linkout_config['jbrowse_hsp_min_score'] = max(0, (int)($_POST['jbrowse_hsp_min_score'] ?? 0));
    $linkout_config['jbrowse_hsp_max_span']  = max(1, (int)($_POST['jbrowse_hsp_max_span']  ?? 500000));

    // Global external linkouts
    $labels    = $_POST['ext_label']    ?? [];
    $templates = $_POST['ext_template'] ?? [];
    $linkout_config['external'] = [];
    foreach ($labels as $i => $label) {
        $label    = trim($label);
        $template = trim($templates[$i] ?? '');
        if ($label === '' || $template === '') continue;
        if (!str_starts_with($template, 'http')) continue;
        $linkout_config['external'][] = ['label' => $label, 'url_template' => $template];
    }

    // Per-DB external linkouts
    $pdb_keys   = $_POST['pdb_key']   ?? [];
    $pdb_labels = $_POST['pdb_label'] ?? [];
    $pdb_urls   = $_POST['pdb_url']   ?? [];
    $per_db = [];
    foreach ($pdb_keys as $i => $key) {
        $key      = trim($key);
        $label    = trim($pdb_labels[$i] ?? '');
        $template = trim($pdb_urls[$i]   ?? '');
        if ($key === '' || $label === '' || $template === '') continue;
        if (!str_starts_with($template, 'http')) continue;
        // Validate key: exactly two pipe separators → organism|assembly|seq_type
        if (substr_count($key, '|') !== 2) continue;
        $per_db[$key][] = ['label' => $label, 'url_template' => $template];
    }
    $linkout_config['per_db_external'] = $per_db;

    $current = [];
    if (file_exists($editable_config_file)) {
        $current = json_decode(file_get_contents($editable_config_file), true) ?? [];
    }
    $current['blast_linkouts'] = $linkout_config;

    if (file_put_contents($editable_config_file, json_encode($current, JSON_PRETTY_PRINT)) !== false) {
        $message     = 'Linkout settings saved.';
        $messageType = 'success';
    } else {
        $message     = 'Failed to write config file — check permissions.';
        $messageType = 'danger';
    }
}

// Build list of all available BLAST databases for the per-DB dropdown.
// BLAST indexes live in gene_set subdirs, but the per-DB linkout key stays
// organism|assembly|seq_type (gene_set-agnostic) to avoid breaking saved configs.
$organisms_dir = $config->getPath('organism_data');
$all_orgs      = getOrganismsWithAssemblies($organisms_dir);
$db_options    = [];
foreach ($all_orgs as $org_name => $assemblies) {
    foreach ($assemblies as $asm_id) {
        $asm_path = $organisms_dir . '/' . $org_name . '/' . $asm_id;
        foreach (glob($asm_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
            foreach (getBlastDatabases($gs_dir) as $db) {
                $key = $org_name . '|' . $asm_id . '|' . $db['seq_type'];
                if (!isset($db_options[$key])) {
                    $db_options[$key] = [
                        'key'      => $key,
                        'display'  => $org_name . ' / ' . $asm_id . ' / ' . $db['name'],
                        'organism' => $org_name,
                        'assembly' => $asm_id,
                        'db_type'  => $db['seq_type'],
                        'db_name'  => $db['name'],
                    ];
                }
            }
        }
    }
}

// Build feature_coords.tsv status for each JBrowse-registered assembly, per gene set
$feature_coord_status = [];
$assemblies_meta_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/assemblies';
if (is_dir($assemblies_meta_dir)) {
    foreach (glob($assemblies_meta_dir . '/*.json') ?: [] as $jf) {
        $jd = json_decode(file_get_contents($jf), true);
        if (empty($jd)) continue;
        $org = $jd['organism'] ?? '';
        $asm = $jd['assemblyId'] ?? '';
        if ($org === '' || $asm === '') continue;
        $asm_path = $organisms_dir . '/' . $org . '/' . $asm;
        if (!is_dir($asm_path)) continue;
        foreach (glob($asm_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
            $gene_set = basename($gs_dir);
            $tsv = $gs_dir . '/feature_coords.tsv';
            $gff = $gs_dir . '/genomic.gff';
            $tsv_size_mb = file_exists($tsv)
                ? round(filesize($tsv) / 1048576, 1) . ' MB'
                : null;
            $feature_coord_status[] = [
                'organism'     => $org,
                'assembly'     => $asm,
                'gene_set'     => $gene_set,
                'has_tsv'      => file_exists($tsv),
                'has_gff'      => file_exists($gff),
                'tsv_modified' => file_exists($tsv) ? date('Y-m-d H:i', filemtime($tsv)) : null,
                'tsv_size'     => $tsv_size_mb,
            ];
        }
    }
}

// Flatten per_db_external into rows for the table
$per_db_rows = [];
foreach ($linkout_config['per_db_external'] ?? [] as $key => $linkouts) {
    foreach ($linkouts as $lo) {
        $per_db_rows[] = [
            'key'          => $key,
            'label'        => $lo['label']        ?? '',
            'url_template' => $lo['url_template'] ?? '',
        ];
    }
}

echo render_display_page(__DIR__ . '/pages/manage_blast_linkouts.php', [
    'linkout_config'       => $linkout_config,
    'db_options'           => $db_options,
    'per_db_rows'          => $per_db_rows,
    'feature_coord_status' => $feature_coord_status,
    'message'              => $message,
    'messageType'          => $messageType,
], 'Manage BLAST Linkouts');
