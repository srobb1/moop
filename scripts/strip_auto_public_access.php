<?php
/**
 * Remove the PUBLIC access fields that JBrowse registration wrote automatically.
 *
 * Until 2026-10-20 registering an assembly wrote `defaultAccessLevel: PUBLIC` into its JSON,
 * and preparing a gene set's track wrote `metadata.access_level: PUBLIC` into the gene track --
 * not a decision anyone made. Access to both is now decided by the site's rules (Manage
 * Groups; api/jbrowse2/config.php), the code no longer writes the fields, and nothing reads
 * them -- but they still SAID "PUBLIC" in the files, and the admin JBrowse page showed it.
 *
 * Touches only those two fields: assembly JSONs under jbrowse2-configs/assemblies/, and gene
 * tracks (metadata.gene_set set) under tracks/{organism}/{assembly}/gff/. Other tracks'
 * access_level is curated in the track sheets and is left alone.
 *
 * A file is rewritten only if re-encoding its UNCHANGED content reproduces it byte-for-byte,
 * so the removed line is the only difference; any other file is reported and left alone.
 *
 * The files are owned by the web server, so run it as that user:
 *     sudo -u apache php scripts/strip_auto_public_access.php            # dry run
 *     sudo -u apache php scripts/strip_auto_public_access.php --apply
 * Safe to re-run: files without the field are counted as already clean.
 */
$BASE = dirname(__DIR__);
require_once $BASE . '/includes/config_init.php';

$apply = in_array('--apply', $argv, true);
$dir   = ConfigManager::getInstance()->getPath('metadata_path') . '/jbrowse2-configs';
$enc   = fn($data) => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$jobs = [];
foreach (glob("$dir/assemblies/*.json") ?: [] as $file)            $jobs[] = [$file, 'assembly'];
foreach (glob("$dir/tracks/*/*/gff/*_genes.json") ?: [] as $file) $jobs[] = [$file, 'gene track'];

$count = ['removed' => 0, 'already clean' => 0, 'not a gene track' => 0, 'left alone' => 0, 'failed' => 0];
foreach ($jobs as [$file, $kind]) {
    $raw  = (string)file_get_contents($file);
    $data = json_decode($raw, true);
    $rel  = substr($file, strlen($dir) + 1);
    if (!is_array($data)) { echo "  unreadable JSON, left alone: $rel\n"; $count['left alone']++; continue; }

    if ($kind === 'gene track' && (string)($data['metadata']['gene_set'] ?? '') === '') { $count['not a gene track']++; continue; }
    $has = $kind === 'assembly'
        ? array_key_exists('defaultAccessLevel', $data)
        : array_key_exists('access_level', $data['metadata'] ?? []);
    if (!$has) { $count['already clean']++; continue; }

    if (rtrim($enc($data)) !== rtrim($raw)) {
        echo "  formatted differently, left alone (edit by hand): $rel\n";
        $count['left alone']++;
        continue;
    }

    if ($kind === 'assembly') unset($data['defaultAccessLevel']);
    else                      unset($data['metadata']['access_level']);

    if ($apply) {
        $out = $enc($data) . (substr($raw, -1) === "\n" ? "\n" : '');
        if (@file_put_contents($file, $out) === false) {
            echo "  COULD NOT WRITE (run as the web server user?): $rel\n";
            $count['failed']++;
            continue;
        }
    }
    $count['removed']++;
}

echo ($apply ? 'Applied: ' : 'Dry run (nothing written; add --apply): ');
echo implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($count), $count)) . "\n";
exit($count['failed'] > 0 ? 1 : 0);
