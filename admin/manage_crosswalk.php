<?php
/**
 * MANAGE ROSETTA STONE (ID translator) — Admin Controller
 *
 * What visitors see on tools/crosswalk.php, per organism: the page title, the groups of the
 * "Translate into" list, and for every source in the organism's crosswalk.sqlite its name, its
 * group, where its IDs open (a MOOP gene page, an outside page, or plain text) and whether it is a
 * column for every gene. Sofia, 2026-11-10: "like the primer maker config".
 *
 * Saves 'crosswalk_display' in config/config_editable.json (checked by
 * moop_crosswalk_display_check()). The HUBS and the build FOLDER stay in site_config.php: they are
 * server paths read by a build, and a web form must never point the server at a path.
 * CSRF is verified in admin_init.php on every POST. POST-redirect-GET. No JavaScript.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/crosswalk.php';

$config_dir  = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
$config_file = "$config_dir/config_editable.json";
$file_write_error = getFileWriteError($config_file);

$organisms = array_values(array_filter(array_keys(moop_crosswalk_sources()), fn($o) => is_file(moop_crosswalk_file($o))));
$organism  = in_array($_GET['organism'] ?? $_POST['organism'] ?? '', $organisms, true) ? (string) ($_GET['organism'] ?? $_POST['organism']) : ($organisms[0] ?? '');
$back = 'manage_crosswalk.php' . ($organism !== '' ? '?organism=' . urlencode($organism) : '');

$flash_and_back = function (string $type, string $msg) use ($back) {
    $_SESSION['crosswalk_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . $back);
    exit;
};

// The gene sets loaded for this organism: where a source's ids can open a MOOP gene page.
$gene_sets = [];   // "assembly|gene_set" => "assembly / gene_set"
if ($organism !== '') {
    $db = rtrim($config->getPath('organism_data'), '/') . '/' . $organism . '/organism.sqlite';
    try {
        $o = new PDO('sqlite:' . $db, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        foreach ($o->query('SELECT g.genome_accession AS a, s.gene_set_name AS s FROM gene_set s JOIN genome g USING (genome_id) ORDER BY 1, 2') as $r) {
            $gene_sets[$r['a'] . '|' . $r['s']] = $r['a'] . ' / ' . $r['s'];
        }
    } catch (PDOException $e) { /* no gene-page choices; outside links and plain text still work */ }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($file_write_error) $flash_and_back('danger', 'Not saved: the settings file is read-only (see below).');
    if ($organism === '') $flash_and_back('danger', 'Not saved: no organism has a crosswalk.');
    $action  = (string) ($_POST['_action'] ?? '');
    $all     = $config->getArray('crosswalk_display', []);
    $current = moop_crosswalk_sources()[$organism];
    $known   = moop_crosswalk_source_list($organism);

    if ($action === 'reset') {
        unset($all[$organism]);
        $r = $all ? $config->saveEditableConfig(['crosswalk_display' => $all], $config_dir)
                  : $config->saveEditableConfig([], $config_dir, ['crosswalk_display']);
        if (empty($r['success'])) $flash_and_back('danger', 'Not saved: ' . htmlspecialchars($r['message'] ?? 'unknown error'));
        $flash_and_back('success', 'Back to the shipped settings.');
    }

    if ($action === 'save') {
        // Every source of the file must come back from the form: a POST that lost its fields would
        // otherwise read as "every name and link removed" -- refused (the Manage Tools rule).
        $posted = (array) ($_POST['src'] ?? []);
        if (array_diff(array_keys($known), array_keys($posted))) {
            $flash_and_back('danger', 'Not saved: the request did not come from this form. Nothing was changed.');
        }
        // Groups: rows in the order given by their position number; a ticked "remove" drops one;
        // the blank row at the end adds one (its key made from its name).
        $rows = [];
        foreach ((array) ($_POST['grp'] ?? []) as $i => $g) {
            $label = trim((string) ($g['label'] ?? ''));
            if ($label === '' || !empty($g['remove'])) continue;
            $key = (string) ($g['key'] ?? '');
            if ($key === '') {
                $key = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_') ?: 'group';
                $key = substr($key, 0, 24);
                $base = $key; $n = 2;
                while (isset($rows[$key]) || isset($current['source_groups'][$key])) $key = $base . '_' . $n++;
            }
            $rows[$key] = ['label' => $label, 'pos' => (float) ($g['pos'] ?? 999) + $i / 1000];
        }
        uasort($rows, fn($a, $b) => $a['pos'] <=> $b['pos']);
        $groups = array_map(fn($r) => $r['label'], $rows);

        $sources = [];
        $always  = [];
        foreach ($known as $name => $unused) {
            $x = (array) $posted[$name];
            $e = ['label' => trim((string) ($x['label'] ?? ''))];
            $g = (string) ($x['group'] ?? '');
            if ($g !== '' && isset($groups[$g])) $e['group'] = $g;   // a removed group: back to Other
            $opens = (string) ($x['opens'] ?? '');
            if ($opens === 'url') {
                $e['url'] = trim((string) ($x['url'] ?? ''));
                if ($e['url'] === '') $flash_and_back('danger', 'Not saved: ' . htmlspecialchars($name) . ' opens an outside link, but no link was given.');
            } elseif (isset($gene_sets[$opens])) {
                [$e['assembly'], $e['gene_set']] = explode('|', $opens, 2);
            }
            $about = trim((string) ($x['about'] ?? ''));
            if ($about !== '') $e['about'] = $about;
            $sources[$name] = $e;
            if (!empty($x['always'])) $always[] = $name;
        }
        $all[$organism] = ['name' => trim((string) ($_POST['name'] ?? '')), 'source_groups' => $groups,
                           'sources' => $sources, 'always_show' => $always];
        $r = $config->saveEditableConfig(['crosswalk_display' => $all], $config_dir);
        if (empty($r['success'])) $flash_and_back('danger', 'Not saved: ' . htmlspecialchars($r['message'] ?? 'unknown error'));
        $flash_and_back('success', 'Saved.');
    }
    $flash_and_back('danger', 'Not saved: the request did not come from this page. Nothing was changed.');
}

// A saved value that fails the check (hand-edited file) is ignored WHOLE by moop_crosswalk_sources();
// say so here, or the page would quietly show the shipped settings.
$saved_problem = moop_crosswalk_display_check($config->getArray('crosswalk_display', []));
$saved_problem = is_string($saved_problem) ? $saved_problem : '';

$flash = $_SESSION['crosswalk_flash'] ?? null;
unset($_SESSION['crosswalk_flash']);

$cw = $organism !== '' ? moop_crosswalk_sources()[$organism] : [];
$counts = [];
if ($organism !== '' && ($xdb = moop_crosswalk_open($organism))) {
    foreach ($xdb->query('SELECT source, COUNT(DISTINCT accession) AS n FROM map GROUP BY source') as $r) $counts[$r['source']] = (int) $r['n'];
}

echo render_display_page(__DIR__ . '/pages/manage_crosswalk.php', [
    'organism'         => $organism,
    'organisms'        => $organisms,
    'cw'               => $cw,
    'sources'          => $organism !== '' ? moop_crosswalk_source_list($organism) : [],
    'counts'           => $counts,
    'gene_sets'        => $gene_sets,
    'customised'       => isset($config->getArray('crosswalk_display', [])[$organism]),
    'tool_on'          => $config->isToolEnabled('crosswalk'),
    'saved_problem'    => $saved_problem,
    'flash'            => $flash,
    'file_write_error' => $file_write_error,
    'config_file'      => $config_file,
], 'Rosetta Stone');
