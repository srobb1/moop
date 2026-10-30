<?php
/**
 * RETIRE / RESTORE AN ORGANISM, ASSEMBLY OR GENE SET — takes it off the site and cleans up what
 * MOOP made for it, reversibly. Plan and reasoning: notes/RETIRE_DATA_PLAN.md.
 *
 * Nothing is deleted. The directory is MOVED (rename) to
 * organisms/.retired/<YYYYMMDD-HHMMSS>_<Organism>[__<Assembly>[__<GeneSet>]]/data/ — same
 * volume, so instant; already denied by nginx; already writable by the web server. Its JBrowse
 * settings are moved into the same bundle; derived JBrowse files are removed (assembly level:
 * the registration) or moved with it (gene set level: its built track and search index, so a
 * restore needs no rebuild); its group entries are saved in the bundle's retired.json and
 * removed; its cache directory (regenerable) is deleted. users.json is never touched, so a
 * restore brings people's access back.
 *
 * ONE DATABASE PER ORGANISM: an assembly or gene set retired here still has rows in
 * organism.sqlite until the pipeline rebuilds the database without it. Meanwhile it is hidden
 * everywhere because every scope — selectors, downloads, and since this change the search
 * endpoint (moop_search_permitted_scope()) — is built from group entries plus directories, and
 * it has neither. The health checks list it as "retired, waiting for a database rebuild"
 * (validateAssemblyDirectories()'s 'retired' list), not as a missing directory.
 *
 * Every step is recorded in retired.json AS IT COMPLETES. A failure part-way leaves an exact
 * record of what was done, and restore undoes exactly that.
 */

require_once __DIR__ . '/functions_filesystem.php';   // moop_organism_dirs(), is_gene_set_dir(), rrmdir()
require_once __DIR__ . '/functions_json.php';         // loadJsonFile()
require_once __DIR__ . '/functions_database.php';     // fetchData()
require_once __DIR__ . '/cache_paths.php';            // moop_cache_root()
require_once __DIR__ . '/gene_set_identity.php';      // moop_gene_set_identity()
require_once __DIR__ . '/jbrowse/gene_set_functions.php';   // unregisterAssembly(), moop_gene_track_json_path()

const MOOP_RETIRED_DIR = '.retired';
const MOOP_RETIRE_LEVELS = ['organism', 'assembly', 'gene_set'];

/** Where retired bundles live: inside organisms/, so a move is a rename on the same volume. */
function moop_retired_root(string $organism_data): string {
    return rtrim($organism_data, '/') . '/' . MOOP_RETIRED_DIR;
}

/** Organism, assembly, gene set and bundle names are directory names; never a path, never hidden. */
function moop_retire_valid_name(string $name): bool {
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $name);
}

/** "Org", "Org/Asm" or "Org/Asm/GS" — the path under organisms/ a target or record names. */
function moop_retire_rel(array $t): string {
    return implode('/', array_filter([$t['organism'] ?? '', $t['assembly'] ?? '', $t['gene_set'] ?? ''], 'strlen'));
}

/** How a target reads in messages: "Danio_rerio", "assembly GCA_1 of Danio_rerio", … */
function moop_retire_label(array $t): string {
    switch ($t['level']) {
        case 'assembly': return "assembly {$t['assembly']}";
        case 'gene_set': return "gene set {$t['gene_set']} ({$t['assembly']})";
        default:         return $t['organism'];
    }
}

/** Assembly directories of an organism: directories only, never hidden. */
function moop_retire_assembly_dirs(string $org_dir): array {
    return moop_organism_dirs($org_dir);   // same rule, one level down
}

/** Gene-set directories of an assembly (is_gene_set_dir(): holds a sequence FASTA). */
function moop_retire_gene_set_dirs(string $asm_dir): array {
    return array_values(array_filter(moop_organism_dirs($asm_dir), fn($d) => is_gene_set_dir("$asm_dir/$d")));
}

