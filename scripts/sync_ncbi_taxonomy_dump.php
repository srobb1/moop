#!/usr/bin/env php
<?php
/**
 * Sync NCBI taxonomy dump and populate taxonomy_lineage_cache.json.
 *
 * Keeps metadata/ncbi_rankedlineage.dmp.gz locally so future runs (and the
 * refresh_lineage_cache fallback path) never need to re-download.
 *
 * Run order:
 *   1. Fetch the 50-byte MD5 from NCBI (cURL, 5 s timeout).
 *   2. If MD5 matches stored → skip download; if different (or no local dump)
 *      → download ~60 MB, extract rankedlineage.dmp, compress and keep it.
 *   3. Scan local dump for any taxon IDs not yet in taxonomy_lineage_cache.json.
 *   4. Write updated lineage cache.
 *
 * Usage:
 *   php scripts/sync_ncbi_taxonomy_dump.php          # smart: skip download if up to date
 *   php scripts/sync_ncbi_taxonomy_dump.php --force  # re-download and re-process everything
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$base_dir = dirname(__DIR__);
require_once "$base_dir/includes/config_init.php";
require_once "$base_dir/lib/functions_data.php";

$force = in_array('--force', $argv);

$config        = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');

$stored_gz   = "$metadata_path/ncbi_rankedlineage.dmp.gz";
$dump_url    = 'https://ftp.ncbi.nlm.nih.gov/pub/taxonomy/new_taxdump/new_taxdump.tar.gz';
$md5_url     = $dump_url . '.md5';

// --- Collect taxon IDs from all organism.json files ---
$taxon_map = []; // tid => ['common_name' => ...]
foreach (glob("$organism_data/*/organism.json") ?: [] as $json_file) {
    $d = json_decode(@file_get_contents($json_file), true);
    if (!empty($d['taxon_id'])) {
        $taxon_map[(string)$d['taxon_id']] = ['common_name' => $d['common_name'] ?? ''];
    }
}

if (empty($taxon_map)) {
    echo "No taxon IDs found in organism.json files — nothing to do.\n";
    exit(0);
}
echo "Organisms with taxon IDs: " . count($taxon_map) . "\n";

// --- Decide whether to (re)download ---
$meta           = ncbi_load_local_dump_meta($metadata_path);
$need_download  = $force || !file_exists($stored_gz);
$remote_md5     = null;

if (!$need_download) {
    echo "Checking NCBI dump for updates...\n";
    $remote_md5 = ncbi_fetch_remote_md5($md5_url);
    if ($remote_md5 === null) {
        echo "  Warning: MD5 check failed (NCBI unreachable?) — using local dump.\n";
    } else {
        $meta['last_checked'] = date('Y-m-d H:i:s');
        ncbi_save_local_dump_meta($metadata_path, $meta);
        if ($remote_md5 !== ($meta['md5'] ?? '')) {
            echo "  Dump changed on NCBI (stored: " . ($meta['md5'] ?? 'none') . ", remote: $remote_md5) — re-downloading.\n";
            $need_download = true;
        } else {
            echo "  Dump is current (MD5 matches).\n";
        }
    }
}

// --- (Re)download if needed ---
if ($need_download) {
    $tmp_dir  = sys_get_temp_dir() . '/moop_ncbi_' . getmypid();
    if (!@mkdir($tmp_dir, 0700, true)) {
        echo "ERROR: Cannot create temp directory $tmp_dir\n";
        exit(1);
    }

    $tar_file = "$tmp_dir/new_taxdump.tar.gz";
    $fp       = fopen($tar_file, 'wb');
    if (!$fp) {
        echo "ERROR: Cannot write to $tar_file\n";
        @rmdir($tmp_dir);
        exit(1);
    }

    echo "Downloading NCBI taxonomy dump (~60 MB)...\n";

    $ch = curl_init($dump_url);
    curl_setopt_array($ch, [
        CURLOPT_FILE             => $fp,
        CURLOPT_FOLLOWLOCATION   => true,
        CURLOPT_CONNECTTIMEOUT   => 30,
        CURLOPT_TIMEOUT          => 600,
        CURLOPT_USERAGENT        => 'MOOP/1.0 Taxonomy Sync (NCBI eutils)',
        CURLOPT_NOPROGRESS       => false,
        CURLOPT_PROGRESSFUNCTION => function($ch, $dl_total, $dl_now, $ul_total, $ul_now) {
            static $last_pct = -1;
            if ($dl_total <= 0) return;
            $pct = (int)($dl_now / $dl_total * 100);
            if ($pct !== $last_pct && $pct % 10 === 0) {
                echo sprintf("  %d / %d MB (%d%%)\n",
                    (int)($dl_now / 1048576), (int)($dl_total / 1048576), $pct);
                $last_pct = $pct;
            }
        },
    ]);
    $ok       = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $dl_md5   = curl_getinfo($ch, CURLINFO_CONTENT_MD5); // may be empty
    curl_close($ch);
    fclose($fp);

    if (!$ok || $curl_err) {
        echo "ERROR: Download failed: $curl_err\n";
        exec('rm -rf ' . escapeshellarg($tmp_dir));
        exit(1);
    }
    if ($http !== 200) {
        echo "ERROR: HTTP $http from NCBI\n";
        exec('rm -rf ' . escapeshellarg($tmp_dir));
        exit(1);
    }
    echo "Downloaded " . round(filesize($tar_file) / 1048576, 1) . " MB\n";

    // Extract rankedlineage.dmp
    echo "Extracting rankedlineage.dmp...\n";
    exec('tar xzf ' . escapeshellarg($tar_file) . ' -C ' . escapeshellarg($tmp_dir)
        . ' rankedlineage.dmp 2>&1', $out, $code);
    if ($code !== 0 || !file_exists("$tmp_dir/rankedlineage.dmp")) {
        echo "ERROR: Extraction failed\n" . implode("\n", $out) . "\n";
        exec('rm -rf ' . escapeshellarg($tmp_dir));
        exit(1);
    }
    unlink($tar_file); // tar.gz no longer needed

    $lineage_file = "$tmp_dir/rankedlineage.dmp";
    echo "rankedlineage.dmp: " . round(filesize($lineage_file) / 1048576, 1) . " MB\n";

    // Compress and save to metadata/
    echo "Compressing and saving to $stored_gz...\n";
    $in  = fopen($lineage_file, 'rb');
    $out_gz = gzopen($stored_gz . '.tmp', 'wb6');
    while (!feof($in)) {
        gzwrite($out_gz, fread($in, 65536));
    }
    fclose($in);
    gzclose($out_gz);
    rename($stored_gz . '.tmp', $stored_gz);
    @chmod($stored_gz, 0664);

    // Compute and store local MD5 of the tar.gz content — use the remote MD5 if we fetched it,
    // otherwise compute from the downloaded file (already deleted; use stored gz as proxy).
    $stored_md5 = $remote_md5 ?? md5_file($lineage_file);
    unlink($lineage_file);
    @rmdir($tmp_dir);

    $meta = [
        'md5'          => $stored_md5,
        'downloaded'   => date('Y-m-d H:i:s'),
        'last_checked' => date('Y-m-d H:i:s'),
    ];
    ncbi_save_local_dump_meta($metadata_path, $meta);
    echo "Saved " . round(filesize($stored_gz) / 1048576, 1) . " MB compressed dump.\n";
}

