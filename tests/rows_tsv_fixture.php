<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
/**
 * For tests/js_smoke_tests.js: one set of awkward rows, printed as {json, tsv}.
 * json = what a page embeds for its browser download; tsv = what the API writes for the same rows
 * (moop_rows_to_tsv). The JS suite parses the json, runs moopRowsToTsv() on it and requires the
 * same bytes -- the browser's download and the API's file are one format, proven, not assumed.
 */
require_once __DIR__ . '/../lib/rows_tsv.php';

$rows = [
    ['pair' => 'p1', 'forward' => 'ACGT', 'start' => 1093812, 'tm' => 59.97, 'gc' => 50.0, 'penalty' => 0.1,
     'tiny' => 1.0e-7, 'small' => 0.000001, 'huge' => 1.5e21, 'big' => 1.0e15, 'neg' => -2.5, 'third' => 1 / 3,
     'empty' => '', 'none' => null, 'flag' => true, 'off' => false, 'zero' => 0, 'list' => ['a', 2]],
    ['pair' => "tab\there", 'forward' => "line\nbreak\r\nand cr", 'start' => -1, 'tm' => '60.0', 'gc' => 'x',
     'penalty' => 12345.678, 'tiny' => 'ünïcode 5′→3′', 'small' => 0.0001234, 'huge' => -0.0, 'big' => 1e21,
     'neg' => 123456789012345680000.0, 'third' => 2 / 3, 'empty' => ' lead and trail ', 'extra' => 'dropped'],
    // 'none' .. 'list' missing: empty cells, as in the browser
    ['pair' => 'p3', 'forward' => '', 'start' => 0, 'tm' => 1.0, 'gc' => 100, 'penalty' => 0.30000000000000004,
     'tiny' => 5e-324, 'small' => 1.7976931348623157e308, 'huge' => 0.5, 'big' => 10.0, 'neg' => -1e-7, 'third' => 7.0e20],
];
echo json_encode(['json' => json_encode($rows), 'tsv' => moop_rows_to_tsv($rows)]);
