<?php
/**
 * RETIRE / RESTORE AN ORGANISM — takes an organism off the site and cleans up what MOOP made for
 * it, reversibly. Plan and reasoning: notes/RETIRE_DATA_PLAN.md (organism level; assembly and
 * gene set come later — the per-organism database still holds their rows until a rebuild).
 *
 * Nothing is deleted. The organism's directory is MOVED (rename) to
 * organisms/.retired/<YYYYMMDD-HHMMSS>_<Organism>/data/ — same volume, so instant; already
 * denied by nginx; already writable by the web server. Its JBrowse track and sheet settings are
 * moved into the same bundle; the JBrowse registration (regenerable) is removed; its group
 * entries are saved in the bundle's retired.json and removed; its cache directory (regenerable)
 * is deleted. Restore reverses all of it except registration, which the admin redoes in Manage
 * JBrowse. users.json is never touched, so a restore brings people's access back.
 *
 * Every step is recorded in retired.json AS IT COMPLETES. A failure part-way leaves an exact
 * record of what was done, and restore undoes exactly that.
 */

require_once __DIR__ . '/functions_filesystem.php';   // moop_organism_dirs(), rrmdir()
require_once __DIR__ . '/functions_json.php';         // loadJsonFile()
require_once __DIR__ . '/functions_database.php';     // fetchData()
require_once __DIR__ . '/cache_paths.php';            // moop_cache_root()
require_once __DIR__ . '/jbrowse/gene_set_functions.php';   // unregisterAssembly()

const MOOP_RETIRED_DIR = '.retired';

/** Where retired bundles live: inside organisms/, so a move is a rename on the same volume. */
function moop_retired_root(string $organism_data): string {
    return rtrim($organism_data, '/') . '/' . MOOP_RETIRED_DIR;
}

/** Organism and bundle names are directory names; never a path, never hidden. */
function moop_retire_valid_name(string $name): bool {
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $name);
}

/** Everything the retire would touch for one organism — the same facts the preview shows. */
function moop_retire_facts(string $organism, ConfigManager $config): array {
    $organism_data = $config->getPath('organism_data');
    $metadata      = $config->getPath('metadata_path');
    $org_dir       = "$organism_data/$organism";

    // Assemblies: the directories on disk plus the database's accessions — a registration is
    // keyed by whichever name it was made under.
    $assemblies = [];
    foreach (glob("$org_dir/*", GLOB_ONLYDIR) ?: [] as $d) $assemblies[basename($d)] = true;
    if (is_file("$org_dir/organism.sqlite")) {
        try {
            foreach (fetchData('SELECT genome_accession, genome_name FROM genome', "$org_dir/organism.sqlite") as $g) {
                foreach ([$g['genome_accession'] ?? '', $g['genome_name'] ?? ''] as $n) if ($n !== '') $assemblies[$n] = true;
            }
        } catch (Throwable $e) { /* directories alone still find the registrations made from them */ }
    }
    $registered = [];
    foreach (array_keys($assemblies) as $asm) {
        if (is_file("$metadata/jbrowse2-configs/assemblies/{$organism}_{$asm}.json")) $registered[] = (string)$asm;
    }

    $groups_file = "$metadata/organism_assembly_groups.json";
    $entries = array_values(array_filter(loadJsonFile($groups_file, []) ?: [], fn($e) => ($e['organism'] ?? '') === $organism));

    $moved = [];
    foreach (['tracks', 'sheets'] as $kind) {
        $p = "$metadata/jbrowse2-configs/$kind/$organism";
        if (is_dir($p)) $moved[$kind] = $p;
    }
    $cache_dir = moop_cache_root() . "/$organism";

    return [
        'organism'      => $organism,
        'org_dir'       => $org_dir,
        'registered'    => $registered,
        'group_entries' => $entries,
        'groups_file'   => $groups_file,
        'jbrowse_dirs'  => $moved,
        'cache_dir'     => is_dir($cache_dir) && realpath($cache_dir) !== realpath($org_dir) ? $cache_dir : null,
    ];
}

/**
 * What retiring $organism would do, without doing it. ['ok' => bool, 'error' => ?string,
 * 'steps' => list<string>, 'facts' => array]. The confirm dialog shows exactly this list.
 */
