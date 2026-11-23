<?php
/**
 * Download DB-IP's free IP-to-region file and rebuild {cache}/geoip/dbip-region.sqlite now.
 * Housekeeping does this monthly on its own (task geoip_update); run this to get it straight away.
 *
 *   php scripts/update_geoip.php            (as smr or apache; takes a minute or two)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/geoip.php';

$r = moop_geoip_update(fn($m) => fwrite(STDERR, "$m\n"));
if (!$r['ok']) { fwrite(STDERR, 'FAILED: ' . $r['error'] . "\n"); exit(1); }
echo "Built " . moop_geoip_db_file() . " -- {$r['ranges']} ranges from {$r['source']}\n";
foreach (['8.8.8.8', '132.66.42.126', '172.16.2.52'] as $ip) {
    echo "  $ip -> " . (moop_geoip_label(moop_geoip_lookup($ip)) ?: '(unknown)') . "\n";
}
