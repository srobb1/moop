<?php
/**
 * MANAGE BLAST LINKOUTS - Admin Controller
 *
 * Configures which linkout buttons appear on BLAST hit results:
 *   - Gene Page (parent.php, if organism.sqlite present)
 *   - JBrowse (jbrowse2.php at gene locus, if assembly registered)
 *   - External URLs (user-defined templates with {fasta_id}, {organism}, {assembly})
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

$editable_config_file = __DIR__ . '/../config/config_editable.json';
$message     = '';
$messageType = '';

$linkout_config = $config->getArray('blast_linkouts', [
    'gene_page' => true,
    'jbrowse'   => true,
    'external'  => [],
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linkout_config['gene_page'] = isset($_POST['gene_page']);
    $linkout_config['jbrowse']   = isset($_POST['jbrowse']);

    $labels    = $_POST['ext_label']    ?? [];
    $templates = $_POST['ext_template'] ?? [];
    $linkout_config['external'] = [];
    foreach ($labels as $i => $label) {
        $label    = trim($label);
        $template = trim($templates[$i] ?? '');
        if ($label === '' || $template === '') continue;
        if (!str_starts_with($template, 'http')) continue;
        $linkout_config['external'][] = [
            'label'        => $label,
            'url_template' => $template,
        ];
    }

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

render_display_page(__DIR__ . '/pages/manage_blast_linkouts.php', [
    'linkout_config' => $linkout_config,
    'message'        => $message,
    'messageType'    => $messageType,
], 'Manage BLAST Linkouts');