function moop_retire_preview(string $organism, ConfigManager $config): array {
    $organism_data = $config->getPath('organism_data');
    if (!moop_retire_valid_name($organism) || !in_array($organism, moop_organism_dirs($organism_data), true)) {
        return ['ok' => false, 'error' => 'No such organism.', 'steps' => [], 'facts' => []];
    }
    $f = moop_retire_facts($organism, $config);
    $steps = ["Move organisms/$organism/ (its database, genome and gene sets) to organisms/" . MOOP_RETIRED_DIR . "/ — nothing is deleted"];
    foreach ($f['jbrowse_dirs'] as $kind => $p) $steps[] = "Keep its JBrowse $kind settings with it";
    foreach ($f['registered'] as $asm) $steps[] = "Unregister $asm from the genome browser";
    if ($f['group_entries']) {
        $steps[] = 'Remove ' . count($f['group_entries']) . ' group entr' . (count($f['group_entries']) === 1 ? 'y' : 'ies')
                 . ' (saved, so Restore puts them back)';
    }
    if ($f['cache_dir']) $steps[] = 'Delete its cache (rebuilt automatically if restored)';
    $steps[] = 'Refresh the organism list';

    $error = null;
    if (!is_writable($f['org_dir']) || !is_writable($organism_data)) {
        $error = "The web server cannot move organisms/$organism/ — check its permissions (Filesystem Permissions).";
    } elseif (!is_writable($f['groups_file'])) {
        $error = 'The web server cannot write organism_assembly_groups.json.';
    }
    return ['ok' => $error === null, 'error' => $error, 'steps' => $steps, 'facts' => $f];
}

/** Write the bundle's record. Returns false if it could not be written — the caller stops. */
function moop_retire_record(string $bundle, array $record): bool {
    return file_put_contents("$bundle/retired.json",
        json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX) !== false;
}

/** Append one line to the Manage Groups change log (where group-entry history already lives). */
function moop_retire_log(ConfigManager $config, string $line): void {
    $log = $config->getPath('metadata_path') . '/change_log/manage_groups.log';
    @file_put_contents($log, '[' . date('Y-m-d H:i:s') . "] $line\n", FILE_APPEND | LOCK_EX);
}

/**
 * Retire $organism. Returns ['ok' => bool, 'error' => ?string, 'done' => list<string>,
 * 'bundle' => ?string]. The data move comes FIRST: it is the step most likely to fail, and if it
 * does nothing else has been touched.
 */