// --- Determine which taxon IDs still need lineage data ---
$lineage_cache = $force ? [] : load_lineage_cache($metadata_path);

if ($force) {
    // In force mode, wipe only dump-sourced entries so API-sourced ones survive
    $full_cache = load_lineage_cache($metadata_path);
    foreach ($full_cache as $tid => $entry) {
        if (($entry['source'] ?? '') !== 'ncbi_dump') {
            $lineage_cache[$tid] = $entry;
        }
    }
}

$need_lineage = [];
foreach ($taxon_map as $tid => $info) {
    if (!isset($lineage_cache[$tid])) {
        $need_lineage[$tid] = $info;
    }
}

if (empty($need_lineage) && !$force) {
    echo "All " . count($taxon_map) . " organisms already have lineage data — nothing to update.\n";
    echo "Done.\n";
    exit(0);
}

if ($force) {
    $need_lineage = $taxon_map; // re-process all
    echo "Force mode: re-processing all " . count($need_lineage) . " organisms.\n";
} else {
    echo count($need_lineage) . " organism(s) need lineage data.\n";
}

// --- Scan local compressed dump ---
echo "Scanning local dump for " . count($need_lineage) . " taxon ID(s)...\n";
$found  = [];
$needed = $need_lineage;

$gz = gzopen($stored_gz, 'r');
if (!$gz) {
    echo "ERROR: Cannot open $stored_gz\n";
    exit(1);
}
while (!empty($needed) && ($line = gzgets($gz)) !== false) {
    $tab = strpos($line, "\t");
    if ($tab === false) continue;
    $tid = substr($line, 0, $tab);
    if (!isset($needed[$tid])) continue;

    $stripped = rtrim($line, "\r\n");
    if (substr($stripped, -2) === "\t|") $stripped = substr($stripped, 0, -2);
    $found[$tid] = explode("\t|\t", $stripped);
    unset($needed[$tid]);
}
gzclose($gz);

echo "Found " . count($found) . " of " . count($need_lineage) . " in dump.\n";
if (!empty($needed)) {
    echo "Not found (will fall back to NCBI API on next cache warm): " . implode(', ', array_keys($needed)) . "\n";
}

// --- Build and write lineage entries ---
$rank_fields = [
    'superkingdom' => 9, 'kingdom' => 8, 'phylum' => 7,
    'class'        => 6, 'order'   => 5, 'family' => 4,
    'genus'        => 3, 'species' => 1,
];

$updated = 0;
foreach ($found as $tid => $parts) {
    $lineage = [];
    foreach ($rank_fields as $rank => $idx) {
        $name = isset($parts[$idx]) ? trim($parts[$idx]) : '';
        if ($name !== '') $lineage[] = ['rank' => $rank, 'name' => $name];
    }
    if (empty($lineage)) { echo "  SKIP [$tid]: no usable lineage data\n"; continue; }

    $lineage_cache[$tid] = [
        'lineage' => $lineage,
        'image'   => $lineage_cache[$tid]['image'] ?? null,
        'fetched' => date('Y-m-d'),
        'source'  => 'ncbi_dump',
    ];
    $label = $need_lineage[$tid]['common_name'] ?: $tid;
    echo "  ✓ [$tid] $label\n";
    $updated++;
}

if (save_lineage_cache($lineage_cache, $metadata_path)) {
    echo "\nSaved $updated lineage entr" . ($updated === 1 ? 'y' : 'ies') . ".\n";
} else {
    echo "\nERROR: Could not write taxonomy_lineage_cache.json — check permissions.\n";
    exit(1);
}

echo "Done.\n";
