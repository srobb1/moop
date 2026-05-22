#!/usr/bin/env php
<?php
/**
 * Download NCBI new_taxdump and populate taxonomy_lineage_cache.json
 * with lineage data for all MOOP organisms.
 *
 * Eliminates per-organism NCBI API calls during cache warm — after running
 * this once, warm_organism_cache.php makes no network calls for taxonomy.
 *
 * Usage:
 *   php scripts/sync_ncbi_taxonomy_dump.php
 *
 * Run again whenever new organisms are added to MOOP.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$base_dir = dirname(__DIR__);
require_once "$base_dir/includes/config_init.php";
require_once "$base_dir/lib/functions_data.php";

$config        = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');

// --- Collect taxon IDs from all organism.json files ---
$taxon_map = []; // tid => ['common_name' => ...]
foreach (glob("$organism_data/*/organism.json") ?: [] as $json_file) {
    $d = json_decode(@file_get_contents($json_file), true);
    if (!empty($d['taxon_id'])) {
        $taxon_map[(string)$d['taxon_id']] = [
            'common_name' => $d['common_name'] ?? '',
        ];
    }
}

if (empty($taxon_map)) {
    echo "No taxon IDs found in organism.json files — nothing to do.\n";
    exit(0);
}

echo "Organisms with taxon IDs: " . count($taxon_map) . "\n";

// --- Download new_taxdump.tar.gz ---
$dump_url = 'https://ftp.ncbi.nlm.nih.gov/pub/taxonomy/new_taxdump/new_taxdump.tar.gz';
$tmp_dir  = sys_get_temp_dir() . '/moop_ncbi_' . getmypid();

if (!@mkdir($tmp_dir, 0700, true)) {
    echo "ERROR: Cannot create temp directory $tmp_dir\n";
    exit(1);
}

$tar_file = "$tmp_dir/new_taxdump.tar.gz";
$fp = fopen($tar_file, 'wb');
if (!$fp) {
    echo "ERROR: Cannot write to $tar_file\n";
    @rmdir($tmp_dir);
    exit(1);
}

echo "Downloading NCBI taxonomy dump (~60 MB)...\n";
echo "  From: $dump_url\n";

$ch = curl_init($dump_url);
curl_setopt_array($ch, [
    CURLOPT_FILE            => $fp,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_CONNECTTIMEOUT  => 30,
    CURLOPT_TIMEOUT         => 600,
    CURLOPT_USERAGENT       => 'MOOP/1.0 Taxonomy Sync (NCBI eutils)',
    CURLOPT_NOPROGRESS      => false,
    CURLOPT_PROGRESSFUNCTION => function($ch, $dl_total, $dl_now, $ul_total, $ul_now) {
        static $last_pct = -1;
        if ($dl_total <= 0) return;
        $pct = (int)($dl_now / $dl_total * 100);
        if ($pct !== $last_pct && $pct % 10 === 0) {
            echo sprintf("  %d / %d MB (%d%%)\n",
                (int)($dl_now / 1048576),
                (int)($dl_total / 1048576),
                $pct);
            $last_pct = $pct;
        }
    },
]);

$ok       = curl_exec($ch);
$curl_err = curl_error($ch);
$http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

$size_mb = round(filesize($tar_file) / 1048576, 1);
echo "Downloaded {$size_mb} MB\n";

// --- Extract rankedlineage.dmp only (no need to unpack the entire archive) ---
echo "Extracting rankedlineage.dmp...\n";
exec('tar xzf ' . escapeshellarg($tar_file) . ' -C ' . escapeshellarg($tmp_dir)
    . ' rankedlineage.dmp 2>&1', $out, $code);

if ($code !== 0 || !file_exists("$tmp_dir/rankedlineage.dmp")) {
    echo "ERROR: Extraction failed\n";
    if (!empty($out)) echo implode("\n", $out) . "\n";
    exec('rm -rf ' . escapeshellarg($tmp_dir));
    exit(1);
}