/**
 * Check what the admin asked to retire against the disk. Every name must be a directory that is
 * there now; nothing is built from the request beyond picking among them. Refuses the LAST
 * assembly of an organism and the last gene set of an assembly: retiring that is retiring the
 * level above, which is cleaner (the database goes with it, nothing waits for a rebuild).
 *
 * @return array ['ok' => bool, 'error' => ?string, 'target' => ?array]
 */
function moop_retire_target(string $level, string $organism, string $assembly, string $gene_set, ConfigManager $config): array {
    $no = fn(string $e) => ['ok' => false, 'error' => $e, 'target' => null];
    $organism_data = $config->getPath('organism_data');
    if (!in_array($level, MOOP_RETIRE_LEVELS, true)) return $no('Unknown level.');
    if (!moop_retire_valid_name($organism) || !in_array($organism, moop_organism_dirs($organism_data), true)) return $no('No such organism.');
    $t = ['level' => $level, 'organism' => $organism, 'assembly' => '', 'gene_set' => ''];
    if ($level === 'organism') return ['ok' => true, 'error' => null, 'target' => $t];

    $org_dir = "$organism_data/$organism";
    $asms = moop_retire_assembly_dirs($org_dir);
    if (!moop_retire_valid_name($assembly) || !in_array($assembly, $asms, true)) return $no('No such assembly.');
    $t['assembly'] = $assembly;
    if ($level === 'assembly') {
        if (count($asms) === 1) return $no("$assembly is this organism's only assembly — retire the organism instead.");
        return ['ok' => true, 'error' => null, 'target' => $t];
    }

    $sets = moop_retire_gene_set_dirs("$org_dir/$assembly");
    if (!moop_retire_valid_name($gene_set) || !in_array($gene_set, $sets, true)) return $no('No such gene set.');
    if (count($sets) === 1) return $no("$gene_set is this assembly's only gene set — retire the assembly instead.");
    $t['gene_set'] = $gene_set;
    return ['ok' => true, 'error' => null, 'target' => $t];
}

/**
 * The names an assembly goes by: its directory plus the database's genome_name and
 * genome_accession for it. A JBrowse registration is keyed by whichever one it was made under.
 */
function moop_retire_assembly_names(string $organism, string $assembly, ConfigManager $config): array {
    $names = [$assembly => true];
    $identity = moop_gene_set_identity([$organism], $config->getPath('organism_data'))[$organism] ?? [];
    foreach ($identity['genomes'] ?? [] as $g) {
        if (($g['genome_name'] ?? '') !== $assembly && ($g['genome_accession'] ?? '') !== $assembly) continue;
        foreach ([$g['genome_name'] ?? '', $g['genome_accession'] ?? ''] as $n) if ($n !== '' && moop_retire_valid_name($n)) $names[$n] = true;
    }
    return array_keys($names);
}

