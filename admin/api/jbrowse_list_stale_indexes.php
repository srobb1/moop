<?php
/**
 * JBrowse Admin API: list gene tracks whose bgzip/tabix index is out of date.
 *
 * Checked LIVE, not read from the housekeeping cache. The dashboard is a router --
 * "N issues, go look" -- and the manager page it routes to re-checks, so an admin who
 * has just fixed something is not told it is still broken for up to an hour.
 * housekeeping_check_jbrowse_indexes() applies exactly the same rule for the cached
 * count; if these two ever disagree, this one is right.
 *
 * WHY THERE IS ANYTHING TO LIST. data/genomes/{org}/{asm}/{gs}/annotations.gff3 is a
 * symlink into organisms/, so it follows every gene-set reload automatically -- while
 * annotations.gff3.gz, the file JBrowse actually reads, is a snapshot that only changes
 * on an explicit re-prep. Nothing connected the two, so a reload staled the browser track
 * and reported nothing.
 *
 * Returns the work list only. The caller re-preps one gene set at a time via
 * jbrowse_reprep_gff.php, which keeps progress honest and keeps a 100 MB+ bgzip out of
 * a single request that would time out partway and leave no record of how far it got.
 *
 * GET/POST: no parameters.
 */

require_once __DIR__ . '/../../admin/admin_init.php';

header('Content-Type: application/json');

$config    = ConfigManager::getInstance();
$site_path = $config->getPath('site_path');
$base      = "$site_path/data/genomes";

if (!is_dir($base)) {
    echo json_encode([
        'success' => true,
        'total'   => 0,
        'stale'   => [],
        'note'    => 'No data/genomes directory — no assemblies registered with JBrowse yet.',
    ]);
    exit;
}

$total = 0;
$stale = [];

foreach (glob("$base/*/*/*/annotations.gff3") as $gff) {
    // glob() matches the symlink itself; file_exists() follows it. A dangling link means
    // the gene set is gone, not stale, so there is nothing to rebuild.
    if (!file_exists($gff)) {
        continue;
    }
    $total++;

    $gz  = "$gff.gz";
    $tbi = "$gz.tbi";
    $csi = "$gz.csi";

    $why = null;
    if (!file_exists($gz)) {
        $why = 'no compressed GFF';
    } elseif (filemtime($gz) < filemtime($gff)) {
        $why = 'compressed GFF older than source';
    } elseif (!file_exists($tbi) && !file_exists($csi)) {
        $why = 'no tabix index';
    } else {
        $index = file_exists($tbi) ? $tbi : $csi;
        if (filemtime($index) < filemtime($gz)) {
            $why = 'tabix index older than compressed GFF';
        }
    }

    if ($why === null) {
        continue;
    }

    $parts = explode('/', substr($gff, strlen($base) + 1));
    if (count($parts) < 3) {
        continue;
    }

    $stale[] = [
        'organism'   => $parts[0],
        'assembly'   => $parts[1],
        'gene_set'   => $parts[2],
        'why'        => $why,
        'gff_date'   => date('Y-m-d H:i', filemtime($gff)),
        'index_date' => file_exists($gz) ? date('Y-m-d H:i', filemtime($gz)) : null,
    ];
}

// Oldest index first: those are the ones most likely to be serving IDs the database no
// longer has, which is the failure that actually breaks gene-page links into JBrowse.
usort($stale, function ($a, $b) {
    return strcmp((string)$a['index_date'], (string)$b['index_date']);
});

echo json_encode([
    'success' => true,
    'total'   => $total,
    'stale'   => $stale,
]);