// Free the downloaded tar immediately — rankedlineage.dmp is all we need
unlink($tar_file);

$lineage_file  = "$tmp_dir/rankedlineage.dmp";
$lineage_mb    = round(filesize($lineage_file) / 1048576, 1);
echo "rankedlineage.dmp: {$lineage_mb} MB — scanning for " . count($taxon_map) . " taxon IDs...\n";

// --- Stream through rankedlineage.dmp line by line ---
// Format (tab-pipe separated):
//   tax_id \t|\t name \t|\t species \t|\t genus \t|\t family \t|\t order \t|\t class \t|\t phylum \t|\t kingdom \t|\t superkingdom \t|
// Field indices: 0=tid 1=name 2=species 3=genus 4=family 5=order 6=class 7=phylum 8=kingdom 9=superkingdom
$found  = []; // tid => parsed parts array
$needed = $taxon_map; // shrinks as matches are found (early exit)

$fh = fopen($lineage_file, 'r');
while (!empty($needed) && ($line = fgets($fh)) !== false) {
    // Fast pre-check: extract just the tax_id before splitting the line
    $tab = strpos($line, "\t");
    if ($tab === false) continue;
    $tid = substr($line, 0, $tab);
    if (!isset($needed[$tid])) continue;

    // Full parse: strip trailing "\t|\n" then split on "\t|\t"
    $stripped = rtrim($line, "\r\n");
    if (substr($stripped, -2) === "\t|") {
        $stripped = substr($stripped, 0, -2);
    }
    $found[$tid] = explode("\t|\t", $stripped);
    unset($needed[$tid]);
}
fclose($fh);

// Clean up extracted file
unlink($lineage_file);
@rmdir($tmp_dir);

$found_count   = count($found);
$missing_count = count($taxon_map) - $found_count;
echo "Found {$found_count} of " . count($taxon_map) . " taxon IDs in dump.\n";
if ($missing_count > 0) {
    $missing = array_keys($needed); // $needed now only contains unmatched IDs
    echo "Not found in dump (will fall back to NCBI API): " . implode(', ', $missing) . "\n";
}

// --- Build lineage entries in taxonomy_lineage_cache format ---
// Ordered broadest→narrowest: superkingdom, kingdom, phylum, class, order, family, genus, species
$rank_fields = [
    'superkingdom' => 9,
    'kingdom'      => 8,
    'phylum'       => 7,
    'class'        => 6,
    'order'        => 5,
    'family'       => 4,
    'genus'        => 3,
    'species'      => 1, // tax_name = canonical name for this taxon
];

$lineage_cache = load_lineage_cache($metadata_path);
$updated = 0;

foreach ($found as $tid => $parts) {
    $lineage = [];
    foreach ($rank_fields as $rank => $idx) {
        $name = isset($parts[$idx]) ? trim($parts[$idx]) : '';
        if ($name !== '') {
            $lineage[] = ['rank' => $rank, 'name' => $name];
        }
    }

    if (empty($lineage)) {
        echo "  SKIP [$tid]: no usable lineage data\n";
        continue;
    }

    $existing_image         = $lineage_cache[$tid]['image'] ?? null;
    $lineage_cache[$tid]    = [
        'lineage' => $lineage,
        'image'   => $existing_image,
        'fetched' => date('Y-m-d'),
        'source'  => 'ncbi_dump',
    ];

    $label = $taxon_map[$tid]['common_name'] ?: $tid;
    echo "  ✓ [$tid] $label\n";
    $updated++;
}

// --- Save updated cache ---
if (save_lineage_cache($lineage_cache, $metadata_path)) {
    echo "\nSaved $updated lineage entr" . ($updated === 1 ? 'y' : 'ies') . " to taxonomy_lineage_cache.json\n";
} else {
    echo "\nERROR: Could not write taxonomy_lineage_cache.json — check permissions.\n";
    exit(1);
}

echo "Done.\n";
