<?php
/**
 * Move gene sets archived by the old Archive button into the Retired list.
 *
 * Until 2026-10-30, archiveGeneSet() moved a gene set to {site}/archived_gene_sets/{Org}/{Asm}/
 * {gene_set}_{YYYYMMDD_HHMMSS}/ — inside the docroot, with no nginx deny, so every file in it was
 * downloadable over HTTP (the directory was renamed to .archived_gene_sets the same day, which the
 * dot-path deny blocks). It kept no record: the group entries and track JSON were deleted.
 *
 * This turns each one into an organisms/.retired/ bundle (lib/retire.php, gene-set level) with a
 * retired.json saying what is known, so Manage Organisms → Retired lists it, can restore its
 * files, and shows the delete-for-good command. Same volume, so every move is a rename.
 *
 * Run as the web server's user — the archives are apache-owned, and moving a directory to a new
 * parent needs write permission on the directory itself:
 *
 *   sudo -u apache php scripts/adopt_archived_gene_sets.php            # dry run: says what it would do
 *   sudo -u apache php scripts/adopt_archived_gene_sets.php --apply
 */

$BASE = dirname(__DIR__);
require_once $BASE . '/includes/config_init.php';
require_once $BASE . '/lib/retire.php';

$config = ConfigManager::getInstance();
$apply  = in_array('--apply', $argv, true);
$site   = $config->getPath('site_path');
$root   = moop_retired_root($config->getPath('organism_data'));

// Who archived what, from the change log the old endpoint wrote.
$by = [];
foreach (@file($config->getPath('metadata_path') . '/change_log/manage_groups.log') ?: [] as $line) {
    if (preg_match('/ARCHIVE_GENE_SET by (\S+) \| .*Archived to: \S*\/([^\/\s]+)\/([^\/\s]+)\/([^\/\s]+) \|/', $line, $m)) {
        $by["$m[2]/$m[3]/$m[4]"] = $m[1];
    }
}

$failed = 0;
$found  = 0;
foreach (["$site/.archived_gene_sets", "$site/archived_gene_sets"] as $src) {
    foreach (glob("$src/*/*/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $found++;
        $asm = basename(dirname($dir));
        $org = basename(dirname($dir, 2));
        if (!preg_match('/^(.+)_(\d{8})_(\d{6})$/', basename($dir), $m)
            || !moop_retire_valid_name($org) || !moop_retire_valid_name($asm) || !moop_retire_valid_name($m[1])) {
            echo "SKIP  $dir — not named {gene_set}_{YYYYMMDD}_{HHMMSS}\n";
            $failed++;
            continue;
        }
        [, $gs, $date, $time] = $m;
        $bundle = "$root/$date-{$time}_{$org}__{$asm}__{$gs}";
        $when   = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' '
                . substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':' . substr($time, 4, 2);
        echo ($apply ? 'MOVE  ' : 'WOULD ') . "$org / $asm / $gs  ($when)  ->  organisms/" . MOOP_RETIRED_DIR . '/' . basename($bundle) . "/data\n";
        if (!$apply) continue;

        if (file_exists($bundle) || !moop_retire_mkdir($root) || !moop_retire_mkdir($bundle)) {
            echo "  FAILED: could not create the bundle\n";
            $failed++;
            continue;
        }
        if (!@rename($dir, "$bundle/data")) {
            @rmdir($bundle);
            echo "  FAILED: could not move it (run as the web server's user: sudo -u apache …)\n";
            $failed++;
            continue;
        }
        $record = ['level' => 'gene_set', 'organism' => $org, 'assembly' => $asm, 'gene_set' => $gs,
                   'retired_at' => $when, 'retired_by' => $by["$org/$asm/" . basename($dir)] ?? 'unknown',
                   'status' => 'retired', 'steps' => ["Archived with the old Archive button; moved here " . date('Y-m-d') . ' by scripts/adopt_archived_gene_sets.php'],
                   'moved' => ['data' => "organisms/$org/$asm/$gs"], 'unregistered' => [], 'primary_gene_tracks' => [],
                   'group_entries' => [], 'in_database' => false,
                   'note' => 'Archived before Retire existed: its group entries and JBrowse track were deleted at the time, not saved. '
                           . 'Restore brings back its files only; it was not in the database when archived.'];
        if (!moop_retire_record($bundle, $record)) {
            echo "  WARNING: moved, but retired.json could not be written — the bundle will list without details\n";
            $failed++;
        }
    }
    if ($apply && is_dir($src)) {
        moop_retire_rmdir_empty($src);   // only the now-empty Org/Asm skeleton
        if (is_dir($src)) echo "Left $src: something is still in it\n";
    }
}
echo $found ? '' : "Nothing to adopt.\n";
if (!$apply && $found) echo "\nDry run. Re-run with --apply to move them.\n";
exit($failed ? 1 : 0);