/** Everything the retire would touch — the same facts the preview shows. */
function moop_retire_facts(array $t, ConfigManager $config): array {
    $organism_data = $config->getPath('organism_data');
    $metadata      = $config->getPath('metadata_path');
    $site_path     = $config->getPath('site_path');
    $organism      = $t['organism'];
    $jb            = "$metadata/jbrowse2-configs";

    // Assembly names this retire covers (organism: all of them, from disk and the database).
    if ($t['level'] === 'organism') {
        $asm_names = moop_retire_assembly_dirs("$organism_data/$organism");
        foreach (moop_gene_set_identity([$organism], $organism_data)[$organism]['genomes'] ?? [] as $g) {
            foreach ([$g['genome_accession'] ?? '', $g['genome_name'] ?? ''] as $n) if ($n !== '' && moop_retire_valid_name($n)) $asm_names[] = $n;
        }
        $asm_names = array_values(array_unique($asm_names));
    } else {
        $asm_names = moop_retire_assembly_names($organism, $t['assembly'], $config);
    }

    // What moves into the bundle: bundle-relative path => site-relative original.
    $moves = [];
    $rel = fn(string $abs) => substr($abs, strlen($site_path) + 1);
    if ($t['level'] === 'organism') {
        foreach (['tracks', 'sheets'] as $kind) if (is_dir("$jb/$kind/$organism")) $moves["jbrowse/$kind"] = $rel("$jb/$kind/$organism");
    } elseif ($t['level'] === 'assembly') {
        foreach ($asm_names as $a) foreach (['tracks', 'sheets'] as $kind) {
            if (is_dir("$jb/$kind/$organism/$a")) $moves["jbrowse/$kind/$a"] = $rel("$jb/$kind/$organism/$a");
        }
    } else {
        // Its built gene track and search index move WITH it (a restore needs no rebuild), and
        // so does its track JSON. Same volume, so these are renames too.
        foreach ($asm_names as $a) {
            $gs = $t['gene_set'];
            if (is_dir("$site_path/data/genomes/$organism/$a/$gs")) $moves["jbrowse/genomes/$a"] = "data/genomes/$organism/$a/$gs";
            $track = moop_gene_track_json_path($organism, $a, $gs);
            if (is_file($track)) $moves["jbrowse/track/$a.json"] = $rel($track);
        }
    }

    // JBrowse registrations to remove (organism, assembly) or the gene track to drop from one.
    $registered = [];
    $primary    = [];   // assembly JSON file => the trackId to drop from its primaryGeneTracks
    foreach ($asm_names as $a) {
        $asm_json = "$jb/assemblies/{$organism}_{$a}.json";
        if (!is_file($asm_json)) continue;
        if ($t['level'] !== 'gene_set') { $registered[] = (string)$a; continue; }
        $track_id = "{$organism}_{$a}_{$t['gene_set']}_genes";
        if (in_array($track_id, loadJsonFile($asm_json, [])['primaryGeneTracks'] ?? [], true)) $primary[$rel($asm_json)] = $track_id;
    }

    $groups_file = "$metadata/organism_assembly_groups.json";
    $entries = array_values(array_filter(loadJsonFile($groups_file, []) ?: [], fn($e) => moop_retire_entry_matches($e, $t, $asm_names)));

    $cache_dir = moop_cache_root() . '/' . moop_retire_rel($t);
    $data_dir  = "$organism_data/" . moop_retire_rel($t);
    return [
        'data_dir'      => $data_dir,
        'asm_names'     => $asm_names,
        'moves'         => $moves,
        'registered'    => $registered,
        'primary'       => $primary,
        'group_entries' => $entries,
        'groups_file'   => $groups_file,
        'cache_dir'     => is_dir($cache_dir) && realpath($cache_dir) !== realpath($data_dir) ? $cache_dir : null,
    ];
}

/** Does a groups-file entry belong to the target? */
function moop_retire_entry_matches(array $e, array $t, array $asm_names): bool {
    if (($e['organism'] ?? '') !== $t['organism']) return false;
    if ($t['level'] === 'organism') return true;
    if (!in_array($e['assembly'] ?? '', $asm_names, true)) return false;
    return $t['level'] === 'assembly' || ($e['gene_set'] ?? '') === $t['gene_set'];
}

/**
 * What retiring the target would do, without doing it. ['ok' => bool, 'error' => ?string,
 * 'steps' => list<string>, 'notes' => list<string>, 'target' => ?array, 'facts' => array].
 * The confirm dialog shows exactly this list.
 */
