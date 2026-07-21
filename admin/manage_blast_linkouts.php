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
    'jbrowse_hsp_max_span'  => 500000,
    'jbrowse_hsp_max_link'  => 10,
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
    $linkout_config['jbrowse_hsp_max_link']  = max(1, (int)($_POST['jbrowse_hsp_max_link']  ?? 10));

    // Rows that fail validation are dropped — tell the admin which ones, rather than
    // reporting a clean save for settings that were silently discarded.
    $rejected = [];

    // Global external linkouts
    $labels    = $_POST['ext_label']    ?? [];
    $templates = $_POST['ext_template'] ?? [];
    $linkout_config['external'] = [];
    foreach ($labels as $i => $label) {
        $label    = trim($label);
        $template = trim($templates[$i] ?? '');
        if ($label === '' && $template === '') continue;  // untouched blank row
        if ($label === '' || $template === '') {
            $rejected[] = 'Global linkout #' . ($i + 1) . ' needs both a label and a URL.';
            continue;
        }
        if (!preg_match('#^https?://#i', $template)) {
            $rejected[] = 'Global linkout "' . $label . '" — URL must start with http:// or https://.';
            continue;
        }
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
        if ($key === '' && $label === '' && $template === '') continue;  // untouched blank row
        $who = $label !== '' ? '"' . $label . '"' : '#' . ($i + 1);
        if ($key === '') {
            $rejected[] = 'Per-database linkout ' . $who . ' — no database selected.';
            continue;
        }
        if ($label === '' || $template === '') {
            $rejected[] = 'Per-database linkout ' . $who . ' needs both a label and a URL.';
            continue;
        }
        if (!preg_match('#^https?://#i', $template)) {
            $rejected[] = 'Per-database linkout ' . $who . ' — URL must start with http:// or https://.';
            continue;
        }
        // Validate key: exactly two pipe separators → organism|assembly|seq_type
        if (substr_count($key, '|') !== 2) {
            $rejected[] = 'Per-database linkout ' . $who . ' — unrecognized database "' . $key . '".';
            continue;
        }
        $per_db[$key][] = ['label' => $label, 'url_template' => $template];
    }
    $linkout_config['per_db_external'] = $per_db;

    $current = [];
    if (file_exists($editable_config_file)) {
        $current = loadJsonFile($editable_config_file, []);
    }
    $current['blast_linkouts'] = $linkout_config;

    $json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json !== false && file_put_contents($editable_config_file, $json) !== false) {
        if ($rejected) {
            $message     = 'Saved, but ' . count($rejected) . ' linkout(s) were not saved — '
                         . implode(' ', $rejected);
            $messageType = 'warning';
        } else {
            $message     = 'Linkout settings saved.';
            $messageType = 'success';
        }
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

// Build feature_coords.tsv status for each JBrowse-registered assembly, per gene set.
// A registration whose organisms/ directory is missing gets NO row, so it would otherwise
// vanish from this table entirely — collect those separately and report them.
$feature_coord_status  = [];
$orphan_registrations  = [];
$assemblies_meta_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/assemblies';
if (is_dir($assemblies_meta_dir)) {
    foreach (glob($assemblies_meta_dir . '/*.json') ?: [] as $jf) {
        $jd = loadJsonFile($jf, []);
        if (empty($jd)) {
            $orphan_registrations[] = ['file' => basename($jf), 'reason' => 'unreadable or empty JSON'];
            continue;
        }
        $org = $jd['organism'] ?? '';
        $asm = $jd['assemblyId'] ?? '';
        if ($org === '' || $asm === '') {
            $orphan_registrations[] = ['file' => basename($jf), 'reason' => 'missing "organism" or "assemblyId"'];
            continue;
        }
        $asm_path = $organisms_dir . '/' . $org . '/' . $asm;
        if (!is_dir($asm_path)) {
            // Show what the organism directory actually holds — the usual cause is a
            // registration made under a different assembly name than the data uses.
            $actual = array_map('basename', glob($organisms_dir . '/' . $org . '/*', GLOB_ONLYDIR) ?: []);
            $orphan_registrations[] = [
                'file'     => basename($jf),
                'organism' => $org,
                'assembly' => $asm,
                'reason'   => 'no directory at organisms/' . $org . '/' . $asm,
                'actual'   => $actual,
            ];
            continue;
        }
        foreach (glob($asm_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
            $gene_set = basename($gs_dir);
            $tsv = $gs_dir . '/feature_coords.tsv';
            $gff = $gs_dir . '/' . genes_gff_filename();
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
    'orphan_registrations' => $orphan_registrations,
    'message'              => $message,
    'messageType'          => $messageType,
], 'Manage BLAST Linkouts');