function moop_retire_organism(string $organism, string $username, ConfigManager $config): array {
    $preview = moop_retire_preview($organism, $config);
    if (!$preview['ok']) return ['ok' => false, 'error' => $preview['error'], 'done' => [], 'bundle' => null];
    $f = $preview['facts'];

    $root = moop_retired_root($config->getPath('organism_data'));
    if (!is_dir($root) && !@mkdir($root, 02775) && !is_dir($root)) {
        return ['ok' => false, 'error' => 'Could not create organisms/' . MOOP_RETIRED_DIR . '/.', 'done' => [], 'bundle' => null];
    }
    // mkdir() is filtered by php-fpm's umask (022 → 2755), which would leave the admin unable to
    // run the "delete for good" command without sudo. Group-writable like the rest of organisms/.
    @chmod($root, 02775);
    $name   = date('Ymd-His') . "_$organism";
    $bundle = "$root/$name";
    if (file_exists($bundle) || !@mkdir($bundle, 02775)) {
        return ['ok' => false, 'error' => "Could not create the bundle $name.", 'done' => [], 'bundle' => null];
    }
    @chmod($bundle, 02775);

    $record = ['level' => 'organism', 'organism' => $organism, 'retired_at' => date('Y-m-d H:i:s'), 'retired_by' => $username,
               'status' => 'in_progress', 'steps' => [], 'moved' => [], 'unregistered' => [], 'group_entries' => [],
               'note' => 'Restore from Admin → Manage Organisms → Retired. Then register its assemblies again in Manage JBrowse.'];
    if (!moop_retire_record($bundle, $record)) {
        @rmdir($bundle);
        return ['ok' => false, 'error' => 'Could not write the retire record — nothing was changed.', 'done' => [], 'bundle' => null];
    }
    $done = [];
    $step = function (string $text) use (&$record, &$done, $bundle) {
        $record['steps'][] = $text;
        $done[] = $text;
        moop_retire_record($bundle, $record);
    };
    $fail = function (string $error) use (&$record, &$done, $bundle, $name) {
        $record['status'] = 'failed';
        $record['error']  = $error;
        moop_retire_record($bundle, $record);
        return ['ok' => false, 'error' => $error, 'done' => $done, 'bundle' => $name];
    };

    // 1. The data. rename() only: a copy of gigabytes is not something a button should start.
    if (!@rename($f['org_dir'], "$bundle/data")) {
        $record['status'] = 'failed';
        moop_retire_record($bundle, $record);
        @unlink("$bundle/retired.json");
        @rmdir($bundle);
        return ['ok' => false, 'error' => "Could not move organisms/$organism/ — nothing was changed.", 'done' => [], 'bundle' => null];
    }
    $record['moved']['data'] = "organisms/$organism";
    $step("Moved organisms/$organism/ to organisms/" . MOOP_RETIRED_DIR . "/$name/");

    // 2. JBrowse track and sheet settings: curation work, kept with the data.
    foreach ($f['jbrowse_dirs'] as $kind => $path) {
        if (!is_dir("$bundle/jbrowse")) { @mkdir("$bundle/jbrowse", 02775); @chmod("$bundle/jbrowse", 02775); }
        if (!@rename($path, "$bundle/jbrowse/$kind")) return $fail("Could not move its JBrowse $kind settings.");
        $record['moved']["jbrowse/$kind"] = substr($path, strlen($config->getPath('site_path')) + 1);
        $step("Kept its JBrowse $kind settings");
    }

    // 3. Registrations: regenerable, so removed (Restore asks the admin to register again).
    foreach ($f['registered'] as $asm) {
        $r = unregisterAssembly($organism, $asm, $config);
        if (empty($r['success'])) return $fail("Could not unregister $asm: " . ($r['error'] ?? 'unknown error'));
        $record['unregistered'][] = $asm;
        $step("Unregistered $asm from the genome browser");
    }

    // 4. Group entries: saved in the record first, then removed.
    if ($f['group_entries']) {
        $record['group_entries'] = $f['group_entries'];
        moop_retire_record($bundle, $record);
        $all  = loadJsonFile($f['groups_file'], []) ?: [];
        $kept = array_values(array_filter($all, fn($e) => ($e['organism'] ?? '') !== $organism));
        if (file_put_contents($f['groups_file'], json_encode($kept, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            return $fail('Could not save organism_assembly_groups.json.');
        }
        $step('Removed ' . count($f['group_entries']) . ' group entr' . (count($f['group_entries']) === 1 ? 'y' : 'ies'));
    }

    // 5. Cache: regenerable.
    if ($f['cache_dir'] && rrmdir($f['cache_dir'])) $step('Deleted its cache');

    $record['status'] = 'retired';
    moop_retire_record($bundle, $record);
    moop_retire_log($config, "RETIRE by $username | Organism: $organism | Bundle: $name");
    return ['ok' => true, 'error' => null, 'done' => $done, 'bundle' => $name];
}

/**
 * The retired bundles, grouped by organism and newest first within each: name, record, the
 * command that deletes one for good, and two facts the list needs —
 *   exists_again  an organism of that name is in organisms/ again (copied back the normal way, or
 *                 another bundle restored): Restore would refuse; only its settings can go back
 *   newest        the latest of several bundles for one organism (they never overwrite each other)
 */
function moop_retired_list(ConfigManager $config): array {
    $organism_data = $config->getPath('organism_data');
    $root = moop_retired_root($organism_data);
    $out  = [];
    foreach (glob("$root/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $record = loadJsonFile("$dir/retired.json", []) ?: [];
        $org    = (string)($record['organism'] ?? '');
        $out[] = ['bundle' => basename($dir), 'record' => $record, 'path' => $dir, 'organism' => $org,
                  'exists_again' => $org !== '' && is_dir("$organism_data/$org"),
                  'has_data' => is_dir("$dir/data"),
                  'delete_command' => 'rm -rf ' . escapeshellarg($dir)];
    }
    usort($out, fn($a, $b) => strcmp($a['organism'], $b['organism']) ?: strcmp($b['bundle'], $a['bundle']));
    $count = array_count_values(array_column($out, 'organism'));
    $seen  = [];
    foreach ($out as &$b) {
        $b['siblings'] = $count[$b['organism']];
        $b['newest']   = !isset($seen[$b['organism']]);
        $seen[$b['organism']] = true;
    }
    unset($b);
    return $out;
}

/**
 * Put a retired organism back: data, JBrowse settings, group entries. Refuses if an organism of
 * that name exists again. The bundle directory is removed once it is empty.
 */
function moop_restore_organism(string $bundle_name, string $username, ConfigManager $config, bool $settings_only = false): array {
    if (!moop_retire_valid_name($bundle_name)) return ['ok' => false, 'error' => 'No such retired bundle.', 'done' => []];
    $site_path = $config->getPath('site_path');
    $bundle    = moop_retired_root($config->getPath('organism_data')) . "/$bundle_name";
    $record    = loadJsonFile("$bundle/retired.json", []) ?: [];
    $organism  = (string)($record['organism'] ?? '');
    if (!is_dir($bundle) || ($record['level'] ?? '') !== 'organism' || !moop_retire_valid_name($organism)) {
        return ['ok' => false, 'error' => 'No such retired bundle.', 'done' => []];
    }
    $org_dir = $config->getPath('organism_data') . "/$organism";
    // Settings only: the organism was copied back the normal way, so its data is already in place
    // — put back what the copy did not bring (groups, JBrowse settings) and leave the old data in
    // the bundle for the admin to delete. Only then; a full restore never merges into a copy.
    if ($settings_only !== file_exists($org_dir)) {
        return ['ok' => false, 'done' => [], 'error' => $settings_only
            ? "organisms/$organism/ is not there — use Restore to bring everything back."
            : "organisms/$organism/ exists again — nothing was restored. Use \"Put back its groups and settings\" instead."];
    }

    $done = [];
    if (!$settings_only && is_dir("$bundle/data")) {
        if (!@rename("$bundle/data", $org_dir)) return ['ok' => false, 'error' => "Could not move the data back to organisms/$organism/.", 'done' => []];
        $done[] = "Moved the data back to organisms/$organism/";
    }
    $put_back = [];
    foreach (($record['moved'] ?? []) as $what => $orig) {
        if ($what === 'data' || !is_dir("$bundle/$what")) continue;
        $target = "$site_path/$orig";
        if (file_exists($target)) { $done[] = 'Left its old JBrowse ' . basename($what) . " settings in the bundle: $orig already exists"; continue; }
        if (@rename("$bundle/$what", $target)) { $done[] = 'Put back its JBrowse ' . basename($what) . ' settings'; $put_back[] = $what; }
    }
    if (!empty($record['group_entries'])) {
        $groups_file = $config->getPath('metadata_path') . '/organism_assembly_groups.json';
        $all  = loadJsonFile($groups_file, []) ?: [];
        $have = array_map(fn($e) => ($e['organism'] ?? '') . '|' . ($e['assembly'] ?? '') . '|' . ($e['gene_set'] ?? ''), $all);
        $added = 0;
        foreach ($record['group_entries'] as $e) {
            if (in_array(($e['organism'] ?? '') . '|' . ($e['assembly'] ?? '') . '|' . ($e['gene_set'] ?? ''), $have, true)) continue;
            $all[] = $e;
            $added++;
        }
        if (file_put_contents($groups_file, json_encode(array_values($all), JSON_PRETTY_PRINT), LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'The data is back, but organism_assembly_groups.json could not be saved — re-add its groups in Manage Groups.', 'done' => $done];
        }
        $done[] = "Put back $added group entr" . ($added === 1 ? 'y' : 'ies');
    }
    if ($settings_only) {
        // The bundle keeps its old data (and its record, marked) until the admin deletes it.
        $record['settings_restored_at'] = date('Y-m-d H:i:s');
        $record['settings_restored_by'] = $username;
        $record['moved'] = array_diff_key($record['moved'] ?? [], array_flip($put_back));
        $record['group_entries'] = [];
        moop_retire_record($bundle, $record);
        @rmdir("$bundle/jbrowse");
        moop_retire_log($config, "RESTORE SETTINGS by $username | Organism: $organism | Bundle: $bundle_name");
        $done[] = 'The old data stays in the bundle — delete it with the command shown once you are sure';
    } else {
        @unlink("$bundle/retired.json");
        @rmdir("$bundle/jbrowse");
        @rmdir($bundle);
        moop_retire_log($config, "RESTORE by $username | Organism: $organism | Bundle: $bundle_name");
    }
    $done[] = 'Register its assemblies again in Manage JBrowse to bring back the genome browser';
    return ['ok' => true, 'error' => null, 'done' => $done];
}