function moop_retire_preview(string $level, string $organism, string $assembly, string $gene_set, ConfigManager $config): array {
    $tg = moop_retire_target($level, $organism, $assembly, $gene_set, $config);
    if (!$tg['ok']) return ['ok' => false, 'error' => $tg['error'], 'steps' => [], 'notes' => [], 'target' => null, 'facts' => []];
    $t   = $tg['target'];
    $f   = moop_retire_facts($t, $config);
    $rel = moop_retire_rel($t);

    $what = ['organism' => 'its database, genome and gene sets', 'assembly' => 'its genome and gene sets', 'gene_set' => 'its sequences, GFF and BLAST indexes'][$level];
    $steps = ["Move organisms/$rel/ ($what) to organisms/" . MOOP_RETIRED_DIR . "/ — nothing is deleted"];
    $kinds = [];
    foreach (array_keys($f['moves']) as $k) $kinds[explode('/', $k)[1]] = true;
    if (isset($kinds['tracks'])) $steps[] = 'Keep its JBrowse track settings with it';
    if (isset($kinds['sheets'])) $steps[] = 'Keep its JBrowse sheet settings with it';
    if (isset($kinds['genomes']) || isset($kinds['track'])) $steps[] = 'Keep its genome-browser gene track and search index with it';
    foreach ($f['registered'] as $asm) $steps[] = "Unregister $asm from the genome browser";
    if ($f['primary']) $steps[] = 'Take its gene track off the genome browser\'s default tracks';
    if ($f['group_entries']) {
        $steps[] = 'Remove ' . count($f['group_entries']) . ' group entr' . (count($f['group_entries']) === 1 ? 'y' : 'ies')
                 . ' (saved, so Restore puts them back)';
    }
    if ($f['cache_dir']) $steps[] = 'Delete its cache (rebuilt automatically if restored)';
    $steps[] = 'Refresh the organism list';

    $notes = [];
    if ($level !== 'organism' && moop_retire_in_database($t, $config)) {
        $notes[] = "The organism's database still holds this " . ($level === 'assembly' ? 'assembly' : 'gene set')
                 . ' until the pipeline rebuilds it without it. Until then it is hidden from every page and search, '
                 . 'and the health checks list it as "retired, waiting for a database rebuild".';
    }

    $error = null;
    $parent = dirname($f['data_dir']);
    if (!is_writable($f['data_dir']) || !is_writable($parent)) {
        $error = "The web server cannot move organisms/$rel/ — check its permissions (Filesystem Permissions).";
    } elseif (!is_writable($f['groups_file'])) {
        $error = 'The web server cannot write organism_assembly_groups.json.';
    }
    return ['ok' => $error === null, 'error' => $error, 'steps' => $steps, 'notes' => $notes, 'target' => $t, 'facts' => $f];
}

