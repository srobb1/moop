<?php
/**
 * Rows of named cells -> TSV, byte-for-byte what the browser's moopRowsToTsv()
 * (js/modules/utilities.js) writes for the same rows.
 *
 * Why the twin: the Primer Maker and Primer BLAST pages build their download in the browser from
 * rows the server embedded as JSON; the API (api/v1/primer_*.php) writes the SAME rows as TSV on
 * the server. A person and an agent asking the same question must get the same file, so the rules
 * live in two languages and tests/js_smoke_tests.js runs both on one set of rows and compares.
 *
 * The rules (both sides): header = the FIRST row's keys; a later row's missing key is an empty
 * cell and its extra keys are dropped; null is empty; tab, CR and LF inside a value become a
 * space; lines end "\n", including the last. A number is written the way JavaScript's String()
 * writes it after the page's JSON round trip (PHP alone would write 1.0 as "1" but 1e-7 as
 * "1.0E-7"); true/false as "true"/"false".
 */

function moop_rows_to_tsv(array $rows): string
{
    if (!$rows) return '';
    $rows   = array_values($rows);
    $header = array_map('strval', array_keys($rows[0]));
    $lines  = [implode("\t", $header)];
    foreach ($rows as $row) {
        $cells = [];
        foreach ($header as $key) {
            $cells[] = str_replace(["\t", "\r", "\n"], ' ', moop_js_cell_string($row[$key] ?? null));
        }
        $lines[] = implode("\t", $cells);
    }
    return implode("\n", $lines) . "\n";
}

/** One cell as JavaScript's String() writes the value JSON.parse() gave it. */
function moop_js_cell_string($v): string
{
    if ($v === null)  return '';
    if (is_bool($v))  return $v ? 'true' : 'false';
    if (is_int($v))   return (string) $v;
    if (is_float($v)) return moop_js_number_string($v);
    if (is_array($v)) return implode(',', array_map('moop_js_cell_string', $v));   // String([a, b]) = "a,b"
    return (string) $v;
}

/**
 * A float as JavaScript's Number#toString() writes it: the shortest digits that read back as the
 * same number, fixed notation from 1e-6 up to 1e21, exponent ("1e-7", "1.5e+21") outside that.
 */
function moop_js_number_string(float $f): string
{
    if (is_nan($f))      return 'NaN';
    if (is_infinite($f)) return $f > 0 ? 'Infinity' : '-Infinity';
    if ($f == 0.0)       return '0';   // -0 too: String(-0) is "0"

    // The shortest round-trip digits, as JSON writes them (serialize_precision -1 -- set here
    // rather than trusted, since a host can configure it otherwise).
    $old = ini_get('serialize_precision');
    ini_set('serialize_precision', '-1');
    $s = json_encode($f);
    ini_set('serialize_precision', $old);

    $neg = $s[0] === '-';
    if ($neg) $s = substr($s, 1);
    if (!preg_match('/^(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $s, $m)) return ($neg ? '-' : '') . $s;

    $all    = $m[1] . ($m[2] ?? '');
    $lead   = strlen($all) - strlen(ltrim($all, '0'));
    $digits = rtrim(ltrim($all, '0'), '0');           // the significant digits
    $k      = strlen($digits);
    $n      = strlen($m[1]) + (int) ($m[3] ?? 0) - $lead;   // value = 0.<digits> x 10^n

    // ECMAScript Number::toString, steps 6-10.
    if ($k <= $n && $n <= 21) {
        $out = $digits . str_repeat('0', $n - $k);
    } elseif (0 < $n && $n <= 21) {
        $out = substr($digits, 0, $n) . '.' . substr($digits, $n);
    } elseif (-6 < $n && $n <= 0) {
        $out = '0.' . str_repeat('0', -$n) . $digits;
    } else {
        $e   = $n - 1;
        $out = $digits[0] . ($k > 1 ? '.' . substr($digits, 1) : '') . 'e' . ($e >= 0 ? '+' : '-') . abs($e);
    }
    return ($neg ? '-' : '') . $out;
}