/** Does the organism's database still hold this assembly / gene set? (Organism: always false.) */
function moop_retire_in_database(array $t, ConfigManager $config): bool {
    if ($t['level'] === 'organism') return false;
    $identity = moop_gene_set_identity([$t['organism']], $config->getPath('organism_data'))[$t['organism']] ?? [];
    $ids = [];
    foreach ($identity['genomes'] ?? [] as $g) {
        if (($g['genome_name'] ?? '') === $t['assembly'] || ($g['genome_accession'] ?? '') === $t['assembly']) $ids[] = (string)$g['genome_id'];
    }
    if ($t['level'] === 'assembly') return (bool)$ids;
    foreach ($identity['gene_sets'] ?? [] as $gs) {
        if (in_array((string)$gs['genome_id'], $ids, true) && $gs['gene_set_name'] === $t['gene_set']) return true;
    }
    return false;
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

/** mkdir + chmod: php-fpm's umask (022) would give 2755 and the admin could not delete a bundle without sudo. */
function moop_retire_mkdir(string $dir): bool {
    if (is_dir($dir)) return true;
    // One level at a time, so every directory created gets the mode — mkdir(…, true) applies
    // the umask to the parents it makes, and only the leaf would be chmodded.
    if (!moop_retire_mkdir(dirname($dir))) return false;
    if (!@mkdir($dir, 02775) && !is_dir($dir)) return false;
    @chmod($dir, 02775);
    return true;
}

/**
 * Retire an organism, assembly or gene set. Returns ['ok' => bool, 'error' => ?string,
 * 'done' => list<string>, 'bundle' => ?string]. The data move comes FIRST: it is the step most
 * likely to fail, and if it does nothing else has been touched.
 */
function moop_retire(string $level, string $organism, string $assembly, string $gene_set, string $username, ConfigManager $config): array {
    $preview = moop_retire_preview($level, $organism, $assembly, $gene_set, $config);
    if (!$preview['ok']) return ['ok' => false, 'error' => $preview['error'], 'done' => [], 'bundle' => null];
    $t = $preview['target'];
    $f = $preview['facts'];
    $rel       = moop_retire_rel($t);
    $site_path = $config->getPath('site_path');

    $root = moop_retired_root($config->getPath('organism_data'));
    if (!moop_retire_mkdir($root)) {
        return ['ok' => false, 'error' => 'Could not create organisms/' . MOOP_RETIRED_DIR . '/.', 'done' => [], 'bundle' => null];
    }
    $name   = date('Ymd-His') . '_' . str_replace('/', '__', $rel);
    $bundle = "$root/$name";
    if (file_exists($bundle) || !moop_retire_mkdir($bundle)) {
        return ['ok' => false, 'error' => "Could not create the bundle $name.", 'done' => [], 'bundle' => null];
    }

    $record = ['level' => $level, 'organism' => $t['organism'], 'assembly' => $t['assembly'], 'gene_set' => $t['gene_set'],
               'retired_at' => date('Y-m-d H:i:s'), 'retired_by' => $username,
               'status' => 'in_progress', 'steps' => [], 'moved' => [], 'unregistered' => [], 'primary_gene_tracks' => [],
               'group_entries' => [], 'in_database' => moop_retire_in_database($t, $config),
               'note' => 'Restore from Admin → Manage Organisms → Retired.'
                       . ($level === 'gene_set' ? '' : ' Then register its assemblies again in Manage JBrowse.')
                       . ($level === 'organism' ? '' : ' Until the pipeline rebuilds organism.sqlite without it, the database still holds its rows; the pipeline must leave it out, and copy2moop must not bring the directory back.')];
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
    if (!@rename($f['data_dir'], "$bundle/data")) {
        @unlink("$bundle/retired.json");
        @rmdir($bundle);
        return ['ok' => false, 'error' => "Could not move organisms/$rel/ — nothing was changed.", 'done' => [], 'bundle' => null];
    }
    $record['moved']['data'] = "organisms/$rel";
    $step("Moved organisms/$rel/ to organisms/" . MOOP_RETIRED_DIR . "/$name/");

    // 2. JBrowse settings (curation work) and, for a gene set, its built track: kept with the data.
    //    BEFORE unregistering — unregisterAssembly() deletes an assembly's sheet source.
    foreach ($f['moves'] as $in_bundle => $orig) {
        if (!moop_retire_mkdir(dirname("$bundle/$in_bundle")) || !@rename("$site_path/$orig", "$bundle/$in_bundle")) {
            return $fail("Could not move $orig into the bundle.");
        }
        $record['moved'][$in_bundle] = $orig;
        $step("Kept $orig");
    }

    // 3. Registrations: regenerable, so removed (Restore asks the admin to register again). A
    //    gene set instead comes off its assembly's default tracks — its track JSON is in the bundle.
    foreach ($f['registered'] as $asm) {
        $r = unregisterAssembly($t['organism'], $asm, $config);
        if (empty($r['success'])) return $fail("Could not unregister $asm: " . ($r['error'] ?? 'unknown error'));
        $record['unregistered'][] = $asm;
        $step("Unregistered $asm from the genome browser");
    }
    foreach ($f['primary'] as $asm_rel => $track_id) {
        $asm_json = "$site_path/$asm_rel";
        $data = loadJsonFile($asm_json, []);
        $data['primaryGeneTracks'] = array_values(array_diff($data['primaryGeneTracks'] ?? [], [$track_id]));
        if (file_put_contents($asm_json, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            return $fail("Could not update $asm_rel.");
        }
        $record['primary_gene_tracks'][$asm_rel] = $track_id;
        $step('Took its gene track off the default tracks');
    }

    // 4. Group entries: saved in the record first, then removed.
    if ($f['group_entries']) {
        $record['group_entries'] = $f['group_entries'];
        moop_retire_record($bundle, $record);
        $all  = loadJsonFile($f['groups_file'], []) ?: [];
        $kept = array_values(array_filter($all, fn($e) => !moop_retire_entry_matches($e, $t, $f['asm_names'])));
        if (file_put_contents($f['groups_file'], json_encode($kept, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            return $fail('Could not save organism_assembly_groups.json.');
        }
        $step('Removed ' . count($f['group_entries']) . ' group entr' . (count($f['group_entries']) === 1 ? 'y' : 'ies'));
    }

    // 5. Cache: regenerable.
    if ($f['cache_dir'] && rrmdir($f['cache_dir'])) $step('Deleted its cache');

    $record['status'] = 'retired';
    moop_retire_record($bundle, $record);
    moop_retire_log($config, "RETIRE by $username | " . ucfirst(str_replace('_', ' ', $level)) . ": $rel | Bundle: $name");
    return ['ok' => true, 'error' => null, 'done' => $done, 'bundle' => $name];
}

/**
 * The retired bundles, grouped by what was retired and newest first within each: name, record,
 * the command that deletes one for good, and the facts the list needs —
 *   exists_again  its directory is in organisms/ again (copied back the normal way, or another
 *                 bundle restored): Restore would refuse; only its settings can go back
 *   parent_gone   the organism / assembly it belonged to is not in organisms/ (retired itself):
 *                 restore that first
 *   in_database   assembly / gene set only: the database still holds its rows — waiting for a
 *                 pipeline rebuild without it
 *   newest        the latest of several bundles for one thing (they never overwrite each other)
 */
function moop_retired_list(ConfigManager $config): array {
    $organism_data = $config->getPath('organism_data');
    $root = moop_retired_root($organism_data);
    $out  = [];
    foreach (glob("$root/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $record = loadJsonFile("$dir/retired.json", []) ?: [];
        $record += ['level' => 'organism', 'assembly' => '', 'gene_set' => ''];
        $rel    = moop_retire_rel($record);
        $level  = (string)$record['level'];
        $out[] = ['bundle' => basename($dir), 'record' => $record, 'path' => $dir, 'organism' => (string)($record['organism'] ?? ''),
                  'level' => $level, 'rel' => $rel,
                  'exists_again' => $rel !== '' && is_dir("$organism_data/$rel"),
                  'parent_gone'  => $level !== 'organism' && $rel !== '' && !is_dir("$organism_data/" . dirname($rel)),
                  'in_database'  => $level !== 'organism' && is_dir("$dir/data") && ($record['organism'] ?? '') !== ''
                                    && moop_retire_in_database($record, $config),
                  'has_data' => is_dir("$dir/data"),
                  'delete_command' => 'sudo rm -rf ' . escapeshellarg($dir)];
    }
    usort($out, fn($a, $b) => strcmp($a['rel'], $b['rel']) ?: strcmp($b['bundle'], $a['bundle']));
    $count = array_count_values(array_column($out, 'rel'));
    $seen  = [];
    foreach ($out as &$b) {
        $b['siblings'] = $count[$b['rel']];
        $b['newest']   = !isset($seen[$b['rel']]);
        $seen[$b['rel']] = true;
    }
    unset($b);
    return $out;
}

/**
 * Put a retired organism, assembly or gene set back: data, JBrowse settings and files, group
 * entries, default-track entry. Refuses if its directory exists again, or if what it belonged to
 * is not there. The bundle directory is removed once it is empty.
 */
function moop_restore(string $bundle_name, string $username, ConfigManager $config, bool $settings_only = false): array {
    if (!moop_retire_valid_name($bundle_name)) return ['ok' => false, 'error' => 'No such retired bundle.', 'done' => []];
    $site_path     = $config->getPath('site_path');
    $organism_data = $config->getPath('organism_data');
    $bundle        = moop_retired_root($organism_data) . "/$bundle_name";
    $record        = (loadJsonFile("$bundle/retired.json", []) ?: []) + ['level' => 'organism', 'assembly' => '', 'gene_set' => ''];
    $level         = (string)$record['level'];
    $names_ok      = moop_retire_valid_name((string)($record['organism'] ?? ''))
                  && ($level === 'organism' || moop_retire_valid_name((string)$record['assembly']))
                  && ($level !== 'gene_set' || moop_retire_valid_name((string)$record['gene_set']));
    if (!is_dir($bundle) || !in_array($level, MOOP_RETIRE_LEVELS, true) || !$names_ok) {
        return ['ok' => false, 'error' => 'No such retired bundle.', 'done' => []];
    }
    $rel    = moop_retire_rel($record);
    $target = "$organism_data/$rel";
    if ($level !== 'organism' && !is_dir(dirname($target))) {
        return ['ok' => false, 'done' => [], 'error' => 'organisms/' . dirname($rel) . '/ is not there — restore it first.'];
    }
    // Settings only: it was copied back the normal way, so its data is already in place — put
    // back what the copy did not bring (groups, JBrowse settings) and leave the old data in the
    // bundle for the admin to delete. Only then; a full restore never merges into a copy.
    if ($settings_only !== file_exists($target)) {
        return ['ok' => false, 'done' => [], 'error' => $settings_only
            ? "organisms/$rel/ is not there — use Restore to bring everything back."
            : "organisms/$rel/ exists again — nothing was restored. Use \"Put back its groups and settings\" instead."];
    }

    $done = [];
    if (!$settings_only && is_dir("$bundle/data")) {
        if (!@rename("$bundle/data", $target)) return ['ok' => false, 'error' => "Could not move the data back to organisms/$rel/.", 'done' => []];
        $done[] = "Moved the data back to organisms/$rel/";
    }
    $put_back = [];
    foreach (($record['moved'] ?? []) as $what => $orig) {
        if ($what === 'data' || !file_exists("$bundle/$what")) continue;
        // A gene set's built track files are only worth putting back with its data.
        if ($settings_only && strpos($what, 'jbrowse/genomes/') === 0) continue;
        $dest = "$site_path/$orig";
        if (file_exists($dest)) { $done[] = "Left the old $orig in the bundle: it already exists"; continue; }
        if (moop_retire_mkdir(dirname($dest)) && @rename("$bundle/$what", $dest)) { $done[] = "Put back $orig"; $put_back[] = $what; }
        else $done[] = "Could not put back $orig — it is still in the bundle";
    }
    foreach (($record['primary_gene_tracks'] ?? []) as $asm_rel => $track_id) {
        $asm_json = "$site_path/$asm_rel";
        if (!is_file($asm_json)) continue;   // unregistered meanwhile: registering again re-adds it
        $data = loadJsonFile($asm_json, []);
        if (in_array($track_id, $data['primaryGeneTracks'] ?? [], true)) continue;
        $data['primaryGeneTracks'][] = $track_id;
        if (file_put_contents($asm_json, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
            $done[] = 'Put its gene track back on the default tracks';
        }
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
    $what = ['organism' => 'Organism', 'assembly' => 'Assembly', 'gene_set' => 'Gene set'][$level];
    if ($settings_only) {
        // The bundle keeps its old data (and its record, marked) until the admin deletes it.
        $record['settings_restored_at'] = date('Y-m-d H:i:s');
        $record['settings_restored_by'] = $username;
        $record['moved'] = array_diff_key($record['moved'] ?? [], array_flip($put_back));
        $record['group_entries'] = [];
        $record['primary_gene_tracks'] = [];
        moop_retire_record($bundle, $record);
        moop_retire_log($config, "RESTORE SETTINGS by $username | $what: $rel | Bundle: $bundle_name");
        $done[] = 'The old data stays in the bundle — delete it with the command shown once you are sure';
    } else {
        @unlink("$bundle/retired.json");
        moop_retire_rmdir_empty($bundle);
        moop_retire_log($config, "RESTORE by $username | $what: $rel | Bundle: $bundle_name");
    }
    if (!empty($record['unregistered'])) $done[] = 'Register its assemblies again in Manage JBrowse to bring back the genome browser';
    if ($level !== 'organism' && empty($record['in_database'])) {
        $done[] = 'The database did not hold it when it was retired — it shows on the site once the pipeline loads it';
    }
    return ['ok' => true, 'error' => null, 'done' => $done];
}

/** Remove a bundle's now-empty directories (bottom up); anything still holding a file stays. */
function moop_retire_rmdir_empty(string $dir): void {
    foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $sub) moop_retire_rmdir_empty($sub);
    @rmdir($dir);
}
